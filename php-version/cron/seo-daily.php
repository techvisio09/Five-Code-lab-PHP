<?php
// =====================================================================
//  /cron/seo-daily.php — daily AI-powered SEO/GEO/AEO pipeline
//
//  Schedule with:
//    0 4 * * *  php /app/php-version/cron/seo-daily.php >/var/log/seo-daily.log 2>&1
//
//  CLI-only.  Returns JSON summary of the run to stdout.
// =====================================================================

if (PHP_SAPI !== 'cli') {
    // Allow web invocation only with admin cookie OR matching ?token=
    require_once __DIR__ . '/../includes/functions.php';
    $u = current_user();
    $tok = (string)($_GET['token'] ?? '');
    $expected = (string)(setting_get('seo_cron_token', '') ?: bin2hex(random_bytes(8)));
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
$result = seo_run_daily((int)($_GET['max'] ?? $argv[1] ?? 8));
$result['elapsed_seconds'] = round(microtime(true) - $start, 2);

header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
