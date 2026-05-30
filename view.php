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
 * View a single on-behalf submission record.
 *
 * @package    local_edtutor
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_edtutor\manager;
use local_edtutor\submission;

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);

$context = context_system::instance();
require_login();

$submission = submission::get_record(['id' => $id]);
if (!$submission) {
    throw new moodle_exception('error:submissionnotfound', 'local_edtutor');
}

$canprocess = manager::can_process((int)$USER->id, $context);
$isowner = ((int)$USER->id === $submission->get('tutorid'));
if (!$isowner && !$canprocess) {
    throw new moodle_exception('error:cannotview', 'local_edtutor');
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/edtutor/view.php', ['id' => $id]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('submissiondetails', 'local_edtutor'));
$PAGE->set_heading(get_string('submissiondetails', 'local_edtutor'));

$info = manager::describe_submission($submission);
$cm = get_coursemodule_from_id('assign', $submission->get('cmid'), 0, false, IGNORE_MISSING);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('submissiondetails', 'local_edtutor'));

$details = new html_table();
$details->data[] = [get_string('student', 'local_edtutor'),
    s(manager::name_with_username($submission->get('studentid')))];
$details->data[] = [get_string('selectcourse', 'local_edtutor'), $info->coursename];
$details->data[] = [get_string('target', 'local_edtutor'), $info->assignmentname];
$details->data[] = [get_string('status', 'local_edtutor'), $info->statusname];
$details->data[] = [get_string('createdby', 'local_edtutor'),
    s(manager::name_with_username($submission->get('tutorid')))];
$details->data[] = [get_string('createdon', 'local_edtutor'), $info->timecreated];
if ($submission->get('failurereason')) {
    $details->data[] = [get_string('reason', 'local_edtutor'), $submission->get('failurereason')];
}
if ($submission->get('status') == submission::STATUS_COMPLETED) {
    $completedby = core_user::get_user($submission->get('completedby'));
    $details->data[] = [get_string('completedby', 'local_edtutor'), $completedby ? fullname($completedby) : '-'];
    $details->data[] = [get_string('completedon', 'local_edtutor'), userdate($submission->get('timecompleted'))];
}
echo html_writer::table($details);

// Online text.
echo $OUTPUT->heading(get_string('onlinetext', 'local_edtutor'), 4);
$text = (string)$submission->get('onlinetext');
if (trim(html_to_text($text)) === '') {
    echo html_writer::div(get_string('notext', 'local_edtutor'));
} else {
    echo $OUTPUT->box(format_text($text, $submission->get('onlinetextformat')));
}

// Uploaded files.
echo $OUTPUT->heading(get_string('submittedfiles', 'local_edtutor'), 4);
$fs = get_file_storage();
$files = $fs->get_area_files(
    $context->id,
    submission::FILE_COMPONENT,
    submission::FILE_AREA,
    $submission->get('id'),
    'filename',
    false
);
if (empty($files)) {
    echo html_writer::div(get_string('nofiles', 'local_edtutor'));
} else {
    $items = [];
    foreach ($files as $file) {
        $fileurl = moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            submission::FILE_COMPONENT,
            submission::FILE_AREA,
            $submission->get('id'),
            $file->get_filepath(),
            $file->get_filename(),
            true
        );
        $items[] = html_writer::link($fileurl, $file->get_filename());
    }
    echo html_writer::alist($items);
}

// Processing actions for escalated items.
if ($canprocess && $submission->get('status') == submission::STATUS_ESCALATED) {
    echo $OUTPUT->heading(get_string('markcompleteheading', 'local_edtutor'), 4);
    $actions = [];
    if ($cm) {
        $actions[] = html_writer::link(
            new moodle_url('/mod/assign/view.php', ['id' => $cm->id]),
            get_string('gotoassignment', 'local_edtutor'),
            ['class' => 'btn btn-secondary mr-1']
        );
        $actions[] = html_writer::link(new moodle_url('/course/loginas.php', [
            'id' => $submission->get('courseid'),
            'user' => $submission->get('studentid'),
            'sesskey' => sesskey(),
        ]), get_string('loginasstudent', 'local_edtutor'), ['class' => 'btn btn-secondary mr-1']);
    }
    $actions[] = html_writer::link(
        new moodle_url('/local/edtutor/complete.php', ['id' => $submission->get('id')]),
        get_string('markcomplete', 'local_edtutor'),
        ['class' => 'btn btn-primary']
    );
    echo html_writer::div(implode(' ', $actions), 'mb-3');
}

// Completion notes.
if (trim((string)$submission->get('notes')) !== '') {
    echo $OUTPUT->heading(get_string('completionnotes', 'local_edtutor'), 4);
    echo html_writer::div(s($submission->get('notes')));
}

echo html_writer::link(new moodle_url('/local/edtutor/index.php'), get_string('backtotutorarea', 'local_edtutor'));

echo $OUTPUT->footer();
