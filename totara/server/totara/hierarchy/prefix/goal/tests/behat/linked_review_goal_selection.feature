@totara @perform @mod_perform @perform_element @performelement_linked_review @totara_hierarchy  @totara_hierarchy_goals @javascript @vuejs
Feature: Selecting goals linked to a performance review

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email             |
      | user1    | User      | One      | user1@example.com |
      | user2    | User      | Two      | user2@example.com |
      | user3    | User      | Three    | user3@example.com |
    And the following job assignments exist:
      | user  | manager |
      | user1 | user2   |
      | user2 | user3   |
    And the following "activity with section and review element" exist in "performelement_linked_review" plugin:
      | activity_name | section_title | element_title        | content_type  | content_type_settings                                                |
      | activity1     | section1      | Personal goal review | personal_goal | {"enable_status_change":true,"status_change_relationship":"subject"} |
      | activity1     | section1      | Company goal review  | company_goal  | {"enable_status_change":true,"status_change_relationship":"subject"} |
    And the following "child elements" exist in "mod_perform" plugin:
      | section  | parent_element       | element_plugin | element_title  |
      | section1 | Personal goal review | short_text     | child personal |
      | section1 | Company goal review  | short_text     | child company  |
    And the following "participants in section" exist in "performelement_linked_review" plugin:
      | section  | subject_user | user  | relationship     | can_answer |
      | section1 | user1        | user1 | subject          | true       |
      | section1 | user1        | user2 | manager          | true       |
      | section1 | user1        | user3 | managers_manager | false      |
    And the following "goal" frameworks exist:
      | fullname      | idnumber      |
      | Company goals | Company goals |
    And the following "goal" hierarchy exists:
      | fullname       | idnumber        | framework     | description                                              | targetdate |
      | Company goal A | Company goals A | Company goals | <ul><li>Complete part 1</li><li>Complete part 2</li><ul> | 04/12/2045 |
      | Company goal B | Company goals B | Company goals | Company goal B                                           |            |

  Scenario: When I have no personal goals or company goals assigned, nothing can happen
    When I log in as "user1"
    And I navigate to the outstanding perform activities list page
    And I click on "activity1" "link"
    And I click on "Add personal goals" "link_or_button"
    Then I should see "Select personal goals" in the tui modal
    And I should see "No items to display" in the tui modal
    And the "Add" "button" should be disabled in the ".tui-modalContent" "css_element"
    When I click on "Cancel" "button" in the ".tui-modal" "css_element"
    Then I should not see "Select personal goals"

    When I click on "Add company goals" "link_or_button"
    Then I should see "Select company goals" in the tui modal
    And I should see "No items to display" in the tui modal
    And the "Add" "button" should be disabled in the ".tui-modalContent" "css_element"
    When I click on "Cancel" "button" in the ".tui-modal" "css_element"
    Then I should not see "Select company goals"

  Scenario: Waiting for another user to select the goals
    When I log in as "user2"
    And I navigate to the outstanding perform activities list page
    And I click on "As Manager" "link_or_button"
    And I click on "activity1" "link"
    Then I should see "Awaiting personal goal selection from a Subject."
    Then I should see "Awaiting company goal selection from a Subject."

  Scenario: View only participant can select goals and change status
    Given the following "activity with section and review element" exist in "performelement_linked_review" plugin:
      | activity_name | section_title | element_title        | content_type  | content_type_settings                                                |
      | activity2     | section2      | Personal goal review | personal_goal | {"enable_status_change":true,"status_change_relationship":"subject"} |
      | activity2     | section2      | Company goal review  | company_goal  | {"enable_status_change":true,"status_change_relationship":"subject"} |
    And the following "section relationships" exist in "mod_perform" plugin:
      | section_name | relationship | can_view | can_answer |
      | section2     | subject      | yes      | no         |
    And the following "participants in section" exist in "performelement_linked_review" plugin:
      | section  | subject_user | user  | relationship |
      | section2 | user1        | user1 | subject      |

    And I log out
    And I log in as "user1"
    And I am on "Goals" page
    And I press "Add company goal"
    And I click on "Company goal A" "link"
    And I click on "Company goal B" "link"
    And I press "Save"

    And I press "Add personal goal"
    And I set the following fields to these values:
      | Name                | Personal goal A             |
      | Description         | Complete your personal goal |
      | Scale               | Goal scale                  |
      | targetdate[enabled] | 1                           |
      | targetdate[day]     | 4                           |
      | targetdate[month]   | 12                          |
      | targetdate[year]    | 2040                        |
    And I press "Save changes"

    And I press "Add personal goal"
    And I set the following fields to these values:
      | Name | Personal goal B |
    And I press "Save changes"

    When I navigate to the outstanding perform activities list page
    And I click on "activity2" "link"
    And I click on "Add personal goals" "link_or_button"
    Then I should not see "No items to display" in the tui modal
    And I should see "Items selected: 0" in the tui modal
    And I should see the tui datatable contains:
      | Goal            | Target date     |
      | Personal goal A | 4 December 2040 |
      | Personal goal B | -               |

    When I set the following fields to these values:
      | Search | Personal goal C |
    Then I should see the tui datatable is empty
    And I should see "Items selected: 0" in the tui modal

    When I set the following fields to these values:
      | Search | Personal goal A |
    Then I should see the tui datatable contains:
      | Goal            | Target date     |
      | Personal goal A | 4 December 2040 |
    And I toggle the adder picker entry with "Personal goal A" for "Goal"
    And I should see "Items selected: 1" in the tui modal
    And I click on "Add" "button" in the ".tui-modal" "css_element"
    And I click on "Confirm selection" "button"
    Then I should see "Complete your personal goal"

    When I set the following fields to these values:
      | status | Goal in progress |
    And I click on "Submit status" "button"
    Then I should see "You've given 'Personal goal A' a status of Goal in progress for User One" in the ".tui-modal" "css_element"
    And I should see "This will be submitted to the goal" in the ".tui-modal" "css_element"

    When I click on "Submit status" "button" in the ".tui-modal" "css_element"
    Then I should see "Goal status updated" in the tui success notification toast
    And I should see "Goal status" in the 1st selected content item for the "Personal goal review" linked review element
    And I should see "Status update by: User One (Subject)" in the 1st selected content item for the "Personal goal review" linked review element
    And I should see "##today##j F Y##" in the 1st selected content item for the "Personal goal review" linked review element
    And I should see "Goal status: Goal in progress" in the 1st selected content item for the "Personal goal review" linked review element

    When I click on "Add company goals" "link_or_button"
    Then I should not see "No items to display" in the tui modal
    And I should see "Items selected: 0" in the tui modal
    And I should see the tui datatable contains:
      | Goal           | Target date     |
      | Company goal A | 4 December 2045 |
      | Company goal B | -               |

    When I set the following fields to these values:
      | Search | Company goal C |
    Then I should see the tui datatable is empty
    And I should see "Items selected: 0" in the tui modal

    When I set the following fields to these values:
      | Search | Company goal A |
    Then I should see the tui datatable contains:
      | Goal           | Target date     |
      | Company goal A | 4 December 2045 |
    And I toggle the adder picker entry with "Company goal A" for "Goal"
    And I should see "Items selected: 1" in the tui modal
    And I click on "Add" "button" in the ".tui-modal" "css_element"
    And I click on "Confirm selection" "button"
    Then I should see "Complete part 1"
    And I should see "Complete part 2"

    When I set the following fields to these values:
      | status | Goal completed |
    And I click on "Submit status" "button"
    Then I should see "You've given 'Company goal A' a status of Goal completed for User One" in the ".tui-modal" "css_element"
    And I should see "This will be submitted to the goal" in the ".tui-modal" "css_element"

    When I click on "Submit status" "button" in the ".tui-modal" "css_element"
    Then I should see "Goal status updated" in the tui success notification toast
    And I should see "Goal status" in the 1st selected content item for the "Company goal review" linked review element
    And I should see "Status update by: User One (Subject)" in the 1st selected content item for the "Company goal review" linked review element
    And I should see "##today##j F Y##" in the 1st selected content item for the "Company goal review" linked review element
    And I should see "Goal status: Goal completed" in the 1st selected content item for the "Company goal review" linked review element

    When I reload the page
    Then I should see "Complete your personal goal"
    And I should see "Complete part 1"
    And I should see "Complete part 2"

  Scenario: Selecting participant can select goals and change status
    Given the following "users" exist:
      | username | firstname | lastname | email             |
      | user4    | User      | Four     | user4@example.com |
    And the following job assignments exist:
      | user  | manager | appraiser |
      | user4 | user2   | user3     |
    And the following "activity with section and review element" exist in "performelement_linked_review" plugin:
      | activity_name | section_title | element_title        | content_type  | selection_relationships | content_type_settings                                                  |
      | activity3     | section3      | Personal goal review | personal_goal | appraiser               | {"enable_status_change":true,"status_change_relationship":"appraiser"} |
      | activity3     | section3      | Company goal review  | company_goal  | appraiser               | {"enable_status_change":true,"status_change_relationship":"appraiser"} |
    And the following "section relationships" exist in "mod_perform" plugin:
      | section_name | relationship | can_view | can_answer |
      | section3     | subject      | yes      | yes        |
      | section3     | manager      | yes      | yes        |
      | section3     | appraiser    | yes      | no         |
    And the following "participants in section" exist in "performelement_linked_review" plugin:
      | section  | subject_user | user  | relationship | can_answer | can_view |
      | section3 | user4        | user4 | subject      | true       | true     |
      | section3 | user4        | user2 | manager      | true       | true     |
      | section3 | user4        | user3 | appraiser    | false      | true     |
    And I log out

    And I log in as "user4"
    And I am on "Goals" page
    And I press "Add company goal"
    And I click on "Company goal A" "link"
    And I click on "Company goal B" "link"
    And I press "Save"

    And I press "Add personal goal"
    And I set the following fields to these values:
      | Name                | Personal goal 4A            |
      | Description         | Complete your personal goal |
      | Scale               | Goal scale                  |
      | targetdate[enabled] | 1                           |
      | targetdate[day]     | 4                           |
      | targetdate[month]   | 12                          |
      | targetdate[year]    | 2040                        |
    And I press "Save changes"

    And I press "Add personal goal"
    And I set the following fields to these values:
      | Name | Personal goal 4B |
    And I press "Save changes"
    And I log out

    When I log in as "user3"
    And I navigate to the outstanding perform activities list page
    And I click on "As Appraiser" "link_or_button"
    And I click on "activity3" "link"
    And I click on "Add personal goals" "link_or_button"
    Then I should not see "No items to display" in the tui modal
    And I should see "Items selected: 0" in the tui modal
    Then I should see the tui datatable contains:
      | Goal             | Target date     |
      | Personal goal 4A | 4 December 2040 |
      | Personal goal 4B | -               |

    When I toggle the adder picker entry with "Personal goal 4A" for "Goal"
    And I should see "Items selected: 1" in the tui modal
    And I click on "Add" "button" in the ".tui-modal" "css_element"
    And I click on "Confirm selection" "button"
    Then I should see "Complete your personal goal"

    When I set the following fields to these values:
      | status | Goal in progress |
    And I click on "Submit status" "button"
    Then I should see "You've given 'Personal goal 4A' a status of Goal in progress for User Four" in the ".tui-modal" "css_element"
    And I should see "This will be submitted to the goal" in the ".tui-modal" "css_element"

    When I click on "Submit status" "button" in the ".tui-modal" "css_element"
    Then I should see "Goal status updated" in the tui success notification toast
    And I should see "Goal status" in the 1st selected content item for the "Personal goal review" linked review element
    And I should see "Status update by: User Three (Appraiser)" in the 1st selected content item for the "Personal goal review" linked review element
    And I should see "##today##j F Y##" in the 1st selected content item for the "Personal goal review" linked review element
    And I should see "Goal status: Goal in progress" in the 1st selected content item for the "Personal goal review" linked review element

    When I click on "Add company goals" "link_or_button"
    Then I should not see "No items to display" in the tui modal
    And I should see "Items selected: 0" in the tui modal
    And I should see the tui datatable contains:
      | Goal           | Target date     |
      | Company goal A | 4 December 2045 |
      | Company goal B | -               |

    And I toggle the adder picker entry with "Company goal B" for "Goal"
    And I should see "Items selected: 1" in the tui modal
    And I click on "Add" "button" in the ".tui-modal" "css_element"
    And I click on "Confirm selection" "button"
    Then I should see "Company goal B"

    When I set the following fields to these values:
      | status | Goal completed |
    And I click on "Submit status" "button"
    Then I should see "You've given 'Company goal B' a status of Goal completed for User Four" in the ".tui-modal" "css_element"
    And I should see "This will be submitted to the goal" in the ".tui-modal" "css_element"

    When I click on "Submit status" "button" in the ".tui-modal" "css_element"
    Then I should see "Goal status updated" in the tui success notification toast
    And I should see "Goal status" in the 1st selected content item for the "Company goal review" linked review element
    And I should see "Status update by: User Three (Appraiser)" in the 1st selected content item for the "Company goal review" linked review element
    And I should see "##today##j F Y##" in the 1st selected content item for the "Company goal review" linked review element
    And I should see "Goal status: Goal completed" in the 1st selected content item for the "Company goal review" linked review element

    When I reload the page
    Then I should see "Complete your personal goal"
    And I should see "Company goal B"
