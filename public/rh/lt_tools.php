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

// Only these users may run the raw SQL query tool.
const LT_TOOLS_SQL_ALLOWED_EMAILS = [
    'davidso1@rose-hulman.edu',
];

// Only these actions are allowed via AJAX
const LT_TOOLS_AJAX_ACTIONS = [
    'course_lookup',
    'course_details_lookup',
    'lt_user_lookup',
    'lt_user_details_lookup',
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

    // AJAX endpoints must enforce the same access rules as the page itself.
    require_login();
    if (!in_array($USER->email, LT_TOOLS_ALLOWED_EMAILS, true)) {
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
} elseif ($action !== 'multifunction' && array_key_exists($action, $actions)) {
    // Require sesskey for any action that can modify data or run arbitrary queries.
    if (in_array($action, ['multi_course_merger', 'lookup_sql'], true)) {
        require_sesskey();
    }
    $params[$action . '_answer'] = $action();
}

/**
 * Generates the main tool form.
 */
function main_tool_form($params = []) {
    global $USER, $action, $actions;

    // Tab id => label + content. Order here is the order shown across the top.
    $tabs = [];

    $answer = $params['lookup_multifunction_answer'] ?? false;
    $tabs['search'] = [
        'label' => 'Search',
        'html'  => lt_tools_section(lookup_multifunction_form($answer))
                 . lt_tools_section(quick_user_lookup_form())
                 . lt_tools_section(quick_course_lookup_form()),
    ];

    $tabs['merge'] = [
        'label' => 'Course Merger',
        'html'  => lt_tools_section(multi_course_merger_form($params['multi_course_merger_answer'] ?? false)),
    ];

    // SQL tool is only available to the SQL allowlist.
    if (in_array($USER->email, LT_TOOLS_SQL_ALLOWED_EMAILS, true)) {
        $answer = $params['lookup_sql_answer'] ?? false;
        $tabs['sql'] = [
            'label' => 'SQL',
            'html'  => lt_tools_section(lookup_sql_form($answer)),
        ];
    }

    // After a form submit, reopen the tab that submitted it.
    $actiontab = null;
    if (!empty($action)) {
        if (array_key_exists($action, $actions['multifunction'])) {
            $actiontab = 'search';
        } elseif ($action === 'multi_course_merger') {
            $actiontab = 'merge';
        } elseif ($action === 'lookup_sql') {
            $actiontab = 'sql';
        }
    }
    if ($actiontab !== null && !isset($tabs[$actiontab])) {
        $actiontab = null;
    }

    return lt_tools_render_tabs($tabs, $actiontab);
}

/**
 * Wraps one tool in a section block.
 */
function lt_tools_section($html) {
    return '<div class="lt-section">' . $html . '</div>';
}

/**
 * Renders a tab bar plus one panel per tab.
 *
 * @param array       $tabs      id => ['label' => string, 'html' => string]
 * @param string|null $forcedtab Tab that must be open (e.g. the one that just submitted a form).
 *                               When null, the tab is taken from the URL hash, then the last
 *                               tab used, then the first tab.
 */
function lt_tools_render_tabs(array $tabs, $forcedtab = null) {
    $ids = array_keys($tabs);
    $initial = ($forcedtab !== null && isset($tabs[$forcedtab])) ? $forcedtab : reset($ids);

    $nav = '';
    $panels = '';
    foreach ($tabs as $id => $tab) {
        $isactive = ($id === $initial);
        $nav .= '<button type="button" role="tab" class="lt-tab' . ($isactive ? ' active' : '') . '"'
              . ' id="lt-tab-' . s($id) . '" data-tab="' . s($id) . '"'
              . ' aria-controls="lt-panel-' . s($id) . '"'
              . ' aria-selected="' . ($isactive ? 'true' : 'false') . '"'
              . ' tabindex="' . ($isactive ? '0' : '-1') . '">' . s($tab['label']) . '</button>';

        $panels .= '<div role="tabpanel" class="lt-tabpanel' . ($isactive ? ' active' : '') . '"'
                 . ' id="lt-panel-' . s($id) . '" data-tab="' . s($id) . '"'
                 . ' aria-labelledby="lt-tab-' . s($id) . '">' . $tab['html'] . '</div>';
    }

    return '
    <style>
        .lt-tabs {
            display: flex;
            gap: 4px;
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 20px;
        }
        .lt-tab {
            background: none;
            border: 1px solid transparent;
            border-bottom: none;
            border-radius: 4px 4px 0 0;
            padding: 8px 20px;
            margin-bottom: -2px;
            cursor: pointer;
            font-weight: 500;
            color: #0f6cbf;
        }
        .lt-tab:hover {
            background: #f1f3f5;
        }
        .lt-tab:focus-visible {
            outline: 2px solid #0f6cbf;
            outline-offset: -2px;
        }
        .lt-tab.active {
            background: #fff;
            border-color: #dee2e6;
            border-bottom: 2px solid #fff;
            color: #212529;
            font-weight: 700;
        }
        .lt-tabpanel {
            display: none;
        }
        .lt-tabpanel.active {
            display: block;
        }
        .lt-section + .lt-section {
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #dee2e6;
        }
    </style>

    <div class="lt-tabs" role="tablist" aria-label="L&amp;T Tools">' . $nav . '</div>
    ' . $panels . '

    <script>
        (function() {
            var STORAGE_KEY = "lt_tools_active_tab";
            var forced = ' . json_encode($forcedtab !== null && isset($tabs[$forcedtab]) ? $forcedtab : "") . ';
            var tabs = Array.prototype.slice.call(document.querySelectorAll(".lt-tab"));
            var panels = Array.prototype.slice.call(document.querySelectorAll(".lt-tabpanel"));

            function exists(name) {
                return tabs.some(function(t) { return t.dataset.tab === name; });
            }

            function activate(name, persist) {
                if (!exists(name)) {
                    name = tabs[0].dataset.tab;
                }
                tabs.forEach(function(t) {
                    var on = t.dataset.tab === name;
                    t.classList.toggle("active", on);
                    t.setAttribute("aria-selected", on ? "true" : "false");
                    t.tabIndex = on ? 0 : -1;
                });
                panels.forEach(function(p) {
                    p.classList.toggle("active", p.dataset.tab === name);
                });
                if (persist) {
                    try { localStorage.setItem(STORAGE_KEY, name); } catch (e) {}
                    if (window.history && history.replaceState) {
                        history.replaceState(null, "", "#" + name);
                    }
                }
            }

            tabs.forEach(function(tab, index) {
                tab.addEventListener("click", function() {
                    activate(tab.dataset.tab, true);
                });
                // Left/Right arrows move between tabs.
                tab.addEventListener("keydown", function(e) {
                    var next = null;
                    if (e.key === "ArrowRight") { next = tabs[(index + 1) % tabs.length]; }
                    if (e.key === "ArrowLeft")  { next = tabs[(index - 1 + tabs.length) % tabs.length]; }
                    if (next) {
                        e.preventDefault();
                        activate(next.dataset.tab, true);
                        next.focus();
                    }
                });
            });

            // A submitted form wins; otherwise URL hash, then last used tab.
            var hash = window.location.hash.replace("#", "");
            var saved = "";
            try { saved = localStorage.getItem(STORAGE_KEY) || ""; } catch (e) {}
            activate(forced || hash || saved, !!forced);
        })();
    </script>';
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
    global $DB, $CFG, $USER, $params;

    // The form is hidden from other users, but the handler can be POSTed to directly.
    if (!in_array($USER->email, LT_TOOLS_SQL_ALLOWED_EMAILS, true)) {
        return 'Error: You do not have permission to run SQL queries.';
    }

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
    $enrolurl = new moodle_url('/enrol/instances.php', ['id' => $course->id]);
    $groupsurl = new moodle_url('/group/index.php', ['id' => $course->id]);
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
        <strong>Quick Links</strong>
        <table class="generaltable tablestyle">
            <tr>
                <td><a target="_blank" href="' . $enrolurl . '">Enrolment methods</a></td>
                <td><a target="_blank" href="' . $groupsurl . '">Groups</a></td>
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
 * Search for users by username, first name, last name, full name or user id (AJAX).
 */
function lt_user_lookup() {
    global $DB;

    $search = trim(optional_param('search', '', PARAM_TEXT));
    $users = [];

    if ($search !== '') {
        $like = '%' . $DB->sql_like_escape($search) . '%';
        $fullname = $DB->sql_concat_join("' '", ['firstname', 'lastname']);

        $sql = 'SELECT id, username, firstname, lastname, suspended, deleted
                  FROM {user}
                 WHERE (' . $DB->sql_like('username', ':username', false, false) . '
                     OR ' . $DB->sql_like('firstname', ':firstname', false, false) . '
                     OR ' . $DB->sql_like('lastname', ':lastname', false, false) . '
                     OR ' . $DB->sql_like($fullname, ':fullname', false, false);
        $params = [
            'username'  => $like,
            'firstname' => $like,
            'lastname'  => $like,
            'fullname'  => $like,
        ];

        // Numeric input is also treated as a user id.
        if (ctype_digit($search) && strlen($search) <= 10) {
            $sql .= ' OR id = :userid';
            $params['userid'] = (int)$search;
        }

        $sql .= ')
              ORDER BY deleted ASC, lastname ASC, firstname ASC';

        foreach ($DB->get_records_sql($sql, $params, 0, 25) as $user) {
            $users[] = [
                'id'        => (int)$user->id,
                'username'  => $user->username,
                'firstname' => $user->firstname,
                'lastname'  => $user->lastname,
                'suspended' => (bool)$user->suspended,
                'deleted'   => (bool)$user->deleted,
            ];
        }
    }

    header('Content-Type: application/json');
    echo json_encode($users);
    exit;
}

/**
 * User details (AJAX): profile link, today's log links and the last 3 courses visited.
 */
function lt_user_details_lookup() {
    global $DB;

    $userid = optional_param('userid', 0, PARAM_INT);
    if (!$userid) {
        echo 'Invalid user ID';
        exit;
    }

    $user = $DB->get_record('user', ['id' => $userid]);
    if (!$user) {
        echo 'User not found';
        exit;
    }

    $midnight = usergetmidnight(time());

    $profileurl = new moodle_url('/user/profile.php', ['id' => $user->id]);

    // Log viewer, all courses, filtered to this user and today's date.
    $todaylogsurl = new moodle_url('/report/log/index.php', [
        'chooselog'   => 1,
        'showusers'   => 1,
        'showcourses' => 1,
        'id'          => SITEID,
        'user'        => $user->id,
        'date'        => $midnight,
        'modid'       => '',
        'edulevel'    => -1,
    ]);

    // Moodle's built-in "today's logs" user activity report.
    $todayreporturl = new moodle_url('/report/log/user.php', [
        'id'     => $user->id,
        'course' => SITEID,
        'mode'   => 'today',
    ]);

    // Last 3 courses visited (user_lastaccess holds one row per user per course).
    $courses = $DB->get_records_sql(
        'SELECT c.id, c.fullname, c.shortname, ul.timeaccess
           FROM {user_lastaccess} ul
           JOIN {course} c ON c.id = ul.courseid
          WHERE ul.userid = :userid
       ORDER BY ul.timeaccess DESC',
        ['userid' => $user->id],
        0,
        3
    );

    $flags = '';
    if (!empty($user->deleted)) {
        $flags .= ' <strong style="color: #b00020;">(deleted)</strong>';
    }
    if (!empty($user->suspended)) {
        $flags .= ' <strong style="color: #b00020;">(suspended)</strong>';
    }

    $lastaccess = $user->lastaccess ? userdate($user->lastaccess) : 'Never';

    $userinfo = '
    <div>
        <strong>User</strong>
        <table class="generaltable tablestyle">
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Name</th>
                <th>Email</th>
                <th>Last site access</th>
            </tr>
            <tr>
                <td>' . (int)$user->id . '</td>
                <td>' . s($user->username) . '</td>
                <td>' . s($user->firstname . ' ' . $user->lastname) . $flags . '</td>
                <td>' . s($user->email) . '</td>
                <td>' . s($lastaccess) . '</td>
            </tr>
        </table>
    </div>';

    $links = '
    <div>
        <strong>Quick Links</strong>
        <table class="generaltable tablestyle">
            <tr>
                <td><a target="_blank" href="' . $profileurl . '">Profile</a></td>
                <td><a target="_blank" href="' . $todaylogsurl . '">Today\'s logs (all courses)</a></td>
                <td><a target="_blank" href="' . $todayreporturl . '">Today\'s user activity report</a></td>
            </tr>
        </table>
    </div>';

    if (empty($courses)) {
        $courserows = '<tr><td>No course visits found.</td></tr>';
    } else {
        $courserows = '<tr><th>ID</th><th>Course</th><th>Last visited</th><th>Logs today</th></tr>';
        foreach ($courses as $course) {
            $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
            $courselogsurl = new moodle_url('/report/log/index.php', [
                'chooselog' => 1,
                'showusers' => 1,
                'id'        => $course->id,
                'user'      => $user->id,
                'date'      => $midnight,
                'modid'     => '',
                'edulevel'  => -1,
            ]);
            $courserows .= '
            <tr>
                <td>' . (int)$course->id . '</td>
                <td><a target="_blank" href="' . $courseurl . '">' . s($course->fullname) . '</a>
                    (' . s($course->shortname) . ')</td>
                <td>' . s(userdate($course->timeaccess)) . '</td>
                <td><a target="_blank" href="' . $courselogsurl . '">Logs</a></td>
            </tr>';
        }
    }

    $recentcourses = '
    <div>
        <strong>Last 3 Courses Visited</strong>
        <table class="generaltable tablestyle">
            ' . $courserows . '
        </table>
    </div>';

    echo '
    <style>
        div.user_details {
            display: flex;
        }
        div.user_details > div {
            width: 50%;
        }
    </style>
    ' . $userinfo . '
    <div class="user_details">
        ' . $links . '
        <div style="width: 10px"></div>
        ' . $recentcourses . '
    </div>';
    exit;
}

/**
 * Generates a form to quickly search for a user by username, name or user id.
 */
function quick_user_lookup_form() {
    return '
    <table>
        <tr>
            <th scope="col"><strong>Quick User Search</strong></th>
        </tr>
    </table>
    <table class="quick_user_lookup">
        <tr>
            <td style="vertical-align: top"><strong>User Search</strong></td>
            <td>
                <input type="text" id="user_lookup" size="35" autocomplete="off"
                       placeholder="username, first/last name or user ID" />
                <span id="user_lookup_loading" style="display: none;">Loading...</span>
            </td>
        </tr>
    </table>
    <div id="user_details_results"></div>

    <script defer type="text/javascript">
        function user_details_lookup(userid) {
            if (!userid) {
                jQuery("#user_details_results").html("");
                return;
            }

            jQuery.ajax({
                url: "lt_tools.php",
                type: "POST",
                data: { action: "lt_user_details_lookup", userid: userid },
                success: function(data) {
                    jQuery("#user_details_results").html(data);
                }
            });
        }

        function executeUserLookupWhenJQueryLoaded() {
            if (window.jQuery) {
                jQuery(document).ready(function() {
                    var timer = null;
                    var requestseq = 0;

                    function runUserSearch() {
                        var inputVal = jQuery("#user_lookup").val().trim();
                        var seq = ++requestseq;

                        jQuery("#user_lookup_results").remove();

                        if (inputVal === "") {
                            jQuery("#user_details_results").html("");
                            return;
                        }

                        // Short text is too broad, but any number can be a user id.
                        if (inputVal.length < 3 && !/^[0-9]+$/.test(inputVal)) {
                            return;
                        }

                        jQuery("#user_lookup_loading").show();

                        jQuery.ajax({
                            url: "lt_tools.php",
                            type: "POST",
                            dataType: "json",
                            data: { action: "lt_user_lookup", search: inputVal },
                            success: function(data) {
                                // Ignore responses for an outdated search.
                                if (seq !== requestseq) {
                                    return;
                                }

                                jQuery("#user_lookup_results").remove();

                                if (data.length === 0) {
                                    jQuery("#user_details_results").html("");
                                    jQuery("<span id=\"user_lookup_results\">No users found.</span>")
                                        .insertAfter(jQuery("#user_lookup"));
                                } else if (data.length === 1) {
                                    user_details_lookup(data[0].id);
                                } else {
                                    var lookupResults = jQuery("<select id=\"user_lookup_results\"></select>")
                                        .on("change", function() { user_details_lookup(this.value); });

                                    jQuery("<option>").val("").text("Select a user").appendTo(lookupResults);
                                    jQuery.each(data, function(index, item) {
                                        var flags = (item.deleted ? " [deleted]" : "") + (item.suspended ? " [suspended]" : "");
                                        jQuery("<option>")
                                            .val(item.id)
                                            .text(item.id + " - " + item.firstname + " " + item.lastname + " (" + item.username + ")" + flags)
                                            .appendTo(lookupResults);
                                    });
                                    jQuery("#user_details_results").html("");
                                    lookupResults.insertAfter(jQuery("#user_lookup"));
                                }
                            },
                            complete: function() {
                                if (seq === requestseq) {
                                    jQuery("#user_lookup_loading").hide();
                                }
                            }
                        });
                    }

                    jQuery("#user_lookup").on("input", function() {
                        clearTimeout(timer);
                        timer = setTimeout(runUserSearch, 400);
                    });

                    // Enter searches immediately.
                    jQuery("#user_lookup").on("keydown", function(e) {
                        if (e.key === "Enter") {
                            e.preventDefault();
                            clearTimeout(timer);
                            runUserSearch();
                        }
                    });
                });
            } else {
                setTimeout(executeUserLookupWhenJQueryLoaded, 50);
            }
        }

        executeUserLookupWhenJQueryLoaded();
    </script>
    <style>
        #user_lookup_results {
            margin-left: 10px;
            padding: 2px;
        }
    </style>
    ';
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
 * Builds a merger result message.
 *
 * @param string $type  error|warning|info|created|success
 * @param string $text  Plain text (escaped when rendered).
 */
function mcm_msg($type, $text) {
    return ['type' => $type, 'text' => $text];
}

/**
 * Renders merger result messages as HTML.
 */
function mcm_render_messages(array $messages) {
    $styles = [
        'error'   => 'color: #b00020;',
        'warning' => 'color: #b26a00;',
        'created' => 'font-weight: bold;',
        'success' => 'font-weight: bold;',
    ];

    $html = '';
    foreach ($messages as $message) {
        $style = $styles[$message['type']] ?? '';
        $html .= '<div style="' . $style . '">' . s($message['text']) . '</div>';
    }
    return $html;
}

/**
 * Reads and cleans the submitted linked course rows.
 *
 * @return array[] Each row: ['rawid' => string, 'id' => int (0 if invalid), 'group' => string]
 */
function mcm_get_linked_input() {
    $submitted = data_submitted();
    $rows = [];

    if (empty($submitted->linkedcourses) || !is_array($submitted->linkedcourses)) {
        return $rows;
    }

    $clean = function ($value) {
        return is_scalar($value) ? trim(clean_param((string)$value, PARAM_TEXT)) : '';
    };

    foreach ($submitted->linkedcourses as $row) {
        if (!is_array($row)) {
            continue;
        }
        $rawid = $clean($row['id'] ?? '');
        $rows[] = [
            'rawid' => $rawid,
            'id'    => (ctype_digit($rawid) && strlen($rawid) <= 10) ? (int)$rawid : 0,
            'group' => $clean($row['group'] ?? ''),
        ];
    }

    return $rows;
}

/**
 * Generates the HTML form for the multicourse merger tool.
 *
 * @param array|false $answer Messages returned by multi_course_merger(), if it just ran.
 */
function multi_course_merger_form($answer = false) {
    $primarycourseid   = optional_param('primarycourseid', 0, PARAM_INT);
    $primarygroupvalue = optional_param('primarygroupvalue', '', PARAM_TEXT);
    $dryrun            = optional_param('dryrun', 0, PARAM_BOOL);

    $linkedcourserows = '';
    foreach (mcm_get_linked_input() as $i => $linkedcourse) {
        $linkedcourserows .= linked_course_template($i, $linkedcourse['rawid'], $linkedcourse['group']);
    }
    if ($linkedcourserows === '') {
        $linkedcourserows = linked_course_template(time());
    }

    $return = '
    <form action="lt_tools.php" method="post" onsubmit="return multi_course_merger_submit(this);">
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
                    <input type="text" name="primarycourseid" value="' . ($primarycourseid ? (int)$primarycourseid : '') . '" />
                </td>
                <td style="vertical-align: top"><strong>Group Name</strong></td>
                <td>
                    <input type="text" name="primarygroupvalue" value="' . s($primarygroupvalue) . '" />
                </td>
            </tr>
        </table>

        ' . $linkedcourserows . '

        <label style="margin: 10px 0;">
            <input type="checkbox" name="dryrun" value="1"' . ($dryrun ? ' checked' : '') . ' />
            Dry run (show what would change, make no changes)
        </label>
        <br />
        <button type="submit" id="mcm_submit">Run Merge Course Tool</button>
    </form>

    <template id="linked_course_template">' . linked_course_template('__KEY__') . '</template>

    <script defer type="text/javascript">
        function add_linked_course(elem) {
            var html = document.getElementById("linked_course_template").innerHTML.replace(/__KEY__/g, Date.now());
            jQuery(html.trim()).insertAfter(jQuery(elem).closest("table"));
        }

        function remove_linked_course(elem) {
            if (jQuery("table.linked_course").length > 1) {
                jQuery(elem).closest("table").remove();
            }
        }

        function multi_course_merger_submit(form) {
            var dryrun = form.elements.dryrun.checked;
            if (!dryrun && !confirm("Run the merge? This creates meta links and changes group memberships.")) {
                return false;
            }
            var button = document.getElementById("mcm_submit");
            button.disabled = true;
            button.textContent = dryrun ? "Running dry run..." : "Running merge...";
            return true;
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
                    <td>' . mcm_render_messages($answer) . '</td>
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
 * Finds a group by name in a course, creating it if missing.
 *
 * In dry run mode nothing is created; 'group' is false when it would have been.
 *
 * @return array ['group' => stdClass|false, 'messages' => array]
 */
function sync_group_create($courseid, $groupname, $dryrun = false) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/group/lib.php');

    $messages = [];
    $group = false;

    $groupname = trim($groupname);
    if ($groupname === '') {
        return ['group' => false, 'messages' => []];
    }

    $groupid = groups_get_group_by_name($courseid, $groupname);

    if ($groupid) {
        $group = $DB->get_record('groups', ['id' => $groupid], '*', MUST_EXIST);
        $messages[] = mcm_msg('info', 'Group "' . $groupname . '" found.');

        if ($DB->count_records('groups', ['courseid' => $courseid, 'name' => $groupname]) > 1) {
            $messages[] = mcm_msg('warning', 'Course ' . (int)$courseid . ' has several groups named "' .
                $groupname . '"; using group ' . (int)$group->id . '.');
        }
    } elseif ($dryrun) {
        $messages[] = mcm_msg('created', 'Would create group "' . $groupname . '".');
    } else {
        $newgroup = (object) [
            'courseid'        => $courseid,
            'name'            => $groupname,
            'enablemessaging' => 0,
        ];
        $newgroup->id = groups_create_group($newgroup);
        $group = $DB->get_record('groups', ['id' => $newgroup->id], '*', MUST_EXIST);
        $messages[] = mcm_msg('created', 'Group "' . $groupname . '" created.');
    }

    return ['group' => $group, 'messages' => $messages];
}

/**
 * Returns ids of users with an active enrolment in any of the given enrol instances.
 *
 * Active means: user enrolment active and within its start/end dates, enrol instance
 * enabled, and the user account not deleted.
 *
 * @param int[] $enrolids
 * @return int[]
 */
function mcm_get_active_userids(array $enrolids) {
    global $DB;

    if (empty($enrolids)) {
        return [];
    }

    [$insql, $inparams] = $DB->get_in_or_equal($enrolids, SQL_PARAMS_NAMED, 'enrolid');
    $now = time();

    $sql = "SELECT DISTINCT ue.userid
              FROM {user_enrolments} ue
              JOIN {enrol} e ON e.id = ue.enrolid
              JOIN {user} u ON u.id = ue.userid
             WHERE ue.enrolid $insql
               AND ue.status = :uestatus
               AND e.status = :estatus
               AND u.deleted = 0
               AND (ue.timestart = 0 OR ue.timestart <= :now1)
               AND (ue.timeend = 0 OR ue.timeend > :now2)";

    return array_map('intval', $DB->get_fieldset_sql($sql, $inparams + [
        'uestatus' => ENROL_USER_ACTIVE,
        'estatus'  => ENROL_INSTANCE_ENABLED,
        'now1'     => $now,
        'now2'     => $now,
    ]));
}

/**
 * Filters user ids down to those holding the given role in the given context.
 *
 * @param int[] $userids
 * @return int[]
 */
function mcm_filter_role_holders(array $userids, $roleid, $contextid) {
    global $DB;

    $holders = [];
    foreach (array_chunk($userids, 1000) as $chunk) {
        [$insql, $inparams] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'uid');
        $found = $DB->get_fieldset_sql(
            "SELECT DISTINCT ra.userid
               FROM {role_assignments} ra
              WHERE ra.roleid = :roleid AND ra.contextid = :contextid AND ra.userid $insql",
            ['roleid' => $roleid, 'contextid' => $contextid] + $inparams
        );
        $holders = array_merge($holders, array_map('intval', $found));
    }

    return $holders;
}

/**
 * Makes a group's membership match the given users (students only).
 *
 * Only members that actually differ are added or removed, using the groups API so
 * events fire and caches stay correct.
 *
 * @param int[]          $userids  Candidate users (active enrolments).
 * @param stdClass       $role     Student role record.
 * @param context_course $primarycoursecontext
 * @param stdClass|false $group    Group record, or false when it would be created (dry run).
 * @return array Messages.
 */
function sync_group_membership(array $userids, $role, $primarycoursecontext, $group, $groupname, $dryrun = false) {
    global $DB;

    $userids  = array_values(array_unique(array_map('intval', $userids)));
    $eligible = array_values(array_unique(mcm_filter_role_holders($userids, $role->id, $primarycoursecontext->id)));
    $skipped  = count($userids) - count($eligible);

    $current  = $group ? array_map('intval', $DB->get_fieldset_select('groups_members', 'userid', 'groupid = ?', [$group->id])) : [];
    $toremove = array_diff($current, $eligible);
    $toadd    = array_diff($eligible, $current);
    $unchanged = count(array_intersect($current, $eligible));

    $added = $removed = $failed = 0;

    if ($dryrun) {
        $added = count($toadd);
        $removed = count($toremove);
    } else {
        foreach ($toremove as $userid) {
            if (groups_remove_member($group->id, $userid)) {
                $removed++;
            } else {
                $failed++;
            }
        }
        foreach ($toadd as $userid) {
            if (groups_add_member($group->id, $userid)) {
                $added++;
            } else {
                $failed++;
            }
        }
    }

    $messages = [mcm_msg('info', sprintf(
        'Group "%s": %s %d, %s %d, %d unchanged, %d non-student user(s) skipped.',
        $groupname,
        $dryrun ? 'would add' : 'added',
        $added,
        $dryrun ? 'would remove' : 'removed',
        $removed,
        $unchanged,
        $skipped
    ))];

    if ($failed > 0) {
        $messages[] = mcm_msg('warning', 'Group "' . $groupname . '": ' . $failed .
            ' membership change(s) failed (for example a user who is not enrolled in the course).');
    }

    return $messages;
}

/**
 * Finds/creates a group and syncs its membership from the given users.
 *
 * @return array Messages.
 */
function mcm_sync_group($courseid, $groupname, array $userids, $role, $context, $dryrun) {
    $created  = sync_group_create($courseid, $groupname, $dryrun);
    $messages = $created['messages'];

    if (empty($userids)) {
        $messages[] = mcm_msg('info', 'No active enrolments found for group "' . $groupname .
            '"; its membership was left unchanged.');
        return $messages;
    }

    return array_merge(
        $messages,
        sync_group_membership($userids, $role, $context, $created['group'], $groupname, $dryrun)
    );
}

/**
 * Creates the meta link for one linked course (if needed) and syncs its group.
 *
 * @return array Messages.
 */
function mcm_process_linked_course($primarycourse, $primarycontext, $linkedid, $groupname, $enrolplugin, $role, $dryrun) {
    global $DB;

    $messages = [];

    if (!$DB->record_exists('course', ['id' => $linkedid])) {
        return [mcm_msg('error', 'Course ' . $linkedid . ' could not be found.')];
    }

    $existing = $DB->get_records('enrol', [
        'courseid'   => $primarycourse->id,
        'customint1' => $linkedid,
        'enrol'      => 'meta',
    ], 'id ASC', '*', 0, 1);
    $metainstance = $existing ? reset($existing) : false;

    if ($metainstance) {
        $messages[] = mcm_msg('info', 'Meta link ' . $linkedid . ' → ' . (int)$primarycourse->id . ' already exists.');
    } elseif ($dryrun) {
        $messages[] = mcm_msg('created', 'Would create meta link: ' . $linkedid . ' → ' . (int)$primarycourse->id . '.');
        if ($groupname !== '') {
            $messages[] = mcm_msg('info', 'Group "' . $groupname .
                '": membership preview is only available once the meta link exists.');
        }
        return $messages;
    } else {
        if (!$enrolplugin->can_add_instance($primarycourse->id)) {
            return [mcm_msg('error', 'You do not have permission to add meta links to course ' .
                (int)$primarycourse->id . '.')];
        }

        $enrolid = $enrolplugin->add_instance($primarycourse, [
            'customint1' => $linkedid,
            'customint2' => 0,
        ]);
        if (!$enrolid) {
            return [mcm_msg('error', 'Could not create meta link for course: ' . $linkedid)];
        }

        $messages[] = mcm_msg('created', 'Metalink created: ' . $linkedid . ' → ' . (int)$primarycourse->id);
        $metainstance = $DB->get_record('enrol', ['id' => $enrolid], '*', MUST_EXIST);
    }

    if ($groupname === '') {
        return $messages;
    }

    $userids = mcm_get_active_userids([$metainstance->id]);
    return array_merge(
        $messages,
        mcm_sync_group($primarycourse->id, $groupname, $userids, $role, $primarycontext, $dryrun)
    );
}

/**
 * Merges linked courses into a single course.
 *
 * @return array Messages (see mcm_msg()).
 */
function multi_course_merger() {
    global $DB, $CFG;

    // This can run for a long time; lift limits before doing any work.
    set_time_limit(0);
    ignore_user_abort(true);

    require_once($CFG->dirroot . '/group/lib.php');
    require_once($CFG->dirroot . '/enrol/meta/locallib.php');

    $dryrun           = (bool)optional_param('dryrun', 0, PARAM_BOOL);
    $primarycourseid  = optional_param('primarycourseid', 0, PARAM_INT);
    $primarygroupname = trim(optional_param('primarygroupvalue', '', PARAM_TEXT));

    // Ignore completely blank rows.
    $linkedinput = array_filter(mcm_get_linked_input(), function ($row) {
        return $row['rawid'] !== '' || $row['group'] !== '';
    });

    if (empty($linkedinput)) {
        return [mcm_msg('error', 'At least 1 linked course is required.')];
    }
    if (empty($primarycourseid)) {
        return [mcm_msg('error', 'Primary course required.')];
    }
    if ($primarycourseid == SITEID) {
        return [mcm_msg('error', 'The site course cannot be used as the primary course.')];
    }

    $primarycourse = $DB->get_record('course', ['id' => $primarycourseid]);
    if (!$primarycourse) {
        return [mcm_msg('error', 'Primary course not found')];
    }

    $primarycontext = context_course::instance($primarycourseid, IGNORE_MISSING);
    if (!$primarycontext) {
        return [mcm_msg('error', 'Primary course not found')];
    }

    if (!enrol_is_enabled('meta')) {
        return [mcm_msg('error', 'Meta links are not allowed')];
    }
    $enrolplugin = enrol_get_plugin('meta');
    if (!$enrolplugin) {
        return [mcm_msg('error', 'Meta enrolment plugin not available')];
    }

    $studentrole = $DB->get_record('role', ['shortname' => 'student']);
    if (!$studentrole) {
        return [mcm_msg('error', 'Student role not found')];
    }

    $messages = [];
    if ($dryrun) {
        $messages[] = mcm_msg('warning', 'Dry run: no changes will be made.');
    }

    // Primary course group: built from ALL manual/database enrol instances at once,
    // so one instance can't undo another's work.
    if ($primarygroupname !== '') {
        try {
            [$insql, $inparams] = $DB->get_in_or_equal(['manual', 'database']);
            $primaryenrolids = $DB->get_fieldset_select(
                'enrol', 'id', "courseid = ? AND enrol $insql", array_merge([$primarycourseid], $inparams)
            );

            if (empty($primaryenrolids)) {
                $messages[] = mcm_msg('info', 'No manual or database enrolment methods in the primary course; ' .
                    'primary group skipped.');
            } else {
                $messages = array_merge($messages, mcm_sync_group(
                    $primarycourseid,
                    $primarygroupname,
                    mcm_get_active_userids($primaryenrolids),
                    $studentrole,
                    $primarycontext,
                    $dryrun
                ));
            }
        } catch (\Throwable $e) {
            $messages[] = mcm_msg('error', 'Primary course group: ' . $e->getMessage());
        }
    }

    $seen = [];
    foreach ($linkedinput as $row) {
        if ($row['id'] <= 0) {
            $messages[] = mcm_msg('error', 'Linked course id is required and must be a number' .
                ($row['rawid'] !== '' ? ' (got "' . $row['rawid'] . '")' : '') . '.');
            continue;
        }

        $linkedid = $row['id'];

        if ($linkedid == $primarycourseid) {
            $messages[] = mcm_msg('error', 'Course ' . $linkedid . ' is the primary course and cannot be linked to itself.');
            continue;
        }
        if ($linkedid == SITEID) {
            $messages[] = mcm_msg('error', 'The site course cannot be linked.');
            continue;
        }
        if (isset($seen[$linkedid])) {
            $messages[] = mcm_msg('warning', 'Course ' . $linkedid . ' was listed more than once; duplicate row skipped.');
            continue;
        }
        $seen[$linkedid] = true;

        try {
            $messages = array_merge($messages, mcm_process_linked_course(
                $primarycourse, $primarycontext, $linkedid, $row['group'], $enrolplugin, $studentrole, $dryrun
            ));
        } catch (\Throwable $e) {
            $messages[] = mcm_msg('error', 'Course ' . $linkedid . ': ' . $e->getMessage());
        }
    }

    $messages[] = mcm_msg('success', $dryrun ? 'Dry run finished - no changes were made.' : 'Course Merge Finished');
    return $messages;
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