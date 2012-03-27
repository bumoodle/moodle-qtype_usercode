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
 * True-false question definition class.
 *
 * @package    qtype
 * @subpackage usercode
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

//require use of the Scripted quesiton mechanism, which handles MathScript
require_once($CFG->dirroot.'/question/type/scripted/question.php');

//require use of the UserCode Interop
require_once($CFG->dirroot.'/question/type/usercode/UserCodeInterop.class.php');

/**
 * Represents a true-false question.
 *
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_usercode_question extends question_graded_automatically 
{
    /**
     * Returns an array containing the data expected in a valid submission of a usercode question.
     */
    public function get_expected_data() 
    {
        return array('answer' => PARAM_RAW);
    }

    /**
     * Indicates that no sample "correct response" is applicable.
     */
    public function get_correct_response() 
    {
        return null;
    }

    /**
     * Performs the actual grading, simulating a piece of user code.
     * 
     * @param array $response 
     * @return void
     */
    public function grade_response(array $response)
    {  
        //run the given usercode using our caching execution engine
        $result = $this->run_usercode($response);

        //and return the fraction created by the grading script 
        return array($result['fraction'], question_state::graded_state_for_fraction($result['fraction']));
    }

    /**
     * Converts script variables from the initialization script into UCS-friendly variables.
     * 
     * @param array $vars   An array of variables, as returned by the MathScript interpreter.
     * @return array        An array of variables fit for uploading into the UserCode simulator.
     */
    static function script_vars_to_system_state(array $vars)
    {
        $state_vars = array(); 

        //for each MathScript variable
        foreach($vars as $name => $value)
        {
            //if the variable is of the form R[0-9]+, then it should be convereted to a numeric address
            if($name[0] == 'R' && is_numeric(substr($name, 1)))
                $name = intval(substr($name, 1));

            //set the state var with the appropriate name
            $state_vars[$name] = $value;
        }

        //and return the newly created state vars
        return $state_vars;
    }

    /**
     * Converts the system state output from the UserCode Simulator into a set of variables which can be imported
     * by the MathScript engine.
     * 
     * @param array $vars   An associative array of variables, as exported by the UserCode simulator.
     * @return array        An associative array of variables suitable for import by the MathScript engine.
     */
    static function system_state_to_script_vars(array $vars)
    {
        $state_vars = array();

        //for each system state variable
        foreach($vars as $name => $value)
        {
            //if the state variable starts with a number (like HCS08 RAM addresses), prefix it with an R
            if(is_numeric(substr($name, 0, 1)) || substr($name, 0, 1) == '_')
                $name = 'R' . $name;

            //remove all invalid characters from the name
            $name = preg_replace('#[^A-Za-z0-9_]#', '', $name);

            //and, if anything's left over, set the variable with the new name
            if(!empty($name))
                $state_vars[$name] = $value;
        }

        //return the newly created state_vars
        return $state_vars;
    }

    /**
     * Determines if the given response is complete, and thus should be graded.
     */
    public function is_complete_response(array $response)
    {
        //if no answer has been set, the response must be incomplete
        if(!array_key_exists('answer', $response) || !trim($response['answer']))
            return false;
    
        //otherwise, run the user code using our caching evaluation engine
        $result = $this->run_usercode($response);

        //be merciful: if the user code doesn't validate, don't use up a try
        return $result['validated'];
    }

    /**
     * Returns true iff the given response is gradeable.
     */
    public function is_gradable_response(array $response)
    {
        //any complete response is gradeable
        return $this->is_complete_response($response);
    }

    /**
     * Returns true iff $a and $b both refer to the same response.
     * This is used to prevent duplicate submissions from being graded. 
     */
    public function is_same_response(array $a, array $b)
    {
        //compare the two answers
        return question_utils::arrays_same_at_key_missing_is_blank($a, $b, 'answer');
    }


    /**
     * Returns a short-but-compelte summary of the given response.
     */
    public function summarise_response(array $response)
    {
        return $response['answer'];
    }

    /**
     * Returns an error message if the given response doesn't validate (isn't complete),
     * or null if the response is gradeable.
     */
    public function get_validation_error(array $response)
    {

        //run the usercode (or retrieve a previous run from cache)
        $results = $this->run_usercode($response);

        //if we weren't able to validate the response, the message is the validation error 
        if(!$results['validated'])
            return $results['message'];        
        else
            return null;
    }

    /**
     * Returns the message provided by the grading script for the given response, if one exists. 
     * If an error occurs during execution or parsing, it will be returned instead.
     */
    public function get_grading_message(array $response)
    {
        //if we didn't recieve a valid response, return null
        if(!array_key_exists('answer', $response) || !(trim($response['answer'])))
            return null;


        //run the usercode (or retrieve a previous run from cache)
        $results = $this->run_usercode($response);

        //and return the generated message
        return $results['message'];
    }

    /**
     * Perform a special run of the user's code whose result is cached for the remainder of the transaction.
     * (In other words, the user code is only evaluated a max of once per HTTP request.) 
     * 
     * @param array $response   The user's response, which contains their code.
     * @return array            An associative array with three keys:
     *                              -'validated' => boolean; true iff the user's code passed validation, and should be graded
     *                              -'message' => string; a message which should be passed to the user; often explains why validation failed, or a grade was wrong
     *                              -'fraction' => float; a final fraction, between 0 and 1, which describes the user's grade, if applicable
     */
    protected function run_usercode(array $response)
    {
        //create a simple cache, which stores the result of the last run-through
        //this is used to ensure the same code is only evaluated once per transaction
        static $cached_hash = null;
        static $cached_result = null;

        //create a hash of the provided response
        $hash = md5($response['answer']);

        //if the hash matches the existing response's hash, return the cached answer
        if($hash == $cached_hash)
            return $cached_result;

        //first, load the initialization script, and use it to get an initial system state
        list(, $vars, ) =  qtype_scripted_question::execute_script($this->init_code, $this->questiontext);

        //then, create a new usercode session
        $ucs = new UserCodeSession();

        //start off assuming we've failed validation, have no message, and have a grade of 0
        $validated = false;
        $message = '';
        $fraction = 0;

        //attempt to run the user's code
        try
        {
            //
            // First, set up the user code for parsing:
            //

            //if a whitelist was provided, use it
            if(!empty($this->whitelist))
                $ucs->whitelist($this->whitelist);

            //otherwise, if a blacklist was provided, use it
            elseif(!empty($this->blacklist))
                $ucs->blacklist($this->blacklist);

            //if a requirelist was provided, load it as well
            if(!empty($this->requirelist))
                $ucs->set_required($this->requirelist);

            //
            // Next, parse the user code, and set up some initial state
            // 

            //load the user's code
            $ucs->load_code($response['answer']);

            //once the code has loaded successfully, we've passed validation
            $validated = true;

            //limit the UCS runtime
            $ucs->limit_runtime($this->runlimit);

            //use the variables generated by the initialization to load an initial system state
            $ucs->set_state(self::script_vars_to_system_state($vars)); 

            //
            //Now, run the user code, until it's either complete, or TODO: hits a breakpoint
            //
            
            //TODO: while(!$ucs->has_terminated()) //or similar
            $ucs->run();

            //TODO: if not terminated, this is a breakpoint- run breakpoint code, and then run again
            

            //
            //Once the user code is complete, get the final system state, then kill the UCS
            //
            $state = $ucs->get_state();

            //close the UCS connection, killing the UCS
            $ucs->close();             

            //
            //Finally, grade the user based on the state
            //

            //merge the initialization vars and the current system state
            $vars = array_merge($vars, self::system_state_to_script_vars($state));

            //assume a grade of 0; this will hopefully be overwritten by the grading script
            $vars['grade'] = 0;

            //execute the final grading script
            list(, $vars, ) = qtype_scripted_question::execute_script($this->grading_code, false, $vars); 

            //if the grading script provided a message for the respondant, store it, for use by the renderer
            if(array_key_exists('message', $vars))
            {
                //store a local copy of the current message
                $message = $vars['message'];
            }

            //finally, ensure the user's grade is between 0 and 1
            $fraction =  min(max($vars['grade'], 0), 1);

            //and return the graded value
            //return array($fraction, question_state::graded_state_for_fraction($fraction));

        }
        //if an there was an error in the user's code, grade it as a 0, and store the error message as the last message
        catch(UserCodeException $e)
        {
            //store the message associated with the error
            $message = $e->getMessage();

            //and assume a grade of 0, as we weren't able to grade the student
            $grade = 0;
        }

        //create an array from the results
        $result  = array('validated' => $validated, 'message' => $message, 'fraction' => $fraction);

        //cache the results
        $cached_hash = $hash;
        $cached_result = $result;

        //and return the result array
        return $result;

    }

}
