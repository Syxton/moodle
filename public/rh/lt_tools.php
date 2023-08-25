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

// === Configuration (easy to edit) ===
const LT_TOOLS_ALLOWED_EMAILS = [
    'davidso1@rose-hulman.edu',
    'tettehri@rose-hulman.edu',
    'dee@rose-hulman.edu',
    'boswell@rose-hulman.edu',
];

// Only these actions are allowed via AJAX
const LT_TOOLS_AJAX_ACTIONS = [
    'course_lookup',
    'course_details_lookup',
];

require_once '../config.php';

// List of all allowed actions used in this page.
$action = optional_param('action', null, PARAM_ALPHANUMEXT);

// AJAX calls – strict whitelist
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {

    if (!in_array($action, LT_TOOLS_AJAX_ACTIONS, true)) {
        http_response_code(403);
        die('Forbidden');
    }
    $action();
    die();
}

require_login();

$PAGE->set_context(\context_system::instance());
$PAGE->set_url('/lt_tools.php');
$PAGE->set_title("Learning & Technology Tools");
$PAGE->set_heading("L&T Tools");
echo $OUTPUT->header();

// Permission check
if (!in_array($USER->email, LT_TOOLS_ALLOWED_EMAILS, true)) {
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
    #sql_history {
        margin-top: 8px;
        max-height: 180px;
        overflow-y: auto;
        border: 1px solid #ccc;
        padding: 6px;
        background: #fafafa;
        font-size: 0.9em;
    }
    #sql_history div {
        cursor: pointer;
        padding: 3px 6px;
        border-bottom: 1px solid #eee;
    }
    #sql_history div:hover {
        background: #e8f0fe;
    }
    #sql_history .sql-time {
        color: #666;
        font-size: 0.85em;
        margin-right: 8px;
    }
</style>';

// Parameters array.
$params = [];

$actions = [
    "multifunction" => [
        "lookup_submissionid" => 'Submission ID',
        "lookup_fileid"       => 'File ID',
        "lookup_groupid"      => 'Group ID',
        "lookup_userid"       => 'User ID',
    ],
    "lookup_sql"          => "SQL Query",
    "multi_course_merger" => "Multi Course Merger"
];

/**
 * Handle multifunction lookup.
 */
if (array_key_exists($action, $actions["multifunction"])) {
    $params["lid"] = optional_param('lid', null, PARAM_INT);
    $params['lookup_multifunction_selected'] = $action;
    $params['lookup_multifunction_answer'] = $action($params);
} elseif (array_key_exists($action, $actions)) {
    // Require sesskey for any action that can modify data
    if ($action === 'multi_course_merger') {
        require_sesskey();
    }
    $params[$action . '_answer'] = $action();
}

/**
 * Generates the main tool form.
 */
function main_tool_form($params = []) {
    global $USER;

    $answer = isset($params['lookup_multifunction_answer']) ? $params['lookup_multifunction_answer'] : false;
    $returnform = lookup_multifunction_form($answer);

    $returnform .= '<br><br>';
    $returnform .= quick_course_lookup_form();
    $returnform .= '<br><br>';
    $returnform .= multi_course_merger_form();
    $returnform .= '<br><br>';

    // Display SQL Query form only to davidso1
    if ($USER->email === 'davidso1@rose-hulman.edu') {
        $answer = isset($params['lookup_sql_answer']) ? $params['lookup_sql_answer'] : false;
        $returnform .= lookup_sql_form($answer);
    }

    return $returnform;
}

/**
 * Look up submission by id.
 */
function lookup_submissionid($params) {
    global $DB, $CFG;

    if (empty($params["lid"])) {
        return '';
    }

    $submission = $DB->get_record('assign_submission', ['id' => $params["lid"]]);
    if (!$submission) {
        return 'Error: Submission not found.';
    }

    $name = "Unknown";
    if ($submission->groupid > 0) {
        $group = $DB->get_record('groups', ['id' => $submission->groupid]);
        $name = $group ? "Group: " . s($group->name) : "Group: (unknown)";
    } else {
        $user = $DB->get_record('user', ['id' => $submission->userid]);
        $name = $user ? "User: " . s($user->firstname . ' ' . $user->lastname) : "User: (unknown)";
    }

    $assignment = $DB->get_record('assign', ['id' => $submission->assignment]);
    if (!$assignment) {
        return 'Error: Assignment not found.';
    }

    $mod = get_coursemodule_from_instance('assign', $assignment->id, $assignment->course);
    if (!$mod) {
        return 'Error: Course module not found.';
    }

    $answer = 'Submission ID ' . (int)$submission->id . ' belongs to '
        . '<a href="' . $CFG->wwwroot . '/mod/assign/view.php?id=' . (int)$mod->id . '&action=grading">'
        . s($assignment->name) . '</a> by ' . $name;

    return $answer;
}

