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
- **Branding**
  - `config.php` updated: `SITE_BRAND`, `SITE_LEGAL`, `SITE_EMAIL`, `ADMIN_EMAIL`, `SITE_ADDRESS`, new `SITE_WEBMAIL` constant
  - DB `settings` table updated for `company_name`, `company_email`, `company_address`, statement names and gateway merchant names
  - All hardcoded "Maventech" strings replaced with "Fivecodelab" in PHP files (header, footer, account, checkout, about-us, reviews, email helpers, pdf templates, mailer, visitor_track)
  - Promo code `MAVEN20` → `FIVE20` in header bar + deal bar
- **Custom Fivecodelab brand assets**
  - **Mark SVG** (`assets/images/fivecodelab-logo.svg`): rounded navy/blue square with stylized "5" + signature yellow accent dot — rendered by `render_logo()` and used in navbar + footer (2 instances per page).
  - **Wordmark SVG** (`assets/images/fivecodelab-wordmark.svg` + `assets/images/brand/fivecodelab-wordmark.svg`): full mark + "Fivecodelab Software / AUTHORIZED RESELLER" wordmark, plugged into invoices/PDFs (`includes/pdf.php`) and email templates (`includes/email.php`).
  - **OG share SVG** (`assets/images/fivecodelab-og.svg`, 1200×630): branded social share image with brand mark + wordmark + tagline + yellow "Shop Now →" CTA — wired into `<meta property="og:image">` via `header.php`.
- **Hero Orbit (front-page)** — mirrors fivecodelabsoftware.com
  - `.hero-orbit` with 2 rotating dashed rings + **6 floating bouncing Office satellite icons** + **3D flip-and-fade central spotlight** cycling every 3.4s + floating yellow "Keys in 15–30 min" pill + ★ rating chip.
  - Honours `prefers-reduced-motion`. Full `data-testid` coverage.
- **PayPal-style theme overhaul** — clean white surfaces, navy `#003087` / `#0070BA` headings, yellow `#FFC439` CTAs.
- **Comprehensive Dark Mode** — Elegant PayPal-dark theme (deep navy `#0A1330` bg, sky-blue `#7CD3FF` accents, yellow `#FFC439` highlights). Every text element verified visible:
  - Hero (navy gradient + white headline + cyan accent), navbar (translucent indigo glass + white nav links), trustbar, deal bar
  - Cards: dark indigo `#142046` surface, light-gray body copy, white headings, white product image wraps preserved so artwork pops
  - Accordion: active state with light-cyan title on dark indigo bg
  - Forms: navy inputs with blue focus ring
  - Buttons: bright cyan `.btn-primary`, yellow CTAs, ghost buttons with light border
  - Footer always navy with light-gray links + yellow hover
  - Chat widget, cart popup, alerts, modals, tables all themed.
- **Webmail link** in top trust bar + footer (Brand column + Support column)
- **Google Maps** auto-resolves to Moreno Valley via dynamic `urlencode($brandAddress)`
- **Admin credentials**: `services@fivecodelabsoftware.com` / `Fivecode@2026!`

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
