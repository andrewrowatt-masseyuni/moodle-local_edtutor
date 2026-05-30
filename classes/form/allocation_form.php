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

use local_edtutor\allocation;

/**
 * Form for manually creating a tutor to student allocation.
 *
 * @package    local_edtutor
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class allocation_form extends \moodleform {
    /**
     * Define the form.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement(
            'autocomplete',
            'tutorid',
            get_string('tutor', 'local_edtutor'),
            [],
            self::user_selector_options()
        );
        $mform->addRule('tutorid', get_string('required'), 'required', null, 'client');

        $mform->addElement(
            'autocomplete',
            'studentid',
            get_string('student', 'local_edtutor'),
            [],
            self::user_selector_options()
        );
        $mform->addRule('studentid', get_string('required'), 'required', null, 'client');

        $this->add_action_buttons(false, get_string('addallocation', 'local_edtutor'));
    }

    /**
     * Validate the selected users and the uniqueness of the allocation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);

        $tutorid = (int)($data['tutorid'] ?? 0);
        $studentid = (int)($data['studentid'] ?? 0);

        if ($tutorid && !$DB->record_exists('user', ['id' => $tutorid, 'deleted' => 0])) {
            $errors['tutorid'] = get_string('error:tutornotfound', 'local_edtutor');
        }
        if ($studentid && !$DB->record_exists('user', ['id' => $studentid, 'deleted' => 0])) {
            $errors['studentid'] = get_string('error:studentnotfound', 'local_edtutor');
        }
        if ($tutorid && $studentid && allocation::allocation_exists($tutorid, $studentid)) {
            $errors['studentid'] = get_string('allocationexists', 'local_edtutor');
        }

        return $errors;
    }

    /**
     * Shared options for the AJAX user autocomplete selectors.
     *
     * @return array
     */
    protected static function user_selector_options(): array {
        return [
            'multiple' => false,
            'ajax' => 'core_user/form_user_selector',
            'valuehtmlcallback' => function ($userid) {
                global $OUTPUT;
                if (empty($userid) || $userid === '_qf__force_multiselect_submission') {
                    return false;
                }
                $context = \context_system::instance();
                $fields = \core_user\fields::for_name()->with_identity($context, false);
                $record = \core_user::get_user($userid, 'id ' . $fields->get_sql()->selects, IGNORE_MISSING);
                if (!$record) {
                    return false;
                }
                $user = (object)[
                    'id' => $record->id,
                    'fullname' => fullname($record, has_capability('moodle/site:viewfullnames', $context)),
                    'extrafields' => [],
                ];
                foreach ($fields->get_required_fields([\core_user\fields::PURPOSE_IDENTITY]) as $extrafield) {
                    $user->extrafields[] = (object)[
                        'name' => $extrafield,
                        'value' => s($record->$extrafield),
                    ];
                }
                return $OUTPUT->render_from_template('core_user/form_user_selector_suggestion', $user);
            },
        ];
    }
}