/**
 * Lookup a file by id
 */
function lookup_fileid($params) {
    global $DB, $CFG;

    if (empty($params["lid"])) {
        return '';
    }

    $file = $DB->get_record('files', ['id' => $params["lid"], 'filearea' => 'submission_files']);
    if (!$file) {
        return 'Error: File record not found.';
    }

    $user = $DB->get_record('user', ['id' => $file->userid]);
    $username = $user ? s($user->firstname . ' ' . $user->lastname) : '(unknown user)';

    $context = $DB->get_record('context', ['id' => $file->contextid]);
    if (!$context) {
        return 'Error: Context not found.';
    }

    $mod = $DB->get_record("course_modules", ["id" => $context->instanceid]);
    if (!$mod) {
        return 'Error: Course module not found.';
    }

    $assignment = $DB->get_record('assign', ['id' => $mod->instance]);
    if (!$assignment) {
        return 'Error: Assignment not found.';
    }

    $answer = 'File ID ' . (int)$file->id . ' (' . s($file->filename) . ') belongs to '
        . '<a href="' . $CFG->wwwroot . '/mod/assign/view.php?id=' . (int)$mod->id . '&action=grading">'
        . s($assignment->name) . '</a> by ' . $username;

    return $answer;
}

/**
 * Lookup a group by id
 */
function lookup_groupid($params) {
    global $DB, $CFG;

    if (empty($params["lid"])) {
        return '';
    }

    $group = $DB->get_record('groups', ['id' => $params["lid"]]);
    if (!$group) {
        return 'Error: Group record not found.';
    }

    $course = $DB->get_record('course', ['id' => $group->courseid]);
    $coursename = $course ? s($course->fullname) : '(unknown course)';

    $answer = 'Group ID ' . (int)$group->id . ' (' . s($group->name) . ') belongs to '
        . '<a href="' . $CFG->wwwroot . '/group/index.php?id=' . (int)$group->courseid . '">'
        . $coursename . '</a>';

    return $answer;
}

/**
 * Lookup a user by id
 */
function lookup_userid($params) {
    global $DB, $CFG;

    if (empty($params["lid"])) {
        return '';
    }

    $user = $DB->get_record('user', ['id' => $params["lid"]]);
    if (!$user) {
        return 'Error: User record not found.';
    }

    $answer = 'User ID ' . (int)$user->id . ' belongs to '
        . '<a href="' . $CFG->wwwroot . '/user/profile.php?id=' . (int)$user->id . '">'
        . s($user->firstname . ' ' . $user->lastname) . '</a>';

    return $answer;
}

/**
 * Generate a form to lookup an assignment by submission id
 */
function lookup_multifunction_form($answer = false) {
    global $action, $params, $actions;

    $answerform = '';
    if ($answer) {
        $answerform = '
        <table>
            <tr>
                <td><strong>Answer</strong></td>
                <td>' . $answer . '</td>
            </tr>
        </table>';
    }

    $lid = '';
    $actionselect = '<select name="action">';
    foreach ($actions["multifunction"] as $k => $v) {
        if ($action == $k) {
            $lid = isset($params["lid"]) ? (int)$params["lid"] : '';
        }

        $selected = (isset($params['lookup_multifunction_selected']) && $params['lookup_multifunction_selected'] == $k)
            ? ' selected' : '';
        $actionselect .= '<option value="' . s($k) . '"' . $selected . '>' . s($v) . '</option>';
    }
    $actionselect .= '</select>';

    return '
    <form action="lt_tools.php" method="post">
        <input type="hidden" name="sesskey" value="' . sesskey() . '">
        <table>
            <tr>
                <th colspan="4"><strong>Multifunction ID Search</strong></th>
            </tr>
            <tr>
                <td><strong>Find</strong></td>
                <td>' . $actionselect . '</td>
                <td><input name="lid" value="' . s($lid) . '" /></td>
                <td><button type="submit">Search</button></td>
            </tr>
        </table>
        ' . $answerform . '
    </form>';
}

/**
 * Executes a SQL query and displays the results.
 */
