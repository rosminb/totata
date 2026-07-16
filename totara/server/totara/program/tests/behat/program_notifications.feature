@totara @totara_program @totara_notification @javascript
Feature: Check program notifications

  Background:
    Given I am on a totara site
    And the following "programs" exist in "totara_program" plugin:
      | fullname    | shortname | idnumber |
      | Program One | prog1     | prog1    |
      | Program Two | prog2     | prog2    |
    And the following "courses" exist:
      | fullname   | shortname | format | enablecompletion |
      | Course One | course1   | topics | 1                |
    And the following "users" exist:
      | username | firstname     | lastname | email                |
      | authuser | Authenticated | User     | authuser@example.com |
      | progman  | Program       | Manager  | progman@example.com  |
      | john     | John          | Smith    | john@example.com     |
      | mary     | Mary          | Jones    | mary@example.com     |
    And the following "roles" exist:
      | shortname   |
      | progmanager |
    And the following "role assigns" exist:
      | user    | role        | contextlevel  | reference |
      | progman | progmanager | Program       | prog1     |

  Scenario: program manager not allows to edit program notification
    Given the following "permission overrides" exist:
      | capability                           | permission | role          | contextlevel | reference |
      | totara/program:configuredetails      | Allow      | progmanager   | Program      | prog1     |
    And I log in as "progman"
    And I am on "Program One" program homepage
    When I press "Edit program details"
    Then I should not see "Notifications"
    And I log out

  Scenario: totara/program:configuremessages allows a user to edit program notification
    Given the following "permission overrides" exist:
      | capability                           | permission | role          | contextlevel | reference |
      | totara/program:configuremessages     | Allow      | progmanager   | Program      | prog1     |
    And I log in as "progman"
    And I am on "Program One" program homepage
    When I press "Edit program details"
    And I switch to "Notifications" tab
    Then I should see "Notifications"
    And I log out

  Scenario: program manager can configure program notification at the admin level
    Given
    And the following "role assigns" exist:
      | user    | role        | contextlevel  | reference |
      | progman | progmanager | System        | prog2     |
    And the following "permission overrides" exist:
      | capability                           | permission | role          | contextlevel  | reference |
      | totara/program:configuremessages     | Allow      | progmanager   | System        | prog2     |
    And I log in as "progman"
    When I navigate to system notifications page
    Then I should see "Notifications"
    And I log out

  Scenario: program manager should not see messages tab
    Given the following "permission overrides" exist:
      | capability                           | permission | role          | contextlevel | reference |
      | totara/program:configuremessages     | Allow      | progmanager   | Program      | prog1     |
    And I log in as "progman"
    And I am on "Program One" program homepage
    When I press "Edit program details"
    Then I should not see "Messages"