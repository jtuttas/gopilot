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

namespace local_gopilot;

/**
 * Installation tests for local_gopilot.
 *
 * @package    local_gopilot
 * @category   test
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class install_test extends \advanced_testcase {

    /**
     * The three GoPilot capabilities are installed.
     */
    public function test_capabilities_installed(): void {
        foreach (['use', 'coach', 'configure'] as $name) {
            $this->assertNotEmpty(get_capability_info('local/gopilot:' . $name), $name);
        }
    }

    /**
     * The web service "gopilot" exists and is enabled.
     */
    public function test_service_installed(): void {
        global $DB;
        $service = $DB->get_record('external_services', ['shortname' => 'gopilot', 'component' => 'local_gopilot']);
        $this->assertNotFalse($service);
        $this->assertEquals(1, $service->enabled);
    }
}
