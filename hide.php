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
 * Hide or show an assessment for an allocated student on the tutor dashboard.
 *
 * Hiding shows a confirmation and warning page first; showing is a direct
 * action guarded by a sesskey. The hidden state is per tutor.
 *
 * @package    local_edtutor
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_edtutor\manager;

require(__DIR__ . '/../../config.php');

$studentid = required_param('studentid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$cmid = required_param('cmid', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$context = context_system::instance();
require_login();

$tutorcaps = ['local/edtutor:submit', 'local/edtutor:loginas', 'local/edtutor:setpreferences'];
if (!has_any_capability($tutorcaps, $context)) {
    throw new required_capability_exception($context, 'local/edtutor:submit', 'nopermissions', '');
}

$tutorid = (int)$USER->id;
$dashboardurl = new moodle_url('/local/edtutor/dashboard.php');

// The tutor may only hide assessments for students allocated to them.
if (!manager::is_allocated($tutorid, $studentid)) {
    throw new moodle_exception('error:notallocated', 'local_edtutor');
}

// The assessment must be a real assignment or quiz in the given course.
$cm = get_coursemodule_from_id('', $cmid, $courseid, false, MUST_EXIST);
if (!in_array($cm->modname, ['assign', 'quiz'], true)) {
    throw new moodle_exception('error:invalidassignment', 'local_edtutor');
}

if ($action === 'show') {
    require_sesskey();
    manager::set_assessment_hidden($tutorid, $studentid, $courseid, $cmid, false);
    redirect(
        $dashboardurl,
        get_string('assessmentshown', 'local_edtutor'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

if ($action !== 'hide') {
    throw new moodle_exception('invalidparameter', 'debug');
}

// Perform the hide once the tutor has confirmed.
if ($confirm && confirm_sesskey()) {
    manager::set_assessment_hidden($tutorid, $studentid, $courseid, $cmid, true);
    redirect(
        $dashboardurl,
        get_string('assessmenthidden', 'local_edtutor'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$pageurl = new moodle_url('/local/edtutor/hide.php', [
    'studentid' => $studentid,
    'courseid' => $courseid,
    'cmid' => $cmid,
    'action' => 'hide',
]);
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('hideassessment', 'local_edtutor'));
$PAGE->set_heading(get_string('hideassessment', 'local_edtutor'));
$PAGE->navbar->add(get_string('tutorarea', 'local_edtutor'), new moodle_url('/local/edtutor/index.php'));
$PAGE->navbar->add(get_string('hideassessment', 'local_edtutor'));

$course = get_course($courseid);
$messagedata = (object)[
    'activity' => format_string($cm->name),
    'course' => format_string($course->fullname),
    'student' => manager::name_with_username($studentid),
];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('hideassessment', 'local_edtutor'));
echo $OUTPUT->render_from_template('local_edtutor/hide_confirm', [
    'message' => get_string('hideassessmentconfirm', 'local_edtutor', $messagedata),
    'warning' => get_string('hideassessmentwarning', 'local_edtutor'),
    'actionurl' => (new moodle_url('/local/edtutor/hide.php'))->out(false),
    'studentid' => $studentid,
    'courseid' => $courseid,
    'cmid' => $cmid,
    'sesskey' => sesskey(),
    'cancelurl' => $dashboardurl->out(false),
]);
echo $OUTPUT->footer();
