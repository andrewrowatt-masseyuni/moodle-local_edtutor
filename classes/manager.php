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
 * Helper queries shared across the plugin pages.
 *
 * @package    local_edtutor
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /**
     * Students allocated to a tutor, ordered by name.
     *
     * @param int $tutorid
     * @return array Array of user records keyed by user id.
     */
    public static function get_allocated_students(int $tutorid): array {
        global $DB;
        $userfields = \core_user\fields::for_name()->get_sql('u')->selects;
        $sql = "SELECT u.id $userfields
                  FROM {local_edtutor_allocation} a
                  JOIN {user} u ON u.id = a.studentid
                 WHERE a.tutorid = :tutorid AND u.deleted = 0
              ORDER BY u.lastname, u.firstname";
        return $DB->get_records_sql($sql, ['tutorid' => $tutorid]);
    }

    /**
     * Whether a student is allocated to a tutor.
     *
     * @param int $tutorid
     * @param int $studentid
     * @return bool
     */
    public static function is_allocated(int $tutorid, int $studentid): bool {
        return allocation::allocation_exists($tutorid, $studentid);
    }

    /**
     * Active course enrolments for a student.
     *
     * @param int $studentid
     * @return array Array of course records keyed by course id.
     */
    public static function get_student_courses(int $studentid): array {
        $courses = enrol_get_users_courses($studentid, true, ['id', 'fullname', 'shortname', 'visible']);
        \core_collator::asort_objects_by_property($courses, 'fullname');
        return $courses;
    }

    /**
     * Visible assignment course modules in a course.
     *
     * @param int $courseid
     * @return \cm_info[] Array of assignment course modules keyed by cmid.
     */
    public static function get_course_assignments(int $courseid): array {
        $modinfo = get_fast_modinfo($courseid);
        $assigns = [];
        foreach ($modinfo->get_instances_of('assign') as $cm) {
            if (!$cm->visible) {
                continue;
            }
            $assigns[$cm->id] = $cm;
        }
        return $assigns;
    }

    /**
     * User ids of the configured support staff (resolved from usernames).
     *
     * @return int[]
     */
    public static function get_support_staff_userids(): array {
        global $DB;
        $raw = (string)get_config('local_edtutor', 'supportstaff');
        if (trim($raw) === '') {
            return [];
        }
        $usernames = preg_split('/[\s,]+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY);
        $usernames = array_values(array_unique(array_map(function ($username) {
            return \core_text::strtolower(trim($username));
        }, $usernames)));
        if (empty($usernames)) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($usernames, SQL_PARAMS_NAMED);
        $records = $DB->get_records_select('user', "deleted = 0 AND username $insql", $params, '', 'id, username');
        return array_keys($records);
    }

    /**
     * Whether a user may view and complete escalated submissions.
     *
     * @param int $userid
     * @param \context $context
     * @return bool
     */
    public static function can_process(int $userid, \context $context): bool {
        if (has_capability('local/edtutor:processsubmissions', $context, $userid)) {
            return true;
        }
        return in_array($userid, self::get_support_staff_userids());
    }

    /**
     * All allocations, prepared for display with resolved names.
     *
     * @return array Array of objects with id, tutorid, studentid, tutorname and studentname.
     */
    public static function get_all_allocations(): array {
        global $DB;
        $allocations = $DB->get_records('local_edtutor_allocation', null, 'id ASC');
        if (empty($allocations)) {
            return [];
        }
        $userids = [];
        foreach ($allocations as $a) {
            $userids[$a->tutorid] = $a->tutorid;
            $userids[$a->studentid] = $a->studentid;
        }
        $namefields = implode(',', \core_user\fields::for_name()->get_required_fields());
        $users = $DB->get_records_list('user', 'id', array_values($userids), '', 'id,' . $namefields);
        $rows = [];
        foreach ($allocations as $a) {
            $rows[] = (object)[
                'id' => $a->id,
                'tutorid' => $a->tutorid,
                'studentid' => $a->studentid,
                'tutorname' => isset($users[$a->tutorid]) ? fullname($users[$a->tutorid]) : '-',
                'studentname' => isset($users[$a->studentid]) ? fullname($users[$a->studentid]) : '-',
            ];
        }
        return $rows;
    }

    /**
     * On-behalf submissions created by a tutor, newest first.
     *
     * @param int $tutorid
     * @return submission[]
     */
    public static function get_tutor_submissions(int $tutorid): array {
        return submission::get_records(['tutorid' => $tutorid], 'timecreated', 'DESC');
    }

    /**
     * Submissions awaiting manual completion, oldest first.
     *
     * @return submission[]
     */
    public static function get_escalated_submissions(): array {
        return submission::get_records(['status' => submission::STATUS_ESCALATED], 'timecreated', 'ASC');
    }

    /**
     * Resolve the display fields for a submission record.
     *
     * @param submission $submission
     * @return \stdClass Object with studentname, coursename, assignmentname, statusname, timecreated, viewurl.
     */
    public static function describe_submission(submission $submission): \stdClass {
        $student = \core_user::get_user($submission->get('studentid'));
        $course = get_course($submission->get('courseid'));
        $cm = get_coursemodule_from_id('assign', $submission->get('cmid'), 0, false, IGNORE_MISSING);
        return (object)[
            'studentname' => $student ? fullname($student) : '-',
            'coursename' => format_string($course->fullname),
            'assignmentname' => $cm ? format_string($cm->name) : '-',
            'statusname' => $submission->get_status_name(),
            'timecreated' => userdate($submission->get('timecreated')),
            'viewurl' => new \moodle_url('/local/edtutor/view.php', ['id' => $submission->get('id')]),
        ];
    }
}
