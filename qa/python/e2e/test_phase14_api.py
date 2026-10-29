"""Phase 14 API E2E: cases are visible to buyer or seller; no counterparty PII."""
from __future__ import annotations

import json
import os
import time

import pytest
import requests


def _auth_headers(token: str) -> dict[str, str]:
    return {"Accept": "application/json", "Authorization": f"Bearer {token}"}


def _token(api_url: str) -> tuple[str, dict]:
    phone = f"010{str(int(time.time() * 1000))[-8:]}"
    requests.post(f"{api_url}/auth/otp/send", json={"phone": phone, "consent": True}, timeout=10)
    verify = requests.post(
        f"{api_url}/auth/otp/verify",
        json={"phone": phone, "code": "123456"},
        timeout=10,
    )
    assert verify.status_code == 200, verify.text
    payload = verify.json()["data"]
    return payload["token"], payload["user"]


def _extract_csrf(html: str) -> str:
    marker = 'name="_token" value="'
    start = html.find(marker)
    assert start != -1, "CSRF token not found"
    start += len(marker)
    end = html.find('"', start)
    return html[start:end]


def _staff_session(base_url: str) -> requests.Session | None:
    session = requests.Session()
    login_page = session.get(f"{base_url}/dashboard", timeout=10)
    if login_page.status_code != 200 or 'name="_token"' not in login_page.text:
        return None
    token = _extract_csrf(login_page.text)
    response = session.post(
        f"{base_url}/dashboard/login",
        data={
            "_token": token,
            "email": os.getenv("QA_STAFF_EMAIL", "test@example.com"),
            "password": os.getenv("QA_STAFF_PASSWORD", "password"),
        },
        timeout=10,
        allow_redirects=True,
    )
    if response.status_code != 200 or 'data-screen="ADM-01"' not in response.text:
        return None
    return session


@pytest.mark.usefixtures("ensure_app_is_running")
class TestPhase14ApiE2E:
    def test_guest_cannot_list_cases(self, api_url: str) -> None:
        response = requests.get(f"{api_url}/my/cases", timeout=10)
        assert response.status_code == 401, response.text

    def test_authenticated_case_list_hides_internal_stage(self, api_url: str) -> None:
        token, _user = _token(api_url)
        response = requests.get(f"{api_url}/my/cases", headers=_auth_headers(token), timeout=10)
        assert response.status_code == 200, response.text
        encoded = json.dumps(response.json())
        assert "internal_stage" not in encoded
        assert "internal_note" not in encoded
        for row in response.json().get("data", []):
            assert "buyer" not in row
            assert "seller" not in row
            if "timeline" in row:
                for item in row["timeline"]:
                    assert item.get("key") != "new_client"

    def test_seller_can_open_own_case_without_buyer_objects(self, api_url: str, base_url: str) -> None:
        staff = _staff_session(base_url)
        if staff is None:
            pytest.skip("staff seed login is not available")

        seller_token, _seller = _token(api_url)
        listing = requests.post(
            f"{api_url}/my/listings",
            headers=_auth_headers(seller_token),
            json={},
            timeout=10,
        )
        assert listing.status_code == 201, listing.text
        listing_id = listing.json()["data"]["id"]

        _buyer_token, buyer = _token(api_url)
        cases_page = staff.get(f"{base_url}/dashboard/cases", timeout=10)
        assert cases_page.status_code == 200, cases_page.text
        csrf = _extract_csrf(cases_page.text)
        created = staff.post(
            f"{base_url}/dashboard/cases",
            data={
                "_token": csrf,
                "buyer_id": buyer["id"],
                "listing_id": listing_id,
                "reason": "e2e phase 14",
            },
            timeout=10,
            allow_redirects=True,
        )
        assert created.status_code == 200, created.text

        listed = requests.get(f"{api_url}/my/cases", headers=_auth_headers(seller_token), timeout=10)
        assert listed.status_code == 200, listed.text
        rows = listed.json().get("data", [])
        assert rows, listed.text
        case_id = rows[0]["id"]
        assert rows[0].get("viewer") == "seller"
        assert "buyer" not in rows[0]
        assert "seller" not in rows[0]
        assert "internal_stage" not in rows[0]

        show = requests.get(f"{api_url}/my/cases/{case_id}", headers=_auth_headers(seller_token), timeout=10)
        assert show.status_code == 200, show.text
        encoded = json.dumps(show.json())
        assert "internal_stage" not in encoded
        assert buyer.get("phone") not in encoded
        assert "new_client" not in encoded
        timeline = show.json()["data"].get("timeline", [])
        assert timeline
        assert timeline[0].get("key") == "interest_received"
        assert timeline[0].get("at")
