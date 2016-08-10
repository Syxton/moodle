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
 * Renderable class for gradeoutcomes report.
 *
 * @package    gradereport_outcomes
 * @copyright  2014 onwards Ankit Agarwal <ankit.agrr@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace gradereport_outcomes\output;

defined('MOODLE_INTERNAL') || die;

/**
 * Renderable class for gradeoutcomes report.
 *
 * @since      Moodle 3.2
 * @package    gradereport_outcomes
 * @copyright  2014 onwards Ankit Agarwal <ankit.agrr@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class outcomes_table extends \table_sql {

    /**
     * @var object course.
     */
    protected $course;

    /**
     * @var \context context of the page to be rendered.
     */
    protected $context;

    /**
     * @var object outcomes array.
     */
    public $outcomes;

    /**
     * Constructor
     * @param int $uniqueid all tables have to have a unique id.
     * @param object $course Course object.
     * @param \context_course $context Context of the report.
     */
    public function __construct($uniqueid, $course, $context) {
        parent::__construct($uniqueid);

        $this->course = $course;
        $this->context  = $context;

        // Define the list of columns to show.
        $columns = array('shortname', 'courseavg', 'sitewide', 'activities', 'average', 'numberofgrades');
        $this->define_columns($columns);

        // Define the titles of columns to show in header.
        $headers = array(get_string('outcomeshortname', 'grades'),
                         get_string('courseavg', 'grades'),
                         get_string('sitewide', 'grades'),
                         get_string('activities', 'grades'),
                         get_string('average', 'grades'),
                         get_string('numberofgrades', 'grades'));
        $this->define_headers($headers);

        // Disable sorting and collapsing column features.
        $this->collapsible(false);
        $this->no_sorting('shortname');
        $this->no_sorting('courseavg');
        $this->no_sorting('sitewide');
        $this->no_sorting('activities');
        $this->no_sorting('average');
        $this->no_sorting('numberofgrades');

        // Assign class outcomes_main to all table columns.
        $this->column_class('shortname', 'outcomes_main');
        $this->column_class('courseavg', 'outcomes_main');
        $this->column_class('sitewide', 'outcomes_main');
        $this->column_class('activities', 'outcomes_main');
        $this->column_class('average', 'outcomes_main');
        $this->column_class('numberofgrades', 'outcomes_main');

        // Assign class outcomes_sub to columns that could contain a sub table.
        $this->column_class('activities', 'outcomes_sub');
        $this->column_class('average', 'outcomes_sub');
        $this->column_class('numberofgrades', 'outcomes_sub');
    }

    /**
     * Method to display outcome shortname.
     *
     * @param \stdClass $outcome an entry of outcome record.
     *
     * @return string HTML to display
     */
    public function col_shortname(\stdClass $outcome) {
        return $outcome->shortname;
    }

    /**
     * Method to display outcome course average.
     *
     * @param \stdClass $outcome an entry of outcome record.
     *
     * @return string HTML to display
     */
    public function col_courseavg(\stdClass $outcome) {
        return $outcome->courseavg;
    }

    /**
     * Method to display outcome sitewide field.
     *
     * @param \stdClass $outcome an entry of outcome record.
     *
     * @return string HTML to display
     */
    public function col_sitewide(\stdClass $outcome) {
        return $outcome->sitewide;
    }

    /**
     * Method to display outcome activities.
     *
     * @param \stdClass $outcome an entry of outcome record.
     *
     * @return string HTML to display
     */
    public function col_activities(\stdClass $outcome) {
        global $CFG;
        $html = "";

        if (!$this->is_downloading()) {
            $htmltable = new \html_table();
            $htmltable->attributes["class"] = "sub_table";
            $htmltable->attributes["style"] = "width:100%;";
        }

        $i = 0;
        foreach ($outcome->activities as $val) {
            if (!$this->is_downloading()) {
                if (gettype($val) == "array") {
                    $link = \html_writer::link($CFG->wwwroot.'/mod/'.$val[0].'/view.php?id='.$val[1], $val[2]);
                    $row = new \html_table_row(array($link));
                    if ($i > 0) {
                        $row->attributes["class"] = "top_border";   
                    }
                } else {
                    $row = new \html_table_row(array($val));
                }
                $htmltable->data[] = $row;
            } else {
                if ($this->download == 'html') {
                    if (gettype($val) == "array") {
                        $link = \html_writer::link($CFG->wwwroot.'/mod/'.$val[0].'/view.php?id='.$val[1], $val[2]);
                        $html .= empty($html) ? $link : "<br />" . $link;
                    } else {
                        $html .= empty($html) ? $val : "<br />" . $val;
                    }
                } else if ($this->download == 'json') {
                    if (gettype($val) == "array") {
                        $html .= empty($html) ? $val[2] : "," . $val[2];
                    } else {
                        $html .= empty($html) ? $val : "," . $val;
                    }
                } else {
                    if (gettype($val) == "array") {
                        $html .= empty($html) ? $val[2] : "\n" . $val[2];
                    } else {
                        $html .= empty($html) ? $val : "\n" . $val;
                    }
                }
            }
            $i++;
        }

        if (!$this->is_downloading()) {
            $html = \html_writer::table($htmltable);
        }

        return $html;
    }

    /**
     * Method to display outcome activity averages.
     *
     * @param \stdClass $outcome an entry of outcome record.
     *
     * @return string HTML to display
     */
    public function col_average(\stdClass $outcome) {
        $html = "";

        if (!$this->is_downloading()) {
            $htmltable = new \html_table();
            $htmltable->attributes["class"] = "sub_table";
            $htmltable->attributes["style"] = "width:100%;";
        }

        $i = 0;
        foreach ($outcome->average as $val) {
            if (!$this->is_downloading()) {
                $row = new \html_table_row(array($val));
                if ($i > 0) {
                    $row->attributes["class"] = "top_border";   
                }
                $htmltable->data[] = $row;
            } else {
                if ($this->download == 'html') {
                    $html .= empty($html) ? $val : "<br />" . $val;
                } else if ($this->download == 'json') {
                    $html .= empty($html) ? $val : "," . $val;
                } else {
                    $html .= empty($html) ? $val : "\n" . $val;
                }
            }
            $i++;
        }

        if (!$this->is_downloading()) {
            $html = \html_writer::table($htmltable);
        }

        return $html;
    }

    /**
     * Method to display outcome number of grades field.
     *
     * @param \stdClass $outcome an entry of outcome record.
     *
     * @return string HTML to display
     */
    public function col_numberofgrades(\stdClass $outcome) {
        $html = "";

        if (!$this->is_downloading()) {
            $htmltable = new \html_table();
            $htmltable->attributes["class"] = "sub_table";
            $htmltable->attributes["style"] = "width:100%;";
        }

        $i = 0;
        foreach ($outcome->numberofgrades as $val) {
            if (!$this->is_downloading()) {
                $row = new \html_table_row(array($val));
                if ($i > 0) {
                    $row->attributes["class"] = "top_border";   
                }
                $htmltable->data[] = $row;
            } else {
                if ($this->download == 'html') {
                    $html .= empty($html) ? $val : "<br />" . $val;
                } else if ($this->download == 'json') {
                    $html .= empty($html) ? $val : "," . $val;
                } else {
                    $html .= empty($html) ? $val : "\n" . $val;
                }
            }
            $i++;
        }

        if (!$this->is_downloading()) {
            $html = \html_writer::table($htmltable);
        }

        return $html;
    }

    /**
     * Method to overwrite database query.
     *
     * @param int $pagesize not used.
     * @param bool $useinitialsbar not used.
     *
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        $this->rawdata = $this->outcomes;
    }

    /**
     * Method to gather data for the report.
     *
     * @param \stdClass $outcome an entry of outcome record.
     *
     */
    public function prepare_outcomes($outcomes) {
        global $DB;
        // Grab all outcomes used in course.
        $reportinfo = array();

        // Will exclude grades of suspended users if required.
        $defaultgradeshowactiveenrol = !empty($CFG->grade_report_showonlyactiveenrol);
        $showonlyactiveenrol = get_user_preferences('grade_report_showonlyactiveenrol', $defaultgradeshowactiveenrol);
        $showonlyactiveenrol = $showonlyactiveenrol || !has_capability('moodle/course:viewsuspendedusers', $this->context);
        if ($showonlyactiveenrol) {
            $suspendedusers = get_suspended_userids($this->context);
        }

        // Get grade_items that use each outcome.
        foreach ($outcomes as $outcomeid => $outcome) {
            $info = $DB->get_records_select('grade_items', "outcomeid = ? AND courseid = ?", array($outcomeid, $this->course->id));
            $reportinfo[$outcomeid]['items'] = $info;
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
                             WHERE itemid = ?".
                             $hidesuspendedsql.
                          " GROUP BY itemid";
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
        $this->outcomes = $this->process_report($reportinfo);
    }

    /**
     * Method to format the data for the report.
     *
     * @param array $reportinfo data for the report.
     *
     * @return array outcome data.
     */
    private function process_report($reportinfo) {
        $outcomes = array();
        foreach ($reportinfo as $outcomeid => $outcomedata) {
            $activities = array();
            $average = array();
            $numberofgrades = array();

            $outcomedata['outcome']->sum = 0;
            $scale = new \grade_scale(array('id' => $outcomedata['outcome']->scaleid), false);

            $sitewide = get_string('no');
            if (empty($outcomedata['outcome']->courseid)) {
                $sitewide = get_string('yes');
            }

            if (!empty($outcomedata['items'])) {
                foreach ($outcomedata['items'] as $itemid => $item) {
                    $gradeitem = new \grade_item($item, false);

                    if ($item->itemtype == 'mod') {
                        $cm = get_coursemodule_from_instance($item->itemmodule, $item->iteminstance, $item->courseid);
                        $itemname = array($item->itemmodule, $cm->id, format_string($cm->name, true, $cm->course));
                    } else {
                        $itemname = $gradeitem->get_name();
                    }

                    $outcomedata['outcome']->sum += $item->avg;

                    $activities[] = $itemname;
                    $average[] = $scale->get_nearest_item($item->avg) . " ($item->avg)";
                    $numberofgrades[] = $item->count;
                }
            } else {
                $activities[] = "-";
                $average[] = "-";
                $numberofgrades[] = "0";
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

            $outcomes[] = array('shortname' => trim($outcomedata['outcome']->shortname),
                                'courseavg' => trim($avghtml),
                                'sitewide' => trim($sitewide),
                                'activities' => $activities,
                                'average' => $average,
                                'numberofgrades' => $numberofgrades);
        }
        return $outcomes;
    }
}
