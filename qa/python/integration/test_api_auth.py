"""API integration tests — HTTP layer against running Laravel app."""
import pytest
import requests


@pytest.mark.usefixtures("ensure_app_is_running")
class TestAuthApiIntegration:
    def test_health_endpoint_is_up(self, base_url: str) -> None:
        response = requests.get(f"{base_url}/up", timeout=5)
        assert response.status_code == 200

    def test_register_login_logout_flow(self, api_url: str, http_session: requests.Session) -> None:
        import time

        email = f"integration-{int(time.time())}@example.com"
        password = "password123"

        register = http_session.post(
            f"{api_url}/auth/register",
            json={
                "name": "Integration User",
                "email": email,
                "password": password,
                "password_confirmation": password,
                "consent": True,
            },
            timeout=10,
        )
        assert register.status_code == 201
        token = register.json()["data"]["token"]

        login = http_session.post(
            f"{api_url}/auth/login",
            json={"email": email, "password": password},
            timeout=10,
        )
        assert login.status_code == 200
        assert login.json()["data"]["token"]

        logout = http_session.post(
            f"{api_url}/auth/logout",
            headers={"Authorization": f"Bearer {token}"},
            timeout=10,
        )
        assert logout.status_code == 200

    def test_invalid_login_returns_422(self, api_url: str, http_session: requests.Session) -> None:
        response = http_session.post(
            f"{api_url}/auth/login",
            json={"email": "not-valid", "password": ""},
            timeout=10,
        )
        assert response.status_code == 422
        assert "errors" in response.json()

    def test_users_api_requires_auth(self, api_url: str, http_session: requests.Session) -> None:
        response = http_session.get(f"{api_url}/users", timeout=10)
        assert response.status_code == 401

    def test_users_api_with_token(self, api_url: str, registered_user: dict) -> None:
        response = requests.get(
            f"{api_url}/users",
            headers={"Authorization": f"Bearer {registered_user['token']}"},
            timeout=10,
        )
        assert response.status_code == 403
