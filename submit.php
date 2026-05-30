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
 * Submit work on behalf of an allocated student.
 *
 * @package    local_edtutor
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_edtutor\manager;
use local_edtutor\submission;
use local_edtutor\submission_service;
use local_edtutor\form\submission_form;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

$studentid = optional_param('studentid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$cmid = optional_param('cmid', 0, PARAM_INT);

$context = context_system::instance();
require_login();
require_capability('local/edtutor:submit', $context);

$tutorid = (int)$USER->id;
$baseurl = new moodle_url('/local/edtutor/submit.php');

$PAGE->set_context($context);
$PAGE->set_url($baseurl);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('submitonbehalf', 'local_edtutor'));
$PAGE->set_heading(get_string('submitonbehalf', 'local_edtutor'));
$PAGE->navbar->add(get_string('tutorarea', 'local_edtutor'), new moodle_url('/local/edtutor/index.php'));
$PAGE->navbar->add(get_string('submitonbehalf', 'local_edtutor'));

$students = manager::get_allocated_students($tutorid);

// Validate any selections that were already made.
if ($studentid && !isset($students[$studentid])) {
    throw new moodle_exception('error:notallocated', 'local_edtutor');
}
$courses = $studentid ? manager::get_student_courses($studentid) : [];
if ($courseid && !isset($courses[$courseid])) {
    throw new moodle_exception('invalidcourseid', 'error');
}
$assigns = $courseid ? manager::get_course_assignments($courseid) : [];
if ($cmid && !isset($assigns[$cmid])) {
    throw new moodle_exception('error:invalidassignment', 'local_edtutor');
}

// Build and process the submission form before any output (so redirects work cleanly).
$form = null;
if ($studentid && $courseid && $cmid) {
    $cm = $assigns[$cmid];
    $filecontext = context_system::instance();
    $draftitemid = file_get_submitted_draft_itemid('submissionfiles');
    file_prepare_draft_area(
        $draftitemid,
        $filecontext->id,
        submission::FILE_COMPONENT,
        submission::FILE_AREA,
        null,
        submission_service::file_options($CFG->maxbytes)
    );

    $actionurl = new moodle_url($baseurl, ['studentid' => $studentid, 'courseid' => $courseid, 'cmid' => $cmid]);
    $form = new submission_form($actionurl, ['studentid' => $studentid, 'cmid' => $cmid]);
    $form->set_data(['studentid' => $studentid, 'cmid' => $cmid, 'submissionfiles' => $draftitemid]);

    if ($form->is_cancelled()) {
        redirect(new moodle_url('/local/edtutor/index.php'));
    } else if ($data = $form->get_data()) {
        $submission = submission_service::create_and_submit($studentid, $cm, $data, $tutorid);
        $viewurl = new moodle_url('/local/edtutor/view.php', ['id' => $submission->get('id')]);
        if ($submission->get('status') == submission::STATUS_SUBMITTED_AUTO) {
            $studentname = manager::student_name_with_username($submission->get('studentid'));
            redirect(
                $viewurl,
                get_string('outcome_submitted', 'local_edtutor', $studentname),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
        redirect(
            $viewurl,
            get_string('outcome_escalated', 'local_edtutor', $submission->get('failurereason')),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('submitonbehalf', 'local_edtutor'));

if (empty($students)) {
    echo $OUTPUT->notification(get_string('nostudentsallocated', 'local_edtutor'), 'info');
    echo $OUTPUT->footer();
    die;
}

// Step 1: choose the student.
$studentoptions = [];
foreach ($students as $sid => $user) {
    $studentoptions[$sid] = fullname($user);
}
$select = new single_select($baseurl, 'studentid', $studentoptions, $studentid);
$select->label = get_string('selectstudent', 'local_edtutor') . ': ';
echo $OUTPUT->render($select);

// Step 2: choose the course.
if ($studentid) {
    if (empty($courses)) {
        echo $OUTPUT->notification(get_string('nocoursesforstudent', 'local_edtutor'), 'info');
        echo $OUTPUT->footer();
        die;
    }
    $courseoptions = [];
    foreach ($courses as $cid => $course) {
        $courseoptions[$cid] = format_string($course->fullname);
    }
    $select = new single_select(
        new moodle_url($baseurl, ['studentid' => $studentid]),
        'courseid',
        $courseoptions,
        $courseid
    );
    $select->label = get_string('selectcourse', 'local_edtutor') . ': ';
    echo $OUTPUT->render($select);
}

// Step 3: choose the assignment.
if ($studentid && $courseid) {
    if (empty($assigns)) {
        echo $OUTPUT->notification(get_string('noassignmentsincourse', 'local_edtutor'), 'info');
        echo $OUTPUT->footer();
        die;
    }
    $assignoptions = [];
    foreach ($assigns as $acmid => $acm) {
        $assignoptions[$acmid] = format_string($acm->get_formatted_name());
    }
    $select = new single_select(
        new moodle_url($baseurl, ['studentid' => $studentid, 'courseid' => $courseid]),
        'cmid',
        $assignoptions,
        $cmid
    );
    $select->label = get_string('selectassignment', 'local_edtutor') . ': ';
    echo $OUTPUT->render($select);
}

// Step 4: the upload form.
if ($form) {
    echo $OUTPUT->heading($assigns[$cmid]->get_formatted_name(), 4);
    $form->display();
}

echo $OUTPUT->footer();
