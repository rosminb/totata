@totara @engage @totara_engage @engage_article @javascript
Feature: View article

  Background:
    Given I am on a totara site
    And I set the site theme to "ventura"

    And the following "topics" exist in "totara_topic" plugin:
      | name    |
      | Topic 1 |

    And the following "users" exist:
      | username | firstname | lastname | email             |
      | user1    | User      | One      | user1@example.com |

    And the following "articles" exist in "engage_article" plugin:
      | name           | username | content       | format       | access     | topics  |
      | Test Article 1 | user1    | Test Aticle 1 | FORMAT_PLAIN | PRIVATE    | Topic 1 |
      | Test Article 2 | user1    | Test Aticle 1 | FORMAT_PLAIN | RESTRICTED | Topic 1 |
      | Test Article 3 | user1    | Test Aticle 1 | FORMAT_PLAIN | PUBLIC     | Topic 1 |

  Scenario: admin views the article that created by one user
    Given I log in as "admin"
    And I click on "Find Learning" in the totara menu
    Then I should see "Test Article 1"
    And I click on "Test Article 1" "text"
    Then I should see "Test Article 1"

  Scenario: User views restricted article and public article
    Given I log in as "user1"
    And I view article "Test Article 2"
    Then I should not see "Reshare"
    And I view article "Test Article 3"
    And I click on "Share" "button" in the ".tui-accessSetting" "css_element"
    Then I should see "Settings" in the ".tui-modalContent__header-title" "css_element"