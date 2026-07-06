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

    if ($oldversion < 2026070602) {
        // Store the assessments a tutor has hidden for a student on their dashboard.
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_edtutor_hidden');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('tutorid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('tutorid', XMLDB_KEY_FOREIGN, ['tutorid'], 'user', ['id']);
        $table->add_key('studentid', XMLDB_KEY_FOREIGN, ['studentid'], 'user', ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('cmid', XMLDB_KEY_FOREIGN, ['cmid'], 'course_modules', ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

        $table->add_index('tutorid-studentid-cmid', XMLDB_INDEX_UNIQUE, ['tutorid', 'studentid', 'cmid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026070602, 'local', 'edtutor');
    }

    return true;
}
