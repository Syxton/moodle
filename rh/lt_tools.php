<?php
/**
 * This file is part of Moodle - http://moodle.org/
 *
 * Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Rose Hulman Learning & Technology Tools.
 *
 * @copyright 2024 onwards Rose-Hulman Institute of Technology
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once '../config.php';

// List of all allowed actions used in this page.
$action = optional_param('action', null, PARAM_ALPHANUMEXT);

// AJAX calls.
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
    $action();
    die();
}

require_login();

$PAGE->set_context(\context_system::instance());
$PAGE->set_url('/lt_tools.php');
$PAGE->set_title("Learning & Technology Tools");
$PAGE->set_heading("L&T Tools");
echo $OUTPUT->header();

$viewallcourses = has_capability('moodle/course:view', \context_system::instance());
if ($USER->email !== 'davidso1@rose-hulman.edu'
    && $USER->email !== 'tettehri@rose-hulman.edu'
    && $USER->email !== 'dee@rose-hulman.edu'
    && $USER->email !== 'boswell@rose-hulman.edu'
) {
    echo "You do not have permissions to access these tools.";
    echo $OUTPUT->footer();
    die();
}

echo '
<style>
    table, tbody, thead, th, td, tr {
        border-collapse: collapse !important;
        border: none !important;
    }
    .tablestyle tr:nth-child(even) {
        background: whitesmoke;
    }
    .tablestyle tr:nth-child(odd) {
        background: white;
    }
</style>';

// Parameters array.
$params = [];

$actions = [
    "multifunction" => [
        "lookup_submissionid" => 'Submission ID',
        "lookup_fileid" => 'File ID',
        "lookup_groupid" => 'Group ID',
        "lookup_userid" => 'User ID',
    ],
    "lookup_sql" => "SQL Query",
    "multi_course_merger" => "Multi Course Merger"
];

/**
 * Handle multifunction lookup.
 *
 * This function handles the multifunction lookup process by calling the
 * appropriate function based on the user's input.
 *
 * @param string $action The action to perform
 * @param array $multifunction An array of actions that are multifunction
 * @param array $params The parameters to pass to the selected function
 */

if (array_key_exists($action, $actions["multifunction"])) { // Action is in multifunction array.
    // Multifunction ID Search parameters
    $params["lid"] = optional_param('lid', null, PARAM_INT);

    // Save the selected action in the params array.
    $params['lookup_multifunction_selected'] = $action;

    // Call the selected function and get the answer.
    $params['lookup_multifunction_answer'] = $action($params);
} elseif (array_key_exists($action, $actions)) { // Action: ie 'lookup_sql'.
    $params[$action . '_answer'] = $action();
}

/**
 * Generates the main tool form.
 *
 * This function generates the form that allows the user to choose
 * which tool to use.
 *
 * The form contains two parts:
 *  - A ID lookup form for looking up a selection of possible database identifiers
 *  - A SQL query form for looking up submissions by SQL query (only
 *    visible to users with the email address 'davidso1@rose-hulman.edu')
 *
 * @param  array $params An optional array of parameters.
 * @return string The lookup submission ID form.
 */
function main_tool_form($params = []) {
    global $USER;

    // Display submission ID lookup form.
    $answer = isset($params['lookup_multifunction_answer']) ? $params['lookup_multifunction_answer'] : false;

    // Generate the multifunction ID form and add it to the return form.
    $returnform = lookup_multifunction_form($answer);

    // --------------------------------------------------------------------
    $returnform .= '<br><br>'; // Space between the two forms.
    // --------------------------------------------------------------------

    // ----------------------------------------------------------------
    $returnform .= quick_course_lookup_form();
    // ----------------------------------------------------------------
    $returnform .= '<br><br>'; // Space between the two forms.

    // Generate the course merger form and add it to the return form.
    $returnform .= multi_course_merger_form();

    // --------------------------------------------------------------------
    $returnform .= '<br><br>'; // Space between the two forms.
    // --------------------------------------------------------------------

    // Display SQL Query form to davidso1 only.
    if ($USER->email == 'davidso1@rose-hulman.edu') {
        // Display SQL Query form.
        $answer = isset($params['lookup_sql_answer']) ? $params['lookup_sql_answer'] : false;

        // Generate the SQL form and add it to the return form.
        $returnform .= lookup_sql_form($answer);
    }

    // Return the form.
    return $returnform;
}

/**
 * Look up submission by id.
 *
 * This function looks up a submission in the assign_submission table by its id.
 * If a matching submission is found, it returns an answer indicating the
 * assignment and user associated with the submission.
 *
 * @param  array $params The parameters for the lookup, must include "lid"
 * @return string The answer with the assignment and user associated with the submission, or an error message
 */
function lookup_submissionid($params) {
    global $DB, $CFG;

    // Look up submission by id.
    if (!empty($params["lid"])) {
        // Get the submission record from the database.
        $submission = $DB->get_record('assign_submission', ['id' => $params["lid"]]);

        // If a matching submission is found...
        if ($submission) {
            $name = "Unknown";
            if ($submission->groupid > 0) {
                // Get the group record.
                $group = $DB->get_record('groups', ['id' => $submission->groupid]);
                $name = "Group: " . $group->name;
            } else {
                // Get the user record.
                $user = $DB->get_record('user', ['id' => $submission->userid]);
                $name = "User: " . $user->firstname . ' ' . $user->lastname;
            }

            // Get the assignment record.
            $assignment = $DB->get_record('assign', ['id' => $submission->assignment]);

            // Get the course module record.
            $mod = get_coursemodule_from_instance('assign', $assignment->id, $assignment->course);

            // Construct the answer
            $answer = 'Submission ID ' . $submission->id . ' belongs to '
                . '<a href="' . $CFG->wwwroot . '/mod/assign/view.php?id=' . $mod->id . '&action=grading">'
                . $assignment->name . '</a> by ' . $name;

            // Save the answer in the params array
            return $answer;

        } else {
            // If the submission is not found, set the answer to indicate that
            return 'Error: Submission not found.';
        }
    }
}

