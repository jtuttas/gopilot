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

use moodle_exception;

/**
 * Course settings: teaching weeks and Ampel thresholds.
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class courseconfig {
    /** @var array Default thresholds (ADR 0003). */
    const DEFAULTS = [
        'plangreen' => 70,
        'planyellow' => 40,
        'backlogyellow' => 2,
        'backlogred' => 4,
        'reflectionyellow' => 1,
        'reflectionred' => 2,
    ];

    /** @var int */
    protected $courseid;

    /** @var string[] */
    protected $teachingweeks = [];

    /** @var array|null custom thresholds, null = defaults */
    protected $thresholds = null;

    /**
     * Load the configuration of a course.
     *
     * @param int $courseid
     */
    public function __construct(int $courseid) {
        global $DB;
        $this->courseid = $courseid;
        $record = $DB->get_record('local_gopilot_courseconfig', ['courseid' => $courseid]);
        if ($record) {
            $weeks = json_decode((string)$record->teachingweeks, true);
            $this->teachingweeks = is_array($weeks) ? array_values(array_filter($weeks, 'is_string')) : [];
            $thresholds = json_decode((string)$record->thresholds, true);
            if (is_array($thresholds) && $thresholds) {
                $this->thresholds = array_map('intval', array_intersect_key($thresholds, self::DEFAULTS)) + self::DEFAULTS;
            }
        }
    }

    /**
     * Teaching weeks (Mondays), sorted ascending.
     *
     * @return string[]
     */
    public function get_teachingweeks(): array {
        $weeks = $this->teachingweeks;
        sort($weeks);
        return $weeks;
    }

    /**
     * Whether the week is a teaching week of this course.
     *
     * @param string $week
     * @return bool
     */
    public function is_schoolweek(string $week): bool {
        return in_array($week, $this->teachingweeks, true);
    }

    /**
     * Whether the defaults are used.
     *
     * @return bool
     */
    public function uses_defaults(): bool {
        return $this->thresholds === null;
    }

    /**
     * Effective thresholds.
     *
     * @return array
     */
    public function get_thresholds(): array {
        return $this->thresholds ?? self::DEFAULTS;
    }

    /**
     * Validate and save a configuration.
     *
     * @param int $courseid
     * @param string[] $teachingweeks
     * @param bool $usedefaults
     * @param array $thresholds ignored if $usedefaults
     * @return self the reloaded configuration
     * @throws moodle_exception invalidweek, invalidthresholds
     */
    public static function save(int $courseid, array $teachingweeks, bool $usedefaults, array $thresholds): self {
        global $DB;
        foreach ($teachingweeks as $week) {
            weeks::require_valid($week);
        }
        $teachingweeks = array_values(array_unique($teachingweeks));
        sort($teachingweeks);

        $json = '';
        if (!$usedefaults) {
            $values = [];
            foreach (self::DEFAULTS as $key => $default) {
                $values[$key] = isset($thresholds[$key]) ? (int)$thresholds[$key] : $default;
            }
            if (
                $values['planyellow'] >= $values['plangreen'] || $values['backlogyellow'] >= $values['backlogred']
                    || $values['reflectionyellow'] >= $values['reflectionred']
            ) {
                throw new moodle_exception('invalidthresholds', 'local_gopilot');
            }
            foreach ($values as $value) {
                if ($value < 0) {
                    throw new moodle_exception('invalidthresholds', 'local_gopilot');
                }
            }
            $json = json_encode($values);
        }

        $record = $DB->get_record('local_gopilot_courseconfig', ['courseid' => $courseid]);
        $data = (object)[
            'courseid' => $courseid,
            'teachingweeks' => json_encode($teachingweeks),
            'thresholds' => $json,
            'timemodified' => time(),
        ];
        if ($record) {
            $data->id = $record->id;
            $DB->update_record('local_gopilot_courseconfig', $data);
        } else {
            $DB->insert_record('local_gopilot_courseconfig', $data);
        }
        return new self($courseid);
    }
}
