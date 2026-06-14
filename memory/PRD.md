# Fivecodelab Software Store — Product Requirements Document

## Original Problem Statement
Rebrand the existing storefront from "Maventech Software" to **Fivecodelab Software** with:
1. Company name: **Fivecodelab Software**
2. Address: **12266 Heritage Dr, Moreno Valley, CA 92557**
3. Email: **services@fivecodelabsoftware.com**
4. Webmail link: **https://fivecodelabsoftware.com/webmail**
5. Google Map auto-pointed at the new address
6. Theme overhaul to a strict **PayPal-clone** look (white surfaces, navy `#003087` / blue `#0070BA` headings, signature **yellow `#FFC439`** CTAs)
7. Reworked hero / sections for a PayPal feel
8. Working admin login credentials

## Tech Stack
- PHP 8.2 monolith served via built-in server on port 3000 (supervisor `frontend` runs `/app/php-version/start.sh`)
- MariaDB on local socket (`ucode_store`)
- Bootstrap 5 + custom `style.css`
- Optional: Emergent LLM key (AI chat), Stripe (payments), Resend (email)

## What's Been Implemented (2026-02-14)
- **Branding** — `config.php` constants, DB `settings` table, all hardcoded "Maventech" strings replaced site-wide; promo code `MAVEN20` → `FIVE20`.
- **Custom Fivecodelab brand assets**
  - Mark SVG (`assets/images/fivecodelab-logo.svg`) — used in navbar + footer
  - Wordmark SVG (`assets/images/fivecodelab-wordmark.svg` + `brand/fivecodelab-wordmark.svg`) — used in invoices/PDF receipts + email templates
  - OG share SVG (`assets/images/fivecodelab-og.svg`, 1200×630) — wired into `og:image` meta
  - PWA web manifest (`assets/manifest.webmanifest`) — shortcuts for Shop, Office 2024, Windows 11, Antivirus, Contact
- **Hero Orbit** — 6 floating Office satellite icons + 3D flip-and-fade central spotlight (3.4s cycle)
- **PayPal-style theme** + **comprehensive Dark Mode** (every word legible)
- **Toll-free number visibility** — navy-on-yellow navbar pill, weight 700 (not 800); white-with-yellow-icon trust bar phone
- **Elegant footer redesign** — `.footer-elegant` layer on top of `.footer-dark`:
  - Refined newsletter band with INSIDER DEALS eyebrow + 81%-off pill + yellow Join CTA + trust strip
  - Brand column with icon-pill contact list (phone, email, webmail, address, hours)
  - Polished red Google Maps button with eyebrow + bold label + arrow
  - Yellow underline accents under each column heading, '›' bullet hover on links
  - Social circles with yellow hover, Schema.org `WPFooter` + nested `Organization`/`PostalAddress` microdata
