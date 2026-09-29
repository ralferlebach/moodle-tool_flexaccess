@tool @tool_flexaccess
Feature: Frozen FlexAccess accounts are recovered through a preview and an explicit confirmation
  In order to let visitors back in after their account expired
  As a teacher or administrator
  I need to see frozen accounts, preview the recovery and confirm it

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |

  Scenario: A teacher recovers a frozen visitor of their course
    Given a frozen FlexAccess account "Frozen Visitor" exists in course "Course 1"
    And I log in as "teacher1"
    When I open the FlexAccess users of course "Course 1"
    Then I should see "Frozen Visitor"
    And I should see "Account expired"
    When I set the field "Select Frozen Visitor" to "1"
    And I press "Reactivate selected"
    Then I should see "Account becomes valid again for a limited time"
    When I press "Recover now"
    Then I should see "Recovered"

  Scenario: An administrator decides the origin of legacy suspensions in a batch
    Given a frozen FlexAccess account "Legacy Visitor" exists in course "Course 1"
    And the suspension of the FlexAccess account "Legacy Visitor" has an unknown origin
    And I log in as "admin"
    When I visit "/admin/tool/flexaccess/lockreview.php"
    Then I should see "Legacy Visitor"
    When I set the field "Select Legacy Visitor" to "1"
    And I set the field "The suspension comes from FlexAccess - record that and recover the accounts now" to "1"
    And I press "Preview"
    Then I should see "Legacy Visitor"
    When I press "Apply to 1 account(s)"
    Then I should see "Origin recorded and account recovered"
    When I visit "/admin/tool/flexaccess/lockreview.php"
    Then I should see "There are no suspensions of unknown origin"
