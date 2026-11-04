<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_gopilot\task;

use local_gopilot\local\access;
use local_gopilot\local\notifier;
use local_gopilot\local\weeks;

/**
 * Reminds learners on Friday afternoon to reflect on the courses they planned this week.
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reflection_due extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_reflection_due', 'local_gopilot');
    }

    /**
     * Run the task.
     *
     * @return void
     */
    public function execute() {
        global $DB;
        $week = weeks::current();
        $pending = [];
        // Learners who planned steps of a course this week and have not reflected on it yet.
        $sql = "SELECT DISTINCT w.id AS weekid, w.userid, p.courseid
                  FROM {local_gopilot_planitem} p
                  JOIN {local_gopilot_week} w ON w.id = p.weekid
                  JOIN {course} c ON c.id = p.courseid
                 WHERE w.week = :week
              ORDER BY w.userid, p.courseid";
        $rows = $DB->get_recordset_sql($sql, ['week' => $week]);
        foreach ($rows as $row) {
            $userid = (int)$row->userid;
            $courseid = (int)$row->courseid;
            $text = $DB->get_field('local_gopilot_coursereflection', 'text', ['weekid' => $row->weekid, 'courseid' => $courseid]);
            if (trim((string)$text) !== '' || !access::is_learner($courseid, $userid)) {
                continue;
            }
            $context = \context_course::instance($courseid);
            $pending[$userid][] = format_string(get_course($courseid)->fullname, true, ['context' => $context]);
        }
        $rows->close();
        foreach ($pending as $userid => $courses) {
            $a = (object)['courses' => implode(', ', $courses), 'kw' => weeks::number($week)];
            notifier::send(
                'reflectiondue',
                $userid,
                null,
                get_string('notify_reflectiondue_subject', 'local_gopilot', $a),
                get_string('notify_reflectiondue_body', 'local_gopilot', $a),
                '/woche/' . $week
            );
        }
        mtrace('GoPilot: sent ' . count($pending) . ' reflection reminders.');
    }
}
