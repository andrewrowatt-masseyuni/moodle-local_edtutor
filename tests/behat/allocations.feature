@local @local_edtutor @javascript
Feature: Manage education tutor allocations
  In order to let tutors act on behalf of their students
  As a manager
  I need to allocate students to tutors using user pickers

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | manager1 | Manager   | One      |
      | tutor1   | Tutor     | One      |
      | student1 | Student   | One      |
    And the following "role assigns" exist:
      | user     | role    | contextlevel | reference |
      | manager1 | manager | System       |           |

  Scenario: A manager adds an allocation using the user pickers
    Given I am on the "local_edtutor > allocations" page logged in as "manager1"
    When I set the field "Tutor" to "Tutor One"
    And I set the field "Student" to "Student One"
    And I press "Add allocation"
    Then I should see "Allocation added"
    And I should see "Tutor One"
    And I should see "Student One"

  Scenario: A manager removes an existing allocation
    Given the following "local_edtutor > allocations" exist:
      | tutor  | student  |
      | tutor1 | student1 |
    And I am on the "local_edtutor > allocations" page logged in as "manager1"
    Then I should see "Student One"
    When I click on "Remove" "link"
    And I press "Continue"
    Then I should see "Allocation removed"
    And I should not see "Student One"
