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

use context_system;
use core_user;
use moodle_exception;
use moodle_url;
use stdClass;

/**
 * Automatic Moodle login from the app (ADR 0007).
 *
 * The app only holds a web service token, so Moodle has no browser session. To open an activity without a
 * second login, the app asks for a one-time key (valid for a few seconds, bound to the requesting IP address)
 * and opens autologin.php with it. This mirrors tool_mobile's autologin, which only accepts the official app.
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class autologin {
    /** Script name of the user keys in user_private_key. */
    public const SCRIPT = 'local_gopilot';

    /** Lifetime of a key in seconds: the app opens the link right away. */
    public const KEY_TTL = 60;

    /**
     * Check that an automatic login is allowed for this user on this site.
     *
     * @param int $userid
     * @return void
     * @throws moodle_exception
     */
    public static function check_prerequisites(int $userid): void {
        global $CFG;
        if (empty($CFG->enablewebservices)) {
            throw new moodle_exception('enablewsdescription', 'webservice');
        }
        if (!is_https() && !self::is_local_site()) {
            throw new moodle_exception('autologinhttps', 'local_gopilot');
        }
        if (is_siteadmin($userid) || has_capability('moodle/site:config', context_system::instance(), $userid)) {
            throw new moodle_exception('autologinnotforadmins', 'local_gopilot');
        }
    }

    /**
     * Whether this is a local development site (http allowed).
     *
     * @return bool
     */
    protected static function is_local_site(): bool {
        global $CFG;
        $host = (string)parse_url($CFG->wwwroot, PHP_URL_HOST);
        return in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.localhost');
    }

    /**
     * Build a login link for the current user that ends on the given Moodle page.
     *
     * @param string $url target page, must be inside this Moodle
     * @return string link to autologin.php with a fresh one-time key
     * @throws moodle_exception
     */
    public static function create_link(string $url): string {
        global $USER;
        $target = self::local_target($url);
        self::check_prerequisites((int)$USER->id);

        // Only one key per user at a time.
        delete_user_key(self::SCRIPT, $USER->id);
        $key = create_user_key(self::SCRIPT, $USER->id, null, getremoteaddr(), time() + self::KEY_TTL);

        return (new moodle_url('/local/gopilot/autologin.php', [
            'userid' => $USER->id,
            'key' => $key,
            'urltogo' => $target,
        ]))->out(false);
    }

    /**
     * Normalise a target URL and make sure it points into this Moodle.
     *
     * @param string $url
     * @return string
     * @throws moodle_exception
     */
    public static function local_target(string $url): string {
        global $CFG;
        $url = trim($url);
        $root = rtrim($CFG->wwwroot, '/');
        if ($url === '') {
            return $root . '/';
        }
        if ($url === $root || str_starts_with($url, $root . '/') || str_starts_with($url, $root . '?')) {
            return $url;
        }
        throw new moodle_exception('invalidmoodleurl', 'local_gopilot');
    }

    /**
     * Validate and consume a key. The key is deleted in any case after the first use.
     *
     * @param int $userid user the link was issued for
     * @param string $key
     * @return stdClass the active user to log in
     * @throws moodle_exception
     */
    public static function consume_key(int $userid, string $key): stdClass {
        global $DB;
        self::check_prerequisites($userid);
        try {
            $record = validate_user_key($key, self::SCRIPT, null);
        } finally {
            // Any presented key is used up, whoever it belongs to.
            $DB->delete_records('user_private_key', ['script' => self::SCRIPT, 'value' => $key]);
            delete_user_key(self::SCRIPT, $userid);
        }
        if ((int)$record->userid !== $userid) {
            throw new moodle_exception('invalidkey');
        }
        $user = core_user::get_user($userid, '*', MUST_EXIST);
        core_user::require_active_user($user, true, true);
        return $user;
    }
}
