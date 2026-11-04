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

use local_gopilot\ampel\calculator;
use local_gopilot\local\notifier;
use local_gopilot\local\steps;

/**
 * Recalculates all Ampels and notifies learners about changes to yellow or red.
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recalculate_ampel extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_recalculate_ampel', 'local_gopilot');
    }

    /**
     * Courses in which GoPilot is used (configuration, Ampel or plan items exist).
     *
     * @return int[]
     */
    protected function courses(): array {
        global $DB;
        $ids = [];
        foreach (['local_gopilot_courseconfig', 'local_gopilot_ampel', 'local_gopilot_planitem'] as $table) {
            foreach ($DB->get_fieldset_sql("SELECT DISTINCT courseid FROM {{$table}}") as $id) {
                $ids[(int)$id] = true;
            }
        }
        $result = [];
        foreach (array_keys($ids) as $id) {
            if ($id != SITEID && $DB->record_exists('course', ['id' => $id])) {
                $result[] = $id;
            }
        }
        return $result;
    }

    /**
     * Run the task.
     *
     * @return void
     */
    public function execute() {
        $now = time();
        $calculator = new calculator($now, new steps($now));
        $count = 0;
        foreach ($this->courses() as $courseid) {
            $context = \context_course::instance($courseid);
            $course = get_course($courseid);
            $users = get_enrolled_users($context, 'local/gopilot:use', 0, 'u.id', null, 0, 0, true);
            foreach ($users as $user) {
                $calculator->compute_and_store($user->id, $courseid);
                $count++;
                $ampel = calculator::take_pending_notification($user->id, $courseid);
                if ($ampel) {
                    self::notify((int)$user->id, $course, $ampel);
                }
            }
        }
        mtrace('GoPilot: recalculated ' . $count . ' Ampels.');
    }

    /**
     * Notify a learner about an Ampel that changed to yellow or red.
     *
     * @param int $userid
     * @param \stdClass $course
     * @param array $ampel
     * @return void
     */
    public static function notify(int $userid, \stdClass $course, array $ampel): void {
        $reasons = [];
        foreach ($ampel['signals'] as $signal) {
            if (!empty($signal['active']) && in_array($signal['colour'], ['yellow', 'red'])) {
                $reasons[] = $signal['reason'];
            }
        }
        $a = (object)[
            'course' => format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]),
            'colour' => get_string('colour_' . $ampel['colour'], 'local_gopilot'),
            'reasons' => implode('; ', $reasons),
        ];
        notifier::send(
            'ampelchange',
            $userid,
            null,
            get_string('notify_ampelchange_subject', 'local_gopilot', $a),
            get_string('notify_ampelchange_body', 'local_gopilot', $a),
            '/',
            (int)$course->id
        );
    }
}