function lookup_sql() {
    global $DB, $CFG, $params;

    $params["lquery"] = optional_param('lquery', null, PARAM_TEXT);
    $lquery = trim($params["lquery"] ?? '');

    if ($lquery === '') {
        return '';
    }

    // Rewrite ANY prefix-style token (letters/numbers + underscore)
    // to the current Moodle prefix ($CFG->prefix already includes the trailing _).
    // Example matches: mdl_, m_, xyz_, foo123_, etc.
    $lquery = preg_replace('/\b[a-z0-9]+_(?=[a-z])/i', $CFG->prefix, $lquery);

    $lquery_lower = strtolower($lquery);

    // Only allow pure SELECT or WITH ... SELECT
    if (!preg_match('/^\s*(with\s+[\s\S]*?\s+)?select\s/is', $lquery_lower)) {
        return 'Only SELECT (and WITH … SELECT) queries are allowed.';
    }

    // Block common dangerous constructs
    $dangerous = [
        'into\s+outfile', 'into\s+dumpfile', 'load_file\s*\(',
        'benchmark\s*\(', 'sleep\s*\(', ';\s*\S',
        'information_schema', 'mysql\.', 'performance_schema',
        'pg_', 'sys\.',
    ];
    foreach ($dangerous as $pattern) {
        if (preg_match('/' . $pattern . '/i', $lquery)) {
            return 'Query contains disallowed constructs.';
        }
    }

    try {
        if ($results = $DB->get_records_sql($lquery)) {
            return array_to_html_table($results);
        }
        return 'No results found.';
    } catch (Exception $e) {
        return 'Error executing query. Check the query syntax.';
    }
}

/**
 * Generate a form to run a SQL query (includes localStorage history)
 */
