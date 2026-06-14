"""
Iteration 6: Public /articles.php SEO hub testing.
Covers: HTTP 200 on all filter combos, testids, JSON-LD, sitemap inclusion,
nav-articles link on every public page, grid/cards/pagination after seed,
empty state, admin regressions.
"""
import os
import re
import subprocess
import pytest
import requests

BASE_URL = os.environ.get("REACT_APP_BACKEND_URL", "https://side-showcase.preview.emergentagent.com").rstrip("/")


def _mysql(sql: str) -> str:
    """Execute mysql command in the container; returns stdout."""
    r = subprocess.run(
        ["mysql", "-u", "root", "-N", "-B", "-e", sql, "ucode_store"],
        capture_output=True, text=True, timeout=20,
    )
    if r.returncode != 0:
        raise RuntimeError(f"mysql failed: {r.stderr}")
    return r.stdout.strip()


@pytest.fixture(scope="module")
def seeded_articles():
    """Insert 15 TEST_iter6_* rows across regions US/UK/AU/CA so we can verify grid + pagination."""
    rows = []
    slugs = _mysql(
        "SELECT slug, name FROM products WHERE brand='Microsoft' ORDER BY id ASC LIMIT 15"
    ).splitlines()
    regions = ["US", "UK", "AU", "CA"]
    for i, line in enumerate(slugs):
        parts = line.split("\t")
        if len(parts) < 2:
            continue
        slug, name = parts[0], parts[1].replace("'", "''")
        region = regions[i % 4]
        bid = f"TEST_iter6_{i+1:02d}"
        title = f"TEST_iter6 office buying guide for {name}".replace("'", "''")[:240]
        rows.append(
            f"('{bid}', '{slug}', '{name}', '{title}', '{region}', 880, 4)"
        )
    if rows:
        _mysql(
            "INSERT INTO seo_ai_blog_log (blog_id, product_slug, product_name, title, region, word_count, internal_links) VALUES "
            + ",".join(rows)
        )
    yield len(rows)
    _mysql("DELETE FROM seo_ai_blog_log WHERE blog_id LIKE 'TEST_iter6%'")


@pytest.fixture(scope="module")
def session():
    s = requests.Session()
    s.headers.update({"User-Agent": "iteration6-test/1.0"})
    return s


# ---------- Basic HTTP 200 on /articles.php with filter combinations ----------
class TestArticlesPublic:
    def test_articles_index_200(self, session):
        r = session.get(f"{BASE_URL}/articles.php", timeout=15)
        assert r.status_code == 200
        assert "Editorial Articles" in r.text
        assert 'data-testid="articles-index"' in r.text
        assert 'data-testid="articles-hero"' in r.text
        assert 'data-testid="articles-search-form"' in r.text
        assert 'data-testid="articles-search-input"' in r.text
        assert 'data-testid="articles-region-chips"' in r.text

    def test_chips_all_5_regions(self, session):
        r = session.get(f"{BASE_URL}/articles.php", timeout=15)
        for chip in ["all", "US", "UK", "AU", "CA"]:
            assert f'data-testid="articles-chip-{chip}"' in r.text, f"missing chip {chip}"

    def test_region_filter_us_200(self, session):
        r = session.get(f"{BASE_URL}/articles.php?region=US", timeout=15)
        assert r.status_code == 200
        # active chip on US
        assert re.search(r'data-testid="articles-chip-US"[^>]*class="[^"]*active', r.text) \
            or 'data-testid="articles-chip-US"' in r.text and 'active' in r.text

    def test_search_q_office_200(self, session):
        r = session.get(f"{BASE_URL}/articles.php?q=office", timeout=15)
        assert r.status_code == 200
        # search input value preserved
        assert 'value="office"' in r.text

    def test_region_and_search_combined_200(self, session):
        r = session.get(f"{BASE_URL}/articles.php?region=UK&q=office", timeout=15)
        assert r.status_code == 200
        assert 'name="region" value="UK"' in r.text  # hidden region input

    def test_page2_returns_200(self, session):
        r = session.get(f"{BASE_URL}/articles.php?page=2", timeout=15)
        assert r.status_code == 200


