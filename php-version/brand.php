<?php
require_once __DIR__ . '/includes/functions.php';

// Resolve brand from ?slug=microsoft (case-insensitive, hyphens → spaces)
$slug  = trim((string)($_GET['slug'] ?? ''));
$brand = '';
if ($slug !== '') {
    // Match products.brand case-insensitively against a slugged version
    $stmt = db()->prepare("SELECT DISTINCT brand FROM products WHERE LOWER(REPLACE(brand,' ','-')) = LOWER(?) LIMIT 1");
    $stmt->execute([$slug]);
    $brand = (string)($stmt->fetchColumn() ?: '');
}
if ($brand === '') {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

// Products in this brand (active markets only)
$productsStmt = db()->prepare(
    "SELECT * FROM products
     WHERE brand = ? AND " . active_regions_sql_in('region') . "
     ORDER BY reviews DESC, rating DESC, id DESC"
);
$productsStmt->execute([$brand]);
$products = $productsStmt->fetchAll();

// AI articles for any product in this brand
$articles = [];
try {
    $artStmt = db()->prepare(
        "SELECT b.blog_id, b.title, b.region, b.product_name, b.product_slug,
                b.word_count, b.created_at,
                p.image AS product_image
         FROM seo_ai_blog_log b
         LEFT JOIN products p ON p.slug = b.product_slug
         WHERE b.product_slug IN (SELECT slug FROM products WHERE brand = ?)
         ORDER BY b.id DESC LIMIT 50"
    );
    $artStmt->execute([$brand]);
    $articles = $artStmt->fetchAll();
} catch (Throwable $e) { /* schema may not exist yet */ }

$brandSlug = strtolower(str_replace(' ', '-', $brand));
$pageTitle = $brand . ' Products & Articles | ' . SITE_BRAND;
$pageDescription = 'Browse all ' . $brand . ' software products and editorial guides published by ' . SITE_BRAND . ' for buyers in the US, UK, AU, and Canada.';
include __DIR__ . '/includes/header.php';
?>

<style>
.brand-hero {
  background: linear-gradient(135deg, #f8fafc 0%, #e0e7ff 100%);
  border-radius: 18px;
  padding: 42px 38px;
  margin-bottom: 36px;
  border: 1px solid #e5e7eb;
}
[data-bs-theme="dark"] .brand-hero {
  background: linear-gradient(135deg, rgba(15,23,42,.7), rgba(99,102,241,.18));
  border-color: rgba(99,102,241,.30);
}
.brand-hero h1 {
  font-size: 36px; font-weight: 800; margin: 0 0 8px;
  color: #0f172a; letter-spacing: -0.02em;
}
[data-bs-theme="dark"] .brand-hero h1 { color: #e2e8f0; }
.brand-hero .lead { color:#475569; font-size: 17px; max-width: 680px; }
[data-bs-theme="dark"] .brand-hero .lead { color:#cbd5e1; }
.brand-hero .stats { display: flex; gap: 28px; flex-wrap: wrap; margin-top: 18px; }
.brand-hero .stats div { font-size: 13px; color:#64748b; }
.brand-hero .stats strong { font-size: 22px; color:#0070BA; font-weight: 800; display: block; }
.brand-section { margin-bottom: 48px; }
.brand-section h2 { font-weight: 800; font-size: 24px; margin: 0 0 18px; color:#0f172a; letter-spacing:-0.01em; }
[data-bs-theme="dark"] .brand-section h2 { color:#e2e8f0; }
.brand-article-card {
  background:#fff; border:1px solid #e5e7eb; border-radius: 12px;
  padding: 16px; height: 100%; text-decoration: none;
  transition: transform .15s, box-shadow .2s, border-color .15s;
}
[data-bs-theme="dark"] .brand-article-card { background: var(--card-bg); border-color: var(--border); }
.brand-article-card:hover { transform: translateY(-3px); box-shadow: 0 10px 24px rgba(15,23,42,.10); border-color:#3b82f6; }
.brand-article-thumb {
  width: 60px; height: 60px; object-fit: contain; padding: 4px;
  border-radius: 8px; background:#f8fafc; border:1px solid #e5e7eb; flex-shrink: 0;
}
[data-bs-theme="dark"] .brand-article-thumb { background:#1e293b; border-color:#334155; }
.brand-region-pill {
  display:inline-block; padding:2px 7px; border-radius:5px;
  font-size:9.5px; font-weight:700; letter-spacing:.12em;
  background:#ddd6fe; color:#5b21b6;
}
.brand-region-pill[data-r="UK"] { background:#dbeafe; color:#1e40af; }
.brand-region-pill[data-r="AU"] { background:#fef3c7; color:#92400e; }
.brand-region-pill[data-r="CA"] { background:#fee2e2; color:#991b1b; }
</style>

<!-- Breadcrumbs -->
<script type="application/ld+json">{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    {"@type":"ListItem","position":1,"name":"Home","item":"<?= esc(site_url()) ?>/"},
    {"@type":"ListItem","position":2,"name":"Shop","item":"<?= esc(site_url()) ?>/shop.php"},
    {"@type":"ListItem","position":3,"name":"<?= esc($brand) ?>","item":"<?= esc(site_url()) ?>/brand.php?slug=<?= esc($brandSlug) ?>"}
  ]
}</script>

<!-- Brand entity for AI engines (Knowledge Panel + AEO) -->
<script type="application/ld+json">{
  "@context": "https://schema.org",
  "@type": "Brand",
  "name": "<?= esc($brand) ?>",
  "url": "<?= esc(site_url()) ?>/brand.php?slug=<?= esc($brandSlug) ?>",
  "description": "Genuine <?= esc($brand) ?> software available from <?= esc(SITE_BRAND) ?> — authorised reseller serving the US, UK, Australia and Canada."
}</script>

<div class="container py-4" data-testid="brand-page" data-brand="<?= esc($brand) ?>">

  <!-- Breadcrumb -->
  <nav aria-label="breadcrumb" class="mb-3 small">
    <a href="/" class="text-decoration-none">Home</a> ·
    <a href="shop.php" class="text-decoration-none">Shop</a> ·
    <strong><?= esc($brand) ?></strong>
  </nav>

  <!-- Hero -->
  <section class="brand-hero" data-testid="brand-hero">
    <h1><?= esc($brand) ?> Software</h1>
    <p class="lead">Browse every genuine <?= esc($brand) ?> product we sell, plus the editorial guides our team publishes for each market we serve.</p>
    <div class="stats">
      <div><strong data-testid="brand-product-count"><?= count($products) ?></strong> products available</div>
      <div><strong data-testid="brand-article-count"><?= count($articles) ?></strong> articles published</div>
      <div><strong>4</strong> markets served (US · UK · AU · CA)</div>
    </div>
  </section>

  <!-- Articles section -->
  <?php if ($articles): ?>
    <section class="brand-section" data-testid="brand-articles-section">
      <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap">
        <h2 style="margin:0;"><i class="bi bi-journals me-2 text-primary"></i>Articles about <?= esc($brand) ?> products</h2>
        <small class="text-muted">Updated daily by our editorial team</small>
      </div>
      <div class="row g-3">
        <?php foreach ($articles as $a):
          $thumb = (string)($a['product_image'] ?? '');
          $readEst = max(3, (int)round((int)$a['word_count'] / 220));
        ?>
          <div class="col-md-6 col-lg-4">
            <a href="blog-post.php?id=<?= esc($a['blog_id']) ?>" class="brand-article-card d-flex gap-3"
               data-testid="brand-article-<?= esc($a['blog_id']) ?>">
              <?php if ($thumb): ?>
                <img class="brand-article-thumb" src="<?= esc($thumb) ?>" alt="<?= esc($a['product_name']) ?>"
                     loading="lazy"
                     onerror="this.outerHTML='<div class=&quot;brand-article-thumb d-flex align-items-center justify-content-center&quot; style=&quot;color:#a855f7;font-size:22px;&quot;><i class=&quot;bi bi-robot&quot;></i></div>'">
              <?php else: ?>
                <div class="brand-article-thumb d-flex align-items-center justify-content-center" style="color:#a855f7;font-size:22px;"><i class="bi bi-robot"></i></div>
              <?php endif; ?>
              <div class="flex-grow-1" style="min-width:0;">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <span class="brand-region-pill" data-r="<?= esc($a['region']) ?>"><?= esc($a['region']) ?></span>
                  <small class="text-muted"><?= esc(date('M j, Y', strtotime((string)$a['created_at']) ?: time())) ?> · <?= (int)$readEst ?> min read</small>
                </div>
                <div class="fw-semibold" style="color:#1e3a8a;line-height:1.35;font-size:14px;"><?= esc($a['title']) ?></div>
                <div class="text-muted small mt-1" style="font-size:11.5px;"><i class="bi bi-box-seam"></i> <?= esc($a['product_name']) ?></div>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php else: ?>
    <section class="brand-section" data-testid="brand-articles-section-empty">
      <h2><i class="bi bi-journals me-2 text-primary"></i>Articles about <?= esc($brand) ?> products</h2>
      <div class="p-4 text-center text-muted small rounded" style="border:1px dashed var(--border);background:rgba(99,102,241,.04);">
        <i class="bi bi-pencil-square d-block mb-2" style="font-size:30px;color:#94a3b8;"></i>
        New editorial guides are published daily across US · UK · AU · CA. Check back soon.
      </div>
    </section>
  <?php endif; ?>

  <!-- Products -->
  <?php if ($products): ?>
    <section class="brand-section" data-testid="brand-products-section">
      <h2><i class="bi bi-bag-check me-2 text-success"></i><?= esc($brand) ?> products</h2>
      <div class="row g-4">
        <?php foreach ($products as $p): ?>
          <div class="col-xl-3 col-lg-4 col-sm-6"><?= render_product_card($p) ?></div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
