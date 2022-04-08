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

require_once( $CFG->dirroot .'/mod/quiz/locallib.php' );
require_once( $CFG->dirroot .'/mod/quiz/report/reportlib.php' );
require_once( $CFG->dirroot .'/mod/quiz/report/default.php' );
require_once( $CFG->dirroot .'/mod/quiz/report/attemptsreport.php' );

require_once( $CFG->dirroot .'/question/type/questionbase.php' );

use moodle_url;
use quiz_attempt;
use user_picture;
use action_link;
use question_display_options;
use stdClass;
use html_writer;
use context_course;

class create_archive extends \quiz_attempts_report
{
    public function display($quiz, $cm, $course)
    {
        global $OUTPUT;

        $this->options = new options('', $quiz, $cm, $course);

        list( $currentgroup, $students, $groupstudents, $allowed ) =
            $this->init('', '\report_quizarchive\form', $quiz, $cm, $course);

        if( $fromform = $this->form->get_data() ){
            $this->options->process_settings_from_form( $fromform );
        } else {
            $this->options->process_settings_from_params();
        }
        
        $this->form->set_data($this->options->get_initial_form_data());

        $questions = quiz_report_get_significant_questions( $quiz );

        $table = new table( $quiz, $this->context, $this->qmsubselect, $this->options, $groupstudents, $students, $questions, $this->options->get_url() );
        
        $this->process_actions($quiz, $cm, $currentgroup, $groupstudents, $allowed, $this->options->get_url());

        if( $groupmode = groups_get_activity_groupmode($cm) ){
            groups_print_activity_menu( $cm, $this->options->get_url() );
        }

        $hasquestions = quiz_has_questions( $quiz->id );
        if( !$hasquestions ){
            echo quiz_no_questions_message( $quiz, $cm, $this->context );
        } else if( !$students ){
            echo $OUTPUT->notification( get_string('nostudentsyet') );
        } else if( $currentgroup && !$groupstudents ){
            echo $OUTPUT->notification( get_string('nostudentsingroup') );
        }

        $this->form->display();

        $hasstudents = $students && ( !$currentgroup || $groupstudents );
        if ($hasquestions && ( $hasstudents || $this->options->attempts == self::ALL_WITH ) ){
            list($fields, $from, $where, $params) = $table->base_sql($allowed);

            $table->set_sql($fields, $from, $where, $params);

            $columns = array();
            $headers = array();

            $columnname = 'checkbox';
            $columns[] = $columnname;
            $headers[] = $table->checkbox_col_header($columnname);

            $this->add_user_columns($table, $columns, $headers);

            $this->add_state_column($columns, $headers);

            $this->add_time_columns($columns, $headers);

            $this->add_grade_columns($quiz, $this->options->usercanseegrades, $columns, $headers, false);

            $this->set_up_table_columns($table, $columns, $headers, $this->get_base_url(), $this->options, false);

            $table->out( $this->options->pagesize, true );
        }
    }
    
    protected function get_base_url()
    {
        return new moodle_url( '/report/quizarchive/create.php', array( 'id' => $this->options->course->id, 'modid' => $this->options->quiz->id ) );
    }

