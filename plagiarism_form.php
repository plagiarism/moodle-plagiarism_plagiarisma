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
 * plagiarism_form.php
 *
 * @since 2.0
 * @package    plagiarism_plagiarisma
 * @subpackage plagiarism
 * @copyright  2010 Dan Marsden http://danmarsden.com
 * @copyright  2015 Plagiarisma.Net http://plagiarisma.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->dirroot.'/lib/formslib.php');

/**
 * plagiarism_setup_form - settings form
 * @copyright  2015 Plagiarisma.Net http://plagiarisma.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plagiarism_setup_form extends moodleform {
    /**
     * form elements
     */
    public function definition () {
        global $CFG;

        $mform =& $this->_form;
        $choices = array('No', 'Yes');
        $mform->addElement('html', get_string('plagiarismaexplain', 'plagiarism_plagiarisma'));
        $mform->addElement('checkbox', 'plagiarisma_use', get_string('useplagiarisma', 'plagiarism_plagiarisma'));

        $mform->addElement('textarea', 'plagiarisma_student_disclosure',
                           get_string('studentdisclosure', 'plagiarism_plagiarisma'), 'wrap="virtual" rows="4" cols="50"');
        $mform->addHelpButton('plagiarisma_student_disclosure', 'studentdisclosure', 'plagiarism_plagiarisma');
        $mform->setDefault('plagiarisma_student_disclosure', get_string('studentdisclosuredefault', 'plagiarism_plagiarisma'));

        $mform->addElement('text', 'plagiarisma_accountid', get_string('plagiarismaaccountid', 'plagiarism_plagiarisma'));
        $mform->addHelpButton('plagiarisma_accountid', 'plagiarismaaccountid', 'plagiarism_plagiarisma');
        $mform->setType('plagiarisma_accountid', PARAM_TEXT);

        $mform->addElement('passwordunmask', 'plagiarisma_secretkey', get_string('plagiarismasecretkey', 'plagiarism_plagiarisma'));
        $mform->addHelpButton('plagiarisma_secretkey', 'plagiarismasecretkey', 'plagiarism_plagiarisma');

        $mform->addElement('html', get_string('advanced_settings', 'plagiarism_plagiarisma') . "<br/>");

        $mform->addElement('checkbox', 'plagiarisma_disable_dynamic_inline',
                           get_string('disable_dynamic_inline', 'plagiarism_plagiarisma'));
        $mform->addHelpButton('plagiarisma_disable_dynamic_inline', 'disable_dynamic_inline', 'plagiarism_plagiarisma');

        $mform->addElement('html', get_string('tools', 'plagiarism_plagiarisma'));
        $mform->addElement('checkbox', 'delall', get_string('clean_tables', 'plagiarism_plagiarisma'));
        $mform->addHelpButton('delall', 'cleantables', 'plagiarism_plagiarisma');

        $this->add_action_buttons(true);
    }
}
