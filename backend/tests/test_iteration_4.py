"""
Iteration 4 backend/integration tests for the PHP storefront.
Covers:
 - Admin login with seeded credentials
 - SEO tab HTTP 200 + zero PHP errors
 - Hero card with seo-quick-publish + seo-publish-batch-today testids
 - Hero subtitle showing 'today X/Y' counter
 - Sidebar 'AI Auto-Blogger' link (adm-nav-seo) always present
 - Sidebar auto-scroll JS snippet present
 - All 12 admin tabs return HTTP 200
 - Public product/blog/homepage rendering
 - seo_ai_publish_one_random function existence and reference
"""
import os
import re
import requests
import pytest

BASE_URL = os.environ.get("REACT_APP_BACKEND_URL", "").rstrip("/")
ADMIN_EMAIL = "services@fivecodelabsoftware.com"
ADMIN_PASS = "Fivecode@2026!"

ADMIN_TABS = [
    "dashboard", "products", "orders", "company", "regions", "leads",
    "emails", "reviews", "templates", "gateways", "smtp", "seo",
]

PHP_ERR_RE = re.compile(
    r"(Parse error|Fatal error|Warning:|Notice:|Deprecated:|Uncaught)",
    re.IGNORECASE,
)


@pytest.fixture(scope="module")
def admin_session():
    s = requests.Session()
    r = s.post(
        f"{BASE_URL}/login.php",
        data={"email": ADMIN_EMAIL, "password": ADMIN_PASS},
        allow_redirects=False,
        timeout=30,
    )
    assert r.status_code in (302, 303), f"login expected redirect, got {r.status_code} body={r.text[:200]}"
    return s


# ---- Public site -----------------------------------------------------------

def test_homepage_renders_without_php_errors():
    r = requests.get(f"{BASE_URL}/", timeout=30)
    assert r.status_code == 200
    assert not PHP_ERR_RE.search(r.text), "PHP error on homepage"
    # JSON-LD checks (LocalBusiness + Brand)
    assert '"@type":"LocalBusiness"' in r.text or '"@type": "LocalBusiness"' in r.text
    assert '"@type":"Brand"' in r.text or '"@type": "Brand"' in r.text


def test_public_product_page_renders():
    slug = "microsoft-office-2024-professional-plus-windows"
    r = requests.get(f"{BASE_URL}/product.php?slug={slug}", timeout=30)
    assert r.status_code == 200, f"product page status {r.status_code}"
    assert not PHP_ERR_RE.search(r.text), "PHP error on product page"


def test_product_articles_section_absent_when_no_articles():
    """seo_ai_blog_log is empty so the per-product articles section should NOT render."""
    slug = "microsoft-office-2024-professional-plus-windows"
    r = requests.get(f"{BASE_URL}/product.php?slug={slug}", timeout=30)
    assert r.status_code == 200
    # When there are no rows, the wrapping <?php if ($productArticles): ?> evaluates false,
    # so the section testid must be absent.
    assert 'data-testid="product-articles-section"' not in r.text


def test_blog_post_12_renders():
    r = requests.get(f"{BASE_URL}/blog-post.php?id=12", timeout=30)
    assert r.status_code == 200
    assert not PHP_ERR_RE.search(r.text), "PHP error on blog-post"


# ---- Admin auth ------------------------------------------------------------

def test_admin_login_succeeds(admin_session):
    # If fixture didn't raise, login worked.
    r = admin_session.get(f"{BASE_URL}/admin.php?tab=dashboard", timeout=30)
    assert r.status_code == 200
    assert not PHP_ERR_RE.search(r.text), "PHP error on dashboard"


# ---- All 12 tabs -----------------------------------------------------------

@pytest.mark.parametrize("tab", ADMIN_TABS)
def test_admin_tab_loads_ok(admin_session, tab):
    r = admin_session.get(f"{BASE_URL}/admin.php?tab={tab}", timeout=45)
    assert r.status_code == 200, f"tab {tab} HTTP {r.status_code}"
    m = PHP_ERR_RE.search(r.text)
    assert not m, f"tab {tab} PHP error: {m.group(0) if m else ''}"
    # Sidebar 'AI Auto-Blogger' link must always be present on every admin page
    assert 'data-testid="adm-nav-seo"' in r.text, f"adm-nav-seo missing on tab={tab}"


# ---- SEO tab specifics -----------------------------------------------------

@pytest.fixture(scope="module")
def seo_html(admin_session):
    r = admin_session.get(f"{BASE_URL}/admin.php?tab=seo", timeout=45)
    assert r.status_code == 200
    return r.text


def test_seo_hero_has_quick_publish_button(seo_html):
    assert 'data-testid="seo-quick-publish"' in seo_html


def test_seo_hero_has_publish_batch_button(seo_html):
    assert 'data-testid="seo-publish-batch-today"' in seo_html


def test_seo_quick_publish_form_action(seo_html):
    """The purple button form must POST action=seo_quick_publish."""
    # find the seo-quick-publish testid then look for the action input nearby
    idx = seo_html.find('data-testid="seo-quick-publish"')
    assert idx > 0
    # search up to ~400 chars before the button for hidden action input
    window = seo_html[max(0, idx - 600): idx + 200]
    assert 'name="action" value="seo_quick_publish"' in window


def test_seo_publish_batch_form_action(seo_html):
    idx = seo_html.find('data-testid="seo-publish-batch-today"')
    assert idx > 0
    window = seo_html[max(0, idx - 700): idx + 200]
    assert 'name="action" value="seo_publish_batch"' in window


def test_seo_hero_today_counter_format(seo_html):
    """Hero subtitle must show 'today X/Y' with Y = per_market_cap*4 = 24 by default."""
    # The rendered HTML contains: today <strong>0</strong>/<strong>24</strong>
    # Allow flexible whitespace.
    m = re.search(r"today\s*<strong>\s*(\d+)\s*/\s*(\d+)\s*</strong>", seo_html)
    assert m, "today X/Y counter not found in SEO hero"
    today_count = int(m.group(1))
    y = int(m.group(2))
    assert y == 24, f"expected per-market_cap*4 == 24, got {y}"
    assert today_count >= 0


def test_seo_hero_market_list_text(seo_html):
    assert "daily article + indexing across" in seo_html
    # All four markets mentioned
    for m in ("US", "UK", "AU", "CA"):
        assert f"{m}" in seo_html


# ---- Sidebar auto-scroll JS ------------------------------------------------

def test_admin_shell_has_scrollintoview_js():
    with open("/app/php-version/includes/admin-shell.php", "r") as f:
        content = f.read()
    assert "scrollIntoView" in content, "scrollIntoView JS missing from admin-shell"
    assert ".adm-sidebar" in content


# ---- seo_ai.php has the new function --------------------------------------

def test_seo_ai_publish_one_random_function_exists():
    with open("/app/php-version/includes/seo_ai.php", "r") as f:
        content = f.read()
    assert "function seo_ai_publish_one_random" in content


def test_seo_quick_publish_handler_calls_one_random():
    with open("/app/php-version/admin.php", "r") as f:
        content = f.read()
    # handler exists
    assert "'seo_quick_publish'" in content
    # references the new function
    assert "seo_ai_publish_one_random(" in content


def test_seo_publish_batch_handler_calls_daily_blog():
    with open("/app/php-version/admin.php", "r") as f:
        content = f.read()
    assert "'seo_publish_batch'" in content
    # Inside that handler block, ensure seo_ai_run_daily_blog is invoked
    idx = content.find("'seo_publish_batch'")
    window = content[idx: idx + 800]
    assert "seo_ai_run_daily_blog(" in window
