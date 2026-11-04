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

/**
 * Log in with a one-time key from local_gopilot_get_moodle_link and open the requested page.
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This page performs the login itself, so it cannot require one.
// phpcs:ignore moodle.Files.RequireLogin.Missing
require_once(__DIR__ . '/../../config.php');

$userid = required_param('userid', PARAM_INT);
$key = required_param('key', PARAM_ALPHANUMEXT);
$urltogo = optional_param('urltogo', $CFG->wwwroot, PARAM_LOCALURL) ?: $CFG->wwwroot;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/gopilot/autologin.php'));

// Already logged in as this user: just go there. As someone else: do not switch silently.
if (isloggedin() && !isguestuser()) {
    delete_user_key(\local_gopilot\local\autologin::SCRIPT, $userid);
    if ((int)$USER->id === $userid) {
        redirect($urltogo);
    }
    throw new moodle_exception('alreadyloggedin', 'error', '', format_string(fullname($USER)));
}

$user = \local_gopilot\local\autologin::consume_key($userid, $key);
if (!$user = get_complete_user_data('id', $user->id)) {
    throw new moodle_exception('cannotfinduser', '', '', $userid);
}
complete_user_login($user);
\core\session\manager::apply_concurrent_login_limit($user->id, session_id());

redirect($urltogo);
