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

require_once($CFG->dirroot . '/mod/quiz/report/attemptsreport_form.php');

class form extends \mod_quiz_attempts_report_form
{
    protected function definition(){
        $mform = $this->_form;

        $mform->addElement('header', 'generalheader', get_string('general', 'report_quizarchive'));
        $mform->setExpanded('generalheader', true);

        $generalarray = array(
            $mform->createElement('text', 'archivename', get_string('archivename', 'report_quizarchive')),
            $mform->createElement('submit', 'generalsubmit', get_string('set_name', 'report_quizarchive'))
        );
        $mform->addGroup($generalarray, 'generalarr', get_string('archivename', 'report_quizarchive'), [' '], false);
        $mform->addHelpButton('generalarr', 'archivename', 'report_quizarchive');

        $mform->setType('archivename', PARAM_TEXT);
        
        $mform->addElement('header', 'preferencespage', get_string('reportwhattoinclude', 'quiz'));
        $mform->setExpanded('preferencespage', true);

        $this->standard_attempt_fields($mform);
        $this->other_attempt_fields($mform);

        $this->standard_preference_fields($mform);
        $this->other_preference_fields($mform);

        $mform->addElement('submit', 'filterbutton', get_string('show_by_filter', 'report_quizarchive'));
    }

    protected function other_preference_fields(\MoodleQuickForm $mform)
    {   
        
    }
}