"""E2E: clickable HTML mockups must expose every APP-* and ADM-* screen.

Requires AQDAR_UI_MOCKUP_PATH pointing at aqdar-ui-v2 (flutter.html + dashboard.html).
This is the UI contract until the real Flutter app and React admin exist.
"""

from __future__ import annotations

import json
import os
from pathlib import Path

import pytest

ROOT = Path(__file__).resolve().parents[3]
CONTRACT = ROOT / "tests" / "Fixtures" / "ui-screen-contract.json"


def _mockup_root() -> Path | None:
    raw = os.getenv("AQDAR_UI_MOCKUP_PATH", "").strip()
    if raw:
        path = Path(raw)
        return path if path.is_dir() else None
    fallback = Path("/Users/mostafa.osmananmat.sa/anaylsis project/aqdar-ui-v2")
    return fallback if fallback.is_dir() else None


@pytest.fixture(scope="module")
def mockup_root() -> Path:
    root = _mockup_root()
    if root is None:
        pytest.skip("AQDAR_UI_MOCKUP_PATH is not set and default aqdar-ui-v2 path is missing")
    return root


@pytest.fixture(scope="module")
def contract() -> dict:
    return json.loads(CONTRACT.read_text(encoding="utf-8"))


def test_flutter_html_declares_all_app_screens(mockup_root: Path, contract: dict) -> None:
    html = (mockup_root / contract["mobile"]["file"]).read_text(encoding="utf-8")
    missing = [screen_id for screen_id in contract["mobile"]["ids"] if f'id:"{screen_id}"' not in html]
    assert missing == [], f"flutter.html missing {missing}"


def test_dashboard_html_declares_all_adm_screens(mockup_root: Path, contract: dict) -> None:
    html = (mockup_root / contract["dashboard"]["file"]).read_text(encoding="utf-8")
    missing = [screen_id for screen_id in contract["dashboard"]["ids"] if f'id:"{screen_id}"' not in html]
    assert missing == [], f"dashboard.html missing {missing}"


def test_dashboard_uses_ui_css_not_hub_styles(mockup_root: Path, contract: dict) -> None:
    dashboard = (mockup_root / contract["dashboard"]["file"]).read_text(encoding="utf-8")
    flutter = (mockup_root / contract["mobile"]["file"]).read_text(encoding="utf-8")
    assert contract["theme"]["dashboard_css"] in dashboard
    assert "styles.css" not in dashboard
    assert contract["theme"]["dashboard_css"] in flutter


def test_phase_1_mockup_screens_exist(mockup_root: Path, contract: dict) -> None:
    flutter = (mockup_root / contract["mobile"]["file"]).read_text(encoding="utf-8")
    dashboard = (mockup_root / contract["dashboard"]["file"]).read_text(encoding="utf-8")
    phase = contract["phases"]["1"]

    missing_app = [screen_id for screen_id in phase["mobile"] if f'id:"{screen_id}"' not in flutter]
    missing_adm = [screen_id for screen_id in phase["dashboard"] if f'id:"{screen_id}"' not in dashboard]

    assert missing_app == [], f"Phase 1 flutter.html missing {missing_app}"
    assert missing_adm == [], f"Phase 1 dashboard.html missing {missing_adm}"
    assert "إرسال رمز التحقق" in flutter
    assert "رمز التحقق" in flutter
    assert "الملف الشخصي" in flutter
    assert "المستخدمون والهويات" in dashboard
    assert "الموظفون والصلاحيات" in dashboard


