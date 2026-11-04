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
 * Generate VAPID keys for Web Push and store them in the plugin settings.
 *
 * Usage: php local/gopilot/cli/generate_vapid.php [--subject=mailto:admin@example.com] [--force]
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params(
    ['subject' => '', 'force' => false, 'help' => false],
    ['s' => 'subject', 'f' => 'force', 'h' => 'help']
);

if ($options['help']) {
    cli_writeln("Generate VAPID keys for GoPilot Web Push.

Options:
  -s, --subject=STRING  Contact (mailto: or https: URL), stored as VAPID subject
  -f, --force           Replace existing keys (existing subscriptions stop working)
  -h, --help            Print this help");
    exit(0);
}

if (\local_gopilot\push\sender::is_enabled() && !$options['force']) {
    cli_writeln('VAPID keys exist already. Use --force to replace them.');
    exit(0);
}

$keys = \local_gopilot\push\vapid::generate_keys();
set_config('vapidpublickey', $keys['publickey'], 'local_gopilot');
set_config('vapidprivatekey', $keys['privatekey'], 'local_gopilot');
if ($options['subject'] !== '') {
    set_config('vapidsubject', $options['subject'], 'local_gopilot');
}
cli_writeln('VAPID keys stored. Public key: ' . $keys['publickey']);
