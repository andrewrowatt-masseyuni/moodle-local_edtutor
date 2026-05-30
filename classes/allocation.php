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

use core\persistent;

/**
 * Persistent model for a tutor to student allocation.
 *
 * @package    local_edtutor
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class allocation extends persistent {
    /** @var string The table name. */
    const TABLE = 'local_edtutor_allocation';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'tutorid' => [
                'type' => PARAM_INT,
            ],
            'studentid' => [
                'type' => PARAM_INT,
            ],
        ];
    }

    /**
     * Whether the given student is already allocated to the given tutor.
     *
     * @param int $tutorid
     * @param int $studentid
     * @return bool
     */
    public static function allocation_exists(int $tutorid, int $studentid): bool {
        return self::record_exists_select('tutorid = :tutorid AND studentid = :studentid', [
            'tutorid' => $tutorid,
            'studentid' => $studentid,
        ]);
    }
}
