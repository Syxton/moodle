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
 * Print an overview of groupings & group membership
 *
 * @copyright  Matt Clarkson mattc@catalyst.net.nz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package    core_group
 */

require_once('../config.php');
require_once($CFG->libdir . '/csvlib.class.php');

$courseid   = required_param('id', PARAM_INT);
$type       = optional_param('type', "checkboxes", PARAM_TEXT);
$groupid    = optional_param('groupid', 0, PARAM_INT);
$groupingid = optional_param('groupingid', 0, PARAM_INT);

if (!$course = $DB->get_record('course', ['id' => $courseid])) {
    print_error('invalidcourse');
}

$url = new moodle_url('/group/exportoverview.php', ['id' => $courseid, 'groupid' => $groupid, 'groupingid' => $groupingid]);
if ($groupid !== 0) {
    $url->param('group', $groupid);
}
if ($groupingid !== 0) {
    $url->param('grouping', $groupingid);
}
$PAGE->set_url($url);

// Make sure that the user has permissions to manage groups.
require_login($course);

$context = context_course::instance($courseid);
require_capability('moodle/course:managegroups', $context);

$csv = [];

if ($type === "checkboxes") {
    $csvgroupsheader = [0 => "Not in Group"];
    $csvgroups = [0 => "x"];
    $csvgroupingsheader = [0 => "Not in Grouping"];
    $csvgroupings = [0 => "x"];

    // Get all groupings and sort them by formatted name.
    $groupings = $DB->get_records('groupings', ['courseid'=>$courseid], 'name');
    foreach ($groupings as $gid => $grouping) {
        $groupings[$gid]->formattedname = format_string($grouping->name, true, ['context' => $context]);
        $csvgroupingsheader[$grouping->id] = $grouping->name;
        $csvgroupings[$grouping->id] = ' ';
    }

    // Get all groups
    $groups = $DB->get_records('groups', ['courseid' => $courseid], 'name');
    foreach ($groups as $group) {
        $csvgroupsheader[$group->id] = strip_tags(format_string($group->name));
        $csvgroups[$group->id] = ' ';
    }

    $params = ['courseid' => $courseid];

    $extrasql = "";
    if ($groupid) {
        $extrasql .= " AND g.id = :groupid";
        $params['groupid']   = $groupid;
    }

    if ($groupingid) {
        $extrasql .= " AND gg.groupingid = :groupingid";
        $params['groupingid'] = $groupingid;
    }

    $users = rose_get_group_filtered_users($context, $params, $extrasql);

    foreach ($users as $row) {
        $user = username_load_fields_from_object((object)[], $row, null, ['id' => 'userid', 'username', 'idnumber', 'email']);

        if (empty($user->email)) {
            continue;
        }

        if (empty($csv[$user->id])) {
            $csv[$user->id]['user'] = ['email' => $user->email, 'fullname' => fullname($user, true)];
            $csv[$user->id]['groupings'] = $csvgroupings;
            $csv[$user->id]['groups'] = $csvgroups;
        }

        if (!empty($row->groupingid) && !empty($csv[$user->id]['groupings'][$row->groupingid])) {
            $csv[$user->id]['groupings'][$row->groupingid] = "x";
            $csv[$user->id]['groupings'][0] = "";
        }

        if (!empty($row->groupid) && !empty($csv[$user->id]['groups'][$row->groupid])) {
            $csv[$user->id]['groups'][$row->groupid] = "x";
            $csv[$user->id]['groups'][0] = "";
        }
    }
    $users->close();

    // Add users who are not in a group.
    if ($groupid <= 0 && $groupingid <= 0) {
        $params['courseid'] = $courseid;
        $users = rose_get_non_grouped_users($context, $params);

        foreach ($users as $row) {
            $user = username_load_fields_from_object((object)[], $row, null, ['id' => 'userid', 'username', 'idnumber', 'email']);

            if (empty($user->email)) {
                continue;
            }

            if (empty($csv[$user->id])) {
                $csv[$user->id]['user'] = ['email' => $user->email, 'fullname' => fullname($user, true)];
                $csv[$user->id]['groupings'] = $csvgroupings;
                $csv[$user->id]['groups'] = $csvgroups;
            }
        }
        $users->close();
    }

    $downloadfilename = clean_filename("overviewexport");
    $csvexport = new csv_export_writer();
    $csvexport->set_filename($downloadfilename);

    $csvheader = array_merge(['Fullname', 'Email'], $csvgroupingsheader, $csvgroupsheader);
    $csvexport->add_data($csvheader);
    foreach ($csv as $row) {
        $temp = [$row['user']['fullname'], $row['user']['email']];
        foreach ($row['groupings'] as $g) {
            $temp[] = $g;
        }
        foreach ($row['groups'] as $g) {
            $temp[] = $g;
        }
        $csvexport->add_data($temp);
    }
} elseif ($type === "banner") {
    $params = ['courseid' => $courseid];

    $extrasql = "";
    if ($groupid) {
        $extrasql .= " AND g.id = :groupid";
        $params['groupid']   = $groupid;
    }

    if ($groupingid) {
        $extrasql .= " AND gg.groupingid = :groupingid";
        $params['groupingid'] = $groupingid;
    }

    $users = rose_get_group_filtered_users($context, $params, $extrasql);
    foreach ($users as $row) {
        $user = username_load_fields_from_object((object)[], $row, null, ['id' => 'userid', 'username', 'idnumber', 'email']);

        if (empty($csv[$user->id])) {
            $idnumber = empty($user->idnumber) ? "NA" : $user->idnumber;
            $csv[$user->id]['user'] = ['fullname' => fullname($user, true), 'idnumber' => $idnumber];
        }
    }
    $users->close();

    // Add users who are not in a group.
    if (!$groupid && !$groupingid) {
        $params['courseid'] = $courseid;
        $users = rose_get_non_grouped_users($context, $params);
        foreach ($users as $row) {
            $user = username_load_fields_from_object((object)[], $row, null, ['id' => 'userid', 'username', 'idnumber', 'email']);

            if (empty($csv[$user->id])) {
                $idnumber = empty($user->idnumber) ? "NA" : $user->idnumber;
                $csv[$user->id]['user'] = ['fullname' => fullname($user, true), 'idnumber' => $idnumber];
            }
        }
        $users->close();
    }

    $downloadfilename = clean_filename("bannerid_export");
    $csvexport = new csv_export_writer();
    $csvexport->set_filename($downloadfilename);
    $csvexport->add_data(['Fullname', 'BannerID']);

    foreach ($csv as $row) {
        $csvexport->add_data([$row['user']['fullname'], $row['user']['idnumber']]);
    }
}

