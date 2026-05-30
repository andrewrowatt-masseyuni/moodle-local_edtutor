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
 * Persistent model for an on-behalf submission record.
 *
 * Every attempt a tutor makes through the plugin is stored here, whether it was
 * submitted automatically or escalated for manual completion.
 *
 * @package    local_edtutor
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission extends persistent {
    /** @var string The table name. */
    const TABLE = 'local_edtutor_submission';

    /** @var int Created but not yet processed. */
    const STATUS_DRAFT = 0;

    /** @var int Submitted automatically on behalf of the student. */
    const STATUS_SUBMITTED_AUTO = 1;

    /** @var int Could not be submitted automatically; awaiting manual completion. */
    const STATUS_ESCALATED = 2;

    /** @var int Completed manually by support staff. */
    const STATUS_COMPLETED = 3;

    /** @var int Cancelled. */
    const STATUS_CANCELLED = 4;

    /** @var string Component used for the plugin's own copy of the uploaded files. */
    const FILE_COMPONENT = 'local_edtutor';

    /** @var string File area used for the plugin's own copy of the uploaded files. */
    const FILE_AREA = 'submission';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'studentid' => [
                'type' => PARAM_INT,
            ],
            'tutorid' => [
                'type' => PARAM_INT,
            ],
            'courseid' => [
                'type' => PARAM_INT,
            ],
            'cmid' => [
                'type' => PARAM_INT,
            ],
            'assignid' => [
                'type' => PARAM_INT,
            ],
            'contextid' => [
                'type' => PARAM_INT,
            ],
            'onlinetext' => [
                'type' => PARAM_RAW,
                'default' => '',
                'null' => NULL_ALLOWED,
            ],
            'onlinetextformat' => [
                'type' => PARAM_INT,
                'default' => FORMAT_HTML,
            ],
            'status' => [
                'type' => PARAM_INT,
                'default' => self::STATUS_DRAFT,
                'choices' => [
                    self::STATUS_DRAFT,
                    self::STATUS_SUBMITTED_AUTO,
                    self::STATUS_ESCALATED,
                    self::STATUS_COMPLETED,
                    self::STATUS_CANCELLED,
                ],
            ],
            'failurereason' => [
                'type' => PARAM_RAW,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'completedby' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'timecompleted' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'notes' => [
                'type' => PARAM_RAW,
                'default' => '',
                'null' => NULL_ALLOWED,
            ],
        ];
    }

    /**
     * Map a status value to its language string key.
     *
     * @param int $status
     * @return string
     */
    public static function status_key(int $status): string {
        $map = [
            self::STATUS_DRAFT => 'status_draft',
            self::STATUS_SUBMITTED_AUTO => 'status_submitted_auto',
            self::STATUS_ESCALATED => 'status_escalated',
            self::STATUS_COMPLETED => 'status_completed',
            self::STATUS_CANCELLED => 'status_cancelled',
        ];
        return $map[$status] ?? 'status_draft';
    }

    /**
     * Human readable status for this record.
     *
     * @return string
     */
    public function get_status_name(): string {
        return get_string(self::status_key($this->get('status')), 'local_edtutor');
    }
}