function lookup_sql_form($answer = false) {
    global $params;

    $params["lquery"] = optional_param('lquery', null, PARAM_TEXT);
    $currentquery = $params["lquery"] ?? '';

    $answerform = '';
    $save_to_history_js = '';

    if ($answer) {
        $answerform = '
        <table>
            <tr>
                <td><strong>Answer</strong></td>
                <td>' . $answer . '</td>
            </tr>
        </table>';

        // Only save to history if the answer does NOT look like an error
        $is_error = (
            str_starts_with($answer, 'Only SELECT') ||
            str_starts_with($answer, 'Query contains disallowed') ||
            str_starts_with($answer, 'Error executing query') ||
            str_starts_with($answer, 'No results found.') === false && // keep "No results" as success
            str_starts_with($answer, 'Error:')
        );

        // Simpler and clearer check:
        $is_error = preg_match('/^(Only SELECT|Query contains disallowed|Error executing query|Error:)/i', $answer);

        if (!$is_error && $currentquery !== '') {
            // Escape for JavaScript
            $js_query = json_encode($currentquery);
            $save_to_history_js = "
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    if (typeof window.ltToolsAddToHistory === 'function') {
                        window.ltToolsAddToHistory({$js_query});
                    }
                });
            </script>";
        }
    }

    return '
    <form action="lt_tools.php" method="post" id="sql_form">
        <input type="hidden" name="action" value="lookup_sql">
        <input type="hidden" name="sesskey" value="' . sesskey() . '">
        <table>
            <tr>
                <th colspan="3" style="vertical-align: top">
                    <strong>Run SQL Query</strong>
                </th>
            </tr>
            <tr>
                <td style="vertical-align: top"><strong>SQL</strong></td>
                <td>
                    <textarea name="lquery" id="lquery" rows="10" cols="100">' . s($currentquery) . '</textarea>
                    <br />
                    <button type="submit">Run Query</button>
                    <div style="margin-top: 10px;">
                        <strong>Pinned Queries</strong>
                        <div id="sql_pinned" style="margin-top: 4px; border: 1px solid #cce5ff; background: #f0f7ff; padding: 6px; max-height: 180px; overflow-y: auto; min-height: 20px;"></div>
                    </div>

                    <div style="margin-top: 12px;">
                        <strong>Recent Queries</strong> <span style="font-size: 0.85em; color: #666;">(max 25)</span>
                        <div id="sql_history" style="margin-top: 4px; border: 1px solid #ccc; background: #fafafa; padding: 6px; max-height: 180px; overflow-y: auto;"></div>
                    </div>
                </td>
            </tr>
        </table>
        ' . $answerform . '
    </form>

    <script>
        (function() {
            const HISTORY_KEY = "lt_tools_sql_history";
            const MAX_HISTORY = 25;

            function loadHistory() {
                try {
                    const raw = localStorage.getItem(HISTORY_KEY);
                    return raw ? JSON.parse(raw) : [];
                } catch (e) {
                    return [];
                }
            }

            function saveHistory(history) {
                try {
                    localStorage.setItem(HISTORY_KEY, JSON.stringify(history));
                } catch (e) {
                    // localStorage full or disabled
                }
            }

            function addToHistory(query, description) {
                query = (query || "").trim();
                if (!query) return;

                let history = loadHistory();

                // Preserve existing description if the query already exists
                let existingDescription = "";
                const existingIndex = history.findIndex(item => item.query === query);
                if (existingIndex !== -1) {
                    existingDescription = history[existingIndex].description || "";
                    history.splice(existingIndex, 1);
                }

                const finalDescription = (description || "").trim() || existingDescription;

                const newItem = {
                    query: query,
                    description: finalDescription,
                    time: new Date().toLocaleString()
                };

                history.unshift(newItem);

                // Enforce limit ONLY on unpinned items
                const pinned = history.filter(item => item.description);
                let unpinned = history.filter(item => !item.description);

                if (unpinned.length > MAX_HISTORY) {
                    unpinned = unpinned.slice(0, MAX_HISTORY);
                }

                // Pinned first, then recent unpinned
                history = pinned.concat(unpinned);

                saveHistory(history);
                renderHistory();
            }

            // Called by PHP after a successful query
            window.ltToolsAddToHistory = function(query) {
                addToHistory(query, "");
            };

            function deleteHistoryItem(index) {
                let history = loadHistory();
                if (index >= 0 && index < history.length) {
                    history.splice(index, 1);
                    saveHistory(history);
                    renderHistory();
                }
            }

            function editDescription(index) {
                let history = loadHistory();
                if (index < 0 || index >= history.length) return;

                const current = history[index].description || "";
                const newDesc = prompt("Enter a short description for this query (leave blank to unpin):", current);

                if (newDesc === null) return; // cancelled

                history[index].description = newDesc.trim();

                // Re-order: pinned first
                const pinned = history.filter(item => item.description);
                const unpinned = history.filter(item => !item.description);
                history = pinned.concat(unpinned);

                saveHistory(history);
                renderHistory();
            }

            function escapeHtml(text) {
                const div = document.createElement("div");
                div.textContent = text;
                return div.innerHTML;
            }

            function createHistoryItem(item, index) {
                const div = document.createElement("div");
                div.style.display = "flex";
                div.style.alignItems = "center";
                div.style.gap = "8px";
                div.style.padding = "4px 6px";
                div.style.borderBottom = "1px solid #eee";

                // Clickable area – loads the query
                const main = document.createElement("div");
                main.style.flex = "1";
                main.style.cursor = "pointer";
                main.title = item.query;

                if (item.description) {
                    main.innerHTML = "<strong>" + escapeHtml(item.description) + "</strong>";
                } else {
                    main.innerHTML = "<span class=\\"sql-time\\">" + escapeHtml(item.time) + "</span> " +
                                    escapeHtml(item.query.substring(0, 100)) +
                                    (item.query.length > 100 ? "…" : "");
                }

                main.addEventListener("click", function() {
                    document.getElementById("lquery").value = item.query;
                });

                // Describe / Edit button
                const descBtn = document.createElement("button");
                descBtn.type = "button";
                descBtn.textContent = item.description ? "Edit" : "Describe";
                descBtn.title = item.description ? "Edit description" : "Add description (pins the query)";
                descBtn.style.fontSize = "0.8em";
                descBtn.addEventListener("click", function(e) {
                    e.stopPropagation();
                    editDescription(index);
                });

                // Delete button
                const delBtn = document.createElement("button");
                delBtn.type = "button";
                delBtn.textContent = "Delete";
                delBtn.title = "Remove from history";
                delBtn.style.fontSize = "0.8em";
                delBtn.addEventListener("click", function(e) {
                    e.stopPropagation();
                    if (confirm("Delete this query?")) {
                        deleteHistoryItem(index);
                    }
                });

                div.appendChild(main);
                div.appendChild(descBtn);
                div.appendChild(delBtn);

                return div;
            }

            function renderHistory() {
                const pinnedContainer = document.getElementById("sql_pinned");
                const recentContainer = document.getElementById("sql_history");
                if (!pinnedContainer || !recentContainer) return;

                const history = loadHistory();

                // Clear both
                pinnedContainer.innerHTML = "";
                recentContainer.innerHTML = "";

                const pinned = [];
                const unpinned = [];

                history.forEach((item, index) => {
                    if (item.description) {
                        pinned.push({ item: item, index: index });
                    } else {
                        unpinned.push({ item: item, index: index });
                    }
                });

                // Pinned section
                if (pinned.length === 0) {
                    pinnedContainer.innerHTML = "<em style=\'color:#666;\'>No pinned queries – add a description to pin one</em>";
                } else {
                    pinned.forEach(function(entry) {
                        pinnedContainer.appendChild(createHistoryItem(entry.item, entry.index));
                    });
                }

                // Recent (unpinned) section
                if (unpinned.length === 0) {
                    recentContainer.innerHTML = "<em style=\'color:#666;\'>No recent queries</em>";
                } else {
                    unpinned.forEach(function(entry) {
                        recentContainer.appendChild(createHistoryItem(entry.item, entry.index));
                    });
                }
            }

            // Initial render
            renderHistory();
        })();
    </script>
    ' . $save_to_history_js;
}

