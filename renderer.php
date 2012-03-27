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
 * True-false question renderer class.
 *
 * @package    qtype
 * @subpackage usercode
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * Generates the output for true-false questions.
 *
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_usercode_renderer extends qtype_renderer 
{

    /**
     * Informs Moodle to include the codemirror CSS before the header is submitted.
     */
    public function head_code(question_attempt $qa)
    {
        global $PAGE;

        //require the codemirror CSS files to be loaded as stylesheets
        $PAGE->requires->css('/scripts/codemirror/codemirror.css');
        $PAGE->requires->css('/scripts/codemirror/default.css');

        //return an empty string, as we don't need to add any other HTML
        return '';
    }


    /**
     * Returns the main formulation and controls for a UserCode question.
     */
    public function formulation_and_controls(question_attempt $qa, question_display_options $options) 
    {
        //access the global page object
        global $PAGE, $CFG;

        //get the question, and the user's last response
        $question = $qa->get_question();
        $response = $qa->get_last_qt_var('answer', "\t");

        //get the field name that should be used to submit the answer code
        $name = $qa->get_qt_field_name('answer');

        //start a new output buffer containing the question text
        $output = html_writer::tag('div', $question->format_questiontext($qa), array('class' => 'qtext'));

        //add the core text-area for user response
        $output .= html_writer::tag('textarea', htmlentities($response), array('name' => $name, 'id' => $name));

        //tell Moodle where to find the core codemirror code
        $PAGE->requires->js('/scripts/codemirror/codemirror.js');

        //and tell Moodle where to find the language definition
        switch($question->system)
        {
            //assume we're working with the HCS08
            default:
                $PAGE->requires->js('/scripts/codemirror/hcs08.js');
        }

        //insert a small block of initialization script, which will initialize codemirror
        $codemirror = 
            ' var editor = CodeMirror.fromTextArea(document.getElementById("'.$name.'"), {
                    lineNumbers: true,
                    matchBrackets: false,
                    theme: \'elegant\',
                    mode: \'text/asm-hcs08\'
                }); ';
        $PAGE->requires->js_init_code($codemirror, true);

        //get a refernce to the grading behaviour, which will allow us to get the last graded step
        $behaviour = $qa->get_behaviour();
    
        //FIXME: this is not the last graded step        
        $last_step = $qa->get_last_step(); //$qa->get_last_step_with_qt_var('answer');
        $last_graded_step = $qa->get_last_step_with_qt_var('-submit');

        if($last_graded_step && $last_graded_step->has_qt_var('-submit'))
        {

            $last_graded_response = $last_graded_step->get_qt_var('answer');


            //get all validation errors for the question; this should be fast, as we cache those for the length of the transaction
            $validation_error = $question->get_validation_error(array('answer' => $last_graded_response));

            //if validation errors exist, display them
            if($validation_error !== null)
            {
                $message .= $validation_error;
                $class = 'usercode_error';
            }

            //otherwise, display any relevant messages from the grading script
            else
            {
                //ask the question for a grading message
                $message = $question->get_grading_message(array('answer' => $response));
                $class = 'usercode_message';

           }

            //if the user's response has changed in the interim
            if($last_step->get_qt_var('answer') != $last_graded_step->get_qt_var('answer'))
                $message .= html_writer::empty_tag('br') . html_writer::tag('em', get_string('mayhavechanged', 'qtype_usercode'));

            //if we recieved one, display it
            if(!is_null($message))
                $output .= html_writer::tag('div', $message, array('class' => $class)); 
 
        }
    
    
        //return the output
        return $output;
    }

}
