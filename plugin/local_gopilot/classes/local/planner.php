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

use context_course;
use context_user;
use invalid_parameter_exception;
use local_gopilot\ampel\calculator;
use moodle_exception;

/**
 * Write operations: weekly plan, reflection and feedback.
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class planner {
    /** @var int reference time */
    protected $now;

    /**
     * Constructor.
     *
     * @param int|null $now
     */
    public function __construct(?int $now = null) {
        $this->now = $now ?? time();
    }

    /**
     * Replace goal and plan items of a week.
     *
     * @param int $userid
     * @param string $week
     * @param string $goal
     * @param int[] $cmids
     * @return void
     * @throws moodle_exception invalidweek, weekclosed, notalearner
     */
    public function save_plan(int $userid, string $week, string $goal, array $cmids): void {
        global $DB;
        weeks::require_valid($week);
        if (weeks::is_closed($week, $this->now)) {
            throw new moodle_exception('weekclosed', 'local_gopilot');
        }
        $steps = new steps($this->now);
        $courses = access::learner_courses($userid);
        $items = [];
        foreach (array_unique(array_map('intval', $cmids)) as $cmid) {
            $cm = $DB->get_record('course_modules', ['id' => $cmid], 'id, course');
            if (!$cm) {
                throw new invalid_parameter_exception('Unknown cmid ' . $cmid);
            }
            if (!isset($courses[$cm->course])) {
                throw new moodle_exception('notalearner', 'local_gopilot');
            }
            if (!$steps->get((int)$cm->course, $cmid, $userid)) {
                throw new invalid_parameter_exception('Course module ' . $cmid . ' is not a visible learning step');
            }
            $items[] = $cm;
        }

        $transaction = $DB->start_delegated_transaction();
        $record = weekdata::get_or_create($userid, $week);
        $record->goal = $goal;
        $record->timemodified = $this->now;
        $DB->update_record('local_gopilot_week', $record);
        $DB->delete_records('local_gopilot_planitem', ['weekid' => $record->id]);
        foreach ($items as $sortorder => $cm) {
            $DB->insert_record('local_gopilot_planitem', (object)[
                'weekid' => $record->id,
                'courseid' => $cm->course,
                'cmid' => $cm->id,
                'sortorder' => $sortorder,
                'completedatclose' => null,
                'timecreated' => $this->now,
            ]);
        }
        $transaction->allow_commit();

        \local_gopilot\event\plan_saved::create([
            'objectid' => $record->id,
            'context' => context_user::instance($userid),
            'relateduserid' => $userid,
            'other' => ['week' => $week],
        ])->trigger();

        $this->recalculate($userid, array_keys($courses));
    }

    /**
     * Save the reflection of a week.
     *
     * @param int $userid
     * @param string $week
     * @param string $generalreflection
     * @param array $courses list of ['courseid', 'reflection', 'selfassessments' => [['sectionid', 'level', 'comment']]]
     * @return void
     * @throws moodle_exception invalidweek, reflectionclosed, notalearner
     */
    public function save_reflection(int $userid, string $week, string $generalreflection, array $courses): void {
        global $DB;
        weeks::require_valid($week);
        $current = weeks::current($this->now);
        if ($week !== $current && $week !== weeks::add($current, -1)) {
            throw new moodle_exception('reflectionclosed', 'local_gopilot');
        }
        foreach ($courses as $course) {
            access::require_learner((int)$course['courseid'], $userid);
            foreach ($course['selfassessments'] ?? [] as $sa) {
                $level = (int)$sa['level'];
                if ($level < 0 || $level > 4) {
                    throw new invalid_parameter_exception('Invalid level ' . $level);
                }
                if (!$DB->record_exists('course_sections', ['id' => $sa['sectionid'], 'course' => $course['courseid']])) {
                    throw new invalid_parameter_exception('Section ' . $sa['sectionid'] . ' is not part of the course');
                }
            }
        }

        $transaction = $DB->start_delegated_transaction();
        $record = weekdata::get_or_create($userid, $week);
        $record->generalreflection = $generalreflection;
        $record->reflectedat = $this->now;
        $record->timemodified = $this->now;
        $DB->update_record('local_gopilot_week', $record);
        $saved = [];
        foreach ($courses as $course) {
            $courseid = (int)$course['courseid'];
            $existing = $DB->get_record('local_gopilot_coursereflection', ['weekid' => $record->id, 'courseid' => $courseid]);
            $text = (string)$course['reflection'];
            if ($existing) {
                $changed = trim((string)$existing->text) !== trim($text);
                $existing->text = $text;
                $existing->timemodified = $this->now;
                $DB->update_record('local_gopilot_coursereflection', $existing);
                $reflectionid = $existing->id;
            } else {
                $changed = trim($text) !== '';
                $reflectionid = $DB->insert_record('local_gopilot_coursereflection', (object)[
                    'weekid' => $record->id,
                    'courseid' => $courseid,
                    'text' => $text,
                    'timecreated' => $this->now,
                    'timemodified' => $this->now,
                ]);
            }
            foreach ($course['selfassessments'] ?? [] as $sa) {
                $sarecord = $DB->get_record(
                    'local_gopilot_selfassessment',
                    ['weekid' => $record->id, 'sectionid' => $sa['sectionid']]
                );
                $data = (object)[
                    'weekid' => $record->id,
                    'courseid' => $courseid,
                    'sectionid' => (int)$sa['sectionid'],
                    'selflevel' => (int)$sa['level'],
                    'remark' => (string)($sa['comment'] ?? ''),
                    'timemodified' => $this->now,
                ];
                if ($sarecord) {
                    $data->id = $sarecord->id;
                    $DB->update_record('local_gopilot_selfassessment', $data);
                } else {
                    $DB->insert_record('local_gopilot_selfassessment', $data);
                }
            }
            $saved[] = ['courseid' => $courseid, 'id' => $reflectionid, 'notify' => $changed && trim($text) !== ''];
        }
        $transaction->allow_commit();

        $learner = \core_user::get_user($userid);
        foreach ($saved as $item) {
            $context = context_course::instance($item['courseid']);
            \local_gopilot\event\reflection_saved::create([
                'objectid' => $item['id'],
                'context' => $context,
                'relateduserid' => $userid,
                'other' => ['week' => $week],
            ])->trigger();
            if (!$item['notify']) {
                continue;
            }
            $course = get_course($item['courseid']);
            $a = (object)[
                'learner' => fullname($learner),
                'course' => format_string($course->fullname, true, ['context' => $context]),
                'kw' => weeks::number($week),
            ];
            foreach (self::coaches($item['courseid'], $userid) as $coachid) {
                notifier::send(
                    'reflection',
                    $coachid,
                    $learner,
                    get_string('notify_reflection_subject', 'local_gopilot', $a),
                    get_string('notify_reflection_body', 'local_gopilot', $a),
                    '/lerndoku/' . $item['courseid'] . '/' . $userid,
                    $item['courseid']
                );
            }
        }

        $this->recalculate($userid, array_keys(access::learner_courses($userid)));
    }

    /**
     * Add an entry to the feedback thread of a week and course.
     *
     * @param int $authorid
     * @param int $courseid
     * @param int $learnerid
     * @param string $week
     * @param string $message
     * @return \stdClass the feedback record
     * @throws moodle_exception
     */
    public function add_feedback(int $authorid, int $courseid, int $learnerid, string $week, string $message): \stdClass {
        global $DB;
        weeks::require_valid($week);
        $message = trim($message);
        if ($message === '') {
            throw new invalid_parameter_exception('Empty message');
        }
        $context = context_course::instance($courseid);
        if ($authorid != $learnerid) {
            require_capability('local/gopilot:coach', $context, $authorid);
        }
        access::require_learner($courseid, $learnerid);

        $weekrecord = weekdata::get_or_create($learnerid, $week);
        $record = (object)[
            'weekid' => $weekrecord->id,
            'courseid' => $courseid,
            'authorid' => $authorid,
            'message' => $message,
            'timecreated' => $this->now,
        ];
        $record->id = $DB->insert_record('local_gopilot_feedback', $record);

        \local_gopilot\event\feedback_added::create([
            'objectid' => $record->id,
            'context' => $context,
            'relateduserid' => $learnerid,
            'other' => ['week' => $week],
        ])->trigger();

        $author = \core_user::get_user($authorid);
        $course = get_course($courseid);
        $a = (object)[
            'author' => fullname($author),
            'course' => format_string($course->fullname, true, ['context' => $context]),
            'kw' => weeks::number($week),
            'message' => \core_text::substr($message, 0, 200),
        ];
        $subject = get_string('notify_feedback_subject', 'local_gopilot', $a);
        $body = get_string('notify_feedback_body', 'local_gopilot', $a);
        if ($authorid == $learnerid) {
            foreach (self::coaches($courseid, $learnerid) as $coachid) {
                notifier::send(
                    'feedback',
                    $coachid,
                    $author,
                    $subject,
                    $body,
                    '/lerndoku/' . $courseid . '/' . $learnerid,
                    $courseid
                );
            }
        } else {
            notifier::send('feedback', $learnerid, $author, $subject, $body, '/woche/' . $week, $courseid);
        }
        return $record;
    }

    /**
     * Learning coaches of a course (active enrolment), excluding a user.
     *
     * @param int $courseid
     * @param int $excludeid
     * @return int[]
     */
    public static function coaches(int $courseid, int $excludeid = 0): array {
        $users = get_enrolled_users(
            context_course::instance($courseid),
            'local/gopilot:coach',
            0,
            'u.id',
            null,
            0,
            0,
            true
        );
        $ids = [];
        foreach ($users as $user) {
            if ($user->id != $excludeid) {
                $ids[] = (int)$user->id;
            }
        }
        return $ids;
    }

    /**
     * Recalculate and store the Ampel of a learner in the given courses.
     *
     * @param int $userid
     * @param int[] $courseids
     * @return void
     */
    protected function recalculate(int $userid, array $courseids): void {
        $calculator = new calculator($this->now);
        foreach ($courseids as $courseid) {
            $calculator->compute_and_store($userid, (int)$courseid);
        }
    }
}
