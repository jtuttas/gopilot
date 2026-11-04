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
use local_gopilot\local\weekdata;
use local_gopilot\local\weeks;

/**
 * Tests for the scheduled tasks.
 *
 * @package    local_gopilot
 * @category   test
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_gopilot\task\freeze_weeks
 * @covers     \local_gopilot\task\reflection_due
 * @covers     \local_gopilot\task\recalculate_ampel
 */
final class tasks_test extends \advanced_testcase {
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
        $this->course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->user = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
    }

    /**
     * Create a page with manual completion.
     *
     * @param int $expected
     * @return \stdClass course module
     */
    protected function page(int $expected = 0): \stdClass {
        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id, 'section' => 1,
            'completion' => COMPLETION_TRACKING_MANUAL, 'completionexpected' => $expected]);
        return get_coursemodule_from_instance('page', $page->id);
    }

    /**
     * Plan items of closed weeks get their completion state frozen.
     */
    public function test_freeze_weeks(): void {
        global $DB;
        $week = weeks::add(weeks::current(), -1);
        $record = weekdata::get_or_create($this->user->id, $week);
        $done = $this->page();
        $late = $this->page();
        $current = weekdata::get_or_create($this->user->id, weeks::current());
        foreach ([[$record, $done], [$record, $late], [$current, $done]] as [$weekrecord, $cm]) {
            $DB->insert_record('local_gopilot_planitem', (object)['weekid' => $weekrecord->id, 'courseid' => $this->course->id,
                'cmid' => $cm->id, 'sortorder' => 0, 'completedatclose' => null, 'timecreated' => time()]);
        }
        $DB->insert_record('course_modules_completion', (object)['coursemoduleid' => $done->id, 'userid' => $this->user->id,
            'completionstate' => COMPLETION_COMPLETE, 'timemodified' => weeks::end($week) - 10]);
        $DB->insert_record('course_modules_completion', (object)['coursemoduleid' => $late->id, 'userid' => $this->user->id,
            'completionstate' => COMPLETION_COMPLETE, 'timemodified' => weeks::end($week) + 10]);

        $this->expectOutputRegex('/froze 2 plan items/');
        (new freeze_weeks())->execute();
        $this->assertEquals(1, $DB->get_field(
            'local_gopilot_planitem',
            'completedatclose',
            ['weekid' => $record->id, 'cmid' => $done->id]
        ));
        $this->assertEquals(0, $DB->get_field(
            'local_gopilot_planitem',
            'completedatclose',
            ['weekid' => $record->id, 'cmid' => $late->id]
        ));
        $this->assertNull($DB->get_field('local_gopilot_planitem', 'completedatclose', ['weekid' => $current->id]));
    }

    /**
     * Learners without reflection in a school week get a reminder.
     */
    public function test_reflection_due(): void {
        global $DB;
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $record = weekdata::get_or_create($other->id, weeks::current());
        $DB->insert_record('local_gopilot_coursereflection', (object)['weekid' => $record->id,
            'courseid' => $this->course->id, 'text' => 'Erledigt', 'timecreated' => time(), 'timemodified' => time()]);

        $sink = $this->redirectMessages();
        $this->expectOutputRegex('/sent 0 reflection reminders.*sent 1 reflection reminders/s');
        (new reflection_due())->execute();
        courseconfig::save($this->course->id, [weeks::current()], true, []);
        (new reflection_due())->execute();
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertEquals($this->user->id, $messages[0]->useridto);
        $this->assertSame('reflectiondue', $messages[0]->eventtype);
    }

    /**
     * The nightly recalculation notifies once when the Ampel turns yellow or red.
     */
    public function test_recalculate_ampel_notifies_once(): void {
        courseconfig::save($this->course->id, [], true, []);
        $sink = $this->redirectMessages();
        ob_start();
        (new recalculate_ampel())->execute();
        $this->assertCount(0, $sink->get_messages(), 'Green: no notification');

        $this->page(time() - DAYSECS);
        $this->page(time() - DAYSECS);
        (new recalculate_ampel())->execute();
        (new recalculate_ampel())->execute();
        $output = ob_get_clean();
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertSame('ampelchange', $messages[0]->eventtype);
        $this->assertEquals($this->user->id, $messages[0]->useridto);
        $this->assertStringContainsString('recalculated 1 Ampels', $output);
    }
}
