"""Shared fixtures for Structer Python QA suite."""
import os
import subprocess
import time

import pytest
import requests

BASE_URL = os.getenv("QA_BASE_URL", "http://127.0.0.1:8000").rstrip("/")
API_URL = f"{BASE_URL}/api/v1"


@pytest.fixture(scope="session")
def base_url() -> str:
    return BASE_URL


@pytest.fixture(scope="session")
def api_url() -> str:
    return API_URL


@pytest.fixture(scope="session")
def http_session() -> requests.Session:
    session = requests.Session()
    session.headers.update({"Accept": "application/json"})
    return session


@pytest.fixture(scope="session")
def ensure_app_is_running(base_url: str) -> None:
    """Skip Python tests if Laravel is not reachable."""
    try:
        response = requests.get(f"{base_url}/up", timeout=3)
        if response.status_code != 200:
            pytest.skip(f"App health check failed: {response.status_code}")
    except requests.RequestException as exc:
        pytest.skip(f"Laravel app not running at {base_url}: {exc}")


@pytest.fixture
def registered_user(api_url: str, http_session: requests.Session) -> dict:
    """Register a unique test user via API."""
    suffix = str(int(time.time() * 1000))
    payload = {
        "name": "QA User",
        "email": f"qa-{suffix}@example.com",
        "password": "password123",
        "password_confirmation": "password123",
        "consent": True,
    }
    response = http_session.post(f"{api_url}/auth/register", json=payload, timeout=10)
    assert response.status_code == 201, response.text
    data = response.json()["data"]
    return {
        "email": payload["email"],
        "password": payload["password"],
        "token": data["token"],
        "user": data["user"],
    }
