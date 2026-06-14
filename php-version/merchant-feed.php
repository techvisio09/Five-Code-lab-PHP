<?php
// =====================================================================
// Google Merchant Center + Bing Shopping product feed
// Served at /merchant-feed.xml — submit this URL inside Merchant Center
// (Products → Feeds) and Bing Webmaster Tools (Shopping → Product feeds).
//
// Schema reference:
//   Google: https://support.google.com/merchants/answer/7052112
//   Bing:   https://help.ads.microsoft.com/apex/index/3/en/53056
// Bing reads the same g: namespace, plus a few of its own optional fields.
// =====================================================================
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=3600');

$base = site_url();

function feed_brand(string $name): string
{
    if (stripos($name, 'bitdefender') !== false) return 'Bitdefender';
    if (stripos($name, 'mcafee')      !== false) return 'McAfee';
    if (stripos($name, 'norton')      !== false) return 'Norton';
    if (stripos($name, 'kaspersky')   !== false) return 'Kaspersky';
    if (stripos($name, 'eset')        !== false) return 'ESET';
    if (stripos($name, 'adobe')       !== false) return 'Adobe';
    if (stripos($name, 'autocad') !== false || stripos($name, 'autodesk') !== false) return 'Autodesk';
    if (stripos($name, 'parallels')   !== false) return 'Parallels';
    return 'Microsoft';
}

function feed_product_type(array $p): string
{
    $cat = strtolower((string)($p['category'] ?? ''));
    if (str_contains($cat, 'antivirus') || str_contains($cat, 'bitdefender') || str_contains($cat, 'mcafee') || str_contains($cat, 'norton')) return 'Software > Antivirus & Security';
    if (str_contains($cat, 'windows')) return 'Software > Operating Systems > Windows';
    if (str_contains($cat, 'office'))  return 'Software > Office Suites > Microsoft Office';
    if (str_contains($cat, 'project') || str_contains($cat, 'visio')) return 'Software > Office Apps';
    return 'Software > Computer Software';
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">
<channel>
  <title><?= esc(SITE_BRAND) ?> — Genuine Microsoft &amp; Antivirus Software</title>
  <link><?= esc($base) ?>/</link>
  <description>Genuine Microsoft Office, Windows and antivirus license keys with instant digital delivery from <?= esc(SITE_BRAND) ?>. Lifetime activation, 30-day money-back guarantee, US-based support.</description>
  <language>en-US</language>
  <lastBuildDate><?= date(DATE_RSS) ?></lastBuildDate>
<?php foreach (db()->query('SELECT * FROM products WHERE ' . active_regions_sql_in('region')) as $p):
    $hasSale = $p['original_price'] && $p['original_price'] > $p['price'];
    $brand   = feed_brand($p['name']);
    $platform = ucfirst((string)($p['platform'] ?? 'PC'));
    $url     = $base . '/product.php?slug=' . $p['slug'];
    $desc    = 'Genuine ' . $p['name'] . ' lifetime license key for ' . $platform
             . '. Instant email delivery within 15-30 minutes, official download link, free activation support and a 30-day money-back guarantee from ' . SITE_BRAND . '.';
    $rawPrice = number_format((float)$p['price'], 2, '.', '');
    $listPrice = number_format((float)($hasSale ? $p['original_price'] : $p['price']), 2, '.', '');
    $stock = (int)($p['stock'] ?? 1);
    $avail = $stock > 0 ? 'in_stock' : 'out_of_stock';
?>
  <item>
    <g:id><?= esc($p['slug']) ?></g:id>
    <g:title><?= esc(mb_substr($p['name'], 0, 150)) ?></g:title>
    <g:description><?= esc(mb_substr($desc, 0, 5000)) ?></g:description>
    <g:link><?= esc($url) ?></g:link>
    <g:mobile_link><?= esc($url) ?></g:mobile_link>
    <g:image_link><?= esc($p['image']) ?></g:image_link>
    <g:availability><?= $avail ?></g:availability>
    <g:availability_date><?= date('c') ?></g:availability_date>
    <g:price><?= $listPrice ?> USD</g:price>
<?php if ($hasSale): ?>
    <g:sale_price><?= $rawPrice ?> USD</g:sale_price>
    <g:sale_price_effective_date><?= date('Y-m-d\TH:i:sP') ?>/<?= date('Y-m-d\TH:i:sP', strtotime('+30 days')) ?></g:sale_price_effective_date>
<?php endif; ?>
    <g:brand><?= esc($brand) ?></g:brand>
    <g:mpn><?= esc($p['slug']) ?></g:mpn>
    <g:condition>new</g:condition>
    <g:identifier_exists>no</g:identifier_exists>
    <g:adult>no</g:adult>
    <g:is_bundle>no</g:is_bundle>
    <g:google_product_category>Software &gt; Computer Software</g:google_product_category>
    <g:product_type><?= esc(feed_product_type($p)) ?></g:product_type>
    <g:item_group_id><?= esc($p['category'] ?: 'software') ?></g:item_group_id>
    <g:product_highlight>Instant digital delivery (15-30 min)</g:product_highlight>
    <g:product_highlight>Genuine vendor activation</g:product_highlight>
    <g:product_highlight>Lifetime perpetual license</g:product_highlight>
    <g:product_highlight>Free 30-day money-back guarantee</g:product_highlight>
    <g:additional_image_link><?= esc($p['image']) ?></g:additional_image_link>
    <!-- Digital download — zero shipping cost, instant delivery -->
    <g:shipping>
      <g:country>US</g:country>
      <g:service>Digital delivery</g:service>
      <g:price>0.00 USD</g:price>
    </g:shipping>
    <g:shipping>
      <g:country>GB</g:country>
      <g:service>Digital delivery</g:service>
      <g:price>0.00 USD</g:price>
    </g:shipping>
    <g:shipping>
      <g:country>CA</g:country>
      <g:service>Digital delivery</g:service>
      <g:price>0.00 USD</g:price>
    </g:shipping>
    <g:shipping>
      <g:country>AU</g:country>
      <g:service>Digital delivery</g:service>
      <g:price>0.00 USD</g:price>
    </g:shipping>
    <g:shipping_weight>0.00 kg</g:shipping_weight>
    <g:max_handling_time>0</g:max_handling_time>
    <g:min_handling_time>0</g:min_handling_time>
    <g:tax>
      <g:country>US</g:country>
      <g:rate>0.00</g:rate>
      <g:tax_ship>no</g:tax_ship>
    </g:tax>
    <!-- Bing Shopping & Google Merchant custom labels for ad grouping -->
    <g:custom_label_0>Digital</g:custom_label_0>
    <g:custom_label_1><?= esc($brand) ?></g:custom_label_1>
    <g:custom_label_2><?= esc($platform) ?></g:custom_label_2>
    <g:custom_label_3><?= $hasSale ? 'On sale' : 'Standard' ?></g:custom_label_3>
    <g:custom_label_4><?= esc(strtolower((string)($p['category'] ?? ''))) ?></g:custom_label_4>
<?php if (!empty($p['rating']) && !empty($p['reviews'])): ?>
    <g:product_review_count><?= (int)$p['reviews'] ?></g:product_review_count>
    <g:product_review_average><?= number_format((float)$p['rating'], 1, '.', '') ?></g:product_review_average>
<?php endif; ?>
  </item>
<?php endforeach; ?>
</channel>
</rss>
