<?php
// =====================================================================
//  /cron/citation-weekly.php — runs the AI citation tracker
//
//  Schedule with:
//    0 6 * * 0  /usr/bin/php /app/php-version/cron/citation-weekly.php
//        >/var/log/citation-weekly.log 2>&1
//
//  Polls the 5 major AI engines (ChatGPT 4o & 5, Claude, Gemini, Copilot)
//  with 5 buyer-style queries and stores everything in seo_ai_citations
//  for the admin dashboard to surface.
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
$result = seo_citation_run();
$result['elapsed_seconds'] = round(microtime(true) - $start, 2);

header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
