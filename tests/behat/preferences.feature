@local @local_edtutor @javascript
Feature: Education tutors can set forum preferences for their allocated students
  In order to manage the email my students receive from forums
  As an education tutor
  I need to set the email digest type for each of my allocated students

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | tutor1   | Tutor     | One      |
      | student1 | Student   | One      |
      | student2 | Student   | Two      |
    And the following "local_edtutor > allocations" exist:
      | tutor  | student  |
      | tutor1 | student1 |
      | tutor1 | student2 |
    And the following "role assigns" exist:
      | user   | role    | contextlevel | reference |
      | tutor1 | edtutor | System       |           |

  Scenario: A tutor sets the email digest type for an allocated student
    Given I log in as "tutor1"
    When I follow "Set student forum preferences" in the user menu
    Then I should see "Student One (student1)"
    And I should see "Student Two (student2)"
    When I set the field "Email digest type for Student One" to "Complete (daily email with full posts)"
    Then I should see "Email digest type updated for Student One (student1)"
    And the field "Email digest type for Student One" matches value "Complete (daily email with full posts)"
    And the field "Email digest type for Student Two" matches value "No digest (single email per forum post)"

  Scenario: A student cannot see the student forum preferences link
    Given I log in as "student1"
    Then "Set student forum preferences" "link" should not exist in the user menu