/**
 * Converts an array of stdClass objects to an HTML table
 */
function array_to_html_table(array $array) {
    $count = count($array);
    if ($count === 0) {
        return '0 results found.';
    }

    // Build header from first object
    $headerobj = reset($array);
    $header = '<tr>';
    foreach ($headerobj as $key => $value) {
        $header .= '<th>' . s($key) . '</th>';
    }
    $header .= '</tr>';

    // Data rows
    $rows = '';
    foreach ($array as $obj) {
        $rows .= '<tr>';
        foreach ($headerobj as $key => $dummy) {
            $value = isset($obj->$key) ? $obj->$key : '';
            $rows .= '<td>' . s($value) . '</td>';
        }
        $rows .= '</tr>';
    }

    return $count . ' results found. <br />'
        . '<table class="generaltable tablestyle">' . $header . $rows . '</table>';
}

/**
 * Checks if a string begins with any of the substrings in a given array
 */
function str_starts_with_any(string $haystack, array $needles) {
    foreach ($needles as $needle) {
        if (str_starts_with(strtolower($haystack), strtolower($needle))) {
            return true;
        }
    }
    return false;
}

/**
 * Search for courses based on a search string.
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
            "fullname"  => '%' . $DB->sql_like_escape($search) . '%',
            "shortname" => '%' . $DB->sql_like_escape($search) . '%',
        ]
    );

    if ($results) {
        foreach ($results as $course) {
            $courses[] = [
                'id'        => $course->id,
                'fullname'  => $course->fullname,
                'shortname' => $course->shortname,
            ];
        }
    }

    echo json_encode($courses);
    exit;
}

/**
 * Retrieves an array of teachers for a given course.
 */
function get_course_teachers($courseid) {
    return get_enrolled_users(
        \context_course::instance($courseid),
        'moodle/course:manageactivities'
    );
}

/**
 * Course details page (AJAX)
 */
function course_details_lookup() {
    global $DB;

    $courseid = optional_param('courseid', 0, PARAM_INT);
    if (!$courseid) {
        echo 'Invalid course ID';
        exit;
    }

    $course = $DB->get_record('course', ['id' => $courseid]);
    if (!$course) {
        echo 'Course not found';
        exit;
    }

    $connected = get_course_meta_courses($courseid);
    $groups = get_course_groups_and_memberships($courseid);

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

function display_course_teachers($course) {
    $teachernames = get_course_teachers($course->id);

    if (empty($teachernames)) {
        $teachers = '<tr><td>No teachers found.</td></tr>';
    } else {
        $teachers = '<tr><th>Name</th><th>Email</th></tr>';
        foreach ($teachernames as $teacher) {
            $teachers .= '
            <tr>
                <td>' . s($teacher->firstname . ' ' . $teacher->lastname) . '</td>
                <td>' . s($teacher->email) . '</td>
            </tr>';
        }
    }

    return '
    <div>
        <strong>Teachers</strong>
        <table class="generaltable tablestyle">
            ' . $teachers . '
        </table>
    </div>';
}

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
                <td><a target="_blank" href="' . $url . '">' . (int)$course->id . '</a></td>
                <td><a target="_blank" href="' . $url . '">' . s($course->shortname) . '</a></td>
                <td><a target="_blank" href="' . $url . '">' . s($course->fullname) . '</a></td>
            </tr>
        </table>
    </div>';
}

function display_groups_and_memberships($groups) {
    $memberships = $groups['memberships'];

    if (empty($groups['groups'])) {
        $return = '<tr><td>No groups found.</td></tr>';
    } else {
        $return = '<tr><th>Group Name</th><th>Members</th></tr>';
        foreach ($groups['groups'] as $group) {
            $count = isset($memberships[$group->id]) ? count($memberships[$group->id]) : 0;
            $return .= '
            <tr>
                <td>' . s($group->name) . '</td>
                <td>' . $count . ' member(s)</td>
            </tr>';
        }
    }

    return '
    <div>
        <strong>Groups and Memberships</strong>
        <table class="generaltable tablestyle">
            ' . $return . '
        </table>
    </div>';
}

