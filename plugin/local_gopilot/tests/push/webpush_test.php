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
 * Tests for VAPID JWTs, aes128gcm encryption and the sender.
 *
 * @package    local_gopilot
 * @category   test
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_gopilot\push\vapid
 * @covers     \local_gopilot\push\encryption
 * @covers     \local_gopilot\push\sender
 */
final class webpush_test extends \advanced_testcase {
    /**
     * Generated keys have the Web Push format.
     */
    public function test_generate_keys(): void {
        $keys = vapid::generate_keys();
        $public = vapid::b64url_decode($keys['publickey']);
        $this->assertSame(65, strlen($public));
        $this->assertSame("\x04", $public[0]);
        $this->assertSame(32, strlen(vapid::b64url_decode($keys['privatekey'])));
        $this->assertDoesNotMatchRegularExpression('/[+\/=]/', $keys['publickey']);
        $this->assertNotFalse(openssl_pkey_get_public(vapid::public_pem($public)));
        $this->assertNotFalse(openssl_pkey_get_private(vapid::private_pem(
            vapid::b64url_decode($keys['privatekey']),
            $public
        )));
    }

    /**
     * The JWT has the ES256 shape, the right claims and a valid signature.
     */
    public function test_jwt(): void {
        $keys = vapid::generate_keys();
        $jwt = vapid::create_jwt(
            'https://push.example.com:8443/send/abc',
            'mailto:admin@example.com',
            $keys['publickey'],
            $keys['privatekey'],
            1900000000
        );
        $parts = explode('.', $jwt);
        $this->assertCount(3, $parts);
        $this->assertSame(['typ' => 'JWT', 'alg' => 'ES256'], json_decode(vapid::b64url_decode($parts[0]), true));
        $this->assertSame(
            ['aud' => 'https://push.example.com:8443', 'exp' => 1900000000, 'sub' => 'mailto:admin@example.com'],
            json_decode(vapid::b64url_decode($parts[1]), true)
        );
        $signature = vapid::b64url_decode($parts[2]);
        $this->assertSame(64, strlen($signature));
        $pem = vapid::public_pem(vapid::b64url_decode($keys['publickey']));
        $this->assertSame(1, openssl_verify(
            $parts[0] . '.' . $parts[1],
            vapid::raw_to_der($signature),
            $pem,
            OPENSSL_ALGO_SHA256
        ));
        $this->assertSame(0, openssl_verify('tampered', vapid::raw_to_der($signature), $pem, OPENSSL_ALGO_SHA256));
    }

    /**
     * DER and raw signature conversion are inverse, also with leading zero bytes.
     */
    public function test_signature_conversion(): void {
        $raw = str_repeat("\x00", 31) . "\x7f" . "\x80" . str_repeat("\x01", 31);
        $this->assertSame($raw, vapid::der_to_raw(vapid::raw_to_der($raw)));
    }

    /**
     * The body decrypts with the user agent key (independent implementation of RFC 8291 on the receiving side).
     */
    public function test_encryption_roundtrip(): void {
        $ua = vapid::generate_raw();
        $auth = random_bytes(16);
        $payload = json_encode(['title' => 'Neues Feedback', 'body' => 'Gut gemacht – weiter so!', 'url' => '/woche/2026-11-02']);
        $body = encryption::encrypt($payload, vapid::b64url_encode($ua['publickey']), vapid::b64url_encode($auth));

        $salt = substr($body, 0, 16);
        $rs = unpack('N', substr($body, 16, 4))[1];
        $idlen = ord($body[20]);
        $aspublic = substr($body, 21, $idlen);
        $ciphertext = substr($body, 21 + $idlen, -16);
        $tag = substr($body, -16);
        $this->assertSame(4096, $rs);
        $this->assertSame(65, $idlen);
        $this->assertSame(strlen($payload) + 1, strlen($ciphertext));

        $ecdh = openssl_pkey_derive(openssl_pkey_get_public(vapid::public_pem($aspublic)), $ua['key'], 32);
        $prk = hash_hmac('sha256', $ecdh, $auth, true);
        $ikm = hash_hmac('sha256', "WebPush: info\x00" . $ua['publickey'] . $aspublic . "\x01", $prk, true);
        $prk2 = hash_hmac('sha256', $ikm, $salt, true);
        $cek = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\x00\x01", $prk2, true), 0, 16);
        $nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\x00\x01", $prk2, true), 0, 12);
        $plain = openssl_decrypt($ciphertext, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
        $this->assertSame($payload . "\x02", $plain);
    }

    /**
     * A bare e-mail address becomes a mailto: subject; valid subjects stay unchanged.
     */
    public function test_subject_normalisation(): void {
        $this->resetAfterTest();
        $this->assertSame('mailto:admin@example.org', sender::normalise_subject(' admin@example.org '));
        $this->assertSame('mailto:admin@example.org', sender::normalise_subject('mailto:admin@example.org'));
        $this->assertSame('https://moodle.example.org', sender::normalise_subject('https://moodle.example.org'));
        $this->assertSame('', sender::normalise_subject('  '));

        set_config('vapidsubject', 'admin@example.org', 'local_gopilot');
        $this->assertSame('mailto:admin@example.org', sender::subject());
    }

    /**
     * The sender posts to every subscription and deletes gone subscriptions (404/410).
     */
    public function test_sender_removes_gone_subscriptions(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $keys = vapid::generate_keys();
        $ua = vapid::generate_raw();
        foreach (['ok' => 201, 'gone' => 410, 'missing' => 404, 'error' => 500] as $name => $status) {
            $endpoint = 'https://push.example.com/' . $name;
            $DB->insert_record('local_gopilot_pushsub', (object)[
                'userid' => $user->id, 'endpoint' => $endpoint, 'endpointhash' => sha1($endpoint),
                'p256dh' => vapid::b64url_encode($ua['publickey']), 'auth' => vapid::b64url_encode(random_bytes(16)),
                'timecreated' => time(),
            ]);
        }
        $sender = new class extends sender {
            /** @var array recorded requests */
            public $requests = [];

            /**
             * Record instead of sending.
             *
             * @param string $url
             * @param array $headers
             * @param string $body
             * @return int
             */
            protected function post(string $url, array $headers, string $body): int {
                $this->requests[] = ['url' => $url, 'headers' => $headers];
                return ['ok' => 201, 'gone' => 410, 'missing' => 404, 'error' => 500][basename($url)];
            }
        };

        $this->assertSame(0, $sender->send_to_user($user->id, 'Titel', 'Text', '/'), 'Disabled without keys');
        $this->assertCount(0, $sender->requests);

        set_config('vapidpublickey', $keys['publickey'], 'local_gopilot');
        set_config('vapidprivatekey', $keys['privatekey'], 'local_gopilot');
        $this->assertSame(1, $sender->send_to_user($user->id, 'Titel', 'Text', '/woche/2026-11-02'));
        $this->assertCount(4, $sender->requests);
        $headers = implode("\n", $sender->requests[0]['headers']);
        $this->assertStringContainsString('Content-Encoding: aes128gcm', $headers);
        $this->assertMatchesRegularExpression('/Authorization: vapid t=[\w-]+\.[\w-]+\.[\w-]+, k=' .
            preg_quote($keys['publickey'], '/') . '/', $headers);
        $remaining = $DB->get_fieldset_select('local_gopilot_pushsub', 'endpoint', 'userid = ?', [$user->id]);
        sort($remaining);
        $this->assertSame(['https://push.example.com/error', 'https://push.example.com/ok'], $remaining);
    }
}
