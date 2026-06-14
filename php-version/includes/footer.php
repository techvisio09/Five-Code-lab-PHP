<?php /* Footer + chat widget + scripts */ ?>
<footer class="footer-dark footer-elegant pt-0 pb-4 mt-5" itemscope itemtype="https://schema.org/WPFooter" role="contentinfo">

  <!-- Newsletter band — refined PayPal-elegant layout -->
  <div class="footer-newsletter">
    <div class="container py-5">
      <div class="row align-items-center g-4">
        <div class="col-lg-7 text-center text-lg-start">
          <span class="footer-eyebrow"><i class="bi bi-envelope-paper-heart me-2"></i>Insider Deals</span>
          <h3 class="text-white fw-bold mb-2 mt-2 footer-newsletter-title">Join 50,000+ savers and save up to <span class="footer-savings-pill">81% off</span></h3>
          <p class="footer-newsletter-sub mb-0">One short email per week — fresh Microsoft Office + antivirus deals, activation tips, no spam.</p>
        </div>
        <div class="col-lg-5">
          <form class="footer-newsletter-form d-flex gap-2" onsubmit="subscribeNewsletter(event)" aria-label="Newsletter signup">
            <label class="visually-hidden" for="footer-newsletter-email">Email address</label>
            <input id="footer-newsletter-email" type="email" required class="form-control rounded-pill px-4 footer-newsletter-input" placeholder="you@email.com" data-testid="newsletter-email" autocomplete="email">
            <button class="btn footer-newsletter-btn rounded-pill px-4 fw-bold" type="submit" data-testid="newsletter-join">Join free <i class="bi bi-arrow-right ms-1"></i></button>
          </form>
          <div class="d-flex justify-content-center justify-content-lg-end gap-3 flex-wrap mt-3 footer-newsletter-trust">
            <span><i class="bi bi-shield-lock-fill"></i> No spam</span>
            <span><i class="bi bi-x-circle"></i> Unsubscribe anytime</span>
            <span><i class="bi bi-patch-check-fill"></i> Genuine deals</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="container pt-5">
    <div class="row g-4 g-lg-5">
      <!-- Brand column -->
      <div class="col-lg-4" itemscope itemtype="https://schema.org/Organization">
        <a href="index.php" class="d-inline-flex align-items-center gap-2 mb-3 text-decoration-none" itemprop="url" aria-label="<?= esc($brandName) ?> — Home" data-testid="footer-brand-link">
          <?php if (!empty($brandLogo)): ?>
            <img src="<?= esc($brandLogo) ?>" alt="<?= esc($brandName) ?> logo" style="height:46px;width:auto;max-width:160px;object-fit:contain;" itemprop="logo">
          <?php else: ?>
            <?= render_logo(46) ?>
          <?php endif; ?>
          <span>
            <?php
              $bnParts = preg_split('/\s+/', trim($brandName));
              $bnLast  = array_pop($bnParts) ?: '';
              $bnHead  = implode(' ', $bnParts);
            ?>
            <span class="brand-text d-block lh-1 text-white" itemprop="name"><?= esc($bnHead) ?><?php if ($bnHead !== ''): ?> <?php endif; ?><span class="brand-grad"><?= esc($bnLast) ?></span></span>
            <small class="brand-tag">AUTHORIZED RESELLER</small>
          </span>
        </a>
        <p class="footer-brand-tagline" itemprop="description">Your trusted source for <strong>genuine Microsoft Office, Windows and antivirus</strong> license keys at up to 81% off. Instant digital delivery in 15-30 minutes, lifetime activation, expert support.</p>

        <ul class="footer-contact list-unstyled mb-3">
          <li><span class="footer-contact-icon"><i class="bi bi-telephone-fill"></i></span><a href="tel:<?= esc($brandPhone) ?>" itemprop="telephone"><?= esc($brandPhone) ?></a></li>
          <li><span class="footer-contact-icon"><i class="bi bi-envelope-fill"></i></span><a href="mailto:<?= esc($brandEmail) ?>" itemprop="email"><?= esc($brandEmail) ?></a></li>
          <li><span class="footer-contact-icon"><i class="bi bi-envelope-paper"></i></span><a href="<?= defined('SITE_WEBMAIL') ? esc(SITE_WEBMAIL) : '#' ?>" target="_blank" rel="noopener" data-testid="footer-webmail-link">Webmail Login <i class="bi bi-box-arrow-up-right small ms-1"></i></a></li>
          <li itemprop="address" itemscope itemtype="https://schema.org/PostalAddress"><span class="footer-contact-icon"><i class="bi bi-geo-alt-fill"></i></span><span itemprop="streetAddress"><?= esc($brandAddress) ?></span></li>
          <li><span class="footer-contact-icon"><i class="bi bi-clock-fill"></i></span><?= SITE_HOURS ?></li>
        </ul>

        <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($brandAddress) ?>" target="_blank" rel="noopener" class="footer-map-btn" data-testid="footer-gmap-btn" aria-label="Open address in Google Maps">
          <span class="gmap-pin"><i class="bi bi-geo-alt-fill"></i></span>
          <span class="footer-map-btn-text">
            <small class="d-block">View location on</small>
            <strong>Google Maps</strong>
          </span>
          <i class="bi bi-arrow-up-right ms-2"></i>
        </a>

        <div class="footer-social mt-3" aria-label="Social media">
          <?php foreach ([['Facebook','bi-facebook'],['Twitter','bi-twitter-x'],['LinkedIn','bi-linkedin'],['Instagram','bi-instagram']] as [$sn,$si]): ?>
            <a href="#top" aria-label="<?= esc($brandName) ?> on <?= $sn ?>" class="social-circle" rel="noopener" target="_blank"><i class="bi <?= $si ?>"></i></a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Products -->
      <div class="col-lg-2 col-md-4 col-6">
        <h6 class="text-white fw-bold mb-3">Products</h6>
        <ul class="list-unstyled small d-grid gap-2">
          <li><a href="category.php?slug=office-2024-pc">Microsoft Office 2024</a></li>
          <li><a href="category.php?slug=office-2021-pc">Microsoft Office 2021</a></li>
          <li><a href="category.php?slug=office-2019-pc">Microsoft Office 2019</a></li>
          <li><a href="category.php?slug=microsoft-project">Microsoft Project</a></li>
          <li><a href="category.php?slug=microsoft-visio">Microsoft Visio</a></li>
          <li><a href="category.php?slug=office-mac">Office for Mac</a></li>
          <li><a href="category.php?slug=windows">Windows OS</a></li>
        </ul>
      </div>

      <!-- Support -->
      <div class="col-lg-3 col-md-4 col-6">
        <h6 class="text-white fw-bold mb-3">Support</h6>
        <ul class="list-unstyled small d-grid gap-2">
          <li><a href="account.php">My Account</a></li>
          <li><a href="order-history.php" data-testid="footer-order-history-link">Order History &amp; Receipts</a></li>
          <li><a href="support.php">Support Center</a></li>
          <li><a href="page.php?slug=help-center">Help Center</a></li>
          <li><a href="page.php?slug=installation-guide">Installation Guide</a></li>
          <li><a href="page.php?slug=activation-help">Activation Help</a></li>
          <li><a href="page.php?slug=faqs">FAQs</a></li>
          <li><a href="contact.php">Contact Us</a></li>
          <li><a href="returns.php">Returns &amp; Refunds</a></li>
          <li><a href="<?= defined('SITE_WEBMAIL') ? esc(SITE_WEBMAIL) : '#' ?>" target="_blank" rel="noopener" data-testid="footer-support-webmail-link"><i class="bi bi-envelope-paper me-1"></i>Webmail</a></li>
        </ul>
      </div>

      <!-- Company -->
      <div class="col-lg-3 col-md-4 col-6">
        <h6 class="text-white fw-bold mb-3">Company</h6>
        <ul class="list-unstyled small d-grid gap-2">
          <li><a href="about-us.php">About Us</a></li>
          <li><a href="page.php?slug=why-choose-us">Why Choose Us</a></li>
          <li><a href="reviews.php">Customer Reviews</a></li>
          <li><a href="blog.php">Blog</a></li>
          <li><a href="affiliate.php">Affiliate Program</a></li>
        </ul>
      </div>
    </div>

    <!-- Secure payments / reviews band -->
    <hr class="border-secondary my-4">
    <div class="row g-4 align-items-center text-center text-md-start">
      <div class="col-md-5">
        <div class="text-white small fw-bold mb-2"><i class="bi bi-lock-fill text-success me-1"></i>Secure Payments</div>
        <div class="d-flex gap-3 small mb-3 flex-wrap justify-content-center justify-content-md-start">
          <span><i class="bi bi-lock-fill text-success me-1"></i>SSL Encrypted Checkout</span>
          <span><i class="bi bi-shield-fill-check text-info me-1"></i>Secure Encrypted Transactions</span>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-center justify-content-md-start" data-testid="footer-pay-icons">
          <?= render_payment_icons() ?>
        </div>
      </div>
      <div class="col-md-3 text-md-center">
        <div class="fs-6"><span class="text-warning">★★★★★</span> <span class="text-white fw-bold">4.6</span><span class="small">/5</span></div>
        <div class="small">5,519+ verified reviews</div>
        <a href="reviews.php" class="small text-info" data-testid="footer-see-reviews">See all reviews →</a>
      </div>
      <div class="col-md-4 text-md-end">
        <div class="d-flex gap-2 justify-content-center justify-content-md-end mb-2" data-testid="footer-trust-badges">
          <img src="assets/images/badges/microsoft-verified.svg" alt="Microsoft Verified" class="trust-badge-img" loading="lazy">
          <img src="assets/images/badges/pci-compliant.svg" alt="PCI Compliant" class="trust-badge-img" loading="lazy">
        </div>
        <small><i class="bi bi-award-fill text-warning me-1"></i>Authorized Reseller • 2+ Years</small>
      </div>
    </div>

    <!-- Trademark + legal -->
    <hr class="border-secondary my-4">
    <p class="small text-center mx-auto" style="max-width: 760px;">Microsoft®, Office®, and Windows® are trademarks of Microsoft Corporation. <?= esc($brandName) ?> is independent of and not affiliated with Microsoft Corporation.</p>
    <nav class="legal-links d-flex justify-content-center flex-wrap gap-2 small mb-3" aria-label="Legal & policies">
      <?php
      $legal = [
          ['Privacy Policy', 'page.php?slug=privacy-policy'], ['Terms of Service', 'page.php?slug=terms-of-service'],
          ['Refund Policy', 'page.php?slug=refund-policy'], ['Shipping & Delivery', 'page.php?slug=shipping-delivery'],
          ['Payment Policy', 'page.php?slug=payment-policy'], ['Cookie Policy', 'page.php?slug=cookie-policy'],
          ['Do Not Sell My Info', 'page.php?slug=do-not-sell'], ['Disclaimer', 'page.php?slug=disclaimer'], ['Sitemap', 'sitemap.php'],
      ];
      foreach ($legal as $idx => [$ll, $lh]): ?>
        <a href="<?= $lh ?>"><?= $ll ?></a><?= $idx < count($legal) - 1 ? '<span class="legal-sep">|</span>' : '' ?>
      <?php endforeach; ?>
    </nav>
    <div class="footer-copyright">© <?= date('Y') ?> <strong><?= esc($brandName) ?></strong>. All rights reserved.</div>
  </div>
