<?php
// =====================================================================
//  Public order lookup — opened from the QR code in the bottom-right
//  corner of every Receipt / Invoice PDF.  Requires both the order
//  number AND the customer email (basic anti-enumeration protection).
//
//  Anyone holding a printed copy can scan → instantly pull a fresh
//  digital receipt + invoice PDF.  Handy for accountants/auditors.
// =====================================================================
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/pdf.php';

$pdo = db();

$rawO = trim((string)($_GET['o'] ?? ''));
$rawE = strtolower(trim((string)($_GET['e'] ?? '')));

$order = null;
$items = [];
$lookupErr = '';

if ($rawO !== '' && $rawE !== '') {
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE order_number = ? AND LOWER(email) = ? LIMIT 1');
    $stmt->execute([$rawO, $rawE]);
    $order = $stmt->fetch();
    if (!$order) {
        $lookupErr = 'No order matches that combination of order number and email. Please double-check both fields, or contact support.';
    } else {
        $it = $pdo->prepare('SELECT oi.*, p.image AS prod_image, p.category, p.platform
                              FROM order_items oi
                              LEFT JOIN products p ON p.slug = oi.product_slug
                             WHERE oi.order_id = ?
                             ORDER BY oi.id');
        $it->execute([$order['id']]);
        $items = $it->fetchAll();

        // Allow on-demand PDF download
        if (isset($_GET['download'])) {
            $type = $_GET['download'] === 'invoice' ? 'invoice' : 'receipt';
            $itemsForPdf = array_map(function ($r) {
                $r['image'] = $r['prod_image'] ?? null;
                return $r;
            }, $items);
            $bin = $type === 'invoice'
                ? generate_invoice_pdf($order, $itemsForPdf)
                : generate_receipt_pdf($order, $itemsForPdf, null);
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . ucfirst($type) . '-' . $order['order_number'] . '.pdf"');
            header('Content-Length: ' . strlen($bin));
            echo $bin;
            exit;
        }
    }
} elseif ($rawO !== '' || $rawE !== '') {
    $lookupErr = 'Please provide both the order number and the email used to place the order.';
}

$pageTitle = ($order ? 'Order ' . esc($order['order_number']) : 'Order Lookup') . ' · ' . SITE_BRAND;
$pageDescription = 'Verify a ' . SITE_BRAND . ' order, view its details and download a fresh receipt or invoice PDF.';
$noIndex = true; // never let search engines index a customer-specific lookup page
include __DIR__ . '/includes/header.php';
?>

