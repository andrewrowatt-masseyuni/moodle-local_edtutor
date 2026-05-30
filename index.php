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
 * Tutor landing page: links to the submission flow and a list of the tutor's submissions.
 *
 * @package    local_edtutor
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$context = context_system::instance();
require_login();
require_capability('local/edtutor:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/edtutor/index.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_edtutor'));
$PAGE->set_heading(get_string('pluginname', 'local_edtutor'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('tutorarea', 'local_edtutor'));

$buttons = $OUTPUT->single_button(
    new moodle_url('/local/edtutor/submit.php'),
    get_string('submitonbehalf', 'local_edtutor'),
    'get'
);
if (\local_edtutor\manager::can_process((int)$USER->id, $context)) {
    $buttons .= $OUTPUT->single_button(
        new moodle_url('/local/edtutor/queue.php'),
        get_string('submissionqueue', 'local_edtutor'),
        'get'
    );
}
if (has_capability('local/edtutor:manageallocations', $context)) {
    $buttons .= $OUTPUT->single_button(
        new moodle_url('/local/edtutor/allocations.php'),
        get_string('manageallocations', 'local_edtutor'),
        'get'
    );
}
echo html_writer::div($buttons, 'mb-3');

echo $OUTPUT->heading(get_string('mysubmissions', 'local_edtutor'), 3);
$submissions = \local_edtutor\manager::get_tutor_submissions((int)$USER->id);
if (empty($submissions)) {
    echo $OUTPUT->notification(get_string('nosubmissions', 'local_edtutor'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('student', 'local_edtutor'),
        get_string('selectcourse', 'local_edtutor'),
        get_string('selectassignment', 'local_edtutor'),
        get_string('status', 'local_edtutor'),
        get_string('createdon', 'local_edtutor'),
        '',
    ];
    foreach ($submissions as $submission) {
        $info = \local_edtutor\manager::describe_submission($submission);
        $table->data[] = [
            $info->studentname,
            $info->coursename,
            $info->assignmentname,
            $info->statusname,
            $info->timecreated,
            html_writer::link($info->viewurl, get_string('viewsubmission', 'local_edtutor')),
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
