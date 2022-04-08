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

use moodle_url;
use html_writer;
use context_course;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once( $CFG->libdir .'/formslib.php' );


class archive {

    public function get_archives( $id )
    {
        global $DB, $USER;

        $sql = "
            SELECT
                qa.id,
                qa.name AS 'name',
                qa.courseid,
                qa.timecreated,
                qu.name AS 'quizname',
                qu.decimalpoints,
                qu.grade,
                qu.attempts,
                COUNT(qj.id) AS 'attempts_count'
            FROM {report_quizarchive} qa
                JOIN {report_quizarchive_qu} qu ON qa.quizid = qu.id
                LEFT JOIN {report_quizarchive_join} qj ON qa.id = qj.quizarchiveid
            WHERE qa.userid = ? AND qa.courseid = ? AND qa.deleted = ? AND qu.deleted = ?
            GROUP BY qa.name, qa.timecreated
        ";
        
        $params = array( $USER->id, $id, 0, 0 );

        $archives = $DB->get_records_sql( $sql, $params );
        
        return $archives;
    }

    public function get_archive( $id )
    {
        global $DB, $USER;

        $sql = "
            SELECT
                qa.id,
                qa.courseid,
                qa.name AS 'name',
                qa.timecreated,
                qu.id AS 'quizid',
                qu.name AS 'quizname',
                qu.decimalpoints,
                qu.grade,
                qu.attempts AS 'max_attempts'
            FROM {report_quizarchive} qa
                JOIN {report_quizarchive_qu} qu ON qa.quizid = qu.id
            WHERE qa.id = ? AND qa.userid = ? AND qa.deleted = ? AND qu.deleted = ?
        ";
        
        $params = array( $id, $USER->id, 0, 0 );

        $archive = $DB->get_record_sql( $sql, $params );
        
        if( !$archive ) return $archive;

        $context = \context_system::instance();
        $userfields = \core_user\fields::for_name()->with_identity($context)->including('email')->excluding('id');

        $userfields = $userfields->get_sql('u');

        $sql = "
            SELECT
                qa.id,
                qa.attempt,
                qa.state,
                qa.timestart,
                qa.timefinish,
                (qa.timefinish-qa.timestart) AS 'taken',
                qa.sumgrades,
                qa.userid,
                IF(u.deleted, CONCAT('id: ', qa.userid), CONCAT(u.firstname, ' ', u.lastname) ) AS 'fullname'
                ". $userfields->selects ."
            FROM {report_quizarchive_at} qa
                JOIN {user} u ON qa.userid = u.id
                JOIN {report_quizarchive_join} qjoin ON qa.id = qjoin.quizarchive_atid 
            WHERE qjoin.quizarchiveid = ? AND qa.deleted = ?
        ";

        $params = array( $archive->id, 0 );

        $archive->attempts = $DB->get_records_sql( $sql, $params );

        return $archive;
    }

    public function get_attempt_details( $id )
    {
        global $DB;

        $context = \context_system::instance();
        $userfields = \core_user\fields::for_name()->with_identity($context)->excluding('id');

        $userfields = $userfields->get_sql('u');

        $sql = "
            SELECT
                qa.id,
                qa.attempt,
                qa.state,
                qa.timestart,
                qa.timefinish,
                (qa.timefinish-qa.timestart) AS 'taken',
                qa.sumgrades,
                qa.userid,
                IF(u.deleted, CONCAT('id: ', qa.userid), CONCAT(u.firstname, ' ', u.lastname) ) AS 'fullname'
                ". $userfields->selects ."
            FROM {report_quizarchive_at} qa
                JOIN {user} u ON qa.userid = u.id
            WHERE qa.id = ? AND qa.deleted = ?
        ";

        $params = array( $id, 0 );

        $attempt = $DB->get_record_sql( $sql, $params );

        return $attempt;
    }

