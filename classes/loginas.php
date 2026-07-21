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
 * loginascontext, so they can enter any of the student's courses.
 *
 * This exists instead of core's login-as because core is either too powerful
 * (system context impersonates anyone, admin-only) or too narrow (course
 * context is limited to one enrolled course). This class reuses
 * \core\session\manager::loginas() for the session swap and adds only an
 * allocation-gated, least-privilege policy. Exiting the login-as session
 * works exactly as in core: a full logout followed by re-authentication.
 * See docs/loginas-rationale.md for the full rationale.
 *
 * @see \core\session\manager::loginas()
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
     * Log in as an allocated student at site level.
     *
     * Must be called from the tutor's real session. For security reasons the
     * only way out of an existing login-as session is a full logout followed
     * by re-authentication, so this refuses to switch or re-enter while one
     * is active.
     *
     * @param int $studentid The student to log in as.
     * @throws \moodle_exception When the login-as is not permitted.
     */
    public static function loginas_student(int $studentid): void {
        global $USER;

        if (\core\session\manager::is_loggedinas()) {
            throw new \moodle_exception('error:alreadyloggedinas', 'local_edtutor');
        }

        self::require_can_loginas((int)$USER->id, $studentid);

        \core\session\manager::loginas($studentid, \context_system::instance());
        \core\notification::info(get_string('sessionforceclean', 'core'));
    }
}
