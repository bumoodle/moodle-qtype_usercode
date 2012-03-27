<?php


/**
 * safe_tcl_session
 * Class for creating and managing TCL sessions for evaluating untrusted code.
 * 
 * @package 
 * @version $id$
 * @copyright 2011, 2012 Binghamton University
 * @author Kyle Temkin <ktemkin@binghamton.edu> 
 * @license GNU Public License, {@link http://www.gnu.org/copyleft/gpl.html}
 */
class safe_tcl_session
{

    /**
     * Returns true iff the given TCL code has correctly matched curly braces.
     * In order to be safely used with 'safeinterp eval { '.$tcl.'}'
     * 
     * @param mixed $tcl 
     * @access public
     * @return void
     */
    static function braces_match($tcl)
    {
        //keep track of the number of open curly braces
        $open_brades = 0;

        //for each character in the string
        foreach($tcl as $location => $char)
        {
            //if the previous character was an escape character (\), ignore the next character
            if($prev == '\\')
            {
                $prev = '';
                continue;
            }
        }
    }
}
