<?php

/**
 * SimuatlorFailedException 
 * 
 * Indicates that we could not start a simulator process.
 * 
 * @uses Exception
 * @package UserCodeSimulator
 * @version $id$
 * @copyright 2011, 2012 Binghamton University
 * @author Kyle Temkin <ktemkin@binghamton.edu> 
 * @license GNU Public License, {@link http://www.gnu.org/copyleft/gpl.html}
 */
class SimulatorFailedException extends Exception {}

/**
 * Wrapper for exceptions thrown by the UCS.
 */
class UserCodeException extends Exception {}


/**
 * UserCode Session 
 *
 * Handles simulation of abstract user code using the UserCode Simulator, a python
 * program designed to .
 * 
 * @package UserCodeSimulator 
 * @version $id$
 * @copyright 2011, 2012 Binghamton University
 * @author Kyle Temkin <ktemkin@binghamton.edu> 
 * @license GNU Public License, {@link http://www.gnu.org/copyleft/gpl.html}
 */
class UserCodeSession
{
    
    /**
     *  Path to the usercode simulator executable.
     */
    const USERCODE_SIMULATOR = '/srv/usercode/usercode.py';
    //const USERCODE_SIMULATOR = '/home/ktemkin/Documents/Teaching/Software/usercode/usercode.py';

    /**
     *  Constant which is used to indicate the end of a block of user code.
     */
    const USERCODE_TERMINATOR = '___END_USER_CODE___';

    /**
     * The maximum response length should be 10KiB.
     */
    const MAXIMUM_RESPONSE_LENGTH = 10240;

    /**
     *  A character specified by the UCS, which indicates that it is ready for the next command.
     *  This is passed to the UCS during initialization, so it can be changed here.
     */
    const PROMPT_CHARACTER = '!';


    /**
     *  Prefix which indicates that the rest of a response line is an error message.
     */
    const ERROR_PREFIX = 'ERROR:';


    /**
     *  Correct response, which should termiante a handshaking command.
     */
    const HANDSHAKE_RESPONSE = 'Pong.';

    /**
     * Resource handle for the active UserCode Simulator process.
     *
     * @var resouce
     */
    private $proc;

    /**
     * Pipe which represents the standard input of the child process. 
     * 
     * @var mixed
     */
    private $to_ucs;

    /**
     * Pipe which represents the standard output of the child process. 
     * 
     * @var mixed
     */
    private $from_ucs;


    /**
     * Creates a new UserCode Simulator process, and sets up the relevant IPC pipes.
     * 
     * @access protected
     * @return void
     */
    function __construct()
    {
        //create an array which describes how to connect to the correct IPC pipes
        $descriptors = 
            array
            (
                0 => array('pipe', 'r'), //attach to the child's stdin (_TO_ the child)
                1 => array('pipe', 'w'), //attach to the child's stdout (_FROM_ the child)
            );

        //and start the new UCS child process
        $this->proc = proc_open(self::USERCODE_SIMULATOR.' --noprompt', $descriptors, $pipes, '/tmp');
        
        //if we failed to open the UCS, throw an error
        if(!is_resource($this->proc))
            throw new SimulatorFailedException(); //TODO: add message

        //extract the created stdin/stdout
        $this->to_ucs = $pipes[0];
        $this->from_ucs = $pipes[1];

        //perform an initial handshake, to very working communications
        $this->handshake();
    }

    public function handshake()
    {
        //send a "ping" command, which should result in a response ending in Pong
        $this->send_raw_command('pn');

        //get the response
        $response = $this->read_response();

        //if the response did not end with the correct handshaking suffix, throw an error
        if(!preg_match('#.*'.preg_quote(self::HANDSHAKE_RESPONSE).'\s+$#', $response))
            throw new UserCodeException('Could not connect to the simulator!');
    }

    /**
     * Sends a block of code, which will be loaded by the interpreter.
     * 
     * @param string $user_code     The user code to be simulated.
     */
    public function load_code($user_code)
    {
        //remove any non-ASCII and escape characters from the user code
        $user_code = preg_replace('/[^(\x20-\x7F)\n]*/', '', $user_code);

        //strip any terminating characters that may appear in the user code
        $user_code = str_replace(self::USERCODE_TERMINATOR, '', $user_code);

        //instruct the UCS to begin recieving user code
        $this->send_raw_command('c '.self::USERCODE_TERMINATOR);

        //send the user code directly
        $this->send_raw_command($user_code);

        //terminate the user code
        $this->send_raw_command(self::USERCODE_TERMINATOR);

        //read any response from the command, and discard it
        $this->read_response();

    }

    
    /**
     * Returns an associative array of state variables for the active UCS machine. 
     * 
     * @return array    An associative array of the UCS machine's state variables. Contents are machine dependant.
     */
    public function get_state($get_full_state = false)
    {
        //send the "get state" command
        if(!$get_full_state)
            $this->send_raw_command('g');
        else
            $this->send_raw_command('f');

        //retrieve the query-encoded state string
        $response = $this->read_response();

        //parse the response
        parse_str($response, $values);

        //and return
        return $values;
    }