/**
 * Lookup a file by id
 *
 * This function looks up a file by id and returns an HTML link to the assignment
 * where the file is stored along with the user who uploaded it and the filename.
 * If the file is not found, an error message is returned.
 *
 * @param array $params The parameters for the lookup, must include "lid"
 * @return string The HTML link to the assignment, or an error message
 */
function lookup_fileid($params) {
    global $DB, $CFG;

    // Look up file by id.
    if (!empty($params["lid"])) {
        // Get the file record from the database.
        $file = $DB->get_record('files', ['id' => $params["lid"], 'filearea' => 'submission_files']);

        // If a matching file is found...
        if ($file) {
            // Get the user record.
            $user = $DB->get_record('user', ['id' => $file->userid]);

            // Get the assignment from context.
            $context = $DB->get_record('context', ['id' => $file->contextid]);
            $mod = $DB->get_record("course_modules", ["id" => $context->instanceid]);
            $assignment = $DB->get_record('assign', ['id' => $mod->instance]);

            // Construct the answer
            $answer = 'File ID ' . $file->id . ' (' . $file->filename . ') ' . ' belongs to '
                . '<a href="' . $CFG->wwwroot . '/mod/assign/view.php?id=' . $mod->id . '&action=grading">'
                . $assignment->name
                . '</a> by ' . $user->firstname . ' ' . $user->lastname;

            // Save the answer in the params array
            return $answer;

        } else {
            // If the file is not found, set the answer to indicate that
            return 'Error: File record not found.';
        }
    }
}

/**
 * Lookup a group by id
 *
 * This function looks up a group by id and returns an HTML link to the course
 * with the group name.  If the group is not found, an error message is returned.
 *
 * @param array $params The parameters for the lookup, must include "lid"
 * @return string The HTML link to the course, or an error message
 */
function lookup_groupid($params) {
    global $DB, $CFG;

    // Look up group by id.
    if (!empty($params["lid"])) {
        // Get the group record from the database.
        $group = $DB->get_record('groups', ['id' => $params["lid"]]);

        // If a matching group is found...
        if ($group) {
            // Get the course record.
            $course = $DB->get_record('course', ['id' => $group->courseid]);

            // Construct the answer
            $answer = 'Group ID ' . $group->id . ' (' . $group->name . ') ' . ' belongs to '
                . '<a href="' . $CFG->wwwroot . '/group/index.php?id=' . $group->courseid . '">'
                . $course->fullname
                . '</a>';

            // Save the answer in the params array
            return $answer;

        } else {
            // If the group is not found, set the answer to indicate that
            return 'Error: Group record not found.';
        }
    }
}

/**
 * Lookup a user by id
 *
 * This function looks up a user by id and returns an HTML link to their profile
 * with their first and last names.
 *
 * @param array $params The parameters for the lookup, must include "lid"
 * @return string The HTML link to the user's profile, or an error message
 */
function lookup_userid($params) {
    global $DB, $CFG;

    // Only proceed if the "lid" parameter is set
    if (!empty($params["lid"])) {
        // Look up the user record
        $user = $DB->get_record('user', ['id' => $params["lid"]]);

        // If a matching user is found...
        if ($user) {
            // Construct the answer
            $answer = 'User ID ' . $user->id . ' belongs to '
                . '<a href="' . $CFG->wwwroot . '/user/profile.php?id=' . $user->id . '">'
                . $user->firstname . ' ' . $user->lastname
                . '</a>';

            // Return the answer
            return $answer;

        } else {
            // If the user is not found, set the answer to indicate that
            return 'Error: User record not found.';
        }
    }
}


/**
 * Generate a form to lookup an assignment by submission id
 *
 * This function generates a form with a dropdown menu of available
 * multifunction lookups (submission id, file id, group id, user id). The
 * user can select which type of lookup to perform and enter an ID to
 * search for. When the form is submitted, the selected function and ID
 * are passed to the lt_tools.php script for further processing.
 *
 * @param string $answer OPTIONAL An answer to display underneath the form
 * @return string The form HTML
 */
function lookup_multifunction_form($answer = false) {
    global $action, $params, $actions;

    $answerform = '';
    if ($answer) {
        $answerform = '
        <table>
            <!-- Display the answer underneath the form -->
            <tr>
                <td><strong>Answer</strong></td>
                <td>' . $answer . '</td>
            </tr>
        </table>';
    }

    $lid = "";
    $actionselect = '<select name="action">';
    foreach ($actions["multifunction"] as $k => $v) {
        if ($action == $k) {
            $lid = $params["lid"];
        }

        if (isset($params['lookup_multifunction_selected']) && $params['lookup_multifunction_selected'] == $k) {
            $actionselect .= '<option value="' . $k . '" selected>' . $v . '</option>';
        } else {
            $actionselect .= '<option value="' . $k . '">' . $v. '</option>';
        }
    }
    $actionselect .= '</select>';

    return '
    <form action="lt_tools.php" method="post">
        <table>
            <tr>
                <th colspan="4">
                    <strong>Multifunction ID Search</strong>
                </th>
            </tr>
            <tr>
                <td><strong>Find</strong></td>
                <td>' . $actionselect . '</td>
                <td>
                    <input name="lid" value="' . $lid . '" />
                </td>
                <td>
                    <button type="submit">Search</button>
                </td>
            </tr>
        </table>
        ' . $answerform . '
    </form>';
}

