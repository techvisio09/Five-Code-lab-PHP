"""
Iteration 5 backend tests
Focus: brand pages, admin feed pagination, sitemap brand entries,
budget alert removed, robots.txt, all admin tabs.
"""
import os
import re
import subprocess
import pytest
import requests

BASE_URL = os.environ.get("REACT_APP_BACKEND_URL", "https://side-showcase.preview.emergentagent.com").rstrip("/")
LOCAL = "http://localhost:3000"
ADMIN_EMAIL = "services@fivecodelabsoftware.com"
ADMIN_PASSWORD = "Fivecode@2026!"


def mysql_exec(sql: str) -> str:
    """Run a MySQL statement against ucode_store."""
    p = subprocess.run(
        ["mysql", "-uroot", "ucode_store", "-Nse", sql],
        capture_output=True, text=True, timeout=15,
    )
    return p.stdout.strip()


# ---------- fixtures ----------
@pytest.fixture(scope="session")
def admin_session():
    s = requests.Session()
    s.headers.update({"User-Agent": "iter5-test"})
    # Login
    r = s.post(f"{BASE_URL}/login.php",
               data={"email": ADMIN_EMAIL, "password": ADMIN_PASSWORD},
               allow_redirects=False, timeout=20)
    assert r.status_code in (200, 302, 303), f"login HTTP {r.status_code}"
    return s


@pytest.fixture(scope="session")
def seeded_blog_rows():
    """Insert 30 test rows into seo_ai_blog_log; clean up after session."""
    # Use real product slugs so brand JOIN works
    slugs = mysql_exec(
        "SELECT slug,name,brand FROM products WHERE brand IN ('Microsoft','Bitdefender','McAfee') LIMIT 30"
    ).splitlines()
    rows = []
    regions = ["US", "UK", "AU", "CA"]
    for i, line in enumerate(slugs[:30]):
        parts = line.split("\t")
        if len(parts) < 3:
            continue
        slug, name, _brand = parts
        region = regions[i % 4]
        blog_id = f"TEST_iter5_{i}"
        title = f"TEST_iter5 Guide #{i} for {name[:40]}"
        rows.append((blog_id, slug, name.replace("'", ""), title, region))

    # Pad to 30 by re-using product[0]
    while len(rows) < 30 and slugs:
        i = len(rows)
        parts = slugs[0].split("\t")
        slug, name = parts[0], parts[1].replace("'", "")
        rows.append((f"TEST_iter5_{i}", slug, name, f"TEST_iter5 Extra #{i}", regions[i % 4]))

    for (bid, slug, name, title, region) in rows:
        sql = (
            "INSERT INTO seo_ai_blog_log (blog_id,product_slug,product_name,title,region,word_count) "
            f"VALUES ('{bid}','{slug}','{name}','{title}','{region}',900)"
        )
        mysql_exec(sql)

    # Also create a matching minimal row in blog_posts for blog-post.php?id=TEST_iter5_0 link
    # (not strictly required for pagination test)
    yield len(rows)

    # Cleanup
    mysql_exec("DELETE FROM seo_ai_blog_log WHERE blog_id LIKE 'TEST_iter5_%'")


