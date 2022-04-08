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

defined('MOODLE_INTERNAL') || die;


function report_quizarchive_extend_navigation_course($navigation, $course, $context)
{
    if( has_capability( 'mod/quiz:viewreports', $context ) AND $navigation->key == 'coursereports' ){
        $url = new moodle_url('/report/quizarchive/index.php', array( 'id' => $course->id ));
        $navigation->add( get_string('navigation_reports', 'report_quizarchive'), $url, navigation_node::TYPE_ACTIVITY, null, null, new pix_icon('i/report', ''));
    }
}

function report_quizarchive_extend_navigation_module($navigation, $cm)
{
    $context = context_module::instance( $cm->id );

    if( $cm->modname == 'quiz' && has_capability( 'mod/quiz:viewreports', $context ) ){
        $url = new moodle_url('/report/quizarchive/create.php', array( 'id' => $cm->course, 'modid' => $cm->instance ));
        $navigation->add( get_string('navigation_activitymenu', 'report_quizarchive'), $url, navigation_node::TYPE_ACTIVITY, null, null, new pix_icon('box-archive', 'box-archive', 'report_quizarchive'));
    }
}