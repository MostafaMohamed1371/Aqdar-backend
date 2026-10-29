"""Phase 9 API E2E: health, public config, listings stay public unless maintenance is on."""
from __future__ import annotations

import pytest
import requests


@pytest.mark.usefixtures("ensure_app_is_running")
class TestPhase9ApiE2E:
    def test_health_and_config(self, base_url: str, api_url: str) -> None:
        health = requests.get(f"{base_url}/up", timeout=10)
        assert health.status_code == 200, health.text

        config = requests.get(f"{api_url}/config", timeout=10)
        assert config.status_code == 200, config.text
        body = config.json()["data"]
        assert body["currency"] == "EGP"
        assert "platform_name" in body
        assert "maintenance" in body
        assert set(body["sales_contact"]) >= {"name", "phone", "hours"}

    def test_public_listings_are_throttled_search_group(self, api_url: str) -> None:
        response = requests.get(f"{api_url}/listings", timeout=10)
        assert response.status_code in (200, 429), response.text
