@local @local_edtutor @local_edtutor_access
Feature: Access restrictions are honoured when submitting on behalf of a student
  In order to avoid forcing work into activities a student cannot reach
  As a tutor
  I need on-behalf submissions to respect the assignment's access restrictions

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
    And the following "groups" exist:
      | name    | course | idnumber |
      | Group 1 | C1     | G1       |
    # The assignment is restricted to students who belong to a group; a student
    # in no group cannot reach it.
    And the following "activities" exist:
      | activity | course | idnumber | name         | assignsubmission_onlinetext_enabled | availability                                      |
      | assign   | C1     | assign1  | Assignment 1 | 1                                   | {"op":"&","c":[{"type":"group"}],"showc":[true]}  |
    And the following "local_edtutor > allocations" exist:
      | tutor  | student  |
      | tutor1 | student1 |
    And the following config values are set as admin:
      | enableavailability | 1 |
    # The edtutor role lets the tutor submit automatically, so only the access
    # restriction should stand in the way.
    And the following "role assigns" exist:
      | user   | role    | contextlevel | reference |
      | tutor1 | edtutor | System       |           |
    And I change the window size to "large"

  Scenario: A submission is escalated when access restrictions stop the student reaching the assignment
    Given I am on the "local_edtutor > submit" page logged in as "tutor1"
    When I select "Student One (student1)" from the "Student" singleselect
    And I select "Course 1" from the "Course" singleselect
    And I select "Assignment 1" from the "Assignment" singleselect
    And I set the field "Online text" to "This is the student's work"
    And I press "Submit on behalf of student"
    Then I should see "Pending manual submission"
    And I should see "Access restrictions"

  Scenario: A submission proceeds automatically when the student meets the access restrictions
    Given the following "group members" exist:
      | user     | group |
      | student1 | G1    |
    And I am on the "local_edtutor > submit" page logged in as "tutor1"
    When I select "Student One (student1)" from the "Student" singleselect
    And I select "Course 1" from the "Course" singleselect
    And I select "Assignment 1" from the "Assignment" singleselect
    And I set the field "Online text" to "This is the student's work"
    And I press "Submit on behalf of student"
    Then I should see "Submitted automatically"
    And I should see "Student One"
