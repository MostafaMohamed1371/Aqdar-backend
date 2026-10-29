Feature: Dashboard authentication
  As a dashboard user
  I want to sign in securely
  So that I can access my overview

  Scenario: Guest sees login form
    Given the dashboard page is open
    Then I should see the sign in form

  Scenario: Staff can log in with valid credentials
    Given a staff user exists
    When I log in with valid credentials
    Then I should see the dashboard overview
