"""Phase 13 API E2E: listing fields stay private where required."""
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
class TestPhase13ApiE2E:
    def test_public_categories_omit_commission_policy_code(self, api_url: str) -> None:
        response = requests.get(f"{api_url}/categories", timeout=10)
        assert response.status_code == 200, response.text
        encoded = json.dumps(response.json())
        assert "commission_policy_code" not in encoded

    def test_public_listings_omit_unit_code_and_reviewer(self, api_url: str) -> None:
        response = requests.get(f"{api_url}/listings", timeout=10)
        assert response.status_code in (200, 429), response.text
        if response.status_code == 429:
            pytest.skip("search throttle")
        encoded = json.dumps(response.json())
        assert "unit_code" not in encoded
        assert "assigned_reviewer" not in encoded
        assert "commission_policy_code" not in encoded

    def test_owner_specs_round_trip_phase_parking_unit_code(self, api_url: str) -> None:
        token = _token(api_url)
        headers = _auth_headers(token)
        created = requests.post(f"{api_url}/my/listings", headers=headers, json={}, timeout=10)
        assert created.status_code == 201, created.text
        listing_id = created.json()["data"]["id"]

        saved = requests.put(
            f"{api_url}/my/listings/{listing_id}/step/specs",
            headers=headers,
            json={"phase": "المرحلة 3", "parking": 1, "unit_code": "E2E-B-12"},
            timeout=10,
        )
        assert saved.status_code == 200, saved.text
        data = saved.json()["data"]
        assert data["phase"] == "المرحلة 3"
        assert data["parking"] == 1
        assert data["unit_code"] == "E2E-B-12"

        public = requests.get(f"{api_url}/listings/{listing_id}", timeout=10)
        assert public.status_code == 404
