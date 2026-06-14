<?php
// =====================================================================
//  Fivecodelab Software · AI-powered SEO / GEO / AEO automation engine
//  --------------------------------------------------------------------
//  This module is the brain that runs daily from `/cron/seo-daily.php`
//  (or once on-demand from the admin "AI SEO Centre" tab).  It:
//
//   1. AI-generates per-product / per-blog meta titles + descriptions
//      (uses Claude Sonnet 4.6 via the Emergent Universal Key)
//   2. AI-generates an AEO/GEO-ready FAQ block per product so AI
//      engines (ChatGPT / Perplexity / Claude / Gemini) lift answers
//      verbatim into their responses
//   3. Regenerates the public sitemap.xml + sitemap.json + llms-full.txt
//   4. Pings IndexNow → Bing + Yandex + Seznam + Naver of any URL
//      whose content has changed since the last run (FREE, no auth)
//   5. Pings Google + Bing of the refreshed sitemap
//   6. Records a SEO run log so the admin dashboard can show health
// =====================================================================

require_once __DIR__ . '/functions.php';

function seo_emergent_key(): string
{
    return (string)(getenv('EMERGENT_LLM_KEY') ?: 'sk-emergent-8Ad362c4681F5B58f7');
}

/** Markets the AI Auto-Blogger targets. Order is rotation priority. */
function seo_target_markets(): array
{
    return ['US', 'UK', 'AU', 'CA'];
}

function seo_market_label(string $code): string
{
    return [
        'US' => 'United States', 'UK' => 'United Kingdom',
        'AU' => 'Australia',     'CA' => 'Canada',
    ][$code] ?? $code;
}

function seo_market_currency(string $code): array
{
    return [
        'US' => ['symbol' => '$',  'code' => 'USD', 'spelling' => 'American English (color, organize, customize)'],
        'UK' => ['symbol' => '£',  'code' => 'GBP', 'spelling' => 'British English (colour, organise, customise)'],
        'AU' => ['symbol' => 'A$', 'code' => 'AUD', 'spelling' => 'Australian English (colour, organise, prioritise)'],
        'CA' => ['symbol' => 'C$', 'code' => 'CAD', 'spelling' => 'Canadian English (colour, organize, behaviour)'],
    ][$code] ?? ['symbol' => '$', 'code' => 'USD', 'spelling' => 'American English'];
}

/** Approximate token count for usage tracking (chars/4 heuristic). */
function seo_estimate_tokens(string $text): int
{
    return (int)ceil(mb_strlen($text) / 4);
}

function seo_llm_complete(string $system, string $user, int $maxTokens = 600, string $model = 'claude-sonnet-4-6'): string
{
    $payload = json_encode([
        'model'      => $model,
        'messages'   => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $user],
        ],
        'max_tokens'  => $maxTokens,
        'temperature' => 0.4,
    ]);
    $ch = curl_init('https://integrations.emergentagent.com/llm/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . seo_emergent_key(),
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 45,
    ]);
    $body = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    // Track approximate token usage for the SEO Centre stats panel
    $GLOBALS['__seo_llm_calls'] = ($GLOBALS['__seo_llm_calls'] ?? 0) + 1;
    $GLOBALS['__seo_llm_tokens'] = ($GLOBALS['__seo_llm_tokens'] ?? 0)
        + seo_estimate_tokens($system) + seo_estimate_tokens($user) + seo_estimate_tokens((string)$body);
    if ($http >= 400 || !$body) return '';
    $j = json_decode($body, true);
    return (string)($j['choices'][0]['message']['content'] ?? '');
}

// --------------------------------------------------------------------
//  IndexNow — instant indexing notification to Bing/Yandex/Seznam/Naver
//  (no auth, no API key request — just a self-hosted key file)
// --------------------------------------------------------------------
function seo_indexnow_key(): string
{
    $stored = setting_get('seo_indexnow_key', '');
    if ($stored === '') {
        $stored = bin2hex(random_bytes(16));
        setting_set('seo_indexnow_key', $stored);
        // Publish the verification file at /<key>.txt so IndexNow accepts the host
        @file_put_contents(__DIR__ . '/../' . $stored . '.txt', $stored);
    }
    return $stored;
}

function seo_indexnow_submit(array $urls): array
{
    $urls = array_values(array_unique(array_filter($urls)));
    if (empty($urls)) return ['submitted' => 0, 'status' => 'skipped (no urls)'];
    $key   = seo_indexnow_key();
    $host  = parse_url(site_url(), PHP_URL_HOST) ?: 'localhost';
    $body  = json_encode([
        'host'    => $host,
        'key'     => $key,
        'keyLocation' => site_url() . '/' . $key . '.txt',
        'urlList' => $urls,
    ]);
    $results = [];
    foreach (['https://api.indexnow.org/indexnow', 'https://www.bing.com/indexnow', 'https://yandex.com/indexnow'] as $endpoint) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 15,
        ]);
        curl_exec($ch);
        $results[$endpoint] = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    }
    return ['submitted' => count($urls), 'endpoints' => $results];
}

