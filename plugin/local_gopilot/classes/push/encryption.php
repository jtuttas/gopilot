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
 * Web Push message encryption, content coding "aes128gcm" (RFC 8188, RFC 8291).
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class encryption {
    /** @var int record size written to the header */
    const RECORD_SIZE = 4096;

    /**
     * Encrypt a payload for a subscription.
     *
     * @param string $payload plaintext (max. ~3990 bytes)
     * @param string $p256dh user agent public key, b64url
     * @param string $auth user agent auth secret, b64url
     * @return string request body (header + ciphertext)
     */
    public static function encrypt(string $payload, string $p256dh, string $auth): string {
        $uapublic = vapid::b64url_decode($p256dh);
        $authsecret = vapid::b64url_decode($auth);
        if (strlen($payload) > self::RECORD_SIZE - 17 - 86) {
            throw new \invalid_parameter_exception('Push payload too large');
        }

        // Ephemeral application server key pair.
        $local = vapid::generate_raw();
        $aspublic = $local['publickey'];
        $peer = openssl_pkey_get_public(vapid::public_pem($uapublic));
        if ($peer === false) {
            throw new \invalid_parameter_exception('Invalid subscription key');
        }
        $ecdhsecret = openssl_pkey_derive($peer, $local['key'], 32);
        if ($ecdhsecret === false) {
            throw new \coding_exception('ECDH failed: ' . openssl_error_string());
        }

        // RFC 8291, section 3.4.
        $keyinfo = "WebPush: info\x00" . $uapublic . $aspublic;
        $ikm = hash_hkdf('sha256', $ecdhsecret, 32, $keyinfo, $authsecret);
        $salt = random_bytes(16);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\x00", $salt);

        // Single record: payload followed by the padding delimiter 0x02 (last record).
        $tag = '';
        $ciphertext = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($ciphertext === false) {
            throw new \coding_exception('AES-GCM encryption failed');
        }
        $header = $salt . pack('N', self::RECORD_SIZE) . chr(strlen($aspublic)) . $aspublic;
        return $header . $ciphertext . $tag;
    }
}
