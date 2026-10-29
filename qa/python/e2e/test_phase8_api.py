"""Phase 8 API E2E: in-app inbox is source of truth; guest 401."""
from __future__ import annotations

import time

import pytest
import requests


def _auth_headers(token: str) -> dict[str, str]:
    return {"Accept": "application/json", "Authorization": f"Bearer {token}"}


def _token(api_url: str) -> str:
    phone = f"010{str(int(time.time() * 1000))[-8:]}"
    requests.post(f"{api_url}/auth/otp/send", json={"phone": phone, "consent": True}, timeout=10)
    verify = requests.post(
        f"{api_url}/auth/otp/verify",
        json={"phone": phone, "code": "123456"},
        timeout=10,
    )
    assert verify.status_code == 200, verify.text
    return verify.json()["data"]["token"]


@pytest.mark.usefixtures("ensure_app_is_running")
class TestPhase8ApiE2E:
    def test_guest_cannot_read_notifications(self, api_url: str) -> None:
        response = requests.get(f"{api_url}/notifications", timeout=10)
        assert response.status_code == 401, response.text

    def test_inbox_and_preferences_roundtrip(self, api_url: str) -> None:
        token = _token(api_url)
        headers = _auth_headers(token)
        inbox = requests.get(f"{api_url}/notifications", headers=headers, timeout=10)
        assert inbox.status_code == 200, inbox.text
        body = inbox.json()
        assert "data" in body
        assert "unread_count" in body.get("meta", {})
        assert body["meta"]["smart_enabled"] is False

        current = requests.get(
            f"{api_url}/me/notification-preferences",
            headers=headers,
            timeout=10,
        )
        assert current.status_code == 200, current.text
        assert current.json()["data"]["push_enabled"] is True
        assert current.json()["data"]["marketing_opt_in"] is False
        assert current.json()["data"]["in_app_always_on"] is True

        prefs = requests.patch(
            f"{api_url}/me/notification-preferences",
            headers=headers,
            json={"push_enabled": False, "marketing_opt_in": False},
            timeout=10,
        )
        assert prefs.status_code == 200, prefs.text
        assert prefs.json()["data"]["push_enabled"] is False
        assert prefs.json()["data"]["in_app_always_on"] is True

        read_all = requests.post(f"{api_url}/notifications/read-all", headers=headers, timeout=10)
        assert read_all.status_code == 200, read_all.text
