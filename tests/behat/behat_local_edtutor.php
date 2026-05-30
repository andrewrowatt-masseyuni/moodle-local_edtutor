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

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Behat page resolvers for local_edtutor.
 *
 * @package    local_edtutor
 * @category   test
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_edtutor extends behat_base {
    /**
     * Convert page names to URLs for steps like 'When I am on the "local_edtutor > [page]" page'.
     *
     * Recognised page names are:
     * | Page        | Description                                  |
     * | tutor area  | The tutor landing page                       |
     * | submit      | Submit on behalf of a student                |
     * | allocations | Manage tutor to student allocations          |
     * | queue       | Submissions awaiting manual completion       |
     *
     * @param string $page name of the page, with the component name removed.
     * @return moodle_url the corresponding URL.
     * @throws Exception with a meaningful error message if the specified page cannot be found.
     */
    protected function resolve_page_url(string $page): moodle_url {
        switch (strtolower($page)) {
            case 'tutor area':
                return new moodle_url('/local/edtutor/index.php');
            case 'submit':
                return new moodle_url('/local/edtutor/submit.php');
            case 'allocations':
                return new moodle_url('/local/edtutor/allocations.php');
            case 'queue':
                return new moodle_url('/local/edtutor/queue.php');
            default:
                throw new Exception('Unrecognised local_edtutor page type "' . $page . '."');
        }
    }
}