    protected function process_actions($quiz, $cm, $currentgroup, $groupstudents, $allowed, $redirecturl)
    {
        global $DB, $USER, $OUTPUT;

        if( empty( $currentgroup ) || $groupstudents ){
            if( optional_param( 'createarchive', 0, PARAM_BOOL ) && confirm_sesskey() ){
                raise_memory_limit(MEMORY_HUGE);
                set_time_limit(600);
                if( $attemptids = optional_param_array( 'attemptid', array(), PARAM_INT ) ){

                    $courseid = optional_param( 'id', 0, PARAM_INT );
                    $quizid = optional_param( 'modid', 0, PARAM_INT );
                    $archivename = optional_param( 'archivename', '', PARAM_TEXT );

                    if( empty( $archivename ) ){
                        $archivename = $quiz->name .' - '. date('Ymd-His');
                    }

                    $params = array(
                        'userid' => $USER->id,
                        'courseid' => $quiz->course,
                        'quizid' => $quiz->id,
                        'name' => $archivename,
                        'timecreated' => time()
                    );

                    $quizarchiveid = $DB->insert_record( 'report_quizarchive', $params, true );

                    $conditions = array( 'id' => $quiz->id );
                    $count_quiz = $DB->count_records( 'report_quizarchive_qu', $conditions );

                    if( !$count_quiz ){
                        $params = array(
                            $quiz->id,
                            $quiz->name,
                            $quiz->decimalpoints,
                            $quiz->grade,
                            $quiz->attempts,
                            time()
                        );

                        $sql = "INSERT INTO {report_quizarchive_qu}(id,name,decimalpoints,grade,attempts,timecreated) VALUES(?,?,?,?,?,?)";

                        $quizinsert = $DB->execute( $sql, $params );
                    }

                    foreach ($attemptids as $attemptid) {
                        $conditions = array( 'id' => $attemptid );
                        $count_attempt = $DB->count_records( 'report_quizarchive_at', $conditions );

                        if( $count_attempt ){
                            $sql = "INSERT INTO {report_quizarchive_join}(quizarchiveid,quizarchive_atid) VALUES(?,?)";
                            $params = array( $quizarchiveid, $attemptid );
                            $join = $DB->execute( $sql, $params );
                            
                            continue;
                        }

                        $attemptobj = quiz_attempt::create($attemptid);

                        $attempt = $attemptobj->get_attempt();

                        $params = array(
                            $attempt->id,
                            $attempt->userid,
                            $attempt->attempt,
                            $attempt->state,
                            $attempt->timestart,
                            $attempt->timefinish,
                            $attempt->sumgrades,
                            time()
                        );

                        ob_start();
                        echo $this->get_attempt_html( $attemptobj, $quizarchiveid );
                        $output = ob_get_contents();
                        ob_end_clean();

                        $filecontent = $output;

                        $sql = "INSERT INTO {report_quizarchive_at}(id,userid,attempt,state,timestart,timefinish,sumgrades,timecreated) VALUES(?,?,?,?,?,?,?,?)";

                        $attemptinsert = $DB->execute( $sql, $params );

                        $context = context_course::instance( $cm->course );

                        $content = preg_match_all('#<section (.*?)>(.*?)<\/section>#si', $filecontent, $matches);
                        $content = $matches[0][0];

                        $content = preg_replace('#<div class="[^"]*?questionflag[^"]*?">(.*?)<\/div>#si', '', $content);
                        $content = preg_replace('#<div class="[^"]*?editquestion[^"]*?">(.*?)<\/div>#si', '', $content);
                        $content = preg_replace('#<div class="[^"]*?commentlink[^"]*?">(.*?)<\/div>#si', '', $content);
                        $content = preg_replace('#<div class="[^"]*?comment[^"]*?">(.*?)<\/div>#si', '', $content);
                        $content = preg_replace('#<div class="[^"]*?submitbtns[^"]*?">(.*?)<\/div>#si', '', $content);

                        $content = preg_replace('#<a (.*?)user(.*?)>(.*?)<\/a>#si', '{student:'. $attempt->userid .'}', $content);
                        $content = preg_replace('#<a (.*?)reviewquestion(.*?)>(.*?)<\/a>#si', '$3', $content);

                        $content = htmlspecialchars( $content );
                        $content = gzcompress( serialize( $content ), 9 );

                        $fs = get_file_storage();

                        $fileinfo = array(
                            'contextid' => $context->id,
                            'component' => 'report_quizarchive',
                            'filearea' => 'report_quizarchive',
                            'itemid' => $attempt->id,
                            'filepath' => '/',
                            'filename' => 'attempt-'. $attempt->id
                        );

                        $file = $fs->get_file(
                            $fileinfo['contextid'],
                            $fileinfo['component'],
                            $fileinfo['filearea'], 
                            $fileinfo['itemid'],
                            $fileinfo['filepath'],
                            $fileinfo['filename']
                        );

                        if( $file ) $file->delete();

                        $fs->create_file_from_string($fileinfo, $content);

                        $sql = "INSERT INTO {report_quizarchive_join}(quizarchiveid,quizarchive_atid) VALUES(?,?)";
                        $params = array( $quizarchiveid, $attemptid );
                        $join = $DB->execute( $sql, $params );
                    }

                    $redir = new moodle_url( '/report/quizarchive/show.php', array( 'id' => $quizarchiveid ) );
                    redirect($redir);
                    exit;
                }
            }
        }
    }

    public function get_attempt_html( $attemptobj, $quizarchiveid )
    {
        global $PAGE;

        $slots = $attemptobj->get_slots();
        
        $options = $attemptobj->get_display_options(true);

        $this->setup_new_page();

        $url = new moodle_url('/report/quizarchive/view.php', array( 'id' => $quizarchiveid, 'attemptid' => $attemptobj->get_attemptid()));
        $PAGE->set_url($url);

        $summarydata = $this->summary_table($attemptobj, $options);

        $PAGE->set_pagelayout('embedded');

        $output = $PAGE->get_renderer('mod_quiz');
        $content = $output->review_page( $attemptobj, $slots, 0, true, true, $options, $summarydata );

        return $content;
    }

