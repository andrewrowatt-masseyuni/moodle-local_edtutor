@local @local_edtutor @javascript
Feature: Education tutors can hide assessments on the dashboard
  In order to focus on work that can still be submitted
  As an education tutor
  I need to hide overdue assessments that will never be submitted for a student

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | tutor1   | Tutor     | One      |
      | student1 | Anne      | Student  |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "activities" exist:
      | activity | course | idnumber | name           | assignsubmission_onlinetext_enabled | duedate       |
      | assign   | C1     | assign1  | Overdue assign | 1                                   | ##yesterday## |
    And the following "local_edtutor > allocations" exist:
      | tutor  | student  |
      | tutor1 | student1 |
    And the following "role assigns" exist:
      | user   | role    | contextlevel | reference |
      | tutor1 | edtutor | System       |           |
    And I change the window size to "large"

  Scenario: Hiding an assessment removes it from the default view and Show restores it
    Given I am on the "local_edtutor > dashboard" page logged in as "tutor1"
    Then I should see "Overdue assign" in the "[data-region='local_edtutor-view-bystudent']" "css_element"
    When I click on "Hide" "link" in the "[data-region='local_edtutor-view-bystudent']" "css_element"
    Then I should see "Are you sure you want to hide"
    When I press "Confirm"
    Then I should see "The assessment has been hidden."
    And I should not see "Overdue assign" in the "[data-region='local_edtutor-view-bystudent']" "css_element"
    When I set the field "Status" to "Show only hidden assessments"
    Then I should see "Overdue assign" in the "[data-region='local_edtutor-view-bystudent']" "css_element"
    And I should see "Show" in the "[data-region='local_edtutor-view-bystudent']" "css_element"
    When I click on "Show" "link" in the "[data-region='local_edtutor-view-bystudent']" "css_element"
    Then I should see "The assessment is now shown."
    When I set the field "Status" to "Show all"
    Then I should see "Overdue assign" in the "[data-region='local_edtutor-view-bystudent']" "css_element"
    And I should see "Hide" in the "[data-region='local_edtutor-view-bystudent']" "css_element"
