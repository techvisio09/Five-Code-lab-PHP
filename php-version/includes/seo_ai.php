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
        CURLOPT_TIMEOUT        => 35,
    ]);
    $body = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
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

    // 6. Log the run
    db()->prepare('INSERT INTO seo_run_log (items_refreshed, urls_indexnow, sitemap_urls, notes) VALUES (?,?,?,?)')
        ->execute([$refreshed, count($indexnowUrls), $sitemapUrls, implode("\n", $notes)]);

    return [
        'items_refreshed'  => $refreshed,
        'urls_indexnow'    => count($indexnowUrls),
        'sitemap_urls'     => $sitemapUrls,
        'llms_full_lines'  => $llmsLines,
        'indexnow_results' => $in,
        'sitemap_pings'    => $pings,
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
