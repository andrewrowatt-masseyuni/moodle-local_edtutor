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
 * Education Tutor dashboard: allocated students, courses and assignment statuses.
 *
 * @package    local_edtutor
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$context = context_system::instance();
require_login();

$tutorcaps = ['local/edtutor:submit', 'local/edtutor:loginas', 'local/edtutor:setpreferences'];
if (!has_any_capability($tutorcaps, $context)) {
    throw new required_capability_exception($context, 'local/edtutor:submit', 'nopermissions', '');
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/edtutor/dashboard.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('dashboard', 'local_edtutor'));
$PAGE->set_heading(get_string('dashboard', 'local_edtutor'));

$view = get_user_preferences('local_edtutor_dashboard_view', 'bystudent');
if (!in_array($view, ['bystudent', 'bycourse'], true)) {
    $view = 'bystudent';
}

$timeframe = get_user_preferences('local_edtutor_dashboard_timeframe', 'duesoon');
if (!in_array($timeframe, ['all', 'duesoon', 'overdue'], true)) {
    $timeframe = 'duesoon';
}

$dashboard = new \local_edtutor\output\dashboard(
    (int)$USER->id,
    $view,
    $timeframe,
    has_capability('local/edtutor:loginas', $context),
    has_capability('local/edtutor:submit', $context),
    has_capability('local/edtutor:setpreferences', $context)
);

echo $OUTPUT->header();
echo $OUTPUT->render($dashboard);
echo $OUTPUT->footer();
