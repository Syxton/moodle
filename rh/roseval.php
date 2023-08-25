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
 * Rose Hulman Batch course lookup tool.
 *
 * @copyright  2022 onwards Rose-Hulman Institute of Technology
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../config.php');
require_once($CFG->dirroot .'/course/lib.php');
require_once($CFG->libdir .'/filelib.php');

use core_files\archive_writer;

require_login();

$PAGE->set_context(\context_system::instance());
$PAGE->set_url('/roseval.php');
$PAGE->set_title("RosEval Artifact Finder");
$PAGE->set_heading("RosEval Artifact Finder");

$viewallcourses = has_capability('moodle/course:view', \context_system::instance());
if (!$viewallcourses && ($USER->email !== 'lovellmd@rose-hulman.edu' &&
                         $USER->email !== 'chow@rose-hulman.edu' &&
                         $USER->email !== 'daniel@rose-hulman.edu' &&
                         $USER->email !== 'kirby@rose-hulman.edu')) {
    echo $OUTPUT->header();
    echo "You do not have permissions to access this tool.";
    echo $OUTPUT->footer();
    die();
}

global $CFG, $DB;

// Totalcsv is used to compile multiple assignments covering multiple courses.
$totalcsv = ['header' => ['Student ID',
                          'Course Code',
                          'Section ID',
                          'Term',
                          'FileName',
                         ],
            'csv' => [0 => [],
                     ],
];

$totalfilelist = [0 => []];
$groupsubmissionregister = [];

// Parameters.
$tagsearch = optional_param('tagsearch', null, PARAM_TAGLIST);
$namesearch = optional_param('namesearch', null, PARAM_TEXT);
$firstsubmission = optional_param('firstsubmission', null, PARAM_ALPHANUMEXT);
$action = optional_param('action', null, PARAM_ALPHANUMEXT);

// Only echo header and styles if original form or on search results.
if (empty($action) || $action == "search") {
    echo $OUTPUT->header();
    echo '
    <style>
        .tablestyle tr:nth-child(even) {
            background: whitesmoke;
        }
        .tablestyle tr:nth-child(odd) {
            background: white;
        }
        input[type=submit].submitlink {
            border: none;
            background: none;
            display: inline;
        }
        input[type=submit].submitlink:hover {
            text-decoration: underline;
        }
    </style>
    <script type="text/javascript">
        function somethingchecked(event) {
            if(jQuery(\'.selectable:checked\').length > 0) {
                return true;
            }
            event.preventDefault();
        }
        window.addEventListener("load", function() {
            jQuery(\'#selectAll\').click(function() {
                if (this.checked) {
                    jQuery(\'.selectable:checkbox\').each(function() {
                        this.checked = true;
                        $(this).prop("checked", true);
                    });
                } else {
                    jQuery(\'.selectable:checkbox\').each(function() {
                        this.checked = false;
                        $(this).prop("checked", false);
                    });
                }
            });
        });
    </script>';
}

