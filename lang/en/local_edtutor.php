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
 * Language strings for Education tutor submissions.
 *
 * @package    local_edtutor
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addallocation'] = 'Add allocation';
$string['allocation'] = 'Allocation';
$string['allocationadded'] = 'Allocation added';
$string['allocationaddednorole'] = 'Allocation added, but the education tutor role "{$a}" was not found. Create the role and assign it to the tutor, or update the plugin setting.';
$string['allocationexists'] = 'That student is already allocated to this tutor';
$string['allocationremoved'] = 'Allocation removed';
$string['allocations'] = 'Allocations';
$string['allocationsadded'] = '{$a} allocations added';
$string['allocationsexist'] = 'All of the selected students are already allocated to this tutor';
$string['backtotutorarea'] = 'Back to the tutor area';
$string['completedby'] = 'Completed by';
$string['completedon'] = 'Completed on';
$string['completionnotes'] = 'Notes';
$string['completionnotes_help'] = 'Optional notes about how this submission was completed.';
$string['confirmremoveallocation'] = 'Remove the allocation of {$a->student} to {$a->tutor}?';
$string['createdby'] = 'Created by';
$string['createdon'] = 'Created on';
$string['edtutor:loginas'] = 'Log in as allocated students at site level';
$string['edtutor:manageallocations'] = 'Manage tutor and student allocations';
$string['edtutor:processsubmissions'] = 'Process and complete escalated submissions';
$string['edtutor:submit'] = 'Submit assignments on behalf of allocated students';
$string['edtutormanagerrole'] = 'Education tutor manager';
$string['edtutormanagerrole_desc'] = 'Education tutor managers can manage which students are allocated to each education tutor, and view and complete escalated submissions.';
$string['edtutorrole'] = 'Education tutor';
$string['edtutorrole_desc'] = 'Education tutors can submit assignments on behalf of the students allocated to them.';
$string['error:alreadycompleted'] = 'This submission has already been completed.';
$string['error:cannotloginas'] = 'You cannot log in as this user.';
$string['error:cannotview'] = 'You do not have permission to view this submission.';
$string['error:invalidassignment'] = 'Invalid assignment selection.';
$string['error:invalidstudent'] = 'Invalid student selection.';
$string['error:notallocated'] = 'You are not allocated to this student.';
$string['error:studentnotfound'] = 'Please select a valid student.';
$string['error:submissionnotfound'] = 'Submission not found.';
$string['error:tutornotfound'] = 'Please select a valid tutor.';
$string['error:tutornotrole'] = 'The selected user does not have the education tutor role.';
$string['escalation_body'] = 'A submission made by tutor {$a->tutor} on behalf of student {$a->student} for "{$a->assignment}" in {$a->course} could not be submitted automatically.

Reason: {$a->reason}

