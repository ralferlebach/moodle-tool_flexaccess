@tool @tool_flexaccess
Feature: FlexAccess administration scaffold
  Scenario: Administrator can open the FlexAccess dashboard
    Given I log in as "admin"
    When I visit "/admin/tool/flexaccess/index.php"
    Then I should see "FlexAccess administration scaffold"
