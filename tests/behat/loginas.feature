@local @local_edtutor @local_edtutor_loginas
Feature: Education tutors can log in as their allocated students at site level
  In order to act for my students across all of their courses
  As a tutor
  I need to log in as an allocated student and switch between them without logging out

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | tutor1   | Tutor     | One      |
      | tutor2   | Tutor     | Two      |
      | 27010001 | Anne      | Student  |
      | 27010002 | Bob       | Student  |
      | 27010003 | Carol     | Student  |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
      | Course 2 | C2        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | 27010001 | C1     | student |
      | 27010002 | C1     | student |
      | 27010003 | C2     | student |
    And the following "local_edtutor > allocations" exist:
      | tutor  | student  |
      | tutor1 | 27010001 |
      | tutor1 | 27010002 |
      | tutor1 | 27010003 |
    And the following "permission overrides" exist:
      | capability           | permission | role | contextlevel | reference |
      | local/edtutor:submit | Allow      | user | System       |           |

  Scenario: A tutor sees a login-as entry for each allocated student
    Given I log in as "tutor1"
    Then "Login as Anne Student (27010001)" "link" should exist in the user menu
    And "Login as Bob Student (27010002)" "link" should exist in the user menu
    And "Login as Carol Student (27010003)" "link" should exist in the user menu
    And "Return to my account" "link" should not exist in the user menu

  Scenario: A tutor logs in as a student at site level and can enter the student's course
    Given I log in as "tutor1"
    When I follow "Login as Anne Student (27010001)" in the user menu
    Then I should see "You are logged in as Anne Student" in the "page-footer" "region"
    When I am on "Course 1" course homepage
    Then I should see "Course 1"
    And I should see "You are logged in as Anne Student" in the "page-footer" "region"

  Scenario: A tutor switches directly from one student to another without logging out
    Given I log in as "tutor1"
    And I follow "Login as Anne Student (27010001)" in the user menu
    And I am on "Course 1" course homepage
    When I follow "Login as Bob Student (27010002)" in the user menu
    Then I should see "You are logged in as Bob Student" in the "page-footer" "region"
    When I am on "Course 1" course homepage
    Then I should see "You are logged in as Bob Student" in the "page-footer" "region"

  Scenario: A tutor switches from a student in one course to a student in another course
    Given I log in as "tutor1"
    And I follow "Login as Anne Student (27010001)" in the user menu
    And I am on "Course 1" course homepage
    When I follow "Login as Carol Student (27010003)" in the user menu
    And I am on "Course 2" course homepage
    Then I should see "Course 2"
    And I should see "You are logged in as Carol Student" in the "page-footer" "region"

  Scenario: A tutor returns to their own account without logging out
    Given I log in as "tutor1"
    And I follow "Login as Anne Student (27010001)" in the user menu
    When I follow "Return to my account" in the user menu
    Then I should see "You have returned to your own account"
    And I should see "You are logged in as Tutor One" in the "page-footer" "region"
    And "Login as Anne Student (27010001)" "link" should exist in the user menu

  Scenario: A student does not see any login-as entries
    Given I log in as "27010001"
    Then "Login as Bob Student (27010002)" "link" should not exist in the user menu
    And "Return to my account" "link" should not exist in the user menu

  Scenario: A tutor with no allocated students does not see any login-as entries
    Given I log in as "tutor2"
    Then "Login as Anne Student (27010001)" "link" should not exist in the user menu
    And "Return to my account" "link" should not exist in the user menu
