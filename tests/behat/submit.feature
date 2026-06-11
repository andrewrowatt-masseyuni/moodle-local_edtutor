@local @local_edtutor
Feature: Submit an assignment on behalf of a student
  In order to submit for a student I act for
  As a tutor
  I need to upload the work and have it submitted or escalated

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | tutor1   | Tutor     | One      |
      | student1 | Student   | One      |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
      | tutor1   | C1     | teacher |
    And the following "activities" exist:
      | activity | course | idnumber | name         | assignsubmission_onlinetext_enabled |
      | assign   | C1     | assign1  | Assignment 1 | 1                                   |
    And the following "local_edtutor > allocations" exist:
      | tutor  | student  |
      | tutor1 | student1 |
    And the following "role assigns" exist:
      | user   | role    | contextlevel | reference |
      | tutor1 | edtutor | System       |           |
    And I change the window size to "large"

  Scenario: A tutor with the on-behalf capability submits work automatically
    Given I am on the "local_edtutor > submit" page logged in as "tutor1"
    When I select "Student One (student1)" from the "Student" singleselect
    And I select "Course 1" from the "Course" singleselect
    And I select "Assignment 1" from the "Assignment" singleselect
    And I set the field "Online text" to "This is the student's work"
    And I press "Submit on behalf of student"
    Then I should see "Submitted automatically"
    And I should see "Student One"

  Scenario: A submission is escalated when the tutor lacks the on-behalf capability
    # The edtutor role normally grants mod/assign:editothersubmission; take it
    # away so the submission cannot be made automatically.
    Given the following "permission overrides" exist:
      | capability                     | permission | role    | contextlevel | reference |
      | mod/assign:editothersubmission | Prevent    | edtutor | System       |           |
    And I am on the "local_edtutor > submit" page logged in as "tutor1"
    When I select "Student One (student1)" from the "Student" singleselect
    And I select "Course 1" from the "Course" singleselect
    And I select "Assignment 1" from the "Assignment" singleselect
    And I set the field "Online text" to "This is the student's work"
    And I press "Submit on behalf of student"
    Then I should see "Pending manual submission"