- **DB content sweep** — every long-form content table (`blog_posts`, `pages`, `email_templates`, `email_template_versions`, `email_outbox`, `faqs`, `lead_notes`, `products`, `testimonials`, `customer_reviews`) now has zero "Maventech" mentions. Email addresses, statement names, merchant names all rebranded.
- **`database.sql` refresh** — fresh-pod seed now comes up branded as Fivecodelab Software, with the new Moreno Valley address and admin email.
- **`llms.txt` + `robots.txt` rebrand** — AI crawler allow-list + structured site index now reference Fivecodelab.
- **SEO + AI-Search visibility pass** (header.php + index.php + product.php):
  - **JSON-LD**:
    - Homepage (17 schema types): `Organization`, `LocalBusiness`, `WebSite`, `FAQPage`, `BreadcrumbList`, `ItemList` of best-selling Products with full `Offer` + `Brand` + `Seller`
    - Product pages (27 schema types): `Product` with `@id`, `productID`, `mpn`, `sku`, `brand`, `manufacturer`, `category`, `additionalProperty[5]` (delivery method, license type, platform, etc.), `audience`, `aggregateRating`, **inline `review[]` snippets pulled from `customer_reviews`**, `Offer` with `PriceSpecification`/VAT flag, `shippingDetails` for 6 countries, `MerchantReturnPolicy` with `applicableCountry`, `BreadcrumbList`, `FAQPage`
  - **Raster OG assets** — `fivecodelab-og.png` (1200×630, generated via `rsvg-convert`), `favicon-32.png`, `apple-touch-icon.png` (180×180), `fivecodelab-logo-192.png`, `fivecodelab-logo-512.png` (manifest icons). Wired as `<link rel="icon">`, `apple-touch-icon`, and richer `og:image:type/width/height/alt` + `twitter:image:alt`/`twitter:site/creator`.
  - **hreflang** — `en-US` + `en` + `x-default` rel=alternate links emitted on every page (pointing at the canonical URL today; ready to expand when regional sub-domains go live).
  - **Meta upgrades**: `theme-color` (light + dark), `color-scheme`, `apple-mobile-web-app-title`, geo (`geo.region`/`placename`/`position`/`ICBM` for Moreno Valley CA), `content-language`, `author`/`publisher`/`copyright`, `referrer`, `format-detection`, `og:locale`.
  - **Performance**: `preconnect` + `dns-prefetch` to jsdelivr + Google Fonts CDNs.
  - **PWA manifest** at `/assets/manifest.webmanifest` (now serves raster + SVG icons + 5 shortcuts).
  - **Accessibility**: `role="contentinfo"` on footer, ARIA labels on social/map/legal nav, `visually-hidden` label on newsletter input.
- **Admin credentials**: `services@fivecodelabsoftware.com` / `Fivecode@2026!`

## Latest enhancements (2026-06-14 night)

### Comprehensive admin "All AI Posts" view with pagination
- `/admin.php?tab=seo` Live Feed now shows **every AI-published post** in a paginated table (25 per page), newest first, with full pagination controls (prev/next + numbered page chips with windowed `…` collapsing).
- Heading rewritten to "All AI-published blog posts · newest first · click any to view live".
- Pagination respects the region filter — `?tab=seo&feed_region=UK&feed_page=2` shows page 2 of UK-only posts.
- Range indicator at the bottom: "Showing 26-35 of 35 AI-published posts" with full filter context.

### Public Brand Profile pages
- New `/brand.php?slug={microsoft|bitdefender|mcafee}` — auto-resolves slug to canonical brand name (case-insensitive, hyphen-to-space match against `products.brand`). 404 for unknown brands.
- Hero shows brand name + product count + article count + 4-market badge.
- **Articles section** lists every AI-published guide whose product belongs to that brand (up to 50, with thumbnail, region pill, date, read-time, product caption, click-to-view).
- Empty-state placeholder (`brand-articles-section-empty`) when no AI articles yet.
- Products grid below using the existing `render_product_card()` helper.
- Embedded `Brand` JSON-LD + `BreadcrumbList` for AI Knowledge Panel eligibility.
- Product breadcrumb on every `/product.php` page now includes a clickable brand link → `brand.php?slug=...` (testid `product-brand-link`).
- Brand pages emitted in `/sitemap.xml` (priority 0.8) — both `sitemap-xml.php` (router-served) and `seo_ai.php::seo_generate_sitemap()` (cron-generated) include them.

### Budget alert removed (user request)
- Removed the red "LLM budget exceeded" card from the SEO tab.
- Removed the global red toast from every admin page.
- `seo_llm_complete()` still detects HTTP-400 "budget" responses but exits the retry loop silently (no `setting_set`, no UI signalling).

## Earlier 2026-06-14 enhancements