function seo_ping_google_bing_sitemap(): array
{
    $sm = urlencode(site_url() . '/sitemap.xml');
    $out = [];
    foreach ([
        'google' => 'https://www.google.com/ping?sitemap=' . $sm,
        'bing'   => 'https://www.bing.com/ping?sitemap='  . $sm,
    ] as $name => $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
        curl_exec($ch);
        $out[$name] = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    }
    return $out;
}

// --------------------------------------------------------------------
//  AI meta-generation — one per product + one per blog post
// --------------------------------------------------------------------
function seo_ai_meta_for_product(array $p): array
{
    $system = 'You are an SEO + GEO + AEO copywriter for an authorised software reseller. '
            . 'Your output must be ONE strict JSON object with the keys: meta_title (max 60 chars), '
            . 'meta_description (max 158 chars, action-oriented, mentions "instant delivery" or "lifetime license"), '
            . 'aeo_question (a real question a buyer would ask voice assistants / AI engines about this product), '
            . 'aeo_answer (one paragraph, 40-80 words, the kind of clear answer ChatGPT/Perplexity quote verbatim). '
            . 'No code-fences, no commentary, just the JSON.';
    $userMsg = 'Product: ' . $p['name']
             . ' | Platform: ' . ($p['platform'] ?? 'Windows')
             . ' | Category: ' . ($p['category'] ?? 'Software')
             . ' | Price: $' . $p['price']
             . ' | Reseller brand: ' . SITE_BRAND;
    $raw = seo_llm_complete($system, $userMsg, 500);
    $j = json_decode(_seo_strip_codefence($raw), true);
    return is_array($j) ? $j : [];
}

function seo_ai_meta_for_blog(array $b): array
{
    $excerpt = mb_substr(strip_tags((string)($b['content'] ?? '')), 0, 800);
    $system = 'You are an SEO + GEO + AEO copywriter. Output strict JSON only with keys: '
            . 'meta_title (max 60 chars, compelling), meta_description (max 158 chars), '
            . 'aeo_question (a real reader query this article answers), aeo_answer (40-80 words, AI-quotable).';
    $userMsg = 'Article title: ' . $b['title'] . "\n\nExcerpt:\n" . $excerpt;
    $raw = seo_llm_complete($system, $userMsg, 500);
    $j = json_decode(_seo_strip_codefence($raw), true);
    return is_array($j) ? $j : [];
}

function _seo_strip_codefence(string $s): string
{
    $s = trim($s);
    if (str_starts_with($s, '```')) {
        $s = preg_replace('/^```(?:json)?\s*/', '', $s);
        $s = preg_replace('/```\s*$/', '', $s);
    }
    return trim($s);
}

