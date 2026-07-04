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
        $sql = "SELECT u.id, u.username, u.maildigest $userfields
                  FROM {local_edtutor_allocation} a
                  JOIN {user} u ON u.id = a.studentid
                 WHERE a.tutorid = :tutorid AND u.deleted = 0
              ORDER BY u.lastname, u.firstname";
        return $DB->get_records_sql($sql, ['tutorid' => $tutorid]);
    }

    /**
     * Forum email digest type options, keyed by the user.maildigest value.
     *
     * The keys and labels match the user's own forum preferences page.
     *
     * @return string[] Array of digest type names keyed by digest value.
     */
    public static function get_maildigest_options(): array {
        return [
            0 => get_string('emaildigestoff'),
            1 => get_string('emaildigestcomplete'),
            2 => get_string('emaildigestsubjects'),
        ];
    }

    /**
     * Students allocated to a tutor who are also enrolled in a given course.
     *
     * @param int $tutorid
     * @param int $courseid
     * @return array Array of user records keyed by user id.
     */
    public static function get_allocated_students_in_course(int $tutorid, int $courseid): array {
        global $DB;
        $context = \context_course::instance($courseid);
        [$enrolsql, $enrolparams] = get_enrolled_sql($context);
        $userfields = \core_user\fields::for_name()->get_sql('u')->selects;
        $sql = "SELECT u.id, u.username $userfields
                  FROM {local_edtutor_allocation} a
                  JOIN {user} u ON u.id = a.studentid
                  JOIN ($enrolsql) eu ON eu.id = u.id
                 WHERE a.tutorid = :tutorid AND u.deleted = 0
              ORDER BY u.lastname, u.firstname";
        return $DB->get_records_sql($sql, array_merge($enrolparams, ['tutorid' => $tutorid]));
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
     * Assignment and quiz course modules in a course that a student can access.
     *
     * Unlike {@see get_course_assignments()} this applies availability
     * restrictions for the given student, not just module visibility.
     *
     * @param int $courseid
     * @param int $studentid
     * @return \cm_info[] Array of course modules keyed by cmid.
     */
    public static function get_student_course_activities(int $courseid, int $studentid): array {
        $modinfo = get_fast_modinfo($courseid, $studentid);
        $activities = [];
        foreach (['assign', 'quiz'] as $modname) {
            foreach ($modinfo->get_instances_of($modname) as $cm) {
                if (!$cm->uservisible) {
                    continue;
                }
                $activities[$cm->id] = $cm;
            }
        }
        return $activities;
    }

    /**
     * Submission status and effective due date for a student on one assignment.
     *
     * The due date reflects any user or group override, and an approved
     * extension takes precedence over both.
     *
     * @param \assign $assign Assignment api instance, reusable across students.
     * @param int $studentid
     * @return \stdClass Object with submitted, duedate, extension and overdue.
     */
    public static function get_assignment_status(\assign $assign, int $studentid): \stdClass {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $assign->update_effective_access($studentid);
        $duedate = (int)$assign->get_instance($studentid)->duedate;

        $extension = false;
        $flags = $assign->get_user_flags($studentid, false);
        if ($flags && $flags->extensionduedate > 0) {
            $extension = true;
            $duedate = (int)$flags->extensionduedate;
        }

        $submission = $assign->get_user_submission($studentid, false);
        $submitted = $submission && $submission->status === ASSIGN_SUBMISSION_STATUS_SUBMITTED;

        return (object)[
            'submitted' => $submitted,
            'duedate' => $duedate,
            'extension' => $extension,
            'overdue' => !$submitted && $duedate > 0 && $duedate < time(),
        ];
    }

    /**
     * Attempt status and effective close date for a student on one quiz.
     *
     * The close date reflects any user or group override; an override that
     * changes the close date is reported as an extension.
     *
     * @param \stdClass $quiz Base quiz record (without user overrides applied), reusable across students.
     * @param int $studentid
     * @return \stdClass Object with submitted, duedate, extension and overdue.
     */
    public static function get_quiz_status(\stdClass $quiz, int $studentid): \stdClass {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/lib.php');

        // The quiz record is cloned because quiz_update_effective_access() modifies it in place.
        $effective = quiz_update_effective_access(clone $quiz, $studentid);
        $duedate = (int)$effective->timeclose;
        $extension = $duedate !== (int)$quiz->timeclose;

        $submitted = false;
        foreach (quiz_get_user_attempts([$quiz->id], $studentid) as $attempt) {
            if ($attempt->state === \mod_quiz\quiz_attempt::FINISHED) {
                $submitted = true;
                break;
            }
        }

        return (object)[
            'submitted' => $submitted,
            'duedate' => $duedate,
            'extension' => $extension,
            'overdue' => !$submitted && $duedate > 0 && $duedate < time(),
        ];
    }

    /**
     * Last course access times for a set of students.
     *
     * @param int[] $studentids
     * @return array Two maps: 'percourse' keyed by "userid-courseid" and 'latest' keyed by userid.
     */
    public static function get_last_course_access(array $studentids): array {
        global $DB;
        $access = ['percourse' => [], 'latest' => []];
        if (empty($studentids)) {
            return $access;
        }
        [$insql, $params] = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED);
        $records = $DB->get_records_select(
            'user_lastaccess',
            "userid $insql",
            $params,
            '',
            'id, userid, courseid, timeaccess'
        );
        foreach ($records as $record) {
            $access['percourse'][$record->userid . '-' . $record->courseid] = (int)$record->timeaccess;
            $access['latest'][$record->userid] = max($access['latest'][$record->userid] ?? 0, (int)$record->timeaccess);
        }
        return $access;
    }

    /**
     * User ids of the staff notified when a submission is escalated (resolved from usernames).
     *
     * This list only controls who receives the escalation notification; access to the
     * queue is controlled by the local/edtutor:processsubmissions capability.
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
        return has_capability('local/edtutor:processsubmissions', $context, $userid);
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
        $users = $DB->get_records_list('user', 'id', array_values($userids), '', 'id,username,' . $namefields);
        $rows = [];
        foreach ($allocations as $a) {
            $rows[] = (object)[
                'id' => $a->id,
                'tutorid' => $a->tutorid,
                'studentid' => $a->studentid,
                'tutorname' => isset($users[$a->tutorid])
                    ? s(fullname($users[$a->tutorid]) . ' (' . $users[$a->tutorid]->username . ')')
                    : '-',
                'studentname' => isset($users[$a->studentid]) ? s(fullname($users[$a->studentid])) : '-',
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
        global $DB;
        $student = \core_user::get_user($submission->get('studentid'));
        $course = $DB->get_record('course', ['id' => $submission->get('courseid')], 'id, fullname');
        $cm = get_coursemodule_from_id('assign', $submission->get('cmid'), 0, false, IGNORE_MISSING);
        return (object)[
            'studentname' => $student ? s(fullname($student)) : '-',
            'coursename' => $course ? format_string($course->fullname) : '-',
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
     * Align a tutor's education tutor role assignment with their allocations.
     *
     * Assigns the role at system context while the tutor has at least one
     * allocation and unassigns it once they have none.
     *
     * @param int $tutorid
     * @return bool True if the role exists and is now in sync, false if the role was not found.
     */
    public static function sync_tutor_role(int $tutorid): bool {
        global $DB;
        $roleid = self::get_tutor_roleid();
        if (!$roleid) {
            return false;
        }
        $contextid = \context_system::instance()->id;
        if ($DB->record_exists('local_edtutor_allocation', ['tutorid' => $tutorid])) {
            role_assign($roleid, $tutorid, $contextid);
        } else {
            role_unassign($roleid, $tutorid, $contextid);
        }
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
     * A user's full name with their username in parentheses.
     *
     * @param int $userid
     * @return string
     */
    public static function name_with_username(int $userid): string {
        $user = \core_user::get_user($userid);
        if (!$user) {
            return '-';
        }
        return fullname($user) . ' (' . $user->username . ')';
    }
}
