@totara @totara_engage @totara_playlist @engage
Feature: Create playlist
  Background:
    Given I am on a totara site
    And I set the site theme to "ventura"
    And the following "users" exist:
      | username | firstname | lastname | email           |
      | user_one | User      | One      | one@example.com |

  @javascript @editor_weka
  Scenario: User create a playlist
    Given I log in as "user_one"
    And I click on "Your Library" in the totara menu
    When I click on "Contribute playlist" "button"
    Then the "Next" "button" should be disabled
    And I set the field "Enter playlist title" to "Playlist1"
    And I activate the weka editor with css ".tui-playlistForm__description-textArea"
    And I type "Some description with \"quotes\". Tag <example@example.com> and test icon tag: <i class=\"fab fa-accessible-icon\"></i> stuff" in the weka editor
    Then the "Next" "button" should be enabled
    And I click on "Next" "button"
    And I wait for the next second
    Then I should see "Content visibility settings"
    And I should see "Public (anyone can see and share this content)"
    And I should see "Limited (only people and workspaces you share to)"
    And I click on "Hidden (only you)" "text" in the ".tui-accessSelector" "css_element"
    And the "Done" "button" should be enabled
    And I click on "Done" "button"
    And I should see "Some description with \"quotes\". Tag <example@example.com> and test icon tag: <i class=\"fab fa-accessible-icon\"></i> stuff"

  @javascript
  Scenario: User create a playlist with hashtag
    Given I log in as "user_one"
    And I click on "Your Library" in the totara menu
    When I click on "Contribute playlist" "button"
    Then the "Next" "button" should be disabled
    And I set the field "Enter playlist title" to "Playlist1"
    And I activate the weka editor with css ".tui-playlistForm__description-textArea"
    And I type "#hashtag " in the weka editor
    Then the "Next" "button" should be enabled
    And I click on "Next" "button"
    And I wait for the next second
    Then I should see "Content visibility settings"
    And I should see "Public (anyone can see and share this content)"
    And I should see "Limited (only people and workspaces you share to)"
    And I click on "Hidden (only you)" "text" in the ".tui-accessSelector" "css_element"
    And the "Done" "button" should be enabled
    When I click on "Done" "button"
    Then I should see "#hashtag"

  @javascript
  Scenario: Create playlist without description should produce a add description link
    Given I log in as "user_one"
    And I click on "Your Library" in the totara menu
    When I click on "Contribute playlist" "button"
    Then the "Next" "button" should be disabled
    And I set the field "Enter playlist title" to "Playlist1"
    Then the "Next" "button" should be enabled
    And I click on "Next" "button"
    And I wait for the next second
    And I click on "Hidden (only you)" "text" in the ".tui-accessSelector" "css_element"
    When I click on "Done" "button"
    Then I should see "Add a description (optional)"