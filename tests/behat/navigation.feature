@tool @tool_flexaccess
Feature: FlexAccess administration dashboard
  In order to operate FlexAccess
  As an administrator
  I need to reach the FlexAccess dashboard

  Scenario: Administrator can open the FlexAccess dashboard
    Given I log in as "admin"
    When I visit "/admin/tool/flexaccess/index.php"
    Then I should see "Dashboard"