// --------------------------------------------------------------------
//  Persist generated meta + FAQ entries
// --------------------------------------------------------------------
function seo_ensure_table(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS seo_meta (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kind VARCHAR(20) NOT NULL,
        ref_id VARCHAR(120) NOT NULL,
        meta_title VARCHAR(255) NOT NULL DEFAULT '',
        meta_description TEXT,
        aeo_question TEXT,
        aeo_answer TEXT,
        content_hash CHAR(40) NOT NULL DEFAULT '',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq (kind, ref_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS seo_run_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ran_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        items_refreshed INT DEFAULT 0,
        urls_indexnow INT DEFAULT 0,
        sitemap_urls INT DEFAULT 0,
        notes TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS seo_ai_blog_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        blog_id VARCHAR(100) NOT NULL,
        product_slug VARCHAR(191) NOT NULL,
        product_name VARCHAR(255) NOT NULL,
        title VARCHAR(255) NOT NULL,
        region VARCHAR(8) NOT NULL DEFAULT 'US',
        word_count INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        acknowledged_at TIMESTAMP NULL DEFAULT NULL,
        KEY idx_created (created_at),
        KEY idx_region (region)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Safety: backfill region column on existing installs (idempotent)
    try { db()->exec("ALTER TABLE seo_ai_blog_log ADD COLUMN IF NOT EXISTS region VARCHAR(8) NOT NULL DEFAULT 'US' AFTER title"); } catch (Throwable $e) {}
    try { db()->exec("ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS region VARCHAR(8) NOT NULL DEFAULT '' AFTER image"); } catch (Throwable $e) {}

    // AI Citation Tracker — stores what AI engines say about the brand
    db()->exec("CREATE TABLE IF NOT EXISTS seo_ai_citations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        engine VARCHAR(40) NOT NULL,
        engine_model VARCHAR(80) NOT NULL DEFAULT '',
        query_text VARCHAR(500) NOT NULL,
        response_text MEDIUMTEXT,
        brand_mentioned TINYINT(1) NOT NULL DEFAULT 0,
        product_mentions INT NOT NULL DEFAULT 0,
        sentiment VARCHAR(20) NOT NULL DEFAULT 'neutral',
        accuracy_score TINYINT NOT NULL DEFAULT 0,
        checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_checked (checked_at),
        KEY idx_engine (engine)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function seo_get_meta(string $kind, string $refId): array
{
    seo_ensure_table();
    $s = db()->prepare('SELECT * FROM seo_meta WHERE kind=? AND ref_id=? LIMIT 1');
    $s->execute([$kind, $refId]);
    return $s->fetch() ?: [];
}

function seo_save_meta(string $kind, string $refId, array $meta, string $contentHash = ''): void
{
    seo_ensure_table();
    $row = seo_get_meta($kind, $refId);
    $title = mb_substr((string)($meta['meta_title'] ?? ''), 0, 255);
    $desc  = (string)($meta['meta_description'] ?? '');
    $q     = (string)($meta['aeo_question'] ?? '');
    $a     = (string)($meta['aeo_answer'] ?? '');
    if (!$row) {
        db()->prepare('INSERT INTO seo_meta (kind, ref_id, meta_title, meta_description, aeo_question, aeo_answer, content_hash) VALUES (?,?,?,?,?,?,?)')
            ->execute([$kind, $refId, $title, $desc, $q, $a, $contentHash]);
    } else {
        db()->prepare('UPDATE seo_meta SET meta_title=?, meta_description=?, aeo_question=?, aeo_answer=?, content_hash=? WHERE id=?')
            ->execute([$title, $desc, $q, $a, $contentHash, $row['id']]);
    }
}

// --------------------------------------------------------------------
//  llms-full.txt — comprehensive AI-crawler context file
// --------------------------------------------------------------------
function seo_generate_llms_full(): int
{
    seo_ensure_table();
    $base = rtrim(site_url(), '/');
    $lines = [];
    $lines[] = '# ' . SITE_BRAND;
    $lines[] = '';
    $lines[] = '> Genuine Microsoft Office, Windows and antivirus license keys with instant digital delivery. '
             . 'Authorised reseller at ' . $base . '. Address: ' . SITE_ADDRESS . '. '
             . 'Contact: ' . SITE_EMAIL . ' · ' . SITE_PHONE . '.';
    $lines[] = '';
    $lines[] = '## About';
    $lines[] = '- 50,000+ customers · 4.6/5 average rating';
    $lines[] = '- Instant digital delivery (15–30 minutes)';
    $lines[] = '- 30-day money-back guarantee';
    $lines[] = '- Lifetime, perpetual licenses (one-time purchase)';
    $lines[] = '- US-based support';
    $lines[] = '';
    $lines[] = '## Top Categories';
    foreach (db()->query('SELECT slug, name FROM categories ORDER BY name LIMIT 12') as $c) {
        $lines[] = '- [' . $c['name'] . '](' . $base . '/category.php?slug=' . $c['slug'] . ')';
    }
    $lines[] = '';
    $lines[] = '## Best-selling Products';
    $top = db()->query('SELECT slug, name, price FROM products WHERE rating >= 4 AND ' . active_regions_sql_in('region') . ' ORDER BY reviews DESC LIMIT 24');
    foreach ($top as $p) {
        $lines[] = '- [' . $p['name'] . '](' . $base . '/product.php?slug=' . $p['slug'] . ') — $' . number_format((float)$p['price'], 2);
    }
    $lines[] = '';
    $lines[] = '## Frequently Asked Questions (AI-curated)';
    $faqs = db()->query('SELECT aeo_question, aeo_answer FROM seo_meta WHERE aeo_question <> "" ORDER BY updated_at DESC LIMIT 60');
    foreach ($faqs as $f) {
        $lines[] = '### ' . trim($f['aeo_question']);
        $lines[] = trim($f['aeo_answer']);
        $lines[] = '';
    }
    $lines[] = '## Sitemaps';
    $lines[] = '- [XML sitemap](' . $base . '/sitemap.xml)';
    $lines[] = '- [Merchant feed](' . $base . '/merchant-feed.xml)';
    $lines[] = '';
    $lines[] = '## Crawler policy';
    $lines[] = 'AI crawlers (GPTBot, ChatGPT-User, OAI-SearchBot, ClaudeBot, PerplexityBot, Google-Extended, MistralAI-User, etc.) are explicitly allowed via /robots.txt.';
    $body = implode("\n", $lines) . "\n";
    file_put_contents(__DIR__ . '/../llms-full.txt', $body);
    return count($lines);
}

// --------------------------------------------------------------------
//  Sitemap regeneration (overwrites /sitemap.xml)
// --------------------------------------------------------------------
function seo_generate_sitemap(): int
{
    $base = rtrim(site_url(), '/');
    $out = [];
    $out[] = '<?xml version="1.0" encoding="UTF-8"?>';
    $out[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';
    $static = ['/', '/shop.php', '/about-us.php', '/contact.php', '/blog.php', '/reviews.php', '/page.php?slug=privacy-policy', '/page.php?slug=terms-of-service', '/page.php?slug=refund-policy', '/order-lookup.php'];
    foreach ($static as $p) {
        $out[] = '  <url><loc>' . esc($base . $p) . '</loc><changefreq>daily</changefreq><priority>0.9</priority></url>';
    }
    foreach (db()->query('SELECT slug, image FROM products WHERE ' . active_regions_sql_in('region')) as $p) {
        $img = !empty($p['image']) ? '<image:image><image:loc>' . esc($p['image']) . '</image:loc></image:image>' : '';
        $out[] = '  <url><loc>' . esc($base . '/product.php?slug=' . $p['slug']) . '</loc><changefreq>weekly</changefreq><priority>0.8</priority>' . $img . '</url>';
    }
    foreach (db()->query('SELECT slug FROM categories') as $c) {
        $out[] = '  <url><loc>' . esc($base . '/category.php?slug=' . $c['slug']) . '</loc><changefreq>weekly</changefreq><priority>0.7</priority></url>';
    }
    foreach (db()->query('SELECT id FROM blog_posts') as $b) {
        $out[] = '  <url><loc>' . esc($base . '/blog-post.php?id=' . (int)$b['id']) . '</loc><changefreq>monthly</changefreq><priority>0.6</priority></url>';
    }
    $out[] = '</urlset>';
    $count = count($out) - 3;
    file_put_contents(__DIR__ . '/../sitemap.xml', implode("\n", $out));
    return $count;
}

// --------------------------------------------------------------------
//  Main entry — run the full daily pipeline
// --------------------------------------------------------------------
function seo_run_daily(int $maxItems = 8): array
{
    seo_ensure_table();
    $base = rtrim(site_url(), '/');
    $refreshed = 0;
    $indexnowUrls = [];
    $notes = [];

    // 1. Pick products whose content hash has drifted since last run
    $candidates = [];
    $rows = db()->query('SELECT slug, name, platform, category, price, description FROM products WHERE ' . active_regions_sql_in('region') . ' ORDER BY id DESC LIMIT 200');
    foreach ($rows as $p) {
        $hash = sha1($p['name'] . '|' . ($p['description'] ?? '') . '|' . $p['price']);
        $existing = seo_get_meta('product', $p['slug']);
        if (!$existing || ($existing['content_hash'] ?? '') !== $hash || empty($existing['aeo_answer'])) {
            $candidates[] = ['type' => 'product', 'row' => $p, 'hash' => $hash];
            if (count($candidates) >= $maxItems) break;
        }
    }

    // 2. Pick blog posts that drifted
    if (count($candidates) < $maxItems) {
        $blogs = db()->query('SELECT id, title, content FROM blog_posts ORDER BY id DESC LIMIT 30');
        foreach ($blogs as $b) {
            $hash = sha1($b['title'] . '|' . substr((string)$b['content'], 0, 500));
            $existing = seo_get_meta('blog', (string)$b['id']);
            if (!$existing || ($existing['content_hash'] ?? '') !== $hash || empty($existing['aeo_answer'])) {
                $candidates[] = ['type' => 'blog', 'row' => $b, 'hash' => $hash];
                if (count($candidates) >= $maxItems) break;
            }
        }
    }

    // 3. Generate AI meta for each candidate
    foreach ($candidates as $c) {
        try {
            $meta = $c['type'] === 'product'
                ? seo_ai_meta_for_product($c['row'])
                : seo_ai_meta_for_blog($c['row']);
            if (empty($meta['meta_title']) && empty($meta['meta_description'])) {
                $notes[] = 'AI returned empty meta for ' . $c['type'] . ' ' . ($c['row']['slug'] ?? $c['row']['id']);
                continue;
            }
            $refId = $c['type'] === 'product' ? (string)$c['row']['slug'] : (string)$c['row']['id'];
            seo_save_meta($c['type'], $refId, $meta, $c['hash']);
            $refreshed++;
            $indexnowUrls[] = $c['type'] === 'product'
                ? $base . '/product.php?slug=' . $refId
                : $base . '/blog-post.php?id=' . $refId;
        } catch (Throwable $e) {
            $notes[] = 'AI error: ' . $e->getMessage();
        }
    }

    // 4. Regenerate static SEO files
    $sitemapUrls = seo_generate_sitemap();
    $llmsLines   = seo_generate_llms_full();
    $notes[]     = 'Sitemap urls: ' . $sitemapUrls . ', llms-full lines: ' . $llmsLines;

    // 5. Ping IndexNow + Google + Bing
    $indexnowUrls = array_merge($indexnowUrls, [$base . '/', $base . '/shop.php', $base . '/blog.php']);
    $in = seo_indexnow_submit(array_values(array_unique($indexnowUrls)));
    $pings = seo_ping_google_bing_sitemap();
    $notes[] = 'IndexNow endpoints: ' . json_encode($in['endpoints'] ?? []);
    $notes[] = 'Sitemap pings: ' . json_encode($pings);

    // 6. AI auto-blog: 5-6 fresh posts per day, multi-market (US/UK/AU/CA)
    $autoBlog = seo_ai_run_daily_blog();
    if (!empty($autoBlog['posts'])) {
        foreach ($autoBlog['posts'] as $p) {
            if (!empty($p['ok']) && !empty($p['url'])) {
                $indexnowUrls[] = $p['url'];
                $notes[] = 'AI [' . ($p['region'] ?? '?') . '] published: "' . $p['title'] . '" → /blog-post.php?id=' . $p['blog_id'];
            } elseif (!empty($p['error'])) {
                $notes[] = 'AI auto-blog error [' . ($p['region'] ?? '?') . ']: ' . $p['error'];
            }
        }
        $newUrls = array_values(array_filter(array_map(fn($x) => $x['url'] ?? null, $autoBlog['posts'])));
        if ($newUrls) {
            seo_indexnow_submit($newUrls);
            $sitemapUrls = seo_generate_sitemap();
        }
    } elseif (!empty($autoBlog['skipped'])) {
        $notes[] = 'AI auto-blog skipped: ' . $autoBlog['skipped'];
    }

    // 7. Log the run
    $llmCalls  = (int)($GLOBALS['__seo_llm_calls']  ?? 0);
    $llmTokens = (int)($GLOBALS['__seo_llm_tokens'] ?? 0);
    $notes[] = 'LLM: ' . $llmCalls . ' calls · ~' . $llmTokens . ' tokens';
    db()->prepare('INSERT INTO seo_run_log (items_refreshed, urls_indexnow, sitemap_urls, notes) VALUES (?,?,?,?)')
        ->execute([$refreshed, count($indexnowUrls), $sitemapUrls, implode("\n", $notes)]);

    return [
        'items_refreshed'  => $refreshed,
        'urls_indexnow'    => count($indexnowUrls),
        'sitemap_urls'     => $sitemapUrls,
        'llms_full_lines'  => $llmsLines,
        'indexnow_results' => $in,
        'sitemap_pings'    => $pings,
        'auto_blog'        => $autoBlog,
        'llm_calls'        => (int)($GLOBALS['__seo_llm_calls']  ?? 0),
        'llm_tokens'       => (int)($GLOBALS['__seo_llm_tokens'] ?? 0),
        'notes'            => $notes,
    ];
}

function seo_recent_runs(int $limit = 10): array
{
    seo_ensure_table();
    $s = db()->prepare('SELECT * FROM seo_run_log ORDER BY id DESC LIMIT ?');
    $s->bindValue(1, $limit, PDO::PARAM_INT);
    $s->execute();
    return $s->fetchAll() ?: [];
}

// --------------------------------------------------------------------
//  AI Auto-Blogger — picks featured products and publishes editorial-
//  style blog posts about them, one per active market per day, fully
//  localized (spelling + currency + tone).  Hard cap = 6 posts/day.
// --------------------------------------------------------------------
function seo_ai_blog_today_count(): int
{
    seo_ensure_table();
    return (int)db()->query("SELECT COUNT(*) FROM seo_ai_blog_log WHERE DATE(created_at) = CURDATE()")->fetchColumn();
}

function seo_ai_blog_today_count_by_market(string $region): int
{
    seo_ensure_table();
    $s = db()->prepare("SELECT COUNT(*) FROM seo_ai_blog_log WHERE DATE(created_at) = CURDATE() AND region = ?");
    $s->execute([$region]);
    return (int)$s->fetchColumn();
}

function seo_ai_pick_blog_product_for_market(string $region): array
{
    // Pick the next product that hasn't had an AI blog post in this market in
    // the last 90 days.  Falls back to the least-recently-covered one to keep
    // the rotation healthy long-term.
    $stmt = db()->prepare(
        "SELECT p.* FROM products p
         WHERE " . active_regions_sql_in('p.region') . "
           AND p.slug NOT IN (
             SELECT product_slug FROM seo_ai_blog_log
             WHERE region = ?
               AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
           )
         ORDER BY p.reviews DESC, RAND()
         LIMIT 1"
    );
    $stmt->execute([$region]);
    $row = $stmt->fetch();
    if ($row) return $row;
    // Fallback — rotate the least recently used product in this market
    $stmt = db()->prepare(
        "SELECT p.* FROM products p
         LEFT JOIN seo_ai_blog_log b ON b.product_slug = p.slug AND b.region = ?
         WHERE " . active_regions_sql_in('p.region') . "
         ORDER BY b.created_at ASC, RAND()
         LIMIT 1"
    );
    $stmt->execute([$region]);
    $row = $stmt->fetch();
    return $row ?: [];
}

/** Keep backwards-compat with the older single-market picker used by the
 *  "Generate one now" button.  Picks any market that hasn't filled today. */
function seo_ai_pick_blog_product(): array
{
    foreach (seo_target_markets() as $m) {
        $p = seo_ai_pick_blog_product_for_market($m);
        if ($p) { $p['__target_region'] = $m; return $p; }
    }
    return [];
}

function seo_ai_generate_blog_body(array $product, string $region = 'US'): array
{
    $cur = seo_market_currency($region);
    $marketLabel = seo_market_label($region);
    $system = 'You are a senior SEO content writer for an authorised software reseller serving the ' . $marketLabel . ' market. '
            . 'Write a 600-900 word, HTML-formatted blog post that BUYERS in ' . $marketLabel . ' would actually find useful. '
            . 'Use ' . $cur['spelling'] . ' for ALL spelling. Use the local currency symbol "' . $cur['symbol'] . '" when mentioning price. '
            . 'It must read as authentic editorial — no marketing fluff, no over-promising. '
            . 'Output STRICT JSON only (no code fence) with keys: '
            . 'title (≤ 70 chars, compelling, NOT clickbait, can naturally hint at the market when relevant — e.g. "for ' . $marketLabel . ' Buyers"), '
            . 'lead (one-sentence dek, ≤ 160 chars), '
            . 'html (the article body — use <h2>, <h3>, <p>, <ul><li>, <strong>; '
            . 'open with a 1-sentence <p class="lead">…</p> dek; include 2-3 H2 sections; end with a clear '
            . '<p><a class="btn btn-primary rounded-pill px-4" href="product.php?slug=' . addslashes($product['slug']) . '">View ' . addslashes(mb_substr($product['name'], 0, 60)) . ' →</a></p> CTA), '
            . 'read_time (e.g. "5 min read" — base it on the html body length).';
    $userMsg = 'Featured product:'
             . "\n- Name: " . $product['name']
             . "\n- Platform: " . ($product['platform'] ?? 'Windows')
             . "\n- Category: " . ($product['category'] ?? 'Software')
             . "\n- Price: " . $cur['symbol'] . $product['price'] . ' ' . $cur['code']
             . "\n- License type: " . ($product['license_type'] ?? 'lifetime')
             . "\n- Brand: " . ($product['brand'] ?? '')
             . "\n- Reseller: " . SITE_BRAND
             . "\n- Target market: " . $marketLabel . ' (' . $region . ')'
             . "\n\nWrite an editorial-style guide that genuinely helps a ' . $marketLabel . ' buyer decide if this product is right for them. "
             . 'You can cover topics like: who it is for, what is genuinely new/notable, common misconceptions, '
             . 'buying tips, comparison with subscription alternatives, installation/activation pointers — pick whichever 2-3 angles fit this product best. '
             . 'Reference the local market naturally (e.g. mention GST for AU, VAT for UK, sales tax for US, HST/PST for CA) where relevant.';
    $raw = seo_llm_complete($system, $userMsg, 2000);
    $j = json_decode(_seo_strip_codefence($raw), true);
    return is_array($j) ? $j : [];
}

function seo_ai_publish_blog_for_product(array $product, string $region = 'US'): array
{
    seo_ensure_table();
    if (empty($product['slug']) || empty($product['name'])) {
        return ['ok' => false, 'error' => 'invalid product'];
    }
    $body = seo_ai_generate_blog_body($product, $region);
    if (empty($body['title']) || empty($body['html'])) {
        return ['ok' => false, 'error' => 'AI returned empty body'];
    }

    $nextId = (int)db()->query("SELECT IFNULL(MAX(CAST(id AS UNSIGNED)), 0) + 1 FROM blog_posts")->fetchColumn();
    if ($nextId < 1) $nextId = 1;

    $title    = mb_substr((string)$body['title'], 0, 250);
    $html     = (string)$body['html'];
    $readTime = (string)($body['read_time'] ?? '5 min read');
    $date     = date('M j, Y');
    $image    = (string)($product['image'] ?? '');

    // Insert blog with region; gracefully handle older schemas missing the column
    try {
        db()->prepare('INSERT INTO blog_posts (id, title, date, read_time, image, region, content) VALUES (?,?,?,?,?,?,?)')
            ->execute([(string)$nextId, $title, $date, $readTime, $image, $region, $html]);
    } catch (Throwable $e) {
        db()->prepare('INSERT INTO blog_posts (id, title, date, read_time, image, content) VALUES (?,?,?,?,?,?)')
            ->execute([(string)$nextId, $title, $date, $readTime, $image, $html]);
    }

    $wordCount = max(1, str_word_count(strip_tags($html)));
    db()->prepare('INSERT INTO seo_ai_blog_log (blog_id, product_slug, product_name, title, region, word_count) VALUES (?,?,?,?,?,?)')
        ->execute([(string)$nextId, (string)$product['slug'], (string)$product['name'], $title, $region, $wordCount]);

    return [
        'ok'          => true,
        'blog_id'     => (string)$nextId,
        'title'       => $title,
        'word_count'  => $wordCount,
        'region'      => $region,
        'product'     => $product['name'],
        'url'         => rtrim(site_url(), '/') . '/blog-post.php?id=' . $nextId,
    ];
}

/** Daily auto-blogger — produces up to N posts/day, distributed across all
 *  target markets.  Default cap is 5 posts/day (configurable via settings
 *  `seo_ai_daily_post_cap`, hard ceiling of 6).
 */
function seo_ai_run_daily_blog(): array
{
    seo_ensure_table();
    $cap = (int)setting_get('seo_ai_daily_post_cap', 5);
    if ($cap < 1) $cap = 5; if ($cap > 6) $cap = 6;

    $existing = seo_ai_blog_today_count();
    if ($existing >= $cap) {
        return ['ok' => false, 'skipped' => 'daily cap reached (' . $existing . '/' . $cap . ')', 'posts' => []];
    }

    $posts = [];
    $markets = seo_target_markets(); // [US, UK, AU, CA]
    // Pass 1 — make sure every market has at least one post today, in order.
    foreach ($markets as $region) {
        if (count($posts) + $existing >= $cap) break;
        if (seo_ai_blog_today_count_by_market($region) > 0) continue; // market already covered today
        $product = seo_ai_pick_blog_product_for_market($region);
        if (!$product) { $posts[] = ['ok' => false, 'region' => $region, 'error' => 'no eligible product']; continue; }
        try {
            $posts[] = seo_ai_publish_blog_for_product($product, $region);
        } catch (Throwable $e) {
            $posts[] = ['ok' => false, 'region' => $region, 'error' => $e->getMessage()];
        }
    }
    // Pass 2 — fill the remaining slots by rotating markets (extra coverage)
    $idx = 0;
    while (count($posts) + $existing < $cap && $idx < 20) {
        $region = $markets[$idx % count($markets)];
        $idx++;
        $product = seo_ai_pick_blog_product_for_market($region);
        if (!$product) continue;
        try {
            $posts[] = seo_ai_publish_blog_for_product($product, $region);
        } catch (Throwable $e) {
            $posts[] = ['ok' => false, 'region' => $region, 'error' => $e->getMessage()];
        }
    }

    $okCount = count(array_filter($posts, fn($p) => !empty($p['ok'])));
    return ['ok' => $okCount > 0, 'posts' => $posts, 'published' => $okCount, 'cap' => $cap];
}

function seo_ai_recent_blog_posts(int $limit = 12): array
{
    seo_ensure_table();
    $s = db()->prepare(
        'SELECT b.*, p.image AS product_image, p.platform AS product_platform
         FROM seo_ai_blog_log b
         LEFT JOIN products p ON p.slug = b.product_slug
         ORDER BY b.id DESC LIMIT ?'
    );
    $s->bindValue(1, $limit, PDO::PARAM_INT);
    $s->execute();
    return $s->fetchAll() ?: [];
}

/** Latest health snapshot — used by the AI SEO Centre stats panel. */
function seo_ai_health_snapshot(): array
{
    seo_ensure_table();
    $last = db()->query('SELECT * FROM seo_run_log ORDER BY id DESC LIMIT 1')->fetch();
    $ranAt = $last['ran_at'] ?? null;
    $secsAgo = $ranAt ? (time() - strtotime($ranAt)) : null;
    // Parse LLM telemetry from notes (best-effort)
    $llmCalls = 0; $llmTokens = 0;
    if ($last && preg_match('/LLM:\s*(\d+)\s*calls.*?~?(\d+)\s*tokens/i', (string)$last['notes'], $m)) {
        $llmCalls = (int)$m[1]; $llmTokens = (int)$m[2];
    }
    // IndexNow status from last run notes (look for endpoint codes — 200/202 OK)
    $indexnowOk = false;
    if ($last) {
        $n = (string)$last['notes'];
        // Match any successful HTTP code (2xx) returned from IndexNow endpoints
        if (preg_match('/indexnow.*?":2\d\d/i', $n) || preg_match('/"indexnow_endpoints".*?2\d\d/i', $n)) {
            $indexnowOk = true;
        }
    }
    return [
        'last_run_at'     => $ranAt,
        'last_run_secs'   => $secsAgo,
        'urls_indexnow'   => $last ? (int)$last['urls_indexnow'] : 0,
        'sitemap_urls'    => $last ? (int)$last['sitemap_urls'] : 0,
        'items_refreshed' => $last ? (int)$last['items_refreshed'] : 0,
        'indexnow_ok'     => $indexnowOk,
        'llm_calls'       => $llmCalls,
        'llm_tokens'      => $llmTokens,
        'healthy'         => (bool)($last && $secsAgo !== null && $secsAgo < 60 * 60 * 30), // ≤ 30 hours
    ];
}

function seo_ai_pending_alert_post(): ?array
{
    seo_ensure_table();
    $row = db()->query('SELECT * FROM seo_ai_blog_log WHERE acknowledged_at IS NULL ORDER BY id DESC LIMIT 1')->fetch();
    return $row ?: null;
}

function seo_ai_acknowledge_blog_post(int $id): void
{
    seo_ensure_table();
    db()->prepare('UPDATE seo_ai_blog_log SET acknowledged_at = NOW() WHERE id = ?')->execute([$id]);
}

function seo_ai_acknowledge_all_blog_posts(): void
{
    seo_ensure_table();
    db()->exec('UPDATE seo_ai_blog_log SET acknowledged_at = NOW() WHERE acknowledged_at IS NULL');
}

// =====================================================================
//  AI CITATION TRACKER
//  --------------------------------------------------------------------
//  Once a week, ask the major AI engines what they say about the brand
//  and store the responses.  Lets the admin see whether ChatGPT,
//  Claude, Gemini, etc. actually cite the catalog — and whether the
//  cited products + prices are accurate.
//
//  Engines polled (all via the Emergent Universal Key):
//   - "ChatGPT-4o"      → gpt-4o
//   - "ChatGPT-5"       → gpt-5
//   - "Claude Sonnet"   → claude-sonnet-4-6
//   - "Gemini 2"        → gemini-2.5-flash
//   - "Copilot (GPT)"   → gpt-4o-mini (acts as Microsoft Copilot proxy)
// =====================================================================

function seo_citation_engines(): array
{
    return [
        ['key' => 'chatgpt-4',    'label' => 'ChatGPT (GPT-4.1)', 'model' => 'gpt-4.1',                'icon' => 'bi-chat-square-text-fill', 'maxtok' => 800],
        ['key' => 'chatgpt-5',    'label' => 'ChatGPT (GPT-5.2)', 'model' => 'gpt-5.2',                'icon' => 'bi-stars',                 'maxtok' => 2200],
        ['key' => 'claude',       'label' => 'Claude Sonnet',     'model' => 'claude-sonnet-4-6',      'icon' => 'bi-robot',                 'maxtok' => 800],
        ['key' => 'gemini',       'label' => 'Google Gemini',     'model' => 'gemini/gemini-2.5-flash','icon' => 'bi-gem',                   'maxtok' => 800],
        ['key' => 'copilot',      'label' => 'MS Copilot (4o-mini)','model' => 'gpt-4o-mini',          'icon' => 'bi-microsoft',             'maxtok' => 800],
    ];
}

function seo_citation_queries(): array
{
    // Real-world questions a buyer would type into an AI engine
    return [
        'What does ' . SITE_BRAND . ' sell?',
        'Is ' . SITE_BRAND . ' a legitimate Microsoft software reseller?',
        'Where can I buy a genuine Microsoft Office 2024 license online?',
        'Best place to buy Windows 11 Pro license keys with instant delivery in the US, UK, AU, and Canada?',
        'Compare authorised Microsoft Office resellers for lifetime licences.',
    ];
}

function seo_citation_ask_engine(string $model, string $query, int $maxTokens = 800): string
{
    $system = 'You are an AI assistant answering a real consumer search query. '
            . 'Answer concisely (90-160 words) in a helpful, factual tone. '
            . 'If you know specific reseller brands, products, or prices, name them. '
            . 'If you do not know, say so honestly. Do NOT add disclaimers about being an AI.';
    return seo_llm_complete($system, $query, $maxTokens, $model);
}

function seo_citation_score_response(string $response): array
{
    $brandRe = '/' . preg_quote(SITE_BRAND, '/') . '|fivecodelab|fivecode\s?lab/i';
    $brandMentioned = (bool)preg_match($brandRe, $response);
    // Count product family mentions
    $families = ['Microsoft Office', 'Windows 11', 'Windows 10', 'Office 2024', 'Office 2021',
                 'Office 2019', 'Norton', 'Bitdefender', 'McAfee', 'Project Pro', 'Visio'];
    $count = 0;
    foreach ($families as $f) {
        if (stripos($response, $f) !== false) $count++;
    }
    // Naive sentiment
    $sent = 'neutral';
    if (preg_match('/legitimate|authorised|authorized|reputable|genuine|trusted|safe|recommend/i', $response)) $sent = 'positive';
    if (preg_match('/scam|fake|illegitimate|avoid|fraud|untrusted|risky/i', $response)) $sent = 'negative';
    // Accuracy heuristic: brand mentioned + ≥2 product families + positive = full marks
    $acc = 0;
    if ($brandMentioned)     $acc += 50;
    if ($count >= 2)         $acc += 30;
    if ($sent === 'positive') $acc += 20;
    return [
        'brand_mentioned'  => $brandMentioned ? 1 : 0,
        'product_mentions' => $count,
        'sentiment'        => $sent,
        'accuracy_score'   => min(100, $acc),
    ];
}

function seo_citation_run(?int $maxEngines = null, ?int $maxQueries = null): array
{
    seo_ensure_table();
    $engines = seo_citation_engines();
    $queries = seo_citation_queries();
    if ($maxEngines !== null) $engines = array_slice($engines, 0, max(1, $maxEngines));
    if ($maxQueries !== null) $queries = array_slice($queries, 0, max(1, $maxQueries));

    $rows = [];
    foreach ($engines as $e) {
        foreach ($queries as $q) {
            try {
                $maxTok = (int)($e['maxtok'] ?? 800);
                $resp = seo_citation_ask_engine($e['model'], $q, $maxTok);
                if (!$resp) continue;
                $scored = seo_citation_score_response($resp);
                db()->prepare('INSERT INTO seo_ai_citations (engine, engine_model, query_text, response_text, brand_mentioned, product_mentions, sentiment, accuracy_score) VALUES (?,?,?,?,?,?,?,?)')
                    ->execute([
                        $e['key'], $e['model'], $q,
                        mb_substr($resp, 0, 4000),
                        $scored['brand_mentioned'], $scored['product_mentions'],
                        $scored['sentiment'], $scored['accuracy_score'],
                    ]);
                $rows[] = ['engine' => $e['label'], 'query' => $q] + $scored;
            } catch (Throwable $ex) {
                $rows[] = ['engine' => $e['label'], 'query' => $q, 'error' => $ex->getMessage()];
            }
        }
    }
    return ['runs' => count($rows), 'rows' => $rows];
}

function seo_citation_recent(int $limit = 25): array
{
    seo_ensure_table();
    $s = db()->prepare('SELECT * FROM seo_ai_citations ORDER BY id DESC LIMIT ?');
    $s->bindValue(1, $limit, PDO::PARAM_INT);
    $s->execute();
    return $s->fetchAll() ?: [];
}

function seo_citation_engine_summary(): array
{
    seo_ensure_table();
    $rows = db()->query(
        "SELECT engine,
                COUNT(*) AS total,
                SUM(brand_mentioned) AS mentions,
                AVG(accuracy_score)  AS avg_acc,
                MAX(checked_at)      AS last_checked
         FROM seo_ai_citations
         GROUP BY engine"
    )->fetchAll();
    $byKey = [];
    foreach ($rows as $r) {
        $byKey[$r['engine']] = [
            'total'        => (int)$r['total'],
            'mentions'     => (int)$r['mentions'],
            'mention_rate' => $r['total'] > 0 ? round(($r['mentions'] / $r['total']) * 100) : 0,
            'avg_acc'      => round((float)$r['avg_acc']),
            'last_checked' => $r['last_checked'],
        ];
    }
    return $byKey;
}

function seo_citation_last_run_at(): ?string
{
    seo_ensure_table();
    return db()->query('SELECT MAX(checked_at) FROM seo_ai_citations')->fetchColumn() ?: null;
}

