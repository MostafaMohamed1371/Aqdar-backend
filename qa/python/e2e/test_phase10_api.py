"""Phase 10 API E2E: OTP consent, resend countdown, and locale on /me."""
from __future__ import annotations

import time

import pytest
import requests


def _phone() -> str:
    return f"010{str(int(time.time() * 1000))[-8:]}"


@pytest.mark.usefixtures("ensure_app_is_running")
class TestPhase10ApiE2E:
    def test_first_otp_send_without_consent_is_rejected(self, api_url: str) -> None:
        response = requests.post(f"{api_url}/auth/otp/send", json={"phone": _phone()}, timeout=10)
        assert response.status_code == 422, response.text
        assert "consent" in response.json().get("errors", {})

    def test_accepted_send_returns_resend_after_and_no_code(self, api_url: str) -> None:
        response = requests.post(
            f"{api_url}/auth/otp/send",
            json={"phone": _phone(), "consent": True, "locale": "en"},
            timeout=10,
        )
        assert response.status_code == 200, response.text
        body = response.json()
        assert isinstance(body["resend_after"], int)
        assert body["consent_required"] is False
        assert "code" not in body

    def test_verified_user_stores_consent_and_can_patch_locale(self, api_url: str) -> None:
        phone = _phone()
        requests.post(f"{api_url}/auth/otp/send", json={"phone": phone, "consent": True}, timeout=10)
        verify = requests.post(
            f"{api_url}/auth/otp/verify",
            json={"phone": phone, "code": "123456"},
            timeout=10,
        )
        assert verify.status_code == 200, verify.text
        token = verify.json()["data"]["token"]
        headers = {"Accept": "application/json", "Authorization": f"Bearer {token}"}

        me = requests.get(f"{api_url}/me", headers=headers, timeout=10)
        assert me.status_code == 200, me.text
        assert me.json()["data"]["consent_privacy_at"] is not None
        assert me.json()["data"]["consent_terms_at"] is not None

        patched = requests.patch(f"{api_url}/me", headers=headers, json={"locale": "en"}, timeout=10)
        assert patched.status_code == 200, patched.text
        assert patched.json()["data"]["locale"] == "en"

        bad = requests.patch(f"{api_url}/me", headers=headers, json={"locale": "fr"}, timeout=10)
        assert bad.status_code == 422, bad.text
