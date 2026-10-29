"""End-to-end tests using Selenium WebDriver against the Blade staff desk."""
import os
import subprocess
import time

import pytest
import requests
from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.common.by import By
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import WebDriverWait


def _chrome_major_version(chrome_bin: str) -> str | None:
    try:
        out = subprocess.check_output([chrome_bin, "--version"], text=True, timeout=15)
    except (OSError, subprocess.SubprocessError):
        return None

    for part in out.split():
        if part and part[0].isdigit():
            return part.split(".", 1)[0]

    return None


@pytest.fixture(scope="module")
def browser(base_url: str, ensure_app_is_running):
    """Launch headless Chrome for E2E tests."""
    if os.getenv("QA_SKIP_E2E", "").lower() in ("1", "true", "yes"):
        pytest.skip("E2E tests skipped (QA_SKIP_E2E=1)")

    options = Options()
    options.add_argument("--headless=new")
    options.add_argument("--no-sandbox")
    options.add_argument("--disable-dev-shm-usage")
    options.add_argument("--window-size=1920,1080")

    chrome_bin = os.getenv("CHROME_BIN")
    if chrome_bin:
        options.binary_location = chrome_bin

    chromedriver_path = os.getenv("CHROMEDRIVER_PATH")
    if chromedriver_path:
        service = Service(executable_path=chromedriver_path)
        driver = webdriver.Chrome(service=service, options=options)
    else:
        try:
            driver = webdriver.Chrome(options=options)
        except Exception:
            from webdriver_manager.chrome import ChromeDriverManager

            major = _chrome_major_version(chrome_bin) if chrome_bin else None
            driver_path = (
                ChromeDriverManager(driver_version=major).install()
                if major
                else ChromeDriverManager().install()
            )
            driver = webdriver.Chrome(service=Service(driver_path), options=options)

    driver.implicitly_wait(5)
    yield driver
    driver.quit()


def _staff_credentials() -> tuple[str, str]:
    return (
        os.getenv("QA_STAFF_EMAIL", "test@example.com"),
        os.getenv("QA_STAFF_PASSWORD", "password"),
    )


def _reset_to_guest(browser, base_url: str) -> None:
    """Clear session so /dashboard shows the staff login form."""
    browser.get(base_url)
    browser.delete_all_cookies()
    try:
        browser.execute_script("window.localStorage.clear(); window.sessionStorage.clear();")
    except Exception:
        pass


def _login_staff(browser, base_url: str) -> None:
    email, password = _staff_credentials()
    browser.get(f"{base_url}/dashboard")
    wait = WebDriverWait(browser, 10)
    wait.until(EC.presence_of_element_located((By.NAME, "email")))
    browser.find_element(By.NAME, "email").clear()
    browser.find_element(By.NAME, "email").send_keys(email)
    browser.find_element(By.NAME, "password").clear()
    browser.find_element(By.NAME, "password").send_keys(password)
    browser.find_element(By.CSS_SELECTOR, "button[type='submit']").click()
    wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, '[data-screen="ADM-01"]')))


