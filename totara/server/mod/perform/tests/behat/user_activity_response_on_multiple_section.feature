@totara @perform @mod_perform @javascript @vuejs
Feature: Respond to activity with multiple sections
  Background:
    And I log in as "admin"
    Given the following "users" exist:
      | username | firstname | lastname | email             |
      | user1    | user      | 1        | user1@example.com |
      | user2    | user      | 2        | user2@example.com |
    And the following "cohorts" exist:
      | name | idnumber |
      | aud1 | aud1     |
    And the following "cohort members" exist:
      | user  | cohort |
      | user1 | aud1   |
      | user2 | aud1   |
    And the following "activities" exist in "mod_perform" plugin:
      | activity_name   | activity_type | activity_status | create_section |
      | Closed activity | check-in      | Active          | false          |
      | Open activity   | check-in      | Active          | false          |
    And the following "activity settings" exist in "mod_perform" plugin:
      | activity_name   | close_on_completion | multisection |
      | Closed activity | yes                 | yes          |
      | Open activity   | no                  | no           |
    And the following "activity sections" exist in "mod_perform" plugin:
      | activity_name   | section_name |
      | Closed activity | section 1    |
      | Closed activity | section 2    |
      | Closed activity | section 3    |
      | Open activity   | section A    |
    And the following "section relationships" exist in "mod_perform" plugin:
      | section_name | relationship |
      | section 1    | subject      |
      | section 2    | subject      |
      | section 3    | subject      |
      | section A    | subject      |
    And the following "section elements" exist in "mod_perform" plugin:
      | section_name | element_name |
      | section 1    | short_text   |
      | section 2    | short_text   |
      | section 3    | short_text   |
      | section A    | short_text   |
    And the following "activity tracks" exist in "mod_perform" plugin:
      | activity_name   | track_description |
      | Closed activity | closed track 1    |
      | Open activity   | open track 2      |
    And the following "track assignments" exist in "mod_perform" plugin:
      | track_description | assignment_type | assignment_name |
      | closed track 1    | cohort          | aud1            |
      | open track 2      | cohort          | aud1            |
    And I trigger cron
    And I am on homepage
    And I log out

  Scenario: Shows read-only mode on closed participant section, auto navigate to next section and redirect to list after responding to last section.
    Given I log in as "user1"
    And I navigate to the outstanding perform activities list page
    And I click on "Closed activity" "link"

    # Section 1
    Then I should see "section 1"
    When I answer "short text" question "test element title" with "John Answer one"
    And I click on "Submit" "button"
    Then I should see "You will not be able to update your responses after submission."
    And I confirm the tui confirmation modal
    Then I should see "Section submitted and closed." in the tui success notification toast
    And I close the tui notification toast

    # Section 2
    And I should see "section 2"
    When I answer "short text" question "test element title" with "John Answer two"
    And I click on "Submit" "button"
    Then I should see "You will not be able to update your responses after submission."
    And I confirm the tui confirmation modal
    Then I should see "Section submitted and closed." in the tui success notification toast
    And I close the tui notification toast
    And I should see "section 3"

    # Section 3
    When I answer "short text" question "test element title" with "John Answer three"
    And I click on "Submit" "button"
    Then I should see "You will not be able to update your responses after submission."
    And I confirm the tui confirmation modal
    And I should see "Performance activities"
    And I should see "Section submitted and closed." in the tui success notification toast
    And I close the tui notification toast

    # View participant section in read-only mode.
    And I click on "Closed activity" "link"

    # Section 1
    Then I should see "section 1" in the ".tui-participantContent__sectionHeading-title" "css_element"
    And I should not see "Submit"
    And I should not see "Cancel"
    And I should see "John Answer one" in the ".tui-performElementParticipantFormContent" "css_element"
    And I should not see "Previous section" in the ".tui-participantContent__navigation" "css_element"
    And I should see "Next section" in the ".tui-participantContent__navigation" "css_element"
    And I should see "Close" in the ".tui-participantContent__navigation" "css_element"
    And I click on "Next section" "button"

    # Section 2
    Then I should see "section 2" in the ".tui-participantContent__sectionHeading-title" "css_element"
    And I should not see "Submit"
    And I should not see "Cancel"
    And I should see "John Answer two" in the ".tui-performElementParticipantFormContent" "css_element"
    And I should see "Previous section" in the ".tui-participantContent__navigation" "css_element"
    And I should see "Next section" in the ".tui-participantContent__navigation" "css_element"
    And I should see "Close" in the ".tui-participantContent__navigation" "css_element"
    And I click on "Next section" "button"

    # Section 3
    Then I should see "section 3" in the ".tui-participantContent__sectionHeading-title" "css_element"
    And I should not see "Submit"
    And I should not see "Cancel"
    Then I should see "John Answer three" in the ".tui-performElementParticipantFormContent" "css_element"
    And I should see "Previous section" in the ".tui-participantContent__navigation" "css_element"
    And I should not see "Next section" in the ".tui-participantContent__navigation" "css_element"
    And I should see "Close" in the ".tui-participantContent__navigation" "css_element"
    And I click on "Close" "button"
    Then I should see "Performance activities"
    And I should see "Closed activity"
    And I should see "Open activity"

    # Check view-only report version
    When I log out
    And I log in as "admin"
    And I navigate to the view only report view of performance activity "Closed activity" where "user1" is the subject

    Then I should see "section 1" in the ".tui-participantContent__sectionHeading-title" "css_element"
    Then I should see the "Responses by relationship" tui select filter has the following options "All, Subject"
    Then I should see perform "short text" question "test element title" is answered by "Subject" with "John Answer one"

    When I click on "Next section" "button"
    Then I should see "section 2" in the ".tui-participantContent__sectionHeading-title" "css_element"
    Then I should see the "Responses by relationship" tui select filter has the following options "All, Subject"
    Then I should see perform "short text" question "test element title" is answered by "Subject" with "John Answer two"

    When I click on "Next section" "button"
    Then I should see "section 3" in the ".tui-participantContent__sectionHeading-title" "css_element"
    Then I should see the "Responses by relationship" tui select filter has the following options "All, Subject"
    Then I should see perform "short text" question "test element title" is answered by "Subject" with "John Answer three"

  Scenario: Displays close on completion confirmation text when close on completion is enabled.
    Given I log in as "user1"
    And I navigate to the outstanding perform activities list page
    And I click on "Closed activity" "link"
    Then I should see "section 1"
    When I answer "short text" question "test element title" with "John Answer one"
    And I click on "Submit" "button"
    Then I should see "You will not be able to update your responses after submission."
    And I confirm the tui confirmation modal
    And I should see "Section submitted and closed." in the tui success notification toast

  Scenario: Does not display close on completion confirmation text when close on completion is disabled.
    Given I log in as "user1"
    And I navigate to the outstanding perform activities list page
    When I click on "Open activity" "link"
    Then I should see "Open activity" in the ".tui-pageHeading__title" "css_element"
    And I answer "short text" question "test element title" with "John Answer one"
    And I click on "Submit" "button"
    Then I should see "Once submitted, your responses will be visible to other users who have permission to view them."
    And I should not see "You will not be able to update your responses after submission."
    And I confirm the tui confirmation modal
    And I should see "Section submitted" in the tui success notification toast

  Scenario: Navigate to next section from side panel
    Given I log in as "user1"
    And I navigate to the outstanding perform activities list page
    And I click on "Closed activity" "link"
    Then I should see "section 1" in the ".tui-sidePanel__content" "css_element"
    And I should see "section 2" in the ".tui-sidePanel__content" "css_element"
    And I should see "section 1" in the ".tui-participantContent__sectionHeading-title" "css_element"
    When I click on "section 2" "button"
    Then I should see "section 2" in the ".tui-participantContent__sectionHeading-title" "css_element"

    # Check view-only report version
    When I log out
    And I log in as "admin"
    And I navigate to the view only report view of performance activity "Closed activity" where "user1" is the subject

    Then I should see "section 1" in the ".tui-participantContent__sectionHeading-title" "css_element"

    When I click on "section 2" "button"
    Then I should see "section 2" in the ".tui-participantContent__sectionHeading-title" "css_element"

    When I click on "section 3" "button"
    Then I should see "section 3" in the ".tui-participantContent__sectionHeading-title" "css_element"

    When I click on "section 1" "button"
    Then I should see "section 1" in the ".tui-participantContent__sectionHeading-title" "css_element"

    # Test push/pop state
    When I press the "back" button in the browser
    Then I should see "section 3" in the ".tui-participantContent__sectionHeading-title" "css_element"

    When I press the "back" button in the browser
    Then I should see "section 2" in the ".tui-participantContent__sectionHeading-title" "css_element"

    When I press the "back" button in the browser
    Then I should see "section 1" in the ".tui-participantContent__sectionHeading-title" "css_element"

    When I press the "forward" button in the browser
    Then I should see "section 2" in the ".tui-participantContent__sectionHeading-title" "css_element"

    When I press the "forward" button in the browser
    Then I should see "section 3" in the ".tui-participantContent__sectionHeading-title" "css_element"

  @ignore-js-logging
  Scenario: Show browser based warning message when navigate to different section with unsaved change
    Given I log in as "user1"
    And I navigate to the outstanding perform activities list page
    And I click on "Closed activity" "link"
    And I click on "section 2" "button"
    # test popup confirm when it has unsaved changes
    When I answer "short text" question "test element title" with "John Answer two"
    And I click on "section 1" "button"
    Then I should see "Your unsaved changes will be lost." in browser confirmation popup
    # test click "OK" on popup confirm, then show correct section
    When I confirm the browser confirmation popup
    Then I should see "section 1" in the ".tui-participantContent__sectionHeading-title" "css_element"

  Scenario: Show updated responses when navigate to different section then navigate back
    Given I log in as "user1"
    And I navigate to the outstanding perform activities list page
    And I click on "Closed activity" "link"

    Then I should see "section 1"
    When I answer "short text" question "test element title" with "John Answer one"
    And I click on "Submit" "button"
    And I confirm the tui confirmation modal
    # navigate to section 2
    And I click on "section 2" "button"
    And I answer "short text" question "test element title" with "John Answer two"
    And I click on "draft" "button"
    # navigate back to section 1
    When I click on "section 1" "button"
    Then I should see "John Answer one" in the ".tui-performElementParticipantFormContent" "css_element"
    # navigate back to section 2
    When I click on "section 2" "button"
    Then the field "Your response" matches value "John Answer two"

  Scenario: End user can navigate back to activity list by nav link
    Given I log in as "user1"
    And I navigate to the outstanding perform activities list page
    And I click on "Closed activity" "link"
    Then I should see "Performance activities"

    When I click on "Performance activities" "link"
    Then I should see "Closed activity"
    And I should see "Open activity"