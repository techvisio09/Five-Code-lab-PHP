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

## Latest additions (P3 cleanup)
- **Webmail link removed from footer** (Brand & Support columns). Top trust-bar Webmail link kept.
- **`merchant-feed.xml` refreshed** — 37 products now exported with rich Google Merchant + Bing Shopping fields: `g:mobile_link`, `g:mpn`, `g:product_type`, `g:item_group_id`, `g:product_highlight` (×4), `g:additional_image_link`, multi-country `g:shipping` (US/GB/CA/AU), `g:max_handling_time`, `g:min_handling_time`, `g:tax`, 5× `g:custom_label_*` (Digital, brand, platform, sale state, category), `g:product_review_count` + `g:product_review_average`, `g:sale_price` + `g:sale_price_effective_date`.
- **Per-post `Article` JSON-LD** on `blog-post.php` — drives Google Top Stories eligibility + clean AI summarisation: `mainEntityOfPage`, `headline`, `wordCount` (auto-computed), `timeRequired` (auto-computed reading minutes), `articleSection`, `keywords`, `inLanguage`, `isAccessibleForFree`, `author`/`publisher` Organization with 512×512 `ImageObject` logo, `BreadcrumbList`, plus `itemscope` microdata on the `<article>` element.
- **Image alt-text audit completed** — every product card already uses SEO-rich `product_img_alt()` (name + platform + license type + discount + brand). Enriched: blog cards (`index.php` + `blog.php`) now have descriptive titles + `loading="lazy"` + `decoding="async"` + explicit width/height for CLS, OS badge on product page uses "Microsoft Windows compatible" / "Apple macOS compatible" alts, blog hero image has descriptive alt + `fetchpriority="high"`. Only remaining empty `alt=""` is the decorative OS chip icon next to its visible text label (correct per ARIA).
- **Review URL bug fix** — `/review.php` now parses both proper (`?t=TOK&rating=N`) and legacy (`?t=TOK?rating=N`) URLs. DB swept for stale templates; source-code audit shows zero remaining bad patterns.
- **Admin "Email Link Sanity Test" tool** — collapsible card at the top of the Reviews tab in `/admin.php` lets admins paste any review URL and instantly see (a) whether it parses, (b) the resolved token, (c) the pre-selected star rating, (d) which `customer_reviews` row it lands on, (e) whether the legacy-URL recovery path was triggered, plus errors/warnings. One-click "Use real sample URL" button auto-loads a real DB token for a green-path test. Helper lives at `includes/functions.php::parse_review_url_for_admin()` and runs the exact same parser as `review.php`, so the test result is a 100% faithful preview of production behaviour.

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
