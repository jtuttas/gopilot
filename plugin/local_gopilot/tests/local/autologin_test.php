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

use local_gopilot\external\get_moodle_link;
use moodle_exception;

/**
 * Tests for the automatic Moodle login from the app.
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_gopilot\local\autologin
 * @covers     \local_gopilot\external\get_moodle_link
 */
final class autologin_test extends \advanced_testcase {
    /**
     * Keys are bound to the client IP; the CLI has none.
     */
    protected function setUp(): void {
        parent::setUp();
        $_SERVER['REMOTE_ADDR'] = '192.0.2.10';
    }

    /**
     * A key from another IP address is rejected.
     */
    public function test_key_from_other_ip(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $query = $this->parse_link(autologin::create_link(''));
        $this->setUser(null);
        $_SERVER['REMOTE_ADDR'] = '198.51.100.7';

        try {
            autologin::consume_key((int)$user->id, $query['key']);
            $this->fail('Key from another IP accepted');
        } catch (moodle_exception $e) {
            $this->assertSame('ipmismatch', $e->errorcode);
        }
    }

    /**
     * Extract userid and key from a link.
     *
     * @param string $link
     * @return array
     */
    protected function parse_link(string $link): array {
        parse_str((string)parse_url($link, PHP_URL_QUERY), $query);
        return $query;
    }

    /**
     * A link carries a one-time key that is bound to time and IP and points to the requested page.
     */
    public function test_create_link(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $target = $CFG->wwwroot . '/mod/page/view.php?id=5';
        $link = autologin::create_link($target);
        $this->assertStringStartsWith($CFG->wwwroot . '/local/gopilot/autologin.php?', $link);
        $query = $this->parse_link($link);
        $this->assertEquals($user->id, $query['userid']);
        $this->assertSame($target, $query['urltogo']);

        $record = $DB->get_record('user_private_key', ['script' => autologin::SCRIPT, 'userid' => $user->id], '*', MUST_EXIST);
        $this->assertSame($query['key'], $record->value);
        $this->assertNotEmpty($record->iprestriction);
        $this->assertLessThanOrEqual(time() + autologin::KEY_TTL, (int)$record->validuntil);

        // A new link replaces the old key.
        autologin::create_link($target);
        $this->assertSame(1, $DB->count_records('user_private_key', ['script' => autologin::SCRIPT, 'userid' => $user->id]));
    }

    /**
     * Targets outside this Moodle are refused.
     */
    public function test_foreign_target_is_refused(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('invalidmoodleurl', 'local_gopilot'));
        autologin::create_link('https://evil.example.com/moodle/');
    }

    /**
     * Administrators are never logged in automatically.
     */
    public function test_admin_is_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('autologinnotforadmins', 'local_gopilot'));
        autologin::create_link('');
    }

    /**
     * A key works exactly once.
     */
    public function test_key_is_one_time(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $query = $this->parse_link(autologin::create_link(''));
        $this->setUser(null);

        $this->assertEquals($user->id, autologin::consume_key((int)$user->id, $query['key'])->id);

        $this->expectException(moodle_exception::class);
        autologin::consume_key((int)$user->id, $query['key']);
    }

    /**
     * A key of another user is rejected and deleted.
     */
    public function test_key_of_other_user(): void {
        global $DB;
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->setUser($owner);
        $query = $this->parse_link(autologin::create_link(''));
        $this->setUser(null);

        try {
            autologin::consume_key((int)$other->id, $query['key']);
            $this->fail('Key of another user accepted');
        } catch (moodle_exception $e) {
            $this->assertSame('invalidkey', $e->errorcode);
        }
        // The presented key is used up, so it cannot be retried with the right user ID.
        $this->assertFalse($DB->record_exists('user_private_key', ['script' => autologin::SCRIPT, 'userid' => $owner->id]));
    }

    /**
     * Expired keys are rejected.
     */
    public function test_expired_key(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $query = $this->parse_link(autologin::create_link(''));
        $DB->set_field('user_private_key', 'validuntil', time() - 1, ['script' => autologin::SCRIPT, 'userid' => $user->id]);
        $this->setUser(null);

        try {
            autologin::consume_key((int)$user->id, $query['key']);
            $this->fail('Expired key accepted');
        } catch (moodle_exception $e) {
            $this->assertSame('expiredkey', $e->errorcode);
        }
        $this->assertFalse($DB->record_exists('user_private_key', ['script' => autologin::SCRIPT, 'userid' => $user->id]));
    }

    /**
     * Suspended users are not logged in.
     */
    public function test_suspended_user(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $query = $this->parse_link(autologin::create_link(''));
        $this->setUser(null);
        $DB->set_field('user', 'suspended', 1, ['id' => $user->id]);

        $this->expectException(moodle_exception::class);
        autologin::consume_key((int)$user->id, $query['key']);
    }

    /**
     * The web service returns the link.
     */
    public function test_external(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $result = get_moodle_link::execute($CFG->wwwroot . '/course/view.php?id=1');
        $result = \core_external\external_api::clean_returnvalue(get_moodle_link::execute_returns(), $result);
        $this->assertStringContainsString('/local/gopilot/autologin.php', $result['url']);
        $this->assertGreaterThan(time(), $result['validuntil']);
    }
}
