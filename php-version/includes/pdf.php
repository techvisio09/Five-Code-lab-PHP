<?php
/*
 * Receipt + Invoice PDF generators — used by send_email() to attach
 * proper, professionally-formatted PDFs to every paid order email.
 *
 * Layout closely mirrors the reference Emergent receipt / invoice style
 * the product owner provided: clean sans-serif, two-column header with
 * company info on the left + brand logo / receipt number on the right,
 * "Bill to" customer block, single line-items table with right-aligned
 * currency, summary totals, payment-history table (for the receipt
 * variant only), and a clear statement-name line so the customer knows
 * what to look for on their bank statement.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/functions.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Build a Dompdf instance with sane defaults for our receipts/invoices.
 */
function _pdf_dompdf(): Dompdf
{
    $o = new Options();
    $o->set('defaultFont',           'DejaVu Sans');   // ships with Dompdf
    $o->set('isHtml5ParserEnabled',  true);
    $o->set('isRemoteEnabled',       true);            // allow remote product images
    $o->set('chroot',                __DIR__ . '/..'); // keep file access local
    return new Dompdf($o);
}

/**
 * Download a remote image to local cache once, then return the cached path.
 * Used so Dompdf can embed remote product imagery without making a live HTTP
 * call on every PDF render.  Returns '' if the download fails.
 */
function _pdf_cache_image(string $url): string
{
    if ($url === '') return '';
    if (str_starts_with($url, '/')) {
        $local = __DIR__ . '/..' . $url;
        return is_file($local) ? realpath($local) : '';
    }
    if (!preg_match('~^https?://~i', $url)) return '';
    $dir = __DIR__ . '/../assets/images/cache';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $hash = sha1($url);
    $ext  = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION)) ?: 'jpg';
    $ext  = in_array($ext, ['jpg','jpeg','png','webp','gif']) ? $ext : 'jpg';
    $dst  = $dir . '/p_' . $hash . '.' . $ext;
    if (is_file($dst) && filesize($dst) > 100) return realpath($dst);

    $ctx = stream_context_create(['http' => ['timeout' => 6, 'user_agent' => 'Mozilla/5.0 PDFCache']]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false || strlen($body) < 100) return '';
    @file_put_contents($dst, $body);
    return is_file($dst) ? realpath($dst) : '';
}

/**
 * Generate a PNG QR code for a given payload, cached locally so we only shell
 * out to `qrencode` once per unique URL.  Returns the cached path or '' on
 * failure (PDF still renders without the QR if the binary isn't installed).
 */
function _pdf_make_qr(string $payload): string
{
    if ($payload === '') return '';
    $cacheDir = __DIR__ . '/../assets/images/cache/qr';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);
    $dst = $cacheDir . '/' . sha1($payload) . '.png';
    if (is_file($dst) && filesize($dst) > 100) return realpath($dst);

    $bin = trim((string)@shell_exec('command -v qrencode 2>/dev/null'));
    if ($bin === '' || !is_executable($bin)) return '';

    // -t PNG  PNG output
    // -s 12   12px per module (sharp at 300×300 cells)
    // -m 1    1-module quiet border
    // -l M    medium error correction
    // -o file output path
    $cmd = escapeshellcmd($bin)
         . ' -t PNG -s 12 -m 1 -l M -o ' . escapeshellarg($dst) . ' '
         . escapeshellarg($payload);
    @shell_exec($cmd . ' 2>/dev/null');
    return is_file($dst) && filesize($dst) > 100 ? realpath($dst) : '';
}

/**
 * Stamp a watermark + tiny Office-app icon strip on every page of a Dompdf
 * document.  page_script() is the only Dompdf approach that reliably draws
 * positioned imagery on each page (CSS position:absolute with negative
 * z-index gets silently dropped by Dompdf in some layouts).
 *
 *   • Centre watermark = the actual product the customer purchased
 *     (first order item) — a soft, low-opacity hero behind the page body.
 *   • Top ornament strip = tiny Microsoft Office app icons
 *     (Word / Excel / PowerPoint / Outlook / Access) just below the title.
 *   • Bottom-right QR code = deep-link to the customer's Order History
 *     entry (order number + email pre-filled).  Anyone holding a printed
 *     copy can scan to pull a fresh digital PDF on the spot.
 */
