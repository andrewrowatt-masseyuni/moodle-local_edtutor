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

namespace local_edtutor\form;

use local_edtutor\submission_service;

/**
 * Form for uploading the work to submit on behalf of a student.
 *
 * @package    local_edtutor
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission_form extends \moodleform {
    /**
     * Define the form.
     */
    public function definition() {
        global $CFG;
        $mform = $this->_form;

        $mform->addElement('hidden', 'studentid', $this->_customdata['studentid']);
        $mform->setType('studentid', PARAM_INT);

        $mform->addElement('hidden', 'cmid', $this->_customdata['cmid']);
        $mform->setType('cmid', PARAM_INT);

        $mform->addElement(
            'filemanager',
            'submissionfiles',
            get_string('submissionfiles', 'local_edtutor'),
            null,
            submission_service::file_options($CFG->maxbytes)
        );
        $mform->addHelpButton('submissionfiles', 'submissionfiles', 'local_edtutor');

        $mform->addElement(
            'editor',
            'onlinetext_editor',
            get_string('onlinetext', 'local_edtutor'),
            null,
            submission_service::editor_options()
        );
        $mform->setType('onlinetext_editor', PARAM_RAW);

        $this->add_action_buttons(true, get_string('submitonbehalf', 'local_edtutor'));
    }

    /**
     * Require at least one file or some online text.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $USER;
        $errors = parent::validation($data, $files);

        $draftid = $data['submissionfiles'] ?? 0;
        $areafiles = [];
        if ($draftid) {
            $usercontext = \context_user::instance($USER->id);
            $fs = get_file_storage();
            $areafiles = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftid, 'id', false);
        }
        $text = trim(html_to_text($data['onlinetext_editor']['text'] ?? ''));

        if (empty($areafiles) && $text === '') {
            $errors['submissionfiles'] = get_string('submissionempty', 'local_edtutor');
        }

        return $errors;
    }
}
