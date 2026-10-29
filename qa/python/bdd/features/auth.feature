Feature: API authentication
  As an API consumer
  I want to register and authenticate
  So that I can access protected resources

  Scenario: Register a new user via API
    When I register via API with valid data
    Then the API should return a token

  Scenario: Reject invalid login
    When I attempt API login with invalid email
    Then the API should return validation errors
