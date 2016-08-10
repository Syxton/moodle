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
 * The gradebook outcomes report
 *
 * @package   gradereport_outcomes
 * @copyright 2007 Nicolas Connault
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot.'/grade/lib.php');
require($CFG->libdir.'/tablelib.php');

$download = optional_param('download', '', PARAM_ALPHA);
$courseid = required_param('id', PARAM_INT);

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('nocourseid');
}

$url = new moodle_url('/grade/report/outcomes/index.php', array('id' => $course->id));
$PAGE->set_url($url);

require_login($course);

$context = context_course::instance($course->id);
$PAGE->set_context($context);

require_capability('gradereport/outcomes:view', $context);

// First make sure we have proper final grades.
grade_regrade_final_grades_if_required($course);

$table = new \gradereport_outcomes\output\outcomes_table(uniqid(), $course, $context);
$table->define_baseurl($url);

$table->is_downloading($download, 'test', 'testing123');

// Work out the sql for the table.
$outcomes = grade_outcome::fetch_all_available($course->id);
$table->prepare_outcomes($outcomes);

if (!$table->is_downloading()) {
    // Only print headers if not asked to download data.
    print_grade_page_head($course->id, 'report', 'outcomes');
}

$table->out(null, null);

$event = \gradereport_outcomes\event\grade_report_viewed::create(
    array(
        'context' => $context,
        'courseid' => $courseid,
    )
);

$event->trigger();

if (!$table->is_downloading()) {
    echo $OUTPUT->footer();
}
