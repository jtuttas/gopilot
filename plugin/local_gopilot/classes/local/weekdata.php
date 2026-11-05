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

namespace local_gopilot\local;

/**
 * Reads planning weeks and builds the "week", "courseweek" and "learnerweek" structures of docs/api-v1.md.
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class weekdata {
    /** @var int reference time */
    protected $now;

    /** @var steps */
    protected $steps;

    /** @var array userid => full name */
    protected $names = [];

    /**
     * Constructor.
     *
     * @param int|null $now
     * @param steps|null $steps
     */
    public function __construct(?int $now = null, ?steps $steps = null) {
        $this->now = $now ?? time();
        $this->steps = $steps ?? new steps($this->now);
    }

    /**
     * Step builder in use.
     *
     * @return steps
     */
    public function steps(): steps {
        return $this->steps;
    }

    /**
     * Week record of a user, or null.
     *
     * @param int $userid
     * @param string $week
     * @return \stdClass|null
     */
    public static function get_record(int $userid, string $week): ?\stdClass {
        global $DB;
        return $DB->get_record('local_gopilot_week', ['userid' => $userid, 'week' => $week]) ?: null;
    }

    /**
     * Week record of a user, created if missing.
     *
     * @param int $userid
     * @param string $week
     * @return \stdClass
     */
    public static function get_or_create(int $userid, string $week): \stdClass {
        global $DB;
        weeks::require_valid($week);
        $record = self::get_record($userid, $week);
        if (!$record) {
            $now = time();
            $record = (object)[
                'userid' => $userid,
                'week' => $week,
                'goal' => '',
                'generalreflection' => '',
                'reflectedat' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $record->id = $DB->insert_record('local_gopilot_week', $record);
        }
        return $record;
    }

    /**
     * Full name of a user (cached).
     *
     * @param int $userid
     * @return string
     */
    protected function fullname(int $userid): string {
        if (!isset($this->names[$userid])) {
            $user = \core_user::get_user($userid);
            $this->names[$userid] = $user ? fullname($user) : '';
        }
        return $this->names[$userid];
    }

    /**
     * Plan items of a week, optionally restricted to one course.
     *
     * @param int $weekid
     * @param int|null $courseid
     * @return \stdClass[]
     */
    protected function planitems(int $weekid, ?int $courseid = null): array {
        global $DB;
        $params = ['weekid' => $weekid];
        if ($courseid) {
            $params['courseid'] = $courseid;
        }
        return $DB->get_records('local_gopilot_planitem', $params, 'sortorder ASC, id ASC');
    }

    /**
     * Step structures of the planned items; closed weeks show the frozen completion state.
     *
     * @param \stdClass $weekrecord
     * @param int|null $courseid
     * @return array list of steps
     */
    protected function items(\stdClass $weekrecord, ?int $courseid = null): array {
        $closed = weeks::is_closed($weekrecord->week, $this->now);
        $end = weeks::end($weekrecord->week);
        $userid = (int)$weekrecord->userid;
        $result = [];
        foreach ($this->planitems((int)$weekrecord->id, $courseid) as $item) {
            $step = $this->safe_step((int)$item->courseid, (int)$item->cmid, $userid);
            if (!$step) {
                continue;
            }
            if ($closed) {
                if ($item->completedatclose !== null) {
                    $step['completed'] = (bool)$item->completedatclose;
                } else {
                    $step['completed'] = $this->steps->completed_by((int)$item->courseid, (int)$item->cmid, $userid, $end);
                }
            }
            $result[] = $step;
        }
        return $result;
    }

    /**
     * A step, or null if the course or module no longer exists or is not visible.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $userid
     * @return array|null
     */
    protected function safe_step(int $courseid, int $cmid, int $userid): ?array {
        try {
            return $this->steps->get($courseid, $cmid, $userid);
        } catch (\dml_missing_record_exception $e) {
            return null;
        } catch (\moodle_exception $e) {
            return null;
        }
    }

    /**
     * Course reflection text.
     *
     * @param int $weekid
     * @param int $courseid
     * @return string
     */
    protected function reflection(int $weekid, int $courseid): string {
        global $DB;
        return (string)$DB->get_field('local_gopilot_coursereflection', 'text', ['weekid' => $weekid, 'courseid' => $courseid]);
    }

    /**
     * Self-assessments: one entry per path with planned steps plus every stored entry.
     *
     * @param int $weekid
     * @param int $courseid
     * @param int $userid
     * @return array list of selfassessment structures
     */
    protected function selfassessments(int $weekid, int $courseid, int $userid): array {
        global $DB;
        $stored = $DB->get_records('local_gopilot_selfassessment', ['weekid' => $weekid, 'courseid' => $courseid]);
        $sectionids = [];
        foreach ($this->planitems($weekid, $courseid) as $item) {
            $step = $this->safe_step($courseid, (int)$item->cmid, $userid);
            if ($step) {
                $sectionids[$step['sectionid']] = true;
            }
        }
        $bysection = [];
        foreach ($stored as $record) {
            $bysection[(int)$record->sectionid] = $record;
            $sectionids[(int)$record->sectionid] = true;
        }
        $result = [];
        $modinfo = get_fast_modinfo($courseid);
        foreach ($modinfo->get_section_info_all() as $section) {
            if (!isset($sectionids[(int)$section->id])) {
                continue;
            }
            $record = $bysection[(int)$section->id] ?? null;
            $result[] = [
                'sectionid' => (int)$section->id,
                'name' => get_section_name($modinfo->get_course(), $section),
                'level' => $record ? (int)$record->selflevel : 0,
                'comment' => $record ? (string)$record->remark : '',
            ];
        }
        return $result;
    }

    /**
     * Feedback thread of a week and course, oldest first.
     *
     * @param int $weekid
     * @param int $courseid
     * @param int $learnerid
     * @return array list of feedbackentry structures
     */
    public function feedback(int $weekid, int $courseid, int $learnerid): array {
        global $DB;
        $result = [];
        $records = $DB->get_records(
            'local_gopilot_feedback',
            ['weekid' => $weekid, 'courseid' => $courseid],
            'timecreated ASC, id ASC'
        );
        foreach ($records as $record) {
            $result[] = $this->feedbackentry($record, $learnerid);
        }
        return $result;
    }

    /**
     * Feedback entry structure.
     *
     * @param \stdClass $record
     * @param int $learnerid
     * @return array
     */
    public function feedbackentry(\stdClass $record, int $learnerid): array {
        return [
            'id' => (int)$record->id,
            'authorid' => (int)$record->authorid,
            'authorname' => $this->fullname((int)$record->authorid),
            'isteacher' => (int)$record->authorid !== $learnerid,
            'message' => (string)$record->message,
            'timecreated' => (int)$record->timecreated,
            'timeread' => (int)($record->timeread ?? 0),
        ];
    }

    /**
     * Unread feedback addressed to a learner, newest first.
     *
     * Entries written by others (coaches) in the learner's own weeks, restricted
     * to courses in which the user is a learner.
     *
     * @param int $userid
     * @return array list of newfeedback structures
     */
    public function unread_feedback(int $userid): array {
        global $DB;
        $courses = access::learner_courses($userid);
        if (!$courses) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($courses), SQL_PARAMS_NAMED);
        $params['userid'] = $userid;
        $params['author'] = $userid;
        $sql = "SELECT f.*, w.week
                  FROM {local_gopilot_feedback} f
                  JOIN {local_gopilot_week} w ON w.id = f.weekid
                 WHERE w.userid = :userid AND f.authorid <> :author AND f.timeread = 0 AND f.courseid $insql
              ORDER BY f.timecreated DESC, f.id DESC";
        $result = [];
        foreach ($DB->get_records_sql($sql, $params) as $record) {
            $course = $courses[$record->courseid];
            $result[] = [
                'id' => (int)$record->id,
                'courseid' => (int)$record->courseid,
                'coursename' => format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]),
                'week' => (string)$record->week,
                'authorname' => $this->fullname((int)$record->authorid),
                'message' => (string)$record->message,
                'timecreated' => (int)$record->timecreated,
            ];
        }
        return $result;
    }

    /**
     * Mark feedback entries as read by the learner they are addressed to.
     *
     * Only unread entries in the user's own weeks written by someone else are
     * changed; other ids are ignored.
     *
     * @param int $userid
     * @param int[] $ids
     * @return int number of entries marked
     */
    public function mark_feedback_read(int $userid, array $ids): int {
        global $DB;
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) {
            return 0;
        }
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $params['userid'] = $userid;
        $params['author'] = $userid;
        $sql = "SELECT f.id
                  FROM {local_gopilot_feedback} f
                  JOIN {local_gopilot_week} w ON w.id = f.weekid
                 WHERE f.id $insql AND w.userid = :userid AND f.authorid <> :author AND f.timeread = 0";
        $own = $DB->get_fieldset_sql($sql, $params);
        if (!$own) {
            return 0;
        }
        [$insql, $params] = $DB->get_in_or_equal($own, SQL_PARAMS_NAMED);
        $params['now'] = $this->now;
        $DB->execute("UPDATE {local_gopilot_feedback} SET timeread = :now WHERE id $insql", $params);
        return count($own);
    }

    /**
     * Complete "week" structure for the learner.
     *
     * @param int $userid
     * @param string $week
     * @return array
     */
    public function week(int $userid, string $week): array {
        weeks::require_valid($week);
        $record = self::get_record($userid, $week);
        $result = [
            'week' => $week,
            'closed' => weeks::is_closed($week, $this->now),
            'goal' => $record ? (string)$record->goal : '',
            'generalreflection' => $record ? (string)$record->generalreflection : '',
            'reflectedat' => $record ? (int)$record->reflectedat : 0,
            'items' => $record ? $this->items($record) : [],
            'courses' => [],
        ];
        foreach (access::learner_courses($userid) as $course) {
            $result['courses'][] = [
                'courseid' => (int)$course->id,
                'fullname' => format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]),
                'reflection' => $record ? $this->reflection((int)$record->id, (int)$course->id) : '',
                'selfassessments' => $record ? $this->selfassessments((int)$record->id, (int)$course->id, $userid) : [],
                'feedback' => $record ? $this->feedback((int)$record->id, (int)$course->id, $userid) : [],
            ];
        }
        return $result;
    }

    /**
     * "learnerweek" structure: one week of a learner restricted to one course (coach view).
     *
     * @param \stdClass $record week record
     * @param int $courseid
     * @return array
     */
    public function learnerweek(\stdClass $record, int $courseid): array {
        $userid = (int)$record->userid;
        return [
            'week' => $record->week,
            'closed' => weeks::is_closed($record->week, $this->now),
            'goal' => (string)$record->goal,
            'generalreflection' => (string)$record->generalreflection,
            'reflectedat' => (int)$record->reflectedat,
            'items' => $this->items($record, $courseid),
            'reflection' => $this->reflection((int)$record->id, $courseid),
            'selfassessments' => $this->selfassessments((int)$record->id, $courseid, $userid),
            'feedback' => $this->feedback((int)$record->id, $courseid, $userid),
        ];
    }

    /**
     * Week records of a user that contain data, newest first.
     *
     * Data means a goal, a general reflection, or (restricted to $courseid if given) plan items,
     * a course reflection, a self-assessment or feedback.
     *
     * @param int $userid
     * @param int|null $courseid
     * @return \stdClass[]
     */
    public static function weeks_with_data(int $userid, ?int $courseid = null): array {
        global $DB;
        $records = $DB->get_records('local_gopilot_week', ['userid' => $userid], 'week DESC');
        if (!$records) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($records), SQL_PARAMS_NAMED);
        $coursesql = '';
        if ($courseid) {
            $coursesql = ' AND courseid = :courseid';
            $params['courseid'] = $courseid;
        }
        $withdata = [];
        $tables = ['local_gopilot_planitem', 'local_gopilot_coursereflection', 'local_gopilot_selfassessment',
            'local_gopilot_feedback'];
        foreach ($tables as $table) {
            $ids = $DB->get_fieldset_sql("SELECT DISTINCT weekid FROM {{$table}} WHERE weekid $insql $coursesql", $params);
            foreach ($ids as $id) {
                $withdata[(int)$id] = true;
            }
        }
        $result = [];
        foreach ($records as $record) {
            if (
                isset($withdata[(int)$record->id]) || trim((string)$record->goal) !== ''
                    || trim((string)$record->generalreflection) !== ''
            ) {
                $result[] = $record;
            }
        }
        return $result;
    }
}
