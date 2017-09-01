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
 *
 * upgrade.php
 *
 * @since 2.0
 * @package    plagiarism_plagiarisma
 * @subpackage plagiarism
 * @copyright  2015-2017 Plagiarisma.Net http://plagiarisma.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later 
 */

/**
 * Nothing to update
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_plagiarism_plagiarisma_upgrade($oldversion) {
    if ($oldversion < 2017080700) {
        upgrade_plugin_savepoint(true, 2017080700, 'plagiarism', 'plagiarisma');
    }

    return true;
}
