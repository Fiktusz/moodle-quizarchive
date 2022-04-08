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

$quizarchive = new \report_quizarchive\archive();

$id = required_param( 'id', PARAM_INT );
$attemptid = optional_param('attemptid', 0, PARAM_INT);

$archive = $quizarchive->get_archive( $id );

$base_url = new moodle_url( '/report/quizarchive/index.php', array( 'id' => $archive->courseid ) );
$url = new moodle_url( '/report/quizarchive/show.php', array( 'id' => $id ) );

$PAGE->set_url( $url );
$PAGE->set_pagelayout('report');

$PAGE->navbar->add( get_string('reports'), new moodle_url( 'report/view.php', array('courseid' => $archive->courseid ) ) );
$PAGE->navbar->add( get_string('pluginname', 'report_quizarchive'), $base_url );
$PAGE->navbar->add( $archive->name, $url );

$course = $DB->get_record( 'course', array('id' => $archive->courseid), '*', MUST_EXIST );

require_login( $course );
$context = context_course::instance( $course->id );
require_capability('mod/quiz:viewreports', $context);

$title = $course->fullname .': '. get_string('pluginname', 'report_quizarchive') .' - '. $archive->name;

if( $attemptid ){
    $attemp = $quizarchive->get_attempt_details( $attemptid );
    $PAGE->navbar->add( $attemp->fullname, $url );
    $title .= ' - '. $attemp->fullname;
}

$PAGE->set_title( $title );
$PAGE->set_heading( $title );

echo $OUTPUT->header();

if( !$attemptid ){
    echo '<form action="index.php" method="get">';
        echo '<div>';
            echo '<input type="hidden" name="id" value="'.$course->id.'" />';
            echo '<input type="submit" value="'. get_string('back', 'report_quizarchive') .'" class="btn btn-secondary"/>';
        echo '</div>';
    echo '</form>';

    $quizarchive->get_table_attempts( $archive );
} else {
    echo $quizarchive->get_attempt( $id, $attemptid );
}

echo $OUTPUT->footer();