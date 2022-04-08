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

require_once($CFG->dirroot . '/mod/quiz/report/attemptsreport_table.php');

use html_writer;
use moodle_url;
use quiz_attempt;

class table extends \quiz_attempts_report_table
{

    public function __construct($quiz, $context, $qmsubselect, options $options, $groupstudents, $students, $questions, $reporturl)
    {
        parent::__construct( 'report-quizarchive', $quiz, $context, $qmsubselect, $options, $groupstudents, $students, $questions, $reporturl );
    }

    public function build_table()
    {
        $this->strtimeformat = str_replace(',', ' ', get_string('strftimedatetime'));
        parent::build_table();
    }
    

    public function col_sumgrades( $attempt )
    {
        if( $attempt->state != quiz_attempt::FINISHED ){
            return '-';
        }

        $grade = quiz_rescale_grade( $attempt->sumgrades, $this->quiz );
        
        if( isset( $this->regradedqs[$attempt->usageid] ) ){
            $newsumgrade = 0;
            $oldsumgrade = 0;
            foreach( $this->questions as $question ){
                if( isset( $this->regradedqs[$attempt->usageid][$question->slot] ) ){
                    $newsumgrade += $this->regradedqs[$attempt->usageid]
                            [$question->slot]->newfraction * $question->maxmark;
                    $oldsumgrade += $this->regradedqs[$attempt->usageid]
                            [$question->slot]->oldfraction * $question->maxmark;
                } else {
                    $newsumgrade += $this->lateststeps[$attempt->usageid]
                            [$question->slot]->fraction * $question->maxmark;
                    $oldsumgrade += $this->lateststeps[$attempt->usageid]
                            [$question->slot]->fraction * $question->maxmark;
                }
            }
            $newsumgrade = quiz_rescale_grade($newsumgrade, $this->quiz);
            $oldsumgrade = quiz_rescale_grade($oldsumgrade, $this->quiz);
            $grade = html_writer::tag('del', $oldsumgrade) . '/' .
                    html_writer::empty_tag('br') . $newsumgrade;
        }
        return $grade;
    }
    
    protected function submit_buttons()
    {
        global $PAGE;

        echo '<div style="text-align: center; margin: 24px 0 0 0;">';
            echo '<input id="quizarchive_create_button" name="createarchive" type="submit" value="'. get_string('create_archive', 'report_quizarchive') .'" class="btn btn-primary"/>';
            echo '<input style="margin-left: 12px;" name="cancel" type="submit" value="'. get_string('cancel', 'report_quizarchive') .'" class="btn btn-secondary"/>';
        echo '</div>';
        
        $PAGE->requires->event_handler(
            '#quizarchive_create_button',
            'click',
            'M.util.show_confirm_dialog',
            array(
                'message' => get_string('dialog_create', 'report_quizarchive'),
                'report_quizarchive')
            );
    }
    
}
