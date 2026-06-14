<?php
require_once __DIR__ . '/includes/functions.php';

$id = $_GET['id'] ?? '';
$post = null;
if ($id) {
    $stmt = db()->prepare('SELECT * FROM blog_posts WHERE id = ?');
    $stmt->execute([$id]);
    $post = $stmt->fetch();
}
$pageTitle = ($post ? $post['title'] : 'Post Not Found') . ' | ' . SITE_BRAND;
if ($post) {
    $plainBody = trim(strip_tags($post['content']));
    $pageDescription = mb_substr($plainBody, 0, 155) . '…';
    $ogType = 'article';
    $canonicalUrl = site_url() . '/blog-post.php?id=' . (int)$post['id'];
    if (!empty($post['image'])) $ogImage = $post['image'];

    // Word count → readingTime + wordCount for AI search engines and Article schema
    $wordCount = max(1, str_word_count($plainBody));
    $readMin   = max(1, (int)round($wordCount / 220));

    // Article JSON-LD — drives Google "Top Stories" eligibility + AI summarisation
    $jsonLd = [
        '@context'         => 'https://schema.org',
        '@type'            => 'Article',
        '@id'              => $canonicalUrl . '#article',
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonicalUrl],
        'headline'         => mb_substr($post['title'], 0, 110),
        'name'             => $post['title'],
        'description'      => $pageDescription,
        'image'            => array_values(array_filter([$post['image'] ?? null])),
        'datePublished'    => date('c', strtotime((string)$post['date']) ?: time()),
        'dateModified'     => date('c', strtotime((string)$post['date']) ?: time()),
        'wordCount'        => $wordCount,
        'timeRequired'     => 'PT' . $readMin . 'M',
        'inLanguage'       => 'en-US',
        'isAccessibleForFree' => true,
        'articleSection'   => 'Software Guides',
        'keywords'         => 'Microsoft Office, Windows, antivirus, software licenses, ' . SITE_BRAND,
        'author' => [
            '@type' => 'Organization',
            'name'  => SITE_BRAND,
            'url'   => site_url() . '/',
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name'  => SITE_BRAND,
            'url'   => site_url() . '/',
            'logo'  => [
                '@type'  => 'ImageObject',
                'url'    => site_url() . '/assets/images/fivecodelab-logo-512.png',
                'width'  => 512,
                'height' => 512,
            ],
        ],
    ];

    $jsonLdBreadcrumb = [
        '@context' => 'https://schema.org',
        '@type'    => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => site_url() . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => site_url() . '/blog.php'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $post['title']],
        ],
    ];
} else {
    http_response_code(404);
    $noIndex = true;
}

