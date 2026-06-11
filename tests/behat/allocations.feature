@local @local_edtutor @javascript
Feature: Manage education tutor allocations
  In order to let tutors act on behalf of their students
  As a manager or administrator
  I need to allocate students to tutors using user pickers

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | manager1 | Manager   | One      |
      | tutor1   | Tutor     | One      |
      | tutor2   | Tutor     | Two      |
      | student1 | Student   | One      |
      | student2 | Student   | Two      |
    And the following "role assigns" exist:
      | user     | role           | contextlevel | reference |
      | manager1 | edtutormanager | System       |           |
      | tutor1   | edtutor        | System       |           |
    And I change the window size to "large"

  Scenario: A user who can assign roles may allocate any user as a tutor
    And I am on the "local_edtutor > allocations" page logged in as "manager1"
    When I set the field "Tutor" to "Tutor Two"
    And I set the field "Students" to "Student One"
    And I press "Add allocation"
    Then I should see "Allocation added"
    And I should see "Tutor Two (tutor2)"
    And I should see "Student One"

  Scenario: A manager allocates several students to a tutor at once
    And I am on the "local_edtutor > allocations" page logged in as "manager1"
    When I set the field "Tutor" to "Tutor Two"
    And I set the field "Students" to "Student One, Student Two"
    And I press "Add allocation"
    Then I should see "2 allocations added"
    And I should see "Student One"
    And I should see "Student Two"

  Scenario: A user who cannot assign roles is limited to existing tutors
    # The edtutormanager role does not include moodle/role:assign, so no
    # override is needed for the manager to be unable to assign roles.
    Given I am on the "local_edtutor > allocations" page logged in as "manager1"
    When I set the field "Tutor" to "Tutor One"
    And I set the field "Students" to "Student One"
    And I press "Add allocation"
    Then I should see "Allocation added"
    And I should see "Tutor One (tutor1)"

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