/**
 * Executes a SQL query and displays the results.
 *
 * This function is called when the user clicks the "Run Query" button
 * on the Lookup Tool SQL form. It executes the given SQL query and
 * displays the results in a table.
 *
 * @param array $params An array of parameters, must include 'lquery'
 * @return string The HTML table of results, or an error message
 */
function lookup_sql() {
    global $DB, $params;

    // SQL query parameters
    $params["lquery"] = optional_param('lquery', null, PARAM_TEXT);

    // Get the SQL query from the parameters.
    $lquery = trim($params["lquery"]);

    // Only execute the query if it is not empty.
    if (!empty($lquery)) {

        // Make sure the query is a SELECT statement, since we don't
        // want to allow anything that could modify data.
        $sql_keywords = ['create ', 'alter ', 'drop ', 'insert into', 'update ', 'delete ', 'truncate ', 'grant ', 'revoke '];

        // Check if any of the disallowed keywords are in the query.
        if (!str_starts_with_any($lquery, $sql_keywords)) {
            try {
                // Execute the SQL query and get the results.
                if ($results = $DB->get_records_sql($lquery)) {
                    // Convert the results to a HTML table and return it as the answer.
                    return array_to_html_table($results);

                } else {
                    // If the query returned no results, set the answer to
                    // indicate that.
                    return 'No results found.';
                }

            } catch (\moodle_exception $e) {
                // We catch only moodle_exception here as other exceptions indicate issue with setup not the pdf.
                return 'Error: ' . $e->debuginfo;
            }

        } else {
            // If the query is not a SELECT statement, set the answer
            // to indicate that only SELECT queries are allowed.
            return 'Only SELECT queries are allowed.';
        }

    }
}

/**
 * Generate a form to run a SQL query
 *
 * @param string $answer OPTIONAL An answer to display underneath the form
 * @return string The form HTML
 */
function lookup_sql_form($answer = false) {
    global $params;

    // SQL query parameters
    $params["lquery"] = optional_param('lquery', null, PARAM_TEXT);

    $answerform = '';
    if ($answer) {
        $answerform = '
        <table>
            <!-- Display the answer underneath the form -->
            <tr>
                <td><strong>Answer</strong></td>
                <td>' . $answer . '</td>
            </tr>
        </table>';
    }

    return '
    <form action="lt_tools.php" method="post">
        <!-- Hidden form field used to identify the action to perform -->
        <input type="hidden" name="action" value="lookup_sql">
        <table>
            <tr>
                <!-- Heading for the form -->
                <th colspan="2" style="vertical-align: top">
                    <strong>Run SQL Query</strong>
                </th>
            </tr>
            <tr>
                <td style="vertical-align: top">
                    <!-- Label for the SQL textarea -->
                    <strong>SQL</strong>
                </td>
                <td>
                    <!-- The SQL textarea -->
                    <textarea name="lquery" rows="10" cols="100">' . $params["lquery"] . '</textarea>
                </td>
                <td>
                    <!-- Submit button for the form -->
                    <button type="submit">Run Query</button>
                </td>
            </tr>
        </table>
        ' . $answerform . '
    </form>';
}

/**
 * Converts an array of stdClass objects to an HTML table
 *
 * This function takes an array of stdClass objects and converts it to an HTML
 * table. The resulting HTML table will have a single row for each object in
 * the array and each property of the array will be a column in the table.
 *
 * @param stdClass[] $array The array to convert to HTML
 *
 * @return string The HTML table
 */
function array_to_html_table(array $array) {
    // Count the number of objects in the array.
    $count = count($array);

    // Copy the first object from the array to use as the table header.
    $headerobj = array_slice($array, 0, 1);

    // Create the header row.
    $header = '<tr>';
    foreach ($headerobj as $obj) {
        foreach ($obj as $key => $value) {
            // Add each property of the first object as a table header.
            $header .= '<th>' . $key . '</th>';
        }
    }
    $header .= '</tr>';

    // Create the data rows.
    $rows = '';
    foreach ($array as $obj) {
        $rows .= '<tr>';
        foreach($obj as $key => $value) {
            // Add each property of the current object as a table cell.
            $rows .= '<td>' . $value . '</td>';
        }
        $rows .= '</tr>';
    }

    // Add the header and data rows to the table and return it.
    return $count . ' results found. <br />'
        . '<table class="generaltable tablestyle">' . $header . $rows . '</table>';
}

/**
 * Checks if a string begins with any of the substrings in a given array
 *
 * This function checks if the given string begins with any of the substrings
 * in the given array. If it finds a match, it returns true immediately,
 * otherwise it returns false.
 *
 * @param string $haystack The string to search in
 * @param string[] $needles An array of substrings to search for
 * @return boolean true if any of the substrings was found, false otherwise
 */
function str_starts_with_any(string $haystack, array $needles) {
    /**
     * Loop through the array of substrings and check if any of them is
     * contained in the given string. If a match is found, return true
     * immediately, otherwise return false.
     */
    foreach ($needles as $needle) {
        if (str_starts_with(strtolower($haystack), strtolower($needle))) {
            return true;
        }
    }
    return false;
}

/**
 * Search for courses based on a search string.
 *
 * This function searches for courses based on a given search string.
 * It will return up to 25 courses that match the search string either
 * in the course fullname or shortname. The courses will be returned in
 * a JSON array with the course id, fullname and shortname.
 *
 * @return string A JSON array containing the matching courses.
 */