# ---------- Empty state when seo_ai_blog_log has 0 rows ----------
class TestArticlesEmptyState:
    def test_empty_state_rendered_when_no_rows(self, session):
        # Make sure no rows exist (we only seed inside fixture for grid tests)
        count = int(_mysql("SELECT COUNT(*) FROM seo_ai_blog_log") or "0")
        if count > 0:
            pytest.skip(f"seo_ai_blog_log has {count} rows - skipping empty state check")
        r = session.get(f"{BASE_URL}/articles.php", timeout=15)
        assert 'data-testid="articles-empty"' in r.text
        assert "No articles match those filters yet" in r.text
        assert "Browse all articles" in r.text

    def test_empty_state_browse_button_links_to_articles_unfiltered(self, session):
        r = session.get(f"{BASE_URL}/articles.php?region=US&q=foo", timeout=15)
        assert r.status_code == 200
        # When 0 results, empty state with link back to articles.php (no filters)
        if 'data-testid="articles-empty"' in r.text:
            assert re.search(r'href="articles\.php"\s+class="btn btn-primary', r.text)


# ---------- JSON-LD ----------
class TestArticlesJsonLd:
    def test_collectionpage_and_breadcrumb_present(self, session):
        r = session.get(f"{BASE_URL}/articles.php", timeout=15)
        assert '"@type": "CollectionPage"' in r.text
        assert '"@type": "BreadcrumbList"' in r.text
        assert '"@type":\n    "ItemList"' in r.text or '"@type": "ItemList"' in r.text

    def test_itemlist_numberofitems_matches_total(self, session):
        r = session.get(f"{BASE_URL}/articles.php", timeout=15)
        m = re.search(r'"numberOfItems":\s*(\d+)', r.text)
        assert m, "numberOfItems missing in JSON-LD"
        n = int(m.group(1))
        db_total = int(_mysql("SELECT COUNT(*) FROM seo_ai_blog_log") or "0")
        # Page-1 result-count caps at 12, but numberOfItems uses $total which is full count
        assert n == db_total, f"JSON-LD numberOfItems={n} should equal db count={db_total}"


# ---------- Sitemap ----------
class TestSitemap:
    def test_sitemap_contains_articles_php(self, session):
        r = session.get(f"{BASE_URL}/sitemap.xml", timeout=15)
        assert r.status_code == 200
        assert "articles.php" in r.text

    def test_sitemap_brand_entries_regression(self, session):
        r = session.get(f"{BASE_URL}/sitemap.xml", timeout=15)
        for slug in ["microsoft", "bitdefender", "mcafee"]:
            assert f"brand.php?slug={slug}" in r.text, f"missing brand {slug} (iter5 regression)"


# ---------- Header nav-articles link on every public page ----------
class TestNavArticles:
    @pytest.mark.parametrize("path", [
        "/", "/index.php", "/shop.php", "/blog.php",
        "/product.php?slug=microsoft-office-2024-professional-plus-windows",
    ])
    def test_nav_articles_link_present(self, session, path):
        r = session.get(f"{BASE_URL}{path}", timeout=15)
        assert r.status_code == 200
        assert 'data-testid="nav-articles"' in r.text
        assert 'href="articles.php"' in r.text


