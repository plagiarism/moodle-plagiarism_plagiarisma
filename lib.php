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
 * lib.php - Contains Plagiarism plugin specific functions called by Modules.
 *
 * @since 2.0
 * @package    plagiarism_plagiarisma
 * @subpackage plagiarism
 * @copyright  2010 Dan Marsden http://danmarsden.com
 * @copyright  2015 Plagiarisma.Net http://plagiarisma.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot.'/plagiarism/lib.php');
require_once($CFG->dirroot.'/plagiarism/plagiarisma/textlib.php');

define('PLAGIARISM_PLAGIARISMA_STATUS_SEND', 0);
define('PLAGIARISM_PLAGIARISMA_STATUS_SUCCESS', 1);
define('PLAGIARISM_PLAGIARISMA_STATUS_LOCKED', 2);
define('PLAGIARISM_PLAGIARISMA_STATUS_FAILED', 3);
define('PLAGIARISM_PLAGIARISMA_STATUS_READY', 4);
define('PLAGIARISM_PLAGIARISMA_ATTEMPTS', 99);
define('PLAGIARISM_PLAGIARISMA_URL', 'http://plagiarisma.net/api.php');

class plagiarism_plugin_plagiarisma extends plagiarism_plugin {

    public function get_settings() {
        static $plagiarismsettings;

        if (!empty($plagiarismsettings) or $plagiarismsettings === false) {
            return $plagiarismsettings;
        }
        $plagiarismsettings = (array)get_config('plagiarism');
        // Check if enabled.
        if (isset($plagiarismsettings['plagiarisma_use']) and $plagiarismsettings['plagiarisma_use']) {
            // Now check to make sure required settings are set!
            if (empty($plagiarismsettings['plagiarisma_accountid'])) {
                notify(get_string('id_notset', 'plagiarism_plagiarisma'), 'notifyproblem');
                return false;
            }
            if (empty($plagiarismsettings['plagiarisma_secretkey'])) {
                notify(get_string('key_notset', 'plagiarism_plagiarisma'), 'notifyproblem');
                return false;
            }
            if (isset($_SESSION['plagiarisma_use'])) {
                return $plagiarismsettings;
            }
            // Validate email, apikey and subscription.
            $status = $this->plagiarism_plagiarisma_authorize($plagiarismsettings['plagiarisma_accountid'],
                                                              $plagiarismsettings['plagiarisma_secretkey']);

            if (isset($status['error'])) {
                // Validation failed.
                notify($status['error'], 'notifyproblem');
                unset($_SESSION['plagiarisma_use']);

                return false;
            } else {
                $_SESSION['plagiarisma_use'] = true;

                return $plagiarismsettings;
            }
        } else {
            return false;
        }
    }
    /**
     * hook to allow plagiarism specific information to be displayed beside a submission 
     * @param array  $linkarraycontains all relevant information for the plugin to generate a link
     * @return string
     */
    public function get_links($linkarray) {
        global $COURSE;

        if (!empty($linkarray['file'])) {
            $file = $linkarray['file'];
            $filearea = $file->get_filearea();

            if ($filearea == 'feedback_files') {
                return;
            }
        }
        $plagiarismsettings = $this->get_settings();

        if ($plagiarismsettings === false) {
            return '';
        }

        $plagiarisma = array();
        $plagiarisma['courseId'] = $COURSE->id;
        $plagiarisma['courseTitle'] = $COURSE->fullname;
        $plagiarisma['cmid'] = $linkarray['cmid'];
        $plagiarisma['userid'] = $linkarray['userid'];

        if (!empty($linkarray['assignment']) and !is_number($linkarray['assignment'])) {
            $plagiarisma['assignmentTitle'] = $linkarray['assignment']->name;
        }
        if (!$plagiarismsettings['plagiarisma_disable_dynamic_inline'] and
            !empty($linkarray['content']) and trim($linkarray['content']) != false) {
            $file = array();
            $linkarray['content'] = '<html>'.$linkarray['content'].'</html>';
            $file['filename'] = '.txt';
            $file['type'] = 'inline';
            $file['identifier'] = $plagiarismsettings['plagiarisma_accountid'].'_'.sha1($linkarray['content']);
            $file['filepath'] = '';
            $file['userid'] = $linkarray['userid'];
            $file['size'] = 100;
            $file['content'] = $linkarray['content'];
            $plagiarisma['file'] = $file;
        } else if (!empty($linkarray['file'])) {
            $file = array();
            $file['filename'] = (!empty($linkarray['file']->filename)) ?
                                        $linkarray['file']->filename :
                                        $linkarray['file']->get_filename();
            $file['type'] = 'file';
            $file['identifier'] = $plagiarismsettings['plagiarisma_accountid'].'_'.$linkarray['file']->get_pathnamehash();
            $file['filepath'] = (!empty($linkarray['file']->filepath)) ?
                                        $linkarray['file']->filepath :
                                        $linkarray['file']->get_filepath();
            $file['userid'] = $linkarray['file']->get_userid();
            $file['size'] = $linkarray['file']->get_filesize();
            $plagiarisma['file'] = $file;
        }
        if (!isset($file) or $file['userid'] !== $plagiarisma['userid'] or $file['size'] > 52428800) {
            return "";
        }

        $results = $this->get_file_results($plagiarisma['cmid'],
                                           $plagiarisma['userid'],
                                           !empty($linkarray['file']) ? $linkarray['file'] : null, $plagiarisma);

        if ((empty($results) and isset($_SESSION['plagiarisma_use'])) or is_numeric($results['score']) === false) {
            return '<br/><b>Pending!</b><br/>';
        }
        $rank = $this->plagiarism_plagiarisma_get_css_rank($results['score']);

        $similaritystring = '&nbsp;<span class="' . $rank . '">' . $results['score'] . '%</span>';

        if (!empty($results['reporturl']) and intval($results['score']) >= 0) {
            // User gets to see link to similarity report & similarity score.
            $output = '<span class="plagiarismscore"><a href="' . $results['reporturl'] . '" target="_blank">';
            $output .= get_string('similarity', 'plagiarism_plagiarisma') . ':</a>' . $similaritystring . '</span>';

            return "<br/>$output<br/>";
        } else if (empty($results['reporturl']) and intval($results['score']) >= 0) {
            $output = '<span class="plagiarismscore">'.
                       get_string('similarity', 'plagiarism_plagiarisma').':'.$similaritystring.
                      '</span>';

            return "<br/>$output<br/>";
        }
    }