def test_phase_2_mockup_screens_exist(mockup_root: Path, contract: dict) -> None:
    flutter = (mockup_root / contract["mobile"]["file"]).read_text(encoding="utf-8")
    dashboard = (mockup_root / contract["dashboard"]["file"]).read_text(encoding="utf-8")
    phase = contract["phases"]["2"]

    missing_app = [screen_id for screen_id in phase["mobile"] if f'id:"{screen_id}"' not in flutter]
    missing_adm = [screen_id for screen_id in phase["dashboard"] if f'id:"{screen_id}"' not in dashboard]

    assert missing_app == [], f"Phase 2 flutter.html missing {missing_app}"
    assert missing_adm == [], f"Phase 2 dashboard.html missing {missing_adm}"
    assert "العقارات المتعثرة" in flutter
    assert "تصنيف الأنواع" in flutter
    assert "المساعدة والدعم" in flutter
    assert "المطورون والبيانات الأساسية" in dashboard
    assert "المحتوى والإشعارات" in dashboard
    assert "إدارة القيم المرجعية" in dashboard


def test_phase_3_mockup_screens_exist(mockup_root: Path, contract: dict) -> None:
    flutter = (mockup_root / contract["mobile"]["file"]).read_text(encoding="utf-8")
    dashboard = (mockup_root / contract["dashboard"]["file"]).read_text(encoding="utf-8")
    phase = contract["phases"]["3"]

    missing_app = [screen_id for screen_id in phase["mobile"] if f'id:"{screen_id}"' not in flutter]
    missing_adm = [screen_id for screen_id in phase["dashboard"] if f'id:"{screen_id}"' not in dashboard]
    assert missing_app == [], f"Phase 3 flutter.html missing {missing_app}"
    assert missing_adm == [], f"Phase 3 dashboard.html missing {missing_adm}"
    assert "نتائج البحث والترتيب" in flutter
    assert "خريطة العقارات" in flutter
    assert "تفاصيل العقار" in flutter
    assert "عقاراتي" in flutter
    assert "اعرض عقارك" in flutter
    assert "التفاصيل المالية" in flutter


def test_phase_4_mockup_screens_exist(mockup_root: Path, contract: dict) -> None:
    flutter = (mockup_root / contract["mobile"]["file"]).read_text(encoding="utf-8")
    dashboard = (mockup_root / contract["dashboard"]["file"]).read_text(encoding="utf-8")
    phase = contract["phases"]["4"]

    missing_app = [screen_id for screen_id in phase["mobile"] if f'id:"{screen_id}"' not in flutter]
    missing_adm = [screen_id for screen_id in phase["dashboard"] if f'id:"{screen_id}"' not in dashboard]
    assert missing_app == [], f"Phase 4 flutter.html missing {missing_app}"
    assert missing_adm == [], f"Phase 4 dashboard.html missing {missing_adm}"
    assert "مراجعة العقارات" in dashboard
    assert "المستندات الخاصة" in dashboard or "Private Document" in dashboard


def test_phase_5_mockup_screens_exist(mockup_root: Path, contract: dict) -> None:
    flutter = (mockup_root / contract["mobile"]["file"]).read_text(encoding="utf-8")
    dashboard = (mockup_root / contract["dashboard"]["file"]).read_text(encoding="utf-8")
    phase = contract["phases"]["5"]

    missing_app = [screen_id for screen_id in phase["mobile"] if f'id:"{screen_id}"' not in flutter]
    missing_adm = [screen_id for screen_id in phase["dashboard"] if f'id:"{screen_id}"' not in dashboard]
    assert missing_app == [], f"Phase 5 flutter.html missing {missing_app}"
    assert missing_adm == [], f"Phase 5 dashboard.html missing {missing_adm}"
    assert "طلب الاستفسار عن عقار" in flutter
    assert "استفساراتي" in flutter
    assert "موعد المعاينة" in flutter
    assert "المبيعات والعملاء المحتملون" in dashboard