function course_lookup() {
    global $DB;

    $search = optional_param('search', '', PARAM_TEXT);
    $courses = [];
    $results = $DB->get_records_sql(
        'SELECT id, fullname, shortname
        FROM {course}
        WHERE ' . $DB->sql_like("fullname", ":fullname", false, false) . '
        OR ' . $DB->sql_like("shortname", ":shortname", false, false) . '
        ORDER BY id DESC
        LIMIT 25',
        [
            "fullname" => '%' . $DB->sql_like_escape($search) . '%',
            "shortname" => '%' . $DB->sql_like_escape($search) . '%',
        ]
    );
    if ($results) {
        foreach ($results as $course) {
            $courses[] = [
                'id' => $course->id,
                'fullname' => $course->fullname,
                'shortname' => $course->shortname,
            ];
        }
    }

    // Return array as JSON.
    echo json_encode($courses);
    exit;
}

/**
 * Retrieves an array of teachers for a given course.
 *
 * This function takes a course id as a parameter and returns an array of
 * teachers enrolled in the course with the capability to manage activities.
 *
 * @param int $courseid The id of the course to retrieve the teachers for.
 * @return array An array of teachers enrolled in the course with the capability to manage activities.
 */
function get_course_teachers($courseid) {
    global $DB;

    $teachers = get_enrolled_users(
        \context_course::instance($courseid),
        'moodle/course:manageactivities'
    );

    return $teachers;
}

/**
 * This function is used to display the course details page.
 * It will display the linked courses and the groups and members of the given course.
 * The function will output HTML and CSS to display the course details in a
 * readable format.
 * The function will exit after outputting the HTML and CSS.
 *
 * @param string $courseid The id of the course to display the details for.
 * @return string The HTML and CSS to display the course details.
 */
function course_details_lookup() {
    global $DB;

    $courseid = optional_param('courseid', '', PARAM_TEXT);

    $course = $DB->get_record('course', ['id' => $courseid]);

    $connected = get_course_meta_courses($courseid);
    $groups = get_course_groups_and_memberships($courseid);

    $url = new moodle_url('/course/view.php', ['id' => $courseid]);

    echo '
    <style>
        div.course_details {
            display: flex;
        }
        div.course_details > div {
            width: 50%;
        }
    </style>
    <div class="course_details">
        ' . display_course_name($course) . '
        <div style="width: 10px"></div>
        ' . display_course_teachers($course) . '
    </div>
    <div class="course_details">
        ' . display_groups_and_memberships($groups) . '
        <div style="width: 10px"></div>
        ' . display_course_meta($connected) . '
    </div>';
    exit;
}

/**
 * Displays the course name and shortname as a table.
 *
 * This function takes a course object and returns a table containing the
 * course name and shortname. If no teachers are found, it will display a
 * message indicating that no teachers were found.
 *
 * @param stdClass $course The course object to display the teachers for.
 *
 * @return string The HTML code to display the course name and shortname.
 */
function display_course_teachers($course) {
    $teachers = "";
    $teachernames = get_course_teachers($course->id);

    if (empty($teachernames)) {
        $teachers = '
        <tr>
            <td>No teachers found.</td>
        </tr>';
    } else {
        $teachers = '
        <tr>
            <th>Name</th>
            <th>Email</th>
        </tr>';
    }

    foreach ($teachernames as $teacher) {
        $teachers .= '
        <tr>
            <td>' . $teacher->firstname . ' ' . $teacher->lastname . '</td>
            <td>' . $teacher->email . '</td>
        </tr>';
    }

    return '
    <div>
        <strong>Teachers</strong>
        <table class="generaltable tablestyle">
            ' . $teachers . '
        </table>
    </div>';
}

/**
 * Displays the course name and shortname as a table.
 *
 * This function takes a course object as an argument and returns an HTML table
 * containing the course id, shortname and fullname. Each of these values is a
 * link to the course page.
 *
 * @param stdClass $course The course object to display.
 *
 * @return string The HTML table containing the course information.
 */
function display_course_name($course) {
    $url = new moodle_url('/course/view.php', ['id' => $course->id]);
        return '
    <div>
        <strong>Course Information</strong>
        <table class="generaltable tablestyle">
            <tr>
                <th>ID</th>
                <th>Shortname</th>
                <th>Fullname</th>
            </tr>
            <tr>
                <td>
                    <a target="_blank" href="' . $url . '">' . $course->id . '</a>
                </td>
                <td>
                    <a target="_blank" href="' . $url . '">' . $course->shortname . '</a>
                </td>
                <td>
                    <a target="_blank" href="' . $url . '">' . $course->fullname . '</a>
                </td>
            </tr>
        </table>
    </div>';
}

/**
 * Displays the groups and their memberships associated with the given course.
 *
 * This function takes an array of group records associated with the given course
 * and an array of group membership records associated with each group as parameters.
 * It returns an HTML table containing the groups and their memberships.
 *
 * @param array $groups An associative array with two keys: 'groups' and 'memberships'.
 * The 'groups' key maps to an array of group records, and the 'memberships' key maps
 * to an associative array where each key is a group ID, and the value is an array of
 * group membership records.
 *
 * @return string The HTML table containing the groups and their memberships.
 */
function display_groups_and_memberships($groups) {
    $memberships = $groups['memberships'];

    $return = "";

    if (empty($groups['groups'])) {
        $return = '
        <tr>
            <td>No groups found.</td>
        </tr>';
    } else {
        $return = '
        <tr>
            <th>Group Name</th>
            <th>Members</th>
        </tr>';
    }

    foreach ($groups['groups'] as $group) {
        $count = 0;
        if (isset($memberships[$group->id])) {
            $count = count($memberships[$group->id]);
        }

        $return .= '
        <tr>
            <td>' . $group->name . '</td>
            <td>' . $count . ' member(s)</td>
        </tr>';
    }

    return '
    <div>
        <strong>Groups and Memberships</strong>
        <table class="generaltable tablestyle">
            ' . $return . '
        </table>
    </div>';
}