# ---------- robots & sitemap ----------
class TestPublicSEO:
    def test_homepage_200_and_jsonld(self):
        r = requests.get(f"{BASE_URL}/", timeout=15)
        assert r.status_code == 200
        body = r.text
        assert "Fatal error" not in body and "Parse error" not in body
        assert "LocalBusiness" in body
        assert '"@type": "Brand"' in body or '"@type":"Brand"' in body

    def test_sitemap_contains_brand_entries(self):
        r = requests.get(f"{BASE_URL}/sitemap.xml", timeout=15)
        assert r.status_code == 200
        body = r.text
        # Count brand entries — KNOWN BUG: /sitemap.xml is routed to sitemap-xml.php
        # (router.php line 8) which does NOT emit brand URLs. seo_ai.php DOES emit
        # them but writes to a different file that isn't served.
        brand_urls = re.findall(r"/brand\.php\?slug=[a-z0-9\-]+", body)
        assert len(brand_urls) >= 3, (
            f"BUG: expected >=3 brand URLs in /sitemap.xml, found {len(brand_urls)}. "
            "Router serves sitemap-xml.php which lacks brand entries."
        )
        slugs = {u.split("=")[-1] for u in brand_urls}
        for needed in ("microsoft", "bitdefender", "mcafee"):
            assert needed in slugs

    def test_sitemap_uses_dynamic_host(self):
        """site_url() should output the actual HTTP host of the request, not a hardcoded preview URL."""
        r = requests.get(f"{BASE_URL}/sitemap.xml", timeout=15)
        body = r.text
        # Must not contain a hardcoded different preview/production URL
        # The host should be derived from the actual request (matches BASE_URL host or
        # the cluster-internal host that emergentcf maps to it)
        assert "<loc>https://" in body
        # Anti-hardcode check: no production gosoftwarebuy.com main domain
        assert "<loc>https://gosoftwarebuy.com/shop.php" not in body

    def test_robots_dynamic_via_localhost(self):
        try:
            r = requests.get(f"{LOCAL}/robots.txt", timeout=10)
        except Exception:
            # fallback - try external
            r = requests.get(f"{BASE_URL}/robots.txt", timeout=15)
        assert r.status_code == 200
        body = r.text
        ua_count = len(re.findall(r"(?mi)^User-agent:", body))
        # Must include the listed AI crawlers
        for bot in ("GPTBot", "ClaudeBot", "PerplexityBot", "Google-Extended", "Applebot"):
            assert bot in body, f"robots.txt missing {bot}"
        assert ua_count >= 40, f"expected >=40 user-agent directives, found {ua_count}"


# ---------- brand pages ----------
class TestBrandPages:
    @pytest.mark.parametrize("slug,h1_contains,product_count", [
        ("microsoft", "Microsoft Software", 30),
        ("bitdefender", "Bitdefender Software", 6),
        ("mcafee", "McAfee Software", 1),
    ])
    def test_brand_page_200(self, slug, h1_contains, product_count):
        r = requests.get(f"{BASE_URL}/brand.php?slug={slug}", timeout=15)
        assert r.status_code == 200
        body = r.text
        assert "Fatal error" not in body and "Parse error" not in body
        assert h1_contains in body, f"H1 '{h1_contains}' not found"
        assert 'data-testid="brand-hero"' in body
        # product count rendered in stat
        assert f'data-testid="brand-product-count">{product_count}<' in body, \
            f"product count {product_count} missing for {slug}"
        # Brand JSON-LD
        assert '"@type": "Brand"' in body or '"@type":"Brand"' in body

    def test_brand_nonexistent_404(self):
        r = requests.get(f"{BASE_URL}/brand.php?slug=nonexistent-zzz", timeout=15)
        assert r.status_code == 404

    def test_brand_articles_empty_state(self):
        """With seo_ai_blog_log empty, brand-articles-section-empty must render."""
        # Ensure empty (no test rows seeded yet — this test runs before seeded_blog_rows)
        # We check the Microsoft page specifically.
        count = mysql_exec("SELECT COUNT(*) FROM seo_ai_blog_log")
        if count != "0":
            pytest.skip("seo_ai_blog_log not empty; skipping empty-state test")
        r = requests.get(f"{BASE_URL}/brand.php?slug=microsoft", timeout=15)
        assert 'data-testid="brand-articles-section-empty"' in r.text

    def test_brand_articles_populated(self, seeded_blog_rows):
        """With 30 seeded rows across Microsoft/Bitdefender/McAfee, articles section renders."""
        r = requests.get(f"{BASE_URL}/brand.php?slug=microsoft", timeout=15)
        body = r.text
        assert 'data-testid="brand-articles-section"' in body
        assert 'data-testid="brand-article-TEST_iter5_' in body
        # at least one article anchor
        m = re.findall(r'data-testid="brand-article-(TEST_iter5_[0-9]+)"\s+', body) or \
            re.findall(r'href="blog-post\.php\?id=TEST_iter5_[0-9]+"', body)
        assert len(m) >= 1, "no brand article cards rendered"