function _pdf_apply_brand_layers(Dompdf $dompdf, string $productImageUrl = '', string $qrPayload = '', string $orderStatus = '', string $scatterSeed = ''): void
{
    $canvas = $dompdf->getCanvas();
    if (!$canvas) return;

    $pageW = $canvas->get_width();
    $pageH = $canvas->get_height();

    $scriptLines = [];

    // 1) Scattered Microsoft Office app icons — soft watermarks dotted across
    //    the entire page (NOT a bottom strip). Deterministic positions seeded
    //    by the order number so the same order always lays out identically
    //    (great for printed copies looking identical to digital).
    $appDir = __DIR__ . '/../assets/images/cache/soft-apps';
    $apps = [];
    foreach (['word.png','excel.png','powerpoint.png','outlook.png','access.png'] as $fn) {
        $p = realpath($appDir . '/' . $fn);
        if ($p) $apps[] = $p;
    }
    if (!empty($apps)) {
        $seed = crc32(($scatterSeed !== '' ? $scatterSeed : 'fivecodelab') . '|' . count($apps));
        mt_srand($seed);
        // 14 icons spread across the page in a jittered grid (avoids overlap)
        $cols = 4; $rows = 5;
        $cellW = ($pageW - 80) / $cols;
        $cellH = ($pageH - 140) / $rows;   // leave room top + bottom
        $count = 0;
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                if (++$count > 14) break 2;
                // Skip the centre two cells so the totals / amount banner stays clean
                if ($r >= 1 && $r <= 3 && ($c === 1 || $c === 2)) continue;
                $ap   = $apps[mt_rand(0, count($apps) - 1)];
                $size = mt_rand(28, 44);
                $jx   = mt_rand(-22, 22);
                $jy   = mt_rand(-18, 18);
                $rot  = mt_rand(-18, 18);
                $x    = 40 + $c * $cellW + ($cellW - $size) / 2.0 + $jx;
                $y    = 80 + $r * $cellH + ($cellH - $size) / 2.0 + $jy;
                $cx   = $x + $size / 2.0;
                $cy   = $y + $size / 2.0;
                $img  = var_export($ap, true);
                $scriptLines[] = "\$pdf->save(); \$pdf->rotate($rot, $cx, $cy); \$pdf->image($img, $x, $y, $size, $size); \$pdf->restore();";
            }
        }
    }

    // 2) PAID / DUE rubber stamp — large, semi-transparent, rotated.
    //    Light-coloured (32% alpha baked into the source PNG so totals
    //    sitting on top stay perfectly readable).
    $statusKey = strtolower(trim($orderStatus));
    $stampFile = null;
    if (in_array($statusKey, ['paid','fulfilled','completed','complete'], true)) {
        $stampFile = realpath(__DIR__ . '/../assets/images/brand/stamp-paid-soft.png');
    } elseif (in_array($statusKey, ['pending','unpaid','awaiting','due','overdue'], true)) {
        $stampFile = realpath(__DIR__ . '/../assets/images/brand/stamp-due-soft.png');
    }
    if ($stampFile) {
        $stampW = 320.0;  // generous — clear "PAID/DUE" call-out
        $stampH = 160.0;
        $stampX = ($pageW - $stampW) / 2.0;
        $stampY = $pageH * 0.34;   // upper-middle of the page
        $img = var_export($stampFile, true);
        $scriptLines[] = "\$pdf->image($img, $stampX, $stampY, $stampW, $stampH);";
    }

    // 3) Bottom-right QR code — deep links to the customer's Order Lookup.
    if ($qrPayload !== '') {
        $qrPath = _pdf_make_qr($qrPayload);
        if ($qrPath) {
            $qrSize = 64.0;
            $qrX    = $pageW - 48 - $qrSize;
            $qrY    = $pageH - 48 - $qrSize;
            $img = var_export($qrPath, true);
            $scriptLines[] = "\$pdf->image($img, $qrX, $qrY, $qrSize, $qrSize);";
        }
    }

    if (empty($scriptLines)) return;
    $canvas->page_script(implode("\n", $scriptLines));
}

