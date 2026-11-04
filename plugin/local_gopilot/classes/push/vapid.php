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
 * VAPID (RFC 8292) keys and ES256 JWTs, and P-256 key helpers built on the PHP openssl extension.
 *
 * Keys are stored like in other Web Push libraries: public key = uncompressed point (65 bytes),
 * private key = scalar d (32 bytes), both Base64-URL without padding.
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class vapid {
    /** @var string DER prefix of a P-256 SubjectPublicKeyInfo before the 65-byte point. */
    const SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    /**
     * Base64-URL encoding without padding.
     *
     * @param string $data
     * @return string
     */
    public static function b64url_encode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64-URL decoding (padding optional).
     *
     * @param string $data
     * @return string
     */
    public static function b64url_decode(string $data): string {
        $data = strtr(trim($data), '-_', '+/');
        $pad = strlen($data) % 4;
        if ($pad) {
            $data .= str_repeat('=', 4 - $pad);
        }
        return (string)base64_decode($data);
    }

    /**
     * Generate a new P-256 key pair.
     *
     * @return array ['publickey' => raw 65 bytes, 'privatekey' => raw 32 bytes, 'key' => OpenSSL key]
     */
    public static function generate_raw(): array {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if ($key === false) {
            throw new \coding_exception('openssl_pkey_new failed: ' . openssl_error_string());
        }
        $details = openssl_pkey_get_details($key);
        return [
            'publickey' => "\x04" . self::pad32($details['ec']['x']) . self::pad32($details['ec']['y']),
            'privatekey' => self::pad32($details['ec']['d']),
            'key' => $key,
        ];
    }

    /**
     * Generate VAPID keys encoded for storage.
     *
     * @return array ['publickey' => b64url, 'privatekey' => b64url]
     */
    public static function generate_keys(): array {
        $raw = self::generate_raw();
        return [
            'publickey' => self::b64url_encode($raw['publickey']),
            'privatekey' => self::b64url_encode($raw['privatekey']),
        ];
    }

    /**
     * Left-pad a big-endian integer to 32 bytes.
     *
     * @param string $value
     * @return string
     */
    protected static function pad32(string $value): string {
        return str_pad(ltrim($value, "\x00"), 32, "\x00", STR_PAD_LEFT);
    }

    /**
     * PEM of a public key given as raw uncompressed point.
     *
     * @param string $point 65 bytes
     * @return string
     */
    public static function public_pem(string $point): string {
        if (strlen($point) !== 65 || $point[0] !== "\x04") {
            throw new \invalid_parameter_exception('Invalid P-256 public key');
        }
        $der = hex2bin(self::SPKI_PREFIX) . $point;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    /**
     * PEM (SEC1) of a private key given as raw scalar and public point.
     *
     * @param string $d 32 bytes
     * @param string $point 65 bytes
     * @return string
     */
    public static function private_pem(string $d, string $point): string {
        if (strlen($d) !== 32 || strlen($point) !== 65) {
            throw new \invalid_parameter_exception('Invalid P-256 key pair');
        }
        $der = hex2bin('30770201010420') . $d . hex2bin('a00a06082a8648ce3d030107a144034200') . $point;
        return "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") .
            "-----END EC PRIVATE KEY-----\n";
    }

    /**
     * Convert a DER ECDSA signature to the 64-byte raw form (r || s) used by JWS.
     *
     * @param string $der
     * @return string
     */
    public static function der_to_raw(string $der): string {
        $pos = 2;
        if (ord($der[1]) & 0x80) {
            $pos += ord($der[1]) & 0x7f;
        }
        $parts = [];
        for ($i = 0; $i < 2; $i++) {
            if (ord($der[$pos]) !== 0x02) {
                throw new \coding_exception('Invalid DER signature');
            }
            $len = ord($der[$pos + 1]);
            $parts[] = self::pad32(substr($der, $pos + 2, $len));
            $pos += 2 + $len;
        }
        return $parts[0] . $parts[1];
    }

    /**
     * Convert a 64-byte raw signature back to DER (used to verify).
     *
     * @param string $raw
     * @return string
     */
    public static function raw_to_der(string $raw): string {
        $der = '';
        foreach ([substr($raw, 0, 32), substr($raw, 32, 32)] as $int) {
            $int = ltrim($int, "\x00");
            if ($int === '' || (ord($int[0]) & 0x80)) {
                $int = "\x00" . $int;
            }
            $der .= "\x02" . chr(strlen($int)) . $int;
        }
        return "\x30" . chr(strlen($der)) . $der;
    }

    /**
     * Create the signed VAPID JWT for an endpoint.
     *
     * @param string $endpoint push endpoint URL
     * @param string $subject mailto: or https: contact
     * @param string $publickey b64url
     * @param string $privatekey b64url
     * @param int|null $expires default now + 12 h
     * @return string JWT
     */
    public static function create_jwt(
        string $endpoint,
        string $subject,
        string $publickey,
        string $privatekey,
        ?int $expires = null
    ): string {
        $parts = parse_url($endpoint);
        $audience = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $header = self::b64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = self::b64url_encode(json_encode([
            'aud' => $audience,
            'exp' => $expires ?? time() + 12 * HOURSECS,
            'sub' => $subject,
        ], JSON_UNESCAPED_SLASHES));
        $input = $header . '.' . $claims;
        $pem = self::private_pem(self::b64url_decode($privatekey), self::b64url_decode($publickey));
        $key = openssl_pkey_get_private($pem);
        if ($key === false || !openssl_sign($input, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new \coding_exception('Could not sign VAPID JWT: ' . openssl_error_string());
        }
        return $input . '.' . self::b64url_encode(self::der_to_raw($signature));
    }
}
