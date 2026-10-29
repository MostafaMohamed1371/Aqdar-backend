"""Phase 5 API E2E: inquiries, sales contact, anonymized seller interest."""
from __future__ import annotations

import time

import pytest
import requests


def _auth_headers(token: str) -> dict[str, str]:
    return {"Accept": "application/json", "Authorization": f"Bearer {token}"}


@pytest.mark.usefixtures("ensure_app_is_running")
class TestPhase5ApiE2E:
    def test_sales_contact_config_is_public(self, api_url: str) -> None:
        response = requests.get(f"{api_url}/config/sales-contact", timeout=10)
        assert response.status_code == 200, response.text
        data = response.json()["data"]
        assert "name" in data
        assert "phone" in data
        assert "hours" in data

    def test_guest_cannot_create_inquiry(self, api_url: str) -> None:
        response = requests.post(
            f"{api_url}/inquiries",
            json={"listing_id": 1, "contact_method": "phone", "consent": True},
            timeout=10,
        )
        assert response.status_code == 401, response.text

    def test_inquiry_on_published_listing_returns_sales_contact(self, api_url: str) -> None:
        listed = requests.get(f"{api_url}/listings", timeout=10)
        assert listed.status_code == 200, listed.text
        rows = listed.json()["data"]
        if not rows:
            pytest.skip("no published listings seeded")

        listing_id = rows[0]["id"]
        phone = f"010{str(int(time.time() * 1000))[-8:]}"
        requests.post(f"{api_url}/auth/otp/send", json={"phone": phone, "consent": True}, timeout=10)
        verify = requests.post(
            f"{api_url}/auth/otp/verify",
            json={"phone": phone, "code": "123456"},
            timeout=10,
        )
        assert verify.status_code == 200, verify.text
        token = verify.json()["data"]["token"]
        headers = _auth_headers(token)

        created = requests.post(
            f"{api_url}/inquiries",
            headers=headers,
            json={
                "listing_id": listing_id,
                "contact_method": "phone",
                "preferred_time": "evening",
                "consent": True,
            },
            timeout=10,
        )
        assert created.status_code in (201, 422), created.text
        if created.status_code == 422:
            pytest.skip("published listing belongs to this buyer or validation blocked")
        body = created.json()["data"]
        assert body["public_code"].startswith("INQ-")
        assert "sales_contact" in body
        assert "phone" in body["sales_contact"]

        mine = requests.get(f"{api_url}/my/inquiries", headers=headers, timeout=10)
        assert mine.status_code == 200, mine.text
        assert mine.json()["data"][0]["id"] == body["id"]
        assert body.get("kind") == "listing_inquiry"
        assert "next_steps" in body
        filtered = requests.get(f"{api_url}/my/inquiries", headers=headers, params={"status": "new"}, timeout=10)
        assert filtered.status_code == 200, filtered.text
        callback = requests.post(f"{api_url}/my/inquiries/{body['id']}/callback", headers=headers, timeout=10)
        assert callback.status_code == 200, callback.text
