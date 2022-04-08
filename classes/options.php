<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * 
 *
 * @package    report
 * @subpackage quizarchive
 * @copyright  2022 Arpad Kabat
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_quizarchive;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/report/attemptsreport_options.php');

use moodle_url;

class options extends \mod_quiz_attempts_report_options
{
    public $archivename = '';

    protected function get_url_params()
    {
        $params = parent::get_url_params();
        $params['archivename'] = $this->archivename;

        $params['id'] = $this->course->id;
        $params['modid'] = $this->quiz->id;

        return $params;
    }

    public function get_initial_form_data()
    {
        $toform = parent::get_initial_form_data();
        $toform->archivename = $this->archivename;
        return $toform;
    }

    public function setup_from_form_data($fromform)
    {
        parent::setup_from_form_data($fromform);
        $this->archivename = $fromform->archivename;
    }

    public function setup_from_params()
    {
        parent::setup_from_params();
        $this->archivename = optional_param('archivename', '', PARAM_TEXT);
    }

    public function resolve_dependencies()
    {
        $this->checkboxcolumn = true;
    }

    public function get_url() {

        $params = $this->get_url_params();
        if( isset( $params['mode'] ) ) unset( $params['mode'] );

        return new moodle_url('/report/quizarchive/create.php', $params);
    }
}
