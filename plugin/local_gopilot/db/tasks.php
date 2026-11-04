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
 * Scheduled tasks of local_gopilot (docs/umsetzungsplan-v1.md, section 2.5).
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        // Fridays 14:00: remind learners without reflection in school weeks.
        'classname' => 'local_gopilot\task\reflection_due',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '14',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '5',
    ],
    [
        // Nightly: recalculate all Ampels and notify on changes to yellow or red.
        'classname' => 'local_gopilot\task\recalculate_ampel',
        'blocking' => 0,
        'minute' => '30',
        'hour' => '2',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    [
        // Shortly after midnight: freeze the completion state of plan items of closed weeks.
        'classname' => 'local_gopilot\task\freeze_weeks',
        'blocking' => 0,
        'minute' => '10',
        'hour' => '0',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
];
