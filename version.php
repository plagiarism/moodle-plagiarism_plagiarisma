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
 * version.php
 *
 * @since 2.0
 * @package    plagiarism_plagiarisma
 * @subpackage plagiarism
 * @copyright  2010 Dan Marsden http://danmarsden.com
 * @copyright  2015 Plagiarisma.Net http://plagiarisma.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2015032400;
$plugin->requires = 2013051406;
$plugin->cron      = 60; // 1 min.
$plugin->component = 'plagiarism_plagiarisma';
$plugin->maturity = MATURITY_STABLE;
$plugin->release = "1.0";
$plugin->dependencies = array('mod_assign' => ANY_VERSION);
