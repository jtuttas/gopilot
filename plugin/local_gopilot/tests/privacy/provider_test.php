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

namespace local_gopilot\privacy;

use context_course;
use context_user;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_gopilot\local\planner;
use local_gopilot\local\weeks;

/**
 * Tests for the privacy provider.
 *
 * @package    local_gopilot
 * @category   test
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_gopilot\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var \stdClass */
    protected $course;

    /** @var \stdClass */
    protected $learner;

    /** @var \stdClass */
    protected $teacher;

    /**
     * A learner with plan, reflection and feedback; a teacher who wrote feedback.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course(['enablecompletion' => 1]);
        $page = $gen->create_module('page', ['course' => $this->course->id, 'section' => 1,
            'completion' => COMPLETION_TRACKING_MANUAL]);
        $this->learner = $gen->create_and_enrol($this->course, 'student');
        $this->teacher = $gen->create_and_enrol($this->course, 'editingteacher');
        $week = weeks::current();
        $this->redirectMessages();
        $planner = new planner();
        $planner->save_plan($this->learner->id, $week, 'Ziel', [$page->cmid]);
        $planner->save_reflection(
            $this->learner->id,
            $week,
            'Allgemein',
            [['courseid' => $this->course->id, 'reflection' => 'Kurs', 'selfassessments' => []]]
        );
        $planner->add_feedback($this->teacher->id, $this->course->id, $this->learner->id, $week, 'Feedback');
    }

    /**
     * Contexts and users are found.
     */
    public function test_contexts_and_users(): void {
        $contexts = provider::get_contexts_for_userid($this->learner->id)->get_contextids();
        $this->assertEqualsCanonicalizing([context_user::instance($this->learner->id)->id,
            context_course::instance($this->course->id)->id], $contexts);
        $this->assertSame(
            [(int)context_course::instance($this->course->id)->id],
            array_map('intval', provider::get_contexts_for_userid($this->teacher->id)->get_contextids())
        );

        $userlist = new userlist(context_course::instance($this->course->id), 'local_gopilot');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$this->learner->id, $this->teacher->id], $userlist->get_userids());
    }

    /**
     * Export writes the week and the course data.
     */
    public function test_export(): void {
        $contextlist = provider::get_contexts_for_userid($this->learner->id);
        provider::export_user_data(new approved_contextlist($this->learner, 'local_gopilot', $contextlist->get_contextids()));
        $week = weeks::current();
        $user = writer::with_context(context_user::instance($this->learner->id))->get_data(['GoPilot', $week]);
        $this->assertSame('Ziel', $user->goal);
        $course = writer::with_context(context_course::instance($this->course->id))->get_data(['GoPilot', $week]);
        $this->assertSame('Kurs', $course->reflection);
        $this->assertSame('Feedback', $course->feedback[0]['message']);
    }

    /**
     * Deleting in the course context removes course data but keeps the weekly goal.
     */
    public function test_delete(): void {
        global $DB;
        $context = context_course::instance($this->course->id);
        provider::delete_data_for_user(new approved_contextlist($this->learner, 'local_gopilot', [$context->id]));
        $this->assertSame(0, $DB->count_records('local_gopilot_coursereflection'));
        $this->assertSame(0, $DB->count_records('local_gopilot_feedback'));
        $this->assertSame(0, $DB->count_records('local_gopilot_ampel'));
        $this->assertSame(1, $DB->count_records('local_gopilot_week'));

        provider::delete_data_for_users(new approved_userlist(
            context_user::instance($this->learner->id),
            'local_gopilot',
            [$this->learner->id]
        ));
        $this->assertSame(0, $DB->count_records('local_gopilot_week'));

        provider::delete_data_for_all_users_in_context($context);
        $this->assertSame(0, $DB->count_records('local_gopilot_planitem'));
    }
}