@pytest.mark.e2e
class TestDashboardE2E:
    def test_guest_sees_login_page(self, browser, base_url: str) -> None:
        _reset_to_guest(browser, base_url)
        browser.get(f"{base_url}/dashboard")
        wait = WebDriverWait(browser, 10)
        email = wait.until(EC.presence_of_element_located((By.NAME, "email")))
        password = browser.find_element(By.NAME, "password")
        assert email.is_displayed()
        assert password.is_displayed()
        assert browser.find_element(By.CSS_SELECTOR, '[data-screen="staff-login"]')

    def test_password_toggle_shows_password(self, browser, base_url: str) -> None:
        _reset_to_guest(browser, base_url)
        browser.get(f"{base_url}/dashboard")
        wait = WebDriverWait(browser, 10)
        password = wait.until(EC.presence_of_element_located((By.NAME, "password")))
        toggle = browser.find_element(By.CSS_SELECTOR, "[data-password-toggle]")

        assert password.get_attribute("type") == "password"
        toggle.click()
        wait.until(lambda d: password.get_attribute("type") == "text")
        assert password.get_attribute("type") == "text"

    def test_marketplace_user_cannot_open_staff_desk(self, browser, base_url: str, api_url: str) -> None:
        _reset_to_guest(browser, base_url)
        suffix = str(int(time.time() * 1000))
        email = f"e2e-{suffix}@example.com"
        password = "password123"

        register = requests.post(
            f"{api_url}/auth/register",
            headers={"Accept": "application/json"},
            json={
                "name": "End To End User",
                "email": email,
                "password": password,
                "password_confirmation": password,
                "consent": True,
            },
            timeout=10,
        )
        assert register.status_code == 201, register.text

        browser.get(f"{base_url}/dashboard")
        wait = WebDriverWait(browser, 10)
        wait.until(EC.presence_of_element_located((By.NAME, "email")))
        browser.find_element(By.NAME, "email").send_keys(email)
        browser.find_element(By.NAME, "password").send_keys(password)
        browser.find_element(By.CSS_SELECTOR, "button[type='submit']").click()
        wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, '[data-screen="staff-login"]')))

    def test_staff_login_opens_adm_01_shell(self, browser, base_url: str) -> None:
        _reset_to_guest(browser, base_url)
        _login_staff(browser, base_url)
        assert browser.find_element(By.CSS_SELECTOR, '[data-screen="ADM-01"]')
        assert "dside" in browser.page_source
        assert "لوحة القيادة التنفيذية" in browser.page_source or "Executive Dashboard" in browser.page_source

    def test_staff_can_open_adm_04_users(self, browser, base_url: str) -> None:
        _reset_to_guest(browser, base_url)
        _login_staff(browser, base_url)
        browser.get(f"{base_url}/dashboard/users")
        wait = WebDriverWait(browser, 10)
        wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, '[data-screen="ADM-04"]')))
        assert "المستخدمون والهويات" in browser.page_source or "Users & Identities" in browser.page_source
        assert "الهاتف" in browser.page_source or "Phone" in browser.page_source

    def test_staff_can_open_adm_06_permissions(self, browser, base_url: str) -> None:
        _reset_to_guest(browser, base_url)
        _login_staff(browser, base_url)
        browser.get(f"{base_url}/dashboard/staff")
        wait = WebDriverWait(browser, 10)
        wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, '[data-screen="ADM-06"]')))
        assert "مصفوفة الصلاحيات" in browser.page_source or "Permission matrix" in browser.page_source
        assert "مدير عام" in browser.page_source or "Super admin" in browser.page_source


    def test_staff_can_open_adm_05_developers(self, browser, base_url: str) -> None:
        _reset_to_guest(browser, base_url)
        _login_staff(browser, base_url)
        browser.get(f"{base_url}/dashboard/developers")
        wait = WebDriverWait(browser, 10)
        wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, '[data-screen="ADM-05"]')))
        assert "المطورون والبيانات الأساسية" in browser.page_source or "Developers & Master Data" in browser.page_source

    def test_staff_can_open_adm_11_content(self, browser, base_url: str) -> None:
        _reset_to_guest(browser, base_url)
        _login_staff(browser, base_url)
        browser.get(f"{base_url}/dashboard/content")
        wait = WebDriverWait(browser, 10)
        wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, '[data-screen="ADM-11"]')))
        source = browser.page_source
        assert "المحتوى والإشعارات" in source or "Content & Notifications" in source
        assert "القوالب" in source or "Templates" in source
        assert "الإعلانات العامة" in source or "Public announcements" in source
        assert "نصوص الفئات" in source or "Category copy" in source
        assert "البانرات" in source or "Banners" in source
        assert "نصوص الإشعارات لا تحتوي بيانات شخصية" in source or "never includes another party" in source
        assert "سجل الإرسال" in source or "Send log" in source

    def test_staff_can_open_adm_15_reference(self, browser, base_url: str) -> None:
        _reset_to_guest(browser, base_url)
        _login_staff(browser, base_url)
        for path in ("/dashboard/categories", "/dashboard/locations", "/dashboard/unit-types", "/dashboard/amenities", "/dashboard/tags"):
            browser.get(f"{base_url}{path}")
            wait = WebDriverWait(browser, 10)
            wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, '[data-screen="ADM-15"]')))
        assert "إدارة القيم المرجعية" in browser.page_source or "Reference Data Management" in browser.page_source

    def test_staff_can_open_adm_02_listings_moderation(self, browser, base_url: str) -> None:
        _reset_to_guest(browser, base_url)
        _login_staff(browser, base_url)
        browser.get(f"{base_url}/dashboard/listings")
        wait = WebDriverWait(browser, 10)
        wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, '[data-screen="ADM-02"]')))
        source = browser.page_source
        assert "مراجعة العقارات" in source or "Listings Moderation" in source
        assert "الاعتماد والنشر صلاحيتان منفصلتان" in source or "Approve and publish are separate" in source

    def test_staff_can_open_adm_03_documents(self, browser, base_url: str) -> None:
        _reset_to_guest(browser, base_url)
        _login_staff(browser, base_url)
        browser.get(f"{base_url}/dashboard/documents")
        wait = WebDriverWait(browser, 10)
        wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, '[data-screen="ADM-03"]')))
        source = browser.page_source
        assert "مستند" in source or "Document" in source
        assert "رابط موقّع" in source or "signed URL" in source or "10-minute" in source

    def test_staff_can_open_adm_07_leads(self, browser, base_url: str) -> None:
        _reset_to_guest(browser, base_url)
        _login_staff(browser, base_url)
        browser.get(f"{base_url}/dashboard/leads")
        wait = WebDriverWait(browser, 10)
        wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, '[data-screen="ADM-07"]')))
        source = browser.page_source
        assert "المبيعات والعملاء المحتملون" in source or "Sales CRM" in source
        assert "SLA" in source or "عميل" in source or "Lead" in source
        assert "إضافة عميل محتمل" in source or "Add lead" in source
        assert "جميع المراحل" in source or "All stages" in source

    def test_staff_can_open_adm_08_buyer_requests(self, browser, base_url: str) -> None:
        _reset_to_guest(browser, base_url)
        _login_staff(browser, base_url)
        browser.get(f"{base_url}/dashboard/requests")
        wait = WebDriverWait(browser, 10)
        wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, '[data-screen="ADM-08"]')))
        source = browser.page_source
        assert "طلبات المشترين" in source or "Buyer Requests" in source
        assert "إضافة طلب" in source or "Add request" in source
        assert "كل الأنواع" in source or "All types" in source
        assert "شقق" in source or "Apartments" in source
        assert "المنطقة" in source or "Region" in source
        assert "تحويل لحالة / إغلاق" in source or "Converted / closed" in source

    def test_staff_can_open_adm_09_cases(self, browser, base_url: str) -> None:
        _reset_to_guest(browser, base_url)
        _login_staff(browser, base_url)
        browser.get(f"{base_url}/dashboard/cases")
        wait = WebDriverWait(browser, 10)
        wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, '[data-screen="ADM-09"]')))
        source = browser.page_source
        assert "حالات" in source or "Transfer" in source
        assert "المرحلة الداخلية" in source or "Internal stage" in source
        assert "ما يراه العميل" in source or "Client sees" in source
        assert "حالة الدورة" in source or "Lifecycle" in source
        assert "آخر تحديث" in source or "Updated" in source

    def test_staff_can_open_adm_10_commissions(self, browser, base_url: str) -> None:
        _reset_to_guest(browser, base_url)
        _login_staff(browser, base_url)
        browser.get(f"{base_url}/dashboard/commissions")
        wait = WebDriverWait(browser, 10)
        wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, '[data-screen="ADM-10"]')))
        source = browser.page_source
        assert "عمول" in source or "Commission" in source
        assert "تحميل تقرير" in source or "Download report" in source
        assert "إضافة عمولة" in source or "Add commission" in source
        assert "المنصة لا تحصّل أموالاً" in source or "does not collect money" in source
        assert "add-commission" in source
        assert "إجمالي العمولات" in source or "Total commissions" in source
        assert "العمولات المستحقة" in source or "Commissions due" in source
        assert "عدد الصفقات" in source or "Closed deals" in source
        assert "متوسط العمولة" in source or "Average commission" in source
        assert "الملتزم بالدفع" in source or "Payer" in source
        assert "قيمة البيع" in source or "Sale value" in source
        assert "حالة مكتملة" in source or "Completed case" in source
        assert "2.5" in source
        assert "الإيرادات الشهرية" in source or "Monthly revenue" in source
        assert "توزيع الإيرادات حسب النوع" in source or "Revenue by type" in source
        assert "إجمالي الإيرادات" in source or "Total revenue" in source
        assert "name=\"month\"" in source
        assert "data-chart=\"monthly\"" in source
        assert "data-chart=\"by-type\"" in source
        assert "<svg" in source

    def test_staff_can_open_adm_01_kpis(self, browser, base_url: str) -> None:
        _reset_to_guest(browser, base_url)
        _login_staff(browser, base_url)
        wait = WebDriverWait(browser, 10)
        wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, '[data-screen="ADM-01"]')))
        source = browser.page_source
        assert "name=\"from\"" in source
        assert "name=\"to\"" in source
        assert "يحتاج إجراء الآن" in source or "Needs action now" in source
        assert "قمع التحويل" in source or "Conversion funnel" in source
        assert "data-chart=\"listing-performance\"" in source
        assert "data-chart=\"listing-status\"" in source
        assert "data-chart=\"egypt-map\"" in source
        assert "آخر 30 يوم" in source or "Last 30 days" in source
        assert "data-ops-health" in source
        assert "assigned_to=unassigned" in source
        assert "<svg" in source

    def test_staff_can_open_adm_12_reports(self, browser, base_url: str) -> None:
        _reset_to_guest(browser, base_url)
        _login_staff(browser, base_url)
        browser.get(f"{base_url}/dashboard/reports")
        wait = WebDriverWait(browser, 10)
        wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, '[data-screen="ADM-12"]')))
        source = browser.page_source
        assert "التقارير والتصدير" in source or "Reports & Exports" in source
        assert "التصدير الافتراضي بدون أرقام هواتف" in source or "Default export has no phone" in source
        assert "تقرير العقارات" in source or "Listings report" in source
        assert "متوسط أول رد على العميل" in source or "Average first reply" in source
        assert "آخر 30 يوم" in source or "Last 30 days" in source or "أشهر" in source
        assert "data-chart=\"platform-overview\"" in source
        assert "CSV" in source
        assert "CSV" in source
        assert "PDF" in source

    def test_staff_can_open_adm_13_settings(self, browser, base_url: str) -> None:
        _reset_to_guest(browser, base_url)
        _login_staff(browser, base_url)
        browser.get(f"{base_url}/dashboard/settings")
        wait = WebDriverWait(browser, 10)
        wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, '[data-screen="ADM-13"]')))
        source = browser.page_source
        assert "الإعدادات والتدقيق الأمني" in source or "Settings & Audit Security" in source
        assert "رقم التواصل الرسمي" in source or "Official sales contact" in source
        assert "وضع الصيانة" in source or "Maintenance mode" in source
        assert "غير مسموح" in source or "Not allowed" in source
        assert "غير مفعّلة" in source or "Not enabled" in source
        assert "عامة" in source or "General" in source
        assert "sw-toggle" in source
        assert "name=\"lead_assignment_rule\"" in source
        assert "name=\"sales_phone\"" in source

    def test_staff_can_open_adm_14_audit(self, browser, base_url: str) -> None:
        _reset_to_guest(browser, base_url)
        _login_staff(browser, base_url)
        browser.get(f"{base_url}/dashboard/audit")
        wait = WebDriverWait(browser, 10)
        wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, '[data-screen="ADM-14"]')))
        source = browser.page_source
        assert "سجل النشاط" in source or "Activity Log" in source
        assert "السجل غير قابل للحذف" in source or "cannot be deleted" in source
        assert "name=\"actor_id\"" in source
        assert "جميع المستخدمين" in source or "All users" in source
        assert "method=\"POST\"" not in source or "تصدير السجلات" in source or "Export logs" in source

    def test_staff_can_switch_adm_01_to_english(self, browser, base_url: str) -> None:
        _reset_to_guest(browser, base_url)
        _login_staff(browser, base_url)
        wait = WebDriverWait(browser, 10)
        wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, '[data-screen="ADM-01"]')))
        assert browser.find_elements(By.CSS_SELECTOR, 'a[href*="/locale/en"]')
        browser.get(f"{base_url}/locale/en")
        browser.get(f"{base_url}/dashboard")
        wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, '[data-screen="ADM-01"]')))
        source = browser.page_source
        assert "Needs action now" in source or "Executive Dashboard" in source
        assert 'dir="ltr"' in source

