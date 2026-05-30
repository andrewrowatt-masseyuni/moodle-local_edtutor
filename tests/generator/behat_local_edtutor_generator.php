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
 * Behat data generator for local_edtutor.
 *
 * @package    local_edtutor
 * @category   test
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_edtutor_generator extends behat_generator_base {
    /**
     * Get a list of the entities that Behat can create using the generator step.
     *
     * @return array the list of creatable entities.
     */
    protected function get_creatable_entities(): array {
        return [
            'allocations' => [
                'singular' => 'allocation',
                'datagenerator' => 'allocation',
                'required' => ['tutor', 'student'],
                'switchids' => ['tutor' => 'tutorid', 'student' => 'studentid'],
            ],
            'submissions' => [
                'singular' => 'submission',
                'datagenerator' => 'submission',
                'required' => ['student', 'tutor', 'activity'],
                'switchids' => ['student' => 'studentid', 'tutor' => 'tutorid', 'activity' => 'cmid'],
            ],
        ];
    }

    /**
     * Resolve a tutor username to its user id.
     *
     * @param string $username
     * @return int
     */
    protected function get_tutor_id(string $username): int {
        return $this->get_user_id($username);
    }

    /**
     * Resolve a student username to its user id.
     *
     * @param string $username
     * @return int
     */
    protected function get_student_id(string $username): int {
        return $this->get_user_id($username);
    }
}