</footer>

<!-- AI chat widget -->
<button id="chat-bubble" onclick="toggleChat()" aria-label="Open chat" data-testid="chat-bubble">
  <i class="bi bi-chat-dots"></i>
  <!-- Tiny bell + unread count overlay; surfaces the moment an admin replies
       while the panel is closed.  Disappears once the customer opens chat
       or starts typing a reply. -->
  <span id="chat-bell" class="chat-bell" style="display:none;" data-testid="chat-bell" aria-hidden="true">
    <i class="bi bi-bell-fill"></i>
    <span id="chat-bell-count" class="chat-bell-count" data-testid="chat-bell-count">1</span>
  </span>
</button>
<!-- Messenger-style admin-reply preview — slides in to the LEFT of the
     chat bubble whenever an admin reply lands while the panel is closed,
     so the customer can see what the agent said before opening chat.
     Clicking it opens the chat immediately.  Auto-fades when the chat
     opens or the customer starts replying. -->
<div id="chat-msg-preview" class="chat-msg-preview" style="display:none;" onclick="openChatFromPreview()" data-testid="chat-msg-preview" role="button" tabindex="0">
  <div class="chat-msg-preview-head">
    <span class="chat-msg-preview-avatar"><i class="bi bi-headset"></i></span>
    <div class="chat-msg-preview-meta">
      <div class="chat-msg-preview-name">Fivecodelab Support</div>
      <div class="chat-msg-preview-sub"><span class="chat-online-dot"></span>just now</div>
    </div>
    <button class="chat-msg-preview-close" type="button" onclick="event.stopPropagation(); hideChatMsgPreview();" aria-label="Dismiss preview" data-testid="chat-msg-preview-close"><i class="bi bi-x"></i></button>
  </div>
  <div class="chat-msg-preview-body" id="chat-msg-preview-body" data-testid="chat-msg-preview-body">—</div>
  <div class="chat-msg-preview-cta">Tap to reply →</div>