/**
 * Retrieves the courses linked to the given course using the Meta Enrollment plugin.
 *
 * This function retrieves the courses linked to the given course using the Meta Enrollment plugin.
 * It does this by first retrieving the enroll record for the given course and then checking if the
 * enrol type is 'meta'. If it is, it retrieves the record from the course table with the id of the
 * customint1 field of the enrol record.
 *
 * @param int $courseid The ID of the course to retrieve the linked courses for.
 * @return array An array of course records linked to the given course.
 */
function get_course_meta_courses($courseid) {
    global $DB;

    $linkedcourses = [];

    // Retrieve the enroll record for the given course and check if the enrol type is 'meta'.
    $enrols = $DB->get_records(
        'enrol',
        [
            'courseid' => $courseid,
            'enrol'=>'meta',
        ]
    );

    if ($enrols) {
        foreach ($enrols as $meta) {
            // Retrieve record from course table with id of customint1 field.
            $linkedcourses[] = $DB->get_record(
                'course',
                [
                    'id' => $meta->customint1,
                ]
            );
        }
    }

    return $linkedcourses;
}

/**
 * Displays the courses linked to the given course using the Meta Enrollment plugin.
 *
 * This function takes an array of course records linked to the given course and displays
 * them in a table.
 *
 * @param array $connected An array of course records linked to the given course.
 * @return string The HTML code to display the linked courses.
 */
function display_course_meta($connected) {
    $return = "";

    if (empty($connected)) {
        $return = '
        <tr>
            <td>No linked courses found.</td>
        </tr>';
    } else {
        $return = '
        <tr>
            <th>ID</th>
            <th>Shortname</th>
            <th>Fullname</th>
        </tr>';
    }

    foreach ($connected as $course) {
        // Display the fullname of each course in a table row
        $return .= '
        <tr>
            <td>' . $course->id . '</td>
            <td>' . $course->shortname . '</td>
            <td>' . $course->fullname . '</td>
        </tr>';
    }

    return '
    <div>
        <strong>Linked Courses</strong>
        <table class="generaltable tablestyle">
            ' . $return . '
        </table>
    </div>';
}

/**
 * Retrieves the groups and their memberships associated with the given course.
 *
 * This function retrieves an array of group records associated with the given course,
 * and an array of group membership records associated with each group.
 *
 * @param int $courseid The ID of the course to retrieve groups and memberships for.
 * @return array An associative array with two keys: 'groups' and 'memberships'.
 * The 'groups' key maps to an array of group records, and the 'memberships' key maps
 * to an associative array where each key is a group ID, and the value is an array of
 * group membership records.
 */
function get_course_groups_and_memberships($courseid) {
    global $DB;

    /**
     * An array of group records associated with the given course.
     * @var array $groups
     */
    $groups = $DB->get_records('groups', ['courseid' => $courseid]);

    /**
     * An associative array where each key is a group ID, and the value is an array of
     * group membership records.
     * @var array $groupmemberships
     */
    $groupmemberships = [];

    foreach ($groups as $group) {
        /**
         * An array of group membership records associated with the given group.
         * @var array $members
         */
        $members = $DB->get_records('groups_members', ['groupid' => $group->id]);

        /**
         * Add the group membership records to the associative array.
         */
        $groupmemberships[$group->id] = $members;
    }

    return [
        'groups' => $groups,
        'memberships' => $groupmemberships,
    ];
}

/**
 * Generates a form to quickly search for a course by its ID or name.
 *
 * This function generates a form with a textarea to enter the course ID or name.
 * When the user types something into the textarea, it will start to fetch courses
 * from the database that match the entered string.
 *
 * The function also includes a JavaScript function to handle the fetching of the courses.
 *
 * @return string The form HTML.
 */