    public function get_file_results($cmid, $userid, $file, $plagiarisma=null) {
        global $DB, $USER, $COURSE, $OUTPUT, $CFG;

        $plagiarismsettings = $this->get_settings();

        if (empty($plagiarismsettings)) {
            // Plugin is not enabled.
            return false;
        }
        $plagiarismvalues = $DB->get_records_menu('plagiarism_plagiarisma_cfg',
                                                   array('cm' => $plagiarisma['cmid']), '', 'name,value');

        if (empty($plagiarismvalues['use_plagiarisma'])) {
            // Plugin is not in use for this cm.
            return false;
        }
        $modulecontext = context_module::instance($plagiarisma['cmid']);
        // Whether the user has permissions to see all items in the context of this module.
        $viewfullreport = $viewsimilarityscore = has_capability('mod/assign:grade', $modulecontext);

        if ($USER->id == $plagiarisma['userid']) {
            $selfreport = true;
            // The user wants to see details on their own report.
            if ($plagiarismvalues['plagiarism_show_student_score'] == 1) {
                $viewsimilarityscore = true;
            }
            if ($plagiarismvalues['plagiarism_show_student_report'] == 1) {
                $viewfullreport = true;
            }
        } else {
            $selfreport = false;
        }
        if (!$viewsimilarityscore and !$viewfullreport and !$selfreport) {
            // The user has no right to see the requested detail.
            return false;
        }

        $results = array(
            'analyzed' => 0,
            'score' => '',
            'reporturl' => ''
        );

        // First check if we already have looked up the score for this class.
        $fileid = $plagiarisma['file']['identifier'];
        $score = -1;

        $mycontent = '';
        $contentscore = $DB->get_records('plagiarism_plagiarisma_files',
                        array('cm' => $plagiarisma['cmid'], 'userid' => $userid, 'identifier' => $fileid),
                        '', 'id,cm,userid,identifier,similarityscore, timeretrieved, status');

        if (!empty($contentscore)) {
            foreach ($contentscore as $content) {
                $mycontent = $content;
                if ($content->status == constant('PLAGIARISM_PLAGIARISMA_STATUS_READY')) {
                    // Since our reports are dynamic, only use the db as a cache.
                    $score = $content->similarityscore;
                }
                break;
            }
        }
        if ($score < 0 and empty($mycontent)) {
            // Ok can't find the score in the cache and its not scheduled to be uploaded.
            $user = ($userid == $USER->id ? $USER : $DB->get_record('user', array('id' => $userid)));

            $customdata = array(
                'plagiarismsettings' => $plagiarismsettings,
                'courseId' => $COURSE->id,
                'cmid' => $cmid,
                'user' => $user,
                'modulecontext' => $modulecontext,
                'plagiarisma' => $plagiarisma,
                'file' => (!empty($file)) ? serialize($file) : "",
                'dataroot' => $CFG->dataroot,
                'contentUserGradeAssignment' => has_capability('mod/assign:grade', $modulecontext, $user->id)
            );
            // Store for cron job to submit the file.
            $update = true;
            if (empty($mycontent)) {
                $newelement = new object();
                $update = false;
            } else {
                $newelement = $mycontent;
            }
            $newelement->cm = $cmid;
            $newelement->timeretrieved = 0;
            $newelement->identifier = $fileid;
            $newelement->userid = $user->id;
            $newelement->data = serialize($customdata);
            $newelement->status = constant('PLAGIARISM_PLAGIARISMA_STATUS_SEND');

            try {
                if ($update) {
                    $DB->update_record('plagiarism_plagiarisma_files', $newelement);
                } else {
                    $DB->insert_record('plagiarism_plagiarisma_files', $newelement);
                }
            } catch (Exception $e) {
                  print_error($e->getMessage());
            }
        }
        if ($score >= 0) {
            // We have successfully found the score and it has been evaluated.
            $results['analyzed'] = 1;

            if ($viewsimilarityscore) {
                $results['score'] = $score;
            }
            // See if the token already exists.
            $conditions = array('identifier' => $fileid);
            $dbtokens = $DB->get_records('plagiarism_plagiarisma_id', $conditions);

            foreach ($dbtokens as $dbtoken) {
                // We found an existing token, set token and break out.
                $token = $dbtoken->token;

                if ($viewfullreport) {
                    $uid = str_rot13(base64_encode($plagiarismsettings['plagiarisma_accountid']));
                    $results['reporturl'] = 'http://plagiarisma.net/users/'.$uid.'/'.$token.'.html';
                }
                break;
            }
        } else {
                return false;
        }
        return $results;
    }
    /** 
     * hook to save plagiarism specific settings on a module settings page
     * @param object $data - data from an mform submission.
     */
    public function save_form_elements($data) {
        global $DB;

        if (!$this->get_settings()) {
            return;
        }
        $plagiarismelements = $this->config_options();
        // First get existing values.
        $existingelements = $DB->get_records_menu('plagiarism_plagiarisma_cfg', array('cm' => $data->coursemodule), '', 'name,id');

        foreach ($plagiarismelements as $element) {
            $newelement = new object();
            $newelement->cm = $data->coursemodule;
            $newelement->name = $element;
            $newelement->value = (isset($data->$element) ? $data->$element : 0);

            if (isset($existingelements[$element])) {
                $newelement->id = $existingelements[$element];
                $DB->update_record('plagiarism_plagiarisma_cfg', $newelement);
            } else {
                $DB->insert_record('plagiarism_plagiarisma_cfg', $newelement);
            }
        }
    }
    /**
     * hook to add plagiarism specific settings to a module settings page
     * @param object $mform  - Moodle form
     * @param object $context - current context
     */
    public function get_form_elements_module($mform, $context, $modulename = '') {
        global $CFG, $DB;

        $plagiarismsettings = $this->get_settings();

        if (!$plagiarismsettings) {
            return;
        }
        $cmid = optional_param('update', 0, PARAM_INT);

        if (!empty($cmid)) {
            $plagiarismvalues = $DB->get_records_menu('plagiarism_plagiarisma_cfg', array('cm' => $cmid), '', 'name,value');
        }
        $plagiarismelements = $this->config_options();

        $mform->addElement('header', 'plagiarismdesc', get_string('pluginname', 'plagiarism_plagiarisma'));
        $ynoptions = array( 0 => get_string('no'), 1 => get_string('yes'));
        $mform->addElement('checkbox', 'use_plagiarisma', get_string("useplagiarisma", "plagiarism_plagiarisma"));

        if (isset($plagiarismvalues['use_plagiarisma'])) {
            $mform->setDefault('use_plagiarisma', $plagiarismvalues['use_plagiarisma']);
        } else if (isset($plagiarismsettings['plagiarisma_use_default'])) {
            $mform->setDefault('use_plagiarisma', $plagiarismsettings['plagiarisma_use_default']);
        }
        $mform->addElement('checkbox', 'plagiarism_show_student_score',
                            get_string("studentscoreplagiarisma", "plagiarism_plagiarisma"));
        $mform->addHelpButton('plagiarism_show_student_score', 'studentscoreplagiarisma', 'plagiarism_plagiarisma');
        $mform->disabledIf('plagiarism_show_student_score', 'use_plagiarisma');

        if (isset($plagiarismvalues['plagiarism_show_student_score'])) {
            $mform->setDefault('plagiarism_show_student_score', $plagiarismvalues['plagiarism_show_student_score']);
        } else if (isset($plagiarismsettings['plagiarisma_student_score_default'])) {
            $mform->setDefault('plagiarism_show_student_score', $plagiarismsettings['plagiarisma_student_score_default']);
        }
        $mform->addElement('checkbox', 'plagiarism_show_student_report',
                            get_string("studentreportplagiarisma", "plagiarism_plagiarisma"));
        $mform->addHelpButton('plagiarism_show_student_report', 'studentreportplagiarisma', 'plagiarism_plagiarisma');
        $mform->disabledIf('plagiarism_show_student_report', 'use_plagiarisma');

        if (isset($plagiarismvalues['plagiarism_show_student_report'])) {
            $mform->setDefault('plagiarism_show_student_report', $plagiarismvalues['plagiarism_show_student_report']);
        } else if (isset($plagiarismsettings['plagiarisma_student_report_default'])) {
            $mform->setDefault('plagiarism_show_student_report', $plagiarismsettings['plagiarisma_student_report_default']);
        }
    }

