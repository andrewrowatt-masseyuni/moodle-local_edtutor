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

namespace local_edtutor;

/**
 * Site-level login-as support for education tutors.
 *
 * Lets a tutor log in as an allocated student with a system-level
 * loginascontext (so they can enter any of the student's courses), and
 * switch directly between allocated students by restoring the tutor's
 * real session before starting the next login-as session.
 *
 * @package    local_edtutor
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class loginas {
    /**
     * Throw unless a tutor is allowed to log in as a student.
     *
     * @param int $realuserid The tutor's real user id (not a logged-in-as identity).
     * @param int $studentid The student to log in as.
     * @throws \moodle_exception When the login-as is not permitted.
     */
    public static function require_can_loginas(int $realuserid, int $studentid): void {
        if (isguestuser($realuserid) || $realuserid == $studentid) {
            throw new \moodle_exception('error:cannotloginas', 'local_edtutor');
        }

        if (!has_capability('local/edtutor:loginas', \context_system::instance(), $realuserid)) {
            throw new \moodle_exception('error:cannotloginas', 'local_edtutor');
        }

        if (!manager::is_allocated($realuserid, $studentid)) {
            throw new \moodle_exception('error:notallocated', 'local_edtutor');
        }

        $student = \core_user::get_user($studentid, '*', MUST_EXIST);
        if ($student->deleted || $student->suspended || isguestuser($student) || is_siteadmin($student)) {
            throw new \moodle_exception('error:cannotloginas', 'local_edtutor');
        }
    }

    /**
     * Restore the real user from a login-as session without logging out.
     *
     * Mirror image of \core\session\manager::loginas(): puts the backed up
     * REALSESSION back as the live session and rebuilds the real user.
     */
    public static function restore_real_user(): void {
        global $SESSION;

        if (!\core\session\manager::is_loggedinas()) {
            return;
        }

        if (empty($_SESSION['REALSESSION']) || empty($_SESSION['REALUSER'])) {
            // The backups are gone; the only safe recovery is a full logout.
            require_logout();
            redirect(get_login_url());
        }

        $realuser = $_SESSION['REALUSER'];

        // Match the approach of \core\session\manager.
        // phpcs:ignore moodle.NamingConventions.ValidVariableName.VariableNameLowerCase
        $studentid = $GLOBALS['USER']->id;

        // Match the approach of \core\session\manager.
        // phpcs:ignore moodle.NamingConventions.ValidVariableName.VariableNameLowerCase
        $GLOBALS['SESSION'] = $_SESSION['REALSESSION'];

        // Match the approach of \core\session\manager.
        // phpcs:ignore moodle.NamingConventions.ValidVariableName.VariableNameLowerCase
        $_SESSION['SESSION'] =& $GLOBALS['SESSION'];
        unset($_SESSION['REALSESSION']);
        unset($_SESSION['REALUSER']);

        $user = get_complete_user_data('id', $realuser->id);
        if (!$user) {
            require_logout();
            redirect(get_login_url());
        }

        // Keep the original sesskey so links rendered before the login-as
        // session (e.g. in other tabs) remain valid.
        if (isset($realuser->sesskey)) {
            $user->sesskey = $realuser->sesskey;
        }

        \core\session\manager::set_user($user);

        // A pre-login-as wantsurl must not hijack the next require_login().
        unset($SESSION->wantsurl);

        $event = event\loginas_returned::create([
            'context' => \context_system::instance(),
            'relateduserid' => $studentid,
        ]);
        $event->trigger();
    }

    /**
     * Log in as an allocated student at site level.
     *
     * If already logged in as another student, the real user is restored
     * first so the tutor can switch students without logging out.
     *
     * @param int $studentid The student to log in as.
     * @throws \moodle_exception When the login-as is not permitted.
     */
    public static function loginas_student(int $studentid): void {
        global $USER;

        $realuser = \core\session\manager::get_realuser();
        self::require_can_loginas((int)$realuser->id, $studentid);

        if (\core\session\manager::is_loggedinas()) {
            if ((int)$USER->id === $studentid) {
                return;
            }
            self::restore_real_user();
        }

        \core\session\manager::loginas($studentid, \context_system::instance());
        \core\notification::info(get_string('sessionforceclean', 'core'));
    }
}