function get_course_meta_courses($courseid) {
    global $DB;

    $linkedcourses = [];
    $enrols = $DB->get_records('enrol', [
        'courseid' => $courseid,
        'enrol'    => 'meta',
    ]);

    if ($enrols) {
        foreach ($enrols as $meta) {
            $course = $DB->get_record('course', ['id' => $meta->customint1]);
            if ($course) {
                $linkedcourses[] = $course;
            }
        }
    }

    return $linkedcourses;
}

function display_course_meta($connected) {
    if (empty($connected)) {
        $return = '<tr><td>No linked courses found.</td></tr>';
    } else {
        $return = '<tr><th>ID</th><th>Shortname</th><th>Fullname</th></tr>';
        foreach ($connected as $course) {
            $return .= '
            <tr>
                <td>' . (int)$course->id . '</td>
                <td>' . s($course->shortname) . '</td>
                <td>' . s($course->fullname) . '</td>
            </tr>';
        }
    }

    return '
    <div>
        <strong>Linked Courses</strong>
        <table class="generaltable tablestyle">
            ' . $return . '
        </table>
    </div>';
}

function get_course_groups_and_memberships($courseid) {
    global $DB;

    $groups = $DB->get_records('groups', ['courseid' => $courseid]);
    $groupmemberships = [];

    foreach ($groups as $group) {
        $members = $DB->get_records('groups_members', ['groupid' => $group->id]);
        $groupmemberships[$group->id] = $members;
    }

    return [
        'groups'       => $groups,
        'memberships'  => $groupmemberships,
    ];
}

/**
 * Generates a form to quickly search for a course by its ID or name.
 */
