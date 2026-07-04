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
 * Set forum preferences for allocated students.
 *
 * @package    local_edtutor
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_edtutor\manager;

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/lib.php');

$context = context_system::instance();
require_login();
require_capability('local/edtutor:setpreferences', $context);

$tutorid = (int)$USER->id;
$baseurl = new moodle_url('/local/edtutor/preferences.php');

$PAGE->set_context($context);
$PAGE->set_url($baseurl);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('studentpreferences', 'local_edtutor'));
$PAGE->set_heading(get_string('studentpreferences', 'local_edtutor'));

$students = manager::get_allocated_students($tutorid);
$digestoptions = manager::get_maildigest_options();

// Handle a change of a student's email digest type.
$studentid = optional_param('studentid', 0, PARAM_INT);
$maildigest = optional_param('maildigest', -1, PARAM_INT);
if ($studentid && $maildigest >= 0) {
    require_sesskey();
    if (!isset($students[$studentid])) {
        throw new moodle_exception('error:notallocated', 'local_edtutor');
    }
    if (!isset($digestoptions[$maildigest])) {
        throw new moodle_exception('invalidparameter', 'debug');
    }
    // The digest type lives on the user record, as on the user's own forum
    // preferences page (user/forum.php).
    $user = new stdClass();
    $user->id = $studentid;
    $user->maildigest = $maildigest;
    user_update_user($user, false, true);
    redirect(
        $baseurl,
        get_string('digestupdated', 'local_edtutor', manager::name_with_username($studentid)),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('studentpreferences', 'local_edtutor'));

if (empty($students)) {
    echo $OUTPUT->notification(get_string('nostudentsallocated', 'local_edtutor'), 'info');
    echo $OUTPUT->footer();
    die;
}

echo html_writer::tag('p', get_string('studentpreferences_desc', 'local_edtutor'));

$table = new html_table();
$table->head = [
    get_string('student', 'local_edtutor'),
    get_string('emaildigest'),
];
foreach ($students as $sid => $student) {
    $select = new single_select(
        new moodle_url($baseurl, ['studentid' => $sid, 'sesskey' => sesskey()]),
        'maildigest',
        $digestoptions,
        (int)$student->maildigest,
        null
    );
    $select->set_label(
        get_string('emaildigestfor', 'local_edtutor', fullname($student)),
        ['class' => 'accesshide']
    );
    $table->data[] = [
        s(fullname($student) . ' (' . $student->username . ')'),
        $OUTPUT->render($select),
    ];
}
echo html_writer::table($table);

echo $OUTPUT->footer();
