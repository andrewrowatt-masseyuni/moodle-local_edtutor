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

namespace local_edtutor\output;

use local_edtutor\manager;

/**
 * Education Tutor dashboard: allocated students, their courses and activity statuses.
 *
 * The by-student and by-course views are both exported so the page can switch
 * between them client side without a reload. Assignments and quizzes are
 * listed in due date order, with activities that have no due date grouped
 * below them.
 *
 * Each activity row carries a timeframe classification and every row, section
 * and card an initial hidden flag matching the active timeframe filter, so the
 * first render is already filtered and the local_edtutor/dashboard AMD module
 * can refilter client side without a reload.
 *
 * @package    local_edtutor
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard implements \core\output\named_templatable, \renderable {
    /** @var int Tutor user id. */
    protected int $tutorid;

    /** @var string Active view, 'bystudent' or 'bycourse'. */
    protected string $view;

    /** @var string Active timeframe filter, 'all', 'duesoon' or 'overdue'. */
    protected string $timeframe;

    /** @var bool Whether the tutor may log in as their allocated students. */
    protected bool $canloginas;

    /** @var bool Whether the tutor may submit on behalf of their allocated students. */
    protected bool $cansubmit;

    /** @var bool Whether the tutor may set preferences for their allocated students. */
    protected bool $cansetpreferences;

    /**
     * Constructor.
     *
     * @param int $tutorid Tutor user id.
     * @param string $view Active view, 'bystudent' or 'bycourse'.
     * @param string $timeframe Active timeframe filter, 'all', 'duesoon' or 'overdue'.
     * @param bool $canloginas Whether the tutor may log in as their allocated students.
     * @param bool $cansubmit Whether the tutor may submit on behalf of their allocated students.
     * @param bool $cansetpreferences Whether the tutor may set preferences for their allocated students.
     */
    public function __construct(
        int $tutorid,
        string $view,
        string $timeframe,
        bool $canloginas,
        bool $cansubmit,
        bool $cansetpreferences
    ) {
        $this->tutorid = $tutorid;
        $this->view = $view;
        $this->timeframe = $timeframe;
        $this->canloginas = $canloginas;
        $this->cansubmit = $cansubmit;
        $this->cansetpreferences = $cansetpreferences;
    }

    /**
     * Name of the template used to render the dashboard.
     *
     * @param \renderer_base $renderer
     * @return string
     */
    public function get_template_name(\renderer_base $renderer): string {
        return 'local_edtutor/dashboard';
    }

    /**
     * Export the data for both dashboard views.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $students = manager::get_allocated_students($this->tutorid);
        $lastaccess = manager::get_last_course_access(array_keys($students));

        $upcomingdays = (int)get_config('local_edtutor', 'upcomingdays') ?: 14;
        $cutoff = time() + $upcomingdays * DAYSECS;

        $assigns = [];
        $quizzes = [];
        $studentcards = [];
        $pivot = [];
        $filterstudents = [];
        $allcourses = [];

        foreach ($students as $student) {
            $studentid = (int)$student->id;
            $loginasurl = (new \moodle_url(
                '/local/edtutor/loginas.php',
                ['userid' => $studentid, 'sesskey' => sesskey()]
            ))->out(false);

            $studentoverdue = false;
            $coursesdata = [];
            foreach (manager::get_student_courses($studentid) as $course) {
                $courseid = (int)$course->id;
                $coursename = format_string($course->fullname);
                // Login-as links from a course row land in that course.
                $courseloginasurl = (new \moodle_url('/local/edtutor/loginas.php', [
                    'userid' => $studentid,
                    'courseid' => $courseid,
                    'sesskey' => sesskey(),
                ]))->out(false);

                $activitiesdata = [];
                foreach (manager::get_student_course_activities($courseid, $studentid) as $cm) {
                    $isassign = $cm->modname === 'assign';

                    // One api instance / base record per course module, shared across students and views.
                    if ($isassign) {
                        if (!isset($assigns[$cm->id])) {
                            $assigns[$cm->id] = new \assign($cm->context, $cm, $cm->get_course());
                        }
                        $status = manager::get_assignment_status($assigns[$cm->id], $studentid);
                    } else {
                        if (!isset($quizzes[$cm->id])) {
                            $quizzes[$cm->id] = \mod_quiz\quiz_settings::create((int)$cm->instance)->get_quiz();
                        }
                        $status = manager::get_quiz_status($quizzes[$cm->id], $studentid);
                    }
                    $studentoverdue = $studentoverdue || $status->overdue;

                    $submiturl = (new \moodle_url('/local/edtutor/submit.php', [
                        'studentid' => $studentid,
                        'courseid' => $courseid,
                        'cmid' => $cm->id,
                    ]))->out(false);

                    $rowtimeframe = self::timeframe_for(
                        $status->submitted,
                        $status->overdue,
                        (int)$status->duedate,
                        $cutoff
                    );
                    $statusdata = [
                        'submitted' => $status->submitted,
                        'hasduedate' => $status->duedate > 0,
                        'duedate' => $status->duedate > 0 ? userdate($status->duedate) : '',
                        'extension' => $status->extension,
                        'overdue' => $status->overdue,
                        'submiturl' => $submiturl,
                        'submittable' => $isassign,
                        'timeframe' => $rowtimeframe,
                        'hidden' => !self::timeframe_visible($rowtimeframe, $this->timeframe),
                    ];

                    $activitiesdata[] = array_merge($statusdata, [
                        'cmid' => $cm->id,
                        'name' => $cm->get_formatted_name(),
                        'iconclass' => $isassign ? 'fa-solid fa-file-arrow-up' : 'fa-solid fa-list-check',
                        'sortdue' => $status->duedate,
                    ]);

                    if (!isset($pivot[$courseid])) {
                        $pivot[$courseid] = [
                            'id' => $courseid,
                            'fullname' => $coursename,
                            'overdue' => false,
                            'activities' => [],
                        ];
                    }
                    if (!isset($pivot[$courseid]['activities'][$cm->id])) {
                        // The by-course view is shared by all students, so it is ordered
                        // by the activity's base due date, without per-student overrides.
                        $custom = (array)($cm->customdata ?? []);
                        $basedue = (int)($isassign ? ($custom['duedate'] ?? 0) : ($custom['timeclose'] ?? 0));
                        $pivot[$courseid]['activities'][$cm->id] = [
                            'cmid' => $cm->id,
                            'name' => $cm->get_formatted_name(),
                            'iconclass' => $isassign ? 'fa-solid fa-file-arrow-up' : 'fa-solid fa-list-check',
                            'submittable' => $isassign,
                            'sortdue' => $basedue,
                            'students' => [],
                        ];
                    }
                    $pivot[$courseid]['overdue'] = $pivot[$courseid]['overdue'] || $status->overdue;
                    $pivot[$courseid]['activities'][$cm->id]['students'][] = array_merge($statusdata, [
                        'id' => $studentid,
                        'fullname' => fullname($student),
                        'username' => $student->username,
                        'loginasurl' => $courseloginasurl,
                    ]);
                }

                [$withdue, $nodue] = self::split_by_duedate($activitiesdata);
                $courseaccess = $lastaccess['percourse'][$studentid . '-' . $courseid] ?? 0;
                // A section with no activities at all only shows under the unfiltered timeframe;
                // the JS filter pass in local_edtutor/dashboard uses the same rule.
                $sectionvisible = self::count_visible($activitiesdata) > 0
                    || ($this->timeframe === 'all' && empty($activitiesdata));
                $coursesdata[] = [
                    'id' => $courseid,
                    'fullname' => $coursename,
                    'loginasurl' => $courseloginasurl,
                    'lastaccess' => $courseaccess ? userdate($courseaccess) : get_string('never'),
                    'hasactivities' => !empty($activitiesdata),
                    'hasdueactivities' => !empty($withdue),
                    'activities' => $withdue,
                    'hasnoduedate' => !empty($nodue),
                    'noduedateactivities' => $nodue,
                    'noduehidden' => self::count_visible($nodue) === 0,
                    'hidden' => !$sectionvisible,
                ];
                $allcourses[$courseid] = $coursename;
            }

            $latestaccess = $lastaccess['latest'][$studentid] ?? 0;
            $cardvisible = self::count_visible($coursesdata) > 0
                || ($this->timeframe === 'all' && empty($coursesdata));
            $studentcards[] = [
                'id' => $studentid,
                'fullname' => fullname($student),
                'username' => $student->username,
                'lastaccess' => $latestaccess ? userdate($latestaccess) : get_string('never'),
                'overdue' => $studentoverdue,
                'loginasurl' => $loginasurl,
                'hascourses' => !empty($coursesdata),
                'courses' => $coursesdata,
                'hidden' => !$cardvisible,
            ];
            $filterstudents[] = [
                'id' => $studentid,
                'label' => fullname($student) . ' (' . $student->username . ')',
            ];
        }

        // Order the by-course view by course name and its activities by base due date.
        $coursenames = array_map(function (array $course): string {
            return $course['fullname'];
        }, $pivot);
        \core_collator::asort($coursenames);
        $coursecards = [];
        // An activity block is hidden when the timeframe filter hides all of its student rows,
        // and a course card when all of its activity blocks are hidden.
        $markhidden = function (array $activity): array {
            $activity['hidden'] = self::count_visible($activity['students']) === 0;
            return $activity;
        };
        foreach (array_keys($coursenames) as $courseid) {
            $coursecard = $pivot[$courseid];
            [$withdue, $nodue] = self::split_by_duedate(array_values($coursecard['activities']));
            $withdue = array_map($markhidden, $withdue);
            $nodue = array_map($markhidden, $nodue);
            $coursecard['hasactivities'] = !empty($coursecard['activities']);
            $coursecard['hasdueactivities'] = !empty($withdue);
            $coursecard['activities'] = $withdue;
            $coursecard['hasnoduedate'] = !empty($nodue);
            $coursecard['noduedateactivities'] = $nodue;
            $coursecard['noduehidden'] = self::count_visible($nodue) === 0;
            $coursecard['hidden'] = self::count_visible(array_merge($withdue, $nodue)) === 0;
            $coursecards[] = $coursecard;
        }

        \core_collator::asort($allcourses);
        $filtercourses = [];
        foreach ($allcourses as $courseid => $coursefullname) {
            $filtercourses[] = ['id' => $courseid, 'fullname' => $coursefullname];
        }

        return [
            'bystudentactive' => $this->view !== 'bycourse',
            'bycourseactive' => $this->view === 'bycourse',
            'canloginas' => $this->canloginas,
            'cansubmit' => $this->cansubmit,
            'cansetpreferences' => $this->cansetpreferences,
            'preferencesurl' => (new \moodle_url('/local/edtutor/preferences.php'))->out(false),
            'hasstudents' => !empty($studentcards),
            'students' => $studentcards,
            'hascourses' => !empty($coursecards),
            'courses' => $coursecards,
            'filterstudents' => $filterstudents,
            'filtercourses' => $filtercourses,
            'tfallselected' => $this->timeframe === 'all',
            'tfduesoonselected' => $this->timeframe === 'duesoon',
            'tfoverdueselected' => $this->timeframe === 'overdue',
            'bystudentempty' => !empty($studentcards) && self::count_visible($studentcards) === 0,
            'bycourseempty' => !empty($coursecards) && self::count_visible($coursecards) === 0,
        ];
    }

    /**
     * Classify an activity row for the timeframe filter.
     *
     * @param bool $submitted Whether the student has submitted.
     * @param bool $overdue Whether the activity is overdue for the student.
     * @param int $duedate Effective due date timestamp, 0 when none.
     * @param int $cutoff Latest due date timestamp that still counts as upcoming.
     * @return string One of 'submitted', 'overdue', 'upcoming' or 'future'.
     */
    private static function timeframe_for(bool $submitted, bool $overdue, int $duedate, int $cutoff): string {
        if ($submitted) {
            return 'submitted';
        }
        if ($overdue) {
            return 'overdue';
        }
        if ($duedate > 0 && $duedate <= $cutoff) {
            return 'upcoming';
        }
        // Due more than the configured window away, or no due date at all.
        return 'future';
    }

    /**
     * Whether a row with the given classification is visible under a timeframe filter.
     *
     * Mirrors the client side rule in the local_edtutor/dashboard AMD module.
     *
     * @param string $rowtimeframe Row classification from timeframe_for().
     * @param string $filter Active filter, 'all', 'duesoon' or 'overdue'.
     * @return bool
     */
    private static function timeframe_visible(string $rowtimeframe, string $filter): bool {
        if ($filter === 'all') {
            return true;
        }
        if ($filter === 'overdue') {
            return $rowtimeframe === 'overdue';
        }
        return $rowtimeframe === 'overdue' || $rowtimeframe === 'upcoming';
    }

    /**
     * Count the rows that are not flagged hidden.
     *
     * @param array $rows Context arrays, each optionally carrying a hidden flag.
     * @return int
     */
    private static function count_visible(array $rows): int {
        return count(array_filter($rows, function (array $row): bool {
            return empty($row['hidden']);
        }));
    }

    /**
     * Split activity rows into a due-date-ordered group and a no-due-date group.
     *
     * @param array $activities Activity context arrays, each with a sortdue timestamp.
     * @return array Two arrays: activities with a due date (ascending) and those without one.
     */
    private static function split_by_duedate(array $activities): array {
        $withdue = array_values(array_filter($activities, function (array $activity): bool {
            return $activity['sortdue'] > 0;
        }));
        usort($withdue, function (array $a, array $b): int {
            return $a['sortdue'] <=> $b['sortdue'];
        });
        $nodue = array_values(array_filter($activities, function (array $activity): bool {
            return $activity['sortdue'] == 0;
        }));
        return [$withdue, $nodue];
    }
}