### Full automation · 6 posts × 4 markets = 24 posts/day
- `seo_ai_run_daily_blog()` rewritten as **per-market round-robin** publisher. Default cap: 6 posts per market per calendar day (configurable via `seo_ai_per_market_post_cap` setting, range 1-10). Hard guarantee that every day's 24 posts cover 24 different products (SQL-level NOT IN + duplicate-guard before insert).
- Every published post auto-submits its URL to **IndexNow** the moment it's saved (fans out to Bing/Yandex/Naver/Seznam). `indexnow_submitted_at` timestamp persisted to `seo_ai_blog_log`.
- Internal backlink counter — `seo_ai_publish_blog_for_product()` counts `<a href="(product|category|blog-post).php">` links in each generated article and stores the count in `seo_ai_blog_log.internal_links`. Average shows in the live feed header.
- LLM call retry with exponential backoff (3 attempts × 0.8s base) so transient rate-limits don't turn into missing posts.

### CRITICAL LLM-budget-exceeded alert (production safeguard)
- `seo_llm_complete()` now detects HTTP-400 "budget exceeded" errors and persists a flag in `settings.seo_ai_budget_alert` so the SEO Centre AND every other admin page show a clear red alert + global toast directing the operator to top-up the Universal Key.
- Alert clears automatically the moment the next successful LLM call goes through.

### Live Feed per-country filter chips
- 5 chips: **All markets · US · UK · AU · CA** with per-country totals and "today X/cap" counters live next to each chip.
- Clicking a chip navigates to `?tab=seo&feed_region=XX` and filters the feed server-side.
- Each feed row now shows: AI · PUBLISHED pill, region pill, **INDEXED** or **PENDING** badge, **N LINKS** badge, date, read-time, product link, blue open button.
- Image bug fixed: `onerror` handler replaces broken thumbnails with the purple robot icon so the row layout never collapses.

### Anti-Scraper / AI-Content-Clone Watchdog
- `seo_scrape_run()` asks GPT-4.1 for evidence of third-party clones of our 10 most recent AI-written articles. Findings → `seo_ai_scrape_alerts` table with status flag for DMCA workflow.
- Weekly cron registered (`cron/scrape-weekly.php` + 10-minute warmup in `start.sh`).
- Admin card surfaces total + open alerts + "Scan for clones now" button + recent-alerts collapsible table.

### Production-cutover hardening
- `SITE_URL` constant no longer hardcoded — defaults to empty, allowing `site_url()` to auto-detect from `$_SERVER['HTTP_HOST']`.
- Belt-and-braces: `site_url()` now also persists the live host into `settings.site_url` on every web request, so even CLI cron jobs writing sitemaps know the public domain.
- Resolution order: env var `SITE_URL` → `SITE_URL` constant → `$_SERVER['HTTP_HOST']` → `settings.site_url` → `http://localhost`.

