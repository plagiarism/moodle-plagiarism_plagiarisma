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
 * plagiarism.php - allows the admin to configure plagiarism stuff
 *
 * @package   plagiarism_plagiarisma
 * @author    Dan Marsden <dan@danmarsden.com>
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @copyright 2015 Plagiarisma.Net http://plagiarisma.net
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(__FILE__)) . '/../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/plagiarismlib.php');
require_once($CFG->dirroot.'/plagiarism/plagiarisma/lib.php');
require_once($CFG->dirroot.'/plagiarism/plagiarisma/plagiarism_form.php');

require_login();
admin_externalpage_setup('plagiarismplagiarisma');
$context = context_system::instance();

require_capability('moodle/site:config', $context, $USER->id, true, "nopermissions");
require_once('plagiarism_form.php');
$mform = new plagiarism_setup_form();
$plagiarismplugin = new plagiarism_plugin_plagiarisma();

if ($mform->is_cancelled()) {
    redirect('');
}

echo $OUTPUT->header();

if (($data = $mform->get_data()) && confirm_sesskey()) {
    // Set checkboxes if they aren't set.
    if (!isset($data->plagiarisma_use)) {
        $data->plagiarisma_use = 0;
    }
    if (!isset($data->plagiarisma_disable_dynamic_inline)) {
        $data->plagiarisma_disable_dynamic_inline = 0;
    }
    if (!isset($data->plagiarisma_use_default)) {
        $data->plagiarisma_use_default = 0;
    }
    if (!isset($data->plagiarisma_student_score_default)) {
        $data->plagiarisma_student_score_default = 0;
    }
    if (!isset($data->plagiarisma_student_report_default)) {
        $data->plagiarisma_student_report_default = 0;
    }
    // Save each setting.
    foreach ($data as $field => $value) {
        if (strpos($field, 'plagiarisma') === 0) {
            set_config($field, $value, 'plagiarism');
        }
        if ($field == 'delall' && $value == true) {
            clean_data();
        }
    }
    unset($_SESSION['plagiarisma_use']);

    notify(get_string('savedconfigsuccess', 'plagiarism_plagiarisma'), 'notifysuccess');
}

$plagiarismsettings = (array)get_config('plagiarism');
$mform->set_data($plagiarismsettings);

echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
$mform->display();
echo $OUTPUT->box_end();
echo $OUTPUT->footer();

function clean_data() {
    global $DB;

    $DB->delete_records("plagiarism_plagiarisma_files");
    $DB->delete_records("plagiarism_plagiarisma_id");

    notify(get_string('tables_cleaned_up', 'plagiarism_plagiarisma'), 'notifysuccess');
}
