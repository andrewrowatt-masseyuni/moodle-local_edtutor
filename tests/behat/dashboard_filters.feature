@local @local_edtutor @javascript
Feature: Education tutors can filter the dashboard
  In order to focus on the students and assessments that need attention
  As an education tutor
  I need to filter the dashboard by student, course and timeframe

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | tutor1   | Tutor     | One      |
      | student1 | Anne      | Student  |
      | student2 | Bob       | Student  |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
      | Course 2 | C2        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
      | student2 | C1     | student |
      | student2 | C2     | student |
    And the following "activities" exist:
      | activity | course | idnumber | name             | assignsubmission_onlinetext_enabled | duedate       |
      | assign   | C1     | assign1  | Overdue assign   | 1                                   | ##yesterday## |
      | assign   | C2     | assign2  | Future assign    | 1                                   | ##+2 months## |
    And the following "activities" exist:
      | activity | course | idnumber | name   |
      | quiz     | C1     | quiz1    | Quiz 1 |
    And the following "local_edtutor > allocations" exist:
      | tutor  | student  |
      | tutor1 | student1 |
      | tutor1 | student2 |
    And the following "role assigns" exist:
      | user   | role    | contextlevel | reference |
      | tutor1 | edtutor | System       |           |
    And I change the window size to "large"

  Scenario: The default timeframe shows only overdue or upcoming assessments and the choice persists
    Given I am on the "local_edtutor > dashboard" page logged in as "tutor1"
    Then I should see "Overdue assign" in the "[data-region='local_edtutor-view-bystudent']" "css_element"
    And I should not see "Future assign" in the "[data-region='local_edtutor-view-bystudent']" "css_element"
    And I should not see "Quiz 1" in the "[data-region='local_edtutor-view-bystudent']" "css_element"
    When I set the field "Status" to "Show all"
    Then I should see "Future assign" in the "[data-region='local_edtutor-view-bystudent']" "css_element"
    And I should see "Quiz 1" in the "[data-region='local_edtutor-view-bystudent']" "css_element"
    And I reload the page
    And I should see "Quiz 1" in the "[data-region='local_edtutor-view-bystudent']" "css_element"

  Scenario: Filtering by one student applies to both dashboard views
    Given I am on the "local_edtutor > dashboard" page logged in as "tutor1"
    When I set the field "Status" to "Show all"
    And I set the field "Student" to "Anne Student (student1)"
    Then I should see "Anne Student" in the "[data-region='local_edtutor-view-bystudent']" "css_element"
    And I should not see "Bob Student" in the "[data-region='local_edtutor-view-bystudent']" "css_element"
    When I set the field "View" to "By course"
    Then I should see "Anne Student" in the "[data-region='local_edtutor-view-bycourse']" "css_element"
    And "//div[@data-region='local_edtutor-view-bycourse']//li[@data-region='local_edtutor-studentrow'][contains(., 'Bob Student')]" "xpath_element" should not be visible
    And "//div[@data-region='local_edtutor-view-bycourse']//li[@data-region='local_edtutor-studentrow'][not(contains(@class, 'local_edtutor-hidden'))][contains(., 'Bob Student')]" "xpath_element" should not exist
    And I should not see "Course 2" in the "[data-region='local_edtutor-view-bycourse']" "css_element"
