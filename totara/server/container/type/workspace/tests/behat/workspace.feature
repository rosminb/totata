@totara @engage @container_workspace @container @javascript
Feature: General workspace workflow
  Background:
    Given I am on a totara site
    And I set the site theme to "ventura"
    And the following "users" exist:
      | username | firstname | lastname | email           |
      | user_one | User      | One      | one@example.com |

  Scenario: Authenticated user can not create workspace
    Given I log in as "user_one"
    And I click on "Your Workspaces" in the totara menu
    And ".tui-contributeWorkspace__button" "css_element" should exist
    When I click on "Create a workspace" "button"
    Then I should see "Create a workspace"
    And I log out

    And I log in as "admin"
    When I set the following system permissions of "Authenticated user" role:
      | container/workspace:createhidden  | Prohibit |
      | container/workspace:createprivate | Prohibit |
      | container/workspace:create        | Prohibit |
    Then I log out

    And I log in as "user_one"
    And I click on "Your Workspaces" in the totara menu
    And ".tui-contributeWorkspace__button" "css_element" should not exist

  Scenario: User can not access non existence workspace
    Given I log in as "user_one"
    When I access the workspace by id "100"
    Then I should see "The workspace cannot be found. It appears to be deleted."

  Scenario: Guest can access public workspace
    Given I log in as "admin"
    And I click on "Your Workspaces" in the totara menu
    And I click on "Create a workspace" "button"
    And I set the field "Workspace name" to "workspace_a"
    And I click on "Submit" "button"
    And I set the following system permissions of "Guest" role:
      | capability                         | permission |
      | container/workspace:workspacesview | Allow      |
    And I set the following administration settings values:
      | guestloginbutton | Show |
    Then I log out
    And I log in as "guest"
    Then I should see "Collaborate" in the totara menu
    When I click on "Find Workspaces" in the totara menu
    Then I should see "workspace_a"
    When I follow "workspace_a"
    Then I should see "workspace_a"
    And I should not see "joined"