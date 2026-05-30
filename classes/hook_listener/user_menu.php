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
 * Hook listener for extending the user menu with a tutor submission link.
 *
 * @package    local_edtutor
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_menu {
    /**
     * Add a "Submit on behalf" link to the user menu.
     *
     * Shown when the current user is a tutor and the current course has a
     * student enrolled who is allocated to them.
     *
     * @param \core_user\hook\extend_user_menu $hook The user menu hook.
     */
    public static function extend_user_menu(\core_user\hook\extend_user_menu $hook): void {
        global $PAGE, $USER;

        $course = $PAGE->course;
        if (!$course || $course->id == SITEID) {
            return;
        }

        // The user must be able to submit on behalf of an allocated student.
        if (!has_capability('local/edtutor:submit', \context_system::instance())) {
            return;
        }

        // At least one allocated student must be enrolled in the current course.
        if (empty(manager::get_allocated_students_in_course((int)$USER->id, $course->id))) {
            return;
        }

        $item = new \stdClass();
        $item->itemtype = 'link';
        $item->url = new \moodle_url('/local/edtutor/submit.php', ['courseid' => $course->id]);
        $item->title = get_string('submitonbehalf', 'local_edtutor');
        $item->titleidentifier = 'submitonbehalf,local_edtutor';
        $item->pix = 'i/users';
        $hook->add_navitem($item);
    }
}