include __DIR__ . '/includes/header.php';
?>
<div class="container py-5" style="max-width: 800px;">
  <?php if ($post): ?>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
        <li class="breadcrumb-item"><a href="blog.php">Blog</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= esc($post['title']) ?></li>
      </ol>
    </nav>
    <article itemscope itemtype="https://schema.org/Article">
      <meta itemprop="datePublished" content="<?= esc(date('c', strtotime((string)$post['date']) ?: time())) ?>">
      <h1 class="fw-bold" itemprop="headline"><?= esc($post['title']) ?></h1>
      <p class="text-secondary small">
        <span itemprop="author" itemscope itemtype="https://schema.org/Organization"><span itemprop="name"><?= esc(SITE_BRAND) ?></span></span>
        · <time datetime="<?= esc(date('Y-m-d', strtotime((string)$post['date']) ?: time())) ?>"><?= esc($post['date']) ?></time>
        · <?= esc($post['read_time']) ?>
      </p>
      <?php if (!empty($post['image'])): ?>
        <img src="<?= esc($post['image']) ?>"
             class="img-fluid rounded mb-4 w-100 object-fit-cover"
             style="max-height:380px;"
             alt="<?= esc($post['title']) ?> — illustration · <?= esc(SITE_BRAND) ?> software guide"
             title="<?= esc($post['title']) ?>"
             width="800" height="380"
             loading="eager" fetchpriority="high"
             itemprop="image">
      <?php endif; ?>
      <div class="post-content" itemprop="articleBody"><?= $post['content'] /* trusted HTML seeded from database.sql */ ?></div>
    </article>

    <?php
    // ============== INTERNAL LINKING (SEO boost) ==============
    // Pull 3-4 related posts (same category if possible) + 3 top products
    // for the same market.  Internal links pass link equity and help AI
    // engines map relationships across the site.
    try {
        $relatedPosts = db()->prepare(
            "SELECT id, title, image, read_time, date
             FROM blog_posts
             WHERE id <> ?
             ORDER BY STR_TO_DATE(date, '%b %e, %Y') DESC, id DESC
             LIMIT 4"
        );
        $relatedPosts->execute([$post['id']]);
        $relatedPosts = $relatedPosts->fetchAll();
        $topProducts = db()->query(
            "SELECT slug, name, price, image, platform
             FROM products
             WHERE " . active_regions_sql_in('region') . "
             ORDER BY reviews DESC, rating DESC LIMIT 3"
        )->fetchAll();
    } catch (Throwable $e) {
        $relatedPosts = $topProducts = [];
    }
    ?>

    <?php if ($relatedPosts): ?>
      <section class="mt-5" aria-labelledby="related-articles-heading">
        <h2 id="related-articles-heading" class="h5 fw-bold mb-3"><i class="bi bi-journals me-2 text-primary"></i>More guides you might like</h2>
        <div class="row g-3">
          <?php foreach ($relatedPosts as $rp): ?>
            <div class="col-md-6 col-lg-3">
              <a href="blog-post.php?id=<?= esc($rp['id']) ?>" class="card h-100 text-decoration-none related-post-card" data-testid="related-post-<?= esc($rp['id']) ?>">
                <?php if (!empty($rp['image'])): ?>
                  <img src="<?= esc($rp['image']) ?>" class="card-img-top" loading="lazy" decoding="async"
                       alt="<?= esc($rp['title']) ?> — read this related article"
                       style="aspect-ratio:16/10;object-fit:cover;">
                <?php endif; ?>
                <div class="card-body p-3">
                  <div class="fw-semibold small" style="color:#1e3a8a;line-height:1.35;"><?= esc($rp['title']) ?></div>
                  <div class="text-muted" style="font-size:11px;margin-top:6px;"><?= esc($rp['date']) ?> · <?= esc($rp['read_time']) ?></div>
                </div>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($topProducts): ?>
      <section class="mt-5" aria-labelledby="top-products-heading">
        <h2 id="top-products-heading" class="h5 fw-bold mb-3"><i class="bi bi-bag-check me-2 text-success"></i>Best-selling products mentioned in this guide</h2>
        <div class="row g-3">
          <?php foreach ($topProducts as $tp): ?>
            <div class="col-md-4">
              <a href="product.php?slug=<?= esc($tp['slug']) ?>" class="card h-100 text-decoration-none related-product-card" data-testid="related-product-<?= esc($tp['slug']) ?>">
                <?php if (!empty($tp['image'])): ?>
                  <img src="<?= esc($tp['image']) ?>" class="card-img-top p-3" loading="lazy" decoding="async"
                       alt="<?= esc($tp['name']) ?> — buy from <?= esc(SITE_BRAND) ?>"
                       style="aspect-ratio:1/1;object-fit:contain;background:#f8fafc;">
                <?php endif; ?>
                <div class="card-body p-3">
                  <div class="fw-semibold small" style="color:#1e3a8a;line-height:1.35;"><?= esc($tp['name']) ?></div>
                  <div class="d-flex justify-content-between align-items-center mt-2">
                    <strong style="color:#0070BA;"><?= esc(region_money((float)$tp['price'])) ?></strong>
                    <small class="text-muted"><?= esc($tp['platform']) ?></small>
                  </div>
                </div>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <style>
      .related-post-card, .related-product-card { transition: transform .15s ease, box-shadow .2s ease; border:1px solid #e5e7eb; }
      .related-post-card:hover, .related-product-card:hover { transform: translateY(-3px); box-shadow:0 8px 22px rgba(15,23,42,.10); border-color:#3b82f6; }
    </style>

    <hr class="my-4">
    <div class="card p-4 text-center">
      <h5 class="fw-bold">Ready to upgrade your software?</h5>
      <p class="small text-secondary">Genuine Microsoft licenses with instant delivery.</p>
      <a href="shop.php" class="btn btn-primary rounded-pill px-4 mx-auto">Shop Now</a>
    </div>
  <?php else: ?>
    <div class="text-center py-5">
      <h1 class="fw-bold">Post not found</h1>
      <a href="blog.php" class="btn btn-primary rounded-pill px-4 mt-3">Back to Blog</a>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