</div>
<div id="chat-panel" data-testid="chat-panel">
  <div id="chat-head" class="d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-2">
      <span class="chat-avatar"><i class="bi bi-stars"></i></span>
      <div class="lh-sm">
        <div class="chat-head-name">Max · AI Assistant</div>
        <small class="chat-head-sub"><span class="chat-online-dot"></span>Online · typically replies in seconds</small>
      </div>
    </div>
    <button class="btn btn-sm btn-link p-0 text-white" onclick="toggleChat()" aria-label="Close chat" data-testid="chat-close"><i class="bi bi-x-lg"></i></button>
  </div>
  <div id="chat-body">
    <div class="chat-msg bot" data-testid="chat-default-message">Hi there! I'm here to help with products, pricing, activation or anything else you need. What can I look up for you?</div>
    <div class="chat-chips" id="chat-chips" data-testid="chat-chips">
      <button class="chat-chip" onclick="quickAsk('Which Office is right for my Mac?')" data-testid="chat-chip-mac"><i class="bi bi-apple me-1"></i>Office for Mac</button>
      <button class="chat-chip" onclick="quickAsk('What is the best deal on Office 2024 right now?')" data-testid="chat-chip-deal"><i class="bi bi-tags me-1"></i>Best deals on Office 2024</button>
      <button class="chat-chip" onclick="quickAsk('How do I activate my license key after purchase?')" data-testid="chat-chip-activate"><i class="bi bi-key me-1"></i>Activation help</button>
      <button class="chat-chip" onclick="quickAsk('Do your licenses expire or need a subscription?')" data-testid="chat-chip-license"><i class="bi bi-infinity me-1"></i>License validity</button>
    </div>
    <div id="chat-lead-form" class="chat-lead-card" style="display:none;" data-testid="chat-lead-form">
      <div id="chat-lead-nudge" class="chat-lead-nudge" style="display:none;" data-testid="chat-lead-nudge">
        <i class="bi bi-lightning-charge-fill"></i>
        <span><strong>Don't lose this</strong> — agent on the way. Share your details so we don't miss you ↓</span>
      </div>
      <div class="chat-lead-title">Share your name, email and phone — we'll connect you with a live agent right away.</div>
      <input id="lead-name"  class="form-control form-control-sm chat-lead-input" placeholder="Full name"      data-testid="lead-name" autocomplete="name">
      <input id="lead-email" type="email" class="form-control form-control-sm chat-lead-input" placeholder="Email address" data-testid="lead-email" autocomplete="email">
      <input id="lead-phone" class="form-control form-control-sm chat-lead-input" placeholder="Phone number"   data-testid="lead-phone" autocomplete="tel">
      <button type="button" class="btn btn-sm chat-lead-cta-chat chat-lead-cta-primary" onclick="submitLead('chat')" data-testid="lead-chat-btn"><i class="bi bi-chat-dots-fill me-1"></i>Connect me with an agent</button>
      <a href="tel:<?= esc($brandPhone) ?>" class="btn btn-sm chat-lead-cta-alt" onclick="submitLead(false)" data-testid="lead-call-btn"><i class="bi bi-telephone me-1"></i>Or call us at <?= esc($brandPhone) ?></a>
    </div>
    <!-- ProAssist install-call scheduler card (hidden until JS detects a ProAssist lead). -->
    <div id="pa-sched-card" class="pa-sched-card" style="display:none;" data-testid="pa-sched-card">
      <div class="pa-sched-header">
        <i class="bi bi-calendar2-week"></i>
        <div>
          <div class="pa-sched-title" data-testid="pa-sched-title">Schedule your install call</div>
          <div class="pa-sched-sub" data-testid="pa-sched-sub">Pick a 30-minute slot — Mon-Sat · 9 AM – 6 PM EST</div>
        </div>
      </div>
      <div class="pa-sched-step" id="pa-sched-step-date">
        <div class="pa-sched-step-label">1. Choose a date</div>
        <div class="pa-sched-dates" id="pa-sched-dates" data-testid="pa-sched-dates"><!-- date pills injected by JS --></div>
      </div>
      <div class="pa-sched-step" id="pa-sched-step-time" style="display:none;">
        <div class="pa-sched-step-label">2. Choose a time <span class="pa-sched-tz">EST</span></div>
        <div class="pa-sched-times" id="pa-sched-times" data-testid="pa-sched-times"><!-- time pills injected by JS --></div>
        <button type="button" class="pa-sched-back" onclick="paSchedBackToDates()" data-testid="pa-sched-back"><i class="bi bi-arrow-left me-1"></i>Pick a different date</button>
      </div>
      <div class="pa-sched-error" id="pa-sched-error" style="display:none;" data-testid="pa-sched-error"></div>
    </div>
    <!-- ProAssist booked confirmation card (shown after booking, hides the picker). -->
    <div id="pa-sched-confirm" class="pa-sched-confirm" style="display:none;" data-testid="pa-sched-confirm">
      <div class="pa-sched-confirm-icon"><i class="bi bi-check2-circle"></i></div>
      <div class="pa-sched-confirm-title">Install call scheduled</div>
      <div class="pa-sched-confirm-when" id="pa-sched-confirm-when" data-testid="pa-sched-confirm-when">—</div>
      <button type="button" class="pa-sched-reschedule" onclick="paSchedReschedule()" data-testid="pa-sched-reschedule"><i class="bi bi-arrow-repeat me-1"></i>Reschedule</button>
    </div>
  </div>
  <div id="chat-typing" class="chat-typing" style="display:none;" data-testid="chat-admin-typing">
    <div class="chat-typing-bubble">
      <span class="chat-typing-dot"></span>
      <span class="chat-typing-dot"></span>
      <span class="chat-typing-dot"></span>
      <span class="chat-typing-text">Live agent is typing…</span>
    </div>
  </div>
  <form class="chat-input-row d-flex align-items-center gap-2 p-2" onsubmit="sendChat(event)">
    <input id="chat-input" class="form-control form-control-sm chat-input" placeholder="Type a message…" autocomplete="off" data-testid="chat-input">
    <button class="btn chat-send-btn" type="submit" aria-label="Send" data-testid="chat-send"><i class="bi bi-send-fill"></i></button>
  </form>
  <div class="chat-talk-band" data-testid="chat-talk-band"><i class="bi bi-headset me-1"></i>Prefer to talk?<span class="ttf-sep">·</span><?= esc(SITE_HOURS) ?><span class="ttf-sep">·</span><a href="tel:<?= esc($brandPhone) ?>"><?= esc($brandPhone) ?></a></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
