<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Rose Hulman Batch course lookup tool.
 *
 * @copyright  2022 onwards Rose-Hulman Institute of Technology
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/filelib.php');

require_login();

$batchinfo = optional_param('batchinfo', null, PARAM_RAW);
$quarter = optional_param('quarter', null, PARAM_ALPHANUM);

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url('/batch_lookup.php');
$PAGE->set_title('Batch Course Lookup');
$PAGE->set_heading('Batch Course Lookup');

$rowheight = 28;

$viewallcourses = has_capability('moodle/course:view', $context);

if (!$viewallcourses && !in_array($USER->email, [
    'ewen@rose-hulman.edu',
    'hendrix2@rose-hulman.edu',
    'brimber1@rose-hulman.edu',
], true)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(
        'You do not have permissions to access this tool.',
        \core\output\notification::NOTIFY_ERROR
    );
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();

if (empty($batchinfo)) {
    echo '
        <form action="batch_lookup.php" method="post">
            <div><strong>Year/Quarter</strong></div>
            <div><em>Is not required e.g. 1920S</em></div>
            <div><input name="quarter" /></div>
            <br />
            <div><strong>Batch Information</strong></div>
            <div>
                <em>Paste in CSV format. All three pieces of information are required. e.g. CHE 311 11</em><br />
                Category&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Course #&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Section #
            </div>
            <div><textarea name="batchinfo" rows="10" cols="50"></textarea></div>
            <br />
            <input type="submit" value="Look up courses">
        </form>
    ';
} else {

    /*
     * ---------------------------------------------------------------
     * Parse and clean batch input.
     * ---------------------------------------------------------------
     */

    $batchinfo = preg_split('/\r?\n/', $batchinfo);

    $batchinfo = array_values(array_filter(
        array_map('trim', $batchinfo),
        static function($line) {
            return $line !== '';
        }
    ));

    echo '<style>
        table, tbody, thead, th, td, tr {
            border-collapse: collapse !important;
            border: none !important;
        }
        .tablestyle tr:nth-child(even) { background: whitesmoke; }
        .tablestyle tr:nth-child(odd) { background: white; }
        td.different:empty { background: yellow; }
        button { border-radius: 5px; padding: 2px 5px; }
        span.columnarrows { font-size: 40px; position: relative; top: 8px; }
        span.columnarrows.left { right: -20px; }
        span.columnarrows.right { left: -20px; }
        img.arrowstem { width: calc(25% - 40px); height: 13px;position: relative; }
        img.arrowstem.left { right: -10px; }
        img.arrowstem.right { left: -10px; }
    </style>';

    /*
     * ---------------------------------------------------------------
     * Find the standard Moodle Teacher role dynamically.
     * One query.
     * ---------------------------------------------------------------
     */

    $teacherroleid = $DB->get_field(
        'role',
        'id',
        ['shortname' => 'editingteacher']
    );

    /*
     * ---------------------------------------------------------------
     * First pass:
     * Parse all input rows and determine the possible shortnames.
     *
     * We keep both the normal shortname and the S variation so that
     * we can retrieve ALL courses in one database query.
     * ---------------------------------------------------------------
     */

    $parsedrows = [];
    $shortnameconditions = [];
    $shortnameparams = [];

    foreach ($batchinfo as $courseinfo) {

        $courseinfo = preg_split(
            '/\s+/',
            $courseinfo,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        if (empty($courseinfo[0]) ||
                empty($courseinfo[1]) ||
                empty($courseinfo[2])) {
            $parsedrows[] = [
                'valid' => false,
                'shortname' => '',
                'alternate' => '',
            ];
            continue;
        }

        $cat = trim($courseinfo[0]);
        $crsnum = trim($courseinfo[1]);
        $section = trim($courseinfo[2]);

        /*
         * Fix for ONL courses.
         */
        if (strpos($section, 'ONL') === false &&
                strpos($section, 'OL') === false &&
                strpos($section, 'SS') === false) {

            $section = $section > 9 ? $section : '0' . $section;
        }

        $shortname = empty($quarter)
            ? $cat . $crsnum . '-' . $section
            : $quarter . ' ' . $cat . $crsnum . '-' . $section;

        /*
         * Alternate form:
         * CHE311-01
         * CHES311-01
         */
        $alternate = empty($quarter)
            ? $cat . 'S' . $crsnum . '-' . $section
            : $quarter . ' ' . $cat . 'S' . $crsnum . '-' . $section;

        $parsedrows[] = [
            'valid' => true,
            'shortname' => $shortname,
            'alternate' => $alternate,
        ];

        /*
         * Build one large OR condition for the entire batch.
         *
         * We retain the existing partial LIKE matching behavior.
         */
        $normalparam = 'shortname_' . count($shortnameparams);
        $shortnameconditions[] =
            $DB->sql_like('c.shortname', ':' . $normalparam, false, false);
        $shortnameparams[$normalparam] = "%$shortname%";

        $alternateparam = 'shortname_' . count($shortnameparams);
        $shortnameconditions[] =
            $DB->sql_like('c.shortname', ':' . $alternateparam, false, false);
        $shortnameparams[$alternateparam] = "%$alternate%";
    }

    /*
     * ---------------------------------------------------------------
     * Query #1:
     * Retrieve every potentially matching course in ONE query.
     *
     * We retrieve all matches instead of LIMIT 1 because different
     * input rows can have different shortnames.
     * ---------------------------------------------------------------
     */

    $coursesbyshortname = [];

    if (!empty($shortnameconditions)) {

        $coursesql = "
            SELECT c.id, c.shortname
              FROM {course} c
             WHERE (" . implode(' OR ', $shortnameconditions) . ")
             ORDER BY c.id DESC
        ";

        $courses = $DB->get_records_sql(
            $coursesql,
            $shortnameparams
        );

        /*
         * For every requested shortname, select the newest matching
         * course, preserving the behavior of the original script.
         */
        foreach ($parsedrows as &$row) {

            if (!$row['valid']) {
                continue;
            }

            /*
             * Don't overwrite a match once we've found the newest one.
             *
             * Because courses are ordered DESC by ID, the first match
             * encountered is the newest.
             */
            if (!isset($coursesbyshortname[$row['shortname']])) {
                foreach ($courses as $course) {
                    if (strpos($course->shortname, $row['shortname']) !== false) {
                        $coursesbyshortname[$row['shortname']] = $course;
                        break;
                    }
                }
            }

            /*
             * If the normal form wasn't found, try the S variation.
             */
            if (!isset($coursesbyshortname[$row['shortname']])) {
                foreach ($courses as $course) {
                    if (strpos($course->shortname, $row['alternate']) !== false) {
                        $coursesbyshortname[$row['shortname']] = $course;
                        break;
                    }
                }
            }
        }
        unset($row);
    }

    /*
     * ---------------------------------------------------------------
     * Build unique course ID list.
     * ---------------------------------------------------------------
     */

    $courseids = [];

    foreach ($coursesbyshortname as $course) {
        $courseids[$course->id] = $course->id;
    }

    $courseids = array_values($courseids);
    $courseidlookup = array_fill_keys($courseids, true);

    /*
    * ---------------------------------------------------------------
    * Query #2:
    * Get the Meta ID for all courses in the batch.
    *
    * In this implementation of Moodle meta enrolment:
    *   e.courseid   = PARENT course ID
    *   e.customint1 = CHILD course ID
    *
    * ---------------------------------------------------------------
    */

    $metaids = [];

    if (!empty($courseids)) {

        $metaparams = [];
        $parentplaceholders = [];
        $childplaceholders = [];

        foreach ($courseids as $index => $courseid) {

            $parentparam = 'metaparent' . $index;
            $childparam = 'metachild' . $index;

            $parentplaceholders[] = ':' . $parentparam;
            $childplaceholders[] = ':' . $childparam;

            $metaparams[$parentparam] = $courseid;
            $metaparams[$childparam] = $courseid;
        }

        /*
        * e.id MUST be the first selected field because it is
        * unique and get_records_sql() uses the first field as
        * the array key.
        */
        $metasql = "
            SELECT e.id,
                e.courseid,
                e.customint1
            FROM {enrol} e
            WHERE e.enrol = 'meta'
            AND e.status = 0
            AND (
                    e.courseid IN (" . implode(',', $parentplaceholders) . ")
                    OR e.customint1 IN (" . implode(',', $childplaceholders) . ")
            )
            ORDER BY e.id
        ";

        $metaenrolments = $DB->get_records_sql(
            $metasql,
            $metaparams
        );

        foreach ($metaenrolments as $enrol) {

            $parentid = (int)$enrol->courseid;
            $childid = (int)$enrol->customint1;

            if ($parentid <= 0) {
                continue;
            }

            /*
            * If the requested course is the PARENT,
            * Meta ID is the parent course ID.
            */
            if (isset($courseidlookup[$parentid])) {
                $metaids[$parentid] = $parentid;
            }

            /*
            * If the requested course is the CHILD,
            * Meta ID is still the parent course ID.
            */
            if ($childid > 0 && isset($courseidlookup[$childid])) {
                $metaids[$childid] = $parentid;
            }
        }
    }

    /*
     * ---------------------------------------------------------------
     * Query #3:
     * Retrieve the first editing teacher's email for EVERY course
     * in one query.
     * ---------------------------------------------------------------
     */

    $teacheremails = [];

    if ($teacherroleid && !empty($courseids)) {

        $teacherparams = [
            'contextlevel' => CONTEXT_COURSE,
            'roleid' => $teacherroleid,
        ];

        $teacherplaceholders = [];

        foreach ($courseids as $index => $courseid) {
            $param = 'teacher_courseid' . $index;
            $teacherplaceholders[] = ':' . $param;
            $teacherparams[$param] = $courseid;
        }

        $teachersql = "
            SELECT ra.id AS id,
                ctx.instanceid AS courseid,
                u.email
            FROM {context} ctx
            JOIN {role_assignments} ra
                ON ra.contextid = ctx.id
            JOIN {user} u
                ON u.id = ra.userid
            WHERE ctx.contextlevel = :contextlevel
            AND ra.roleid = :roleid
            AND ctx.instanceid IN (" . implode(',', $teacherplaceholders) . ")
            ORDER BY ctx.instanceid, u.id
        ";

        $teachers = $DB->get_records_sql(
            $teachersql,
            $teacherparams
        );

        foreach ($teachers as $teacher) {

            /*
             * Only keep the first teacher for each course, matching
             * the original ORDER BY u.id behavior.
             */
            if (!isset($teacheremails[$teacher->courseid])) {
                $teacheremails[$teacher->courseid] = $teacher->email;
            }
        }
    }

    /*
     * ---------------------------------------------------------------
     * Generate output.
     * At this point there are NO MORE database queries.
     * ---------------------------------------------------------------
     */

    $data = '';
    $courseiddata = [];
    $metaiddata = [];
    $bothiddata = [];
    $emaildata = [];

    $rowcount = count($parsedrows);
    $rownum = 0;

    foreach ($parsedrows as $row) {

        $email = '';
        $coursename = '';
        $courseid = '';
        $metaid = '';

        if ($row['valid']) {

            $course = $coursesbyshortname[$row['shortname']] ?? false;

            if ($course) {

                $courseurl = new moodle_url(
                    '/course/view.php',
                    ['id' => $course->id]
                );

                $coursename = html_writer::link(
                    $courseurl,
                    s($course->shortname),
                    [
                        'target' => '_blank',
                        'rel' => 'noopener',
                    ]
                );

                $courseid = (string)$course->id;

                $metaid = isset($metaids[$course->id])
                    ? (string)$metaids[$course->id]
                    : '';

                $email = $teacheremails[$course->id] ?? '';

            } else {

                /*
                 * Preserve the original behavior of displaying the
                 * requested shortname when the course is not found.
                 */
                $coursename = s($row['shortname']) . ' Not Found';
            }
        }

        $priorid = '';

        if ($rownum === 0) {

            $priorid = html_writer::tag(
                'td',
                html_writer::tag(
                    'textarea',
                    '',
                    [
                        'id' => 'priorids',
                        'style' => implode(';', [
                            'text-align:center',
                            'width:100%',
                            'height:100%',
                            'border:none',
                            'line-height:' . $rowheight . 'px',
                            'overflow:hidden',
                            'box-sizing:border-box',
                            'padding:0 10px',
                        ]),
                    ]
                ),
                [
                    'style' =>
                        'vertical-align:top;height:' .
                        ($rowcount * $rowheight) .
                        'px;',
                    'rowspan' => $rowcount,
                ]
            );
        }

        $data .= html_writer::start_tag(
            'tr',
            ['style' => "height:{$rowheight}px;"]
        );

        $data .= html_writer::tag(
            'td',
            $coursename,
            ['style' => 'white-space:nowrap;']
        );

        $data .= html_writer::tag(
            'td',
            s($metaid),
            [
                'class' => 'meta',
                'style' => 'text-align:center;',
            ]
        );

        $data .= html_writer::tag(
            'td',
            s($courseid),
            [
                'class' => 'courseid',
                'style' => 'text-align:center;',
            ]
        );

        $data .= html_writer::tag(
            'td',
            s($email),
            [
                'class' => 'email',
                'style' => 'text-align:center;',
            ]
        );

        $data .= $priorid;

        $data .= html_writer::tag(
            'td',
            '',
            [
                'class' => 'different',
                'style' => 'text-align:center;',
            ]
        );

        $data .= html_writer::end_tag('tr');

        $courseiddata[] = $courseid;
        $metaiddata[] = $metaid;
        $bothiddata[] = $metaid . "\t" . $courseid;
        $emaildata[] = $email;

        $rownum++;
    }

    /*
     * Join rows with newlines, with no newline after the final row.
     */
    $courseiddata = implode("\n", $courseiddata);
    $metaiddata = implode("\n", $metaiddata);
    $bothiddata = implode("\n", $bothiddata);
    $emaildata = implode("\n", $emaildata);

    echo '<h1>Course Lookup Results</h1>';

    /*
     * Decorative images retained from the original tool.
     */
    echo '
        <table class="tablestyle" style="width:100%;margin:auto;">
            <tr>
                <td style="width:36%"></td>
                <td style="width: 16%;white-space: nowrap;text-align: center;">
                    <span class="columnarrows left">⬐</span>
                    <img class="arrowstem left" src="data:image/jpeg;base64, iVBORw0KGgoAAAANSUhEUgAAAAEAAAAeCAIAAABi9+OQAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAWSURBVBhXY/j//z9JGBfAphYn/v8fANCIR7mKxAkaAAAAAElFTkSuQmCC">
                        <button type="button" id="copy-both" style="font-size: initial;position: relative;z-index: 1;">Both</button>
                    <img class="arrowstem right" src="data:image/jpeg;base64, iVBORw0KGgoAAAANSUhEUgAAAAEAAAAeCAIAAABi9+OQAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAWSURBVBhXY/j//z9JGBfAphYn/v8fANCIR7mKxAkaAAAAAElFTkSuQmCC">
                    <span class="columnarrows right">⬎</span>
                </td>
                <td style="width:48%"></td>
            </tr>
        </table>

        <table class="tablestyle" style="width:100%;margin:auto;">
            <tr>
                <td style="width:36%"></td>
                <td style="width:8%;text-align:center;">
                    <button type="button" id="copy-meta">Copy</button>
                </td>
                <td style="width:8%;text-align:center;">
                    <button type="button" id="copy-course">Copy</button>
                </td>
                <td style="width:28%;text-align:center;">
                    <button type="button" id="copy-email">Copy</button>
                </td>
                <td style="width:15%;text-align:center;">
                    <button type="button" id="check-results">Check</button>
                </td>
                <td style="width:5%;text-align:center;">
                    <button type="button" id="copy-checked">Copy</button>
                </td>
            </tr>
        </table>
    ';

    echo "
        <table class='tablestyle' style='width:100%;margin:auto;'>
            <tr>
                <th style='width:36%;'><strong>Course Name</strong></th>
                <th style='width:8%;text-align:center;'><strong>Meta ID</strong></th>
                <th style='width:8%;text-align:center;'><strong>Course ID</strong></th>
                <th style='width:28%;text-align:center;'><strong>Teacher Email</strong></th>
                <th style='width:15%;text-align:center;'><strong>Prior ID</strong></th>
                <th style='width:5%;text-align:center;'><strong>X</strong></th>
            </tr>
            {$data}
        </table>
    ";

    /*
     * JavaScript data.
     */
    $javascriptdata = [
        'courseiddata' => $courseiddata,
        'metaiddata' => $metaiddata,
        'bothiddata' => $bothiddata,
        'emaildata' => $emaildata,
    ];

    $jsondata = json_encode(
        $javascriptdata,
        JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT |
        JSON_UNESCAPED_UNICODE
    );

    echo '<script id="batch-lookup-script">
        (function() {
            "use strict";

            var copyData = ' . $jsondata . ';

            function showCopyNotice() {
                var notice = document.createElement("div");
                notice.className = "unicodehelper_copynotice";
                notice.textContent = "Copied";
                document.body.appendChild(notice);

                window.setTimeout(function() {
                    if (notice && notice.parentNode) {
                        notice.parentNode.removeChild(notice);
                    }
                }, 1000);
            }

            function fallbackCopyText(text) {
                var textArea = document.createElement("textarea");
                textArea.value = text;
                textArea.setAttribute("readonly", "");
                textArea.style.position = "fixed";
                textArea.style.top = "0";
                textArea.style.left = "0";
                textArea.style.opacity = "0";

                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();

                var successful = false;

                try {
                    successful = document.execCommand("copy");
                } catch (error) {
                    console.error("Unable to copy text.", error);
                }

                document.body.removeChild(textArea);
                return successful;
            }

            function copyText(text) {
                text = String(text || "").replace(/\r?\n+$/, "");

                var successful = fallbackCopyText(text);

                if (successful) {
                    showCopyNotice();
                } else {
                    console.error("Copy operation failed.");
                }
            }

            function checkResults() {
                var prioridsElement = document.getElementById("priorids");

                if (!prioridsElement) {
                    return;
                }

                var priorids = prioridsElement.value.split(/\r?\n/);
                var resultCells = document.querySelectorAll(".different");

                resultCells.forEach(function(resultCell, index) {
                    var row = resultCell.closest("tr");

                    if (!row) {
                        resultCell.textContent = "";
                        return;
                    }

                    var metaElement = row.querySelector(".meta");
                    var courseElement = row.querySelector(".courseid");

                    if (!metaElement || !courseElement) {
                        resultCell.textContent = "";
                        return;
                    }

                    var priorid = (priorids[index] || "")
                        .replace(/\t/g, " ")
                        .replace(/\s+/g, " ")
                        .trim();

                    var metaid = metaElement.textContent.trim();
                    var courseid = courseElement.textContent.trim();

                    var matches = courseid !== "" &&
                        (
                            priorid === (metaid + " " + courseid) ||
                            (metaid === "" && priorid === courseid)
                        );

                    resultCell.textContent = matches ? "x" : "";
                });
            }

            function getChecked() {
                var checked = [];

                document.querySelectorAll(".different").forEach(
                    function(element) {
                        checked.push(element.textContent.trim());
                    }
                );

                return checked.join("\n");
            }

            function bindCopyButton(id, dataKey) {
                var button = document.getElementById(id);

                if (!button) {
                    return;
                }

                button.addEventListener("click", function() {
                    copyText(copyData[dataKey]);
                });
            }

            bindCopyButton("copy-both", "bothiddata");
            bindCopyButton("copy-meta", "metaiddata");
            bindCopyButton("copy-course", "courseiddata");
            bindCopyButton("copy-email", "emaildata");

            var checkButton = document.getElementById("check-results");

            if (checkButton) {
                checkButton.addEventListener("click", checkResults);
            }

            var checkedButton = document.getElementById("copy-checked");

            if (checkedButton) {
                checkedButton.addEventListener("click", function() {
                    copyText(getChecked());
                });
            }
        }());
    </script>';
}

echo $OUTPUT->footer();
?>