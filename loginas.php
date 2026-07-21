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
 * Log in as an allocated student at site level, or log out of a login-as session.
 *
 * Authorisation is based on the tutor-student allocation (plus the
 * local/edtutor:loginas capability), not moodle/user:loginas. For security
 * reasons the only way out of a login-as session is a full logout followed
 * by re-authentication, exactly as in core.
 *
 * @package    local_edtutor
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$userid = optional_param('userid', 0, PARAM_INT); // 0 means log out of a login-as session.
$courseid = optional_param('courseid', 0, PARAM_INT); // Optional course to land in.

$PAGE->set_url(new moodle_url('/local/edtutor/loginas.php', ['userid' => $userid]));
$PAGE->set_context(context_system::instance());

// For security reasons the only way out of a login-as session is a full
// logout followed by re-authentication, mirroring course/loginas.php.
if (\core\session\manager::is_loggedinas()) {
    require_sesskey();
    require_logout();
    // The session is destroyed: no notifications or wantsurl here.
    // dashboard.php's require_login() sets wantsurl in the new session.
    redirect(new moodle_url('/local/edtutor/dashboard.php'));
}

require_login(null, false);
require_sesskey();

if ($userid === 0) {
    // Stale link while not in a login-as session: nothing to do.
    redirect(new moodle_url('/local/edtutor/dashboard.php'));
}

\local_edtutor\loginas::loginas_student($userid);
if ($courseid) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
}
redirect(new moodle_url('/my/courses.php'));
