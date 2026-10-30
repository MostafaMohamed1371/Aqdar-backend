"""Phase 18 API E2E: account closure request and saved-search CRUD."""
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
class TestPhase18ApiE2E:
    def test_guest_cannot_close_account(self, api_url: str) -> None:
        response = requests.post(
            f"{api_url}/me/close",
            json={"reason": "أريد إغلاق الحساب"},
            timeout=10,
        )
        assert response.status_code == 401, response.text

    def test_guest_cannot_manage_saved_searches(self, api_url: str) -> None:
        response = requests.get(f"{api_url}/saved-searches", timeout=10)
        assert response.status_code == 401, response.text
        created = requests.post(
            f"{api_url}/saved-searches",
            json={"filters": {"segment": "distressed"}},
            timeout=10,
        )
        assert created.status_code == 401, created.text

    def test_authenticated_user_can_save_a_search(self, api_url: str) -> None:
        token = _token(api_url)
        response = requests.post(
            f"{api_url}/saved-searches",
            headers=_auth_headers(token),
            json={"name": "بحث تجريبي", "notify": True, "filters": {"price_max": 3000000}},
            timeout=10,
        )
        assert response.status_code == 201, response.text
        data = response.json()["data"]
        assert data["notify"] is True
        assert data["filters"]["price_max"] == 3000000
        listed = requests.get(f"{api_url}/saved-searches", headers=_auth_headers(token), timeout=10)
        assert listed.status_code == 200, listed.text
        assert any(row["id"] == data["id"] for row in listed.json()["data"])
        patched = requests.patch(
            f"{api_url}/saved-searches/{data['id']}",
            headers=_auth_headers(token),
            json={"notify": False, "name": "بحث محدث"},
            timeout=10,
        )
        assert patched.status_code == 200, patched.text
        assert patched.json()["data"]["notify"] is False
        deleted = requests.delete(
            f"{api_url}/saved-searches/{data['id']}",
            headers=_auth_headers(token),
            timeout=10,
        )
        assert deleted.status_code == 200, deleted.text
        after = requests.get(f"{api_url}/saved-searches", headers=_auth_headers(token), timeout=10)
        assert after.status_code == 200, after.text
        assert all(row["id"] != data["id"] for row in after.json()["data"])

    def test_close_request_revokes_the_token(self, api_url: str) -> None:
        token = _token(api_url)
        response = requests.post(
            f"{api_url}/me/close",
            headers=_auth_headers(token),
            json={"reason": "أريد إغلاق الحساب"},
            timeout=10,
        )
        assert response.status_code == 200, response.text
        body = response.json()
        assert body["data"]["is_close_pending"] is True
        me = requests.get(f"{api_url}/me", headers=_auth_headers(token), timeout=10)
        assert me.status_code == 401, me.text
