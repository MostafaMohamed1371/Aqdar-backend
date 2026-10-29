"""Phase 3 API E2E against the running Laravel app (listings, search, favorites)."""
from __future__ import annotations

import time

import pytest
import requests


def _auth_headers(token: str) -> dict[str, str]:
    return {"Accept": "application/json", "Authorization": f"Bearer {token}"}


@pytest.mark.usefixtures("ensure_app_is_running")
class TestPhase3ApiE2E:
    def test_guest_lists_only_published_and_unpublished_is_404(self, api_url: str) -> None:
        listed = requests.get(f"{api_url}/listings", timeout=10)
        assert listed.status_code == 200, listed.text
        body = listed.json()
        assert "data" in body

        counts = requests.get(f"{api_url}/listings/counts", timeout=10)
        assert counts.status_code == 200, counts.text
        assert "non_distressed" in counts.json()["data"]

        missing = requests.get(f"{api_url}/listings/999999", timeout=10)
        assert missing.status_code == 404

        pins = requests.get(f"{api_url}/map", timeout=10)
        assert pins.status_code == 200, pins.text

    def test_public_listing_has_no_seller_pii(self, api_url: str) -> None:
        listed = requests.get(f"{api_url}/listings", timeout=10)
        assert listed.status_code == 200, listed.text
        rows = listed.json()["data"]
        if not rows:
            pytest.skip("no published listings seeded")
        detail = requests.get(f"{api_url}/listings/{rows[0]['id']}", timeout=10)
        assert detail.status_code == 200, detail.text
        payload = detail.json()
        encoded = json_dumps(payload)
        assert "user_id" not in encoded
        assert "documents_meta" not in encoded
        data = payload["data"]
        assert "sales_contact" in data
        assert "financial_summary" in data
        assert data.get("status") in {"published", "under_offer"}

    def test_seller_draft_is_not_public(self, api_url: str) -> None:
        phone = f"010{str(int(time.time() * 1000))[-8:]}"
        requests.post(f"{api_url}/auth/otp/send", json={"phone": phone, "consent": True}, timeout=10)
        verify = requests.post(
            f"{api_url}/auth/otp/verify",
            json={"phone": phone, "code": "123456"},
            timeout=10,
        )
        assert verify.status_code == 200, verify.text
        token = verify.json()["data"]["token"]

        created = requests.post(f"{api_url}/my/listings", headers=_auth_headers(token), json={}, timeout=10)
        assert created.status_code == 201, created.text
        listing_id = created.json()["data"]["id"]
        assert created.json()["data"]["status"] == "draft"
        assert created.json()["data"]["public_code"].startswith("AQ-")

        public = requests.get(f"{api_url}/listings/{listing_id}", timeout=10)
        assert public.status_code == 404

        guest_fav = requests.post(f"{api_url}/favorites/{listing_id}", timeout=10)
        assert guest_fav.status_code in {401, 403, 422}

        mine = requests.get(f"{api_url}/my/listings?status=draft", headers=_auth_headers(token), timeout=10)
        assert mine.status_code == 200, mine.text
        assert any(row["id"] == listing_id for row in mine.json()["data"])


def json_dumps(payload: object) -> str:
    import json

    return json.dumps(payload)
