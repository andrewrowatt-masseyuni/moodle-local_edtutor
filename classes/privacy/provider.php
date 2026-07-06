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

namespace local_edtutor\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\plugin\provider as request_plugin_provider;
use core_privacy\local\request\transform;
use core_privacy\local\request\user_preference_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for Education tutor submissions.
 *
 * All plugin data is held against the system context.
 *
 * @package    local_edtutor
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements core_userlist_provider, metadata_provider, request_plugin_provider, user_preference_provider {
    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_edtutor_allocation', [
            'tutorid' => 'privacy:metadata:local_edtutor_allocation:tutorid',
            'studentid' => 'privacy:metadata:local_edtutor_allocation:studentid',
        ], 'privacy:metadata:local_edtutor_allocation');

        $collection->add_database_table('local_edtutor_submission', [
            'studentid' => 'privacy:metadata:local_edtutor_submission:studentid',
            'tutorid' => 'privacy:metadata:local_edtutor_submission:tutorid',
            'onlinetext' => 'privacy:metadata:local_edtutor_submission:onlinetext',
            'completedby' => 'privacy:metadata:local_edtutor_submission:completedby',
        ], 'privacy:metadata:local_edtutor_submission');

        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:local_edtutor_files');

        $collection->add_user_preference(
            'local_edtutor_dashboard_view',
            'privacy:metadata:preference:local_edtutor_dashboard_view'
        );

        $collection->add_user_preference(
            'local_edtutor_dashboard_timeframe',
            'privacy:metadata:preference:local_edtutor_dashboard_timeframe'
        );

        return $collection;
    }

    /**
     * Export the user preferences held by this plugin.
     *
     * @param int $userid
     */
    public static function export_user_preferences(int $userid): void {
        $view = get_user_preferences('local_edtutor_dashboard_view', null, $userid);
        if ($view !== null) {
            writer::export_user_preference(
                'local_edtutor',
                'local_edtutor_dashboard_view',
                $view,
                get_string('privacy:metadata:preference:local_edtutor_dashboard_view', 'local_edtutor')
            );
        }

        $timeframe = get_user_preferences('local_edtutor_dashboard_timeframe', null, $userid);
        if ($timeframe !== null) {
            writer::export_user_preference(
                'local_edtutor',
                'local_edtutor_dashboard_timeframe',
                $timeframe,
                get_string('privacy:metadata:preference:local_edtutor_dashboard_timeframe', 'local_edtutor')
            );
        }
    }

    /**
     * Return the system context if the user appears in any plugin data.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();

        $hasdata = $DB->record_exists_select(
            'local_edtutor_allocation',
            'tutorid = :t OR studentid = :s',
            ['t' => $userid, 's' => $userid]
        )
            || $DB->record_exists_select(
                'local_edtutor_submission',
                'tutorid = :t2 OR studentid = :s2 OR completedby = :c',
                ['t2' => $userid, 's2' => $userid, 'c' => $userid]
            );

        if ($hasdata) {
            $contextlist->add_from_sql(
                'SELECT id FROM {context} WHERE contextlevel = :level',
                ['level' => CONTEXT_SYSTEM]
            );
        }

        return $contextlist;
    }

    /**
     * Find users who have data in the given context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        $userlist->add_from_sql('tutorid', 'SELECT tutorid FROM {local_edtutor_allocation}', []);
        $userlist->add_from_sql('studentid', 'SELECT studentid FROM {local_edtutor_allocation}', []);
        $userlist->add_from_sql('tutorid', 'SELECT tutorid FROM {local_edtutor_submission}', []);
        $userlist->add_from_sql('studentid', 'SELECT studentid FROM {local_edtutor_submission}', []);
        $userlist->add_from_sql(
            'completedby',
            'SELECT completedby FROM {local_edtutor_submission} WHERE completedby > 0',
            []
        );
    }

    /**
     * Export all plugin data for the approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_SYSTEM) {
                continue;
            }

            $allocations = $DB->get_records_select(
                'local_edtutor_allocation',
                'tutorid = :t OR studentid = :s',
                ['t' => $user->id, 's' => $user->id]
            );
            if ($allocations) {
                $data = array_map(function ($a) {
                    return ['tutorid' => $a->tutorid, 'studentid' => $a->studentid];
                }, array_values($allocations));
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_edtutor'), get_string('allocations', 'local_edtutor')],
                    (object)['allocations' => $data]
                );
            }

            $submissions = $DB->get_records_select(
                'local_edtutor_submission',
                'tutorid = :t OR studentid = :s OR completedby = :c',
                ['t' => $user->id, 's' => $user->id, 'c' => $user->id]
            );
            foreach ($submissions as $submission) {
                $subcontext = [
                    get_string('pluginname', 'local_edtutor'),
                    get_string('mysubmissions', 'local_edtutor'),
                    $submission->id,
                ];
                writer::with_context($context)->export_data($subcontext, (object)[
                    'studentid' => $submission->studentid,
                    'tutorid' => $submission->tutorid,
                    'onlinetext' => $submission->onlinetext,
                    'status' => $submission->status,
                    'failurereason' => $submission->failurereason,
                    'completedby' => $submission->completedby,
                    'timecreated' => transform::datetime($submission->timecreated),
                ]);
                writer::with_context($context)->export_area_files(
                    $subcontext,
                    'local_edtutor',
                    'submission',
                    $submission->id
                );
            }
        }
    }

    /**
     * Delete all plugin data in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }
        get_file_storage()->delete_area_files($context->id, 'local_edtutor', 'submission');
        $DB->delete_records('local_edtutor_allocation');
        $DB->delete_records('local_edtutor_submission');
    }

    /**
     * Delete plugin data for one user across the approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_SYSTEM) {
                continue;
            }
            self::delete_for_users($context, [$user->id]);
        }
    }

    /**
     * Delete plugin data for several users in a context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        self::delete_for_users($context, $userlist->get_userids());
    }

    /**
     * Remove (or anonymise) the data for a set of users.
     *
     * Submissions owned by a student are deleted with their files; where the user is only the
     * acting tutor or the completer, that reference is anonymised so the student record survives.
     *
     * @param \context $context
     * @param int[] $userids
     */
    protected static function delete_for_users(\context $context, array $userids): void {
        global $DB;
        if (empty($userids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'ina');
        [$insql2, $params2] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'inb');
        $fs = get_file_storage();

        $DB->delete_records_select(
            'local_edtutor_allocation',
            "tutorid $insql OR studentid $insql2",
            array_merge($params, $params2)
        );

        $studentsubs = $DB->get_records_select('local_edtutor_submission', "studentid $insql", $params, '', 'id');
        foreach ($studentsubs as $sub) {
            $fs->delete_area_files($context->id, 'local_edtutor', 'submission', $sub->id);
        }
        $DB->delete_records_select('local_edtutor_submission', "studentid $insql", $params);

        $DB->set_field_select('local_edtutor_submission', 'tutorid', 0, "tutorid $insql", $params);
        $DB->set_field_select('local_edtutor_submission', 'completedby', 0, "completedby $insql", $params);
    }
}
