"""Phase 17 API E2E: POST /estimates manual inputs; missing % is never invented."""
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
class TestPhase17ApiE2E:
    def test_guest_cannot_post_manual_estimate(self, api_url: str) -> None:
        response = requests.post(
            f"{api_url}/estimates",
            json={"cancellation_percent": 10, "total_paid": 1450000},
            timeout=10,
        )
        assert response.status_code == 401, response.text

    def test_manual_estimate_without_percent_is_not_computable(self, api_url: str) -> None:
        token = _token(api_url)
        response = requests.post(
            f"{api_url}/estimates",
            headers=_auth_headers(token),
            json={
                "contract_price": 2900000,
                "total_paid": 1450000,
                "remaining": 1450000,
                "asking_price": 2650000,
            },
            timeout=10,
        )
        assert response.status_code == 200, response.text
        data = response.json()["data"]
        assert data["source"] == "manual"
        assert data["computable"] is False
        assert data["reason"] == "cancellation_percent_missing"
        assert data["cancellation"] is None
        assert data["market"]["certified_valuation"] is False
        assert data["disclaimer"]["text"]
        keys = [row["key"] for row in data["inputs"]]
        assert "cancellation_percent" in keys
        percent = next(row for row in data["inputs"] if row["key"] == "cancellation_percent")
        assert percent["verified"] is False
        assert percent["required"] is True

    def test_manual_estimate_computes_posted_percent(self, api_url: str) -> None:
        token = _token(api_url)
        response = requests.post(
            f"{api_url}/estimates",
            headers=_auth_headers(token),
            json={"total_paid": 1450000, "cancellation_percent": 10},
            timeout=10,
        )
        assert response.status_code == 200, response.text
        data = response.json()["data"]
        assert data["computable"] is True
        assert data["cancellation"]["percent"] == 10
        assert data["cancellation"]["percent_source"] == "manual"
        assert data["cancellation"]["penalty"] == 145000

    def test_manual_estimate_other_users_listing_is_hidden(self, api_url: str) -> None:
        owner = _token(api_url)
        created = requests.post(
            f"{api_url}/my/listings",
            headers=_auth_headers(owner),
            json={},
            timeout=10,
        )
        assert created.status_code == 201, created.text
        listing_id = created.json()["data"]["id"]

        other = _token(api_url)
        hidden = requests.post(
            f"{api_url}/estimates",
            headers=_auth_headers(other),
            json={
                "listing_id": listing_id,
                "cancellation_percent": 10,
                "total_paid": 1000000,
            },
            timeout=10,
        )
        assert hidden.status_code == 404, hidden.text
