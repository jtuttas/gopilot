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

namespace local_gopilot\ampel;

use local_gopilot\local\courseconfig;
use local_gopilot\local\steps;
use local_gopilot\local\weeks;

/**
 * Computes the support traffic light (Förder-Ampel) of a learner in a course (ADR 0003, docs/api-v1.md).
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class calculator {
    /** @var string[] Signal keys in output order. */
    const SIGNALS = ['plan', 'backlog', 'reflection', 'selfassessment'];

    /** @var string[] Colours from best to worst. */
    const COLOURS = ['none', 'green', 'yellow', 'red'];

    /** @var int reference time */
    protected $now;

    /** @var steps */
    protected $steps;

    /**
     * Constructor.
     *
     * @param int|null $now reference time, default time()
     * @param steps|null $steps shared step builder (must use the same reference time)
     */
    public function __construct(?int $now = null, ?steps $steps = null) {
        $this->now = $now ?? time();
        $this->steps = $steps ?? new steps($this->now);
    }

    /**
     * Worst of the given colours ("none" if empty).
     *
     * @param string[] $colours
     * @return string
     */
    public static function worst(array $colours): string {
        $rank = 0;
        foreach ($colours as $colour) {
            $rank = max($rank, (int)array_search($colour, self::COLOURS, true));
        }
        return self::COLOURS[$rank];
    }

    /**
     * An inactive signal.
     *
     * @param string $key
     * @param string $reason
     * @return array
     */
    protected static function inactive(string $key, string $reason): array {
        return ['key' => $key, 'active' => false, 'colour' => 'none', 'value' => 0, 'reason' => $reason];
    }

    /**
     * An active signal.
     *
     * @param string $key
     * @param string $colour
     * @param int $value
     * @param string $reason
     * @return array
     */
    protected static function active(string $key, string $colour, int $value, string $reason): array {
        return ['key' => $key, 'active' => true, 'colour' => $colour, 'value' => $value, 'reason' => $reason];
    }

    /**
     * Ampel structure without any active signal (used where the person is no learner).
     *
     * @param int $courseid
     * @return array
     */
    public static function empty_result(int $courseid): array {
        $signals = [];
        foreach (self::SIGNALS as $key) {
            $signals[] = self::inactive($key, '');
        }
        return ['courseid' => $courseid, 'colour' => 'none', 'timecomputed' => 0, 'signals' => $signals];
    }

    /**
     * Compute the Ampel without storing it.
     *
     * @param int $userid
     * @param int $courseid
     * @return array ampel structure (courseid, colour, timecomputed, signals)
     */
    public function compute(int $userid, int $courseid): array {
        $config = new courseconfig($courseid);
        $thresholds = $config->get_thresholds();
        $signals = [
            $this->signal_plan($userid, $courseid, $config, $thresholds),
            $this->signal_backlog($userid, $courseid, $thresholds),
            $this->signal_reflection($userid, $courseid, $config, $thresholds),
            $this->signal_selfassessment($userid, $courseid),
        ];
        $colours = [];
        foreach ($signals as $signal) {
            if ($signal['active']) {
                $colours[] = $signal['colour'];
            }
        }
        return [
            'courseid' => $courseid,
            'colour' => self::worst($colours),
            'timecomputed' => $this->now,
            'signals' => $signals,
        ];
    }

    /**
     * Compute the Ampel and store it in local_gopilot_ampel.
     *
     * @param int $userid
     * @param int $courseid
     * @return array ampel structure
     */
    public function compute_and_store(int $userid, int $courseid): array {
        global $DB;
        $result = $this->compute($userid, $courseid);
        $record = $DB->get_record('local_gopilot_ampel', ['userid' => $userid, 'courseid' => $courseid]);
        $data = (object)[
            'userid' => $userid,
            'courseid' => $courseid,
            'colour' => $result['colour'],
            'signals' => json_encode($result['signals']),
            'timecomputed' => $result['timecomputed'],
        ];
        if ($record) {
            $data->id = $record->id;
            $DB->update_record('local_gopilot_ampel', $data);
        } else {
            $data->notifiedcolour = 'none';
            $DB->insert_record('local_gopilot_ampel', $data);
        }
        return $result;
    }

    /**
     * Decide whether the learner must be told about a change to yellow or red, and remember the decision.
     *
     * @param int $userid
     * @param int $courseid
     * @return array|null the stored ampel structure if a notification is due, else null
     */
    public static function take_pending_notification(int $userid, int $courseid): ?array {
        global $DB;
        $record = $DB->get_record('local_gopilot_ampel', ['userid' => $userid, 'courseid' => $courseid]);
        if (!$record || $record->colour === $record->notifiedcolour) {
            return null;
        }
        $DB->set_field('local_gopilot_ampel', 'notifiedcolour', $record->colour, ['id' => $record->id]);
        if ($record->colour !== 'yellow' && $record->colour !== 'red') {
            return null;
        }
        return [
            'courseid' => (int)$record->courseid,
            'colour' => $record->colour,
            'timecomputed' => (int)$record->timecomputed,
            'signals' => json_decode($record->signals, true) ?: [],
        ];
    }

    /**
     * Signal 1: plan fulfilment in the most recently closed week.
     *
     * @param int $userid
     * @param int $courseid
     * @param courseconfig $config
     * @param array $thresholds
     * @return array
     */
    protected function signal_plan(int $userid, int $courseid, courseconfig $config, array $thresholds): array {
        global $DB;
        $week = weeks::last_closed($this->now);
        if (!$config->is_schoolweek($week)) {
            return self::inactive('plan', get_string('signal_plan_noschoolweek', 'local_gopilot'));
        }
        $sql = "SELECT p.id, p.cmid, p.completedatclose
                  FROM {local_gopilot_planitem} p
                  JOIN {local_gopilot_week} w ON w.id = p.weekid
                 WHERE w.userid = :userid AND w.week = :week AND p.courseid = :courseid";
        $items = $DB->get_records_sql($sql, ['userid' => $userid, 'week' => $week, 'courseid' => $courseid]);
        if (!$items) {
            return self::inactive('plan', get_string('signal_plan_nothingplanned', 'local_gopilot'));
        }
        $end = weeks::end($week);
        $done = 0;
        foreach ($items as $item) {
            if ($item->completedatclose !== null) {
                $done += (int)$item->completedatclose ? 1 : 0;
            } else {
                $done += $this->steps->completed_by($courseid, (int)$item->cmid, $userid, $end) ? 1 : 0;
            }
        }
        $total = count($items);
        $percent = (int)round(100 * $done / $total);
        if ($percent >= $thresholds['plangreen']) {
            $colour = 'green';
        } else if ($percent >= $thresholds['planyellow']) {
            $colour = 'yellow';
        } else {
            $colour = 'red';
        }
        $reason = get_string(
            'signal_plan_reason',
            'local_gopilot',
            ['done' => $done, 'total' => $total, 'percent' => $percent]
        );
        return self::active('plan', $colour, $percent, $reason);
    }

    /**
     * Signal 2: overdue learning steps.
     *
     * @param int $userid
     * @param int $courseid
     * @param array $thresholds
     * @return array
     */
    protected function signal_backlog(int $userid, int $courseid, array $thresholds): array {
        $course = get_course($courseid);
        if (!(new \completion_info($course))->is_enabled()) {
            return self::inactive('backlog', get_string('signal_backlog_nocompletion', 'local_gopilot'));
        }
        $overdue = 0;
        foreach ($this->steps->for_course($courseid, $userid) as $step) {
            $overdue += $step['overdue'] ? 1 : 0;
        }
        if ($overdue >= $thresholds['backlogred']) {
            $colour = 'red';
        } else if ($overdue >= $thresholds['backlogyellow']) {
            $colour = 'yellow';
        } else {
            $colour = 'green';
        }
        if ($overdue == 0) {
            $reason = get_string('signal_backlog_none', 'local_gopilot');
        } else if ($overdue == 1) {
            $reason = get_string('signal_backlog_one', 'local_gopilot');
        } else {
            $reason = get_string('signal_backlog_reason', 'local_gopilot', $overdue);
        }
        return self::active('backlog', $colour, $overdue, $reason);
    }

    /**
     * Signal 3: consecutive closed school weeks without course reflection.
     *
     * @param int $userid
     * @param int $courseid
     * @param courseconfig $config
     * @param array $thresholds
     * @return array
     */
    protected function signal_reflection(int $userid, int $courseid, courseconfig $config, array $thresholds): array {
        global $DB;
        $teachingweeks = $config->get_teachingweeks();
        if (!$teachingweeks) {
            return self::inactive('reflection', get_string('signal_reflection_noteachingweeks', 'local_gopilot'));
        }
        $closed = array_values(array_filter($teachingweeks, function ($week) {
            return weeks::is_closed($week, $this->now);
        }));
        if (!$closed) {
            return self::inactive('reflection', get_string('signal_reflection_noclosedweek', 'local_gopilot'));
        }
        $sql = "SELECT w.week, cr.text
                  FROM {local_gopilot_coursereflection} cr
                  JOIN {local_gopilot_week} w ON w.id = cr.weekid
                 WHERE w.userid = :userid AND cr.courseid = :courseid";
        $reflected = [];
        foreach ($DB->get_records_sql($sql, ['userid' => $userid, 'courseid' => $courseid]) as $row) {
            if (trim((string)$row->text) !== '') {
                $reflected[$row->week] = true;
            }
        }
        $missing = 0;
        foreach (array_reverse($closed) as $week) {
            if (isset($reflected[$week])) {
                break;
            }
            $missing++;
        }
        if ($missing >= $thresholds['reflectionred']) {
            $colour = 'red';
        } else if ($missing >= $thresholds['reflectionyellow']) {
            $colour = 'yellow';
        } else {
            $colour = 'green';
        }
        if ($missing == 0) {
            $reason = get_string('signal_reflection_present', 'local_gopilot');
        } else if ($missing == 1) {
            $reason = get_string('signal_reflection_one', 'local_gopilot');
        } else {
            $reason = get_string('signal_reflection_reason', 'local_gopilot', $missing);
        }
        return self::active('reflection', $colour, $missing, $reason);
    }

    /**
     * Signal 4: worst self-assessment of the newest week with a self-assessment in this course.
     *
     * @param int $userid
     * @param int $courseid
     * @return array
     */
    protected function signal_selfassessment(int $userid, int $courseid): array {
        global $DB;
        $sql = "SELECT sa.id, sa.sectionid, sa.selflevel, w.week
                  FROM {local_gopilot_selfassessment} sa
                  JOIN {local_gopilot_week} w ON w.id = sa.weekid
                 WHERE w.userid = :userid AND sa.courseid = :courseid AND sa.selflevel > 0
              ORDER BY w.week DESC, sa.selflevel DESC, sa.id ASC";
        $rows = $DB->get_records_sql($sql, ['userid' => $userid, 'courseid' => $courseid], 0, 1);
        if (!$rows) {
            return self::inactive('selfassessment', get_string('signal_selfassessment_none', 'local_gopilot'));
        }
        $row = reset($rows);
        $level = (int)$row->selflevel;
        if ($level >= 4) {
            $colour = 'red';
        } else if ($level == 3) {
            $colour = 'yellow';
        } else {
            $colour = 'green';
        }
        $reason = get_string('signal_selfassessment_reason', 'local_gopilot', [
            'level' => get_string('selflevel' . min(4, $level), 'local_gopilot'),
            'path' => steps::section_name($courseid, (int)$row->sectionid),
            'kw' => weeks::number($row->week),
        ]);
        return self::active('selfassessment', $colour, $level, $reason);
    }
}
