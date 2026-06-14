#!/bin/bash
export EMERGENT_LLM_KEY="sk-emergent-8Ad362c4681F5B58f7"
# ============================================================
# Emergent preview launcher — serves the PHP store on port 3000
# (replaces the React dev server; supervisor runs this via `yarn start`)
# Self-healing: starts MariaDB if needed and seeds the database
# on a fresh pod. NOT needed on normal PHP hosting (cPanel etc.)
# ============================================================
set -e

# 1) Ensure MariaDB is running
if ! mysqladmin ping --silent 2>/dev/null; then
  mkdir -p /run/mysqld
  chown mysql:mysql /run/mysqld 2>/dev/null || true
  (mysqld_safe --skip-grant-tables=0 >/dev/null 2>&1 &)
  for i in $(seq 1 30); do
    mysqladmin ping --silent 2>/dev/null && break
    sleep 1
  done
fi

# 2) Seed the database if missing (fresh pod)
if ! mysql -uroot -e "USE ucode_store" 2>/dev/null; then
  mysql -uroot -e "CREATE DATABASE IF NOT EXISTS ucode_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
  mysql -uroot ucode_store < /app/php-version/database.sql
  echo "[start.sh] Database ucode_store created and seeded"
fi

# 2b) Idempotent schema migrations (safe on every boot)
mysql -uroot ucode_store -e "ALTER TABLE products ADD COLUMN IF NOT EXISTS activation_url VARCHAR(500) DEFAULT NULL" 2>/dev/null || true
mysql -uroot ucode_store -e "ALTER TABLE products ADD COLUMN IF NOT EXISTS install_guide_url VARCHAR(500) DEFAULT NULL" 2>/dev/null || true

# 2c) Make sure all 4 AI-Auto-Blogger target markets exist + are active
mysql -uroot ucode_store -e "INSERT IGNORE INTO regions (code, name, currency, currency_symbol, tax_rate, active) VALUES ('AU', 'Australia', 'AUD', 'A\$', 0.1000, 1)" 2>/dev/null || true
mysql -uroot ucode_store -e "UPDATE regions SET active=1 WHERE code IN ('US','UK','AU','CA')" 2>/dev/null || true

# Visitor analytics — one row per public page view from a real human (bots/admin skipped at the PHP layer).
mysql -uroot ucode_store -e "CREATE TABLE IF NOT EXISTS visitor_log (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL DEFAULT '',
    ip_hash VARCHAR(64) NOT NULL DEFAULT '',
    user_agent VARCHAR(500) NOT NULL DEFAULT '',
    os VARCHAR(40) NOT NULL DEFAULT 'Unknown',
    browser VARCHAR(40) NOT NULL DEFAULT 'Unknown',
    device VARCHAR(20) NOT NULL DEFAULT 'Desktop',
    country VARCHAR(8) NOT NULL DEFAULT '',
    page_url VARCHAR(255) NOT NULL DEFAULT '',
    referer VARCHAR(255) NOT NULL DEFAULT '',
    visited_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_visited (visited_at),
    KEY idx_session (session_id),
    KEY idx_os (os),
    KEY idx_device (device)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci" 2>/dev/null || true

# 3) Export integration keys from the backend .env (preview convenience)
ENVF=/app/backend/.env
if [ -f "$ENVF" ]; then
  for K in STRIPE_API_KEY EMERGENT_LLM_KEY RESEND_API_KEY SENDER_EMAIL; do
    V=$(grep "^${K}=" "$ENVF" | head -1 | cut -d'=' -f2- | sed 's/^"//; s/"$//')
    [ -n "$V" ] && export "$K=$V"
  done
fi

# 4) Background daily AI SEO runner — runs the full pipeline (AI meta refresh,
#    sitemap regeneration, IndexNow pings, and the once-a-day AI auto-blogger)
#    once at boot (after a 90-second warmup) and then every 24 hours.
#    The PHP function is idempotent + has its own per-day caps, so re-running
#    is always safe.
(
  sleep 90
  while true; do
    /usr/bin/php /app/php-version/cron/seo-daily.php >> /var/log/seo-daily.log 2>&1
    sleep 86400
  done
) &

# 4b) Weekly AI citation tracker — every 7 days asks ChatGPT/Claude/Gemini/Copilot
#     what they say about the brand and stores the answers for the admin
#     dashboard.  Idempotent (just inserts a new run; never blocks).
(
  sleep 300
  while true; do
    /usr/bin/php /app/php-version/cron/citation-weekly.php >> /var/log/citation-weekly.log 2>&1
    sleep 604800
  done
) &

# 4c) Weekly anti-scraper / content-clone watchdog — asks AI engines if any
#     third-party site has scraped our last 10 AI-written articles.
(
  sleep 600
  while true; do
    /usr/bin/php /app/php-version/cron/scrape-weekly.php >> /var/log/scrape-weekly.log 2>&1
    sleep 604800
  done
) &

# 5) Serve the PHP store on port 3000
exec env PHP_CLI_SERVER_WORKERS=8 php -S 0.0.0.0:3000 -t /app/php-version /app/php-version/router.php
