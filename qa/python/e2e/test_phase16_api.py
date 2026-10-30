"""Phase 16 API E2E: no new marketplace routes. Staff RBAC is Blade only."""
from __future__ import annotations

import json

import pytest
import requests


@pytest.mark.usefixtures("ensure_app_is_running")
class TestPhase16ApiE2E:
    def test_config_stays_public_without_staff_permissions(self, api_url: str) -> None:
        response = requests.get(f"{api_url}/config", timeout=10)
        assert response.status_code == 200, response.text
        encoded = json.dumps(response.json())
        assert "advance_finance_stages" not in encoded
        assert "approve_commissions" not in encoded
        assert "view_commissions" not in encoded
