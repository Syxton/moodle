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
 * Exports selected outcomes in CSV format
 *
 * @package   gradereport_outcomes
 * @copyright 2008 Moodle Pty Ltd (http://moodle.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../../config.php');
require_once($CFG->dirroot.'/grade/lib.php');
require_once($CFG->libdir.'/gradelib.php');

$courseid = required_param('id', PARAM_INT);

require_sesskey();

header("Content-Type: text/csv; charset=utf-8");
// TODO: make the filename more useful, include a date, a specific name, something...
header('Content-Disposition: attachment; filename=outcomes_report.csv');

// Make sure they can even access this course.
if ($courseid) {
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    require_login($course);
    $context = context_course::instance($course->id);
    require_capability('moodle/grade:manageoutcomes', $context);

    if (empty($CFG->enableoutcomes)) {
        redirect('../../index.php?id='.$courseid);
    }
} else {
    if (empty($CFG->enableoutcomes)) {
        redirect('../../../');
    }
    require_once($CFG->libdir.'/adminlib.php');
    admin_externalpage_setup('outcomes');
}


// Grab all outcomes used in course.
$reportinfo = array();
$outcomes = grade_outcome::fetch_all_available($courseid);

// Will exclude grades of suspended users if required.
$defaultgradeshowactiveenrol = !empty($CFG->grade_report_showonlyactiveenrol);
$showonlyactiveenrol = get_user_preferences('grade_report_showonlyactiveenrol', $defaultgradeshowactiveenrol);
$showonlyactiveenrol = $showonlyactiveenrol || !has_capability('moodle/course:viewsuspendedusers', $context);
if ($showonlyactiveenrol) {
    $suspendedusers = get_suspended_userids($context);
}

// Get grade_items that use each outcome.
foreach ($outcomes as $outcomeid => $outcome) {
    $items = $DB->get_records_select('grade_items', "outcomeid = ? AND courseid = ?", array($outcomeid, $courseid));
    $reportinfo[$outcomeid]['items'] = $items;
    $reportinfo[$outcomeid]['outcome'] = $outcome;

    // Get average grades for each item.
    if (is_array($reportinfo[$outcomeid]['items'])) {
        foreach ($reportinfo[$outcomeid]['items'] as $itemid => $item) {
            $params = array();
            $hidesuspendedsql = '';
            if ($showonlyactiveenrol && !empty($suspendedusers)) {
                list($notinusers, $params) = $DB->get_in_or_equal($suspendedusers, SQL_PARAMS_QM, null, false);
                $hidesuspendedsql = ' AND userid ' . $notinusers;
            }
            $params = array_merge(array($itemid), $params);

            $sql = "SELECT itemid, AVG(finalgrade) AS avg, COUNT(finalgrade) AS count
                    FROM {grade_grades}
                    WHERE itemid = ? $hidesuspendedsql
                    GROUP BY itemid";
            $info = $DB->get_records_sql($sql, $params);

            if (!$info) {
                unset($reportinfo[$outcomeid]['items'][$itemid]);
                continue;
            } else {
                $info = reset($info);
                $avg = round($info->avg, 2);
                $count = $info->count;
            }

            $reportinfo[$outcomeid]['items'][$itemid]->avg = $avg;
            $reportinfo[$outcomeid]['items'][$itemid]->count = $count;
        }
    }
}

$header = array(get_string('outcomeshortname', 'grades'),
                get_string('courseavg', 'grades'),
                get_string('sitewide', 'grades'),
                get_string('activities', 'grades'),
                get_string('average', 'grades'),
                get_string('numberofgrades', 'grades'));

echo format_csv($header, ',', '"');

foreach ($reportinfo as $outcomeid => $outcomedata) {
    $line = array();

    $outcomedata['outcome']->sum = 0;
    $scale = new grade_scale(array('id' => $outcomedata['outcome']->scaleid), false);

    $sitewide = get_string('no');
    if (empty($outcomedata['outcome']->courseid)) {
        $sitewide = get_string('yes');
    }

    if (!empty($outcomedata['items'])) {
        foreach ($outcomedata['items'] as $itemid => $item) {
            $gradeitem = new grade_item($item, false);

            if ($item->itemtype == 'mod') {
                $cm = get_coursemodule_from_instance($item->itemmodule, $item->iteminstance, $item->courseid);
                $itemname = format_string($cm->name, true, $cm->course);
            } else {
                $itemname = $gradeitem->get_name();
            }

            $outcomedata['outcome']->sum += $item->avg;

            $line[] = $itemname;
            $line[] = $scale->get_nearest_item($item->avg) . " ($item->avg)";
            $line[] = $item->count;
        }
    } else {
        $line[] = "-";
        $line[] = "-";
        $line[] = "0";
    }

    // Calculate outcome average.
    if (is_array($outcomedata['items'])) {
        $count = count($outcomedata['items']);
        if ($count > 0) {
            $avg = $outcomedata['outcome']->sum / $count;
        } else {
            $avg = $outcomedata['outcome']->sum;
        }
        $avghtml = $scale->get_nearest_item($avg) . " (" . round($avg, 2) . ")\n";
    } else {
        $avghtml = "-";
    }

    array_unshift($line, trim($outcomedata['outcome']->shortname),
                         trim($avghtml),
                         trim($sitewide));

    echo format_csv($line, ',', '"');
}

/**
 * Formats and returns a line of data, in CSV format. This code
 * is from http://au2.php.net/manual/en/function.fputcsv.php#77866
 *
 * @param string[] $fields data to be exported
 * @param string $delimiter char to be used to separate fields
 * @param string $enclosure char used to enclose strings that contains newlines, spaces, tabs or the delimiter char itself
 * @return string one line of csv data
 */
function format_csv($fields = array(), $delimiter = ';', $enclosure = '"') {
    $str = '';
    $escapechar = '\\';
    foreach ($fields as $value) {
        if (strpos($value, $delimiter) !== false ||
                strpos($value, $enclosure) !== false ||
                strpos($value, "\n") !== false ||
                strpos($value, "\r") !== false ||
                strpos($value, "\t") !== false ||
                strpos($value, ' ') !== false) {
            $str2 = $enclosure;
            $escaped = 0;
            $len = strlen($value);
            for ($i = 0; $i < $len; $i++) {
                if ($value[$i] == $escapechar) {
                    $escaped = 1;
                } else if (!$escaped && $value[$i] == $enclosure) {
                    $str2 .= $enclosure;
                } else {
                    $escaped = 0;
                }
                $str2 .= $value[$i];
            }
            $str2 .= $enclosure;
            $str .= $str2.$delimiter;
        } else {
            $str .= $value.$delimiter;
        }
    }
    $str = substr($str, 0, -1);
    $str .= "\r\n";

    return $str;
}
