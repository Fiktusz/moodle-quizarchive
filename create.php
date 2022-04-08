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

require( '../../config.php' );

$id = optional_param( 'id', 0, PARAM_INT );
$modid = optional_param( 'modid', 0, PARAM_INT );

$form_cancel = optional_param( 'cancel', 0, PARAM_TEXT );

if( $form_cancel ){
    $redir = new moodle_url( '/report/quizarchive/index.php', array( 'id' => $id ) );
    redirect( $redir );
    exit;
}

if( !$id ){
    $redir = new moodle_url('/index.php');
    redirect( $redir );
    exit;
}

$course = $DB->get_record( 'course', array('id' => $id), '*', MUST_EXIST );

require_login( $course );
$context = context_course::instance( $course->id );
require_capability('mod/quiz:viewreports', $context);

$base_url = new moodle_url( '/report/quizarchive/index.php', array( 'id' => $id ) );
$url = new moodle_url( '/report/quizarchive/create.php', array( 'id' => $id ) );

$PAGE->set_url( $url );
$PAGE->set_pagelayout('report');

$PAGE->navbar->add( get_string('reports'), new moodle_url( '/report/view.php', array('courseid' => $id ) ) );
$PAGE->navbar->add( get_string('pluginname', 'report_quizarchive'), $base_url );
$PAGE->navbar->add( get_string('create_new_archive', 'report_quizarchive'), $url );

$quizarchive_create = new \report_quizarchive\create_archive();

$form_selectquiz = new \report_quizarchive\form_selectquiz();
$form_selectquiz->set_data( [ 'id' => $course->id ] );

$modinfo = get_fast_modinfo($course);

$quizzes = array();
foreach( $modinfo->instances['quiz'] as $quiz ){
    if( !quiz_has_attempts( $quiz->instance ) ) continue;

    $quizzes[ $quiz->instance ] = $quiz->name;
}

$form_selectquiz->setSelectValues( 'modid', $quizzes );

if( $data = $form_selectquiz->get_data() ){
    if( isset( $data->quizselect ) ){
        $redir = new moodle_url( '/report/quizarchive/create.php', array( 'id' => $id, 'modid' => $data->modid ) );
        redirect($redir);
    } else {
        $redir = new moodle_url( '/report/quizarchive/index.php', array( 'id' => $id ) );
        redirect( $redir );
    }
}

$PAGE->set_title( $course->fullname .': '. get_string( 'pluginname', 'report_quizarchive' ) .' - '. get_string('create_new_archive', 'report_quizarchive') );
$PAGE->set_heading( $course->fullname .': '. get_string( 'pluginname', 'report_quizarchive' ) .' - '. get_string('create_new_archive', 'report_quizarchive') );

if( !$modid ){
    echo $OUTPUT->header();

    $form_selectquiz->display();

    echo $OUTPUT->footer();
    exit;
}

$form_selectquiz->set_data( [ 'modid' => $modid ] );

if (!$quiz = $DB->get_record('quiz', array('id' => $modid))) {
    print_error('invalidquizid', 'quiz');
}
if (!$course = $DB->get_record('course', array('id' => $quiz->course))) {
    print_error('invalidcourseid');
}
if (!$cm = get_coursemodule_from_instance("quiz", $quiz->id, $course->id)) {
    print_error('invalidcoursemodule');
}

$url = new moodle_url( '/report/quizarchive/create.php', array( 'id' => $id, 'modid' => $modid ) );
$PAGE->navbar->add( $quiz->name, $url );

if( ! (optional_param( 'createarchive', 0, PARAM_BOOL ) AND optional_param_array( 'attemptid', array(), PARAM_INT ) ) )
{
    echo $OUTPUT->header();

    $form_selectquiz->display();
}

$quizarchive_create->display($quiz, $cm, $course);

echo $OUTPUT->footer();

exit;