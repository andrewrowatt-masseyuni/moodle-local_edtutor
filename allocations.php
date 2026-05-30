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
 * Manage tutor to student allocations.
 *
 * @package    local_edtutor
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_edtutor\allocation;
use local_edtutor\manager;
use local_edtutor\form\allocation_form;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

$context = context_system::instance();
require_login();
require_capability('local/edtutor:manageallocations', $context);

$baseurl = new moodle_url('/local/edtutor/allocations.php');
$PAGE->set_context($context);
$PAGE->set_url($baseurl);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('manageallocations', 'local_edtutor'));
$PAGE->set_heading(get_string('manageallocations', 'local_edtutor'));

// Handle removal of an allocation.
$remove = optional_param('remove', 0, PARAM_INT);
if ($remove) {
    require_sesskey();
    $record = allocation::get_record(['id' => $remove]);
    if ($record) {
        if (optional_param('confirm', 0, PARAM_BOOL)) {
            $record->delete();
            redirect(
                $baseurl,
                get_string('allocationremoved', 'local_edtutor'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
        $tutor = core_user::get_user($record->get('tutorid'));
        $student = core_user::get_user($record->get('studentid'));
        $a = (object)[
            'tutor' => $tutor ? fullname($tutor) : '-',
            'student' => $student ? fullname($student) : '-',
        ];
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(
            get_string('confirmremoveallocation', 'local_edtutor', $a),
            new moodle_url($baseurl, ['remove' => $remove, 'confirm' => 1, 'sesskey' => sesskey()]),
            $baseurl
        );
        echo $OUTPUT->footer();
        die;
    }
    redirect($baseurl);
}

// Add a new allocation.
$form = new allocation_form($baseurl);
if ($data = $form->get_data()) {
    $record = new allocation(0, (object)['tutorid' => (int)$data->tutorid, 'studentid' => (int)$data->studentid]);
    $record->create();
    redirect(
        $baseurl,
        get_string('allocationadded', 'local_edtutor'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manageallocations', 'local_edtutor'));
$form->display();

echo $OUTPUT->heading(get_string('allocations', 'local_edtutor'), 3);
$rows = manager::get_all_allocations();
if (empty($rows)) {
    echo $OUTPUT->notification(get_string('noallocations', 'local_edtutor'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('tutor', 'local_edtutor'),
        get_string('student', 'local_edtutor'),
        '',
    ];
    foreach ($rows as $row) {
        $removeurl = new moodle_url($baseurl, ['remove' => $row->id, 'sesskey' => sesskey()]);
        $table->data[] = [
            $row->tutorname,
            $row->studentname,
            html_writer::link($removeurl, get_string('remove')),
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
