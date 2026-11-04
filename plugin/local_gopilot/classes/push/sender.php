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

namespace local_gopilot\push;

/**
 * Sends Web Push messages to all subscriptions of a user.
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sender {
    /**
     * Whether VAPID keys are configured.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return trim((string)get_config('local_gopilot', 'vapidpublickey')) !== ''
            && trim((string)get_config('local_gopilot', 'vapidprivatekey')) !== '';
    }

    /**
     * VAPID subject (contact), configured or derived from the site.
     *
     * @return string
     */
    public static function subject(): string {
        global $CFG;
        $subject = trim((string)get_config('local_gopilot', 'vapidsubject'));
        if ($subject !== '') {
            return $subject;
        }
        if (strpos($CFG->wwwroot, 'https://') === 0) {
            return $CFG->wwwroot;
        }
        return 'mailto:' . ($CFG->noreplyaddress ?? 'noreply@localhost');
    }

    /**
     * Send a notification to all subscriptions of a user. Gone subscriptions (404/410) are deleted.
     *
     * @param int $userid
     * @param string $title
     * @param string $body
     * @param string $url path in the app
     * @return int number of successful deliveries
     */
    public function send_to_user(int $userid, string $title, string $body, string $url): int {
        global $DB;
        if (!self::is_enabled()) {
            return 0;
        }
        $payload = json_encode(
            ['title' => $title, 'body' => \core_text::substr($body, 0, 500), 'url' => $url],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        $sent = 0;
        foreach ($DB->get_records('local_gopilot_pushsub', ['userid' => $userid]) as $sub) {
            try {
                $status = $this->send_one($sub, $payload);
            } catch (\Throwable $e) {
                debugging('GoPilot push failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
                continue;
            }
            if ($status == 404 || $status == 410) {
                $DB->delete_records('local_gopilot_pushsub', ['id' => $sub->id]);
            } else if ($status >= 200 && $status < 300) {
                $sent++;
            }
        }
        return $sent;
    }

    /**
     * Encrypt and send one message.
     *
     * @param \stdClass $sub subscription record
     * @param string $payload
     * @return int HTTP status
     */
    public function send_one(\stdClass $sub, string $payload): int {
        $publickey = trim((string)get_config('local_gopilot', 'vapidpublickey'));
        $privatekey = trim((string)get_config('local_gopilot', 'vapidprivatekey'));
        $body = encryption::encrypt($payload, $sub->p256dh, $sub->auth);
        $jwt = vapid::create_jwt($sub->endpoint, self::subject(), $publickey, $privatekey);
        $headers = [
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'TTL: 86400',
            'Urgency: normal',
            'Authorization: vapid t=' . $jwt . ', k=' . $publickey,
        ];
        return $this->post($sub->endpoint, $headers, $body);
    }

    /**
     * HTTP POST (overridden in tests).
     *
     * @param string $url
     * @param string[] $headers
     * @param string $body
     * @return int HTTP status
     */
    protected function post(string $url, array $headers, string $body): int {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl(['ignoresecurity' => false]);
        $curl->setHeader($headers);
        $curl->post($url, $body, ['CURLOPT_TIMEOUT' => 10, 'CURLOPT_CONNECTTIMEOUT' => 5]);
        $info = $curl->get_info();
        return (int)($info['http_code'] ?? 0);
    }
}
