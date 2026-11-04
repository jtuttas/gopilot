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

namespace local_gopilot\external;

use context_user;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;

/**
 * local_gopilot_register_push: store a Web Push subscription of the current user.
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class register_push extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'endpoint' => new external_value(PARAM_RAW, 'Push endpoint (https URL)'),
            'p256dh' => new external_value(PARAM_RAW, 'Public key of the subscription, Base64-URL'),
            'auth' => new external_value(PARAM_RAW, 'Auth secret of the subscription, Base64-URL'),
        ]);
    }

    /**
     * Register the subscription; an endpoint belongs to at most one user.
     *
     * @param string $endpoint
     * @param string $p256dh
     * @param string $auth
     * @return array
     */
    public static function execute(string $endpoint, string $p256dh, string $auth): array {
        global $DB, $USER;
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['endpoint' => $endpoint, 'p256dh' => $p256dh, 'auth' => $auth]
        );
        self::validate_context(context_user::instance($USER->id));
        if (strpos($params['endpoint'], 'https://') !== 0) {
            throw new invalid_parameter_exception('The endpoint must be an https URL');
        }
        foreach (['p256dh', 'auth'] as $key) {
            if (!preg_match('/^[A-Za-z0-9_\-]+=*$/', $params[$key]) || strlen($params[$key]) > 255) {
                throw new invalid_parameter_exception('Invalid ' . $key);
            }
        }
        $hash = sha1($params['endpoint']);
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('local_gopilot_pushsub', ['endpointhash' => $hash]);
        $DB->insert_record('local_gopilot_pushsub', (object)[
            'userid' => $USER->id,
            'endpoint' => $params['endpoint'],
            'endpointhash' => $hash,
            'p256dh' => $params['p256dh'],
            'auth' => $params['auth'],
            'timecreated' => time(),
        ]);
        $transaction->allow_commit();
        return ['success' => true];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Registered'),
        ]);
    }
}
