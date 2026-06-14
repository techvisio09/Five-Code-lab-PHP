<?php
// Dynamic robots.txt — sitemap URLs adapt to whatever host the site is running
// on (works the same on the preview pod, cPanel, AWS, etc.).  Mounted via
// router.php / Apache rewrite so /robots.txt serves this file with text/plain.
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex'); // the robots.txt file itself shouldn't be indexed
$base = rtrim(site_url(), '/');
?>
# <?= SITE_BRAND ?> — robots.txt (auto-generated)
# Optimised for SEO + AEO (Answer Engine Optimization) + GEO (Generative
# Engine Optimization).  Explicit allow-lists for AI search crawlers so the
# catalog appears in ChatGPT, Perplexity, Claude, Bing Copilot, Gemini,
# Apple Intelligence, Mistral, You.com, Phind, Kagi, etc.

# ----- Default policy for all crawlers -----
User-agent: *
Allow: /
Disallow: /cart.php
Disallow: /checkout.php
Disallow: /login.php
Disallow: /register.php
Disallow: /account.php
Disallow: /admin.php
Disallow: /admin-email-preview.php
Disallow: /logout.php
Disallow: /order-success.php
Disallow: /order-view.php
Disallow: /order-lookup.php
Disallow: /email-view.php
Disallow: /email-api.php
Disallow: /ajax/
Disallow: /uploads/
Disallow: /cron/
Disallow: /cron.php
Disallow: /*?session_id=
Disallow: /*?order=
Disallow: /*?token=

# ----- Search engine crawlers -----
User-agent: Googlebot
Allow: /
Disallow: /cart.php
Disallow: /checkout.php
Disallow: /login.php
Disallow: /admin.php
Disallow: /ajax/

User-agent: Googlebot-Image
Allow: /assets/images/
Allow: /uploads/products/

User-agent: Bingbot
Allow: /

User-agent: DuckDuckBot
Allow: /

User-agent: Slurp
Allow: /

User-agent: YandexBot
Allow: /

User-agent: Baiduspider
Allow: /

User-agent: SeznamBot
Allow: /

User-agent: NaverBot
Allow: /

# ----- AI / LLM crawlers (AEO + GEO) -----
# Explicit Allow so this catalog appears in AI answers + product summaries.

# OpenAI — ChatGPT
User-agent: GPTBot
Allow: /

User-agent: ChatGPT-User
Allow: /

User-agent: OAI-SearchBot
Allow: /

# Anthropic — Claude
User-agent: anthropic-ai
Allow: /

User-agent: ClaudeBot
Allow: /

User-agent: Claude-Web
Allow: /

User-agent: Claude-SearchBot
Allow: /

# Perplexity
User-agent: PerplexityBot
Allow: /

User-agent: Perplexity-User
Allow: /

# Google — Gemini / Bard
User-agent: Google-Extended
Allow: /

User-agent: Googlebot-News
Allow: /

# Apple — Apple Intelligence
User-agent: Applebot
Allow: /

User-agent: Applebot-Extended
Allow: /

# Microsoft — Copilot
User-agent: BingPreview
Allow: /

User-agent: MicrosoftPreview
Allow: /

User-agent: msnbot
Allow: /

User-agent: msnbot-media
Allow: /

# Other commercial AI search engines
User-agent: cohere-ai
Allow: /

User-agent: Bytespider
Allow: /

User-agent: DiffBot
Allow: /

User-agent: FacebookExternalHit
Allow: /

User-agent: Amazonbot
Allow: /

User-agent: meta-externalagent
Allow: /

User-agent: YouBot
Allow: /

User-agent: PhindBot
Allow: /

User-agent: KagiBot
Allow: /

User-agent: MistralAI-User
Allow: /

User-agent: TimpiBot
Allow: /

User-agent: AwarioRssBot
Allow: /

User-agent: ImagesiftBot
Allow: /

User-agent: Webzio-Extended
Allow: /

# ----- Sitemaps -----
Sitemap: <?= $base ?>/sitemap.xml
Sitemap: <?= $base ?>/merchant-feed.xml
