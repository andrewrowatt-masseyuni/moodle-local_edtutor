@local @local_edtutor
Feature: Complete escalated submissions
  In order to make sure students' work is submitted
  As support staff
  I need to see escalated submissions and mark them complete

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | manager1 | Manager   | One      |
      | tutor1   | Tutor     | One      |
      | student1 | Student   | One      |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "activities" exist:
      | activity | course | idnumber | name         | assignsubmission_onlinetext_enabled |
      | assign   | C1     | assign1  | Assignment 1 | 1                                   |
    And the following "local_edtutor > submissions" exist:
      | student  | tutor  | activity | onlinetext                     |
      | student1 | tutor1 | assign1  | Work needing manual submission |
    And the following "role assigns" exist:
      | user     | role    | contextlevel | reference |
      | manager1 | manager | System       |           |

  Scenario: Support staff view an escalated submission and mark it complete
    Given I am on the "local_edtutor > queue" page logged in as "manager1"
    Then I should see "Student One"
    And I should see "Assignment 1"
    When I follow "View submission"
    Then I should see "Work needing manual submission"
    And I should see "Pending manual submission"
    When I follow "Mark as completed"
    And I press "Mark as completed"
    Then I should see "The submission has been marked as completed"
    And I should see "Completed manually"