## Earlier 2026-06-14 enhancements
- **Webmail link removed from footer** (Brand & Support columns). Top trust-bar Webmail link kept.
- **`merchant-feed.xml` refreshed** — 37 products now exported with rich Google Merchant + Bing Shopping fields: `g:mobile_link`, `g:mpn`, `g:product_type`, `g:item_group_id`, `g:product_highlight` (×4), `g:additional_image_link`, multi-country `g:shipping` (US/GB/CA/AU), `g:max_handling_time`, `g:min_handling_time`, `g:tax`, 5× `g:custom_label_*` (Digital, brand, platform, sale state, category), `g:product_review_count` + `g:product_review_average`, `g:sale_price` + `g:sale_price_effective_date`.
- **Per-post `Article` JSON-LD** on `blog-post.php` — drives Google Top Stories eligibility + clean AI summarisation: `mainEntityOfPage`, `headline`, `wordCount` (auto-computed), `timeRequired` (auto-computed reading minutes), `articleSection`, `keywords`, `inLanguage`, `isAccessibleForFree`, `author`/`publisher` Organization with 512×512 `ImageObject` logo, `BreadcrumbList`, plus `itemscope` microdata on the `<article>` element.
- **Image alt-text audit completed** — every product card already uses SEO-rich `product_img_alt()` (name + platform + license type + discount + brand). Enriched: blog cards (`index.php` + `blog.php`) now have descriptive titles + `loading="lazy"` + `decoding="async"` + explicit width/height for CLS, OS badge on product page uses "Microsoft Windows compatible" / "Apple macOS compatible" alts, blog hero image has descriptive alt + `fetchpriority="high"`. Only remaining empty `alt=""` is the decorative OS chip icon next to its visible text label (correct per ARIA).
- **Review URL bug fix** — `/review.php` now parses both proper (`?t=TOK&rating=N`) and legacy (`?t=TOK?rating=N`) URLs. DB swept for stale templates; source-code audit shows zero remaining bad patterns.
- **Admin "Email Link Sanity Test" tool** — collapsible card at the top of the Reviews tab in `/admin.php` lets admins paste any review URL and instantly see (a) whether it parses, (b) the resolved token, (c) the pre-selected star rating, (d) which `customer_reviews` row it lands on, (e) whether the legacy-URL recovery path was triggered, plus errors/warnings. One-click "Use real sample URL" button auto-loads a real DB token for a green-path test. Helper lives at `includes/functions.php::parse_review_url_for_admin()` and runs the exact same parser as `review.php`, so the test result is a 100% faithful preview of production behaviour.

## AI SEO Centre + Multi-Market Auto-Blogger + Citation Tracker (2026-06-14)
- **Sidebar renamed**: "AI SEO Centre" → **"AI Auto-Blogger"** (Growth section).
- **Redesigned dashboard UI** (purple AI aesthetic): hero card with "▶ Run now", 5-column health stats panel (Last run, IndexNow, LLM refresh, Engines pinged, Health), region-tagged live feed, AI Citation Tracker card, collapsible Manual Controls with copy-to-clipboard cron URL.
- **AI Multi-Market Auto-Blogger** — flagship feature:
  - Targets **4 markets** (US, UK, AU, CA). Australia (AU) added to `regions` table via idempotent migration.
  - **5-6 fresh posts published per day** (configurable via `seo_ai_daily_post_cap`, hard ceiling 6). Pass 1: one post per market in rotation. Pass 2: fill remaining slots by rotating markets.
  - **Localized content**: each post is written with market-appropriate spelling (American/British/Australian/Canadian English), currency symbol (`$`/`£`/`A$`/`C$`), and references (sales tax, VAT, GST, HST/PST).
  - Picks the most relevant un-covered featured product per market (skips anything blogged in that market in last 90 days).
  - Claude Sonnet 4.6 writes a 600-900 word editorial-style guide with lead dek + 2-3 H2 sections + product CTA, titled by the AI.
  - Auto-inserted into `blog_posts` with the next available numeric id and a `region` column. Immediately live at `/blog-post.php?id=N` with full Article JSON-LD.
  - Region pills (US/UK/AU/CA) coloured per market in the admin live feed.
- **AI Citation Tracker** — new flagship feature:
  - Polls **5 major AI engines** weekly: ChatGPT (GPT-4.1) · ChatGPT (GPT-5.2) · Claude Sonnet 4.6 · Google Gemini 2.5 Flash · MS Copilot (GPT-4o-mini).
  - Asks each engine 5 buyer-style questions ("What does Fivecodelab Software sell?", "Is X a legitimate reseller?", "Best place to buy Office 2024?", etc.).
  - Scores each response on: brand mentioned (yes/no), product family mentions (count), sentiment (positive/neutral/negative), accuracy 0-100.
  - Per-engine summary cards: 100% cite rate / accuracy / GREAT-PARTIAL-MISSING status.
  - Collapsible "View last N AI responses" showing the full text of what each AI said about the brand.
  - Auto-runs weekly via `cron/citation-weekly.php` registered in `start.sh`.
  - Manual "Run citation check" button in admin (5 engines × 2 quick queries = 10 LLM calls).
