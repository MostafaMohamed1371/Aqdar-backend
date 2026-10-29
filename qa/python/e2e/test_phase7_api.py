"""Phase 7 API E2E: owner-only exit estimate; missing % is never invented."""
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
class TestPhase7ApiE2E:
    def test_guest_cannot_read_estimate(self, api_url: str) -> None:
        listed = requests.get(f"{api_url}/listings", timeout=10)
        assert listed.status_code == 200, listed.text
        rows = listed.json()["data"]
        listing_id = rows[0]["id"] if rows else 1
        response = requests.get(f"{api_url}/listings/{listing_id}/estimate", timeout=10)
        assert response.status_code == 401, response.text

    def test_owner_estimate_without_percent_is_not_computable(self, api_url: str) -> None:
        token = _token(api_url)
        headers = _auth_headers(token)
        created = requests.post(f"{api_url}/my/listings", headers=headers, json={}, timeout=10)
        assert created.status_code == 201, created.text
        listing_id = created.json()["data"]["id"]

        estimate = requests.get(f"{api_url}/listings/{listing_id}/estimate", headers=headers, timeout=10)
        assert estimate.status_code == 200, estimate.text
        data = estimate.json()["data"]
        assert data["computable"] is False
        assert data["reason"] == "cancellation_percent_missing"
        assert data["cancellation"] is None
        assert data["market"]["label"] == "estimate"
        assert data["market"]["certified_valuation"] is False
        assert data["disclaimer"]["text"]
        keys = [row["key"] for row in data["inputs"]]
        assert "cancellation_percent" in keys

        other = _token(api_url)
        hidden = requests.get(
            f"{api_url}/listings/{listing_id}/estimate",
            headers=_auth_headers(other),
            timeout=10,
        )
        assert hidden.status_code == 404, hidden.text

        financials = requests.put(
            f"{api_url}/my/listings/{listing_id}/step/financials",
            headers=headers,
            json={
                "declared_total_paid": 1450000,
                "declared_cancellation_percent": 10,
            },
            timeout=10,
        )
        assert financials.status_code in (200, 201), financials.text
        computed = requests.get(f"{api_url}/listings/{listing_id}/estimate", headers=headers, timeout=10)
        assert computed.status_code == 200, computed.text
        body = computed.json()["data"]
        assert body["computable"] is True
        assert body["cancellation"]["percent"] == 10
        assert body["cancellation"]["percent_source"] == "declared"
        assert body["cancellation"]["penalty"] == 145000