Please review the submission, complete it manually, and mark it as completed here: {$a->url}';
$string['escalation_small'] = 'A submission for {$a->student} needs manual completion.';
$string['escalation_subject'] = 'Tutor submission needs manual completion: {$a->student}';
$string['event_loginas_returned'] = 'Tutor returned to own account from login-as';
$string['event_submission_autosubmitted'] = 'On-behalf submission submitted automatically';
$string['event_submission_completed'] = 'On-behalf submission completed manually';
$string['event_submission_created'] = 'On-behalf submission created';
$string['event_submission_escalated'] = 'On-behalf submission escalated';
$string['gotoassignment'] = 'Go to the assignment';
$string['loginasstudent'] = 'Log in as the student';
$string['loginasstudentcurrent'] = '{$a} ✓';
$string['loginasstudentname'] = 'Login as {$a}';
$string['manageallocations'] = 'Manage allocations';
$string['markcomplete'] = 'Mark as completed';
$string['markcompleteheading'] = 'Mark submission as completed';
$string['messageprovider:escalation'] = 'Submission needs manual completion';
$string['mysubmissions'] = 'My on-behalf submissions';
$string['noallocations'] = 'There are no allocations yet.';
$string['noassignmentsincourse'] = 'There are no assignments in the selected course.';
$string['nocoursesforstudent'] = 'The selected student is not enrolled in any courses.';
$string['nofiles'] = 'No files were uploaded.';
$string['nostudentsallocated'] = 'You do not have any students allocated to you. Contact the course coordinator if this is unexpected.';
$string['nosubmissions'] = 'You have not made any submissions yet.';
$string['notext'] = 'No online text was entered.';
$string['notutorsavailable'] = 'There are no education tutors to choose from yet. A site administrator can set one up by adding an allocation, or by assigning the education tutor role.';
$string['onlinetext'] = 'Online text';
$string['outcome_escalated'] = 'The submission could not be made automatically and has been sent to support staff to complete. Reason: {$a}';
$string['outcome_submitted'] = 'The submission was made automatically on behalf of {$a}.';
$string['pluginname'] = 'Education tutor submissions';
$string['privacy:metadata:local_edtutor_allocation'] = 'Allocations of students to education tutors.';
$string['privacy:metadata:local_edtutor_allocation:studentid'] = 'The user id of the allocated student.';
$string['privacy:metadata:local_edtutor_allocation:tutorid'] = 'The user id of the tutor.';
$string['privacy:metadata:local_edtutor_files'] = 'Files uploaded by tutors as part of on-behalf submissions.';
$string['privacy:metadata:local_edtutor_submission'] = 'Submissions made on behalf of students by education tutors.';
$string['privacy:metadata:local_edtutor_submission:completedby'] = 'The user id of the support staff member who completed the submission manually.';
$string['privacy:metadata:local_edtutor_submission:onlinetext'] = 'The online text content of the submission.';
$string['privacy:metadata:local_edtutor_submission:studentid'] = 'The user id of the student the work was submitted for.';
$string['privacy:metadata:local_edtutor_submission:tutorid'] = 'The user id of the tutor who created the submission.';
$string['queueempty'] = 'There are no submissions awaiting manual completion.';
$string['queueheading'] = 'Submissions awaiting manual completion';
$string['reason'] = 'Reason';
$string['reason_exception'] = 'An unexpected error occurred: {$a}';
$string['reason_manual'] = 'Routed to support staff for manual submission.';
$string['reason_nocapability'] = 'The tutor does not have permission to submit on behalf of students in this activity.';
$string['reason_nofileplugin'] = 'File submissions are not enabled for this assignment, so it must be completed manually.';
$string['reason_notassign'] = 'The selected activity is not a standard assignment.';
$string['reason_notextplugin'] = 'Online text is not enabled for this assignment, so it must be completed manually.';
$string['reason_notopen'] = 'The assignment is not open for submission (it may be closed or past its cut-off date).';
$string['reason_restricted'] = 'Access restrictions on this assignment prevent the student from reaching it, so it must be completed manually.';
$string['reason_savefailed'] = 'Saving the submission failed: {$a}';
$string['reason_teamsubmission'] = 'This assignment uses group submissions, which must be completed manually.';
$string['returnedtomyaccount'] = 'You have returned to your own account.';
$string['returntomyaccount'] = 'Return to my account';
$string['selectassignment'] = 'Assignment';
$string['selectcourse'] = 'Course';
$string['selectstudent'] = 'Student';
$string['selectstudent_help'] = 'Choose one of the students allocated to you.';
$string['selectstudentuser'] = 'Select student';
$string['selecttutor'] = 'Select tutor';
$string['settings:roleshortname'] = 'Education tutor role shortname';
$string['settings:roleshortname_desc'] = 'Shortname of the system role used to identify education tutors. This role needs local/edtutor:submit, local/edtutor:loginas, mod/assign:editothersubmission and moodle/site:viewuseridentity set to Allow. The plugin creates a suitable "Education tutor" role (shortname edtutor) when it is installed.';
$string['settings:supportcourseid'] = 'Tutor support course id';
$string['settings:supportcourseid_desc'] = 'Optional course id of the education tutor support site. When set, links to the tutor area are added to that course.';
$string['settings:supportstaff'] = 'Support staff';
$string['settings:supportstaff_desc'] = 'Usernames of the staff who are notified when a submission must be completed manually, one per line. This list only controls who is notified; viewing and completing queued submissions requires the local/edtutor:processsubmissions capability (granted by the Education tutor manager role).';
$string['status'] = 'Status';
$string['status_cancelled'] = 'Cancelled';
$string['status_completed'] = 'Completed manually';
$string['status_draft'] = 'Draft';
$string['status_escalated'] = 'Pending manual submission';
$string['status_submitted_auto'] = 'Submitted automatically';
$string['student'] = 'Student';
$string['students'] = 'Students';
$string['submissioncompleted'] = 'The submission has been marked as completed.';
$string['submissiondetails'] = 'Submission details';
$string['submissionempty'] = 'Add at least one file or some online text before submitting.';
$string['submissionfiles'] = 'Files';
$string['submissionfiles_help'] = 'Upload the file(s) that make up the student\'s submission.';
$string['submissionqueue'] = 'Submission queue';
$string['submitonbehalf'] = 'Submit on behalf of student';
$string['submittedfiles'] = 'Submitted files';
$string['target'] = 'Target assignment';
$string['tutor'] = 'Tutor';
$string['tutorarea'] = 'Submit on behalf of a student';
$string['unsupportedsubmissiontype'] = 'This assignment requires a "{$a}" submission which is currently not supported. You can still proceed, but the submission will need to be handled by a support staff member.';
$string['viewsubmission'] = 'View submission';