/**
 * Pre-blend a product image down to a given alpha so it renders as a soft
 * watermark inside the PDF.  Cached so the resize/composite only happens once.
 */
function _pdf_make_soft_image(string $sourcePath, float $alpha = 0.10): string
{
    if (!is_file($sourcePath)) return '';
    $alpha = max(0.02, min(1.0, $alpha));
    $cacheDir = __DIR__ . '/../assets/images/cache/soft';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);
    $cached = $cacheDir . '/' . sha1($sourcePath . '|' . $alpha) . '.png';
    if (is_file($cached) && filesize($cached) > 100) return realpath($cached);
    if (!function_exists('imagecreatefromstring')) return $sourcePath; // GD not available

    $raw = @file_get_contents($sourcePath);
    if ($raw === false) return $sourcePath;
    $src = @imagecreatefromstring($raw);
    if (!$src) return $sourcePath;

    $w = imagesx($src); $h = imagesy($src);
    // Resize down to <= 600px for smaller PDF footprint
    $max = 600;
    $scale = min(1.0, $max / max($w, $h));
    $tw = (int)round($w * $scale); $th = (int)round($h * $scale);
    $dst = imagecreatetruecolor($tw, $th);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
    imagefilledrectangle($dst, 0, 0, $tw, $th, $transparent);
    imagealphablending($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $w, $h);
    imagedestroy($src);

    // Bake alpha by iterating pixels — slow but only runs once per image
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    for ($y = 0; $y < $th; $y++) {
        for ($x = 0; $x < $tw; $x++) {
            $c = imagecolorat($dst, $x, $y);
            $a = ($c >> 24) & 0x7F;
            $r = ($c >> 16) & 0xFF;
            $g = ($c >> 8)  & 0xFF;
            $b = $c & 0xFF;
            // existing alpha (0=opaque, 127=transparent) → opacity 0..1
            $existingOpacity = 1.0 - ($a / 127.0);
            $newOpacity = $existingOpacity * $alpha;
            $newA = (int)round((1.0 - $newOpacity) * 127);
            imagesetpixel($dst, $x, $y, imagecolorallocatealpha($dst, $r, $g, $b, $newA));
        }
    }
    imagepng($dst, $cached, 8);
    imagedestroy($dst);
    return is_file($cached) ? realpath($cached) : $sourcePath;
}

/**
 * Number → currency formatter that matches what we show on the site
 * (uses the symbol of the order's currency, not the active session one).
 */
function _pdf_money(float $amount, string $cur = 'USD'): string
{
    $sym = ['USD'=>'$','GBP'=>'£','EUR'=>'€','CAD'=>'CA$','AUD'=>'A$','INR'=>'₹','AED'=>'د.إ'][$cur] ?? '';
    return $sym . number_format($amount, 2);
}

/**
 * Shared HTML head + brand header used by both Receipt and Invoice.
 * Variant: 'receipt' or 'invoice' — only the title + sub-line change.
 */
