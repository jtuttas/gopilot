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

use local_gopilot\local\courseconfig;
use local_gopilot\local\notifier;
use local_gopilot\local\weeks;

/**
 * Reminds learners on Friday afternoon to reflect in school weeks.
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
        foreach ($DB->get_records('local_gopilot_courseconfig') as $config) {
            $courseid = (int)$config->courseid;
            if (
                !$DB->record_exists('course', ['id' => $courseid])
                    || !(new courseconfig($courseid))->is_schoolweek($week)
            ) {
                continue;
            }
            $context = \context_course::instance($courseid);
            $course = get_course($courseid);
            $users = get_enrolled_users($context, 'local/gopilot:use', 0, 'u.id', null, 0, 0, true);
            foreach ($users as $user) {
                $sql = "SELECT cr.text
                          FROM {local_gopilot_coursereflection} cr
                          JOIN {local_gopilot_week} w ON w.id = cr.weekid
                         WHERE w.userid = :userid AND w.week = :week AND cr.courseid = :courseid";
                $text = $DB->get_field_sql($sql, ['userid' => $user->id, 'week' => $week, 'courseid' => $courseid]);
                if (trim((string)$text) === '') {
                    $pending[(int)$user->id][] = format_string($course->fullname, true, ['context' => $context]);
                }
            }
        }
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
