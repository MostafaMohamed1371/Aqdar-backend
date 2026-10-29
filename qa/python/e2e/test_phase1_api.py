"""Phase 1 API E2E against the running Laravel app (OTP, config, leftover locks)."""
from __future__ import annotations

import time

import pytest
import requests


@pytest.mark.usefixtures("ensure_app_is_running")
class TestPhase1ApiE2E:
    def test_config_exposes_sales_contact(self, api_url: str) -> None:
        response = requests.get(f"{api_url}/config", timeout=10)
        assert response.status_code == 200
        data = response.json()["data"]
        assert data["currency"] == "EGP"
        assert "name" in data["sales_contact"]
        assert "phone" in data["sales_contact"]
        assert "hours" in data["sales_contact"]

    def test_otp_send_verify_me_and_devices(self, api_url: str) -> None:
        phone = f"010{str(int(time.time() * 1000))[-8:]}"

        send = requests.post(
            f"{api_url}/auth/otp/send",
            json={"phone": phone, "consent": True},
            timeout=10,
        )
        assert send.status_code == 200, send.text
        body = send.json()
        assert "code" not in body
        assert "code" not in body.get("data", {})

        bypass = requests.post(
            f"{api_url}/auth/otp/verify",
            json={"phone": "01100000000", "code": "123456"},
            timeout=10,
        )
        assert bypass.status_code == 422

        verify = requests.post(
            f"{api_url}/auth/otp/verify",
            json={"phone": phone, "code": "123456"},
            timeout=10,
        )
        assert verify.status_code == 200, verify.text
        payload = verify.json()["data"]
        assert "code" not in payload
        token = payload["token"]
        user = payload["user"]
        assert user["is_buyer"] is True
        assert user["phone"].startswith("+20")

        me = requests.get(
            f"{api_url}/me",
            headers={"Authorization": f"Bearer {token}"},
            timeout=10,
        )
        assert me.status_code == 200
        assert me.json()["data"]["id"] == user["id"]

        patched = requests.patch(
            f"{api_url}/me",
            headers={"Authorization": f"Bearer {token}"},
            json={"is_seller": True, "phone": "01000000000"},
            timeout=10,
        )
        assert patched.status_code == 200
        assert patched.json()["data"]["is_seller"] is True
        assert patched.json()["data"]["phone"] == user["phone"]

        device = requests.post(
            f"{api_url}/devices",
            headers={"Authorization": f"Bearer {token}"},
            json={"device_uuid": f"e2e-{phone}", "platform": "ios"},
            timeout=10,
        )
        assert device.status_code == 201

    def test_settings_and_category_writes_are_locked(self, api_url: str, registered_user: dict) -> None:
        headers = {"Authorization": f"Bearer {registered_user['token']}"}

        guest_settings = requests.get(f"{api_url}/settings", timeout=10)
        assert guest_settings.status_code == 401

        auth_settings = requests.get(f"{api_url}/settings", headers=headers, timeout=10)
        assert auth_settings.status_code == 403

        create_setting = requests.post(
            f"{api_url}/settings",
            headers=headers,
            json={"key": "hack", "value": "1", "group": "general"},
            timeout=10,
        )
        assert create_setting.status_code == 403

        create_category = requests.post(
            f"{api_url}/categories",
            headers=headers,
            json={"name": "Hack", "slug": "hack"},
            timeout=10,
        )
        assert create_category.status_code == 403
