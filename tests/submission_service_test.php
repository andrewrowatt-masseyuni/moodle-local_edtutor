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

namespace local_edtutor;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/locallib.php');

/**
 * Tests for the on-behalf submission service.
 *
 * @package    local_edtutor
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_edtutor\submission_service
 */
final class submission_service_test extends \advanced_testcase {
    /**
     * A tutor with the on-behalf capability submits files and text automatically.
     */
    public function test_autosubmit_success(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'assignsubmission_onlinetext_enabled' => 1,
            'assignsubmission_file_enabled' => 1,
            'assignsubmission_file_maxfiles' => 3,
            'assignsubmission_file_maxsizebytes' => 0,
            'submissiondrafts' => 0,
        ]);
        $cm = get_fast_modinfo($course)->get_cm($assign->cmid);

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $tutor = $this->grant_tutor($course, $coursecontext);

        $this->setUser($tutor);
        $formdata = $this->build_formdata($tutor->id, 'My essay text', 'essay.txt');

        $submission = submission_service::create_and_submit($student->id, $cm, $formdata, $tutor->id);

        $this->assertEquals(submission::STATUS_SUBMITTED_AUTO, $submission->get('status'));
        $this->assertNull($submission->get('failurereason'));

        $assignsub = $DB->get_record(
            'assign_submission',
            ['assignment' => $cm->instance, 'userid' => $student->id]
        );
        $this->assertNotEmpty($assignsub);
        $this->assertEquals(ASSIGN_SUBMISSION_STATUS_SUBMITTED, $assignsub->status);

        $files = get_file_storage()->get_area_files(
            \context_module::instance($cm->id)->id,
            'assignsubmission_file',
            'submission_files',
            $assignsub->id,
            'id',
            false
        );
        $this->assertCount(1, $files);
    }

    /**
     * A closed assignment cannot be submitted automatically and is escalated.
     */
    public function test_escalates_when_closed(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'assignsubmission_onlinetext_enabled' => 1,
            'duedate' => time() - DAYSECS,
            'cutoffdate' => time() - DAYSECS,
            'submissiondrafts' => 0,
        ]);
        $cm = get_fast_modinfo($course)->get_cm($assign->cmid);

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $tutor = $this->grant_tutor($course, $coursecontext);

        $this->setUser($tutor);
        $formdata = (object)[
            'submissionfiles' => 0,
            'onlinetext_editor' => ['text' => 'Late work', 'format' => FORMAT_HTML, 'itemid' => 0],
        ];

        $submission = submission_service::create_and_submit($student->id, $cm, $formdata, $tutor->id);

        $this->assertEquals(submission::STATUS_ESCALATED, $submission->get('status'));
        $this->assertNotEmpty($submission->get('failurereason'));
    }

    /**
     * Without the on-behalf capability the submission is escalated rather than failing hard.
     */
    public function test_escalates_without_capability(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'assignsubmission_onlinetext_enabled' => 1,
            'submissiondrafts' => 0,
        ]);
        $cm = get_fast_modinfo($course)->get_cm($assign->cmid);

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $tutor = $this->getDataGenerator()->create_user();

        $this->setUser($tutor);
        $formdata = (object)[
            'submissionfiles' => 0,
            'onlinetext_editor' => ['text' => 'Work', 'format' => FORMAT_HTML, 'itemid' => 0],
        ];

        $submission = submission_service::create_and_submit($student->id, $cm, $formdata, $tutor->id);

        $this->assertEquals(submission::STATUS_ESCALATED, $submission->get('status'));
    }

    /**
     * Only enabled submission types are reported, and the file plugin's settings
     * (max files, max size and accepted types) are mirrored.
     */
    public function test_enabled_submission_types_mirrors_file_settings(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'assignsubmission_onlinetext_enabled' => 1,
            'assignsubmission_file_enabled' => 1,
            'assignsubmission_file_maxfiles' => 4,
            'assignsubmission_file_maxsizebytes' => 1048576,
            'assignsubmission_file_filetypes' => '.pdf,.docx',
        ]);
        $cm = get_fast_modinfo($course)->get_cm($assign->cmid);

        $types = submission_service::enabled_submission_types($cm);

        $this->assertTrue($types->fileenabled);
        $this->assertTrue($types->textenabled);
        $this->assertSame([], $types->unsupported);
        $this->assertEquals(4, $types->fileoptions['maxfiles']);
        $this->assertEquals(1048576, $types->fileoptions['maxbytes']);
        $this->assertEqualsCanonicalizing(['.pdf', '.docx'], $types->fileoptions['accepted_types']);
    }

    /**
     * A disabled submission type is not offered on the on-behalf form.
     */
    public function test_enabled_submission_types_omits_disabled_file(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'assignsubmission_onlinetext_enabled' => 1,
            'assignsubmission_file_enabled' => 0,
        ]);
        $cm = get_fast_modinfo($course)->get_cm($assign->cmid);

        $types = submission_service::enabled_submission_types($cm);

        $this->assertFalse($types->fileenabled);
        $this->assertTrue($types->textenabled);
        $this->assertSame([], $types->unsupported);
    }

    /**
     * Create a tutor with the on-behalf capability in the course.
     *
     * @param \stdClass $course
     * @param \context_course $coursecontext
     * @return \stdClass The tutor user record.
     */
    protected function grant_tutor(\stdClass $course, \context_course $coursecontext): \stdClass {
        $tutor = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('mod/assign:editothersubmission', CAP_ALLOW, $roleid, $coursecontext->id, true);
        assign_capability('mod/assign:view', CAP_ALLOW, $roleid, $coursecontext->id, true);
        role_assign($roleid, $tutor->id, $coursecontext->id);
        return $tutor;
    }

    /**
     * Build a draft file area and matching form data, as a tutor would submit.
     *
     * @param int $tutorid
     * @param string $text
     * @param string $filename
     * @return \stdClass
     */
    protected function build_formdata(int $tutorid, string $text, string $filename): \stdClass {
        $usercontext = \context_user::instance($tutorid);
        $draftitemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => $filename,
        ], 'Student work content');

        return (object)[
            'submissionfiles' => $draftitemid,
            'onlinetext_editor' => ['text' => $text, 'format' => FORMAT_HTML, 'itemid' => 0],
        ];
    }
}
