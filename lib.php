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
 * Callback implementations for Education tutor submissions.
 *
 * @package    local_edtutor
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add a link to the tutor area on the configured support course (or any course when none is set).
 *
 * @param navigation_node $node Course navigation node.
 * @param stdClass $course Course record.
 * @param context_course $context Course context.
 */
function local_edtutor_extend_navigation_course(navigation_node $node, stdClass $course, context_course $context) {
    if (!has_capability('local/edtutor:submit', context_system::instance())) {
        return;
    }
    $supportcourseid = (int)get_config('local_edtutor', 'supportcourseid');
    if ($supportcourseid && (int)$course->id !== $supportcourseid) {
        return;
    }
    $node->add(
        get_string('tutorarea', 'local_edtutor'),
        new moodle_url('/local/edtutor/index.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_edtutor',
        new pix_icon('i/users', '')
    );
}

/**
 * User preferences that the user may update directly, e.g. via the core_user AJAX repository.
 *
 * @return array[] Preference definitions keyed by preference name.
 */
function local_edtutor_user_preferences(): array {
    return [
        'local_edtutor_dashboard_view' => [
            'type' => PARAM_ALPHA,
            'null' => NULL_NOT_ALLOWED,
            'default' => 'bystudent',
            'choices' => ['bystudent', 'bycourse'],
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
    ];
}

/**
 * Serve the files stored against a submission record.
 *
 * @param stdClass $course Course object.
 * @param stdClass $cm Course module object.
 * @param context $context Context.
 * @param string $filearea File area.
 * @param array $args Extra arguments (itemid, path, filename).
 * @param bool $forcedownload Whether or not force download.
 * @param array $options Additional options affecting the file serving.
 * @return bool False if the file was not found.
 */
function local_edtutor_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $USER;

    if ($context->contextlevel !== CONTEXT_SYSTEM) {
        return false;
    }
    if ($filearea !== \local_edtutor\submission::FILE_AREA) {
        return false;
    }

    require_login();

    $itemid = (int)array_shift($args);
    $submission = \local_edtutor\submission::get_record(['id' => $itemid]);
    if (!$submission) {
        return false;
    }

    // Only the tutor who created the submission, or staff who may process it, can fetch the files.
    if (
        (int)$USER->id !== $submission->get('tutorid')
            && !\local_edtutor\manager::can_process((int)$USER->id, context_system::instance())
    ) {
        return false;
    }

    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_edtutor', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, null, 0, $forcedownload, $options);
}
