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
 * Log in as an allocated student at site level, or return to the tutor's own account.
 *
 * Authorisation is based on the tutor-student allocation (plus the
 * local/edtutor:loginas capability), not moodle/user:loginas. Switching
 * between students is supported without logging out.
 *
 * @package    local_edtutor
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$userid = optional_param('userid', 0, PARAM_INT); // 0 means return to my own account.

$PAGE->set_url(new moodle_url('/local/edtutor/loginas.php', ['userid' => $userid]));
$PAGE->set_context(context_system::instance());

require_login(null, false);
require_sesskey();

if ($userid === 0) {
    if (\core\session\manager::is_loggedinas()) {
        \local_edtutor\loginas::restore_real_user();
        \core\notification::success(get_string('returnedtomyaccount', 'local_edtutor'));
    }
    redirect(new moodle_url('/my/'));
}

\local_edtutor\loginas::loginas_student($userid);
redirect(new moodle_url('/my/courses.php'));
