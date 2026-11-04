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
use local_gopilot\local\weekdata;
use local_gopilot\local\weeks;

/**
 * Tests for the Ampel calculator (docs/api-v1.md, "Ampel-Regeln").
 *
 * @package    local_gopilot
 * @category   test
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_gopilot\ampel\calculator
 */
final class calculator_test extends \advanced_testcase {
    /** @var string current week in all tests */
    const CURRENT = '2026-11-02';

    /** @var string most recently closed week */
    const LASTWEEK = '2026-10-26';

    /** @var int reference time: Wednesday 10:00 of the current week */
    protected $now;

    /** @var \stdClass */
    protected $course;

    /** @var \stdClass */
    protected $user;

    /**
     * Course with completion and a learner.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->now = weeks::start(self::CURRENT) + 2 * DAYSECS + 10 * HOURSECS;
        $this->course = $this->getDataGenerator()->create_course(['enablecompletion' => 1, 'numsections' => 2]);
        $this->user = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
    }

    /**
     * Create a page with manual completion.
     *
     * @param int $expected completionexpected
     * @param \stdClass|null $course
     * @param int $section
     * @return \stdClass course module record
     */
    protected function page(int $expected = 0, ?\stdClass $course = null, int $section = 1): \stdClass {
        $course = $course ?? $this->course;
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'section' => $section,
            'completion' => COMPLETION_TRACKING_MANUAL,
            'completionexpected' => $expected,
        ]);
        return get_coursemodule_from_instance('page', $page->id, $course->id);
    }

    /**
     * Mark a course module complete at a given time.
     *
     * @param \stdClass $cm
     * @param int $time
     */
    protected function complete(\stdClass $cm, int $time): void {
        global $DB;
        $DB->insert_record('course_modules_completion', (object)[
            'coursemoduleid' => $cm->id,
            'userid' => $this->user->id,
            'completionstate' => COMPLETION_COMPLETE,
            'timemodified' => $time,
        ]);
    }

    /**
     * Plan course modules in a week.
     *
     * @param string $week
     * @param \stdClass[] $cms
     * @return \stdClass week record
     */
    protected function plan(string $week, array $cms): \stdClass {
        global $DB;
        $record = weekdata::get_or_create($this->user->id, $week);
        foreach ($cms as $i => $cm) {
            $DB->insert_record('local_gopilot_planitem', (object)[
                'weekid' => $record->id, 'courseid' => $cm->course, 'cmid' => $cm->id, 'sortorder' => $i,
                'completedatclose' => null, 'timecreated' => $this->now,
            ]);
        }
        return $record;
    }

    /**
     * Store a course reflection.
     *
     * @param string $week
     * @param string $text
     */
    protected function reflect(string $week, string $text = 'Reflexion'): void {
        global $DB;
        $record = weekdata::get_or_create($this->user->id, $week);
        $DB->insert_record('local_gopilot_coursereflection', (object)[
            'weekid' => $record->id, 'courseid' => $this->course->id, 'text' => $text,
            'timecreated' => $this->now, 'timemodified' => $this->now,
        ]);
    }

    /**
     * Store a self-assessment.
     *
     * @param string $week
     * @param int $sectionnum
     * @param int $level
     */
    protected function assess(string $week, int $sectionnum, int $level): void {
        global $DB;
        $record = weekdata::get_or_create($this->user->id, $week);
        $section = $DB->get_record('course_sections', ['course' => $this->course->id, 'section' => $sectionnum]);
        $DB->insert_record('local_gopilot_selfassessment', (object)[
            'weekid' => $record->id, 'courseid' => $this->course->id, 'sectionid' => $section->id,
            'selflevel' => $level, 'remark' => '', 'timemodified' => $this->now,
        ]);
    }

    /**
     * Compute and return one signal.
     *
     * @param string $key
     * @return array
     */
    protected function signal(string $key): array {
        $result = (new calculator($this->now))->compute($this->user->id, $this->course->id);
        foreach ($result['signals'] as $signal) {
            if ($signal['key'] === $key) {
                return $signal;
            }
        }
        $this->fail('Signal ' . $key . ' missing');
    }

    /**
     * Result shape: four signals in the defined order.
     */
    public function test_result_shape(): void {
        $result = (new calculator($this->now))->compute($this->user->id, $this->course->id);
        $this->assertSame((int)$this->course->id, $result['courseid']);
        $this->assertSame($this->now, $result['timecomputed']);
        $this->assertSame(['plan', 'backlog', 'reflection', 'selfassessment'], array_column($result['signals'], 'key'));
        foreach ($result['signals'] as $signal) {
            $this->assertSame(['key', 'active', 'colour', 'value', 'reason'], array_keys($signal));
        }
    }

    /**
     * Plan: completed until Sunday 23:59:59 counts, later completions do not; 2 of 3 = 67 % = yellow.
     */
    public function test_plan_fulfilment(): void {
        courseconfig::save($this->course->id, [self::LASTWEEK], true, []);
        $cms = [$this->page(), $this->page(), $this->page()];
        $this->plan(self::LASTWEEK, $cms);
        $this->complete($cms[0], weeks::start(self::LASTWEEK) + DAYSECS);
        $this->complete($cms[1], weeks::end(self::LASTWEEK));
        $this->complete($cms[2], weeks::end(self::LASTWEEK) + 1);

        $signal = $this->signal('plan');
        $this->assertTrue($signal['active']);
        $this->assertSame(67, $signal['value']);
        $this->assertSame('yellow', $signal['colour']);
        $this->assertStringContainsString('2', $signal['reason']);
        $this->assertStringContainsString('67', $signal['reason']);
    }

    /**
     * Plan: colours at the default thresholds (70 green, 40 yellow).
     */
    public function test_plan_colours(): void {
        global $DB;
        courseconfig::save($this->course->id, [self::LASTWEEK], true, []);
        $cms = [];
        for ($i = 0; $i < 10; $i++) {
            $cms[] = $this->page();
        }
        $week = $this->plan(self::LASTWEEK, $cms);
        $expected = [0 => 'red', 3 => 'red', 4 => 'yellow', 6 => 'yellow', 7 => 'green', 10 => 'green'];
        foreach ($expected as $done => $colour) {
            $DB->set_field('local_gopilot_planitem', 'completedatclose', 0, ['weekid' => $week->id]);
            $items = array_values($DB->get_records('local_gopilot_planitem', ['weekid' => $week->id], 'id ASC'));
            for ($i = 0; $i < $done; $i++) {
                $DB->set_field('local_gopilot_planitem', 'completedatclose', 1, ['id' => $items[$i]->id]);
            }
            $signal = $this->signal('plan');
            $this->assertSame($done * 10, $signal['value']);
            $this->assertSame($colour, $signal['colour'], "$done of 10");
        }
    }

    /**
     * Plan: the frozen state (completedatclose) wins over live completion data.
     */
    public function test_plan_uses_frozen_state(): void {
        global $DB;
        courseconfig::save($this->course->id, [self::LASTWEEK], true, []);
        $cm = $this->page();
        $week = $this->plan(self::LASTWEEK, [$cm]);
        $this->complete($cm, weeks::start(self::LASTWEEK));
        $DB->set_field('local_gopilot_planitem', 'completedatclose', 0, ['weekid' => $week->id]);
        $this->assertSame(0, $this->signal('plan')['value']);
        $this->assertSame('red', $this->signal('plan')['colour']);
    }

    /**
     * Plan: inactive in a Betriebswoche (last closed week is no school week).
     */
    public function test_plan_inactive_in_company_week(): void {
        courseconfig::save($this->course->id, [weeks::add(self::LASTWEEK, -1), self::CURRENT], true, []);
        $this->plan(self::LASTWEEK, [$this->page()]);
        $signal = $this->signal('plan');
        $this->assertFalse($signal['active']);
        $this->assertSame('none', $signal['colour']);
        $this->assertSame(0, $signal['value']);
        $this->assertSame(get_string('signal_plan_noschoolweek', 'local_gopilot'), $signal['reason']);
    }

    /**
     * Plan: inactive if nothing of this course was planned (even if other courses were).
     */
    public function test_plan_inactive_without_planned_steps(): void {
        courseconfig::save($this->course->id, [self::LASTWEEK], true, []);
        $other = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->getDataGenerator()->enrol_user($this->user->id, $other->id, 'student');
        $this->plan(self::LASTWEEK, [$this->page(0, $other)]);
        $signal = $this->signal('plan');
        $this->assertFalse($signal['active']);
        $this->assertSame(get_string('signal_plan_nothingplanned', 'local_gopilot'), $signal['reason']);
    }

    /**
     * Plan: a course without teaching weeks never has a school week.
     */
    public function test_plan_inactive_without_teaching_weeks(): void {
        $this->plan(self::LASTWEEK, [$this->page()]);
        $this->assertFalse($this->signal('plan')['active']);
    }

    /**
     * Backlog: only steps with a passed date that are not completed; steps without a date never count.
     */
    public function test_backlog_counts_overdue_steps(): void {
        $past = $this->now - DAYSECS;
        $done = $this->page($past);
        $this->complete($done, $past);
        $this->page($past);
        $this->page(0);
        $this->page($this->now + DAYSECS);
        $signal = $this->signal('backlog');
        $this->assertTrue($signal['active']);
        $this->assertSame(1, $signal['value']);
        $this->assertSame('green', $signal['colour']);
    }

    /**
     * Backlog: colours at the default thresholds (2 yellow, 4 red); assignment due dates count.
     */
    public function test_backlog_colours_and_due_dates(): void {
        $this->assertSame(0, $this->signal('backlog')['value']);
        $this->assertSame(get_string('signal_backlog_none', 'local_gopilot'), $this->signal('backlog')['reason']);
        $this->getDataGenerator()->create_module('assign', [
            'course' => $this->course->id, 'section' => 1, 'completion' => COMPLETION_TRACKING_MANUAL,
            'duedate' => $this->now - HOURSECS,
        ]);
        $this->page($this->now - HOURSECS);
        $this->assertSame(2, $this->signal('backlog')['value']);
        $this->assertSame('yellow', $this->signal('backlog')['colour']);
        $this->page($this->now - HOURSECS);
        $this->assertSame('yellow', $this->signal('backlog')['colour']);
        $this->page($this->now - HOURSECS);
        $this->assertSame(4, $this->signal('backlog')['value']);
        $this->assertSame('red', $this->signal('backlog')['colour']);
    }

    /**
     * Backlog: inactive without completion tracking.
     */
    public function test_backlog_inactive_without_completion(): void {
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 0]);
        $this->getDataGenerator()->enrol_user($this->user->id, $course->id, 'student');
        $result = (new calculator($this->now))->compute($this->user->id, $course->id);
        $this->assertFalse($result['signals'][1]['active']);
        $this->assertSame('none', $result['colour']);
    }

    /**
     * Reflection: inactive without teaching weeks and without closed school week.
     */
    public function test_reflection_inactive(): void {
        $signal = $this->signal('reflection');
        $this->assertFalse($signal['active']);
        $this->assertSame(get_string('signal_reflection_noteachingweeks', 'local_gopilot'), $signal['reason']);

        courseconfig::save($this->course->id, [self::CURRENT, weeks::add(self::CURRENT, 1)], true, []);
        $signal = $this->signal('reflection');
        $this->assertFalse($signal['active']);
        $this->assertSame(get_string('signal_reflection_noclosedweek', 'local_gopilot'), $signal['reason']);
    }

    /**
     * Reflection: consecutive closed school weeks without course reflection, newest first;
     * company weeks in between are skipped and the current week does not count.
     */
    public function test_reflection_missing_weeks(): void {
        $w1 = weeks::add(self::LASTWEEK, -3);
        $w2 = weeks::add(self::LASTWEEK, -2);
        $w3 = self::LASTWEEK;
        courseconfig::save($this->course->id, [$w1, $w2, $w3, self::CURRENT], true, []);

        $this->assertSame(3, $this->signal('reflection')['value']);
        $this->assertSame('red', $this->signal('reflection')['colour']);

        $this->reflect($w2);
        $this->assertSame(1, $this->signal('reflection')['value']);
        $this->assertSame('yellow', $this->signal('reflection')['colour']);

        $this->reflect($w3, '   ');
        $this->assertSame(1, $this->signal('reflection')['value'], 'Whitespace is no reflection');

        global $DB;
        $DB->set_field('local_gopilot_coursereflection', 'text', 'Fertig', ['courseid' => $this->course->id]);
        $this->assertSame(0, $this->signal('reflection')['value']);
        $this->assertSame('green', $this->signal('reflection')['colour']);
    }

    /**
     * Self-assessment: worst level of the newest week with a level; older weeks are ignored.
     */
    public function test_selfassessment(): void {
        $signal = $this->signal('selfassessment');
        $this->assertFalse($signal['active']);

        $this->assess(weeks::add(self::LASTWEEK, -1), 1, 4);
        $this->assertSame('red', $this->signal('selfassessment')['colour']);

        $this->assess(self::LASTWEEK, 1, 2);
        $this->assess(self::LASTWEEK, 2, 3);
        $signal = $this->signal('selfassessment');
        $this->assertTrue($signal['active']);
        $this->assertSame(3, $signal['value']);
        $this->assertSame('yellow', $signal['colour']);
        $this->assertStringContainsString(get_string('selflevel3', 'local_gopilot'), $signal['reason']);
        $this->assertStringContainsString((string)weeks::number(self::LASTWEEK), $signal['reason']);

        $this->assess(self::CURRENT, 1, 0);
        $this->assertSame(3, $this->signal('selfassessment')['value'], 'Level 0 is ignored');

        $this->assess(self::CURRENT, 2, 1);
        $this->assertSame('green', $this->signal('selfassessment')['colour']);
    }

    /**
     * Thresholds of the course override the defaults.
     */
    public function test_threshold_overrides(): void {
        courseconfig::save($this->course->id, [self::LASTWEEK], false, [
            'plangreen' => 60, 'planyellow' => 30, 'backlogyellow' => 1, 'backlogred' => 2,
            'reflectionyellow' => 1, 'reflectionred' => 3,
        ]);
        $cms = [$this->page(), $this->page(), $this->page()];
        $this->plan(self::LASTWEEK, $cms);
        $this->complete($cms[0], weeks::start(self::LASTWEEK));
        $this->complete($cms[1], weeks::start(self::LASTWEEK));
        $this->assertSame('green', $this->signal('plan')['colour']);

        $this->page($this->now - HOURSECS);
        $this->assertSame('yellow', $this->signal('backlog')['colour']);

        $config = new courseconfig($this->course->id);
        $this->assertFalse($config->uses_defaults());
        $this->assertSame(60, $config->get_thresholds()['plangreen']);
    }

    /**
     * Invalid thresholds are rejected.
     */
    public function test_invalid_thresholds(): void {
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('invalidthresholds', 'local_gopilot'));
        courseconfig::save($this->course->id, [], false, ['plangreen' => 40, 'planyellow' => 40]);
    }

    /**
     * Overall colour is the worst active signal; "none" without active signals.
     */
    public function test_worst_colour(): void {
        $this->assertSame('none', calculator::worst([]));
        $this->assertSame('green', calculator::worst(['green', 'none']));
        $this->assertSame('yellow', calculator::worst(['green', 'yellow', 'green']));
        $this->assertSame('red', calculator::worst(['yellow', 'red', 'green']));

        // Backlog green (active), reflection red: overall red.
        courseconfig::save($this->course->id, [weeks::add(self::LASTWEEK, -1), self::LASTWEEK], true, []);
        $result = (new calculator($this->now))->compute($this->user->id, $this->course->id);
        $this->assertSame('red', $result['colour']);

        // Only the backlog is active: green.
        courseconfig::save($this->course->id, [], true, []);
        $result = (new calculator($this->now))->compute($this->user->id, $this->course->id);
        $this->assertSame('green', $result['colour']);
    }

    /**
     * Storing and change notifications: only a change to yellow or red is reported, once.
     */
    public function test_store_and_pending_notification(): void {
        global $DB;
        $calculator = new calculator($this->now);
        $calculator->compute_and_store($this->user->id, $this->course->id);
        $record = $DB->get_record('local_gopilot_ampel', ['userid' => $this->user->id, 'courseid' => $this->course->id]);
        $this->assertSame('green', $record->colour);
        $this->assertCount(4, json_decode($record->signals, true));
        $this->assertNull(calculator::take_pending_notification($this->user->id, $this->course->id));

        $this->page($this->now - HOURSECS);
        $this->page($this->now - HOURSECS);
        $calculator = new calculator($this->now);
        $calculator->compute_and_store($this->user->id, $this->course->id);
        $pending = calculator::take_pending_notification($this->user->id, $this->course->id);
        $this->assertSame('yellow', $pending['colour']);
        $this->assertNull(calculator::take_pending_notification($this->user->id, $this->course->id));
        $this->assertCount(1, $DB->get_records('local_gopilot_ampel'));
    }

    /**
     * Week helpers in the server timezone.
     *
     * @covers \local_gopilot\local\weeks
     */
    public function test_weeks(): void {
        $this->assertTrue(weeks::is_valid('2026-11-02'));
        $this->assertFalse(weeks::is_valid('2026-11-03'));
        $this->assertFalse(weeks::is_valid('2026-13-02'));
        $this->assertFalse(weeks::is_valid('02.11.2026'));
        $this->assertSame(self::CURRENT, weeks::current($this->now));
        $this->assertSame(self::LASTWEEK, weeks::last_closed($this->now));
        $this->assertSame(weeks::start('2026-11-09') - 1, weeks::end(self::CURRENT));
        $this->assertTrue(weeks::is_closed(self::LASTWEEK, $this->now));
        $this->assertFalse(weeks::is_closed(self::CURRENT, $this->now));
        $this->assertSame(45, weeks::number(self::CURRENT));
        $this->assertSame(self::CURRENT, weeks::from_time(weeks::end(self::CURRENT)));
        $this->expectException(\moodle_exception::class);
        weeks::require_valid('2026-11-04');
    }
}