    public function set_state(array $state)
    {
        //build a state string, for submission to the UCS
        $state_query = http_build_query($state, '', '&');  

        //tell the UCS to load the serialized state
        $this->send_raw_command('l '.$state_query);

        //discard the response
        $this->read_response();
    }

    /**
     * Single step through the user program. 
     * 
     * @return void
     */
    public function step()
    {
        $this->send_raw_command('s');

        $this->read_response();
    }


    /**
     * Continue evaluating the user code until the next breakpoint.  
     */
    public function run()
    {
        //send the continue command
        $this->send_raw_command('cc');

        //and discard the response
        $this->read_response();
    }


    /**
     * Passes a blacklist string to the UCS instance. 
     * This can be used to prohibit the use of certain instructions or constructs.
     * 
     * @param string|array $names   The list of constructs to be blacklisted. Format depends on the target machine.
     */
    public function blacklist($names)
    {
        //send the list as a blacklist
        $this->send_list('bl', $names);
    }

    /**
     * Passes a whitelist string to the UCS instance. 
     * This can be used to allow only the use of certain instructions or constructs.
     * 
     * @param string|array $names   The list of constructs to be whitelisted. Format depends on the target machine.
     */
    public function whitelist($names)
    {
        //send the list as a blacklist
        $this->send_list('wl', $names);
    }

    /**
     * Passes a required list string to the UCS instance. 
     * This requires the user to use _all_ of the given instructions or constructions. They may use other instructions/constructs as defined by the white/blacklists.
     * 
     * @param string|array $names   The list of constructs to be blacklisted. Format depends on the target machine.
     */
    public function set_required($names)
    {
        //send the list as a blacklist
        $this->send_list('rl', $names);
    }

    
    /**
     * Limits the maximum amount of time for which the given piece of usercode can execute. The meaning of the limit is system dependent, but typically is a number of small machine cycles. 
     * 
     * @param mixed $limit  The maximum amount of machine cycles (or equivalent) for which the usercode can run.
     */
    public function limit_runtime($limit)
    {
        //send the raw limit
        $this->send_raw_command('rtl '.$limit);

        //and discard the response
        $this->read_response();
    }


    /**
     * Sends a list of words to the UCS instance. Typically used to set black/white/required list data.
     * 
     * @param string $command       The raw command name to be executed on the UCS.
     * @param string|array $list    The list of names to be passed to the UCS.
     */
    protected function send_list($command, $list)
    {
        //if we were provided an array of names, join them into a string
        if(is_array($list))
            $list = implode(' ', $list); 

        //send the raw command
        $this->send_raw_command($command.' '.$list);

        //and wait and discard for a response
        $this->read_response();

    }


    /**
     * Closes the UCS session and all relevant pipes. 
     */
    public function close()
    {
        //let the UCS instance know to die
        $this->send_raw_command('q');

        //close the two IPC pipes
        fclose($this->to_ucs);
        fclose($this->from_ucs); 

        //then, end the UCS process
        proc_close($this->proc);
    }

    /**
     * Sends a raw command to the UCS.
     * 
     * @param mixed $command  The command data to be sent to the UCS. Typically a string, but may be binary. 
     */
    protected function send_raw_command($command)
    {
        //write the command to the UCS, followed by a line end
        fwrite($this->to_ucs, $command."\n");    
    }

    /**
     * Reads a response from the UCS, up to the next prompt character.
     * 
     * @return void
     */
    protected function read_response()
    {
        //$response =  stream_get_line($this->from_ucs, self::MAXIMUM_RESPONSE_LENGTH); //, self::PROMPT_CHARACTER);
        $response = fgets($this->from_ucs);

        //if the response starts with the error prefix, and thus is an error, throw an exception with the error message
        if(substr($response, 0, strlen(self::ERROR_PREFIX)) == self::ERROR_PREFIX)
            throw new UserCodeException(substr($response, strlen(self::ERROR_PREFIX)));
        
        //return the response
        return $response;
    }

    /**
     * Destroys the given UCS session, and closes the UCS instance and pipes if the owner failed to do so.
     * 
     * @access protected
     * @return void
     */
    function __destruct()
    {
        //if the UCS instance has not yet been closed, close it
        if(is_resource($this->proc))
            $this->close();
    }

}
