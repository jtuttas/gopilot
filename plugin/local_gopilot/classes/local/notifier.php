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

use local_gopilot\push\sender;

/**
 * Sends notifications through the Moodle message API and additionally as Web Push (ADR 0005).
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notifier {
    /**
     * Send a notification.
     *
     * @param string $provider feedback, reflection, reflectiondue or ampelchange
     * @param int $touserid
     * @param \stdClass|null $from sender, null = no-reply user
     * @param string $subject
     * @param string $text plain text
     * @param string $path path in the app, e.g. /woche/2026-11-02
     * @param int $courseid
     * @return void
     */
    public static function send(
        string $provider,
        int $touserid,
        ?\stdClass $from,
        string $subject,
        string $text,
        string $path,
        int $courseid = SITEID
    ): void {
        $userto = \core_user::get_user($touserid);
        if (!$userto || !empty($userto->deleted) || !empty($userto->suspended)) {
            return;
        }
        $appurl = rtrim(trim((string)get_config('local_gopilot', 'appurl')), '/');

        $message = new \core\message\message();
        $message->component = 'local_gopilot';
        $message->name = $provider;
        $message->userfrom = $from ?? \core_user::get_noreply_user();
        $message->userto = $userto;
        $message->subject = $subject;
        $message->fullmessage = $text;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = text_to_html(s($text), false, false, true);
        $message->smallmessage = $text;
        $message->notification = 1;
        $message->courseid = $courseid;
        if ($appurl !== '') {
            $message->contexturl = $appurl . $path;
            $message->contexturlname = get_string('pluginname', 'local_gopilot');
        }
        message_send($message);

        try {
            (new sender())->send_to_user($touserid, $subject, $text, $path);
        } catch (\Throwable $e) {
            debugging('GoPilot push failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
