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

    /**
     * Role id for the configured education tutor role shortname, or null if unset/not found.
     *
     * @return int|null
     */
    public static function get_tutor_roleid(): ?int {
        global $DB;
        $shortname = trim((string)get_config('local_edtutor', 'roleshortname'));
        if ($shortname === '') {
            return null;
        }
        $roleid = $DB->get_field('role', 'id', ['shortname' => $shortname]);
        return $roleid ? (int)$roleid : null;
    }

    /**
     * Whether the current user may provision the education tutor role,
     * i.e. they can assign roles in the system context.
     *
     * @return bool
     */
    public static function can_provision_tutor_role(): bool {
        return has_capability('moodle/role:assign', \context_system::instance());
    }

    /**
     * Users who hold the education tutor role at system context.
     *
     * @return array Array of user records keyed by user id.
     */
    public static function get_tutor_role_users(): array {
        $roleid = self::get_tutor_roleid();
        if (!$roleid) {
            return [];
        }
        return get_role_users($roleid, \context_system::instance());
    }

    /**
     * Tutor role holders as an id => fullname options array for a selector.
     *
     * @return array
     */
    public static function get_tutor_role_user_options(): array {
        $options = [];
        foreach (self::get_tutor_role_users() as $user) {
            $options[$user->id] = fullname($user);
        }
        return $options;
    }

    /**
     * Whether a user holds the education tutor role at system context.
     *
     * @param int $userid
     * @return bool
     */
    public static function user_has_tutor_role(int $userid): bool {
        $roleid = self::get_tutor_roleid();
        if (!$roleid) {
            return false;
        }
        return user_has_role_assignment($userid, $roleid, \context_system::instance()->id);
    }

    /**
     * Assign the education tutor role to a user at system context.
     *
     * @param int $userid
     * @return bool True if the role exists and was assigned (or already held), false if the role was not found.
     */
    public static function assign_tutor_role(int $userid): bool {
        $roleid = self::get_tutor_roleid();
        if (!$roleid) {
            return false;
        }
        role_assign($roleid, $userid, \context_system::instance()->id);
        return true;
    }

    /**
     * Identity fields (from showuseridentity) the current user may see in the system context.
     *
     * @return string[]
     */
    public static function get_identity_fields(): array {
        return \core_user\fields::get_identity_fields(\context_system::instance());
    }

    /**
     * Table column headers for the identity fields.
     *
     * @return string[]
     */
    public static function get_identity_headers(): array {
        $headers = [];
        foreach (self::get_identity_fields() as $field) {
            $headers[] = \core_user\fields::get_display_name($field);
        }
        return $headers;
    }

    /**
     * Identity field values for a user, escaped and in the same order as the headers.
     *
     * @param int $userid
     * @return string[]
     */
    public static function get_identity_values(int $userid): array {
        global $CFG;
        $fields = self::get_identity_fields();
        if (empty($fields)) {
            return [];
        }
        $user = \core_user::get_user($userid);
        if (!$user) {
            return array_fill(0, count($fields), '');
        }
        $customfields = null;
        $values = [];
        foreach ($fields as $field) {
            if (preg_match('/^profile_field_(.*)$/', $field, $matches)) {
                if ($customfields === null) {
                    require_once($CFG->dirroot . '/user/profile/lib.php');
                    $customfields = profile_user_record($userid, false);
                }
                $value = $customfields->{$matches[1]} ?? '';
            } else {
                $value = $user->$field ?? '';
            }
            $values[] = s((string)$value);
        }
        return $values;
    }

    /**
     * A student's full name with their username in parentheses, for non-table displays.
     *
     * @param int $userid
     * @return string
     */
    public static function student_name_with_username(int $userid): string {
        $user = \core_user::get_user($userid);
        if (!$user) {
            return '-';
        }
        return fullname($user) . ' (' . $user->username . ')';
    }
}