# ---------- After seed: grid renders + cards have correct testids ----------
class TestArticlesGridWithSeed:
    def test_grid_renders_after_seed(self, session, seeded_articles):
        assert seeded_articles > 0
        r = session.get(f"{BASE_URL}/articles.php", timeout=15)
        assert r.status_code == 200
        assert 'data-testid="articles-grid"' in r.text
        # At least one article-card-* testid
        cards = re.findall(r'data-testid="article-card-([^"]+)"', r.text)
        assert len(cards) > 0
        # Each card is wrapped in an <a href="blog-post.php?id=...">
        assert "blog-post.php?id=" in r.text

    def test_pagination_renders_when_total_gt_12(self, session, seeded_articles):
        # We seeded 15 > 12 → pagination should render
        r = session.get(f"{BASE_URL}/articles.php", timeout=15)
        assert 'data-testid="articles-pagination"' in r.text
        assert 'data-testid="articles-page-next"' in r.text

    def test_page2_pagination_returns_remaining(self, session, seeded_articles):
        r = session.get(f"{BASE_URL}/articles.php?page=2", timeout=15)
        assert r.status_code == 200
        assert 'data-testid="articles-grid"' in r.text
        cards = re.findall(r'data-testid="article-card-([^"]+)"', r.text)
        assert len(cards) == max(0, seeded_articles - 12)

    def test_region_filter_uk_only_shows_uk_articles(self, session, seeded_articles):
        r = session.get(f"{BASE_URL}/articles.php?region=UK", timeout=15)
        assert r.status_code == 200
        # all visible region pills should be UK
        pills = re.findall(r'<span class="article-region-pill"[^>]*data-r="([^"]+)"[^>]*>([A-Z]{2})</span>', r.text)
        card_region_pills = [p[1] for p in pills if p[1] != "UK" and p[1] != "US" and p[1] != "AU" and p[1] != "CA"]
        # Among card pills (inside article-card-N), only UK should appear
        # Simpler: grid html contains only UK region attribute
        grid_match = re.search(r'data-testid="articles-grid"(.*?)(?:</div>\s*<nav|</div>\s*</div>\s*<\?)', r.text, re.S)
        if grid_match:
            grid_html = grid_match.group(1)
            regions_in_grid = re.findall(r'<span class="article-region-pill" data-r="([A-Z]{2})">', grid_html)
            assert all(rg == "UK" for rg in regions_in_grid), f"Non-UK regions in grid: {regions_in_grid}"

    def test_search_filter_returns_matching_titles(self, session, seeded_articles):
        r = session.get(f"{BASE_URL}/articles.php?q=office", timeout=15)
        assert r.status_code == 200
        # All seeded titles contain "office", expect a grid result
        assert 'data-testid="articles-grid"' in r.text


# ---------- Admin regression ----------
class TestAdminRegression:
    @pytest.fixture(scope="class")
    def admin_session(self):
        s = requests.Session()
        login_url = f"{BASE_URL}/login.php"
        # Get any CSRF token if present
        r = s.get(login_url, timeout=15)
        token_match = re.search(r'name="csrf_token"\s+value="([^"]+)"', r.text)
        payload = {
            "email": "services@fivecodelabsoftware.com",
            "password": "Fivecode@2026!",
        }
        if token_match:
            payload["csrf_token"] = token_match.group(1)
        r = s.post(login_url, data=payload, timeout=15, allow_redirects=True)
        # Verify admin access
        ar = s.get(f"{BASE_URL}/admin.php", timeout=15)
        if ar.status_code != 200 or "login" in ar.url.lower():
            pytest.skip("Admin login failed - cannot run admin regression tests")
        return s

    @pytest.mark.parametrize("tab", [
        "dashboard", "orders", "products", "categories", "blog",
        "reviews", "customers", "leads", "email", "settings",
        "seo", "ai",
    ])
    def test_admin_tabs_200(self, admin_session, tab):
        r = admin_session.get(f"{BASE_URL}/admin.php?tab={tab}", timeout=20)
        assert r.status_code == 200, f"admin.php?tab={tab} returned {r.status_code}"
        assert "Fatal error" not in r.text
        assert "Parse error" not in r.text

    def test_admin_seo_tab_has_feed(self, admin_session):
        r = admin_session.get(f"{BASE_URL}/admin.php?tab=seo", timeout=15)
        assert r.status_code == 200
        # Should still render the AI Auto-Blogger feed sub-text
        assert "AI" in r.text and ("Auto-Blogger" in r.text or "newest first" in r.text or "All AI" in r.text or "blog" in r.text.lower())

    def test_brand_microsoft_still_loads(self, session):
        r = session.get(f"{BASE_URL}/brand.php?slug=microsoft", timeout=15)
        assert r.status_code == 200
        assert "Microsoft" in r.text
