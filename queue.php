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
 * Queue of submissions awaiting manual completion by support staff.
 *
 * @package    local_edtutor
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_edtutor\manager;

require(__DIR__ . '/../../config.php');

$context = context_system::instance();
require_login();
require_capability('local/edtutor:processsubmissions', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/edtutor/queue.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('submissionqueue', 'local_edtutor'));
$PAGE->set_heading(get_string('submissionqueue', 'local_edtutor'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('queueheading', 'local_edtutor'));

$submissions = manager::get_escalated_submissions();
if (empty($submissions)) {
    echo $OUTPUT->notification(get_string('queueempty', 'local_edtutor'), 'info');
} else {
    $table = new html_table();
    $table->head = array_merge(
        [get_string('student', 'local_edtutor')],
        manager::get_identity_headers(),
        [
            get_string('selectcourse', 'local_edtutor'),
            get_string('selectassignment', 'local_edtutor'),
            get_string('tutor', 'local_edtutor'),
            get_string('createdon', 'local_edtutor'),
            '',
        ]
    );
    foreach ($submissions as $submission) {
        $info = manager::describe_submission($submission);
        $table->data[] = array_merge(
            [$info->studentname],
            manager::get_identity_values($submission->get('studentid')),
            [
                $info->coursename,
                $info->assignmentname,
                s(manager::name_with_username($submission->get('tutorid'))),
                $info->timecreated,
                html_writer::link($info->viewurl, get_string('viewsubmission', 'local_edtutor')),
            ]
        );
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
