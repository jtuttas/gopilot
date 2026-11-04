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
 * Admin settings of local_gopilot (Web Push, app URL).
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_gopilot', new lang_string('pluginname', 'local_gopilot'));
    $ADMIN->add('localplugins', $settings);

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configtext(
            'local_gopilot/appurl',
            new lang_string('appurl', 'local_gopilot'),
            new lang_string('appurl_desc', 'local_gopilot'),
            '',
            PARAM_URL
        ));

        $settings->add(new admin_setting_heading(
            'local_gopilot/pushheading',
            new lang_string('pushheading', 'local_gopilot'),
            new lang_string('pushheading_desc', 'local_gopilot')
        ));
        $settings->add(new admin_setting_configtext(
            'local_gopilot/vapidpublickey',
            new lang_string('vapidpublickey', 'local_gopilot'),
            new lang_string('vapidpublickey_desc', 'local_gopilot'),
            '',
            PARAM_RAW_TRIMMED,
            90
        ));
        $settings->add(new admin_setting_configpasswordunmask(
            'local_gopilot/vapidprivatekey',
            new lang_string('vapidprivatekey', 'local_gopilot'),
            new lang_string('vapidprivatekey_desc', 'local_gopilot'),
            ''
        ));
        $settings->add(new admin_setting_configtext(
            'local_gopilot/vapidsubject',
            new lang_string('vapidsubject', 'local_gopilot'),
            new lang_string('vapidsubject_desc', 'local_gopilot'),
            '',
            PARAM_RAW_TRIMMED
        ));
    }
}
