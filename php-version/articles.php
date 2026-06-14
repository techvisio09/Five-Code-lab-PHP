<?php
require_once __DIR__ . '/includes/functions.php';

// Filters
$validRegions = ['US', 'UK', 'AU', 'CA'];
$region = strtoupper(trim((string)($_GET['region'] ?? '')));
if (!in_array($region, $validRegions, true)) $region = '';

$q = trim((string)($_GET['q'] ?? ''));
$q = mb_substr($q, 0, 80);

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;

// Build the query
$where  = [];
$params = [];
if ($region !== '') {
    $where[] = 'b.region = ?';
    $params[] = $region;
}
if ($q !== '') {
    $where[] = '(b.title LIKE ? OR b.product_name LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Total + per-region counts (for the filter chips)
$total = 0;
$perRegion = ['US' => 0, 'UK' => 0, 'AU' => 0, 'CA' => 0];
try {
    if ($where) {
        $cs = db()->prepare("SELECT COUNT(*) FROM seo_ai_blog_log b $whereSql");
        $cs->execute($params);
        $total = (int)$cs->fetchColumn();
    } else {
        $total = (int)db()->query('SELECT COUNT(*) FROM seo_ai_blog_log')->fetchColumn();
    }
    // Always show all-region totals for chip counts
    $totalAll = (int)db()->query('SELECT COUNT(*) FROM seo_ai_blog_log')->fetchColumn();
    foreach (db()->query('SELECT region, COUNT(*) c FROM seo_ai_blog_log GROUP BY region') as $r) {
        if (isset($perRegion[$r['region']])) $perRegion[$r['region']] = (int)$r['c'];
    }
} catch (Throwable $e) {
    $total = 0; $totalAll = 0;
}

$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

// Fetch this page
$articles = [];
try {
    $sql = "SELECT b.blog_id, b.title, b.region, b.product_name, b.product_slug,
                   b.word_count, b.internal_links, b.created_at,
                   p.image AS product_image, p.brand AS product_brand
            FROM seo_ai_blog_log b
            LEFT JOIN products p ON p.slug = b.product_slug
            $whereSql
            ORDER BY b.id DESC LIMIT $perPage OFFSET $offset";
    $stmt = db()->prepare($sql);
    foreach ($params as $i => $val) $stmt->bindValue($i + 1, $val, PDO::PARAM_STR);
    $stmt->execute();
    $articles = $stmt->fetchAll();
} catch (Throwable $e) { /* table may not exist */ }

$pageTitle       = ($q ? '"' . $q . '" · ' : '') . 'AI-Published Articles — '
                 . ($region ?: 'All Markets') . ' | ' . SITE_BRAND;
$pageDescription = 'Browse ' . number_format($totalAll) . ' editorial-style buying guides published by '
                 . SITE_BRAND . ' for the US, UK, Australia and Canada — one new product article per market every day.';
include __DIR__ . '/includes/header.php';

// Helper to preserve filter state in pagination links
$qsBase = function(array $extra = []) use ($region, $q) {
    $qs = array_filter(['region' => $region, 'q' => $q] + $extra, fn($v) => $v !== '');
    return $qs ? '?' . http_build_query($qs) : '';
};
?>

<style>
.articles-hero {
  background: linear-gradient(135deg, #0070BA 0%, #003087 100%);
  color:#fff; border-radius: 20px;
  padding: 42px 38px; margin-bottom: 32px;
  position: relative; overflow: hidden;
}
.articles-hero::before {
  content:''; position:absolute; inset:0;
  background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" viewBox="0 0 60 60"><path d="M0 30 L60 30 M30 0 L30 60" stroke="rgba(255,255,255,.04)" stroke-width="1"/></svg>');
  pointer-events: none;
}
.articles-hero > * { position: relative; z-index:1; }
.articles-hero h1 { font-size: 38px; font-weight: 800; margin: 0 0 8px; letter-spacing:-0.02em; }
.articles-hero p  { font-size: 16px; opacity: .92; max-width: 680px; margin: 0; }
.articles-hero .meta { display: flex; gap: 26px; flex-wrap: wrap; margin-top: 16px; font-size: 13px; opacity: .85; }
.articles-hero .meta strong { color:#FFC439; font-weight:700; }

.articles-search {
  background:#fff; border:1px solid #e5e7eb; border-radius:14px;
  padding: 16px; margin-bottom: 24px;
  box-shadow: 0 2px 8px rgba(15,23,42,.04);
}
[data-bs-theme="dark"] .articles-search { background: var(--card-bg); border-color: var(--border); }
.articles-search input[type="search"] {
  border:1px solid #cbd5e1; border-radius: 10px; padding: 10px 14px;
  width: 100%; font-size: 14px; background: transparent; color: inherit;
}
.articles-search input[type="search"]:focus {
  border-color:#0070BA; box-shadow: 0 0 0 3px rgba(0,112,186,.15); outline: none;
}

.articles-chips { display: flex; gap: 8px; flex-wrap: wrap; }
.articles-chip {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 8px 16px; border-radius: 999px;
  border: 1.5px solid #e5e7eb; background:#fff;
  font-size: 13px; font-weight: 600; color:#0f172a;
  text-decoration: none; transition: all .15s;
}
.articles-chip:hover { border-color:#0070BA; color:#0070BA; transform: translateY(-1px); }
.articles-chip.active {
  background: linear-gradient(135deg,#0070BA,#003087); color:#fff; border-color: transparent;
  box-shadow: 0 4px 14px rgba(0,48,135,.30);
}
.articles-chip .count {
  background: rgba(0,0,0,.08); padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 700;
}
.articles-chip.active .count { background: rgba(255,255,255,.22); }
[data-bs-theme="dark"] .articles-chip {
  background: var(--card-bg); border-color: var(--border); color: var(--text);
}

.article-card {
  background:#fff; border:1px solid #e5e7eb; border-radius: 14px;
  overflow: hidden; height: 100%;
  text-decoration: none; color: inherit;
  transition: transform .15s, box-shadow .2s, border-color .15s;
  display: flex; flex-direction: column;
}
[data-bs-theme="dark"] .article-card { background: var(--card-bg); border-color: var(--border); }
.article-card:hover { transform: translateY(-4px); box-shadow: 0 14px 28px rgba(15,23,42,.10); border-color:#0070BA; color: inherit; }
.article-card-thumb {
  width: 100%; aspect-ratio: 16/9; object-fit: cover;
  background:#f8fafc;
}
.article-card-thumb.fallback {
  display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg,#a855f7,#7c3aed); color:#fff; font-size: 44px;
}
.article-card-body { padding: 16px; display: flex; flex-direction: column; flex: 1; }
.article-region-pill {
  display: inline-block; padding: 3px 9px; border-radius: 6px;
  font-size: 10px; font-weight: 700; letter-spacing: .12em;
  background:#ddd6fe; color:#5b21b6;
}
.article-region-pill[data-r="UK"] { background:#dbeafe; color:#1e40af; }
.article-region-pill[data-r="AU"] { background:#fef3c7; color:#92400e; }
.article-region-pill[data-r="CA"] { background:#fee2e2; color:#991b1b; }
.article-card-title {
  font-weight: 700; color:#0f172a; line-height: 1.35; font-size: 15.5px;
  margin: 6px 0 8px;
}
[data-bs-theme="dark"] .article-card-title { color:#e2e8f0; }
.article-card-meta { color:#64748b; font-size: 12px; margin-top: auto; display: flex; align-items: center; gap: 6px; }

.articles-empty {
  text-align: center; padding: 48px 24px;
  border: 1px dashed #cbd5e1; border-radius: 14px;
  color:#64748b;
}
</style>

<!-- JSON-LD ItemList for AI engines / search engines -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "CollectionPage",
  "@id": "<?= esc(site_url()) ?>/articles.php",
  "url": "<?= esc(site_url()) ?>/articles.php",
  "name": "All AI-Published Articles · <?= esc(SITE_BRAND) ?>",
  "description": <?= json_encode($pageDescription) ?>,
  "publisher": {"@id": "<?= esc(site_url()) ?>/#organization"},
  "isPartOf": {"@id": "<?= esc(site_url()) ?>/#website"},
  "mainEntity": {
    "@type": "ItemList",
    "numberOfItems": <?= (int)$total ?>,
    "itemListElement": [
      <?php $i = 0; foreach ($articles as $a): $i++; ?>
        {
          "@type": "ListItem",
          "position": <?= $i ?>,
          "url": "<?= esc(site_url()) ?>/blog-post.php?id=<?= esc($a['blog_id']) ?>",
          "name": <?= json_encode($a['title']) ?>
        }<?= $i < count($articles) ? ',' : '' ?>
      <?php endforeach; ?>
    ]
  }
}
</script>

<!-- BreadcrumbList -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    {"@type":"ListItem","position":1,"name":"Home","item":"<?= esc(site_url()) ?>/"},
    {"@type":"ListItem","position":2,"name":"Articles","item":"<?= esc(site_url()) ?>/articles.php"}
  ]
}
</script>

<div class="container py-4" data-testid="articles-index">

  <!-- Breadcrumb -->
  <nav aria-label="breadcrumb" class="mb-3 small">
    <a href="/" class="text-decoration-none">Home</a> · <strong>Articles</strong>
  </nav>

  <!-- Hero -->
  <section class="articles-hero" data-testid="articles-hero">
    <h1>Editorial Articles &amp; Buying Guides</h1>
    <p>Hand-crafted reviews and guides for every product we sell — written for buyers in the US, UK, Australia and Canada. One new article per market, every single day.</p>
    <div class="meta">
      <div><strong><?= number_format($totalAll) ?></strong> articles published</div>
      <div><strong>4</strong> markets covered (US · UK · AU · CA)</div>
      <div><strong>Updated daily</strong> by our editorial team</div>
    </div>
  </section>

  <!-- Search -->
  <form method="get" action="articles.php" class="articles-search" data-testid="articles-search-form">
    <div class="d-flex gap-2 align-items-center">
      <i class="bi bi-search text-muted" style="font-size:18px;"></i>
      <input type="search" name="q" placeholder="Search by article title or product name…"
             value="<?= esc($q) ?>" data-testid="articles-search-input" aria-label="Search articles">
      <?php if ($region): ?><input type="hidden" name="region" value="<?= esc($region) ?>"><?php endif; ?>
      <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold" data-testid="articles-search-submit">Search</button>
      <?php if ($q): ?>
        <a href="articles.php<?= $region ? '?region=' . esc($region) : '' ?>" class="btn btn-outline-secondary rounded-pill px-3" data-testid="articles-search-clear" title="Clear search"><i class="bi bi-x"></i></a>
      <?php endif; ?>
    </div>
  </form>

  <!-- Region filter chips -->
  <div class="articles-chips mb-4" data-testid="articles-region-chips">
    <a href="articles.php<?= $q ? '?q=' . urlencode($q) : '' ?>"
       class="articles-chip <?= $region === '' ? 'active' : '' ?>"
       data-testid="articles-chip-all">
      <span>All markets</span>
      <span class="count"><?= number_format($totalAll) ?></span>
    </a>
    <?php foreach (['US' => 'United States', 'UK' => 'United Kingdom', 'AU' => 'Australia', 'CA' => 'Canada'] as $code => $label):
      $href = 'articles.php?region=' . $code . ($q ? '&q=' . urlencode($q) : '');
    ?>
      <a href="<?= esc($href) ?>"
         class="articles-chip <?= $region === $code ? 'active' : '' ?>"
         data-testid="articles-chip-<?= $code ?>">
        <span class="article-region-pill" data-r="<?= $code ?>" style="font-size:9px;padding:2px 5px;<?= $region === $code ? 'background:rgba(255,255,255,.20);color:#fff;' : '' ?>"><?= $code ?></span>
        <span><?= esc($label) ?></span>
        <span class="count"><?= number_format((int)$perRegion[$code]) ?></span>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Results header -->
  <?php if ($q || $region): ?>
    <p class="text-muted small mb-3" data-testid="articles-result-summary">
      <i class="bi bi-funnel me-1"></i>
      <?php if ($q && $region): ?>
        Showing <strong><?= number_format($total) ?></strong> result<?= $total === 1 ? '' : 's' ?> for <strong>"<?= esc($q) ?>"</strong> in <strong><?= esc($region) ?></strong>
      <?php elseif ($q): ?>
        Showing <strong><?= number_format($total) ?></strong> result<?= $total === 1 ? '' : 's' ?> for <strong>"<?= esc($q) ?>"</strong>
      <?php else: ?>
        Showing all <strong><?= number_format($total) ?></strong> article<?= $total === 1 ? '' : 's' ?> for <strong><?= esc($region) ?></strong>
      <?php endif; ?>
    </p>
  <?php endif; ?>

  <!-- Article grid -->
  <?php if (!$articles): ?>
    <div class="articles-empty" data-testid="articles-empty">
      <i class="bi bi-journals d-block mb-3" style="font-size:48px;color:#cbd5e1;"></i>
      <h3 class="h5 fw-bold mb-2">No articles match those filters yet</h3>
      <p class="mb-3">New editorial content goes live daily. Try clearing filters or check back tomorrow.</p>
      <a href="articles.php" class="btn btn-primary rounded-pill px-4">Browse all articles</a>
    </div>
  <?php else: ?>
    <div class="row g-3" data-testid="articles-grid">
      <?php foreach ($articles as $a):
        $thumb   = (string)($a['product_image'] ?? '');
        $readEst = max(3, (int)round((int)$a['word_count'] / 220));
        $created = strtotime((string)$a['created_at']) ?: time();
      ?>
        <div class="col-md-6 col-lg-4">
          <a href="blog-post.php?id=<?= esc($a['blog_id']) ?>" class="article-card" data-testid="article-card-<?= esc($a['blog_id']) ?>">
            <?php if ($thumb): ?>
              <img class="article-card-thumb" src="<?= esc($thumb) ?>"
                   alt="<?= esc($a['title']) ?> — read the full article"
                   loading="lazy" decoding="async"
                   onerror="this.outerHTML='<div class=&quot;article-card-thumb fallback&quot;><i class=&quot;bi bi-robot&quot;></i></div>'">
            <?php else: ?>
              <div class="article-card-thumb fallback"><i class="bi bi-robot"></i></div>
            <?php endif; ?>
            <div class="article-card-body">
              <div class="d-flex align-items-center gap-2 mb-1">
                <span class="article-region-pill" data-r="<?= esc($a['region']) ?>"><?= esc($a['region']) ?></span>
                <small class="text-muted"><?= esc(date('M j, Y', $created)) ?></small>
                <small class="text-muted ms-auto"><i class="bi bi-clock"></i> <?= (int)$readEst ?> min</small>
              </div>
              <h2 class="article-card-title"><?= esc($a['title']) ?></h2>
              <div class="article-card-meta">
                <i class="bi bi-box-seam"></i>
                <span class="text-truncate"><?= esc($a['product_name']) ?></span>
                <?php if (!empty($a['product_brand'])): ?>
                  <span class="ms-auto badge text-bg-light"><?= esc($a['product_brand']) ?></span>
                <?php endif; ?>
              </div>
            </div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
      <nav class="d-flex justify-content-center mt-4" data-testid="articles-pagination">
        <ul class="pagination">
          <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="articles.php<?= $qsBase(['page' => max(1, $page-1)]) ?>" data-testid="articles-page-prev"><i class="bi bi-chevron-left"></i></a>
          </li>
          <?php $window = 2;
          for ($i = 1; $i <= $totalPages; $i++):
            if ($i === 1 || $i === $totalPages || ($i >= $page - $window && $i <= $page + $window)): ?>
              <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="articles.php<?= $qsBase(['page' => $i]) ?>" data-testid="articles-page-<?= $i ?>"><?= $i ?></a>
              </li>
            <?php elseif ($i === $page - $window - 1 || $i === $page + $window + 1): ?>
              <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php endif;
          endfor; ?>
          <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="articles.php<?= $qsBase(['page' => min($totalPages, $page+1)]) ?>" data-testid="articles-page-next"><i class="bi bi-chevron-right"></i></a>
          </li>
        </ul>
      </nav>
    <?php endif; ?>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
