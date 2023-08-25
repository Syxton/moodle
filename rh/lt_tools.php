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
 * Rose Hulman Learning & Technology Tools.
 *
 * @copyright  2024 onwards Rose-Hulman Institute of Technology
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../config.php');
require_login();

$PAGE->set_context(\context_system::instance());
$PAGE->set_url('/lt_tools.php');
$PAGE->set_title("Learning & Technology Tools");
$PAGE->set_heading("L&T Tools");
echo $OUTPUT->header();

$viewallcourses = has_capability('moodle/course:view', \context_system::instance());
if ($USER->email !== 'davidso1@rose-hulman.edu' &&
    $USER->email !== 'tettehri@rose-hulman.edu' &&
    $USER->email !== 'dee@rose-hulman.edu' &&
    $USER->email !== 'boswell@rose-hulman.edu') {
    echo "You do not have permissions to access these tools.";
    echo $OUTPUT->footer();
    die();
}

// Parameters array.
$params = [];

// List of all allowed actions used in this page.
$action = optional_param('action', null, PARAM_ALPHANUMEXT);
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
} else if(array_key_exists($action, $actions)) { // Action: ie 'lookup_sql'.
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
 * @param array $params An optional array of parameters.
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
 * @param array $params The parameters for the lookup, must include "lid"
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
            // Get the user record.
            $user = $DB->get_record('user', ['id' => $submission->userid]);

            // Get the assignment record.
            $assignment = $DB->get_record('assign', ['id' => $submission->assignment]);

            // Get the course module record.
            $mod = get_coursemodule_from_instance('assign', $assignment->id, $assignment->course);

            // Construct the answer
            $answer = 'Submission ID ' . $submission->id . ' belongs to '
                . '<a href="' . $CFG->wwwroot . '/mod/assign/view.php?id=' . $mod->id . '&action=grading">'
                . $assignment->name . '</a> by ' . $user->firstname . ' ' . $user->lastname;

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
        $answerform = '<table>
            <!-- Display the answer underneath the form -->
            <tr>
                <td style="vertical-align: top">
                    <strong>Answer</strong>
                </td>
                <td>
                    ' . $answer . '
                </td>
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
                <th colspan="4" style="vertical-align: top">
                    <strong>Multifunction ID Search</strong>
                </th>
            </tr>
            <tr>
                <td>
                    <strong>Find</strong>
                </td>
                <td>
                    ' . $actionselect . '
                </td>
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
        $answerform = '<table>
                            <!-- Display the answer underneath the form -->
                            <tr>
                                <td style="vertical-align: top">
                                    <strong>Answer</strong>
                                </td>
                                <td>
                                    ' . $answer . '
                                </td>
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
        . '<table class="generaltable">' . $header . $rows . '</table>';
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

function multi_course_merger_form($answer = false) {
    global $params;

    $params["parentcourseid"] = optional_param('parentcourseid', 0, PARAM_INT);
    $params["childcourses"] = data_submitted()->childcourses;
    $answer = isset($params['multi_course_merger_answer']) ? $params['multi_course_merger_answer'] : false;

    // Loop through the child courses and fill the form.
    // The child courses are stored in the $params["childcourses"] array.
    // If the array is not empty, loop through it and generate a table row
    // for each child course using the child_course_template function.
    // If the array is empty, generate a single table row for a new child course.
    $childcourserows = '';
    if (!empty($params["childcourses"])) {
        foreach ($params["childcourses"] as $key => $childcourse) {
            // Generate a table row for the current child course.
            $childcourserows .= child_course_template($key, $childcourse["id"], $childcourse["group"]);
        }
    } else {
        // Generate a table row for a new child course.
        $childcourserows .= child_course_template(time());
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
                    <strong>Parent Course ID</strong>
                </td>
                <td>
                    <!-- The course ids textarea -->
                    <input type="text" name="parentcourseid" value="' . $params["parentcourseid"] . '" />
                </td>
            </tr>
        </table>

        ' . $childcourserows . '

        <!-- Submit button for the form -->
        <button type="submit">
            Run Merge Course Tool
        </button>
    </form>

    <script type="text/javascript">
        /**
         * Adds a new child course row to the form.
         *
         * This function generates a new child course row using the current timestamp
         * and inserts it after the current row.
         *
         * @param {Element} elem - The element that triggered the function.
         */
        function add_child_course(elem) {
            // Create a new child course row with a unique key based on the current time
            jQuery(`' . child_course_template('` + Date.now() + `') . '`).insertAfter(jQuery(elem).closest("table"));
        }

        /**
         * Removes a child course row from the form.
         *
         * This function removes the child course row that contains the element that
         * triggered the function, unless it is the only remaining child course row.
         *
         * @param {Element} elem - The element that triggered the function.
         */
        function remove_child_course(elem) {
            // Do not remove the only child course row
            if (jQuery("table.child_course").length > 1) {
                jQuery(elem).closest("table").remove();
            }
        }
    </script>

    <style>
        table.multi_course_merger td:first-child {
            padding-left: 30px;
        }

        table.child_course {
            margin: 10px 0 10px 50px;
        }
    </style>';

    $answerform = '';
    if ($answer) {
        $return .= '
            <table>
                <!-- Display the answer underneath the form -->
                <tr>
                    <td style="vertical-align: top">
                        <strong>Response:</strong>
                    </td>
                    <td>
                        ' . $answer . '
                    </td>
                </tr>
            </table>';
    }
    return $return;
}

function child_course_template($key, $id = "", $groupname = "") {
    return '
    <table class="child_course">
        <tr>
            <td style="vertical-align: top">
                <!-- Label for the child course id input -->
                <strong>Child Course ID</strong>
            </td>
            <td>
                <!-- The course ids input -->
                <input type="text" name="childcourses[' . $key . '][id]" value="' . $id . '" />
            </td>
            <td style="vertical-align: top">
                <!-- Label for the child course id input -->
                <strong>Group Name</strong>
            </td>
            <td>
                <!-- The course group name input -->
                <input type="text" name="childcourses[' . $key . '][group]" value="' . $groupname . '" />
            </td>
            <td>
                <button class="delete_child_course" type="button" onclick="remove_child_course(this)">
                    Delete
                </button>
            </td>
            <td>
                <!-- Label for the child course id input -->
                <button class="add_child_course_button" type="button" onclick="add_child_course(this)">
                    Add Child Course
                </button>
            </td>
        </tr>
    </table>';
}

function multi_course_merger() {
    global $DB, $CFG;

    $response = "";
    $parentcourseid = optional_param('parentcourseid', 0, PARAM_INT);
    $childcourses = data_submitted()->childcourses;

    if (empty($childcourses)) {
        return "At least 1 child course is required.";
    }

    if (empty($parentcourseid)) {
        return "Parent course required.";
    }

    require_once($CFG->dirroot.'/enrol/meta/locallib.php');

    // Make sure parentcourseid exists in database.
    $parentcourse = $DB->get_record('course', ['id' => $parentcourseid]);
    if (!$parentcourse) {
        return "Parent course not found";
    }

    try {
        $parentcoursecontext = context_course::instance($parentcourseid, MUST_EXIST);
    } catch (moodle_exception $e) {
        return "Parent course not found";
    }

    // Make sure meta enrollment is enabled at the site level.
    if (!enrol_is_enabled('meta')) {
        return "Meta links are not allowed";
    }

    // Get role id of role with shortname "student".
    $role = $DB->get_record('role', ['shortname' => "student"]);

    // Loop through all child courses in childcourses array.
    foreach ($childcourses as $childcourse) {
        // Make sure child course exists.
        if (empty($childcourse["id"])) {
            $response .= "<div>Child course id is required.</div>";
            continue; // skip
        }

        $course = $DB->get_record('course', ['id' => $childcourse["id"]]);
        if (!$course) {
            $response .= "<div>Course " . $childcourse["id"] . " could not be found.</div>";
            continue; // skip
        }

        // Check to see if this course is already meta linked to parent course.
        // If child course is NOT already meta linked to parent course, link now.
        if (!$enrols = $DB->get_record('enrol', ['courseid' => $parentcourseid, 'customint1' => $childcourse["id"], 'enrol'=>'meta'])) {
            // create course enrolment instance.
            $enrolplugin = enrol_get_plugin('meta');
            $fields = [
                'customint1' => $childcourse["id"],
                'customint2' => 0, // No group created here so it doesn't sync.
            ];
            $enrolid = $enrolplugin->add_instance($parentcourse, $fields);
            if (!(bool) $enrolid) {
                $response .= "<div>Could not create meta link for course: " . $childcourse["id"] . "</div>";
                continue; // skip the rest.
            }
            $response .= "<div>Added meta link: ". $childcourse["id"] . " -> " . $parentcourseid . "</div>";
            $enrols = $DB->get_record('enrol', ['id' => $enrolid]);
        }

        if (empty($childcourse["group"])) {
            continue; // don't need to do any group work.
        }

        // Check to see if group for this child course exists in the parent course.
        require_once($CFG->dirroot . '/group/lib.php');
        $group = $DB->get_record('groups', ['courseid' => $parentcourseid, 'name' => $childcourse["group"]]);

        // If the group is not found, create the group in the parent course.
        if (!$group) {
            $group = (object) [
                "courseid" => $parentcourseid,
                "name" => $childcourse["group"],
                "enablemessaging" => 0,
            ];
            $group->id = groups_create_group($group);
            $response .= "<div>Created group: ". $childcourse["group"] . "</div>";
        }

        // Add all active students from this child course into the group, skipping duplicates.
        $enrollments = $DB->get_records('user_enrolments', ['enrolid' => $enrols->id, 'status' => '0'], 'id ASC');

        if (!$enrollments) {
            $response .= "<div>No enrollments found in course: "  . $childcourse["id"] . " with enrolid: " . $enrols->id . "</div>";
            continue;
        }

        $added = 0;
        foreach ($enrollments as $enrollment) {
            // Is user a student role in parent course?
            $roleassignment = $DB->record_exists('role_assignments', ['roleid' => $role->id, 'component' => 'enrol_meta', 'userid' => $enrollment->userid, 'contextid' => $parentcoursecontext->id]);
            if (!$roleassignment) {
                $response .= "<div>Roleid: " . $role->id . " did not exist for: " . $enrollment->userid . " in parentcontext: " . $parentcoursecontext->id . ".</div>";
                continue;
            }

            $user = $DB->get_record('user', ['id' => $enrollment->userid], '*', MUST_EXIST);
            if (!$user) {
                $response .= "<div>User could not be found: " . $enrollment->userid . "</div>";
            }

            // Add user to group.
            groups_add_member($group, $user);
            $added++;
        }

        // Display amount of users added to group if there are any.
        if ($added > 0) {
            $response .= "<div>Added $added users to group: ". $childcourse["group"] . "</div>";
        }
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
?>
