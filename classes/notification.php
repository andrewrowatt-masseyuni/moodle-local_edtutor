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

use core\message\message;

/**
 * Sends notifications to the configured support staff.
 *
 * @package    local_edtutor
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notification {
    /**
     * Notify support staff that a submission needs to be completed manually.
     *
     * @param submission $submission The escalated submission record.
     */
    public static function notify_escalation(submission $submission): void {
        $recipients = manager::get_support_staff_userids();
        if (empty($recipients)) {
            return;
        }

        $student = \core_user::get_user($submission->get('studentid'));
        $tutor = \core_user::get_user($submission->get('tutorid'));
        $course = get_course($submission->get('courseid'));
        $cm = get_coursemodule_from_id('assign', $submission->get('cmid'), 0, false, IGNORE_MISSING);
        $url = new \moodle_url('/local/edtutor/view.php', ['id' => $submission->get('id')]);

        $a = (object)[
            'student' => $student ? fullname($student) . ' (' . $student->username . ')' : '',
            'tutor' => $tutor ? fullname($tutor) : '',
            'assignment' => $cm ? format_string($cm->name) : '',
            'course' => format_string($course->fullname),
            'reason' => (string)$submission->get('failurereason'),
            'url' => $url->out(false),
        ];

        $subject = get_string('escalation_subject', 'local_edtutor', $a);
        $body = get_string('escalation_body', 'local_edtutor', $a);
        $small = get_string('escalation_small', 'local_edtutor', $a);

        foreach ($recipients as $userid) {
            $message = new message();
            $message->component = 'local_edtutor';
            $message->name = 'escalation';
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $userid;
            $message->subject = $subject;
            $message->fullmessage = $body;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = text_to_html($body);
            $message->smallmessage = $small;
            $message->notification = 1;
            $message->courseid = $course->id;
            $message->contexturl = $url->out(false);
            $message->contexturlname = get_string('viewsubmission', 'local_edtutor');
            message_send($message);
        }
    }
}
