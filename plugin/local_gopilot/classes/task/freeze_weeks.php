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

use local_gopilot\local\steps;
use local_gopilot\local\weeks;

/**
 * Freezes the completion state of plan items (completedatclose) of closed weeks.
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class freeze_weeks extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_freeze_weeks', 'local_gopilot');
    }

    /**
     * Run the task.
     *
     * @return void
     */
    public function execute() {
        global $DB;
        $now = time();
        $steps = new steps($now);
        $sql = "SELECT p.id, p.cmid, p.courseid, w.userid, w.week
                  FROM {local_gopilot_planitem} p
                  JOIN {local_gopilot_week} w ON w.id = p.weekid
                 WHERE p.completedatclose IS NULL AND w.week < :current";
        $items = $DB->get_records_sql($sql, ['current' => weeks::current($now)]);
        foreach ($items as $item) {
            $done = $steps->completed_by((int)$item->courseid, (int)$item->cmid, (int)$item->userid, weeks::end($item->week));
            $DB->set_field('local_gopilot_planitem', 'completedatclose', $done ? 1 : 0, ['id' => $item->id]);
        }
        mtrace('GoPilot: froze ' . count($items) . ' plan items.');
    }
}