function quick_course_lookup_form() {
    return '
    <table>
        <tr>
            <th scope="col">
                <!-- Label for the Quick Course Search -->
                <strong>Quick Course Search</strong>
            </th>
        </tr>
    </table>
    <table class="quick_course_lookup">
        <tr>
            <td style="vertical-align: top">
                <!-- Label for the course ids textarea -->
                <strong>Course Search</strong>
            </td>
            <td>
                <!-- The course search text box -->
                <input type="text" id="course_lookup" value="" />
                <span id="course_lookup_loading" style="display: none;">Loading...</span>
            </td>
        </tr>
    </table>
    <div id="course_details_results"></div>

    <script defer type="text/javascript">
        function sleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }

        /**
         * Handles the fetching of the courses from the database.
         *
         * This function is called when the user types something into the textarea.
         * It fetches the courses from the database that match the entered string and
         * displays them in a select element.
         *
         * @param {object} element The textarea element that triggered the function.
         */
        function course_details_lookup(element) {
            if (element.value === "" || element.value === "Select a course") {
                jQuery("#course_details_results").html("");
                return;
            }

            // Make an AJAX call to the backend to fetch the courses.
            jQuery.ajax({
                url: "lt_tools.php", // URL to the backend script
                type: "POST",
                data: { action: "course_details_lookup", courseid: element.value }, // Send the input value as a parameter
                success: function(data) {
                    // Display the fetched courses in a select element.
                    jQuery("#course_details_results").html(data);
                }
            });
        }

        /**
         * Checks if jQuery is loaded and executes the code when it is.
         */
        function executeWhenJQueryLoaded() {
            if (window.jQuery) {
                // jQuery is loaded, run the code
                jQuery(document).ready(function() {
                    // Add an event listener to the textarea to fetch the courses when the user types something.
                    jQuery("#course_lookup").on("input", async function() {
                        var inputVal = jQuery(this).val();
                        // Create an empty select element to hold the results.
                        var lookupResults = jQuery(`<select id="course_lookup_results" onchange="course_details_lookup(this)"><option>Select a course</option></select>`);
                        if (inputVal.length < 4) { // Fetch only after 2 characters
                            jQuery("#course_lookup_results").remove();
                            return;
                        }

                        // Wait a bit before making the AJAX call to avoid too many requests.
                        await sleep(500);

                        if (inputVal !== jQuery("#course_lookup").val()) {
                            return; // Input has changed, do not make the AJAX call.
                        }

                        jQuery("#course_lookup_loading").show();

                        // Make an AJAX call to the backend to fetch the courses.
                        jQuery.ajax({
                            url: "lt_tools.php", // URL to the backend script
                            type: "POST",
                            dataType: "json",
                            data: { action: "course_lookup", search: inputVal }, // Send the input value as a parameter
                            success: function(data) {
                                // Add the fetched courses to the select element.
                                jQuery.each(data, function(index, item) {
                                    jQuery("<option>").val(item.id).text(item.id + " - " + item.fullname + " (" + item.shortname + ")").appendTo(lookupResults);
                                });
                                jQuery("#course_lookup_results").remove();
                                jQuery("#course_lookup_loading").hide();
                                lookupResults.insertAfter(jQuery("#course_lookup"));
                            }
                        });
                    });
                });
            } else {
                // Not loaded yet, check again in 50 milliseconds
                setTimeout(executeWhenJQueryLoaded, 50);
            }
        }

        // Initial call to start the checking process
        executeWhenJQueryLoaded();
    </script>
    <style>
        #course_lookup_results {
            margin-left: 10px;
            padding: 2px;
        }
    </style>
    ';
}

/**
 * Generates the HTML form for the multicourse merger tool.
 *
 * @param bool $answer - Whether to display the answer below the form.
 *
 * @return string - The HTML form for the multicourse merger tool.
 */
function multi_course_merger_form($answer = false) {
    global $params;

    $params["primarycourseid"] = optional_param('primarycourseid', 0, PARAM_INT);
    $params["primarygroupvalue"] = optional_param('primarygroupvalue', '', PARAM_TEXT);
    if (!empty(data_submitted()) && isset(data_submitted()->linkedcourses)) {
        $params["linkedcourses"] = data_submitted()->linkedcourses;
    }
    $answer = isset($params['multi_course_merger_answer']) ? $params['multi_course_merger_answer'] : false;

    // Loop through the linked courses and fill the form.
    // The linked courses are stored in the $params["linkedcourses"] array.
    // If the array is not empty, loop through it and generate a table row
    // for each linked course using the linked_course_template function.
    // If the array is empty, generate a single table row for a new linked course.
    $linkedcourserows = '';
    if (!empty($params["linkedcourses"])) {
        foreach ($params["linkedcourses"] as $key => $linkedcourse) {
            // Generate a table row for the current linked course.
            $linkedcourserows .= linked_course_template($key, $linkedcourse["id"], $linkedcourse["group"]);
        }
    } else {
        // Generate a table row for a new linked course.
        $linkedcourserows .= linked_course_template(time());
    }


    $return = '
    <form action="lt_tools.php" method="post">
        <!-- Hidden form field used to identify the action to perform -->
        <input type="hidden" name="action" value="multi_course_merger">
        <table>
            <tr>
                <th scope="col">
                    <!-- Label for the Multi-Merge Tool -->
                    <strong>Multicourse Quick Merger</strong>
                </th>
            </tr>
        </table>

        <table class="multi_course_merger">
            <tr>
                <td style="vertical-align: top">
                    <!-- Label for the course ids textarea -->
                    <strong>Primary Course ID</strong>
                </td>
                <td>
                    <!-- The course ids textarea -->
                    <input type="text" name="primarycourseid" value="' . $params["primarycourseid"] . '" />
                </td>
                <td style="vertical-align: top">
                    <strong>Group Name</strong>
                </td>
                <td>
                    <!-- The course group name input -->
                    <input type="text" name="primarygroupvalue" value="' . $params["primarygroupvalue"] . '" />
                </td>
            </tr>
        </table>

        ' . $linkedcourserows . '

        <!-- Submit button for the form -->
        <button type="submit">
            Run Merge Course Tool
        </button>
    </form>

    <script defer type="text/javascript">
        /**
         * Adds a new linked course row to the form.
         *
         * This function generates a new linked course row using the current timestamp
         * and inserts it after the current row.
         *
         * @param {Element} elem - The element that triggered the function.
         */
        function add_linked_course(elem) {
            // Create a new linked course row with a unique key based on the current time
            jQuery(`' . linked_course_template('` + Date.now() + `') . '`).insertAfter(jQuery(elem).closest("table"));
        }

        /**
         * Removes a linked course row from the form.
         *
         * This function removes the linked course row that contains the element that
         * triggered the function, unless it is the only remaining linked course row.
         *
         * @param {Element} elem - The element that triggered the function.
         */
        function remove_linked_course(elem) {
            // Do not remove the only linked course row
            if (jQuery("table.linked_course").length > 1) {
                jQuery(elem).closest("table").remove();
            }
        }
    </script>

    <style>
        table.multi_course_merger td:first-child {
            padding-left: 30px;
        }

        table.linked_course {
            margin: 10px 0 10px 50px;
        }
    </style>';

    $answerform = '';
    if ($answer) {
        $return .= '
            <table class="generaltable tablestyle">
                <!-- Display the answer underneath the form -->
                <tr>
                    <th style="vertical-align: top">
                        Response
                    </th>
                </tr>
                <tr>
                    <td>
                        ' . $answer . '
                    </td>
                </tr>
            </table>';
    }
    return $return;
}

