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
 * Mark an escalated submission as completed by support staff.
 *
 * @package    local_edtutor
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_edtutor\submission;
use local_edtutor\submission_service;
use local_edtutor\form\complete_form;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

$id = required_param('id', PARAM_INT);

$context = context_system::instance();
require_login();
require_capability('local/edtutor:processsubmissions', $context);

$submission = submission::get_record(['id' => $id]);
if (!$submission) {
    throw new moodle_exception('error:submissionnotfound', 'local_edtutor');
}

$viewurl = new moodle_url('/local/edtutor/view.php', ['id' => $id]);
if ($submission->get('status') == submission::STATUS_COMPLETED) {
    redirect(
        $viewurl,
        get_string('error:alreadycompleted', 'local_edtutor'),
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}
if ($submission->get('status') != submission::STATUS_ESCALATED) {
    redirect(
        $viewurl,
        get_string('error:notescalated', 'local_edtutor'),
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/edtutor/complete.php', ['id' => $id]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('markcompleteheading', 'local_edtutor'));
$PAGE->set_heading(get_string('markcompleteheading', 'local_edtutor'));

$form = new complete_form($PAGE->url, ['id' => $id]);
if ($form->is_cancelled()) {
    redirect($viewurl);
} else if ($data = $form->get_data()) {
    submission_service::complete_submission($submission, (int)$USER->id, (string)$data->notes);
    redirect(
        $viewurl,
        get_string('submissioncompleted', 'local_edtutor'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('markcompleteheading', 'local_edtutor'));
$form->display();
echo $OUTPUT->footer();
