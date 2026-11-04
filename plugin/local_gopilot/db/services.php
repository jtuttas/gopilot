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
 * Web service definitions for local_gopilot (docs/api-v1.md).
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$gopilotfunctions = [
    'get_overview' => ['Courses, current week and own Ampels', 'read'],
    'get_paths' => ['Learning paths of a course', 'read'],
    'get_week' => ['One planning week', 'read'],
    'save_plan' => ['Save weekly goal and planned steps', 'write'],
    'save_reflection' => ['Save the reflection of a week', 'write'],
    'get_biography' => ['Learning biography', 'read'],
    'get_lerndoku' => ['Learners of a course with Ampel (coach)', 'read'],
    'get_learner' => ['Weeks of a learner in a course (coach)', 'read'],
    'add_feedback' => ['Add coaching feedback', 'write'],
    'get_courseconfig' => ['Course configuration', 'read'],
    'save_courseconfig' => ['Save course configuration', 'write'],
    'save_pathmeta' => ['Save path metadata', 'write'],
    'get_moodle_link' => ['One-time link that logs the user into Moodle', 'write'],
    'get_push_config' => ['Web Push configuration', 'read'],
    'register_push' => ['Register a Web Push subscription', 'write'],
    'unregister_push' => ['Remove a Web Push subscription', 'write'],
];

$functions = [];
foreach ($gopilotfunctions as $name => [$description, $type]) {
    $functions['local_gopilot_' . $name] = [
        'classname' => 'local_gopilot\\external\\' . $name,
        'description' => $description,
        'type' => $type,
        'ajax' => true,
    ];
}

$services = [
    'GoPilot' => [
        'shortname' => 'gopilot',
        'functions' => array_merge(array_keys($functions), [
            'core_webservice_get_site_info',
            'core_completion_update_activity_completion_status_manually',
        ]),
        'enabled' => 1,
        'restrictedusers' => 0,
        'downloadfiles' => 0,
        'uploadfiles' => 0,
    ],
];
