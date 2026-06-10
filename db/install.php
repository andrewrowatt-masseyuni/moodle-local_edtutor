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
 * Install-time setup for Education tutor submissions.
 *
 * @package    local_edtutor
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Create the education tutor system roles and assign their capabilities.
 *
 * Runs once on a clean install of the plugin.
 *
 * @return void
 */
function xmldb_local_edtutor_install() {
    global $DB;

    // This plugin's own capabilities are not registered until update_capabilities()
    // is called from upgrade_component_updated(), which runs *after* this install
    // script. Register them now so assign_capability() can find the local/edtutor:*
    // capabilities below (it throws a coding_exception for unknown capabilities).
    update_capabilities('local_edtutor');

    $systemcontext = context_system::instance();

    // Education tutor: submits assignments on behalf of allocated students.
    if (!$DB->record_exists('role', ['shortname' => 'edtutor'])) {
        $tutorroleid = create_role(
            get_string('edtutorrole', 'local_edtutor'),
            'edtutor',
            get_string('edtutorrole_desc', 'local_edtutor')
        );
        set_role_contextlevels($tutorroleid, [CONTEXT_SYSTEM]);
        assign_capability('local/edtutor:submit', CAP_ALLOW, $tutorroleid, $systemcontext->id);
        assign_capability('mod/assign:editothersubmission', CAP_ALLOW, $tutorroleid, $systemcontext->id);
        assign_capability('moodle/site:viewuseridentity', CAP_ALLOW, $tutorroleid, $systemcontext->id);
    }

    // Education tutor manager: manages tutor to student allocations.
    if (!$DB->record_exists('role', ['shortname' => 'edtutormanager'])) {
        $managerroleid = create_role(
            get_string('edtutormanagerrole', 'local_edtutor'),
            'edtutormanager',
            get_string('edtutormanagerrole_desc', 'local_edtutor')
        );
        set_role_contextlevels($managerroleid, [CONTEXT_SYSTEM]);
        assign_capability('local/edtutor:manageallocations', CAP_ALLOW, $managerroleid, $systemcontext->id);
    }

    // Point the tutor role setting at the role we just created so that the
    // allocation pages can find education tutors by role. An admin can still
    // change this later to target a different role.
    if (!get_config('local_edtutor', 'roleshortname')) {
        set_config('roleshortname', 'edtutor', 'local_edtutor');
    }
}
