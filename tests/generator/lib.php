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
 * Test data generator for local_edtutor.
 *
 * @package    local_edtutor
 * @category   test
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_edtutor_generator extends component_generator_base {
    /**
     * Create a tutor to student allocation.
     *
     * @param array $record Must contain tutorid and studentid.
     * @return \local_edtutor\allocation
     */
    public function create_allocation(array $record): \local_edtutor\allocation {
        if (empty($record['tutorid'])) {
            throw new coding_exception('A tutorid is required to create an allocation.');
        }
        if (empty($record['studentid'])) {
            throw new coding_exception('A studentid is required to create an allocation.');
        }
        $allocation = new \local_edtutor\allocation(0, (object)[
            'tutorid' => $record['tutorid'],
            'studentid' => $record['studentid'],
        ]);
        $allocation->create();
        return $allocation;
    }

    /**
     * Create an on-behalf submission record (escalated by default).
     *
     * @param array $record Must contain cmid; may contain studentid, tutorid, onlinetext, status, failurereason.
     * @return \local_edtutor\submission
     */
    public function create_submission(array $record): \local_edtutor\submission {
        if (empty($record['cmid'])) {
            throw new coding_exception('An activity is required to create a submission.');
        }
        $cm = get_coursemodule_from_id('assign', $record['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        $submission = new \local_edtutor\submission(0, (object)[
            'studentid' => $record['studentid'] ?? 0,
            'tutorid' => $record['tutorid'] ?? 0,
            'courseid' => $cm->course,
            'cmid' => $cm->id,
            'assignid' => $cm->instance,
            'contextid' => $context->id,
            'onlinetext' => $record['onlinetext'] ?? 'Seeded submission text',
            'onlinetextformat' => FORMAT_HTML,
            'status' => $record['status'] ?? \local_edtutor\submission::STATUS_ESCALATED,
            'failurereason' => $record['failurereason'] ?? 'Seeded for testing',
        ]);
        $submission->create();
        return $submission;
    }
}