    public function get_attempt( $quizarchiveid, $id, $export = false )
    {
        global $DB;

        $sql = "
            SELECT
                qa.id,
                qa.courseid
            FROM {report_quizarchive} qa
                JOIN {report_quizarchive_join} qjoin ON qa.id = qjoin.quizarchiveid
            WHERE qjoin.quizarchive_atid = ? AND qa.id = ?
        ";

        $attempt = $DB->get_record_sql( $sql, array( $id, $quizarchiveid ) );
        
        $course = $DB->get_record( 'course', array('id' => $attempt->courseid), '*', MUST_EXIST );
        $context = context_course::instance( $course->id );
        
        $fs = get_file_storage();

        $fileinfo = array(
            'component' => 'report_quizarchive',
            'filearea' => 'report_quizarchive',
            'itemid' => $id,
            'contextid' => $context->id,
            'filepath' => '/',
            'filename' => 'attempt-'. $id
        );

        $file = $fs->get_file(
            $fileinfo['contextid'],
            $fileinfo['component'],
            $fileinfo['filearea'],
            $fileinfo['itemid'],
            $fileinfo['filepath'],
            $fileinfo['filename']
        );
                
        if( $file ){
            $content = $file->get_content();
            $content = gzuncompress( $content );

            $f = 1;
            for( $i = 0; $i < strlen( $content ); $i++ ){
                if( $content[ $i ] == '"' ) break;
                
                $f++;
            }

            $content = substr( $content, $f, strlen( $content ) );
            $content = substr( $content, 0, -2 );

            $content = $this->update_attempt_content( $content, $quizarchiveid, $id, $export );

            return $content;
        }
    }

    private function update_attempt_content( $content, $quizarchiveid, $id, $export = false )
    {
        global $DB;

        $table_sort = optional_param('table_sort', 'fullname', PARAM_TEXT);
        $table_ascdesc = optional_param('table_ascdesc', 1, PARAM_INT);
        
        $url_attributes = array(
            'id' => $quizarchiveid,
            'attemptid' => $id,
            'table_sort' => $table_sort,
            'table_ascdesc' => $table_ascdesc
        );

        $content = preg_replace('#{student:(.*?)}#si', get_string('full_name', 'report_quizarchive'), $content, 1);

        preg_match('#{student:(.*?)}#si', $content, $matches);

        if( !isset( $matches[1] ) ) return $content;

        $userid = $matches[1];

        $context = \context_system::instance();
        $userfields = \core_user\fields::for_name()->with_identity($context)->excluding('id', 'deleted');
        $userfields = $userfields->get_sql('u');

        $sql = "
            SELECT
                u.id
                ". $userfields->selects ."
            FROM {user} u
            WHERE u.id = ? AND u.deleted = ?
        ";

        $params = array( $matches[1], 0 );
        $user = $DB->get_record_sql( $sql, $params );

        if( !$user ){
            $content = preg_replace('#{student:(.*?)}#si', get_string('student', 'report_quizarchive') .', id: $1', $content);
        } else {
            $content = preg_replace('#{student:(.*?)}#si', $user->firstname .' '. $user->lastname, $content);
        }

        $sql = "
            SELECT
                qa.id,
                qa.attempt
            FROM {report_quizarchive_at} qa
                JOIN {report_quizarchive_join} qjoin ON qa.id = qjoin.quizarchive_atid 
            WHERE qjoin.quizarchiveid = ? AND qa.deleted = ? AND qa.userid = ?
            ORDER BY
                qa.attempt ASC
        ";
        $params = array( $quizarchiveid, 0, $userid );
        $user_attempts = $DB->get_records_sql( $sql, $params );

        if( count( $user_attempts ) > 1 ){
            $content_before = '<p>';
            $content_before .= get_string('attempts', 'report_quizarchive') .':';
            foreach( $user_attempts as $user_attempt ){

                $link_params = $url_attributes;
                $link_params['attemptid'] = $user_attempt->id;

                if( $export ) {
                    $link = '<a href="attempt-'. $user_attempt->id .'.html">'. $user_attempt->attempt .'</a>';
                } else {
                    $link = html_writer::link( new moodle_url('/report/quizarchive/show.php', $link_params), $user_attempt->attempt);
                }

                $content_before .= ( substr( $content_before, -1 ) == ':' ) ? ' ' : ', ';

                $content_before .= ( $user_attempt->id == $id ) ? '<strong>'. $link .'</strong>' : $link;
            }

            $content_before .= '</p>';

            $content = $content_before . $content;
        }

        $content_before = '';

        $action = ( $export ) ? 'index.html' : 'show.php';

        $content_before .= '<form action="'. $action .'" method="get">';
            $content_before .= '<div>';
                if( !$export ){
                    foreach( $url_attributes as $key => $value ){
                        if( $key == 'attemptid' ) continue;

                        $content_before .= '<input type="hidden" name="'. $key .'" value="'. $value .'" />';
                    }
                }
                $content_before .= '<input type="submit" value="'. get_string('back', 'report_quizarchive') .'" class="btn btn-secondary"/>';
            $content_before .= '</div>';
        $content_before .= '</form><br class="clearer"/>';

        $content = htmlspecialchars_decode( $content_before . $content );

        if( $export ){
            $correct_answer = '<span style="color: green; padding: 0 5px;">'. get_string('correct', 'report_quizarchive') .'</span>';
            $wrong_answer = '<span style="color: red; padding: 0 5px;">'. get_string('wrong', 'report_quizarchive') .'</span>';

            $content = preg_replace('#<i class="icon fa fa-check(.*?)<\/i>#si', $correct_answer, $content );
            $content = preg_replace('#<i class="icon fa fa-remove(.*?)<\/i>#si', $wrong_answer, $content );
        }

        return $content;
    }