def test_phase_6_mockup_screens_exist(mockup_root: Path, contract: dict) -> None:
    flutter = (mockup_root / contract["mobile"]["file"]).read_text(encoding="utf-8")
    dashboard = (mockup_root / contract["dashboard"]["file"]).read_text(encoding="utf-8")
    phase = contract["phases"]["6"]

    missing_app = [screen_id for screen_id in phase["mobile"] if f'id:"{screen_id}"' not in flutter]
    missing_adm = [screen_id for screen_id in phase["dashboard"] if f'id:"{screen_id}"' not in dashboard]
    assert missing_app == [], f"Phase 6 flutter.html missing {missing_app}"
    assert missing_adm == [], f"Phase 6 dashboard.html missing {missing_adm}"
    assert "طلب شراء مخصص" in flutter
    assert "متابعة الصفقة" in flutter
    assert "طلبات المشترين" in dashboard
    assert "حالات البيع وإعادة البيع" in dashboard
    assert "المرحلة الداخلية" in dashboard
    assert "ما يراه العميل" in dashboard


def test_phase_7_mockup_screens_exist(mockup_root: Path, contract: dict) -> None:
    flutter = (mockup_root / contract["mobile"]["file"]).read_text(encoding="utf-8")
    dashboard = (mockup_root / contract["dashboard"]["file"]).read_text(encoding="utf-8")
    phase = contract["phases"]["7"]

    missing_app = [screen_id for screen_id in phase["mobile"] if f'id:"{screen_id}"' not in flutter]
    missing_adm = [screen_id for screen_id in phase["dashboard"] if f'id:"{screen_id}"' not in dashboard]
    assert missing_app == [], f"Phase 7 flutter.html missing {missing_app}"
    assert missing_adm == [], f"Phase 7 dashboard.html missing {missing_adm}"
    assert "تقدير الخروج" in flutter
    assert "تقدير استرشادي غير ملزم" in flutter
    assert "العمولات والإيرادات" in dashboard
    assert "الملتزم بالدفع" in dashboard
    assert "المنصة لا تحصّل أموالاً" in dashboard


def test_phase_8_mockup_screens_exist(mockup_root: Path, contract: dict) -> None:
    flutter = (mockup_root / contract["mobile"]["file"]).read_text(encoding="utf-8")
    dashboard = (mockup_root / contract["dashboard"]["file"]).read_text(encoding="utf-8")
    phase = contract["phases"]["8"]

    missing_app = [screen_id for screen_id in phase["mobile"] if f'id:"{screen_id}"' not in flutter]
    missing_adm = [screen_id for screen_id in phase["dashboard"] if f'id:"{screen_id}"' not in dashboard]
    assert missing_app == [], f"Phase 8 flutter.html missing {missing_app}"
    assert missing_adm == [], f"Phase 8 dashboard.html missing {missing_adm}"
    assert "مركز الإشعارات" in flutter
    assert "إشعارات البائع" in flutter
    assert "الإشعارات الذكية" in flutter
    assert "نصوص الإشعارات لا تحتوي بيانات شخصية" in dashboard
    assert "سجل الإرسال" in dashboard


def test_phase_9_mockup_screens_exist(mockup_root: Path, contract: dict) -> None:
    flutter = (mockup_root / contract["mobile"]["file"]).read_text(encoding="utf-8")
    dashboard = (mockup_root / contract["dashboard"]["file"]).read_text(encoding="utf-8")
    phase = contract["phases"]["9"]

    missing_app = [screen_id for screen_id in phase["mobile"] if f'id:"{screen_id}"' not in flutter]
    missing_adm = [screen_id for screen_id in phase["dashboard"] if f'id:"{screen_id}"' not in dashboard]
    assert missing_app == [], f"Phase 9 flutter.html missing {missing_app}"
    assert missing_adm == [], f"Phase 9 dashboard.html missing {missing_adm}"
    assert "يحتاج إجراء الآن" in dashboard
    assert "قمع التحويل" in dashboard
    assert "التقارير والتصدير" in dashboard
    assert "التصدير الافتراضي بدون أرقام هواتف" in dashboard
    assert "الإعدادات والتدقيق الأمني" in dashboard
    assert "رقم التواصل الرسمي للمبيعات" in dashboard
    assert "سجل النشاط" in dashboard
    assert "السجل غير قابل للحذف" in dashboard

