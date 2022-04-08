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

global $CFG;

require_once( $CFG->libdir .'/formslib.php' );

class form_selectquiz extends \moodleform {

    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('header', 'quizheader', get_string('select_quiz', 'report_quizarchive'));

        $mform->addElement('select', 'modid', get_string('quizzes', 'report_quizarchive'), array(''=>''));
        $mform->setExpanded('quizheader', true);
    }

    public function definition_after_data() {
        $mform = $this->_form;
        
        $buttonarray = [
            $mform->createElement('submit', 'quizselect', get_string('select', 'report_quizarchive')),
        ];

        $modid = $mform->getElement('modid')->getValue();
        if( empty( $modid ) ){
            $buttonarray[] = $mform->createElement('cancel');
        }

        $mform->addGroup($buttonarray, 'buttonar_select', '', '', false);

        $mform->closeHeaderBefore('buttonar');
    }

    public function setSelectValues( $name, $values ){
        $mform = $this->_form;

        $select =& $mform->getElement( $name );
        $select->load( $values );
    }

}