- **Production-grade SEO infrastructure**:
  - **Dynamic robots.txt** (`robots-dynamic.php`) — 41 user-agent allow-lists covering Googlebot, Bingbot, GPTBot, ChatGPT-User, OAI-SearchBot, ClaudeBot, anthropic-ai, PerplexityBot, Perplexity-User, Google-Extended, Applebot-Extended, MicrosoftPreview, MistralAI-User, cohere-ai, YouBot, PhindBot, KagiBot, Amazonbot, meta-externalagent, etc. Sitemap URL adapts to the request host (no preview-URL leakage in production).
  - **Apache .htaccess** updated with rewrite rules so production hosts (cPanel/Plesk/LiteSpeed) route `/sitemap.xml` → `sitemap-xml.php`, `/merchant-feed.xml` → `merchant-feed.php`, `/robots.txt` → `robots-dynamic.php`.
  - **Internal linking (SEO boost)**: each blog post now renders 4 "More guides you might like" links + 3 "Best-selling products mentioned in this guide" cards, passing link equity from editorial content to commercial product pages.
- **Notification system** — purple alert in SEO Centre, orange toast on every admin page until acknowledged, red badge on the sidebar "AI Auto-Blogger" link with unread count.
- **Daily background runner** registered in `start.sh` (90s warmup + every 24h loop). Idempotent — content-hash drift detection + per-day caps + per-market 90-day cooldown.
- **Weekly citation runner** registered in `start.sh` (5min warmup + every 7d loop).
- **Telemetry**: each pipeline run records LLM call count + estimated tokens + IndexNow URL count + sitemap URL count, surfaced in the admin stats panel.
- **Production-ready**: All schema migrations are idempotent (start.sh + `seo_ensure_table` self-heal). Tested 100% green via `testing_agent_v3_fork` × 2 iterations (frontend + backend + sitemap + llms-full + robots.txt + citation tracker + internal linking + cross-tab navigation). No hardcoded URLs (uses `site_url()` everywhere).
- **P1 backlog cleanup** verified — DB sweep returned zero "Maventech" leftovers in blog_posts, pages, faqs, products, settings, or email_templates.

## Test Status
- Smoke test screenshots: home + footer band — PayPal theme is rendering correctly
- Admin login: verified via curl (200, dashboard title shows "Admin · Dashboard · Fivecodelab Software")
- Branding strings present on homepage, contact page, footer, and JSON-LD structured data

## Roadmap / Backlog
- **P1** — Update SEO/page meta references that still reference "Maventech" inside database content (blog posts, FAQ pages contain occasional brand mentions in long-form HTML stored in tables: `pages`, `blog_posts`, `faqs`, `email_templates`).
- **P2** — Replace any "Maventech" leftovers in `database.sql` so a fresh-pod seed comes up already branded as Fivecodelab.
- **P2** — Refresh OG/share image (`assets/images/badges/microsoft-verified.svg` placeholder) with a Fivecodelab-branded asset.
- **P2** — Optional: theme-toggle dark mode could use a darker navy variant for an even more polished PayPal feel.

## Reference Files
- `/app/php-version/config.php` — site constants
- `/app/php-version/includes/header.php` — topbar, trustbar (Webmail link), navbar, deal bar
- `/app/php-version/includes/footer.php` — footer (Webmail link, Google Maps button)
- `/app/php-version/assets/css/style.css` — PayPal theme (color tokens + polish layer at end)
- `/app/php-version/includes/settings.php` — DB-backed `company_info()`
- `/app/php-version/includes/functions.php` — `ensure_admin()` seeding
- `/app/php-version/start.sh` — supervisor entrypoint

## Service Map
- Frontend (supervisor) → `php -S 0.0.0.0:3000 -t /app/php-version /app/php-version/router.php`
- MariaDB → local socket, db `ucode_store`
- Kubernetes ingress maps the public preview URL → port 3000 (PHP)
