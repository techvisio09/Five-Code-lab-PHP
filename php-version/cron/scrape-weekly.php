<?php
// =====================================================================
//  /cron/scrape-weekly.php — anti-scraper / content-clone watchdog
//
//  Schedule:
//    0 7 * * 1  /usr/bin/php /app/php-version/cron/scrape-weekly.php
//        >/var/log/scrape-weekly.log 2>&1
//
//  Asks an AI engine to find any third-party clones of our most recent
//  AI-written articles.  Logs to seo_ai_scrape_alerts.
// =====================================================================

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/../includes/functions.php';
    $u = function_exists('current_user') ? current_user() : null;
    $tok = (string)($_GET['token'] ?? '');
    $expected = (string)(setting_get('seo_cron_token', '') ?: '');
    if (!$u || ($u['role'] ?? '') !== 'admin') {
        if ($tok === '' || !hash_equals($expected, $tok)) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
    }
}

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/seo_ai.php';

$start = microtime(true);
$result = seo_scrape_run(10);
$result['elapsed_seconds'] = round(microtime(true) - $start, 2);

header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
