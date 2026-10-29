"""Phase 15 API E2E: marketplace inbox stays PII-safe; guest 401."""
from __future__ import annotations

import json
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
class TestPhase15ApiE2E:
    def test_guest_cannot_read_notifications(self, api_url: str) -> None:
        response = requests.get(f"{api_url}/notifications", timeout=10)
        assert response.status_code == 401, response.text

    def test_authenticated_inbox_has_no_phone_fields(self, api_url: str) -> None:
        token = _token(api_url)
        response = requests.get(f"{api_url}/notifications", headers=_auth_headers(token), timeout=10)
        assert response.status_code == 200, response.text
        encoded = json.dumps(response.json())
        assert "phone" not in encoded
        assert "internal_stage" not in encoded
        for row in response.json().get("data", []):
            assert row.get("event") != "staff_new_submission"
            assert row.get("event") != "staff_unassigned_lead"
            assert row.get("event") != "staff_new_buyer_request"
