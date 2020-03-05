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
 * Delete empty course sections
 *
 * @package    core_course
 * @copyright  1999 onwards Martin Dougiamas (http://dougiamas.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../config.php');
require_once($CFG->dirroot.'/course/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$confirmdelete = required_param('delete', PARAM_BOOL);
$returnurl = optional_param('returnurl', null, PARAM_LOCALURL);    // Where to return to after the action.
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$PAGE->set_url('/course/deleteemptysections.php', array('courseid' => $courseid));

// Authorisation checks.
require_login($course);
require_capability('moodle/course:update', context_course::instance($course->id));
require_sesskey();

if ($confirmdelete) {
    course_delete_empty_sections($course);
}

if (!$returnurl) {
    $returnurl = course_get_url($course);
}

// Redirect to where we were..
redirect($returnurl);