    public function removeArchive( $quizarchiveid )
    {
        global $DB;

        $DB->update_record('report_quizarchive', array( 'id' => $quizarchiveid, 'deleted' => 1 ));
    }

    public function downloadArchive( $quizarchiveid )
    {
        global $CFG;
        $tempDir = $CFG->dataroot .'/temp/filestorage';

        $archive = $this->get_archive( $quizarchiveid );

        $table = $this->get_table_attempts( $archive, true );

        $tmpHTMLFiles = array();

        $tmpFile = tempnam($tempDir, "report_quizarchive_index");
        $tmpHTMLFile = $tmpFile . ".html";
        rename( $tmpFile, $tmpHTMLFile );
        $content = '<link rel="stylesheet" href="style.css"><div style="padding: 40px;"><h1 style="margin:20px 0;">'. $archive->name .'</h1>'. $table .'</div>';
        file_put_contents( $tmpHTMLFile, $content );
        $file['temp'] = $tmpHTMLFile;
        $file['name'] = 'index';

        $tmpHTMLFiles[] = $file;
        
        foreach( $archive->attempts as $attempt ){
            $attempt_html = $this->get_attempt( $quizarchiveid, $attempt->id, true );
            
            $tmpFile = tempnam($tempDir, "report_quizarchive_attempt");
            $tmpHTMLFile = $tmpFile . ".html";
            rename( $tmpFile, $tmpHTMLFile );

            $content = '<link rel="stylesheet" href="style.css"><div style="padding: 40px;">'. htmlspecialchars_decode( $attempt_html ).'</div>';

            file_put_contents( $tmpHTMLFile, $content );

            $file['temp'] = $tmpHTMLFile;
            $file['name'] = strtolower( str_replace(' ', '-', $attempt->fullname ) ) .'-attempt-'. $attempt->id;
            $file['name'] = 'attempt-'. $attempt->id;

            $tmpHTMLFiles[] = $file;
        }

        if( count( $tmpHTMLFiles ) ){
            $tmpFile = tempnam($tempDir, "report_quizarchive_attempt");
            $tmpZIPFile = $tmpFile . ".zip";
            rename( $tmpFile, $tmpZIPFile );

            $zip = new \ZipArchive;
            $zip->open( $tmpZIPFile );
            foreach( $tmpHTMLFiles as $tmpHTMLFile ){
                $zip->addFile( $tmpHTMLFile['temp'], $tmpHTMLFile['name'] .'.html' );
            }
            $zip->addFile( $CFG->dirroot .'/theme/'. $CFG->theme .'/style/moodle.css', 'style.css' );
            
            $zip->close();

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="'. urlencode( $archive->name ) .'.zip"');
            readfile( $tmpZIPFile );

            foreach( $tmpHTMLFiles as $tmpHTMLFile ){
                unlink( $tmpHTMLFile['temp'] );
            }
            unset( $zip );
            unlink( $tmpZIPFile );
        }

        exit;
    }