$csvexport->download_file();

function rose_get_non_grouped_users($context, $params) {
    global $DB;
    $userfields = rose_get_all_user_fields();
    list($esql, $userparams) = get_enrolled_sql($context, null, 0, true);
        $sql = "SELECT u.id AS userid, $userfields u.idnumber, u.username, u.email
                FROM {user} u
                JOIN ($esql) e ON e.id = u.id
            LEFT JOIN (
                    SELECT gm.userid
                        FROM {groups_members} gm
                        JOIN {groups} g ON g.id = gm.groupid
                    WHERE g.courseid = :courseid
                    ) grouped ON grouped.userid = u.id
                WHERE grouped.userid IS NULL";
    return $DB->get_recordset_sql($sql, array_merge($params, $userparams));
}

function rose_get_group_filtered_users($context, $params, $extrasql) {
    global $DB;
    $userfields = rose_get_all_user_fields();
    list($sort, $sortparams) = users_order_by_sql('u');
    $sql = "SELECT g.id AS groupid, gg.groupingid, u.id AS userid, $userfields u.idnumber, u.username, u.email
            FROM {groups} g
                LEFT JOIN {groupings_groups} gg ON g.id = gg.groupid
                LEFT JOIN {groups_members} gm ON g.id = gm.groupid
                LEFT JOIN {user} u ON gm.userid = u.id
            WHERE g.courseid = :courseid
            $extrasql
        ORDER BY g.name, $sort";

    return $DB->get_recordset_sql($sql, array_merge($params, $sortparams));
}

function rose_get_all_user_fields() {
    $userfields = "";
    $allnames = \core_user\fields::get_name_fields();
    foreach ($allnames as $userfield) {
        $userfields .= "u.$userfield, ";
    }
    return $userfields;
}