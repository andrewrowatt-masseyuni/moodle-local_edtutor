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
use local_edtutor\manager;

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

        // Site admins may pick any user (the role is granted on save). Everyone else is
        // limited to users who already hold the education tutor role.
        if (manager::can_provision_tutor_role()) {
            $mform->addElement(
                'autocomplete',
                'tutorid',
                get_string('tutor', 'local_edtutor'),
                [],
                self::user_selector_options()
            );
        } else {
            $mform->addElement(
                'autocomplete',
                'tutorid',
                get_string('tutor', 'local_edtutor'),
                manager::get_tutor_role_user_options()
            );
        }
        $mform->addRule('tutorid', get_string('required'), 'required', null, 'client');

        $mform->addElement(
            'autocomplete',
            'studentids',
            get_string('students', 'local_edtutor'),
            [],
            self::user_selector_options(true)
        );
        $mform->addRule('studentids', get_string('required'), 'required', null, 'client');

        $this->add_action_buttons(false, get_string('addallocation', 'local_edtutor'));
    }

    /**
     * Validate the selected users and the uniqueness of the allocations.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);

        $tutorid = (int)($data['tutorid'] ?? 0);
        $studentids = self::clean_studentids($data['studentids'] ?? []);

        if ($tutorid && !$DB->record_exists('user', ['id' => $tutorid, 'deleted' => 0])) {
            $errors['tutorid'] = get_string('error:tutornotfound', 'local_edtutor');
        } else if ($tutorid && !manager::can_provision_tutor_role() && !manager::user_has_tutor_role($tutorid)) {
            $errors['tutorid'] = get_string('error:tutornotrole', 'local_edtutor');
        }

        if (empty($studentids)) {
            $errors['studentids'] = get_string('required');
        } else {
            foreach ($studentids as $studentid) {
                if (!$DB->record_exists('user', ['id' => $studentid, 'deleted' => 0])) {
                    $errors['studentids'] = get_string('error:studentnotfound', 'local_edtutor');
                    break;
                }
            }
            // Require at least one student who is not already allocated to this tutor.
            if (empty($errors['studentids']) && $tutorid) {
                $newstudents = array_filter($studentids, function ($studentid) use ($tutorid) {
                    return !allocation::allocation_exists($tutorid, $studentid);
                });
                if (empty($newstudents)) {
                    $errors['studentids'] = get_string('allocationsexist', 'local_edtutor');
                }
            }
        }

        return $errors;
    }

    /**
     * Normalise a submitted multi-select value into a list of unique, positive user ids.
     *
     * @param mixed $value The raw value submitted for the student selector.
     * @return int[]
     */
    public static function clean_studentids($value): array {
        $ids = array_map('intval', (array)$value);
        return array_values(array_unique(array_filter($ids, function ($id) {
            return $id > 0;
        })));
    }

    /**
     * Shared options for the AJAX user autocomplete selectors.
     *
     * @param bool $multiple Whether the selector should allow multiple users to be chosen.
     * @return array
     */
    protected static function user_selector_options(bool $multiple = false): array {
        return [
            'multiple' => $multiple,
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
