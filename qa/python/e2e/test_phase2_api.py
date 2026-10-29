"""Phase 2 API E2E against the running Laravel app (catalog, locations, content)."""
from __future__ import annotations

import pytest
import requests


@pytest.mark.usefixtures("ensure_app_is_running")
class TestPhase2ApiE2E:
    def test_categories_tree_has_distressed_a_and_b(self, api_url: str) -> None:
        response = requests.get(f"{api_url}/categories", timeout=10)
        assert response.status_code == 200, response.text
        tree = response.json()["data"]
        distressed = next((item for item in tree if item["slug"] == "distressed"), None)
        assert distressed is not None
        children = distressed.get("children") or []
        assert len(children) == 2
        assert {child["distressed_child"] for child in children} == {"a", "b"}
        assert any(item["slug"] == "non-distressed" for item in tree)
        helps = {child.get("help") for child in children}
        assert any(helps), children

    def test_locations_include_egypt_governorates(self, api_url: str) -> None:
        response = requests.get(f"{api_url}/locations", timeout=10)
        assert response.status_code == 200, response.text
        data = response.json()["data"]
        assert len(data) == 27
        slugs = {item["slug"] for item in data}
        assert {"cairo", "giza", "alexandria"} <= slugs

    def test_developers_projects_unit_types_amenities_and_content(self, api_url: str) -> None:
        developers = requests.get(f"{api_url}/developers", timeout=10)
        assert developers.status_code == 200, developers.text
        developer_rows = developers.json()["data"]
        assert any(row.get("is_other") is True for row in developer_rows)
        assert developer_rows[-1].get("is_other") is True
        tmg = next(row for row in developer_rows if row["slug"] == "tmg")

        projects = requests.get(
            f"{api_url}/projects",
            params={"developer_id": tmg["id"]},
            timeout=10,
        )
        assert projects.status_code == 200, projects.text
        project_slugs = {row["slug"] for row in projects.json()["data"]}
        assert "madinaty" in project_slugs
        assert "other" not in project_slugs

        unit_types = requests.get(f"{api_url}/unit-types", timeout=10)
        assert unit_types.status_code == 200
        assert any(row["slug"] == "apartment" for row in unit_types.json()["data"])

        amenities = requests.get(f"{api_url}/amenities", timeout=10)
        assert amenities.status_code == 200
        assert any(row["slug"] == "parking" for row in amenities.json()["data"])

        tags = requests.get(f"{api_url}/tags", timeout=10)
        assert tags.status_code == 200
        assert any(row["slug"] == "featured" for row in tags.json()["data"])

        faqs = requests.get(f"{api_url}/content/faqs", timeout=10)
        assert faqs.status_code == 200
        assert any(row["key"] == "what-is-distressed" for row in faqs.json()["data"])

        banners = requests.get(f"{api_url}/content/banners", timeout=10)
        assert banners.status_code == 200
        assert banners.json()["data"]
        assert "image_url" in banners.json()["data"][0]

        support = requests.get(f"{api_url}/content/support", timeout=10)
        assert support.status_code == 200

    def test_catalog_names_follow_accept_language(self, api_url: str) -> None:
        arabic = requests.get(
            f"{api_url}/developers",
            headers={"Accept-Language": "ar"},
            timeout=10,
        )
        assert arabic.status_code == 200
        assert any(row["name"] == "طلعت مصطفى" for row in arabic.json()["data"])

        english = requests.get(
            f"{api_url}/developers",
            headers={"Accept-Language": "en"},
            timeout=10,
        )
        assert english.status_code == 200
        assert any(row["name"] == "Talaat Moustafa Group" for row in english.json()["data"])

    def test_catalog_writes_are_not_marketplace_routes(self, api_url: str) -> None:
        for path in (
            "/developers",
            "/projects",
            "/locations",
            "/unit-types",
            "/amenities",
            "/tags",
            "/content/faqs",
            "/content/banners",
            "/content/support",
        ):
            response = requests.post(f"{api_url}{path}", json={"slug": "hack"}, timeout=10)
            assert response.status_code in {401, 403, 404, 405}, f"{path} returned {response.status_code}"