function _pdf_shell(array $ctx, string $bodyHtml): string
{
    $co       = $ctx['co'];
    $brand    = htmlspecialchars($co['name']    ?? 'Fivecodelab Software', ENT_QUOTES, 'UTF-8');
    $brandAddr= nl2br(htmlspecialchars($co['address'] ?? '',             ENT_QUOTES, 'UTF-8'));
    $brandEm  = htmlspecialchars($co['email']   ?? '',                   ENT_QUOTES, 'UTF-8');
    $logoUrl  = $ctx['logo']  ?? '';   // local file path is fine for Dompdf
    $docTitle = htmlspecialchars($ctx['title'] ?? 'Document',            ENT_QUOTES, 'UTF-8');
    $invNo    = htmlspecialchars($ctx['invoice_number'] ?? '',           ENT_QUOTES, 'UTF-8');
    $secondRow= '';
    if (!empty($ctx['receipt_number'])) {
        $secondRow .= '<tr><td>Receipt number</td><td class="r">' . htmlspecialchars($ctx['receipt_number'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
    }
    if (!empty($ctx['date_paid'])) {
        $secondRow .= '<tr><td>Date paid</td><td class="r">' . htmlspecialchars($ctx['date_paid'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
    }
    if (!empty($ctx['date_issued'])) {
        $secondRow .= '<tr><td>Date of issue</td><td class="r">' . htmlspecialchars($ctx['date_issued'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
    }
    if (!empty($ctx['date_due'])) {
        $secondRow .= '<tr><td>Date due</td><td class="r">'  . htmlspecialchars($ctx['date_due'], ENT_QUOTES, 'UTF-8')  . '</td></tr>';
    }
    $billLines = '';
    foreach ((array)($ctx['bill_to'] ?? []) as $line) {
        $billLines .= '<div>' . htmlspecialchars((string)$line, ENT_QUOTES, 'UTF-8') . '</div>';
    }
    $logoTag = $logoUrl && file_exists($logoUrl)
        ? '<img src="' . $logoUrl . '" alt="' . $brand . '" style="height:44px;width:auto;vertical-align:top;">'
        : '<div style="font-size:18px;font-weight:800;color:#06b6d4;letter-spacing:.5px;">' . $brand . '</div>';

    return <<<HTML
<!doctype html>
<html><head><meta charset="utf-8">
<style>
  @page { margin: 56px 48px 100px; }   /* extra bottom margin for the app-icon ornament */
  body  { font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif; font-size: 10.5pt; color: #1f2937; }

  /* Title block — refined PayPal-style header */
  .doc-title-wrap { margin: 0 0 10px; }
  h1.doc-title { font-size: 28pt; font-weight: 800; margin: 0 0 6px; color: #003087; letter-spacing: -.4px; }
  .doc-title-rule { width: 56px; height: 4px; background: #FFC439; border-radius: 2px; margin: 0 0 18px; }
  .doc-sub { font-size: 8pt; letter-spacing: 1.4px; text-transform: uppercase; font-weight: 700; color: #6c7378; margin: 0 0 4px; }

  .head-grid { width: 100%; border-collapse: collapse; margin-bottom: 26px; }
  .head-grid td { vertical-align: top; }
  .head-meta { width: 55%; }
  .head-meta table { width: 100%; border-collapse: collapse; font-size: 9.5pt; color: #475569; }
  .head-meta table td { padding: 3px 0; }
  .head-meta table td.r { text-align: right; color: #003087; font-weight: 700; font-family: 'DejaVu Sans Mono', monospace; }
  .head-brand { width: 45%; text-align: right; }
  .head-brand .brand-line { margin-top: 8px; font-size: 9pt; color: #6c7378; line-height: 1.55; }
  .head-brand .brand-line strong { color: #003087; font-size: 10pt; }

  .from-bill { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
  .from-bill td { vertical-align: top; width: 50%; padding-right: 12px; font-size: 9.5pt; color: #1f2937; }
  .from-bill .label { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 1.4px; color: #6c7378; font-weight: 800; margin-bottom: 6px; }
  .from-bill .bold  { color: #003087; font-weight: 700; }

  /* Amount banner — PayPal navy + signature yellow accent */
  .amount-banner { background: #F5F9FD; border-left: 4px solid #0070BA; padding: 16px 18px; margin-bottom: 22px; border-radius: 0 6px 6px 0; }
  .amount-banner .amt { font-size: 20pt; font-weight: 800; color: #003087; letter-spacing: -.3px; }
  .amount-banner .sub { font-size: 9pt; color: #6c7378; margin-top: 4px; line-height: 1.5; }

  table.items, table.payhist { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
  table.items th, table.items td, table.payhist th, table.payhist td { padding: 10px 6px; font-size: 9.5pt; }
  table.items thead, table.payhist thead { border-bottom: 2px solid #003087; background: #F5F9FD; }
  table.items th, table.payhist th { text-align: left; font-weight: 800; color: #003087; font-size: 8.5pt; text-transform: uppercase; letter-spacing: .8px; padding-top: 12px; padding-bottom: 12px; }
  table.items td, table.payhist td { border-bottom: 1px solid #E6E9EC; }
  table.items td.num, table.items th.num, table.payhist td.num, table.payhist th.num { text-align: right; font-variant-numeric: tabular-nums; }
  table.items tbody tr td:first-child { font-weight: 600; color: #1f2937; }

  .totals { width: 50%; margin-left: 50%; border-collapse: collapse; font-size: 10pt; }
  .totals td { padding: 6px 4px; }
  .totals td.label { color: #6c7378; }
  .totals td.value { text-align: right; color: #003087; font-weight: 700; font-variant-numeric: tabular-nums; }
  .totals tr.total-row td { border-top: 2px solid #003087; padding-top: 11px; font-size: 12pt; font-weight: 800; color: #003087; }
  .totals tr.amount-paid td { padding-top: 11px; color: #047857; font-weight: 800; }
  .totals tr.amount-due td { padding-top: 11px; color: #b91c1c; font-weight: 800; }

  .statement {
    background: #FFFBEB; border-left: 3px solid #FFC439; padding: 12px 16px;
    border-radius: 0 6px 6px 0; margin: 24px 0; font-size: 9.5pt; color: #78350F;
  }
  .statement .lbl { font-weight: 800; color: #533F03; }

  /* Ornament strip label (icons themselves are drawn via canvas page_script) */
  .ms-ornament-label {
    text-align: center;
    margin-top: 30px;
    padding-top: 14px;
    border-top: 1px solid #E6E9EC;
    font-size: 7pt;
    letter-spacing: 1.8px;
    text-transform: uppercase;
    color: #94A0BC;
    font-weight: 700;
  }
  .ms-ornament-label .accent { color: #FFC439; }

  .footer {
    margin-top: 14px;
    font-size: 8pt; color: #94A0BC; line-height: 1.6; text-align: center;
  }
  .footer .legal { display: block; margin-top: 4px; font-size: 7.5pt; color: #B7BECC; }
</style>
</head>
<body>
  <div class="doc-title-wrap">
    <div class="doc-sub">{$brand}</div>
    <h1 class="doc-title">{$docTitle}</h1>
    <div class="doc-title-rule"></div>
  </div>
  <table class="head-grid"><tr>
    <td class="head-meta">
      <table>
        <tr><td>Invoice number</td><td class="r">{$invNo}</td></tr>
        {$secondRow}
      </table>
    </td>
    <td class="head-brand">
      {$logoTag}
      <div class="brand-line">
        <strong>{$brand}</strong><br>
        {$brandAddr}<br>
        {$brandEm}
      </div>
    </td>
  </tr></table>

  <table class="from-bill"><tr>
    <td><div class="label">Bill to</div>{$billLines}</td>
    <td></td>
  </tr></table>

  {$bodyHtml}

  <div class="footer">
    Questions? Reply to this email or visit our support page. <strong>Thanks for choosing {$brand}.</strong>
    <span class="legal">Microsoft®, Office® and Windows® are trademarks of Microsoft Corporation. {$brand} is independent of and not affiliated with Microsoft Corporation.</span>
  </div>
</body></html>
HTML;
}

/**
 * Generate a Receipt PDF (paid orders).  Returns the binary PDF string.
 * Throws on rendering failure.
 */
function generate_receipt_pdf(array $order, array $items, ?array $payment = null): string
{
    $co  = function_exists('company_info') ? company_info() : ['name' => 'Fivecodelab Software'];
    $cur = (string)($order['currency'] ?? 'USD');
    $invoiceNo = (string)($order['order_number'] ?? '');
    $receiptNo = strtoupper(substr(bin2hex(sha1((string)$order['id'] . '-' . $invoiceNo, true)), 0, 9));
    // Insert a hyphen so it looks like "2797-4805"
    $receiptNo = substr($receiptNo, 0, 4) . '-' . substr($receiptNo, 4, 4);

    $datePaid = $order['paid_at'] ?? $order['created_at'] ?? date('Y-m-d H:i:s');
    $datePaid = date('F j, Y', strtotime($datePaid));

    // Bill-to block — sanitised, multi-line.
    $billTo = array_filter([
        trim((string)($order['first_name'] ?? '') . ' ' . (string)($order['last_name'] ?? '')),
        (string)$order['email'],
        trim(((string)($order['address']  ?? '')) . (empty($order['address2']) ? '' : ', ' . $order['address2'])),
        trim(((string)($order['city']     ?? '')) . ', ' . ((string)($order['state'] ?? '')) . ' ' . ((string)($order['zip'] ?? ''))),
        (string)($order['country'] ?? ''),
    ], fn($l) => trim((string)$l) !== '');

    $stmtName = !empty($order['card_statement_name'])
        ? (string)$order['card_statement_name']
        : (function_exists('statement_name_for')
            ? (string)statement_name_for((string)($order['payment_method'] ?? 'card'))
            : (string)($co['name'] ?? 'Fivecodelab Software'));

    // Items table rows.
    $itemsHtml = '<table class="items"><thead><tr><th>Description</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Amount</th></tr></thead><tbody>';
    $subtotal = 0.0;
    foreach ($items as $it) {
        $qty   = (int)($it['quantity'] ?? $it['qty'] ?? 1);
        $unit  = (float)($it['unit_price'] ?? $it['price'] ?? 0);
        $amt   = $qty * $unit;
        $subtotal += $amt;
        $itemsHtml .= '<tr><td>' . htmlspecialchars((string)($it['name'] ?? $it['product_name'] ?? '—'), ENT_QUOTES, 'UTF-8')
                   . '</td><td class="num">' . $qty
                   . '</td><td class="num">' . _pdf_money($unit, $cur)
                   . '</td><td class="num">' . _pdf_money($amt, $cur) . '</td></tr>';
    }
    $itemsHtml .= '</tbody></table>';

    $total = (float)($order['total'] ?? $subtotal);

    $payRow = '';
    if ($payment) {
        $payMethod = htmlspecialchars((string)($payment['method'] ?? 'Card'), ENT_QUOTES, 'UTF-8');
        $payDate   = htmlspecialchars((string)($payment['date']   ?? $datePaid), ENT_QUOTES, 'UTF-8');
        $payRow = "<tr><td>{$payMethod}</td><td>{$payDate}</td><td class=\"num\">" . _pdf_money($total, $cur) . "</td><td class=\"num\">{$receiptNo}</td></tr>";
    } elseif (!empty($order['card_brand']) || !empty($order['payment_method'])) {
        $brand = $order['card_brand'] ?: ucfirst((string)$order['payment_method']);
        $tail  = !empty($order['card_last4']) ? ' - ' . $order['card_last4'] : '';
        $payRow = "<tr><td>{$brand}{$tail}</td><td>{$datePaid}</td><td class=\"num\">" . _pdf_money($total, $cur) . "</td><td class=\"num\">{$receiptNo}</td></tr>";
    }

    $bodyHtml = '<div class="amount-banner">
                    <div class="amt">' . _pdf_money($total, $cur) . ' paid on ' . htmlspecialchars($datePaid, ENT_QUOTES, 'UTF-8') . '</div>
                    <div class="sub">Thanks for your purchase — your license keys are delivered in the accompanying email.</div>
                 </div>'
              . $itemsHtml
              . '<table class="totals">
                    <tr><td class="label">Subtotal</td><td class="value">' . _pdf_money($subtotal, $cur) . '</td></tr>
                    <tr class="total-row"><td class="label">Total</td><td class="value">' . _pdf_money($total, $cur) . '</td></tr>
                    <tr class="amount-paid"><td class="label">Amount paid</td><td class="value">' . _pdf_money($total, $cur) . '</td></tr>
                 </table>'
              . '<div class="statement"><span class="lbl">Bank statement note:</span> This charge will appear as <strong>' . htmlspecialchars($stmtName, ENT_QUOTES, 'UTF-8') . '</strong> on your card statement.</div>'
              . ($payRow ? '<div style="font-weight:700;color:#0f172a;margin:18px 0 6px;font-size:11pt;">Payment history</div>
                            <table class="payhist"><thead><tr><th>Payment method</th><th>Date</th><th class="num">Amount paid</th><th class="num">Receipt number</th></tr></thead><tbody>' . $payRow . '</tbody></table>' : '');

    $html = _pdf_shell([
        'co'              => $co,
        'logo'            => __DIR__ . '/../assets/images/brand/fivecodelab-wordmark.svg',
        'title'           => 'Receipt',
        'invoice_number'  => $invoiceNo,
        'receipt_number'  => $receiptNo,
        'date_paid'       => $datePaid,
        'bill_to'         => $billTo,
    ], $bodyHtml);

    $dompdf = _pdf_dompdf();
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('letter', 'portrait');
    // Brand layers: purchased product as soft centre watermark + tiny Office app icon strip + QR
    $heroProductImg = (string)($items[0]['image'] ?? '');
    if ($heroProductImg === '' && !empty($items[0]['product_slug'])) {
        $p = function_exists('get_product') ? get_product($items[0]['product_slug']) : null;
        if ($p && !empty($p['image'])) $heroProductImg = (string)$p['image'];
    }
    $qrPayload = function_exists('site_url')
        ? (rtrim(site_url(), '/') . '/order-lookup.php?o=' . urlencode((string)$order['order_number']) . '&e=' . urlencode((string)($order['email'] ?? '')))
        : '';
    _pdf_apply_brand_layers($dompdf, $heroProductImg, $qrPayload, (string)($order['status'] ?? ''), (string)$order['order_number']);
    $dompdf->render();
    return $dompdf->output();
}

/**
 * Generate an Invoice PDF (issued at order time — works for both paid and
 * pending orders).  Returns the binary PDF string.
 */
function generate_invoice_pdf(array $order, array $items): string
{
    $co  = function_exists('company_info') ? company_info() : ['name' => 'Fivecodelab Software'];
    $cur = (string)($order['currency'] ?? 'USD');
    $invoiceNo = (string)($order['order_number'] ?? '');

    $dateIssued = date('F j, Y', strtotime((string)($order['created_at'] ?? 'now')));
    $dateDue    = $dateIssued;  // For our digital goods, due-on-issue.

    $billTo = array_filter([
        trim((string)($order['first_name'] ?? '') . ' ' . (string)($order['last_name'] ?? '')),
        (string)$order['email'],
        trim(((string)($order['address']  ?? '')) . (empty($order['address2']) ? '' : ', ' . $order['address2'])),
        trim(((string)($order['city']     ?? '')) . ', ' . ((string)($order['state'] ?? '')) . ' ' . ((string)($order['zip'] ?? ''))),
        (string)($order['country'] ?? ''),
    ], fn($l) => trim((string)$l) !== '');

    $stmtName = !empty($order['card_statement_name'])
        ? (string)$order['card_statement_name']
        : (function_exists('statement_name_for')
            ? (string)statement_name_for((string)($order['payment_method'] ?? 'card'))
            : (string)($co['name'] ?? 'Fivecodelab Software'));

    $itemsHtml = '<table class="items"><thead><tr><th>Description</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Amount</th></tr></thead><tbody>';
    $subtotal = 0.0;
    foreach ($items as $it) {
        $qty   = (int)($it['quantity'] ?? $it['qty'] ?? 1);
        $unit  = (float)($it['unit_price'] ?? $it['price'] ?? 0);
        $amt   = $qty * $unit;
        $subtotal += $amt;
        $itemsHtml .= '<tr><td>' . htmlspecialchars((string)($it['name'] ?? $it['product_name'] ?? '—'), ENT_QUOTES, 'UTF-8')
                   . '</td><td class="num">' . $qty
                   . '</td><td class="num">' . _pdf_money($unit, $cur)
                   . '</td><td class="num">' . _pdf_money($amt, $cur) . '</td></tr>';
    }
    $itemsHtml .= '</tbody></table>';

    $total = (float)($order['total'] ?? $subtotal);
    $isPaid = (string)($order['status'] ?? '') === 'paid';

    $bodyHtml = '<div class="amount-banner">
                    <div class="amt">' . _pdf_money($total, $cur) . ' ' . htmlspecialchars($cur, ENT_QUOTES, 'UTF-8') . ($isPaid ? ' &mdash; paid' : ' due ' . htmlspecialchars($dateDue, ENT_QUOTES, 'UTF-8')) . '</div>
                    <div class="sub">' . ($isPaid ? 'Already paid — keep this invoice for your records.' : 'Please complete payment to receive your license keys.') . '</div>
                 </div>'
              . $itemsHtml
              . '<table class="totals">
                    <tr><td class="label">Subtotal</td><td class="value">' . _pdf_money($subtotal, $cur) . '</td></tr>
                    <tr class="total-row"><td class="label">Total</td><td class="value">' . _pdf_money($total, $cur) . '</td></tr>
                    <tr class="' . ($isPaid ? 'amount-paid' : 'amount-due') . '">
                        <td class="label">' . ($isPaid ? 'Amount paid' : 'Amount due') . '</td>
                        <td class="value">' . _pdf_money($total, $cur) . ' ' . htmlspecialchars($cur, ENT_QUOTES, 'UTF-8') . '</td>
                    </tr>
                 </table>'
              . '<div class="statement"><span class="lbl">Bank statement note:</span> This charge ' . ($isPaid ? 'appears' : 'will appear') . ' as <strong>' . htmlspecialchars($stmtName, ENT_QUOTES, 'UTF-8') . '</strong> on your card statement.</div>';

    $html = _pdf_shell([
        'co'              => $co,
        'logo'            => __DIR__ . '/../assets/images/brand/fivecodelab-wordmark.svg',
        'title'           => 'Invoice',
        'invoice_number'  => $invoiceNo,
        'date_issued'     => $dateIssued,
        'date_due'        => $dateDue,
        'bill_to'         => $billTo,
    ], $bodyHtml);

    $dompdf = _pdf_dompdf();
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('letter', 'portrait');
    // Brand layers: purchased product as soft centre watermark + tiny Office app icon strip + QR
    $heroProductImg = (string)($items[0]['image'] ?? '');
    if ($heroProductImg === '' && !empty($items[0]['product_slug'])) {
        $p = function_exists('get_product') ? get_product($items[0]['product_slug']) : null;
        if ($p && !empty($p['image'])) $heroProductImg = (string)$p['image'];
    }
    $qrPayload = function_exists('site_url')
        ? (rtrim(site_url(), '/') . '/order-lookup.php?o=' . urlencode((string)$order['order_number']) . '&e=' . urlencode((string)($order['email'] ?? '')))
        : '';
    _pdf_apply_brand_layers($dompdf, $heroProductImg, $qrPayload, (string)($order['status'] ?? ''), (string)$order['order_number']);
    $dompdf->render();
    return $dompdf->output();
}

/**
 * Save both PDFs to /uploads/order-pdfs/{order_id}/ and return their
 * absolute paths so send_email() can attach them.  Idempotent — overwrites
 * existing files if called repeatedly for the same order.
 */
function generate_order_pdfs(array $order, array $items): array
{
    $dir = __DIR__ . '/../uploads/order-pdfs/' . (int)($order['id'] ?? 0);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $rcptPath = $dir . '/Receipt-'   . (string)($order['order_number'] ?? 'X') . '.pdf';
    $invPath  = $dir . '/Invoice-'   . (string)($order['order_number'] ?? 'X') . '.pdf';
    try {
        @file_put_contents($rcptPath, generate_receipt_pdf($order, $items));
    } catch (Throwable $e) { @error_log('[pdf receipt] ' . $e->getMessage()); $rcptPath = ''; }
    try {
        @file_put_contents($invPath,  generate_invoice_pdf($order, $items));
    } catch (Throwable $e) { @error_log('[pdf invoice] ' . $e->getMessage()); $invPath  = ''; }
    return array_values(array_filter([$rcptPath, $invPath]));
}
