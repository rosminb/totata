@core @core_admin @core_admin_roles @javascript
Feature: Test the default roles can not be removed when they are assigned under User Policies
  Scenario: Admin check the default roles
    Given I log in as "admin"
    And I navigate to "Permissions > Site administrators" in site administration
    When I click on "Define roles" "link"
    Then I should see "Delete" in the "Manager" "table_row"
    And I should see "Delete" in the "Course creator" "table_row"
    And I should not see "Delete" in the "Teacher" "table_row"
    And I should see "Delete" in the "Non-editing teacher" "table_row"
    And I should not see "Delete" in the "Student" "table_row"
    And I should not see "Delete" in the "Guest" "table_row"
    And I should not see "Delete" in the "Authenticated user" "table_row"
    And I should see "Delete" in the "Authenticated user on frontpage" "table_row"
    And I should not see "Delete" in the "Staff Manager" "table_row"
    And I should see "Delete" in the "Performance activity creator" "table_row"
    And I should not see "Delete" in the "Performance activity manager" "table_row"
    And I should see "Delete" in the "Workspace creator" "table_row"
    And I should see "Delete" in the "Workspace owner" "table_row"
