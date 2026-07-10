@local @local_plugwatch
Feature: Manage the personal plugin watch list
  In order to keep track of plugin updates
  As a user with the local/plugwatch:use capability
  I need to see, search, add, remove watched plugins and set my notification frequency

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                 |
      | manager1 | Manager   | One      | manager1@example.com  |
      | student1 | Student   | One      | student1@example.com  |
    And the following "system role assigns" exist:
      | user     | role    |
      | manager1 | manager |

  Scenario: A user without the capability does not see the preferences link
    Given I log in as "student1"
    When I visit "/user/preferences.php"
    Then I should not see "Plugin Monitor"

  Scenario: A user with the capability sees the preferences page
    Given I log in as "manager1"
    When I visit "/local/plugwatch/preferences.php"
    Then I should see "Plugin Monitor — Preferences"

  @javascript
  Scenario: Removing a watched plugin removes it from the table
    Given "manager1" is watching the plugin "block_xp"
    And I log in as "manager1"
    And I visit "/local/plugwatch/preferences.php"
    Then I should see "block_xp"
    When I click on "Remove block_xp" "button"
    Then I should not see "block_xp"

  Scenario: Changing the notification frequency persists after reload
    Given I log in as "manager1"
    And I visit "/local/plugwatch/preferences.php"
    When I set the field "Notification frequency" to "Monthly"
    And I press "Save changes"
    And I reload the page
    Then the field "Notification frequency" matches value "Monthly"