    protected function summary_table($attemptobj, $options)
    {
        global $USER, $DB;

        $attempt = $attemptobj->get_attempt();
        $quiz = $attemptobj->get_quiz();
        $overtime = 0;

        if( $attempt->state == quiz_attempt::FINISHED ){
            if ($timetaken = ($attempt->timefinish - $attempt->timestart)) {
                if ($quiz->timelimit && $timetaken > ($quiz->timelimit + 60)) {
                    $overtime = $timetaken - $quiz->timelimit;
                    $overtime = format_time($overtime);
                }
                $timetaken = format_time($timetaken);
            } else {
                $timetaken = "-";
            }
        } else {
            $timetaken = get_string('unfinished', 'quiz');
        }

        $summarydata = array();
        if( !$attemptobj->get_quiz()->showuserpicture && $attemptobj->get_userid() != $USER->id ){
            $student = $DB->get_record( 'user', array( 'id' => $attemptobj->get_userid() ) );
            $usrepicture = new user_picture( $student );
            $usrepicture->courseid = $attemptobj->get_courseid();
            $summarydata['user'] = array(
                'title' => $usrepicture,
                'content' => new action_link(new moodle_url('/user/view.php', array(
                    'id' => $student->id, 'course' => $attemptobj->get_courseid())),
                    fullname( $student, true )),
            );
        }

        $summarydata['startedon'] = array(
            'title' => get_string('startedon', 'quiz'),
            'content' => userdate($attempt->timestart),
        );

        $summarydata['state'] = array(
            'title' => get_string('attemptstate', 'quiz'),
            'content' => quiz_attempt::state_name($attempt->state),
        );

        if( $attempt->state == quiz_attempt::FINISHED ){
            $summarydata['completedon'] = array(
                'title' => get_string('completedon', 'quiz'),
                'content' => userdate($attempt->timefinish),
            );
            $summarydata['timetaken'] = array(
                'title' => get_string('timetaken', 'quiz'),
                'content' => $timetaken,
            );
        }

        if( !empty($overtime) ){
            $summarydata['overdue'] = array(
                'title' => get_string('overdue', 'quiz'),
                'content' => $overtime,
            );
        }

        $grade = quiz_rescale_grade($attempt->sumgrades, $quiz, false);
        if( $options->marks >= question_display_options::MARK_AND_MAX && quiz_has_grades($quiz) ){

            if ($attempt->state != quiz_attempt::FINISHED) {

            } else if ( is_null( $grade ) ){
                $summarydata['grade'] = array(
                    'title' => get_string('grade', 'quiz'),
                    'content' => quiz_format_grade($quiz, $grade),
                );
            } else {
                if( $quiz->grade != $quiz->sumgrades ){
                    $a = new stdClass();
                    $a->grade = quiz_format_grade($quiz, $attempt->sumgrades);
                    $a->maxgrade = quiz_format_grade($quiz, $quiz->sumgrades);
                    $summarydata['marks'] = array(
                        'title' => get_string('marks', 'quiz'),
                        'content' => get_string('outofshort', 'quiz', $a),
                    );
                }

                $a = new stdClass();
                $a->grade = html_writer::tag('b', quiz_format_grade($quiz, $grade));
                $a->maxgrade = quiz_format_grade($quiz, $quiz->grade);
                if ($quiz->grade != 100) {
                    $a->percent = html_writer::tag('b', format_float(
                        $attempt->sumgrades * 100 / $quiz->sumgrades, 0));
                    $formattedgrade = get_string('outofpercent', 'quiz', $a);
                } else {
                    $formattedgrade = get_string('outof', 'quiz', $a);
                }
                $summarydata['grade'] = array(
                    'title' => get_string('grade', 'quiz'),
                    'content' => $formattedgrade,
                );
            }
        }

        $feedback = $attemptobj->get_overall_feedback($grade);
        if ($options->overallfeedback && $feedback) {
            $summarydata['feedback'] = array(
                'title' => get_string('feedback', 'quiz'),
                'content' => $feedback,
            );
        }

        return $summarydata;
    }

    protected function setup_new_page()
    {
        global $CFG, $PAGE;

        if( !empty( $CFG->moodlepageclass  ) ){
            if( !empty( $CFG->moodlepageclassfile ) ){
                require_once( $CFG->moodlepageclassfile );
            }
            $classname = $CFG->moodlepageclass;
        } else {
            $classname = 'moodle_page';
        }
        $PAGE = new $classname();
        unset($classname);

        $PAGE->set_context(null);
    }

}
