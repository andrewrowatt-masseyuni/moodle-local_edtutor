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

namespace local_edtutor\hook_listener;

use local_edtutor\manager;

/**
 * Hook listener for extending the user menu with tutor actions.
 *
 * @package    local_edtutor
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_menu {
    /**
     * Add tutor entries to the user menu.
     *
     * @param \core_user\hook\extend_user_menu $hook The user menu hook.
     */
    public static function extend_user_menu(\core_user\hook\extend_user_menu $hook): void {
        self::add_submit_link($hook);
        self::add_loginas_items($hook);
    }

    /**
     * Add a "Submit on behalf" link to the user menu.
     *
     * Shown on every page for tutors. When the tutor is in a course with an
     * allocated student enrolled, the course is preselected on the submit form.
     *
     * @param \core_user\hook\extend_user_menu $hook The user menu hook.
     */
    private static function add_submit_link(\core_user\hook\extend_user_menu $hook): void {
        global $PAGE, $USER;

        // The user must be able to submit on behalf of an allocated student.
        if (!has_capability('local/edtutor:submit', \context_system::instance())) {
            return;
        }

        $params = [];
        $course = $PAGE->course;
        if ($course && $course->id != SITEID
                && !empty(manager::get_allocated_students_in_course((int)$USER->id, $course->id))) {
            $params['courseid'] = $course->id;
        }

        $divider = new \stdClass();
        $divider->itemtype = 'divider';
        $divider->titleidentifier = 'divider,local_edtutor';
        $hook->add_navitem($divider);

        $item = new \stdClass();
        $item->itemtype = 'link';
        $item->url = new \moodle_url('/local/edtutor/submit.php', $params);
        $item->title = get_string('submitonbehalf', 'local_edtutor');
        $item->titleidentifier = 'submitonbehalf,local_edtutor';
        $item->pix = 'i/users';
        $hook->add_navitem($item);
    }

    /**
     * Add a "Login as ..." link for each of the tutor's allocated students.
     *
     * Shown on every page. While logged in as a student the entries are
     * authorised against the tutor's real account, so the tutor can switch
     * to another student or return to their own account without logging out.
     *
     * @param \core_user\hook\extend_user_menu $hook The user menu hook.
     */
    private static function add_loginas_items(\core_user\hook\extend_user_menu $hook): void {
        global $USER;

        $realuser = \core\session\manager::get_realuser();
        if (isguestuser($realuser)) {
            return;
        }

        if (!has_capability('local/edtutor:submit', \context_system::instance(), $realuser->id)) {
            return;
        }

        $students = manager::get_allocated_students((int)$realuser->id);
        if (empty($students)) {
            return;
        }

        $loggedinas = \core\session\manager::is_loggedinas();



        if ($loggedinas) {
            $divider = new \stdClass();
            $divider->itemtype = 'divider';
            $divider->titleidentifier = 'divider,local_edtutor';
            $hook->add_navitem($divider);
            
            $item = new \stdClass();
            $item->itemtype = 'link';
            $item->url = new \moodle_url('/local/edtutor/loginas.php', ['userid' => 0, 'sesskey' => sesskey()]);
            $item->title = get_string('returntomyaccount', 'local_edtutor');
            $item->titleidentifier = 'returntomyaccount,local_edtutor';
            $item->pix = 'i/return';
            $hook->add_navitem($item);
        }

        foreach ($students as $student) {
            $title = get_string('loginasstudentname', 'local_edtutor',
                fullname($student) . ' (' . $student->username . ')');
            if ($loggedinas && $USER->id == $student->id) {
                $title .= ' ✓';
            }

            $item = new \stdClass();
            $item->itemtype = 'link';
            $item->url = new \moodle_url('/local/edtutor/loginas.php',
                ['userid' => $student->id, 'sesskey' => sesskey()]);
            $item->title = $title;
            $item->titleidentifier = 'loginasstudentname,local_edtutor';
            $item->pix = 'i/user';
            $hook->add_navitem($item);
        }
    }
}