/**
 * Generates a table row for a linked course in the multicourse merger tool.
 *
 * This function generates a table row that contains input fields for the linked course id and group name.
 * It also includes buttons to delete the linked course or add a new one.
 *
 * @param string $key The key of the linked course to generate.
 * @param string $id The id of the linked course to generate.
 * @param string $groupname The group name of the linked course to generate.
 *
 * @return string The generated table row HTML.
 */
function linked_course_template($key, $id = "", $groupname = "") {
    return '
    <table class="linked_course">
        <tr>
            <td style="vertical-align: top">
                <!-- Label for the linked course id input -->
                <strong>Linked Course ID</strong>
            </td>
            <td>
                <!-- The course ids input -->
                <input type="text" name="linkedcourses[' . $key . '][id]" value="' . $id . '" />
            </td>
            <td style="vertical-align: top">
                <!-- Label for the linked course id input -->
                <strong>Group Name</strong>
            </td>
            <td>
                <!-- The course group name input -->
                <input type="text" name="linkedcourses[' . $key . '][group]" value="' . $groupname . '" />
            </td>
            <td>
                <button class="delete_linked_course" type="button" onclick="remove_linked_course(this)">
                    Delete
                </button>
            </td>
            <td>
                <!-- Label for the linked course id input -->
                <button class="add_linked_course_button" type="button" onclick="add_linked_course(this)">
                    Add linked Course
                </button>
            </td>
        </tr>
    </table>';
}

/**
 * Synchronizes a group from a linked course into the primary course.
 *
 * This function takes a course id and an optional group name as parameters.
 * If the group name is not empty, it checks to see if a group with the
 * same name exists in the primary course. If the group is not found,
 * it creates a new group in the primary course with the given name.
 *
 * @param int $courseid The course id of the primary course.
 * @param string $groupname The name of the group to synchronize.
 *
 * @return array An array containing the synced group and a response message.
 */
function sync_group_create($courseid, $groupname = "") {
    global $DB, $CFG;
    $response = "";
    $group = false;

    if (!empty($groupname)) {
        // Check to see if group for this linked course exists in the primary course.
        require_once($CFG->dirroot . '/group/lib.php');
        $group = $DB->get_record(
            'groups',
            [
                'courseid' => $courseid,
                'name' => $groupname,
            ]
        );

        // If the group is not found, create the group in the primary course.
        if (!$group) {
            $group = (object) [
                "courseid" => $courseid,
                "name" => $groupname,
                "enablemessaging" => 0,
            ];
            $group->id = groups_create_group($group);

            // Get full group record after creation.
            $group = $DB->get_record('groups', ['id' => $group->id]);
            $response .= '
                <div>
                    <strong>'. $groupname . ' created</strong>
                </div>';
        } else {
            $response .= '
                <div>
                    <strong>'. $groupname . ' found</strong>
                </div>';
        }
    }

    return ['group' => $group, 'response' => $response];
}

/**
 * Synchronizes a group from a linked course into the primary course.
 *
 * This function takes an array of enrollments, a role record, a primary course context record, a group record, and an optional group name as parameters.
 * It loops through each enrollment and checks if the user has a student role in the primary course.
 * If the user does not have a student role, it skips the user.
 * It then adds the user to the group in the primary course.
 * Finally, it displays the amount of users added to the group if there are any, or error messages if there are any.
 *
 * @param array $enrollments An array of enrollment records.
 * @param object $role A role record.
 * @param object $primarycoursecontext A primary course context record.
 * @param object $group A group record.
 * @param string $groupname The name of the group to synchronize.
 *
 * @return string A response message containing the amount of users added to the group if there are any, or error messages if there are any.
 */
function sync_group_membership($enrollments, $role, $primarycoursecontext, $group, $groupname) {
    global $DB;
    $response = "";
    $added = 0;
    $skipped = 0;

    // Clear existing group memberships.
    $DB->delete_records('groups_members', ['groupid' => $group->id]);

    foreach ($enrollments as $enrollment) {
        // Is user a student role in primary course?
        $roleassignment = $DB->record_exists(
            'role_assignments',
            [
                'roleid' => $role->id,
                'userid' => $enrollment->userid,
                'contextid' => $primarycoursecontext->id,
            ]
        );

        // User does not have a student role. Skip user.
        if (!$roleassignment) {
            $skipped++;
            continue;
        }

        $user = $DB->get_record(
            'user',
            [
                'id' => $enrollment->userid,
            ],
            '*',
            MUST_EXIST
        );
        if (!$user) {
            $response .= '
                <div>
                    Error: User could not be found: ' . $enrollment->userid . '
                </div>';
        }

        // Add user to group.
        groups_add_member($group, $user);
        $added++;
    }

    // Display amount of users added to group if there are any.
    if ($added > 0) {
        $response .= '
            <div>
                ' . $added . ' user(s) included. <br />
                ' . $skipped . ' non-student user(s) skipped.
                 <br /><br />
            </div>';
    }

    return $response;
}
/**
 * This function merges linked courses into a single course, optionally creating groups in the primary course.
 * It takes an array of linked courses, each containing a course id and an optional group name, as parameters.
 * It loops through each linked course and checks if the course is already meta linked to the primary course.
 * If the linked course is NOT already meta linked to the primary course, it links now.
 * It then creates a group in the primary course if needed, and adds all active students from the linked course into the group, skipping duplicates.
 * Finally, it displays the amount of users added to the group if there are any, or error messages if there are any.
 *
 * @param array $linkedcourses An array of linked courses, each containing a course id and an optional group name.
 * @return string A response message containing the amount of users added to the group if there are any, or error messages if there are any.
 */
