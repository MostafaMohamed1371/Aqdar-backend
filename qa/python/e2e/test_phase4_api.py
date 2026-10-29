"""Phase 4 API E2E: private documents, media hold, submit requires docs."""
from __future__ import annotations

import io
import time

import pytest
import requests


def _auth_headers(token: str) -> dict[str, str]:
    return {"Accept": "application/json", "Authorization": f"Bearer {token}"}


def _pdf() -> io.BytesIO:
    return io.BytesIO(b"%PDF-1.4 test document")


@pytest.mark.usefixtures("ensure_app_is_running")
class TestPhase4ApiE2E:
    def test_submit_blocked_without_documents_and_private_path_is_not_public(self, api_url: str) -> None:
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

        created = requests.post(f"{api_url}/my/listings", headers=headers, json={}, timeout=10)
        assert created.status_code == 201, created.text
        listing_id = created.json()["data"]["id"]

        blocked = requests.post(
            f"{api_url}/my/listings/{listing_id}/submit",
            headers=headers,
            json={"consent": True},
            timeout=10,
        )
        assert blocked.status_code == 422, blocked.text
        assert "documents" in blocked.json().get("errors", {})

        for doc_type in ("national_id", "contract", "payment_proof"):
            files = {"file": (f"{doc_type}.pdf", _pdf(), "application/pdf")}
            uploaded = requests.post(
                f"{api_url}/my/listings/{listing_id}/documents",
                headers=headers,
                data={"type": doc_type},
                files=files,
                timeout=10,
            )
            assert uploaded.status_code == 201, uploaded.text
            body = uploaded.json()["data"]
            assert "path" not in body
            assert body["type"] == doc_type

        mine = requests.get(f"{api_url}/my/documents", headers=headers, timeout=10)
        assert mine.status_code == 200, mine.text
        assert all("path" not in row for row in mine.json()["data"])

        public_docs = requests.get(f"{api_url}/listings/{listing_id}/documents", timeout=10)
        assert public_docs.status_code == 404

        after_docs = requests.post(
            f"{api_url}/my/listings/{listing_id}/submit",
            headers=headers,
            json={"consent": True},
            timeout=10,
        )
        assert after_docs.status_code == 422, after_docs.text
        assert "documents" not in after_docs.json().get("errors", {})

    def test_public_listing_photos_omit_unapproved_media(self, api_url: str) -> None:
        listed = requests.get(f"{api_url}/listings", timeout=10)
        assert listed.status_code == 200, listed.text
        rows = listed.json()["data"]
        if not rows:
            pytest.skip("no published listings seeded")
        listing_id = rows[0]["id"]
        detail = requests.get(f"{api_url}/listings/{listing_id}", timeout=10)
        assert detail.status_code == 200, detail.text
        data = detail.json()["data"]
        assert "documents" not in data
        assert "photos" in data
        media = requests.get(f"{api_url}/listings/{listing_id}/media", timeout=10)
        assert media.status_code == 200, media.text
        assert "data" in media.json()