    public function clear_storage()
    {
        global $DB;

        $archives = $this->get_archives();

        foreach( $archives as $archive ){

            $attempts = $this->get_archive( $archive->id );

            foreach( $attempts->attempts as $attempt ){

                $course = $DB->get_record( 'course', array('id' => $attempts->courseid), '*', MUST_EXIST );
                $context = context_course::instance( $course->id );
                
                $fs = get_file_storage();

                $fileinfo = array(
                    'component' => 'report_quizarchive',
                    'filearea' => 'report_quizarchive',
                    'itemid' => $attempt->id,
                    'contextid' => $context->id,
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

                if( $file ){
                    $file->delete();
                }

                $DB->delete_records('report_quizarchive_at', array( 'id' => $attempt->id ));
                $DB->delete_records('report_quizarchive_join', array( 'quizarchive_atid' => $attempt->id ));
            }

            $DB->delete_records('report_quizarchive_qu', array( 'id' => $attempts->quizid ));
            $DB->delete_records('report_quizarchive', array( 'id' => $attempts->id ));
        }
    }

    public function get_table( $archives )
    {   
        $id = optional_param('id', 0, PARAM_INT);
        $table_sort = optional_param('table_sort', 'timecreated', PARAM_TEXT);
        $table_ascdesc = optional_param('table_ascdesc', 2, PARAM_INT);
        
        $table_header = array(
            'name' => array(
                'title' => get_string('archivename', 'report_quizarchive'),
                'type' => 'text'
            ),
            'quizname' => array(
                'title' => get_string('quiz_name', 'report_quizarchive'),
                'type' => 'text'
            ),
            'attempts_count' => array(
                'title' => get_string('attempts', 'report_quizarchive'),
                'type' => 'number',
                'align' => 'center'
            ),
            'timecreated' => array(
                'title' => get_string('created', 'report_quizarchive'),
                'type' => 'number'
            ),
            'download' => array(
                'title' => '',
                'type' => 'text'
            ),
            'remove' => array(
                'title' => '',
                'type' => 'text'
            )
        );
        
        $archives = $this->sortData( $table_header, $archives, $table_sort, $table_ascdesc );

        $html  = '<br class="clearer"/>';
        $html .= '<div id="completion-progress-wrapper" class="no-overflow">';
            $html .= '<table id="completion-progress" class="generaltable flexible boxaligncenter">';
                $html .= '<thead>';
                    $html .= '<tr>';
                        foreach( $table_header as $name => $column ){
                            $ascdesc = ( $table_sort == $name AND $table_ascdesc == 1 ) ? 2 : 1;
                            $url_attributes = array( 'id' => $id, 'table_sort' => $name, 'table_ascdesc' => $ascdesc );
                            $align = ( isset( $column['align'] ) ) ? 'style="text-align: '. $column['align'] .';"' : '';

                            $asc_icon = ( $table_sort == $name AND $table_ascdesc == 1 ) ? '<i class="icon fa fa-sort-asc fa-fw " title="Ascending" aria-label="Ascending"></i>' : '';
                            $desc_icon = ( $table_sort == $name AND $table_ascdesc == 2 ) ? '<i class="icon fa fa-sort-desc fa-fw " title="Descending" aria-label="Descending"></i>' : '';

                            $html .= '<th '. $align .'>'. html_writer::link( new moodle_url('/report/quizarchive/index.php', $url_attributes), $column['title'] ) .''. $asc_icon . $desc_icon .'</th>';
                        }
                    $html .= '</tr>';
                $html .= '</thead>';
                $html .= '<tbody>';
                    foreach( $archives as $archive ){

                        $html .= '<tr>';
                            $html .= '<td>'.  html_writer::link( new moodle_url('/report/quizarchive/show.php', array( 'id' => $archive->id )), $archive->name ) .'</td>';
                            $html .= '<td>'. $archive->quizname .'</td>';
                            $html .= '<td align="center">'. $archive->attempts_count .'</td>';
                            $html .= '<td>'. userdate( $archive->timecreated ) .'</td>';
                            $html .= '<td>'. html_writer::link( new moodle_url('/report/quizarchive/index.php', array( 'id' => $archive->courseid, 'quizarchiveid' => $archive->id, 'download' => 1, 'sesskey' => sesskey() )), '<i class="fa fa-download" aria-hidden="true"></i>', array('title' => get_string('download', 'report_quizarchive')) ) .'</td>';
                            $html .= '<td>'. html_writer::link( new moodle_url('/report/quizarchive/index.php', array( 'id' => $archive->courseid, 'quizarchiveid' => $archive->id, 'remove' => 1, 'sesskey' => sesskey() )), '<i class="fa fa-trash" aria-hidden="true"></i>', array('title' => get_string('remove', 'report_quizarchive')) ) .'</td>';
                        $html .= '</tr>';
                    }
                $html .= '</tbody>';
            $html .= '</table>';
        $html .= '</div>';
        $html .= '<br class="clearer"/>';

        echo $html;
    }

    public function get_table_attempts( $archive, $export = false )
    {   
        $id = optional_param('id', 0, PARAM_INT);
        $attemptid = optional_param('attemptid', 0, PARAM_INT);
        $table_sort = optional_param('table_sort', 'fullname', PARAM_TEXT);
        $table_ascdesc = optional_param('table_ascdesc', 1, PARAM_INT);
        
        $table_header = array(
            'fullname' => array(
                'title' => get_string('full_name', 'report_quizarchive'),
                'type' => 'text'
            ),
            'email' => array(
                'title' => get_string('email', 'report_quizarchive'),
                'type' => 'text'
            ),
            'state' => array(
                'title' => get_string('state', 'report_quizarchive'),
                'type' => 'text',
                'align' => 'center'
            ),
            'attempt' => array(
                'title' => get_string('attempt_max', 'report_quizarchive', $archive->max_attempts),
                'type' => 'text',
                'align' => 'center'
            ),
            'timestart' => array(
                'title' => get_string('started_on', 'report_quizarchive'),
                'type' => 'number'
            ),
            'taken' => array(
                'title' => get_string('time_taken', 'report_quizarchive'),
                'type' => 'number',
                'align' => 'center'
            ),
            'grade' => array(
                'title' => get_string('grade', 'report_quizarchive', number_format( $archive->grade, $archive->decimalpoints )),
                'type' => 'number',
                'align' => 'center'
            ),
            'show' => array()
        );
        
        $archive->attempts = $this->sortData( $table_header, $archive->attempts, $table_sort, $table_ascdesc );

        $url_attributes = array(
            'id' => $id,
            'attemptid' => $attemptid,
            'table_sort' => $table_sort,
            'table_ascdesc' => $table_ascdesc
        );

        $html  = '<br class="clearer"/>';
        $html .= '<div id="completion-progress-wrapper" class="no-overflow">';
            $html .= '<table id="completion-progress" class="generaltable flexible boxaligncenter">';
                $html .= '<thead>';
                    $html .= '<tr>';
                        foreach( $table_header as $name => $column ){
                            if( !count( $column ) ){
                                $html .= '<th>&nbsp;</th>';
                                continue;
                            }
                            $ascdesc = ( $table_sort == $name AND $table_ascdesc == 1 ) ? 2 : 1;

                            $head_attributes = $url_attributes;

                            $head_attributes['table_sort'] = $name;
                            $head_attributes['table_ascdesc'] = $ascdesc;

                            $align = ( isset( $column['align'] ) ) ? 'style="text-align: '. $column['align'] .';"' : '';

                            $asc_icon = ( $table_sort == $name AND $table_ascdesc == 1 ) ? '<i class="icon fa fa-sort-asc fa-fw " title="Ascending" aria-label="Ascending"></i>' : '';
                            $desc_icon = ( $table_sort == $name AND $table_ascdesc == 2 ) ? '<i class="icon fa fa-sort-desc fa-fw " title="Descending" aria-label="Descending"></i>' : '';

                            if( $export ){
                                $html .= '<th '. $align .'>'. $column['title'] .'</th>';
                            } else {
                                $html .= '<th '. $align .'>'. html_writer::link( new moodle_url('/report/quizarchive/show.php', $head_attributes), $column['title'] ) .''. $asc_icon . $desc_icon .'</th>';
                            }
                        }
                    $html .= '</tr>';
                $html .= '</thead>';
                $html .= '<tbody>';
                    foreach( $archive->attempts as $attempt ){

                        $link_attributes = $url_attributes;
                        $link_attributes['attemptid'] = $attempt->id;

                        $html .= '<tr>';
                            $html .= '<td>'. $attempt->fullname .'</td>';
                            $html .= '<td>'. $attempt->email .'</td>';
                            $html .= '<td align="center">'. $attempt->state .'</td>';
                            $html .= '<td align="center">'. $attempt->attempt .'</td>';
                            $html .= '<td>'. userdate( $attempt->timestart ) .'</td>';
                            $html .= '<td align="center">'. format_time( $attempt->taken ) .'</td>';
                            $html .= '<td align="center">'. number_format( $attempt->sumgrades, $archive->decimalpoints ) .'</td>';
                            if( $export ){
                                $html .= '<td><a href="attempt-'. $attempt->id .'.html">'. get_string('show', 'report_quizarchive') .'</a></td>';
                            } else {
                                $html .= '<td>'. html_writer::link( new moodle_url('/report/quizarchive/show.php', $link_attributes), '<i class="fa fa-eye" aria-hidden="true"></i>', array('title' => get_string('show', 'report_quizarchive')) ) .'</td>';
                            }
                            $html .= '</tr>';
                    }
                $html .= '</tbody>';
            $html .= '</table>';
        $html .= '</div>';
        $html .= '<br class="clearer"/>';
        
        if( $export ) return $html;

        echo $html;
    }

    private function sortData( $columns, $data, $table_sort, $table_ascdesc )
	{	
		if( !is_array( $data ) ) return;

        $sort['direction'] = $table_ascdesc;
        $sort['type'] = $columns[ $table_sort ]['type'];
        $sort['column'] = $table_sort;

		usort( $data, function( $a, $b ) use( $sort ){
			if( $sort['direction'] == 2 ){
				if( $sort['type'] == 'text' ) return strcmp( $b->{$sort['column']}, $a->{$sort['column']} );
				if( $sort['type'] == 'number' ) return $b->{$sort['column']} - $a->{$sort['column']};
			} else {
				if( $sort['type'] == 'text' ) return strcmp( $a->{$sort['column']}, $b->{$sort['column']} );
				if( $sort['type'] == 'number' ) return $a->{$sort['column']} - $b->{$sort['column']};
			}
		});

        return $data;
	}
}
