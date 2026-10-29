"""BDD step definitions — Behavior Driven Development (Gherkin)."""
import os
import time

import pytest
import requests
from pytest_bdd import given, scenarios, then, when

scenarios("../features/auth.feature")
scenarios("../features/dashboard.feature")

BASE_URL = None
API_URL = None
_context: dict = {}


@pytest.fixture(autouse=True)
def _init_urls(base_url: str, api_url: str, ensure_app_is_running) -> None:
    global BASE_URL, API_URL
    BASE_URL = base_url
    API_URL = api_url
    _context.clear()


@given("the dashboard page is open")
def dashboard_page_open() -> None:
    response = requests.get(f"{BASE_URL}/dashboard", timeout=10)
    assert response.status_code == 200
    _context["dashboard_html"] = response.text


@then("I should see the sign in form")
def see_sign_in_form() -> None:
    html = _context.get("dashboard_html", "")
    assert 'name="email"' in html
    assert 'name="password"' in html
    assert 'name="_token"' in html


@given("a staff user exists")
def staff_user_exists() -> None:
    _context["email"] = os.getenv("QA_STAFF_EMAIL", "test@example.com")
    _context["password"] = os.getenv("QA_STAFF_PASSWORD", "password")


@when("I log in with valid credentials")
def login_with_credentials() -> None:
    session = requests.Session()
    login_page = session.get(f"{BASE_URL}/dashboard", timeout=10)
    assert login_page.status_code == 200
    token = _extract_csrf(login_page.text)
    response = session.post(
        f"{BASE_URL}/dashboard/login",
        data={
            "_token": token,
            "email": _context["email"],
            "password": _context["password"],
        },
        allow_redirects=True,
        timeout=10,
    )
    _context["login_response"] = response
    _context["session"] = session


@then("I should see the dashboard overview")
def see_dashboard_overview() -> None:
    response = _context.get("login_response")
    assert response is not None
    assert response.status_code == 200
    html = response.text
    assert 'data-screen="ADM-01"' in html
    assert "لوحة القيادة التنفيذية" in html or "Executive Dashboard" in html


@when("I register via API with valid data")
def register_via_api() -> None:
    suffix = str(int(time.time() * 1000))
    response = requests.post(
        f"{API_URL}/auth/register",
        json={
            "name": "BDD API User",
            "email": f"bdd-api-{suffix}@example.com",
            "password": "password123",
            "password_confirmation": "password123",
            "consent": True,
        },
        timeout=10,
    )
    _context["api_register_response"] = response


@then("the API should return a token")
def api_returns_token() -> None:
    response = _context["api_register_response"]
    assert response.status_code == 201
    assert "token" in response.json()["data"]


@when("I attempt API login with invalid email")
def invalid_api_login() -> None:
    response = requests.post(
        f"{API_URL}/auth/login",
        json={"email": "bad-email", "password": ""},
        timeout=10,
    )
    _context["api_login_response"] = response


@then("the API should return validation errors")
def api_validation_errors() -> None:
    response = _context["api_login_response"]
    assert response.status_code == 422
    assert "errors" in response.json()


def _extract_csrf(html: str) -> str:
    marker = 'name="_token" value="'
    start = html.find(marker)
    assert start != -1, "CSRF token not found"
    start += len(marker)
    end = html.find('"', start)
    return html[start:end]
