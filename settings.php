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
 * settings.php - allows the admin to configure plagiarism stuff
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

require_login();
admin_externalpage_setup('plagiarismplagiarisma');
$context = context_system::instance();
require_capability('moodle/site:config', $context, $USER->id, true, "nopermissions");

require_once($CFG->dirroot.'/plagiarism/plagiarisma/plagiarism_form.php');
$mform = new plagiarism_setup_form();

$mform->set_data(get_config('plagiarism_plagiarisma'));

$plagiarismplugin = new plagiarism_plugin_plagiarisma();

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/'));
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
    // Save each setting.
    foreach ($data as $field => $value) {
        if (strpos($field, 'plagiarisma') == 0) {
            set_config($field, $value, 'plagiarism_plagiarisma');
        }
        if ($field == 'delall' && $value == true) {
            clean_data();
            set_config($field, 0, 'plagiarism_plagiarisma');
        }
    }
    $mform->set_data(get_config('plagiarism_plagiarisma'));

    // Invoke plagiarism_plagiarisma_authorize() to validate account.
    $plagiarismsettings = $plagiarismplugin->get_settings();

    if ($plagiarismsettings === false) {
        set_config('plagiarisma_use', 0, 'plagiarism');
        set_config('plagiarisma_use', 0, 'plagiarism_plagiarisma');
    } else {
        set_config('plagiarisma_use', 1, 'plagiarism');

        echo $OUTPUT->notification(get_string('savedconfigsuccess', 'plagiarism_plagiarisma'), 'notifysuccess');
    }
}

echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
$mform->display();
echo $OUTPUT->box_end();
echo $OUTPUT->footer();

/**
 * clean tables
 */
function clean_data() {
    global $DB, $OUTPUT;

    $DB->delete_records("plagiarism_plagiarisma_files");
    $DB->delete_records("plagiarism_plagiarisma_id");

    echo $OUTPUT->notification(get_string('tables_cleaned_up', 'plagiarism_plagiarisma'), 'notifysuccess');
}
