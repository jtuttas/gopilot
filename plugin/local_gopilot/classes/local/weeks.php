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

use core_date;
use DateTimeImmutable;
use moodle_exception;

/**
 * Helpers for planning weeks ("YYYY-MM-DD" of the Monday, server timezone).
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class weeks {
    /**
     * Server timezone used for all week calculations.
     *
     * @return \DateTimeZone
     */
    protected static function tz(): \DateTimeZone {
        return core_date::get_server_timezone_object();
    }

    /**
     * Whether the string is a well-formed date of a Monday.
     *
     * @param string $week
     * @return bool
     */
    public static function is_valid(string $week): bool {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $week)) {
            return false;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $week, self::tz());
        return $date !== false && $date->format('Y-m-d') === $week && $date->format('N') === '1';
    }

    /**
     * Throw "invalidweek" unless the week string is a valid Monday.
     *
     * @param string $week
     * @return string the week
     * @throws moodle_exception
     */
    public static function require_valid(string $week): string {
        if (!self::is_valid($week)) {
            throw new moodle_exception('invalidweek', 'local_gopilot', '', $week);
        }
        return $week;
    }

    /**
     * Monday 00:00:00 of the week as timestamp.
     *
     * @param string $week
     * @return int
     */
    public static function start(string $week): int {
        self::require_valid($week);
        return DateTimeImmutable::createFromFormat('!Y-m-d', $week, self::tz())->getTimestamp();
    }

    /**
     * Sunday 23:59:59 of the week as timestamp.
     *
     * @param string $week
     * @return int
     */
    public static function end(string $week): int {
        self::require_valid($week);
        $monday = DateTimeImmutable::createFromFormat('!Y-m-d', $week, self::tz());
        return $monday->modify('+7 days')->getTimestamp() - 1;
    }

    /**
     * Week (Monday) containing the given timestamp.
     *
     * @param int $time
     * @return string
     */
    public static function from_time(int $time): string {
        $date = (new DateTimeImmutable('@' . $time))->setTimezone(self::tz());
        $offset = (int)$date->format('N') - 1;
        return $date->modify('-' . $offset . ' days')->format('Y-m-d');
    }

    /**
     * Current week.
     *
     * @param int|null $now
     * @return string
     */
    public static function current(?int $now = null): string {
        return self::from_time($now ?? time());
    }

    /**
     * Week shifted by a number of weeks.
     *
     * @param string $week
     * @param int $weeks positive or negative
     * @return string
     */
    public static function add(string $week, int $weeks): string {
        self::require_valid($week);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $week, self::tz());
        return $date->modify(($weeks >= 0 ? '+' : '') . (7 * $weeks) . ' days')->format('Y-m-d');
    }

    /**
     * Whether the week is closed (its Sunday 23:59:59 has passed).
     *
     * @param string $week
     * @param int|null $now
     * @return bool
     */
    public static function is_closed(string $week, ?int $now = null): bool {
        return self::end($week) < ($now ?? time());
    }

    /**
     * The most recently closed week.
     *
     * @param int|null $now
     * @return string
     */
    public static function last_closed(?int $now = null): string {
        return self::add(self::current($now), -1);
    }

    /**
     * ISO calendar week number of the week.
     *
     * @param string $week
     * @return int
     */
    public static function number(string $week): int {
        self::require_valid($week);
        return (int)DateTimeImmutable::createFromFormat('!Y-m-d', $week, self::tz())->format('W');
    }
}
