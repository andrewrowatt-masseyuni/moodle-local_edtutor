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

/**
 * Tests for site-level login-as for education tutors.
 *
 * @package    local_edtutor
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_edtutor\loginas
 */
final class loginas_test extends \advanced_testcase {
    /**
     * A tutor logs in as an allocated student at site level.
     */
    public function test_loginas_student(): void {
        global $USER;
        $this->resetAfterTest();

        [$tutor, $students] = $this->create_tutor_with_students(1);
        $student = reset($students);

        $this->setUser($tutor);

        $sink = $this->redirectEvents();
        loginas::loginas_student($student->id);
        $events = $sink->get_events();
        $sink->close();

        $this->assertTrue(\core\session\manager::is_loggedinas());
        $this->assertEquals($student->id, $USER->id);
        $this->assertEquals($tutor->id, \core\session\manager::get_realuser()->id);
        $this->assertEquals(\context_system::instance(), $USER->loginascontext);

        $loginasevents = array_filter($events, function ($event) {
            return $event instanceof \core\event\user_loggedinas;
        });
        $this->assertCount(1, $loginasevents);
    }

    /**
     * A tutor already in a login-as session cannot switch to another student.
     *
     * For security reasons the only way out of a login-as session is a full
     * logout followed by re-authentication.
     */
    public function test_loginas_while_loggedinas_throws(): void {
        global $USER;
        $this->resetAfterTest();

        [$tutor, $students] = $this->create_tutor_with_students(2);
        [$studenta, $studentb] = array_values($students);

        $this->setUser($tutor);
        loginas::loginas_student($studenta->id);
        $this->assertEquals($studenta->id, $USER->id);

        $sink = $this->redirectEvents();
        try {
            loginas::loginas_student($studentb->id);
            $this->fail('Expected moodle_exception was not thrown.');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString(
                get_string('error:alreadyloggedinas', 'local_edtutor'),
                $e->getMessage()
            );
        }
        $events = $sink->get_events();
        $sink->close();

        // The existing login-as session is untouched.
        $this->assertTrue(\core\session\manager::is_loggedinas());
        $this->assertEquals($studenta->id, $USER->id);
        $this->assertEquals($tutor->id, \core\session\manager::get_realuser()->id);

        $loginasevents = array_filter($events, function ($event) {
            return $event instanceof \core\event\user_loggedinas;
        });
        $this->assertCount(0, $loginasevents);
    }

    /**
     * Re-entering login-as for the current student is rejected like any other.
     */
    public function test_loginas_same_student_throws(): void {
        global $USER;
        $this->resetAfterTest();

        [$tutor, $students] = $this->create_tutor_with_students(1);
        $student = reset($students);

        $this->setUser($tutor);
        loginas::loginas_student($student->id);

        $sink = $this->redirectEvents();
        try {
            loginas::loginas_student($student->id);
            $this->fail('Expected moodle_exception was not thrown.');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString(
                get_string('error:alreadyloggedinas', 'local_edtutor'),
                $e->getMessage()
            );
        }
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(0, $events);
        $this->assertEquals($student->id, $USER->id);
        $this->assertEquals($tutor->id, \core\session\manager::get_realuser()->id);
    }

    /**
     * A user without the tutor capability cannot log in as a student.
     */
    public function test_rejects_without_capability(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->get_plugin_generator('local_edtutor')->create_allocation([
            'tutorid' => $user->id,
            'studentid' => $student->id,
        ]);

        $this->setUser($user);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error:cannotloginas', 'local_edtutor'));
        loginas::loginas_student($student->id);
    }

    /**
     * A tutor cannot log in as a student who is not allocated to them.
     */
    public function test_rejects_unallocated_student(): void {
        $this->resetAfterTest();

        [$tutor] = $this->create_tutor_with_students(1);
        $other = $this->getDataGenerator()->create_user();

        $this->setUser($tutor);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error:notallocated', 'local_edtutor'));
        loginas::loginas_student($other->id);
    }

    /**
     * A tutor cannot log in as a site administrator even if allocated.
     */
    public function test_rejects_site_admin(): void {
        global $DB;
        $this->resetAfterTest();

        [$tutor] = $this->create_tutor_with_students(1);
        $admin = get_admin();
        $this->getDataGenerator()->get_plugin_generator('local_edtutor')->create_allocation([
            'tutorid' => $tutor->id,
            'studentid' => $admin->id,
        ]);

        $this->setUser($tutor);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error:cannotloginas', 'local_edtutor'));
        loginas::loginas_student((int)$admin->id);
    }

    /**
     * A tutor cannot log in as themselves.
     */
    public function test_rejects_self(): void {
        $this->resetAfterTest();

        [$tutor] = $this->create_tutor_with_students(1);

        $this->setUser($tutor);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error:cannotloginas', 'local_edtutor'));
        loginas::loginas_student((int)$tutor->id);
    }

    /**
     * A tutor with the installed edtutor role can enter a student's course
     * during login-as without being enrolled in the course themselves.
     *
     * Core require_login() requires the real user behind a login-as session
     * to be enrolled in the course or pass is_viewing(); the edtutor role
     * carries moodle/course:view at system level to satisfy the latter.
     */
    public function test_tutor_can_enter_students_course_during_loginas(): void {
        global $DB, $USER;
        $this->resetAfterTest();

        [$tutor, $students] = $this->create_tutor_with_students(1);
        $student = reset($students);

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $coursecontext = \context_course::instance($course->id);

        // Without the installed role the real tutor fails core's gate.
        $this->assertFalse(is_viewing($coursecontext, $tutor));

        $edtutorrole = $DB->get_record('role', ['shortname' => 'edtutor'], '*', MUST_EXIST);
        role_assign($edtutorrole->id, $tutor->id, \context_system::instance()->id);

        $this->setUser($tutor);
        loginas::loginas_student($student->id);

        $realuser = \core\session\manager::get_realuser();
        $this->assertFalse(is_enrolled($coursecontext, $realuser->id, '', true));
        $this->assertTrue(is_viewing($coursecontext, $realuser));

        // The student's course loads for the logged-in-as session.
        require_login($course, false, null, false, true);
        $this->assertEquals($student->id, $USER->id);
    }

    /**
     * Create a tutor with the login-as capability and allocated students.
     *
     * @param int $count Number of students to create and allocate.
     * @return array [tutor user record, array of student user records]
     */
    protected function create_tutor_with_students(int $count): array {
        $tutor = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        $systemcontext = \context_system::instance();
        assign_capability('local/edtutor:loginas', CAP_ALLOW, $roleid, $systemcontext->id, true);
        role_assign($roleid, $tutor->id, $systemcontext->id);

        $generator = $this->getDataGenerator()->get_plugin_generator('local_edtutor');
        $students = [];
        for ($i = 0; $i < $count; $i++) {
            $student = $this->getDataGenerator()->create_user();
            $generator->create_allocation(['tutorid' => $tutor->id, 'studentid' => $student->id]);
            $students[$student->id] = $student;
        }

        return [$tutor, $students];
    }
}
