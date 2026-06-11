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
     * A tutor switches directly from one student to another without logging out.
     */
    public function test_switch_between_students(): void {
        global $USER;
        $this->resetAfterTest();

        [$tutor, $students] = $this->create_tutor_with_students(2);
        [$studenta, $studentb] = array_values($students);

        $this->setUser($tutor);
        loginas::loginas_student($studenta->id);
        $this->assertEquals($studenta->id, $USER->id);

        $sink = $this->redirectEvents();
        loginas::loginas_student($studentb->id);
        $events = $sink->get_events();
        $sink->close();

        $this->assertTrue(\core\session\manager::is_loggedinas());
        $this->assertEquals($studentb->id, $USER->id);
        $this->assertEquals($tutor->id, \core\session\manager::get_realuser()->id);
        $this->assertEquals(\context_system::instance(), $USER->loginascontext);

        $returned = array_filter($events, function ($event) {
            return $event instanceof \local_edtutor\event\loginas_returned;
        });
        $this->assertCount(1, $returned);
        $loginasevents = array_filter($events, function ($event) {
            return $event instanceof \core\event\user_loggedinas;
        });
        $this->assertCount(1, $loginasevents);
    }

    /**
     * Logging in as the student you are already logged in as is a no-op.
     */
    public function test_loginas_same_student_is_idempotent(): void {
        global $USER;
        $this->resetAfterTest();

        [$tutor, $students] = $this->create_tutor_with_students(1);
        $student = reset($students);

        $this->setUser($tutor);
        loginas::loginas_student($student->id);

        $sink = $this->redirectEvents();
        loginas::loginas_student($student->id);
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(0, $events);
        $this->assertEquals($student->id, $USER->id);
        $this->assertEquals($tutor->id, \core\session\manager::get_realuser()->id);
    }

    /**
     * Restoring the real user ends the login-as session without logging out.
     */
    public function test_restore_real_user(): void {
        global $USER;
        $this->resetAfterTest();

        [$tutor, $students] = $this->create_tutor_with_students(1);
        $student = reset($students);

        $this->setUser($tutor);
        $sesskey = sesskey();
        loginas::loginas_student($student->id);

        $sink = $this->redirectEvents();
        loginas::restore_real_user();
        $events = $sink->get_events();
        $sink->close();

        $this->assertFalse(\core\session\manager::is_loggedinas());
        $this->assertEquals($tutor->id, $USER->id);
        $this->assertArrayNotHasKey('REALUSER', $_SESSION);
        $this->assertArrayNotHasKey('REALSESSION', $_SESSION);
        $this->assertSame($sesskey, sesskey());

        $returned = array_filter($events, function ($event) {
            return $event instanceof \local_edtutor\event\loginas_returned;
        });
        $this->assertCount(1, $returned);
        $event = reset($returned);
        $this->assertEquals($tutor->id, $event->userid);
        $this->assertEquals($student->id, $event->relateduserid);
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
     * Create a tutor with the submit capability and allocated students.
     *
     * @param int $count Number of students to create and allocate.
     * @return array [tutor user record, array of student user records]
     */
    protected function create_tutor_with_students(int $count): array {
        $tutor = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        $systemcontext = \context_system::instance();
        assign_capability('local/edtutor:submit', CAP_ALLOW, $roleid, $systemcontext->id, true);
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