<section class="py-5">
  <div class="container" style="max-width: 820px;">
    <div class="text-center mb-4">
      <span class="hero-badge mb-2"><i class="bi bi-qr-code-scan me-1"></i>Order Lookup</span>
      <h1 class="display-6 fw-bold mt-2">Find your order</h1>
      <p class="text-secondary">Enter your order number + email to pull a fresh digital receipt and invoice. The QR code on your PDF auto-fills both fields.</p>
    </div>

    <form method="get" class="card p-4 shadow-sm mb-4" data-testid="order-lookup-form">
      <div class="row g-3">
        <div class="col-md-6">
          <label for="ol-o" class="form-label small fw-semibold text-muted text-uppercase" style="letter-spacing:.12em;">Order number</label>
          <input id="ol-o" type="text" name="o" required class="form-control" placeholder="e.g. MVT-DEMO-001" value="<?= esc($rawO) ?>" data-testid="order-lookup-order">
        </div>
        <div class="col-md-6">
          <label for="ol-e" class="form-label small fw-semibold text-muted text-uppercase" style="letter-spacing:.12em;">Email used at checkout</label>
          <input id="ol-e" type="email" name="e" required class="form-control" placeholder="you@email.com" value="<?= esc($rawE) ?>" data-testid="order-lookup-email" autocomplete="email">
        </div>
        <div class="col-12 d-flex gap-2 mt-2">
          <button class="btn btn-primary rounded-pill px-4" type="submit" data-testid="order-lookup-submit">
            <i class="bi bi-search me-1"></i>Find my order
          </button>
          <a href="contact.php" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="bi bi-life-preserver me-1"></i>Contact support
          </a>
        </div>
      </div>
    </form>

    <?php if ($lookupErr): ?>
      <div class="alert alert-warning" data-testid="order-lookup-error"><i class="bi bi-exclamation-triangle me-2"></i><?= esc($lookupErr) ?></div>
    <?php endif; ?>

    <?php if ($order): ?>
      <?php
        $orderDate = !empty($order['paid_at']) ? $order['paid_at'] : $order['created_at'];
        $statusBadge = strtolower((string)$order['status']) === 'paid' ? 'success' : (strtolower((string)$order['status']) === 'pending' ? 'warning' : 'secondary');
        $totalPaid = (float)($order['total'] ?? 0);
      ?>
      <div class="card shadow-sm overflow-hidden" data-testid="order-lookup-result">
        <div class="card-body p-4 p-md-5">
          <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
            <div>
              <small class="text-muted text-uppercase fw-bold" style="letter-spacing:.18em;">Order</small>
              <h2 class="fw-bold mb-1"><?= esc($order['order_number']) ?></h2>
              <div class="small text-muted">Placed <?= esc(date('M j, Y', strtotime((string)$orderDate) ?: time())) ?></div>
            </div>
            <span class="badge text-bg-<?= $statusBadge ?> fs-6 px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i><?= esc(ucfirst((string)$order['status'])) ?></span>
          </div>

          <hr class="my-3">
          <div class="row g-3">
            <div class="col-md-6">
              <small class="text-muted text-uppercase fw-bold" style="letter-spacing:.14em;">Customer</small>
              <div class="fw-semibold"><?= esc(trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')) ?: 'Customer') ?></div>
              <div class="small text-muted"><?= esc($order['email']) ?></div>
            </div>
            <div class="col-md-6 text-md-end">
              <small class="text-muted text-uppercase fw-bold" style="letter-spacing:.14em;">Total</small>
              <div class="fs-3 fw-bold" style="color:var(--pp-blue-dark,#003087);">$<?= number_format($totalPaid, 2) ?> <small class="text-muted fs-6">USD</small></div>
            </div>
          </div>

          <hr class="my-4">
          <small class="text-muted text-uppercase fw-bold mb-2 d-block" style="letter-spacing:.14em;">Items</small>
          <ul class="list-unstyled mb-0">
            <?php foreach ($items as $it): ?>
              <li class="d-flex align-items-center gap-3 py-2 border-bottom">
                <?php if (!empty($it['prod_image'])): ?>
                  <img src="<?= esc($it['prod_image']) ?>" alt="<?= esc($it['name']) ?>" width="48" height="48" style="border-radius:8px;border:1px solid #e6e9ec;object-fit:contain;padding:4px;background:#fff;">
                <?php endif; ?>
                <div class="flex-grow-1">
                  <div class="fw-semibold"><?= esc($it['name']) ?></div>
                  <div class="small text-muted">Qty <?= (int)$it['qty'] ?> · $<?= number_format((float)$it['price'], 2) ?> each</div>
                </div>
                <div class="fw-bold text-end">$<?= number_format((float)$it['price'] * (int)$it['qty'], 2) ?></div>
              </li>
            <?php endforeach; ?>
          </ul>

          <div class="d-flex flex-wrap gap-2 mt-4">
            <a href="?o=<?= urlencode($order['order_number']) ?>&e=<?= urlencode($order['email']) ?>&download=receipt"
               class="btn btn-primary rounded-pill px-4" data-testid="download-receipt-pdf">
              <i class="bi bi-file-earmark-pdf me-1"></i>Download Receipt PDF
            </a>
            <a href="?o=<?= urlencode($order['order_number']) ?>&e=<?= urlencode($order['email']) ?>&download=invoice"
               class="btn btn-outline-primary rounded-pill px-4" data-testid="download-invoice-pdf">
              <i class="bi bi-file-earmark-text me-1"></i>Download Invoice PDF
            </a>
            <a href="contact.php" class="btn btn-outline-secondary rounded-pill px-4">
              <i class="bi bi-headset me-1"></i>Need help?
            </a>
          </div>
        </div>
      </div>

      <p class="small text-muted text-center mt-4">
        <i class="bi bi-shield-check me-1"></i>
        For your security, the order number and email must match exactly.
        These PDFs are always generated fresh — your latest payment + license info is included.
      </p>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
