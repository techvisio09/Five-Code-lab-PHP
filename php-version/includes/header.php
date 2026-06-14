<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/regions.php';
require_once __DIR__ . '/visitor_track.php';
// Track this public page-view (silently skipped for bots / admin / CLI).
track_visitor();
$co = company_info();                                       // single source of truth
$brandName  = $co['name']  ?: (defined('SITE_BRAND') ? SITE_BRAND : 'Fivecodelab Software');
$brandEmail = $co['email'] ?: (defined('SITE_EMAIL') ? SITE_EMAIL : '');
$brandPhone = $co['phone'] ?: (defined('SITE_PHONE') ? SITE_PHONE : '');
$brandLogo  = $co['logo']  ?: '';
$brandAddress = $co['address'] ?: (defined('SITE_ADDRESS') ? SITE_ADDRESS : '');
$pageTitle = $pageTitle ?? ($brandName . ' | Genuine Microsoft Software');
$cur = current_currency();
$checkoutHeader = $checkoutHeader ?? false;

/* ---- SEO defaults (pages may override before including this header) ---- */
$pageDescription = $pageDescription ?? 'Buy genuine Microsoft Office, Windows and antivirus license keys at up to 81% off. Instant digital delivery, lifetime activation and 24/7 US-based support.';
$script = basename($_SERVER['SCRIPT_NAME'] ?? '');
$noIndex = $noIndex ?? in_array($script, ['cart.php', 'checkout.php', 'login.php', 'register.php', 'account.php', 'admin.php', 'admin-email-preview.php', 'logout.php', 'order-success.php', '404.php'], true);
if (!isset($canonicalUrl)) {
    $canonicalPath = $script === 'index.php' ? '/' : '/' . $script;
    $canonicalSlug = isset($_GET['slug']) && $_GET['slug'] !== '' ? '?slug=' . urlencode($_GET['slug']) : '';
    $canonicalUrl = site_url() . $canonicalPath . $canonicalSlug;
}
$ogImage = $ogImage ?? site_url() . '/assets/images/fivecodelab-og.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <script>
    // Apply saved theme BEFORE styles render — prevents light-mode flicker on every navigation
    (function () { try { document.documentElement.setAttribute('data-bs-theme', localStorage.getItem('uc_theme') || 'light'); } catch (e) {} })();
  </script>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Theme color follows the active palette so mobile browsers tint the chrome to match the brand -->
  <meta name="theme-color" content="#003087" media="(prefers-color-scheme: light)">
  <meta name="theme-color" content="#0A1330" media="(prefers-color-scheme: dark)">
  <meta name="color-scheme" content="light dark">
  <meta name="apple-mobile-web-app-title" content="<?= esc($brandName) ?>">
  <!-- Geo / regional targeting for international SEO -->
  <meta name="geo.region" content="US-CA">
  <meta name="geo.placename" content="Moreno Valley, California">
  <meta name="geo.position" content="33.9425;-117.2297">
  <meta name="ICBM" content="33.9425, -117.2297">
  <!-- Content classification / language -->
  <meta http-equiv="content-language" content="en-US">
  <meta name="author" content="<?= esc($brandName) ?>">
  <meta name="publisher" content="<?= esc($brandName) ?>">
  <meta name="copyright" content="© <?= date('Y') ?> <?= esc($brandName) ?>">
  <meta name="rating" content="general">
  <meta name="referrer" content="origin-when-cross-origin">
  <meta name="format-detection" content="telephone=yes">
  <title><?= esc($pageTitle) ?></title>
  <meta name="description" content="<?= esc($pageDescription) ?>">
  <meta name="robots" content="<?= $noIndex ? 'noindex, nofollow' : 'index, follow' ?>">
  <?php if (isset($pageKeywords)): ?>
  <meta name="keywords" content="<?= esc($pageKeywords) ?>">
  <?php endif; ?>
  <link rel="canonical" href="<?= esc($canonicalUrl) ?>">
  <!-- hreflang: English-only catalog today; x-default + en-US both point at the canonical URL.
       When regional sub-domains/sub-folders go live, additional <link rel="alternate"> rows go here. -->
  <link rel="alternate" hreflang="en-US"    href="<?= esc($canonicalUrl) ?>">
  <link rel="alternate" hreflang="en"       href="<?= esc($canonicalUrl) ?>">
  <link rel="alternate" hreflang="x-default" href="<?= esc($canonicalUrl) ?>">
  <?php if (defined('GOOGLE_SITE_VERIFICATION') && GOOGLE_SITE_VERIFICATION !== ''): ?>
  <meta name="google-site-verification" content="<?= esc(GOOGLE_SITE_VERIFICATION) ?>">
  <?php endif; ?>
  <!-- Open Graph / Twitter -->
  <meta property="og:site_name" content="<?= esc($brandName) ?>">
  <meta property="og:type" content="<?= isset($ogType) ? esc($ogType) : 'website' ?>">
  <meta property="og:title" content="<?= esc($pageTitle) ?>">
  <meta property="og:description" content="<?= esc($pageDescription) ?>">
  <meta property="og:url" content="<?= esc($canonicalUrl) ?>">
  <meta property="og:image" content="<?= esc($ogImage) ?>">
  <meta property="og:image:type" content="image/png">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta property="og:image:alt" content="<?= esc($brandName) ?> — Genuine Microsoft software licenses, instant digital delivery">
  <meta property="og:locale" content="en_US">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:site" content="@fivecodelab">
  <meta name="twitter:creator" content="@fivecodelab">
  <meta name="twitter:title" content="<?= esc($pageTitle) ?>">
  <meta name="twitter:description" content="<?= esc($pageDescription) ?>">
  <meta name="twitter:image" content="<?= esc($ogImage) ?>">
  <meta name="twitter:image:alt" content="<?= esc($brandName) ?> — Genuine Microsoft software licenses, instant digital delivery">
  <!-- Structured data: Organization + LocalBusiness + Brand + WebSite for AEO/GEO -->
  <script type="application/ld+json"><?php
    // Pull aggregate rating from customer_reviews so the org/site schema
    // surfaces star-rating to AI search engines (ChatGPT/Perplexity/etc.)
    // and Google Knowledge Panel.
    $orgRating = null;
    try {
        $r = db()->query("SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS n FROM customer_reviews WHERE status='published' OR status='approved'")->fetch();
        if ($r && (int)$r['n'] > 0) {
            $orgRating = [
                '@type'       => 'AggregateRating',
                'ratingValue' => (string)$r['avg_rating'],
                'reviewCount' => (int)$r['n'],
                'bestRating'  => '5',
                'worstRating' => '1',
            ];
        }
    } catch (Throwable $e) { /* schema is best-effort */ }

    // -- Parse "12266 Heritage Dr, Moreno Valley, CA 92557, USA" into a real PostalAddress --
    $postalAddress = ['@type' => 'PostalAddress', 'streetAddress' => (string)$brandAddress];
    if ($brandAddress) {
        $parts = array_map('trim', explode(',', (string)$brandAddress));
        if (count($parts) >= 4) {
            // [street, city, "STATE ZIP", country]
            $postalAddress['streetAddress']   = $parts[0];
            $postalAddress['addressLocality'] = $parts[1];
            if (preg_match('/^([A-Z]{2})\s+(\S+)/', $parts[2], $m)) {
                $postalAddress['addressRegion']     = $m[1];
                $postalAddress['postalCode']        = $m[2];
            } else {
                $postalAddress['addressRegion']     = $parts[2];
            }
            // Normalise common country names → ISO 3166-1 alpha-2
            $iso = ['USA' => 'US', 'United States' => 'US', 'UK' => 'GB', 'United Kingdom' => 'GB',
                    'Australia' => 'AU', 'Canada' => 'CA'];
            $postalAddress['addressCountry'] = $iso[$parts[3]] ?? $parts[3];
        }
    }

    // -- Per-market currencies accepted (drives Google's market detection) --
    $currenciesAccepted = 'USD, GBP, AUD, CAD';
    try {
        $curList = db()->query("SELECT GROUP_CONCAT(DISTINCT currency SEPARATOR ', ') FROM regions WHERE active=1")->fetchColumn();
        if ($curList) $currenciesAccepted = (string)$curList;
    } catch (Throwable $e) {}

    // -- Markets served (drives "near me" answers in AI engines) --
    $areaServed = [];
    try {
        $regionRows = db()->query("SELECT code, name FROM regions WHERE active=1 ORDER BY code")->fetchAll();
        foreach ($regionRows as $r) {
            $areaServed[] = ['@type' => 'Country', 'name' => $r['name'], 'identifier' => $r['code']];
        }
    } catch (Throwable $e) {}
    if (!$areaServed) {
        $areaServed = [
            ['@type' => 'Country', 'name' => 'United States',  'identifier' => 'US'],
            ['@type' => 'Country', 'name' => 'United Kingdom', 'identifier' => 'GB'],
            ['@type' => 'Country', 'name' => 'Australia',      'identifier' => 'AU'],
            ['@type' => 'Country', 'name' => 'Canada',         'identifier' => 'CA'],
        ];
    }

    // -- Brand entity (lets AI engines treat "Fivecodelab" as a citable brand) --
    $brandEntity = array_filter([
        '@type' => 'Brand',
        '@id'   => site_url() . '/#brand',
        'name'  => $brandName,
        'url'   => site_url() . '/',
        'logo'  => $brandLogo ?: (site_url() . '/assets/images/badges/microsoft-verified.svg'),
        'slogan'=> 'Genuine Microsoft software licences · instant digital delivery · authorised reseller',
        'aggregateRating' => $orgRating,
        'description'     => 'Fivecodelab Software is an authorised digital reseller of Microsoft Office, Windows, Project, Visio, and trusted antivirus software. Lifetime licences with instant email delivery in the US, UK, Australia, and Canada.',
    ]);

    // -- Opening hours: split into weekday + weekend so Google parses them properly --
    $openingHours = [
        [
            '@type'     => 'OpeningHoursSpecification',
            'dayOfWeek' => ['Monday','Tuesday','Wednesday','Thursday','Friday'],
            'opens'     => '09:00',
            'closes'    => '18:00',
        ],
        [
            '@type'     => 'OpeningHoursSpecification',
            'dayOfWeek' => ['Saturday'],
            'opens'     => '10:00',
            'closes'    => '16:00',
        ],
        // Sunday — closed (omit, schema treats missing days as closed)
    ];

    $graph = [
        array_filter([
            '@type' => 'Organization',
            '@id'   => site_url() . '/#organization',
            'name'  => $brandName,
            'url'   => site_url() . '/',
            'logo'  => $brandLogo ?: (site_url() . '/assets/images/badges/microsoft-verified.svg'),
            'email' => $brandEmail ?: null,
            'brand' => ['@id' => site_url() . '/#brand'],
            'sameAs' => array_values(array_filter([
                $co['twitter']  ?? null,
                $co['facebook'] ?? null,
                $co['linkedin'] ?? null,
                $co['instagram']?? null,
            ])),
            'contactPoint' => $brandPhone ? [[
                '@type'             => 'ContactPoint',
                'telephone'         => $brandPhone,
                'contactType'       => 'customer service',
                'availableLanguage' => ['English'],
                'areaServed'        => ['US', 'GB', 'CA', 'AU'],
            ]] : null,
            'aggregateRating' => $orgRating,
        ]),
        // Brand entity (used by AI engines for "what is X" queries)
        $brandEntity,
        // LocalBusiness — fully populated PostalAddress + opening hours + currencies
        $brandAddress ? array_filter([
            '@type' => 'LocalBusiness',
            '@id'   => site_url() . '/#localbusiness',
            'name'  => $brandName,
            'url'   => site_url() . '/',
            'image' => $brandLogo ?: (site_url() . '/assets/images/badges/microsoft-verified.svg'),
            'logo'  => $brandLogo ?: (site_url() . '/assets/images/badges/microsoft-verified.svg'),
            'telephone'   => $brandPhone ?: null,
            'email'       => $brandEmail ?: null,
            'address'     => $postalAddress,
            'priceRange'  => '$$',
            'currenciesAccepted' => $currenciesAccepted,
            'paymentAccepted'    => 'Visa, MasterCard, American Express, PayPal, Apple Pay, Google Pay, Cryptocurrency',
            'areaServed'  => $areaServed,
            'openingHoursSpecification' => $openingHours,
            'aggregateRating' => $orgRating,
            'brand'           => ['@id' => site_url() . '/#brand'],
            // Geo coordinates for "near me" — Moreno Valley, CA
            'geo' => [
                '@type'    => 'GeoCoordinates',
                'latitude' => 33.9425,
                'longitude' => -117.2297,
            ],
            'hasMap' => 'https://maps.google.com/?q=' . rawurlencode((string)$brandAddress),
        ]) : null,
        [
            '@type' => 'WebSite',
            '@id'   => site_url() . '/#website',
            'name'  => $brandName,
            'url'   => site_url() . '/',
            'publisher'       => ['@id' => site_url() . '/#organization'],
            'inLanguage'      => 'en',
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => ['@type' => 'EntryPoint', 'urlTemplate' => site_url() . '/shop.php?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ],
    ];
    $graph = array_values(array_filter($graph));
    echo json_encode([
        '@context' => 'https://schema.org',
        '@graph'   => $graph,
    ], JSON_UNESCAPED_SLASHES);
  ?></script>
  <?php if (isset($jsonLd)): ?>
  <script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_SLASHES) ?></script>
  <?php endif; ?>
  <?php if (isset($jsonLdBreadcrumb)): ?>
  <script type="application/ld+json"><?= json_encode($jsonLdBreadcrumb, JSON_UNESCAPED_SLASHES) ?></script>
  <?php endif; ?>
  <?php if (isset($jsonLdFaq)): ?>
  <script type="application/ld+json"><?= json_encode($jsonLdFaq, JSON_UNESCAPED_SLASHES) ?></script>
  <?php endif; ?>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <!-- Preconnects + DNS prefetch for the asset CDNs to shave 60-120ms off cold starts (Core Web Vitals → LCP/TTFB) -->
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="dns-prefetch" href="https://fonts.gstatic.com">
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <!-- Branded favicons & web app manifest (improves brand recognition in search results and bookmarks) -->
  <link rel="icon" type="image/svg+xml" href="assets/images/fivecodelab-logo.svg">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon-32.png">
  <link rel="icon" type="image/png" sizes="192x192" href="assets/images/fivecodelab-logo-192.png">
  <link rel="apple-touch-icon" sizes="180x180" href="assets/images/apple-touch-icon.png">
  <link rel="mask-icon" href="assets/images/fivecodelab-logo.svg" color="#003087">
  <link rel="manifest" href="assets/manifest.webmanifest">
  <script>window.SITE_PHONE = '<?= esc($brandPhone) ?>'; window.CART_SLUGS = <?= json_encode(array_keys(cart())) ?>;</script>
</head>
<body data-brand-motion="<?= esc(setting_get('company_logo_motion', 'bounce')) ?>" data-brand-vibe="<?= esc(setting_get('company_brand_vibe', 'classic')) ?>">

<?php if ($checkoutHeader): ?>
<!-- Slim secure-checkout header -->
<nav class="navbar bg-body border-bottom">
  <div class="container d-flex align-items-center justify-content-between flex-wrap gap-2 checkout-header">
    <div class="d-none d-md-flex align-items-center gap-2 small">
      <i class="bi bi-patch-check-fill text-success"></i>
      <span class="fw-semibold">Shopper Approved</span>
      <span class="text-secondary">5,519+ verified reviews</span>
      <span class="badge text-bg-warning text-dark">★ 4.6</span>
    </div>
    <div class="d-flex align-items-center gap-3 small">
      <a href="tel:<?= esc($brandPhone) ?>" class="text-decoration-none fw-semibold"><i class="bi bi-telephone-fill me-1"></i><?= esc($brandPhone) ?></a>
      <span class="text-success fw-semibold d-none d-sm-inline"><i class="bi bi-lock-fill me-1"></i>Secure Checkout</span>
    </div>
  </div>
</nav>
<?php else: ?>

<!-- Promo bar -->
<div class="topbar text-center py-2 px-3">
  Save up to 20% on Microsoft Office 2024 — use code <strong>FIVE20</strong> at checkout — <a href="shop.php" class="text-white fw-bold">Shop Now ›</a>
</div>

<!-- Trust bar -->
<div class="trustbar py-1 px-3 d-none d-md-block">
  <div class="container d-flex justify-content-between align-items-center">
    <div class="d-flex gap-4">
      <span><i class="bi bi-patch-check-fill text-success me-1"></i>Genuine Microsoft Products</span>
      <span><a href="reviews.php" class="text-decoration-none text-white"><span class="text-warning">★★★★★</span> 4.6/5 (4,722+ Reviews)</a></span>
      <span><i class="bi bi-lightning-charge-fill text-warning me-1"></i>Instant Digital Delivery</span>
    </div>
    <div class="d-flex gap-3 align-items-center">
      <span class="badge text-bg-warning text-dark">★ Trusted Software Store</span>
      <span class="badge bg-white text-dark border">2 <small>YRS</small></span>
      <a href="tel:<?= esc($brandPhone) ?>" class="text-decoration-none text-white trustbar-phone"><i class="bi bi-telephone-fill me-1"></i><?= esc($brandPhone) ?></a>
      <a href="<?= defined('SITE_WEBMAIL') ? esc(SITE_WEBMAIL) : '#' ?>" target="_blank" rel="noopener" class="text-decoration-none text-white trustbar-webmail" data-testid="trustbar-webmail"><i class="bi bi-envelope-fill me-1"></i>Webmail</a>
    </div>
  </div>
</div>

<!-- Main navbar -->
<nav class="navbar navbar-expand-lg bg-body border-bottom sticky-top">
  <div class="container position-relative">
    <a class="navbar-brand logo-3d d-flex align-items-center gap-2" href="index.php" data-testid="brand-logo">
      <?php if ($brandLogo !== ''): ?>
        <img src="<?= esc($brandLogo) ?>" alt="<?= esc($brandName) ?>" style="height:42px;width:auto;max-width:140px;object-fit:contain;">
      <?php else: ?>
        <?= render_logo(42) ?>
      <?php endif; ?>
      <span>
        <?php
          // Split brand name so the LAST word picks up the gradient accent.
          $bnParts = preg_split('/\s+/', trim($brandName));
          $bnLast  = array_pop($bnParts) ?: '';
          $bnHead  = implode(' ', $bnParts);
        ?>
        <span class="brand-text d-block lh-1"><?= esc($bnHead) ?><?php if ($bnHead !== ''): ?> <?php endif; ?><span class="brand-grad"><?= esc($bnLast) ?></span></span>
        <small class="brand-tag">AUTHORIZED RESELLER</small>
      </span>
    </a>
    <div class="d-flex align-items-center gap-2 d-lg-none ms-auto me-2">
      <a href="cart.php" class="btn btn-sm btn-primary rounded-pill position-relative" data-testid="cart-button-mobile">
        <i class="bi bi-cart3"></i>
        <span class="cart-count-badge position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger <?= cart_count() === 0 ? 'd-none' : '' ?>" data-testid="cart-count-mobile"><?= cart_count() ?></span>
      </a>
    </div>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav mx-auto">
        <li class="nav-item dropdown position-static">
          <a class="nav-link dropdown-toggle fw-semibold" href="#" data-bs-toggle="dropdown" data-testid="nav-microsoft">Microsoft Products</a>
          <div class="dropdown-menu mega p-4 shadow">
            <div class="row g-4">
              <?php foreach (nav_microsoft() as $heading => $col): ?>
                <div class="col-6 col-lg-3">
                  <div class="mega-heading mb-2"><?= esc($heading) ?></div>
                  <?php foreach ($col['groups'] as $label => $catSlug): ?>
                    <a class="mega-year" href="category.php?slug=<?= esc($catSlug) ?>" data-testid="menu-<?= esc($catSlug) ?>"><?= esc($label) ?></a>
                  <?php endforeach; ?>
                  <a class="mega-link fw-bold text-primary mt-2" href="category.php?slug=<?= esc($col['all'][0]) ?>" data-testid="menu-all-<?= esc($col['all'][0]) ?>"><?= esc($col['all'][1]) ?> <i class="bi bi-arrow-right"></i></a>
                </div>
              <?php endforeach; ?>
            </div>
            <?= render_menu_promo() ?>
            <div class="mt-3 pt-2 border-top">
              <a href="page.php?slug=disclaimer" class="text-decoration-none fw-semibold small" data-testid="menu-disclaimer-ms"><i class="bi bi-info-circle me-1"></i>Disclaimer</a>
            </div>
          </div>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle fw-semibold" href="#" data-bs-toggle="dropdown" data-testid="nav-antivirus">Antivirus</a>
          <div class="dropdown-menu p-3 shadow antivirus-menu" style="min-width: 320px;">
            <div class="mega-heading mb-1">ANTIVIRUS</div>
            <a class="mega-year" href="category.php?slug=bitdefender" data-testid="menu-bitdefender">Bitdefender</a>
            <a class="mega-year" href="category.php?slug=mcafee" data-testid="menu-mcafee">McAfee</a>
            <a class="mega-link fw-bold text-primary mt-2" href="category.php?slug=antivirus" data-testid="menu-all-antivirus">All Antivirus <i class="bi bi-arrow-right"></i></a>
            <a class="mega-link mt-1" href="page.php?slug=disclaimer" data-testid="menu-disclaimer-av"><i class="bi bi-info-circle me-1"></i>Disclaimer</a>
            <?= render_menu_promo(true) ?>
          </div>
        </li>
        <li class="nav-item"><a class="nav-link fw-semibold" href="contact.php">Request a Quote</a></li>
        <li class="nav-item"><a class="nav-link fw-semibold" href="shop.php" data-testid="nav-shop">Shop</a></li>
        <li class="nav-item"><a class="nav-link fw-semibold" href="blog.php">Blog</a></li>
        <li class="nav-item"><a class="nav-link fw-semibold" href="articles.php" data-testid="nav-articles">Articles</a></li>
        <li class="nav-item"><a class="nav-link fw-semibold" href="affiliate.php" data-testid="nav-affiliates">Affiliates</a></li>
      </ul>
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="tel:<?= esc($brandPhone) ?>" class="phone-cta d-none d-xl-inline-flex" data-testid="navbar-phone-cta">
          <span class="phone-cta-icon"><i class="bi bi-telephone-fill"></i></span>
          <span class="lh-1 text-start"><small class="phone-cta-label">CALL TOLL-FREE</small><?= esc($brandPhone) ?></span>
        </a>
        <button class="btn btn-sm btn-outline-primary rounded-pill" onclick="toggleChat()" data-testid="ask-ai-btn"><i class="bi bi-stars me-1"></i>Ask AI</button>
        <div class="dropdown">
          <button class="btn btn-sm btn-outline-secondary dropdown-toggle rounded-pill" data-bs-toggle="dropdown" data-testid="currency-selector">
            <i class="bi bi-globe2 me-1"></i><?= esc($cur['code']) ?>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <?php
            // Public currency selector mirrors active regions from admin.
            // Toggling a region OFF in admin removes its currency here too.
            $regionToCurrency = ['US'=>'USD','UK'=>'GBP','EU'=>'EUR','CA'=>'CAD','AU'=>'AUD'];
            $activeCurrencies = [];
            foreach (all_regions() as $regRow) {
              $cc = $regionToCurrency[$regRow['code']] ?? $regRow['currency'];
              if (isset($GLOBALS['CURRENCIES'][$cc])) {
                $activeCurrencies[$cc] = $GLOBALS['CURRENCIES'][$cc];
              }
            }
            if (empty($activeCurrencies)) $activeCurrencies['USD'] = $GLOBALS['CURRENCIES']['USD'] ?? ['symbol'=>'$','rate'=>1.0,'flag'=>'🇺🇸'];
            foreach ($activeCurrencies as $code => $c): ?>
              <li><a class="dropdown-item <?= $code === $cur['code'] ? 'active' : '' ?>" href="?cur=<?= $code ?>" data-testid="currency-opt-<?= $code ?>"><?= $c['flag'] ?> <?= $code ?> (<?= $c['symbol'] ?>)</a></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <button class="btn btn-sm btn-outline-secondary rounded-circle" onclick="toggleTheme()" title="Toggle dark mode" data-testid="theme-toggle"><i id="theme-icon" class="bi bi-moon"></i></button>
        <a href="cart.php" class="btn btn-sm btn-primary rounded-pill position-relative" data-testid="cart-button">
          <i class="bi bi-cart3 me-1"></i>Cart
          <span class="cart-count-badge position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger <?= cart_count() === 0 ? 'd-none' : '' ?>" data-testid="cart-count"><?= cart_count() ?></span>
        </a>
      </div>
    </div>
  </div>
  <!-- Mobile fixed contact strip — stays still inside the sticky header -->
  <div class="mobile-contact-strip d-lg-none w-100" data-testid="mobile-contact-strip">
    <div class="container d-flex align-items-center justify-content-between gap-2 py-1">
      <div class="lh-sm">
        <div class="fw-bold" style="font-size:.74rem;">Have a Question?</div>
        <div class="text-secondary" style="font-size:.62rem;">Call Mon–Fri 9 AM–6 PM EST</div>
      </div>
      <div class="d-flex gap-2 flex-shrink-0">
        <a href="tel:<?= esc($brandPhone) ?>" class="btn btn-sm rounded-pill fw-bold phone-cta-mobile" data-testid="mobile-call-btn"><i class="bi bi-telephone-fill me-1"></i><?= esc($brandPhone) ?></a>
        <button class="btn btn-sm btn-primary rounded-pill fw-bold" style="font-size:.7rem;" onclick="toggleChat()" data-testid="mobile-chat-btn"><i class="bi bi-chat-dots-fill me-1"></i>Chat</button>
      </div>
    </div>
  </div>
</nav>
<!-- Sticky limited-time deal bar — live countdown resets daily at local midnight -->
<div class="deal-bar" id="deal-bar" data-testid="deal-bar">
  <div class="container d-flex align-items-center justify-content-center gap-2 gap-sm-3 flex-wrap py-2 pe-5">
    <span class="deal-bar-flash"><i class="bi bi-lightning-charge-fill"></i></span>
    <span class="fw-bold small">Limited-Time Deal: 20% off sitewide with code <span class="deal-code">FIVE20</span></span>
    <span class="small">Ends in <strong class="deal-countdown" id="deal-countdown" data-testid="deal-bar-countdown">--:--:--</strong></span>
    <a href="shop.php" class="btn btn-sm btn-light rounded-pill fw-bold px-3 deal-cta" data-testid="deal-bar-cta">Shop Now</a>
    <button type="button" class="btn-close btn-close-white deal-close" aria-label="Dismiss deal bar" data-testid="deal-bar-close"></button>
  </div>
</div>
<?php endif; ?>
