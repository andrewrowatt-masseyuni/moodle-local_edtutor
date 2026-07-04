@local @local_edtutor @javascript
Feature: Education Tutors have a dashboard of their allocated students
  In order to support my allocated students
  As an education tutor
  I need to see their courses and assignment statuses in one place

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
    And the following "activities" exist:
      | activity | course | idnumber | name         | assignsubmission_onlinetext_enabled | duedate       |
      | assign   | C1     | assign1  | Assignment 1 | 1                                   | ##yesterday## |
    And the following "activities" exist:
      | activity | course | idnumber | name   |
      | quiz     | C1     | quiz1    | Quiz 1 |
    And the following "local_edtutor > allocations" exist:
      | tutor  | student  |
      | tutor1 | student1 |
    And the following "role assigns" exist:
      | user   | role    | contextlevel | reference |
      | tutor1 | edtutor | System       |           |
    And I change the window size to "large"

  Scenario: A tutor sees allocated students and can switch to the by-course view
    Given I am on the "local_edtutor > dashboard" page logged in as "tutor1"
    Then I should see "Student One" in the "[data-region='local_edtutor-view-bystudent']" "css_element"
    And I should see "Assignment 1" in the "[data-region='local_edtutor-view-bystudent']" "css_element"
    And I should see "Not submitted" in the "[data-region='local_edtutor-view-bystudent']" "css_element"
    And I should see "Overdue" in the "[data-region='local_edtutor-view-bystudent']" "css_element"
    And I should see "Login as" in the "[data-region='local_edtutor-view-bystudent']" "css_element"
    And "a[href*='submit.php']" "css_element" should exist in the "[data-region='local_edtutor-view-bystudent']" "css_element"
    And I should see "Set student preferences" in the "[data-region='local_edtutor-view-bystudent']" "css_element"
    And I should see "Quiz 1" in the "[data-region='local_edtutor-view-bystudent']" "css_element"
    And I should see "The following activities have no due date set" in the "[data-region='local_edtutor-view-bystudent']" "css_element"
    And "button[disabled]" "css_element" should exist in the "[data-region='local_edtutor-view-bystudent']" "css_element"
    And "[data-region='local_edtutor-view-bycourse'].d-none" "css_element" should exist
    When I click on "By course" "text" in the ".btn-group-toggle" "css_element"
    Then I should see "Course 1" in the "[data-region='local_edtutor-view-bycourse']" "css_element"
    And "[data-region='local_edtutor-coursecard'].local_edtutor-overdue" "css_element" should exist
    And "[data-region='local_edtutor-view-bystudent'].d-none" "css_element" should exist
    And I reload the page
    And "[data-region='local_edtutor-view-bystudent'].d-none" "css_element" should exist
    And I should see "Course 1" in the "[data-region='local_edtutor-view-bycourse']" "css_element"

  Scenario: Login on behalf from a course row lands in that course
    Given I am on the "local_edtutor > dashboard" page logged in as "tutor1"
    When I click on "Login as" "link" in the "[data-region='local_edtutor-view-bystudent']" "css_element"
    Then I should see "You are logged in as Student One" in the "page-footer" "region"
    And I should see "Course 1"

  Scenario: A tutor opens the dashboard from the user menu
    Given I log in as "tutor1"
    When I follow "Education Tutor dashboard" in the user menu
    Then I should see "Student One" in the "[data-region='local_edtutor-view-bystudent']" "css_element"

  Scenario: A user without a tutor capability does not see the dashboard link
    Given I log in as "student1"
    Then "Education Tutor dashboard" "link" should not exist in the user menu