# ---------- product page brand breadcrumb ----------
class TestProductBrandLink:
    def test_product_has_brand_breadcrumb_link(self):
        url = f"{BASE_URL}/product.php?slug=microsoft-office-2024-professional-plus-windows"
        r = requests.get(url, timeout=15)
        assert r.status_code == 200
        body = r.text
        assert 'data-testid="product-brand-link"' in body
        assert 'brand.php?slug=microsoft' in body


# ---------- admin: login, all tabs, no budget alert, feed pagination ----------
class TestAdmin:
    def test_admin_login_works(self, admin_session):
        r = admin_session.get(f"{BASE_URL}/admin.php?tab=dashboard", timeout=20)
        assert r.status_code == 200
        assert "Fatal error" not in r.text and "Parse error" not in r.text
        assert "login" not in r.url.lower() or "admin.php" in r.url

    @pytest.mark.parametrize("tab", [
        "dashboard", "products", "orders", "company", "regions",
        "leads", "emails", "reviews", "templates", "gateways", "smtp", "seo",
    ])
    def test_all_admin_tabs(self, admin_session, tab):
        r = admin_session.get(f"{BASE_URL}/admin.php?tab={tab}", timeout=30)
        assert r.status_code == 200
        assert "Fatal error" not in r.text and "Parse error" not in r.text, \
            f"PHP error in tab {tab}"
        # adm-nav-seo must always be present in admin shell
        assert 'data-testid="adm-nav-seo"' in r.text

    def test_seo_tab_subtext(self, admin_session):
        r = admin_session.get(f"{BASE_URL}/admin.php?tab=seo", timeout=30)
        assert r.status_code == 200
        body = r.text
        assert "All AI-published blog posts" in body
        assert "newest first" in body
        assert "click any to view live" in body

    def test_no_budget_alert(self, admin_session):
        """Verify no budget alert testids in seo tab or shell."""
        r = admin_session.get(f"{BASE_URL}/admin.php?tab=seo", timeout=30)
        body = r.text
        assert 'data-testid="seo-budget-alert"' not in body
        assert 'data-testid="adm-budget-toast"' not in body

    def test_feed_pagination_page1(self, admin_session, seeded_blog_rows):
        r = admin_session.get(f"{BASE_URL}/admin.php?tab=seo", timeout=30)
        assert r.status_code == 200
        body = r.text
        # pagination block present (we have 30 > 25)
        assert 'data-testid="feed-pagination"' in body, "feed-pagination block missing"
        assert 'data-testid="feed-page-1"' in body
        assert 'data-testid="feed-page-2"' in body
        assert 'data-testid="feed-page-prev"' in body
        assert 'data-testid="feed-page-next"' in body
        # 25 feed rows on page 1
        rows = re.findall(r'data-testid="seo-feed-row-\d+"', body)
        assert len(rows) == 25, f"expected 25 rows on page 1, got {len(rows)}"

    def test_feed_pagination_page2(self, admin_session, seeded_blog_rows):
        r = admin_session.get(f"{BASE_URL}/admin.php?tab=seo&feed_page=2", timeout=30)
        assert r.status_code == 200
        body = r.text
        rows = re.findall(r'data-testid="seo-feed-row-\d+"', body)
        # 30 total - 25 page1 = 5 on page2 (could be more if other rows exist)
        assert len(rows) >= 5, f"expected >=5 rows on page 2, got {len(rows)}"
        assert 'data-testid="feed-pagination"' in body


# ---------- sanity: blog-post page renders for seeded row's blog_id ----------
class TestBlogPostLink:
    def test_blog_post_for_seeded_id_or_existing(self):
        """Confirm blog-post.php returns 200 for an existing id."""
        existing = mysql_exec("SELECT id FROM blog_posts LIMIT 1")
        if not existing:
            pytest.skip("no blog_posts rows")
        r = requests.get(f"{BASE_URL}/blog-post.php?id={existing}", timeout=15)
        assert r.status_code == 200
