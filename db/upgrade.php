<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Upgrade steps for Education tutor submissions.
 *
 * @package    local_edtutor
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the local_edtutor plugin.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 */
function xmldb_local_edtutor_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2026070400) {
        // This plugin's capabilities are not re-registered until update_capabilities()
        // runs from upgrade_component_updated(), which is *after* this upgrade script.
        // Register them now so assign_capability() can find local/edtutor:setpreferences.
        update_capabilities('local_edtutor');

        // Grant the new capability to the configured education tutor role, matching
        // what a fresh install sets up.
        $shortname = trim((string)get_config('local_edtutor', 'roleshortname'));
        if ($shortname !== '') {
            $roleid = $DB->get_field('role', 'id', ['shortname' => $shortname]);
            if ($roleid) {
                assign_capability(
                    'local/edtutor:setpreferences',
                    CAP_ALLOW,
                    $roleid,
                    context_system::instance()->id
                );
            }
        }

        upgrade_plugin_savepoint(true, 2026070400, 'local', 'edtutor');
    }

    return true;
}
