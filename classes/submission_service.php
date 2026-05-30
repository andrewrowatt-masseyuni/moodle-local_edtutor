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
 * Creates on-behalf submission records and attempts to submit them through mod_assign.
 *
 * The submission is owned by the student (so it grades normally), while the acting tutor
 * is recorded as the actor in the logs. When automatic submission is not possible the
 * record is escalated for support staff to complete manually.
 *
 * @package    local_edtutor
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission_service {
    /**
     * File manager / draft area options used for the plugin's own copy of the files.
     *
     * @param int $maxbytes
     * @return array
     */
    public static function file_options(int $maxbytes = -1): array {
        return [
            'subdirs' => 1,
            'maxbytes' => $maxbytes,
            'maxfiles' => -1,
            'accepted_types' => '*',
        ];
    }

    /**
     * Editor options for the online text element (text only, no embedded files).
     *
     * @return array
     */
    public static function editor_options(): array {
        return [
            'maxfiles' => 0,
            'noclean' => false,
            'context' => \context_system::instance(),
        ];
    }

    /**
     * Create a submission record from a tutor's form data and attempt to submit it automatically.
     *
     * @param int $studentid The student the work is for.
     * @param \cm_info $cm The target assignment course module.
     * @param \stdClass $formdata Form data with submissionfiles (draft itemid) and onlinetext_editor.
     * @param int $tutorid The acting tutor.
     * @return submission The stored submission record.
     */
    public static function create_and_submit(int $studentid, \cm_info $cm, \stdClass $formdata, int $tutorid): submission {
        $modcontext = \context_module::instance($cm->id);
        $course = get_course($cm->course);
        $editor = $formdata->onlinetext_editor ?? [];

        // Persist the record first so the package is never lost, even if submission fails.
        $submission = new submission(0, (object)[
            'studentid' => $studentid,
            'tutorid' => $tutorid,
            'courseid' => $course->id,
            'cmid' => $cm->id,
            'assignid' => $cm->instance,
            'contextid' => $modcontext->id,
            'onlinetext' => $editor['text'] ?? '',
            'onlinetextformat' => $editor['format'] ?? FORMAT_HTML,
            'status' => submission::STATUS_DRAFT,
        ]);
        $submission->create();

        // Keep a permanent plugin-side copy of the uploaded files for audit and manual fallback.
        if (!empty($formdata->submissionfiles)) {
            file_save_draft_area_files(
                $formdata->submissionfiles,
                \context_system::instance()->id,
                submission::FILE_COMPONENT,
                submission::FILE_AREA,
                $submission->get('id'),
                self::file_options()
            );
        }

        self::trigger_event(event\submission_created::class, $submission);

        // Attempt the automatic submission, escalating on any failure.
        try {
            $reason = self::attempt_autosubmit($submission, $cm, $course);
        } catch (\Throwable $e) {
            $reason = get_string('reason_exception', 'local_edtutor', $e->getMessage());
            debugging('local_edtutor autosubmit exception: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        if ($reason === null) {
            $submission->set('status', submission::STATUS_SUBMITTED_AUTO);
            $submission->set('failurereason', null);
            $submission->update();
            self::trigger_event(event\submission_autosubmitted::class, $submission);
        } else {
            $submission->set('status', submission::STATUS_ESCALATED);
            $submission->set('failurereason', $reason);
            $submission->update();
            self::trigger_event(event\submission_escalated::class, $submission);
            notification::notify_escalation($submission);
        }

        return $submission;
    }

    /**
     * Attempt to submit the stored work on behalf of the student via mod_assign.
     *
     * @param submission $submission The stored submission record.
     * @param \cm_info $cm The target assignment course module.
     * @param \stdClass $course The course record.
     * @return string|null Null on success, or a human-readable reason when escalation is needed.
     */
    protected static function attempt_autosubmit(submission $submission, \cm_info $cm, \stdClass $course): ?string {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        if ($cm->modname !== 'assign') {
            return get_string('reason_notassign', 'local_edtutor');
        }

        $studentid = $submission->get('studentid');
        $modcontext = \context::instance_by_id($submission->get('contextid'));
        $assign = new \assign($modcontext, $cm, $course);

        [$hasfiles, $hastext] = self::submission_content($submission);

        $reason = self::preflight_reason($assign, (int)$studentid, $modcontext, $hasfiles, $hastext);
        if ($reason !== null) {
            return $reason;
        }

        $data = self::build_submission_data($submission, $hasfiles);

        $notices = [];
        if (!$assign->save_submission($data, $notices)) {
            return get_string('reason_savefailed', 'local_edtutor', self::format_notices($notices));
        }

        return self::finalise_submission($assign, (int)$studentid);
    }

    /**
     * Determine whether the stored submission has files and/or online text.
     *
     * @param submission $submission
     * @return array [bool $hasfiles, bool $hastext]
     */
    protected static function submission_content(submission $submission): array {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            \context_system::instance()->id,
            submission::FILE_COMPONENT,
            submission::FILE_AREA,
            $submission->get('id'),
            'id',
            false
        );
        $hastext = trim(html_to_text((string)$submission->get('onlinetext'))) !== '';
        return [!empty($files), $hastext];
    }

    /**
     * Check whether the assignment can accept an automatic on-behalf submission.
     *
     * @param \assign $assign
     * @param int $studentid
     * @param \context $modcontext
     * @param bool $hasfiles
     * @param bool $hastext
     * @return string|null Null if it can, otherwise the reason it cannot.
     */
    protected static function preflight_reason(
        \assign $assign,
        int $studentid,
        \context $modcontext,
        bool $hasfiles,
        bool $hastext
    ): ?string {
        if (!empty($assign->get_instance()->teamsubmission)) {
            return get_string('reason_teamsubmission', 'local_edtutor');
        }
        if (!has_capability('mod/assign:editothersubmission', $modcontext)) {
            return get_string('reason_nocapability', 'local_edtutor');
        }
        if (!$assign->submissions_open($studentid)) {
            return get_string('reason_notopen', 'local_edtutor');
        }
        $fileplugin = $assign->get_submission_plugin_by_type('file');
        if ($hasfiles && (!$fileplugin || !$fileplugin->is_enabled() || !$fileplugin->is_visible())) {
            return get_string('reason_nofileplugin', 'local_edtutor');
        }
        $textplugin = $assign->get_submission_plugin_by_type('onlinetext');
        if ($hastext && (!$textplugin || !$textplugin->is_enabled() || !$textplugin->is_visible())) {
            return get_string('reason_notextplugin', 'local_edtutor');
        }
        return null;
    }

    /**
     * Build the form data the mod_assign submission form would produce.
     *
     * @param submission $submission
     * @param bool $hasfiles
     * @return \stdClass
     */
    protected static function build_submission_data(submission $submission, bool $hasfiles): \stdClass {
        $data = new \stdClass();
        $data->userid = $submission->get('studentid');

        if ($hasfiles) {
            $filedraftid = 0;
            file_prepare_draft_area(
                $filedraftid,
                \context_system::instance()->id,
                submission::FILE_COMPONENT,
                submission::FILE_AREA,
                $submission->get('id'),
                self::file_options()
            );
            $data->files_filemanager = $filedraftid;
        }

        $data->onlinetext_editor = [
            'text' => (string)$submission->get('onlinetext'),
            'format' => (int)$submission->get('onlinetextformat'),
            'itemid' => file_get_unused_draft_itemid(),
        ];

        return $data;
    }

    /**
     * Finalise the submission for grading when it is not already submitted.
     *
     * @param \assign $assign
     * @param int $studentid
     * @return string|null Null on success, otherwise the failure reason.
     */
    protected static function finalise_submission(\assign $assign, int $studentid): ?string {
        $assignsubmission = $assign->get_user_submission($studentid, false);
        if ($assignsubmission && $assignsubmission->status !== ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
            $submitdata = new \stdClass();
            $submitdata->userid = $studentid;
            $submitdata->submissionstatement = 1;
            $notices = [];
            if (!$assign->submit_for_grading($submitdata, $notices)) {
                return get_string('reason_savefailed', 'local_edtutor', self::format_notices($notices));
            }
        }
        return null;
    }

    /**
     * Mark an escalated submission as completed by support staff.
     *
     * @param submission $submission
     * @param int $completedby User id of the staff member completing it.
     * @param string $notes Optional notes.
     */
    public static function complete_submission(submission $submission, int $completedby, string $notes = ''): void {
        $submission->set('status', submission::STATUS_COMPLETED);
        $submission->set('completedby', $completedby);
        $submission->set('timecompleted', time());
        $submission->set('notes', $notes);
        $submission->update();
        self::trigger_event(event\submission_completed::class, $submission);
    }

    /**
     * Create and trigger one of the plugin's submission events.
     *
     * @param string $eventclass Fully qualified event class name.
     * @param submission $submission
     */
    protected static function trigger_event(string $eventclass, submission $submission): void {
        $event = $eventclass::create([
            'context' => \context::instance_by_id($submission->get('contextid')),
            'objectid' => $submission->get('id'),
            'relateduserid' => $submission->get('studentid'),
            'other' => [
                'courseid' => $submission->get('courseid'),
                'assignid' => $submission->get('assignid'),
            ],
        ]);
        $event->trigger();
    }

    /**
     * Join mod_assign notices into a single message.
     *
     * @param array $notices
     * @return string
     */
    protected static function format_notices(array $notices): string {
        return $notices ? implode('; ', array_map('strip_tags', $notices)) : '';
    }
}
