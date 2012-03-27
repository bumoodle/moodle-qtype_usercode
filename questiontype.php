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
 * Question type class for the true-false question type.
 *
 * @package    qtype
 * @subpackage usercode
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

/**
 * Constants which describe the type of system the Usercode simulator will run as.
 */
class qtype_usercode_system
{
    const HCS08 = 0;
    const MC9S08QG8 = 0;
}


/**
 * The true-false question type class.
 *
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_usercode extends question_type
{ 

    /**
     * Specifies the extra question fields and table, so question options can automatically be saved.
     */
    public function extra_question_fields()
    {
        return array('question_usercode', 'init_code', 'grading_code', 'blacklist', 'whitelist', 'requirelist', 'system', 'breakpoints', 'runlimit');
    }

    public function questionid_column_name()
    {
        return 'question';
    }

    /**
     *  Indicates the average score for a random guess.
     */
    public function get_random_guess_score($questiondata) 
    {
        //random guesses are nearly impossible
        return 0;
    }

}
