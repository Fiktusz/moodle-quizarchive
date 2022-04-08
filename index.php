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

use core\report_helper;

$id = optional_param( 'id', 0, PARAM_INT );
$modid = optional_param( 'modid', 0, PARAM_INT );

$quizarchiveid = optional_param( 'quizarchiveid', 0, PARAM_INT );
$download = optional_param( 'download', 0, PARAM_INT );
$remove = optional_param( 'remove', 0, PARAM_INT );

if( !$id ){
    $redir = new moodle_url('/index.php');
    redirect( $redir );
    exit;
}

$course = $DB->get_record( 'course', array( 'id' => $id ), '*', MUST_EXIST );

require_login( $course );
$context = context_course::instance( $course->id );
require_capability('mod/quiz:viewreports', $context);

$params = array();
if( $id ) $params['id'] = $id;
if( $modid ) $params['modid'] = $modid;

$url = new moodle_url( '/report/quizarchive/index.php', $params );

$PAGE->set_url( '/report/quizarchive/index.php', array( 'id' => $id ) );
$PAGE->set_pagelayout('report');

$PAGE->set_title( $course->shortname .': '. get_string( 'pluginname', 'report_quizarchive' ) );
$PAGE->set_heading( $course->shortname .': '. get_string( 'pluginname', 'report_quizarchive' ) );

report_helper::save_selected_report( $id, $url );

$quizarchive = new \report_quizarchive\archive();

if( $quizarchiveid && $remove && confirm_sesskey() )
{
    $quizarchive->removeArchive( $quizarchiveid );

    $redir = new moodle_url( '/report/quizarchive/index.php', array( 'id' => $id ) );
    redirect($redir);
}

if( $quizarchiveid && $download && confirm_sesskey() )
{
    $quizarchive->downloadArchive( $quizarchiveid );

    $redir = new moodle_url( '/report/quizarchive/index.php', array( 'id' => $id ) );
    redirect($redir);
}

$archives = $quizarchive->get_archives( $id );

echo $OUTPUT->header();

report_helper::print_report_selector( get_string( 'pluginname', 'report_quizarchive' ) );

echo '<form action="create.php" method="get">';
        echo '<input type="hidden" name="id" value="'.$course->id.'" />';
        echo '<input type="submit" value="'. get_string('create_new_archive', 'report_quizarchive') .'" class="btn btn-primary"/>';
echo '</form>';

if( !count( $archives ) ){
    echo '<hr/><h2>'. get_string('archive_empty', 'report_quizarchive') .'</h2>';
    echo $OUTPUT->footer();
    exit;
}

echo '<hr/><h2>'. get_string('archive_table_name', 'report_quizarchive') .'</h2>';

$quizarchive->get_table( $archives );

echo $OUTPUT->footer();