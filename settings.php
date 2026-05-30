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
 * Admin settings and links for Education tutor submissions.
 *
 * @package    local_edtutor
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_edtutor', get_string('pluginname', 'local_edtutor'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configtextarea(
        'local_edtutor/supportstaff',
        get_string('settings:supportstaff', 'local_edtutor'),
        get_string('settings:supportstaff_desc', 'local_edtutor'),
        '',
        PARAM_RAW
    ));

    $settings->add(new admin_setting_configtext(
        'local_edtutor/supportcourseid',
        get_string('settings:supportcourseid', 'local_edtutor'),
        get_string('settings:supportcourseid_desc', 'local_edtutor'),
        '',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_edtutor/roleshortname',
        get_string('settings:roleshortname', 'local_edtutor'),
        get_string('settings:roleshortname_desc', 'local_edtutor'),
        'edtutorsubmit',
        PARAM_ALPHANUMEXT
    ));

    // Admin menu links to the management pages (each page also enforces its own capability,
    // so managers and support staff can reach them directly by URL too).
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_edtutor_allocations',
        get_string('manageallocations', 'local_edtutor'),
        new moodle_url('/local/edtutor/allocations.php'),
        'local/edtutor:manageallocations'
    ));

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_edtutor_queue',
        get_string('submissionqueue', 'local_edtutor'),
        new moodle_url('/local/edtutor/queue.php'),
        'local/edtutor:processsubmissions'
    ));
}