function multi_course_merger() {
    global $DB, $CFG;

    $response = "";
    $primarycourseid = optional_param('primarycourseid', 0, PARAM_INT);
    $primarygroupvalue = optional_param('primarygroupvalue', '', PARAM_TEXT);

    $linkedcourses = data_submitted()->linkedcourses;

    if (empty($linkedcourses)) {
        return "At least 1 linked course is required.";
    }

    if (empty($primarycourseid)) {
        return "Primary course required.";
    }

    require_once($CFG->dirroot . '/enrol/meta/locallib.php');

    // Make sure primarycourseid exists in database.
    $primarycourse = $DB->get_record('course', ['id' => $primarycourseid]);
    if (!$primarycourse) {
        return "Primary course not found";
    }

    try {
        $primarycoursecontext = context_course::instance($primarycourseid, MUST_EXIST);
    } catch (moodle_exception $e) {
        return "Primary course not found";
    }

    // Make sure meta enrollment is enabled at the site level.
    if (!enrol_is_enabled('meta')) {
        return "Meta links are not allowed";
    }

    // Get role id of role with shortname "student".
    $role = $DB->get_record('role', ['shortname' => "student"]);

    // Add manual or external database enrolled students into primary course group.
    $enrols = $DB->get_records_sql(
        'SELECT * FROM {enrol} WHERE (enrol = ? OR enrol = ?) AND courseid = ?',
        [
            'manual',
            'database',
            $primarycourseid,
        ]
    );

    if ($enrols) {
        foreach ($enrols as $enrol) {
            // Add all students from this course into the group, skipping duplicates.
            $enrollments = $DB->get_records(
                'user_enrolments',
                [
                    'enrolid' => $enrol->id,
                    'status' => '0',
                ],
                'id ASC'
            );

            if ($enrollments) {
                // Create group if needed.
                $groupsync = sync_group_create($primarycourseid, $primarygroupvalue);
                $response .= $groupsync['response'];
                $group = $groupsync['group'];

                // Add users to group.
                $response .= sync_group_membership($enrollments, $role, $primarycoursecontext, $group, $primarygroupvalue);
            }
        }
    }

    // This can take a while, so let's try to keep the session alive.
    set_time_limit(0); // Set to unlimited execution time
    ignore_user_abort(true); // Continue running even if the user disconnects

    // Loop through all linked courses in linkedcourses array.
    foreach ($linkedcourses as $linkedcourse) {
        // Make sure linked course exists.
        if (empty($linkedcourse["id"])) {
            $response .= "<div>Linked course id is required.</div>";
            continue; // skip
        }

        $course = $DB->get_record('course', ['id' => $linkedcourse["id"]]);
        if (!$course) {
            $response .= "<div>Course " . $linkedcourse["id"] . " could not be found.</div>";
            continue; // skip
        }

        // Check to see if this course is already meta linked to primary course.
        // If linked course is NOT already meta linked to primary course, link now.
        $enrols = $DB->get_record(
            'enrol',
            [
                'courseid' => $primarycourseid,
                'customint1' => $linkedcourse["id"],
                'enrol'=>'meta',
            ]
        );
        if (!$enrols) {
            // create course enrolment instance.
            $enrolplugin = enrol_get_plugin('meta');
            $fields = [
                'customint1' => $linkedcourse["id"],
                'customint2' => 0, // No group created here so it doesn't sync.
            ];
            $enrolid = $enrolplugin->add_instance($primarycourse, $fields);
            if (!(bool) $enrolid) {
                $response .= "<div>Could not create meta link for course: " . $linkedcourse["id"] . "</div>";
                continue; // skip the rest.
            }
            $response .= '
                <div>
                    <strong>
                        Metalink created: '. $linkedcourse["id"] . ' -> ' . $primarycourseid . '
                    </strong>
                </div>';
            $enrols = $DB->get_record('enrol', ['id' => $enrolid]);
        }

        if (empty($linkedcourse["group"])) {
            continue; // don't need to do any group work.
        }

        // Create group if needed.
        $groupsync = sync_group_create($primarycourseid, $linkedcourse["group"]);
        $response .= $groupsync['response'];
        $group = $groupsync['group'];

        // Add all active students from this linked course into the group, skipping duplicates.
        $enrollments = $DB->get_records(
            'user_enrolments',
            [
                'enrolid' => $enrols->id,
                'status' => '0',
            ],
            'id ASC'
        );

        if (!$enrollments) {
            $response .= "<div>No enrollments found in course: "  . $linkedcourse["id"] . " with enrolid: " . $enrols->id . "</div>";
            continue;
        }

        // Add users to group.
        $response .= sync_group_membership($enrollments, $role, $primarycoursecontext, $group, $linkedcourse["group"]);
    }
    $response .= "<div><strong>Course Merge Finished</strong></div>";

    return $response;
}

echo '
    <style>
        table td {
            padding: 5px;
        }
    </style>
    <script type="text/javascript">
        window.addEventListener("load", function() {
            // If jQuery code is ever needed, add it here.
        });
    </script>';

echo main_tool_form($params);
echo $OUTPUT->footer();
