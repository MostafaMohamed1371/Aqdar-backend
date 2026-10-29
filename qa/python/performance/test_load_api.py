"""Performance tests — response time, throughput, CPU and memory."""
import os
import statistics
import time
from concurrent.futures import ThreadPoolExecutor, as_completed

import psutil
import pytest
import requests


def _sample_resources() -> dict:
    proc = psutil.Process(os.getpid())
    return {
        "cpu_percent": psutil.cpu_percent(interval=0.1),
        "memory_mb": proc.memory_info().rss / (1024 * 1024),
    }


@pytest.mark.performance
class TestApiPerformance:
    def test_health_endpoint_response_time(self, base_url: str, ensure_app_is_running) -> None:
        times: list[float] = []
        for _ in range(20):
            start = time.perf_counter()
            response = requests.get(f"{base_url}/up", timeout=5)
            elapsed = time.perf_counter() - start
            assert response.status_code == 200
            times.append(elapsed)

        p95 = sorted(times)[int(len(times) * 0.95) - 1]
        avg = statistics.mean(times)
        print(f"\nHealth /up — avg: {avg*1000:.1f}ms, p95: {p95*1000:.1f}ms")
        assert p95 < 1.0, f"p95 response time too high: {p95:.3f}s"

    def test_dashboard_load_under_concurrency(self, base_url: str, ensure_app_is_running) -> None:
        concurrency = int(os.getenv("QA_PERF_CONCURRENCY", "10"))
        requests_count = int(os.getenv("QA_PERF_REQUESTS", "50"))
        max_p95_ms = float(os.getenv("QA_PERF_MAX_P95_MS", "2000"))

        def fetch_dashboard() -> float:
            start = time.perf_counter()
            response = requests.get(f"{base_url}/dashboard", timeout=15)
            elapsed = time.perf_counter() - start
            if response.status_code != 200:
                raise RuntimeError(f"Unexpected status: {response.status_code}")
            return elapsed

        before = _sample_resources()
        times: list[float] = []

        with ThreadPoolExecutor(max_workers=concurrency) as pool:
            futures = [pool.submit(fetch_dashboard) for _ in range(requests_count)]
            for future in as_completed(futures):
                times.append(future.result())

        after = _sample_resources()
        p95 = sorted(times)[int(len(times) * 0.95) - 1] * 1000
        avg = statistics.mean(times) * 1000
        throughput = requests_count / sum(times)

        print(
            f"\nDashboard load — concurrency: {concurrency}, requests: {requests_count}\n"
            f"  avg: {avg:.1f}ms, p95: {p95:.1f}ms, throughput: {throughput:.1f} req/s\n"
            f"  CPU: {after['cpu_percent']:.1f}%, memory: {after['memory_mb']:.1f} MB"
        )

        assert p95 < max_p95_ms, f"p95 {p95:.0f}ms exceeds {max_p95_ms}ms budget"

    def test_api_login_performance(self, api_url: str, registered_user: dict, ensure_app_is_running) -> None:
        """Measure authenticated API response (avoids login rate limit)."""
        times: list[float] = []
        headers = {"Authorization": f"Bearer {registered_user['token']}"}

        for _ in range(10):
            start = time.perf_counter()
            response = requests.get(f"{api_url}/auth/me", headers=headers, timeout=10)
            elapsed = time.perf_counter() - start
            assert response.status_code == 200
            times.append(elapsed)

        p95 = sorted(times)[int(len(times) * 0.95) - 1] * 1000
        print(f"\nAPI /auth/me p95: {p95:.1f}ms")
        assert p95 < 3000, f"/auth/me p95 too slow: {p95:.0f}ms"
