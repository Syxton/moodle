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
$groupid    = optional_param('group', 0, PARAM_INT);
$groupingid = optional_param('grouping', 0, PARAM_INT);
 
if (!$course = $DB->get_record('course', array('id'=>$courseid))) {
    print_error('invalidcourse');
}
 
$url = new moodle_url('/group/exportoverview.php', array('id' => $courseid, 'groupid' => $groupid, 'groupingid' => $groupingid));
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
 
$csv = array();
$csvgroupsheader = array(0 => "Not in Group");
$csvgroups = array(0 => "x");
$csvgroupingsheader = array(0 => "Not in Grouping");
$csvgroupings = array(0 => "x");
 
// Get all groupings and sort them by formatted name.
$groupings = $DB->get_records('groupings', array('courseid'=>$courseid), 'name');
foreach ($groupings as $gid => $grouping) {
    $groupings[$gid]->formattedname = format_string($grouping->name, true, array('context' => $context));
    $csvgroupingsheader[$grouping->id] = $grouping->name;
    $csvgroupings[$grouping->id] = ' ';
}
 
// Get all groups
$groups = $DB->get_records('groups', array('courseid'=>$courseid), 'name');
foreach ($groups as $group) {
    $csvgroupsheader[$group->id] = strip_tags(format_string($group->name));
    $csvgroups[$group->id] = ' ';
}
 
$params = array('courseid'=>$courseid);
if ($groupid) {
    $groupwhere = "AND g.id = :groupid";
    $params['groupid']   = $groupid;
} else {
    $groupwhere = "";
}
 
if ($groupingid) {
    $groupingwhere = "AND gg.groupingid = :groupingid";
    $params['groupingid'] = $groupingid;
} else {
    $groupingwhere = "";
}
 
list($sort, $sortparams) = users_order_by_sql('u');
 
$allnames = get_all_user_name_fields(true, 'u');
$sql = "SELECT g.id AS groupid, gg.groupingid, u.id AS userid, $allnames, u.idnumber, u.username, u.email
          FROM {groups} g
               LEFT JOIN {groupings_groups} gg ON g.id = gg.groupid
               LEFT JOIN {groups_members} gm ON g.id = gm.groupid
               LEFT JOIN {user} u ON gm.userid = u.id
         WHERE g.courseid = :courseid $groupwhere $groupingwhere
      ORDER BY g.name, $sort";
 
$rs = $DB->get_recordset_sql($sql, array_merge($params, $sortparams));
foreach ($rs as $row) {
    $user = new stdClass();
    $user = username_load_fields_from_object($user, $row, null, array('id' => 'userid', 'username', 'idnumber', 'email'));
    
    if (empty($user->email)) {
        continue;
    }
    
    if (empty($csv[$user->id])) {
        $csv[$user->id]['user'] = array('email' => $user->email, 'fullname' => fullname($user, true));
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
$rs->close();

// Add users who are not in a group.
if ($groupid <= 0 && $groupingid <= 0) {
    list($esql, $params) = get_enrolled_sql($context, null, 0, true);
    $sql = "SELECT u.id AS userid, $allnames, u.idnumber, u.username, u.email
              FROM {user} u
              JOIN ($esql) e ON e.id = u.id
         LEFT JOIN (
                  SELECT gm.userid
                    FROM {groups_members} gm
                    JOIN {groups} g ON g.id = gm.groupid
                   WHERE g.courseid = :courseid
                   ) grouped ON grouped.userid = u.id
             WHERE grouped.userid IS NULL";
    $params['courseid'] = $courseid;

    $rs = $DB->get_recordset_sql($sql, $params);
    foreach ($rs as $row) {
        $user = new stdClass();
        $user = username_load_fields_from_object($user, $row, null, array('id' => 'userid', 'username', 'idnumber', 'email'));

        if (empty($user->email)) {
            continue;
        }
        
        if (empty($csv[$user->id])) {
            $csv[$user->id]['user'] = array('email' => $user->email, 'fullname' => fullname($user, true));
            $csv[$user->id]['groupings'] = $csvgroupings;
            $csv[$user->id]['groups'] = $csvgroups;
        }
    }
    $rs->close();
}

$downloadfilename = clean_filename("overviewexport");
$csvexport = new csv_export_writer();
$csvexport->set_filename($downloadfilename);
 
$csvexport->add_data(explode(',', implode(',', array('Fullname', 'Email')) . ',' . implode(',', $csvgroupingsheader) . ',' . implode(',', $csvgroupsheader)));
foreach ($csv as $row) {
    $temp = implode(',', array($row['user']['fullname'], $row['user']['email'])) .
            ',' . 
            implode(',', $row['groupings']) .
            ',' . 
            implode(',', $row['groups']);
    $csvexport->add_data(explode(',', $temp));
}
 
$csvexport->download_file();