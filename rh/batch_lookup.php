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

require_login();

$batchinfo = optional_param('batchinfo', null, PARAM_RAW);
$quarter = optional_param('quarter', null, PARAM_ALPHANUM);
$PAGE->set_context(context_system::instance());
$PAGE->set_url('/batch_lookup.php');
$PAGE->set_title("Batch Course Lookup");
$PAGE->set_heading("Batch Course Lookup");

$viewallcourses = has_capability('moodle/course:view', context_system::instance());
if (!$viewallcourses && ($USER->email !== 'ewen@rose-hulman.edu' &&
                         $USER->email !== 'hendrix2@rose-hulman.edu' &&
                         $USER->email !== 'brimber1@rose-hulman.edu')) {
    echo $OUTPUT->header();
    echo "You do not have permissions to access this tool.";
    echo $OUTPUT->footer();
    die();
}

echo $OUTPUT->header();

if (empty($batchinfo)) {
    // Gather batch information form. Paste in CSV format.
    echo "<form action='batch_lookup.php' method='post'>";
    echo "<table>";
    echo "<tr><td><strong>Year/Quarter</strong><br />
    <em>Is not required e.g. 1920S</em>
    </td></tr><tr><td><input name='quarter' /></td></tr>";
    echo "<tr><td><br /></td></tr>";
    echo "<tr><td><strong>Batch Information</strong><br />
    <em>Paste in CSV format. All three pieces of information are required. e.g. CHE 311 11</em><br />
                  Category&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Course #&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Section #</td></tr>";
    echo "<tr><td><textarea name='batchinfo' rows='10' cols='50'></textarea></td></tr>";
    echo "</table>";
    echo "<input type='submit' value='Look up courses'>";
    echo "</form>";
} else { // Print results.
    global $DB;
    $batchinfo = explode("\n", $batchinfo);
    $batchinfo = array_map('trim', $batchinfo);

    // Alternating row background colors.
    echo "<style>
            .tablestyle tr:nth-child(even) {
                background: whitesmoke;
            }
            .tablestyle tr:nth-child(odd) {
                background: white;
            }
          </style>";
    echo '<script>
            function fallbackCopyTextToClipboard(text) {
                var textArea = document.createElement("textarea");
                textArea.value = text;

                // Avoid scrolling to bottom
                textArea.style.top = "0";
                textArea.style.left = "0";
                textArea.style.position = "fixed";

                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();

                try {
                    var successful = document.execCommand("copy");
                    var msg = successful ? "successful" : "unsuccessful";
                    console.log("Fallback: Copying text command was " + msg);
                } catch (err) {
                    console.error("Fallback: Oops, unable to copy", err);
                }

                document.body.removeChild(textArea);
            }
            function copyTextToClipboard(text) {
                if (!navigator.clipboard) {
                    fallbackCopyTextToClipboard(text);
                    var $temp = $("<div class=\'unicodehelper_copynotice\'>Copied</div>");
                        $("body").append($temp);
                        $(".unicodehelper_copynotice").fadeOut(1000, function() { $(this).remove(); });
                    return;
                }
                navigator.clipboard.writeText(text).then(function() {
                    var $temp = $("<div class=\'unicodehelper_copynotice\'>Copied</div>");
                        $("body").append($temp);
                        $(".unicodehelper_copynotice").fadeOut(1000, function() { $(this).remove(); });
                }, function(err) {
                    console.error("Async: Could not copy text: ", err);
                });
            }
          </script>';
    echo "<h1>Course Lookup Results</h1>";

    $data = $courseiddata = $metaiddata = $bothiddata = $emaildata = "";
    foreach ($batchinfo as $courseinfo) {
        $email = "";
        $coursenames = $courseid = $info = $metaid = "&nbsp;";
        $courseinfo = preg_split('/\s+/', $courseinfo, -1, PREG_SPLIT_NO_EMPTY); // Split on any whitespace.
        if (!empty($courseinfo[0]) && !empty($courseinfo[1]) && !empty($courseinfo[2])) {
            $cat = trim($courseinfo[0]);
            $crsnum = trim($courseinfo[1]);
            $section = trim($courseinfo[2]);

            // Fix for ONL courses.
            if (strpos($section, 'ONL') === false &&
                strpos($section, 'OL') === false &&
                strpos($section, 'SS') === false) {
                $section = $section > 9 ? $section : "0" . $section;
            }

            $shortname = empty($quarter) ? $cat . $crsnum . "-" . $section : $quarter . " " . $cat . $crsnum . "-" . $section;
            $sql = "SELECT *
                    FROM {course} c
                    WHERE {$DB->sql_like('c.shortname', ':shortname', false, false)} ORDER BY c.id DESC";

            if (!$course = $DB->get_record_sql($sql, ['shortname' => "%$shortname%"])) { // Look for course again if failed.
                // Add "S" after category and try again.  Should fix ECON -> ECONS.
                $shortname = empty($quarter) ? $cat . "S" . $crsnum . "-" . $section :
                                               $quarter . " " . $cat . "S" . $crsnum . "-" . $section;
                $course = $DB->get_record_sql($sql, ['shortname' => "%$shortname%"]);
            }

            if ($course) {
                $coursenames = '<a href="' . $CFG->wwwroot . '/course/view.php?id=' . $course->id . '" target="_blank">' .
                                    $course->shortname .
                               '</a>';
                $courseid = $course->id;

                // Check to see if the course is a meta-enrolled course.
                $metasql = "SELECT *
                            FROM {enrol}
                            WHERE customint1 = :courseid
                            AND enrol = 'meta'
                            AND status = 0";
                if ($meta = $DB->get_record_sql($metasql, ['courseid' => $course->id])) {
                    $metaid = $meta->courseid;
                }

                // Check to see if the course is a meta parent.
                $metasql = "SELECT *
                            FROM {enrol}
                            WHERE courseid = :courseid
                            AND enrol = 'meta'
                            AND status = 0";
                if ($metas = $DB->get_records_sql($metasql, ['courseid' => $course->id])) {
                    $metaid = $course->id;
                }

                // Get teacher email address.
                $context = context_course::instance($course->id);
                $teachersql = "SELECT *
                               FROM {role_assignments}
                               WHERE contextid = :contextid
                               AND roleid = 3";
                if ($teachers = $DB->get_records_sql($teachersql, ['contextid' => $context->id], 0, 1)) {
                    foreach ($teachers as $teacher) {
                        $email = $DB->get_field('user', 'email', ['id' => $teacher->userid]);
                    }
                }
            } else {
                $coursenames = "$shortname Not Found";
            }
        }

        $data .= "<tr>
                    <td>
                        $coursenames
                    </td>
                    <td style='text-align:center;'>
                        $metaid
                    </td>
                    <td style='text-align:center;'>
                        $courseid
                    </td>
                    <td style='text-align:center;'>
                        $email
                    </td>
                </tr>";
        $courseiddata .= $courseid . "\\n";
        $metaiddata .= $metaid . "\\n";
        $bothiddata .= $metaid . "	" . $courseid . "\\n";
        $emaildata .= $email . "\\n";
    }

    echo '<table style="width:70%;margin:auto;">
            <tr>
                <td style="width:40%">
                </td>
                <td style="text-align:right;font-size:0;">
                    <img style="width: 15px;height: 30px;"
                    src="data:image/jpeg;base64, iVBORw0KGgoAAAANSUhEUgAAAA8AAAAeCAIAAAB8PtMjAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAABXSURBVDhPY/z//z8D0YAJShMHRlVjgqGqGiWdMDIyQlk4wGiYQACyArLMJhjSEGWDLgSRPY4J4LIIs3FpQBanwN2YxqOJoJuNLI2pGYtLIIowlTIwMAAAtpIwElVmOWYAAAAASUVORK5CYII=" />
                    <img style="width: calc(50% - 33px);height: 30px;"
                    src="data:image/jpeg;base64, iVBORw0KGgoAAAANSUhEUgAAAAEAAAAeCAIAAABi9+OQAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAAAWSURBVBhXY/j//z9JGBfAphYn/v8fANCIR7mKxAkaAAAAAElFTkSuQmCC" />
                </td>
                <td style="text-align:center;min-width:100px;width:5%">
                    <button onclick="copyTextToClipboard(\''.$bothiddata.'\')">Copy Both</button>
                </td>
                <td style="text-align:left;font-size:0;">
                    <img style="width: calc(50% - 33px);height: 30px;"
                    src="data:image/jpeg;base64, iVBORw0KGgoAAAANSUhEUgAAAAEAAAAeCAIAAABi9+OQAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAAAWSURBVBhXY/j//z9JGBfAphYn/v8fANCIR7mKxAkaAAAAAElFTkSuQmCC" />
                    <img style="width: 15px;height: 30px;-webkit-transform: scaleX(-1);transform: scaleX(-1);"
                    src="data:image/jpeg;base64, iVBORw0KGgoAAAANSUhEUgAAAA8AAAAeCAIAAAB8PtMjAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAABXSURBVDhPY/z//z8D0YAJShMHRlVjgqGqGiWdMDIyQlk4wGiYQACyArLMJhjSEGWDLgSRPY4J4LIIs3FpQBanwN2YxqOJoJuNLI2pGYtLIIowlTIwMAAAtpIwElVmOWYAAAAASUVORK5CYII=" />
                </td>
                <td style="width:30%;">
                </td>
            </tr>
        </table>
        <table style="width:70%;margin:auto;">
            <tr>
                <td style="width:40%">
                </td>
                <td style="width:15%;text-align:center;">
                    <button onclick="copyTextToClipboard(\''.$metaiddata.'\')">Copy</button>
                </td>
                <td style="width:15%;text-align:center;">
                    <button onclick="copyTextToClipboard(\''.$courseiddata.'\')">Copy</button>
                </td>
                <td style="width:30%;text-align:center;">
                    <button onclick="copyTextToClipboard(\''.$emaildata.'\')">Copy</button>
                </td>
            </tr>
          </table>';

    echo "<table class='tablestyle' style='width:70%;margin:auto;'>
            <tr>
                <td style='width:40%'>
                    <strong>Course Name</strong>
                </td>
                <td style='width:15%;text-align:center;'>
                    <strong>Meta Parent Course ID</strong>
                </td>
                <td style='width:15%;text-align:center;'>
                    <strong>Course ID</strong>
                </td>
                <td style='width:30%;text-align:center;'>
                    <strong>Teacher Email</strong>
                </td>
            </tr>" . $data . "</table>";
}

echo $OUTPUT->footer();