function quick_course_lookup_form() {
    return '
    <table>
        <tr>
            <th scope="col"><strong>Quick Course Search</strong></th>
        </tr>
    </table>
    <table class="quick_course_lookup">
        <tr>
            <td style="vertical-align: top"><strong>Course Search</strong></td>
            <td>
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

        function course_details_lookup(element) {
            if (element.value === "" || element.value === "Select a course") {
                jQuery("#course_details_results").html("");
                return;
            }

            jQuery.ajax({
                url: "lt_tools.php",
                type: "POST",
                data: { action: "course_details_lookup", courseid: element.value },
                success: function(data) {
                    jQuery("#course_details_results").html(data);
                }
            });
        }

        function executeWhenJQueryLoaded() {
            if (window.jQuery) {
                jQuery(document).ready(function() {
                    jQuery("#course_lookup").on("input", async function() {
                        var inputVal = jQuery(this).val();
                        var lookupResults = jQuery(`<select id="course_lookup_results" onchange="course_details_lookup(this)"><option>Select a course</option></select>`);

                        if (inputVal.length < 4) {
                            jQuery("#course_lookup_results").remove();
                            return;
                        }

                        await sleep(500);

                        if (inputVal !== jQuery("#course_lookup").val()) {
                            return;
                        }

                        jQuery("#course_lookup_loading").show();

                        jQuery.ajax({
                            url: "lt_tools.php",
                            type: "POST",
                            dataType: "json",
                            data: { action: "course_lookup", search: inputVal },
                            success: function(data) {
                                jQuery.each(data, function(index, item) {
                                    jQuery("<option>")
                                        .val(item.id)
                                        .text(item.id + " - " + item.fullname + " (" + item.shortname + ")")
                                        .appendTo(lookupResults);
                                });
                                jQuery("#course_lookup_results").remove();
                                jQuery("#course_lookup_loading").hide();
                                lookupResults.insertAfter(jQuery("#course_lookup"));
                            }
                        });
                    });
                });
            } else {
                setTimeout(executeWhenJQueryLoaded, 50);
            }
        }

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
 */
function multi_course_merger_form($answer = false) {
    global $params;

    $params["primarycourseid"]  = optional_param('primarycourseid', 0, PARAM_INT);
    $params["primarygroupvalue"] = optional_param('primarygroupvalue', '', PARAM_TEXT);

    if (!empty(data_submitted()) && isset(data_submitted()->linkedcourses)) {
        $params["linkedcourses"] = data_submitted()->linkedcourses;
    }

    $answer = isset($params['multi_course_merger_answer']) ? $params['multi_course_merger_answer'] : false;

    $linkedcourserows = '';
    if (!empty($params["linkedcourses"])) {
        foreach ($params["linkedcourses"] as $key => $linkedcourse) {
            $linkedcourserows .= linked_course_template(
                $key,
                $linkedcourse["id"] ?? '',
                $linkedcourse["group"] ?? ''
            );
        }
    } else {
        $linkedcourserows .= linked_course_template(time());
    }

    $return = '
    <form action="lt_tools.php" method="post">
        <input type="hidden" name="action" value="multi_course_merger">
        <input type="hidden" name="sesskey" value="' . sesskey() . '">
        <table>
            <tr>
                <th scope="col"><strong>Multicourse Quick Merger</strong></th>
            </tr>
        </table>

        <table class="multi_course_merger">
            <tr>
                <td style="vertical-align: top"><strong>Primary Course ID</strong></td>
                <td>
                    <input type="text" name="primarycourseid" value="' . s($params["primarycourseid"]) . '" />
                </td>
                <td style="vertical-align: top"><strong>Group Name</strong></td>
                <td>
                    <input type="text" name="primarygroupvalue" value="' . s($params["primarygroupvalue"]) . '" />
                </td>
            </tr>
        </table>

        ' . $linkedcourserows . '

        <button type="submit">Run Merge Course Tool</button>
    </form>

    <script defer type="text/javascript">
        function add_linked_course(elem) {
            jQuery(`' . linked_course_template('` + Date.now() + `') . '`).insertAfter(jQuery(elem).closest("table"));
        }

        function remove_linked_course(elem) {
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

    if ($answer) {
        $return .= '
            <table class="generaltable tablestyle">
                <tr>
                    <th style="vertical-align: top">Response</th>
                </tr>
                <tr>
                    <td>' . $answer . '</td>
                </tr>
            </table>';
    }

    return $return;
}

/**
 * Generates a table row for a linked course in the multicourse merger tool.
 */
function linked_course_template($key, $id = "", $groupname = "") {
    return '
    <table class="linked_course">
        <tr>
            <td style="vertical-align: top"><strong>Linked Course ID</strong></td>
            <td>
                <input type="text" name="linkedcourses[' . s($key) . '][id]" value="' . s($id) . '" />
            </td>
            <td style="vertical-align: top"><strong>Group Name</strong></td>
            <td>
                <input type="text" name="linkedcourses[' . s($key) . '][group]" value="' . s($groupname) . '" />
            </td>
            <td>
                <button class="delete_linked_course" type="button" onclick="remove_linked_course(this)">Delete</button>
            </td>
            <td>
                <button class="add_linked_course_button" type="button" onclick="add_linked_course(this)">Add linked Course</button>
            </td>
        </tr>
    </table>';
}

/**
 * Synchronizes a group from a linked course into the primary course.
 */
function sync_group_create($courseid, $groupname = "") {
    global $DB, $CFG;
    $response = "";
    $group = false;

    if (!empty($groupname)) {
        require_once($CFG->dirroot . '/group/lib.php');
        $group = $DB->get_record('groups', [
            'courseid' => $courseid,
            'name'     => $groupname,
        ]);

        if (!$group) {
            $group = (object) [
                "courseid"        => $courseid,
                "name"            => $groupname,
                "enablemessaging" => 0,
            ];
            $group->id = groups_create_group($group);
            $group = $DB->get_record('groups', ['id' => $group->id]);
            $response .= '<div><strong>' . s($groupname) . ' created</strong></div>';
        } else {
            $response .= '<div><strong>' . s($groupname) . ' found</strong></div>';
        }
    }

    return ['group' => $group, 'response' => $response];
}

/**
 * Synchronizes group membership.
 */
function sync_group_membership($enrollments, $role, $primarycoursecontext, $group, $groupname) {
    global $DB;
    $response = "";
    $added = 0;
    $skipped = 0;

    // Clear existing group memberships.
    $DB->delete_records('groups_members', ['groupid' => $group->id]);

    foreach ($enrollments as $enrollment) {
        $roleassignment = $DB->record_exists('role_assignments', [
            'roleid'    => $role->id,
            'userid'    => $enrollment->userid,
            'contextid' => $primarycoursecontext->id,
        ]);

        if (!$roleassignment) {
            $skipped++;
            continue;
        }

        $user = $DB->get_record('user', ['id' => $enrollment->userid], '*', MUST_EXIST);
        if (!$user) {
            $response .= '<div>Error: User could not be found: ' . (int)$enrollment->userid . '</div>';
            continue;
        }

        groups_add_member($group, $user);
        $added++;
    }

    if ($added > 0) {
        $response .= '
            <div>
                ' . $added . ' user(s) included.<br />
                ' . $skipped . ' non-student user(s) skipped.<br /><br />
            </div>';
    }

    return $response;
}

/**
 * Merges linked courses into a single course.
 */
function multi_course_merger() {
    global $DB, $CFG;

    $response = "";
    $primarycourseid   = optional_param('primarycourseid', 0, PARAM_INT);
    $primarygroupvalue = optional_param('primarygroupvalue', '', PARAM_TEXT);
    $linkedcourses     = data_submitted()->linkedcourses ?? [];

    if (empty($linkedcourses)) {
        return "At least 1 linked course is required.";
    }

    if (empty($primarycourseid)) {
        return "Primary course required.";
    }

    require_once($CFG->dirroot . '/enrol/meta/locallib.php');

    $primarycourse = $DB->get_record('course', ['id' => $primarycourseid]);
    if (!$primarycourse) {
        return "Primary course not found";
    }

    try {
        $primarycoursecontext = context_course::instance($primarycourseid, MUST_EXIST);
    } catch (moodle_exception $e) {
        return "Primary course not found";
    }

    if (!enrol_is_enabled('meta')) {
        return "Meta links are not allowed";
    }

    $role = $DB->get_record('role', ['shortname' => "student"]);
    if (!$role) {
        return "Student role not found";
    }

    // Handle primary course group for manual/database enrolments
    $enrols = $DB->get_records_sql(
        'SELECT * FROM {enrol} WHERE (enrol = ? OR enrol = ?) AND courseid = ?',
        ['manual', 'database', $primarycourseid]
    );

    if ($enrols) {
        foreach ($enrols as $enrol) {
            $enrollments = $DB->get_records('user_enrolments', [
                'enrolid' => $enrol->id,
                'status'  => 0,
            ], 'id ASC');

            if ($enrollments) {
                $groupsync = sync_group_create($primarycourseid, $primarygroupvalue);
                $response .= $groupsync['response'];
                $group = $groupsync['group'];

                if ($group) {
                    $response .= sync_group_membership(
                        $enrollments, $role, $primarycoursecontext, $group, $primarygroupvalue
                    );
                }
            }
        }
    }

    set_time_limit(0);
    ignore_user_abort(true);

    foreach ($linkedcourses as $linkedcourse) {
        if (empty($linkedcourse["id"])) {
            $response .= "<div>Linked course id is required.</div>";
            continue;
        }

        $course = $DB->get_record('course', ['id' => $linkedcourse["id"]]);
        if (!$course) {
            $response .= "<div>Course " . s($linkedcourse["id"]) . " could not be found.</div>";
            continue;
        }

        // Check / create meta link
        $enrols = $DB->get_record('enrol', [
            'courseid'   => $primarycourseid,
            'customint1' => $linkedcourse["id"],
            'enrol'      => 'meta',
        ]);

        if (!$enrols) {
            $enrolplugin = enrol_get_plugin('meta');
            $fields = [
                'customint1' => $linkedcourse["id"],
                'customint2' => 0,
            ];
            $enrolid = $enrolplugin->add_instance($primarycourse, $fields);
            if (!(bool)$enrolid) {
                $response .= "<div>Could not create meta link for course: " . s($linkedcourse["id"]) . "</div>";
                continue;
            }
            $response .= '
                <div>
                    <strong>Metalink created: ' . s($linkedcourse["id"]) . ' → ' . (int)$primarycourseid . '</strong>
                </div>';
            $enrols = $DB->get_record('enrol', ['id' => $enrolid]);
        }

        if (empty($linkedcourse["group"])) {
            continue;
        }

        $groupsync = sync_group_create($primarycourseid, $linkedcourse["group"]);
        $response .= $groupsync['response'];
        $group = $groupsync['group'];

        if (!$group) {
            continue;
        }

        $enrollments = $DB->get_records('user_enrolments', [
            'enrolid' => $enrols->id,
            'status'  => 0,
        ], 'id ASC');

        if (!$enrollments) {
            $response .= "<div>No enrollments found in course: " . s($linkedcourse["id"]) .
                         " with enrolid: " . (int)$enrols->id . "</div>";
            continue;
        }

        $response .= sync_group_membership(
            $enrollments, $role, $primarycoursecontext, $group, $linkedcourse["group"]
        );
    }

    $response .= "<div><strong>Course Merge Finished</strong></div>";
    return $response;
}

// Final output
echo '
    <style>
        table td {
            padding: 5px;
        }
    </style>
    <script type="text/javascript">
        window.addEventListener("load", function() {
            // Reserved for future jQuery needs
        });
    </script>';

echo main_tool_form($params);
echo $OUTPUT->footer();