// Search results.
if (!empty($tagsearch) && $action == "search" || $action == "exportsearch") {
    $export = [];
    if ($action == "search") {
        echo '<br />
        <a href="roseval.php">Go Back to Search Form</a>
        <br />
        <h3>Search Results</h3>';

        echo "<table class='tablestyle' style='width:90%;margin:auto;'>";
    }

    if ($cms = search_for_cms($tagsearch, $namesearch, $firstsubmission)) {
        if ($action == "search") {
            echo '<table class="tablestyle" style="width:95%;margin:auto;">
                    <tr>
                        <td style="width:10%">
                            <form id="selectableform" action="roseval.php"
                                method="post" onsubmit="somethingchecked(event);"
                                style="display: inline;">
                                <input name="tagsearch" value="' . $tagsearch . '" type="hidden" />
                                <input name="namesearch" value="' . $namesearch . '" type="hidden" />
                                <input name="firstsubmission" value="' . $firstsubmission . '" type="hidden" />
                                <input name="action" value="downloadselected" type="hidden" />
                                <input type="submit" class="submitlink" value="Download Selected" style="font-weight: bold;">
                            </form>
                        </td>
                        <td style="text-align:center">
                            <strong>Found '.count($cms).' tagged activities</strong>
                            <br />
                            <form id="allform" action="roseval.php" method="post" style="display: hidden;">
                                <input name="tagsearch" value="' . $tagsearch . '" type="hidden" />
                                <input name="namesearch" value="' . $namesearch . '" type="hidden" />
                                <input name="firstsubmission" value="' . $firstsubmission . '" type="hidden" />
                                <input name="action" value="exportsearch" type="hidden" />
                                <input type="submit" class="submitlink" value="Export List" style="font-weight: bold;">
                            </form>
                        </td>
                        <td style="width:140px;text-align:center">
                            <form id="allform" action="roseval.php" method="post" style="display: hidden;">
                                <input name="tagsearch" value="' . $tagsearch . '" type="hidden" />
                                <input name="namesearch" value="' . $namesearch . '" type="hidden" />
                                <input name="firstsubmission" value="' . $firstsubmission . '" type="hidden" />
                                <input name="action" value="downloadall" type="hidden" />
                                <input type="submit" class="submitlink" value="Download All" style="font-weight: bold;">
                            </form>
                        </td>
                    </tr>
                </table>';
            echo '<table class="tablestyle" style="width:95%;margin:auto;">
                    <tr>
                        <td style="width:4%;text-align:center;">
                            <input type="checkbox" id="selectAll" value="selectAll">
                        </td>
                        <td style="width:28%">
                            <strong>Course Name</strong>
                        </td>
                        <td>
                            <strong>Module Name</strong>
                        </td>
                        <td style="width:200px;text-align:center;">
                            <strong>Activity Tags</strong>
                        </td>
                        <td style="width:140px;text-align:center;">
                            <strong>Artifacts</strong>
                        </td>
                    </tr>';
        } else {
            $export[] = ['Department',
                         'Teacher',
                         'Course Fullname',
                         'Course Shortname',
                         'Module Name',
                         'Activity Tags',
                         'Submissions',
                         'Submission Type',
            ];
        }

        $totalsubmissioncount = 0;
        foreach ($cms as $cm) {
            if ($mod = get_coursemodule_from_id($cm->modname, $cm->cmid)) {
                $module = get_full_module($mod);
                $submissioncount = get_module_submission_count($mod, $module);
                $checkbox = ""; $link = "";
                if ($submissioncount > 0) {
                    $link = '<form action="roseval.php" method="post" style="display: inline;">
                                <input name="cmids[]" value="' . $cm->modname . ',' . $cm->cmid . '" type="hidden" />
                                <input name="action" value="download" type="hidden" />
                                <input type="submit" class="submitlink" value="Download" style="font-weight: bold;">
                            </form>';
                    $checkbox = '<input type="checkbox" class="selectable" form="selectableform"
                                        name="cmids[]" value="' . $cm->modname . ',' . $cm->cmid . '" />';
                } else {
                    $link = "No Submissions";
                    $checkbox = '<input type="checkbox" name="disabled[]" disabled />';
                }

                $totalsubmissioncount += $submissioncount;

                $tags = \core_tag_tag::get_item_tags_array('core', 'course_modules', $cm->cmid, 1) ?: ["No Standard Tags"];

                if ($action == "search") {
                    echo '<tr>
                        <td style="text-align:center;">
                            ' . $checkbox . '
                        </td>
                        <td>
                            <a href="' . $CFG->wwwroot . '/course/view.php?id=' . $cm->course . '"
                            data-toggle="tooltip"
                            target="_blank"
                            title="' . $cm->fullname . '">
                                ' . $cm->shortname . '
                            </a>
                        </td>
                        <td>
                            <a href="' . $CFG->wwwroot . '/mod/assign/view.php?id=' . $cm->cmid . '" target="_blank">
                                ' . $mod->name . '
                            </a>
                        </td>
                        <td style="text-align:center;">
                            <a href="#" data-toggle="tooltip" title="' . implode(", ", $tags) . '">Tags</a>
                        </td>
                        <td style="text-align:center;">
                            ' . $link . '
                        </td>
                    </tr>';
                } else {
                    $team = "Individual";
                    if ($cm->modname === "assign") {
                        $team = $module->get_instance()->teamsubmission == 0 ? "Individual" : "Group";
                    }
                    $info = get_course_extra_info($cm->course);
                    $export[] = [$info["dept"],
                                 $info["teachers"],
                                 $cm->fullname,
                                 $cm->shortname,
                                 $mod->name,
                                 implode(", ", $tags),
                                 $submissioncount,
                                 $team,
                    ];
                }
            }
        }
    } else {
        if ($action == "search") {
            echo "<tr>
                <td style='text-align: center;'>
                    <strong>No Artifacts Found</strong>
                </td>
            </tr>";
        }
    }

    if ($action == "search") {
        echo "</table>";

        if (isset($totalsubmissioncount) && $totalsubmissioncount > 0) { // Show Download All button.
            echo '
            <script type="text/javascript">
                window.addEventListener("load", function() {
                    jQuery(\'#allform\').css(\'display\', \'inline\');
                });
            </script>';
        } else { // Remove Download All button.
            echo '
            <script type="text/javascript">
                window.addEventListener("load", function() {
                    jQuery(\'#allform\').remove();
                });
            </script>';
        }
    } else {
        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename=searchexport.csv");
        header("Pragma: no-cache");
        header("Expires: 0");
        $csv = fopen('php://output', 'w');
        foreach ($export as $row) {
            fputcsv($csv, $row);
        }
        fclose($csv);
    }
}

// Original form.
if (empty($tagsearch) && (empty($action) || $action == "search")) {
    // Gather tag information.
    echo '
    <form action="roseval.php" method="post">
        <table>
            <tr>
                <td>
                    <strong>Tag Search</strong><br />
                    <em>
                    Required! Examples: "RoseEval_2a" <b>|</b> "RoseEval_1a OR RosEval_1b" <b>|</b> "RosEval_1a AND RosEval_1b"
                    </em>
                </td>
            </tr>
            <tr>
                <td>
                    <input name="tagsearch" type="text" class="form-control" />
                    <input name="action" value="search" type="hidden" />
                </td>
            </tr>
            <tr><td><br /></td></tr>
            <tr>
                <td>
                    <strong>Course Name Match</strong><br />
                    <em>Is not required e.g. 2223F</em>
                </td>
            </tr>
            <tr><td><input name="namesearch" type="text" class="form-control" /></td></tr>
            <tr><td><br /></td></tr>
            <tr>
                <td>
                    <strong>First Submission After</strong><br />
                    <em>Is not required e.g. 01/01/2023</em> (00/00/0000)
                </td>
            </tr>
            <tr>
                <td>
                    <input name="firstsubmission" type="date" class="form-control" />
                </td>
            </tr>
        </table>
        <br />
        <button type="submit" class="btn btn-primary">Look up courses</button>
    </form>';
}

// Only echo footer if original form or on search results.
if (empty($action) || $action == "search") {
    echo $OUTPUT->footer();
}

// All matching assignment packages download.
if ($action == "downloadall" || $action == "downloadselected" || $action == "download") {
    $zipfilename = "";
    $s = [[" or ", ",", " and ", " "], ["-or-", "-or-", "-and-", ""]];
    if ($action == "downloadselected" || $action == "download") {
        if ($params = optional_param_array('cmids', null, PARAM_RAW)) {
            $cms = [];
            foreach ($params as $param) {
                $p = explode(',', $param);
                if ($action == "download") {
                    $zipfilename .= $p[0] . '-' . $p[1];
                } else {
                    $zipfilename = "select-" . str_replace($s[0], $s[1], strtolower($tagsearch));
                }

                $cms[] = (object) ['modname' => $p[0], 'cmid' => $p[1]];
            }
            $cms = (object) $cms;
        }
    } else {
        $cms = search_for_cms($tagsearch, $namesearch, $firstsubmission);
        $zipfilename = "all-" . str_replace($s[0], $s[1], strtolower($tagsearch));
    }

    if (isset($cms) && !empty($cms)) {
        foreach ($cms as $cm) {
            if ($mod = get_coursemodule_from_id($cm->modname, $cm->cmid)) {
                if ($act = get_activity($mod)) {
                    $course = $DB->get_record('course', ['id' => $mod->course], '*', MUST_EXIST);
                    $downloader = new custom_downloader($act, null);
                    $downloader->load_filelist($course);
                }
            }
        }

        if (!empty($totalfilelist[0])) {
            // Analyze and optimize filelist.
            $downloader->optimize_filelist();

            // ADD CSV TO FILELIST HERE.
            all_csv_to_filelist("import");
            make_all_zips(clean_filename(date("Ymd-Hi-", time()) . $zipfilename) . ".zip");
        }
    }
    die();
}

/**
 * Get course department and teacher.
 * @package core_admin
 * @param int $courseid course id
 * @return array course category and teacher list
 */
function get_course_extra_info($courseid) {
    global $DB;

    $deptsql = "SELECT *
                  FROM {course_categories} c
                  WHERE c.id IN (SELECT category
                                   FROM {course}
                                  WHERE id = :courseid)";
    $department = $DB->get_record_sql($deptsql, ['courseid' => $courseid]);
    $info["dept"] = $department->name;

    $context = context_course::instance($courseid);
    $teachersql = "SELECT *
                        FROM {user} u
                    WHERE u.id IN (SELECT userid
                                        FROM {role_assignments}
                                    WHERE contextid = :contextid
                                        AND roleid = 3)";
    if ($teachers = $DB->get_records_sql($teachersql, ['contextid' => $context->id])) {
        $info["teachers"] = "";
        foreach ($teachers as $t) {
            $info["teachers"] .= empty($info["teachers"]) ? '' : ', ';
            $info["teachers"] .= fullname($t) . ' <' . $t->email . '>';
        }
    }
    return $info;
}

/**
 * Count submissions of given module.
 * @package core_admin
 * @param object $cm course module object
 * @return int return amount of submissions
 */
function get_full_module($mod) {
global $CFG;
    $context = \context_module::instance($mod->id);

    if ($mod->modname == "assign") {
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $assign = new assign($context, $mod, $mod->course);
        return $assign;
    }
    return false;
}

function get_module_submission_count($mod, $module = false) {
global $CFG;
    if (!$module) {
        return 0;
    }

    if ($mod->modname == "assign") {
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        return $module->count_submissions_with_status(ASSIGN_SUBMISSION_STATUS_SUBMITTED);
    }
    return 0;
}

/**
 * Return activity module.
 *
 * @package core_admin
 * @param object $cm course module object
 * @return assign | bool Either an assign object or false
 */
function get_activity($cm) {
    global $CFG;

    $context = \context_module::instance($cm->id);

    if ($cm->modname == "assign") {
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        return new assign($context, $cm, $cm->course);
    }
    return false;
}

/**
 * Create the csv file and add it to the filelist.
 *
 * @package core_admin
 * @param string $csvfilename name of expected csv file
 */
function all_csv_to_filelist($csvfilename) {
    global $totalcsv, $totalfilelist;

    foreach ($totalfilelist as $i => $filelist) {
        $partnum = $i > 0 ? "_part" . ($i + 1) : "";
        $csvfilename .= $partnum;
        if (!empty($totalcsv['csv'][$i])) {
            $csv = fopen('php://temp/maxmemory:'. (5 * 1024 * 1024), 'r+');
            fputcsv($csv, $totalcsv['header']);
            foreach ($totalcsv['csv'][$i] as $id => $rows) {
                foreach ($rows as $row) {
                    fputcsv($csv, $row);
                }
            }
            rewind($csv);
            $totalfilelist[$i]['csv'][$csvfilename . ".csv"] = rtrim(stream_get_contents($csv));
        }
    }
}

/**
 * Create the zip files.
 *
 * @package core_admin
 * @param string $zipfilename name of expected zip file
 */
function make_all_zips(string $zipfilename = "package.zip") {
    global $totalfilelist;

    $packages = [];
    $i = 0; $partnum = "";
    foreach ($totalfilelist as $i => $filelist) {
        if (!empty($filelist)) {
            $partnum = $i > 0 ? "_part" . ($i + 1) : "";
            $filename = "import" . $partnum . "_files.zip";
            $zipwriter = archive_writer::get_file_writer($filename, archive_writer::ZIP_WRITER);
            // Stream the files into the zip.
            foreach ($filelist as $id => $files) {
                foreach ($files as $pathinzip => $file) {
                    if ($file instanceof stored_file) { // Most of cases are stored_file.
                        $zipwriter->add_file_from_stored_file($pathinzip, $file);
                    } else if (is_array($file)) {
                        // Save $file as contents, from onlinetext subplugin.
                        $content = reset($file);
                        $zipwriter->add_file_from_string($pathinzip, $content);
                    } else if (is_file($file)) { // Regular files.
                        $zipwriter->add_file_from_filepath($pathinzip, $file);
                    }
                }
            }

            // Finish the archive.
            $zipwriter->finish();

            $packages[$filename] = $zipwriter->get_path_to_zip();
            $i++;
        }
    }

    $zipwriter = archive_writer::get_file_writer($zipfilename, archive_writer::ZIP_WRITER);

    // Stream the files into the zip.
    foreach ($packages as $pathinzip => $file) {
        if ($file instanceof stored_file) { // Most of cases are stored_file.
            $zipwriter->add_file_from_stored_file($pathinzip, $file);
        } else if (is_array($file)) {
            // Save $file as contents, from onlinetext subplugin.
            $content = reset($file);
            $zipwriter->add_file_from_string($pathinzip, $content);
        } else if (is_file($file)) { // Regular files.
            $zipwriter->add_file_from_filepath($pathinzip, $file);
        }
    }

    // Add CSV to zip.
    foreach ($totalfilelist as $i => $filelist) {
        if (!empty($totalfilelist[$i]['csv'])) {
            $csv = $totalfilelist[$i]['csv'];
            $content = reset($csv);
            $zipwriter->add_file_from_string(key($csv), $content);
        }
    }

    // Finish the archive.
    $zipwriter->finish();
    send_file($zipwriter->get_path_to_zip(), $zipfilename);
}

/**
 * Search for matching course modules.
 *
 * @package core_admin
 * @param string $tagsearch tag string search
 * @param string $namesearch shortname course search
 * @param string $firstsubmission time of earliest submission
 * @return array course modules
 */
function search_for_cms($tagsearch, $namesearch = false, $firstsubmission = false) {
    global $DB;

    // Check to see if there are assignments with this tag.
    $params = [];
    $tags = preg_split("/(\sand\s|\sor\s|\,)/i", $tagsearch);
    if (preg_match("/(\sor\s|\,)/i", trim($tagsearch))) {
        $operand = "OR";
    } else if (preg_match("/(\sand\s)/i", trim($tagsearch))) {
        $operand = "AND";
    }

    $liketag = "";
    foreach ($tags as $i => $t) {
        $liketag .= empty($liketag) ? "" : " $operand ";
        $liketag .= '(' . $DB->sql_like('t.rawname', ":tag$i", false, false) . ')';
        $params["tag$i"] = "%" . $DB->sql_like_escape(trim($t)) . "%";
    }

    $likename = "";
    if (!empty($namesearch)) {
        $likename = 'AND ' . $DB->sql_like('c.shortname', ":name", false, false);
        $params["name"] = "%" . $DB->sql_like_escape(trim($namesearch)) . "%";
    }

    $firstsub = "";
    $firstsubjoin = "";
    if (!empty($firstsubmission)) {
        // Must check for active enrolment submissions only.
        $firstsubjoin = 'JOIN (SELECT min(jas.timecreated) as subcreated, jas.assignment
                                 FROM {assign_submission} jas
                                 JOIN {user_enrolments} jue ON jue.userid = jas.userid
                                 JOIN {course_modules} AS jcm ON jas.assignment = jcm.instance
                                 JOIN {enrol} e ON (e.status = 0 AND e.id = jue.enrolid AND e.courseid = jcm.course)
                                WHERE jas.status = "submitted"
                                GROUP BY jas.assignment) AS sub ON cm.instance = sub.assignment';
        $firstsub = 'AND subcreated >= :timesub';
        $params["timesub"] = strtotime($firstsubmission);
    }

    $sql = "SELECT cm.id AS cmid, cm.instance, cm.module, m.name AS modname, cm.course, c.fullname, c.shortname
              FROM {tag_instance} ti
              JOIN {course_modules} AS cm ON ti.itemid = cm.id
              JOIN {modules} AS m ON cm.module = m.id
              JOIN {course} AS c ON cm.course = c.id
     $firstsubjoin
             WHERE ti.tagid IN (SELECT t.id
                                  FROM {tag} t
                                 WHERE {$liketag})
         $likename
         $firstsub
               AND ti.itemtype = 'course_modules'
          GROUP BY cmid";

    $modules = $DB->get_records_sql($sql, $params);

    return $modules;
}

/**
 * Custom downloader class.
 *
 * @package    core_admin
 * @copyright  2023 Matt Davidson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_downloader {
    /** @var assign the module manager instance. */
    private $manager;

    /** @var stdClass the assign instance record. */
    private $instance;

    /** @var array|null the selected user ids, if any. */
    private $userids = null;

    /** @var int $groupmode the activity group mode. */
    private $groupmode = '';

    /** @var int $groupid the exported groupid. */
    private $groupid = 0;

    /** @var array $prefixes all loaded the student prefixes.
     *
     * A prefix will be converted into a file prefix or a folder name.
     */
    private $prefixes;

    /**
     * Class constructor.
     *
     * @param assign $manager the instance manager
     * @param array|null $userids the user ids to download.
     */
    public function __construct(assign $manager, ?array $userids = null) {
        $this->manager = $manager;
        $this->userids = $userids;
        $this->instance = $manager->get_instance();

        $cm = $manager->get_course_module();
        $this->groupmode = groups_get_activity_groupmode($cm);
        if ($this->groupmode) {
            $this->groupid = groups_get_activity_group($cm, true);
        }
    }

    /**
     * Get all students and begin building filelist.
     *
     * @param stdClass $course the course record
     * @return stdClass|false the filelist or false if none
     */
    public function load_filelist(stdClass $course): bool {
        global $totalfilelist;
        $manager = $this->manager;
        $groupid = $this->groupid;

        // Increase the server timeout to handle the creation and sending of large zip files.
        core_php_time_limit::raise();

        $manager->require_view_grades();

        // Load all users with submit.
        $students = get_enrolled_users(
            $manager->get_context(),
            "mod/assign:submit",
            0,
            'u.*',
            null,
            0,
            0,
            $manager->show_only_active_users()
        );

        // Get all the files for each student.
        foreach ($students as $student) {
            // Download all assigments submission or only selected users.
            if ($this->userids && !in_array($student->id, $this->userids)) {
                continue;
            }
            if (!groups_is_member($groupid, $student->id) && $this->groupmode && $groupid) {
                continue;
            }
            $this->load_student_filelist($student, $course);
        }

        return !empty($totalfilelist['filelist']);
    }

    /**
     * Load an individual student filelist.
     *
     * @param stdClass $student the user record
     * @param stdClass $course the course record
     */
    private function load_student_filelist(stdClass $student, stdClass $course) {
        global $totalfilelist, $groupsubmissionregister;

        $submission = $this->get_student_submission($student);
        if (!$submission) {
            return;
        }

        if ($submission->groupid !== 0) {
            $uniqueteamsubid = $submission->id . $submission->groupid;
            if (in_array($uniqueteamsubid, $groupsubmissionregister)) {
                return;
            }
            $groupsubmissionregister[] = $uniqueteamsubid;
        }

        $prefix = $this->get_student_prefix($student);
        if (isset($this->prefixes[$prefix])) {
            // We already send that file (in group mode).
            return;
        }

        // Find which fileset will be used.
        $fileset = 0;
        $count = count($totalfilelist);
        while ($fileset <= $count) {
            // This fileset is not yet used for this student_course.
            if (!isset($totalfilelist[$fileset][$student->id . "_" . $course->id])) {
                break;
            }
            $fileset++;
        }

        $this->prefixes[$prefix] = $student->id;
        foreach ($this->manager->get_submission_plugins() as $plugin) {
            if (!$plugin->is_enabled() || !$plugin->is_visible()) {
                continue;
            }

            $this->load_submissionplugin_filelist($student, $course, $plugin, $submission, $prefix, $fileset);
        }
    }

    /**
     * Load a submission plugin filelist for a specific user.
     *
     * @param stdClass $student the user record
     * @param stdClass $course course object
     * @param assign_plugin $plugin the submission plugin instance
     * @param stdClass $submission the submission object
     * @param string $prefix the files prefix
     * @param int $fileset set of filelist to use
     */
    private function load_submissionplugin_filelist(
        stdClass $student,
        stdClass $course,
        assign_plugin $plugin,
        stdClass $submission,
        string $prefix,
        int $fileset,
    ) {
        global $totalfilelist, $totalcsv;

        $submission->exportfullpath = true;
        $pluginfiles = $plugin->get_files($submission, $student);

        $i = 1;
        foreach ($pluginfiles as $filename => $file) {
            $filepath = false;
            $subfilename = basename($filename);
            if ($file instanceof stored_file) {
                $filepath = clean_param($file->get_filepath() . $subfilename, PARAM_PATH);
            } else if (is_array($file)) {
                $filepath = clean_param($subfilename, PARAM_PATH);
            }

            if ($filepath) {
                // Give file a unique name ().
                $uniquefilename = $prefix . '_assign['.$submission->assignment.']_submission['.$submission->id.']_file' . $i;
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                $artifactfilename = clean_filename($uniquefilename . "." . $ext);

                // Register studentid_courseid because no duplicates are allowed.
                $totalfilelist[$fileset][$student->id . "_" . $course->id][$artifactfilename] = $file;

                // Load the file in the student's csv record.
                $csvparts = (object) $this->coursecsvparts($course);
                $totalcsv['csv'][$fileset][$student->id . "_" . $course->id][] = ["$student->idnumber",
                                                                                  "$csvparts->code",
                                                                                  "$course->shortname",
                                                                                  "$csvparts->term",
                                                                                  "$artifactfilename",
                ];
            }
            $i++;
        }
    }

    /**
     * Zip filelists that have multiple files and correct csv list.
     *
     */
    public function optimize_filelist() {
        global $totalcsv, $totalfilelist;

        foreach ($totalfilelist as $i => $filelist) {
            foreach ($filelist as $id => $files) {
                // Check if multiple files exist that we will need to zip up.
                $count = count($files);
                if ($count > 1) {
                    $parts = explode("_", $id);
                    // Add zip file to student list.
                    $zippedartifacts = 'student[MID' . $parts[0] . ']_combined[' . $count . ']_files.zip';
                    $zippedartifacts = clean_filename($zippedartifacts);

                    // Creates the zip file of a single students course submissions to be added to an overarching zip file package.
                    $zipwriter = archive_writer::get_file_writer($zippedartifacts, archive_writer::ZIP_WRITER);

                    // Loop through each submission and add to student zipfile.
                    foreach ($files as $path => $file) {
                        if ($file instanceof stored_file) {
                            $zipwriter->add_file_from_stored_file($path, $file);
                        } else if (is_array($file)) {
                            $content = reset($file);
                            $zipwriter->add_file_from_string($path, $content);
                        }
                    }
                    $zipwriter->finish();
                    $pathtozip = $zipwriter->get_path_to_zip();

                    if (is_file($pathtozip)) {
                        // Fix totalfilelist.
                        unset($totalfilelist[$i][$id]);
                        $totalfilelist[$i][$id][$zippedartifacts] = $pathtozip;

                        // Fix totalcsv.
                        $temp = $totalcsv['csv'][$i][$id]; // Save previous values, we need them.
                        unset($totalcsv['csv'][$i][$id]); // Remove all user files because we will replace with the zip file.
                        $totalcsv['csv'][$i][$id][] = [$temp[0][0], $temp[0][1], $temp[0][2], $temp[0][3], "$zippedartifacts"];
                    }
                }
            }
        }
    }

    /**
     * Return the student submission if any.
     *
     * @param stdClass $student the user record
     * @return stdClass|null the user submission or null if none
     */
    private function get_student_submission(stdClass $student): ?stdClass {
        if ($this->instance->teamsubmission) {
            $submission = $this->manager->get_group_submission($student->id, 0, false);
        } else {
            $submission = $this->manager->get_user_submission($student->id, false);
        }
        return $submission ?: null;
    }

    /**
     * Return the file prefix used to generate the each submission folder or file.
     *
     * @param stdClass $student User object
     * @return string the submission prefix
     */
    private function get_student_prefix(stdClass $student): string {
        $manager = $this->manager;

        // Team submissions are by group, not by student.
        if ($this->instance->teamsubmission) {
            $prefix = 'groupmember';
        } else {
            $prefix = 'student';
        }

        $userid = empty($student->idnumber) ? 'MID' . $student->id : 'BID' . $student->idnumber;
        $prefix = clean_filename($prefix . '[' . $userid . ']');
        return $prefix;
    }

    /**
     * Return csv parts of course template.
     *
     * @param stdClass $course Course object
     * @return array $parts Csv parts
     */
    public function coursecsvparts(stdClass $course) {
        $parts = [];
        $terms = ['s' => 'Spring', 'f' => 'Fall', 'su' => 'Summer', 'w'];

        $parts['term'] = $this->find_term($course);

        // Shortname looks like Rose standard "2324F BE314-01".
        if (preg_match("/[0-9]{4}[A-Za-z0-9]{1,2}[\s][^-]+[-][0-9]+/", trim($course->shortname))) {
            // Course Code extraction.
            $p = explode(" " , trim($course->shortname)); // Becomes: [2223F, BE314-01].
            $parts['code'] = explode("-", $p[1])[0]; // Becomes: [BE314, 01].
        } else {
            $parts['code'] = $course->shortname;
        }

        return $parts;
    }

    /**
     * Create the term for csv.
     *
     * @param stdClass $course Course object
     * @return string Term string
     */
    public function find_term(stdClass $course) {
        $terms = ['s' => 'Spring', 'f' => 'Fall', 'su' => 'Summer', 'w' => 'Winter'];
        $quarter = "";
        $term = "";
        $year = "";
        $fullterm = "";

        // Searches: "2324F BE314-01" -> finds 2324F.
        if (preg_match("/[0-9]{4}[A-Za-z0-9]{1,2}/", trim($course->shortname), $term)) {
            $termletter = preg_replace('/\d+/u', '', $term[0]);  // Becomes: 2223F -> F.
            if (isset($terms[strtolower($termletter)])) { // Is the letter F, S, W, or SU?
                $quarter = $terms[strtolower($termletter)] . " Quarter";
            }

            $termyear = substr($term[0], 0, 4); // Becomes: 2223F -> 2223.
            if ((substr($termyear, 0, 2) + 1) == substr($termyear, -2)) { // Format is 2324 or 2425 etc.
                $century = substr(date("Y"), 0, 2); // Gets the 20 from 2023.
                $year = $century . substr($termyear, 0, 2) . '-' . substr($termyear, -2); // Becomes: 2223 -> 22-23.
            } else {
                $year = $termyear; // Probably 2023 or 2024 etc.
            }
        }

        // Last effort to find Quarter.
        if (empty($quarter)) {
            if (preg_match("/(fall|spring|summer|winter|spr|fa|sum|win)/i", trim($course->shortname), $term) ||
                preg_match("/(fall|spring|summer|winter|spr|fa|sum|win)/i", trim($course->fullname), $term)) {
                $quarter = $term[0] . " Quarter";
            }
        }

        // Last effort to find Year.
        if (empty($year)) {
            if (preg_match("/[0-9]{4}/", trim($course->shortname), $term) ||
                preg_match("/[0-9]{4}/", trim($course->fullname), $term)) {
                if ((substr($term[0], 0, 2) + 1) == substr($term[0], -2)) { // Format is 2324 or 2425 etc.
                    $century = substr(date("Y"), 0, 2); // Gets the 20 from 2023.
                    $year = $century . substr($term[0], 0, 2) . '-' . substr($term[0], -2); // 2223 -> 22-23
                } else {
                    $year = $term[0]; // Probably 2023 or 2024 etc.
                }
            }
        }

        if (!empty($quarter) && !empty($year)) { // We have both.
            $fullterm = $quarter .' - ' . $year;
        } else { // We are missing one or both.
            $fullterm = "Unknown " . $quarter . $year;
        }

        return $fullterm;
    }
}
