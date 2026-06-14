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
