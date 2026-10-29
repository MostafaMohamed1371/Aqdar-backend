"""Phase 6 API E2E: custom buyer requests always 201, client-safe cases."""
from __future__ import annotations

import time

import pytest
import requests


def _auth_headers(token: str) -> dict[str, str]:
    return {"Accept": "application/json", "Authorization": f"Bearer {token}"}


@pytest.mark.usefixtures("ensure_app_is_running")
class TestPhase6ApiE2E:
    def test_guest_cannot_create_buyer_request(self, api_url: str) -> None:
        response = requests.post(
            f"{api_url}/buyer-requests",
            json={"intent": "living", "contact_method": "phone", "consent": True},
            timeout=10,
        )
        assert response.status_code == 401, response.text

    def test_custom_request_is_created_even_without_matches(self, api_url: str) -> None:
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
            f"{api_url}/buyer-requests",
            headers=headers,
            json={
                "intent": "living",
                "contact_method": "phone",
                "consent": True,
                "budget_max": 2500000,
            },
            timeout=10,
        )
        assert created.status_code == 201, created.text
        body = created.json()["data"]
        assert body["public_code"].startswith("BR-")
        assert body["status"] == "in_review"
        assert body.get("kind") == "buyer_request"
        assert "internal_status" not in body
        assert isinstance(body.get("options"), list)

        mine = requests.get(f"{api_url}/my/buyer-requests", headers=headers, timeout=10)
        assert mine.status_code == 200, mine.text
        assert mine.json()["data"][0]["id"] == body["id"]

        inbox = requests.get(f"{api_url}/my/inquiries", headers=headers, timeout=10)
        assert inbox.status_code == 200, inbox.text
        kinds = [row.get("kind") for row in inbox.json()["data"]]
        assert "buyer_request" in kinds
        br_row = next(row for row in inbox.json()["data"] if row.get("kind") == "buyer_request")
        assert br_row.get("status") == "in_review"
        assert "label" in br_row
        assert "intent" in body
        assert "purchase_timeline" in body
        assert "timeline" in body

        cases = requests.get(f"{api_url}/my/cases", headers=headers, timeout=10)
        assert cases.status_code == 200, cases.text
