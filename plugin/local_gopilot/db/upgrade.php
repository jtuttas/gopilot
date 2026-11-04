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
 * Upgrade steps for local_gopilot.
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_gopilot_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026110401) {
        // Version 0.1 had no tables: create all tables of install.xml that are missing.
        $file = __DIR__ . '/install.xml';
        $xmldb = new xmldb_file($file);
        $xmldb->loadXMLStructure();
        foreach ($xmldb->getStructure()->getTables() as $table) {
            if (!$dbman->table_exists($table->getName())) {
                $dbman->install_one_table_from_xmldb_file($file, $table->getName());
            }
        }
        upgrade_plugin_savepoint(true, 2026110401, 'local', 'gopilot');
    }

    return true;
}
