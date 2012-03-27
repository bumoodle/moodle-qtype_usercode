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
 * Defines the editing form for the true-false question type.
 *
 * @package    qtype
 * @subpackage usercode
 * @copyright  2007 Jamie Pratt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


require_once($CFG->dirroot.'/question/type/edit_question_form.php');
require_once($CFG->dirroot.'/question/type/scripted/edit_scripted_form.php');




/**
 * True-false question editing form definition.
 *
 * @copyright  2007 Jamie Pratt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_usercode_edit_form extends question_edit_form 
{
    /**
     * Add question-type specific form fields.
     *
     * @param object $mform the form being built.
     */
    protected function definition_inner($mform) 
    {
        //add the list of possible target systems
        $targets = array ( qtype_usercode_system::MC9S08QG8 => get_string('mc9s08qg8_asm', 'qtype_usercode'));
        $mform->addElement('select', 'system', get_string('targetsystem', 'qtype_usercode'), $targets);


        //add runtime limit configuration
        $mform->addElement('text', 'runlimit', get_string('runtimelimit', 'qtype_usercode'));
        $mform->setType('runlimit', PARAM_INT);
        $mform->setDefault('runlimit', 1000);


        //insert the init-script block
        qtype_scripted_edit_form::insert_editor($mform);


        //and the grading code block
        qtype_scripted_edit_form::insert_editor($mform, 'grading_code', get_string('gradingcode', 'qtype_usercode'), false, false);   


        //TODO: breakpoints and breakpoint code
        $mform->addElement('hidden', 'breakpoints', '');


        //insert a new header, starting the limitations block
        $mform->addElement('header', 'limitations', get_string('usercodelimits', 'qtype_usercode'));
        $mform->addElement('textarea', 'blacklist', get_string('blacklist', 'qtype_usercode'), array('cols' => 70));
        $mform->addElement('textarea', 'whitelist', get_string('whitelist', 'qtype_usercode'), array('cols' => 70));
        $mform->addElement('textarea', 'requirelist', get_string('requiredlist', 'qtype_usercode'), array('cols' => 70));

	
        //add settings for interactive (and similar) modes
    	$this->add_interactive_settings();

    }


    public function qtype() 
    {
        return 'usercode';
    }
}