    public function config_options() {
        return array('use_plagiarisma', 'plagiarism_show_student_score', 'plagiarism_show_student_report');
    }
    /**
     * hook to allow a disclosure to be printed notifying users what will happen with their submission
     * @param int $cmid - course module id
     * @return string
     */
    public function print_disclosure($cmid) {
        global $OUTPUT;

        $plagiarismsettings = (array)get_config('plagiarism');
        // TODO: check if this cmid has plagiarism enabled.
        echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
        $formatoptions = new stdClass;
        $formatoptions->noclean = true;
        echo format_text($plagiarismsettings['plagiarisma_student_disclosure'], FORMAT_MOODLE, $formatoptions);
        echo $OUTPUT->box_end();
    }
    /**
     * hook to allow status of submitted files to be updated - called on grading/report pages.
     *
     * @param object $course - full Course object
     * @param object $cm - full cm object
     */
    public function update_status($course, $cm) {
        // Called at top of submissions/grading pages - allows printing of admin style links or updating status.
    }
    /**
     * called by admin/cron.php 
     */
    public function cron() {
        global $CFG, $DB, $USER;

        $plagiarismsettings = (array)get_config('plagiarism');

        // Submit queued files.
        $dbfiles = $DB->get_records('plagiarism_plagiarisma_files',
                   array('status' => constant('PLAGIARISM_PLAGIARISMA_STATUS_SEND')),
                         '', 'id, cm, userid, identifier, data, status, attempts');
        if (!empty($dbfiles)) {
            $fileids = array();
            foreach ($dbfiles as $dbfile) {
                // Lock DB records that will be worked on.
                array_push($fileids, $dbfile->id);
            }
            list($dsql, $dparam) = $DB->get_in_or_equal($fileids);
            $DB->execute("update {plagiarism_plagiarisma_files} set status = ".
                          constant('PLAGIARISM_PLAGIARISMA_STATUS_LOCKED')." where id ".$dsql, $dparam);

            foreach ($dbfiles as $dbfile) {
                try {
                    $customdata = unserialize($dbfile->data);

                    if (empty($plagiarismsettings['plagiarisma_accountid']) or
                        empty($plagiarismsettings['plagiarisma_secretkey'])) {
                        throw new Exception('Account Id or API Key not set!');
                    }
                    $plagiarisma = $customdata['plagiarisma'];

                    if (!empty($customdata['file'])) {
                        $file = get_file_storage();
                        $file = unserialize($customdata['file']);
                    }
                    $fields = array();

                    $fields['consumer'] = $plagiarismsettings['plagiarisma_accountid'];
                    $fields['consumerSecret'] = $plagiarismsettings['plagiarisma_secretkey'];
                    $fields['identifier'] = $dbfile->identifier;
                    // Create a tmp file to store data.
                    if (!check_dir_exists($customdata['dataroot'].'/plagiarism/', true, true)) {
                        mkdir($customdata['dataroot'].'/plagiarism/', 0700);
                    }
                    $parts = pathinfo($plagiarisma['file']['filename']);
                    $filename = $customdata['dataroot'].'/plagiarism/'.uniqid().'.'.$parts['extension'];
                    $fh = fopen($filename, 'w');

                    if (!empty($plagiarisma['file']['type']) and $plagiarisma['file']['type'] == 'file') {
                        if (!empty($file->filepath)) {
                            fwrite($fh, file_get_contents($file->filepath));
                        } else {
                            fwrite($fh, $file->get_content());
                        }
                    } else {
                        fwrite($fh, $plagiarisma['file']['content']);
                    }
                    fclose($fh);

                    $txt = $this->plagiarism_plagiarisma_tokenizer($filename);

                    if ($txt === false) {
                        // Error of some sort, do not save.
                        throw new Exception('failed to convert a file...');
                    } else {
                        $fields['fileName'] = $plagiarisma['file']['filename'];
                        $fields['fileData'] = urlencode($txt);
                    }

                    $c = new curl(array('proxy' => true));
                    $status = json_decode($c->post(constant('PLAGIARISM_PLAGIARISMA_URL'), $fields), true);

                    if (!empty($status) and isset($status['token'])) {
                        $newelement = new object ();
                        $newelement->cm = $customdata['cmid'];
                        $newelement->userid = $USER->id;
                        $newelement->identifier = $dbfile->identifier;
                        $newelement->timeretrieved = time ();
                        $newelement->token = $status['token'];
                        $DB->insert_record('plagiarism_plagiarisma_id', $newelement);

                        // Now update the record to show we have retreived it.
                        $dbfile->status = constant('PLAGIARISM_PLAGIARISMA_STATUS_SUCCESS');
                        $dbfile->timeretrieved = time ();
                        $dbfile->data = "";
                        $DB->update_record('plagiarism_plagiarisma_files', $dbfile);

                        unlink($filename);
                    } else {
                            throw new Exception('token is not ready yet...');
                    }
                } catch (Exception $e) {
                    // We found uncompleted task and we will wait until it finish.
                    if ($dbfile->attempts < constant('PLAGIARISM_PLAGIARISMA_ATTEMPTS')) {
                        $dbfile->status = constant('PLAGIARISM_PLAGIARISMA_STATUS_SEND');
                        $dbfile->attempts = $dbfile->attempts + 1;
                    } else {
                        $dbfile->status = constant('PLAGIARISM_PLAGIARISMA_STATUS_FAILED');
                    }
                    $DB->update_record('plagiarism_plagiarisma_files', $dbfile);
                }
            }
        }
        // Check if task ready.
        $dbfiles = $DB->get_records('plagiarism_plagiarisma_files',
                   array('status' => constant('PLAGIARISM_PLAGIARISMA_STATUS_SUCCESS')),
                         '', 'id, cm, userid, identifier, data, status, attempts');

        if (!empty($dbfiles)) {
            $fileids = array();
            foreach ($dbfiles as $dbfile) {
                // Lock DB records that will be worked on.
                array_push($fileids, $dbfile->id);
            }
            list($dsql, $dparam) = $DB->get_in_or_equal($fileids);
            $DB->execute("update {plagiarism_plagiarisma_files} set status = ".
                          constant('PLAGIARISM_PLAGIARISMA_STATUS_LOCKED')." where id ".$dsql, $dparam);

            foreach ($dbfiles as $dbfile) {
                try {
                    $fields = array();

                    $fields['consumer'] = $plagiarismsettings['plagiarisma_accountid'];
                    $fields['consumerSecret'] = $plagiarismsettings['plagiarisma_secretkey'];
                    $fields['identifier'] = $dbfile->identifier;
                    $fields['tokenRequest'] = 'true';

                    $c = new curl(array('proxy' => true));
                    $scores = json_decode($c->post(constant('PLAGIARISM_PLAGIARISMA_URL'), $fields), true);

                    if (!empty($scores) and $scores['result'] == 'end_processing') {
                        // Now update the record to show we have retreived it.
                        $dbfile->similarityscore = $scores['score'];
                        $dbfile->timeretrieved = time ();
                        $dbfile->data = "";
                        $dbfile->status = constant('PLAGIARISM_PLAGIARISMA_STATUS_READY');
                        $DB->update_record('plagiarism_plagiarisma_files', $dbfile);
                    } else {
                        throw new Exception('task is not finished yet...');
                    }
                } catch (Exception $e) {
                    $dbfile->status = constant('PLAGIARISM_PLAGIARISMA_STATUS_SUCCESS');
                    $DB->update_record('plagiarism_plagiarisma_files', $dbfile);
                }
            }
        }
    }
    /**
     * check if user valid and has paid subscription.
     */
    public function plagiarism_plagiarisma_authorize($userid, $secretkey) {
        $fields = array();
        $fields['consumer'] = $userid;
        $fields['consumerSecret'] = $secretkey;
        $fields['authRequest'] = 'true';

        $c = new curl(array('proxy' => true));
        $status = json_decode($c->post(constant('PLAGIARISM_PLAGIARISMA_URL'), $fields), true);

        return $status;
    }
    /**
     * convert score numbers to css colors.
     */
    public function plagiarism_plagiarisma_get_css_rank($score) {
        $rank = 'none';

        if ($score > 90) {
            $rank = '10';
        } else if ($score > 80) {
            $rank = '9';
        } else if ($score > 70) {
            $rank = '8';
        } else if ($score > 60) {
            $rank = '7';
        } else if ($score > 50) {
            $rank = '6';
        } else if ($score > 40) {
            $rank = '5';
        } else if ($score > 30) {
            $rank = '4';
        } else if ($score > 20) {
            $rank = '3';
        } else if ($score > 10) {
            $rank = '2';
        } else if ($score >= 0) {
            $rank = '1';
        }

        return "rank$rank";
    }
    /**
     * it takes a path to a file and returns a string variable that contains plain text extracted from the file.
     */
    public function plagiarism_plagiarisma_tokenizer($path) {
        global $CFG;

        if (is_readable($path)) {
            $parts = pathinfo($path);

            if (!isset($parts['extension'])) {
                return false;
            }
            switch (strtolower($parts['extension'])) {
                case 'pdf':
                    $result = plagiarisma_pdf2txt($path);
                    return $result;

                case 'doc':
                     $result = html_entity_decode(plagiarisma_doc2txt($path), null, 'UTF-8');
                     return $result;

                case 'docx':
                     $result = plagiarisma_convert_zippedxml($path, 'word/document.xml');
                     return $result;

                case 'odt':
                     $result = plagiarisma_convert_zippedxml($path, 'content.xml');
                     return $result;

                case 'rtf':
                    $result = plagiarisma_rtf2txt($path);
                    return mb_convert_encoding($result, 'UTF-8', 'Windows-1252');

                case 'txt':
                    return file_get_contents($path);

                default:
                    return false;
            }
        }
    }
}
