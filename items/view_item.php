<?php
include(__DIR__ . '/../config/db.php');
include(__DIR__ . '/../includes/auction_helpers.php');
auction_finalize_ended_items($conn);
include(__DIR__ . '/../includes/header.php');

$item_id = intval($_GET['id'] ?? 0);
if (!$item_id) {
    echo '<main><p>No item specified.</p></main>';
    include(__DIR__ . '/../includes/footer.php');
    exit;
}

$stmt = $conn->prepare("SELECT i.*, u.name as seller, w.name as winner_name FROM items i LEFT JOIN users u ON i.user_id=u.id LEFT JOIN users w ON i.winner_id=w.id WHERE i.id=?");
$stmt->bind_param('i', $item_id);
$stmt->execute();
$res = $stmt->get_result();
$item = $res->fetch_assoc();
if (!$item) {
    echo '<main><p>Item not found.</p></main>';
    include(__DIR__ . '/../includes/footer.php');
    exit;
}

$bidStmt = $conn->prepare('SELECT b.bid_amount, u.name FROM bids b LEFT JOIN users u ON b.user_id=u.id WHERE b.item_id=? ORDER BY b.bid_amount DESC LIMIT 1');
$bidStmt->bind_param('i', $item_id);
$bidStmt->execute();
$bidRes = $bidStmt->get_result();
$highest = $bidRes ? $bidRes->fetch_assoc() : null;
$current_price = $highest ? $highest['bid_amount'] : $item['starting_price'];
$highest_bidder = $highest ? $highest['name'] : null;
$watched = false;
if (isset($_SESSION['user_id'])) {
  $watchStmt = $conn->prepare('SELECT id FROM watchlist WHERE user_id=? AND item_id=? LIMIT 1');
  $watchStmt->bind_param('ii', $_SESSION['user_id'], $item_id);
  $watchStmt->execute();
  $watched = (bool) $watchStmt->get_result()->fetch_assoc();
}

$sellerRatingStmt = $conn->prepare('SELECT COUNT(*) AS total, COALESCE(ROUND(AVG(rating), 2), 0) AS average FROM reviews WHERE seller_id=?');
$sellerRatingStmt->bind_param('i', $item['user_id']);
$sellerRatingStmt->execute();
$sellerRating = $sellerRatingStmt->get_result()->fetch_assoc() ?: ['total' => 0, 'average' => 0];

$is_active = true;
if (!empty($item['end_time'])) {
  $now = new DateTime('now');
  $end = new DateTime($item['end_time']);
  if ($now > $end) {
    $is_active = false;
  }
}

$can_review = false;
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] !== $item['user_id']) {
  $participationStmt = $conn->prepare('SELECT id FROM bids WHERE item_id=? AND user_id=? LIMIT 1');
  $participationStmt->bind_param('ii', $item_id, $_SESSION['user_id']);
  $participationStmt->execute();
  $participated = (bool) $participationStmt->get_result()->fetch_assoc();
  $can_review = $participated || !$is_active;
}

?>

<main>
  <div id="item" class="item-page" data-item-id="<?= $item_id ?>">
    <section class="hero item-hero">
      <div class="item-hero-copy">
        <span class="page-chip">Live auction detail</span>
        <h2><?= htmlspecialchars($item['title']) ?></h2>
        <p><?= nl2br(htmlspecialchars($item['description'])) ?></p>
        <div class="auction-status">
          <span class="status-pill active"><?= $is_active ? 'Live auction' : 'Auction ended' ?></span>
          <?php if (!empty($item['category'])): ?><span class="status-pill"><?= htmlspecialchars($item['category']) ?></span><?php endif; ?>
          <span class="status-pill">Seller: <?= htmlspecialchars($item['seller'] ?? 'Unknown') ?></span>
          <span class="status-pill">Rating: <?= number_format((float) ($sellerRating['average'] ?? 0), 2) ?>/5</span>
        </div>
        <?php if (isset($_SESSION['user_id']) && (int) ($_SESSION['user_id'] ?? 0) === (int) ($item['user_id'] ?? 0)): ?>
          <div class="card-actions owner-actions">
            <a class="btn" href="/auction_system/items/edit_item.php?id=<?= $item_id ?>">Edit title, price & image</a>
            <a class="btn secondary" href="/auction_system/user/dashboard.php#manage-images">Manage from dashboard</a>
          </div>
        <?php endif; ?>
      </div>

      <div class="metric-card item-summary-card">
        <h3>At a glance</h3>
        <div class="summary-list">
          <div><span>Current price</span><strong>$<span id="current-price"><?= number_format($current_price,2) ?></span></strong></div>
          <div><span>Highest bidder</span><strong id="highest-bidder"><?= $highest_bidder ? htmlspecialchars($highest_bidder) : 'No bids yet' ?></strong></div>
          <div><span>Time left</span><strong id="time-left" data-end-time="<?= htmlspecialchars($item['end_time'] ?? '') ?>"><?php if (!empty($item['end_time'])) { echo 'Loading...'; } else { echo 'N/A'; } ?></strong></div>
          <div><span>Seller reviews</span><strong><?= (int) ($sellerRating['total'] ?? 0) ?></strong></div>
        </div>
        <?php if (!$is_active): ?>
          <div class="winner-callout">
            <span>Auction winner</span>
            <strong><?= !empty($item['winner_name']) ? htmlspecialchars($item['winner_name']) : 'No bids placed' ?></strong>
            <?php if (!empty($_SESSION['user_id']) && (int) $_SESSION['user_id'] === (int) ($item['winner_id'] ?? 0)): ?>
              <span class="status-pill active">You won</span>
            <?php elseif (!empty($item['winner_name'])): ?>
              <span class="status-pill">Won by another bidder</span>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <section class="item-detail-grid">
      <article class="create-card item-gallery-card">
        <?php $imageUrl = auction_item_image_url($item); ?>
        <?php if (!empty($imageUrl)): ?>
          <div class="item-image-wrapper" style="position:relative;">
            <img class="item-main-image" id="item-main-image" src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($item['title']) ?>">
            <?php if (isset($_SESSION['user_id']) && (int) ($_SESSION['user_id'] ?? 0) === (int) ($item['user_id'] ?? 0)): ?>
              <button id="image-edit-overlay" class="image-edit-overlay" title="Edit item" style="position:absolute;right:10px;top:10px;padding:8px 10px;background:rgba(0,0,0,0.6);color:#fff;border-radius:6px;border:none;cursor:pointer;font-size:14px;z-index:5;">✎ Edit</button>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="item-fallback-art">
            <span class="page-chip">No image uploaded</span>
            <h3><?= htmlspecialchars($item['title']) ?></h3>
            <p><?= htmlspecialchars($item['category'] ?? 'Auction item') ?></p>
          </div>
        <?php endif; ?>

        <div class="item-details-block">
          <h3>Listing details</h3>
          <div class="detail-list">
            <div><span>Seller</span><strong><?= htmlspecialchars($item['seller'] ?? 'Unknown') ?></strong></div>
            <div><span>Seller rating</span><strong><?= number_format((float) ($sellerRating['average'] ?? 0), 2) ?>/5</strong></div>
            <div><span>Review count</span><strong><?= (int) ($sellerRating['total'] ?? 0) ?></strong></div>
            <div><span>Category</span><strong><?= htmlspecialchars($item['category'] ?? 'Uncategorized') ?></strong></div>
          </div>
          <p class="form-note">Bids update in real time, so this page stays fresh without a hard refresh.</p>
        </div>

        <?php if (isset($_SESSION['user_id'])): ?>
          <div class="card-actions">
            <button class="btn secondary watchlist-toggle" data-item-id="<?= $item_id ?>" data-action="<?= $watched ? 'remove' : 'add' ?>">
              <?= $watched ? 'Remove from Watchlist' : 'Add to Watchlist' ?>
            </button>
            <?php if ((int) ($_SESSION['user_id'] ?? 0) === (int) ($item['user_id'] ?? 0)): ?>
              <a class="btn" href="/auction_system/items/edit_item.php?id=<?= $item_id ?>">Edit Item</a>
              <a class="btn secondary" href="/auction_system/items/delete_item.php?id=<?= $item_id ?>">Delete Item</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </article>

      <aside class="create-card item-bid-card">
        <div class="section-head compact-head">
          <div>
            <span class="page-chip">Bid now</span>
            <h3>Place Your Bid</h3>
          </div>
        </div>

        <?php if (isset($_SESSION['user_id'])): ?>
          <?php if ($is_active): ?>
            <form id="bid-form" class="item-action-form">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
              <label>Your bid
                <input type="number" name="amount" id="bid-amount" step="0.01" placeholder="Enter your bid" required>
              </label>
              <button type="submit" class="btn">Place Bid</button>
            </form>
            <div id="bid-status" class="notice"></div>

            <div class="divider"></div>

            <form id="autobid-form" class="item-action-form">
              <label>Auto-bid max
                <input type="number" name="max_bid" id="max-bid" step="0.01" placeholder="Your maximum bid" required>
              </label>
              <button type="submit" class="btn secondary">Enable Auto-Bid</button>
            </form>
            <div id="autobid-status" class="notice"></div>
            <?php if (isset($_SESSION['user_id']) && (int) ($_SESSION['user_id'] ?? 0) === (int) ($item['user_id'] ?? 0)): ?>
              <!-- Inline editor modal for owners: edit attributes and image (with rotate) -->
              <div class="quick-edit-modal" id="view-item-edit-modal" aria-hidden="true">
                <div class="quick-edit-modal-backdrop" data-edit-close></div>
                <div class="quick-edit-modal-content" role="dialog" aria-modal="true" aria-labelledby="view-edit-title">
                  <div class="quick-edit-modal-head">
                    <div>
                      <span class="page-chip">Edit listing</span>
                      <h3 id="view-edit-title">Edit this item</h3>
                      <p class="form-note" id="view-edit-sub">Change title, price, category, description, or replace the image. Use rotate to adjust orientation before saving.</p>
                    </div>
                    <button type="button" class="quick-edit-close" data-edit-close aria-label="Close edit">&times;</button>
                  </div>

                  <form id="view-edit-form" method="POST" enctype="multipart/form-data" class="item-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="item_id" id="view-edit-item-id" value="<?= (int) $item_id ?>">
                    <div class="quick-edit-grid">
                      <div class="form-stack">
                        <label class="full">Title
                          <input type="text" name="title" id="view-edit-title-field" value="<?= htmlspecialchars($item['title']) ?>" required>
                        </label>
                        <label>Category
                          <select name="category" id="view-edit-category-field" required>
                            <?php foreach (['Electronics','Fashion','Home & Garden','Collectibles','Vehicles','Other'] as $cat): ?>
                              <option value="<?= htmlspecialchars($cat) ?>" <?= ($item['category'] ?? '') === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                            <?php endforeach; ?>
                          </select>
                        </label>
                        <label>Start price
                          <input type="number" name="start_price" id="view-edit-start-price" step="0.01" value="<?= htmlspecialchars($item['starting_price']) ?>" required>
                        </label>
                        <label class="full">End time
                          <input type="datetime-local" name="end_time" id="view-edit-end-time" value="<?= !empty($item['end_time']) ? htmlspecialchars(date('Y-m-d\TH:i', strtotime($item['end_time']))) : '' ?>">
                        </label>
                        <label class="full">Description
                          <textarea name="description" id="view-edit-description" rows="5"><?= htmlspecialchars($item['description']) ?></textarea>
                        </label>
                      </div>

                      <div class="quick-edit-side">
                        <div class="quick-edit-preview">
                          <canvas id="view-edit-canvas" style="max-width:100%;border-radius:6px;background:#fff;"></canvas>
                        </div>
                        <p class="form-note">Current listing image</p>
                        <label>Replace image
                          <input type="file" name="image" id="view-edit-image-input" accept="image/*">
                        </label>
                        <div style="display:flex;gap:8px;margin-top:8px;">
                          <button type="button" class="btn secondary" id="view-rotate-left">⟲ Rotate</button>
                          <button type="button" class="btn secondary" id="view-rotate-right">⟳ Rotate</button>
                        </div>
                        <label class="checkbox-row" style="margin-top:8px;">
                          <input type="checkbox" name="remove_image" value="1" id="view-edit-remove-image">
                          <span>Remove current image and use category fallback</span>
                        </label>
                      </div>

                      <div class="card-actions full">
                        <button type="submit" class="btn">Save changes</button>
                        <button type="button" class="btn secondary" data-edit-close>Cancel</button>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
              <script>
                (function(){
                  const openBtn = document.getElementById('image-edit-overlay');
                  const modal = document.getElementById('view-item-edit-modal');
                  if (!openBtn || !modal) return;
                  const closeEls = modal.querySelectorAll('[data-edit-close]');
                  const canvas = document.getElementById('view-edit-canvas');
                  const ctx = canvas.getContext('2d');
                  const img = new Image();
                  img.crossOrigin = 'anonymous';
                  const originalSrc = document.getElementById('item-main-image').src;
                  let rotation = 0; // degrees

                  function openModal(){
                    modal.classList.add('is-open');
                    modal.setAttribute('aria-hidden','false');
                    // load current image into canvas
                    img.onload = () => drawToCanvas();
                    img.src = originalSrc;
                  }

                  function closeModal(){
                    modal.classList.remove('is-open');
                    modal.setAttribute('aria-hidden','true');
                  }

                  openBtn.addEventListener('click', openModal);
                  closeEls.forEach(el => el.addEventListener('click', closeModal));
                  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal(); });

                  function drawToCanvas(){
                    const w = img.naturalWidth;
                    const h = img.naturalHeight;
                    // set canvas size to fit container width while preserving ratio
                    const maxW = 480; // limit
                    const scale = Math.min(1, maxW / w);
                    const cw = Math.round(w * scale);
                    const ch = Math.round(h * scale);
                    if (rotation % 180 !== 0) {
                      canvas.width = ch; canvas.height = cw;
                    } else {
                      canvas.width = cw; canvas.height = ch;
                    }
                    ctx.save();
                    // move origin to center
                    ctx.translate(canvas.width/2, canvas.height/2);
                    ctx.rotate(rotation * Math.PI / 180);
                    // draw image centered
                    if (rotation % 180 !== 0) {
                      ctx.drawImage(img, -ch/2, -cw/2, ch, cw);
                    } else {
                      ctx.drawImage(img, -cw/2, -ch/2, cw, ch);
                    }
                    ctx.restore();
                  }

                  document.getElementById('view-rotate-left').addEventListener('click', () => { rotation = (rotation - 90) % 360; drawToCanvas(); });
                  document.getElementById('view-rotate-right').addEventListener('click', () => { rotation = (rotation + 90) % 360; drawToCanvas(); });

                  const fileInput = document.getElementById('view-edit-image-input');
                  fileInput.addEventListener('change', () => {
                    const f = fileInput.files && fileInput.files[0];
                    if (!f) return;
                    const reader = new FileReader();
                    reader.onload = () => { img.onload = () => { rotation = 0; drawToCanvas(); }; img.src = String(reader.result || ''); };
                    reader.readAsDataURL(f);
                  });

                  // submit via AJAX, if canvas has been modified we send the blob as 'image'
                  const form = document.getElementById('view-edit-form');
                  form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const fd = new FormData(form);
                    // if a file was selected but we rotated or resized via canvas, send canvas blob instead
                    const hasFile = fileInput.files && fileInput.files.length > 0;
                    if (hasFile || rotation !== 0) {
                      // convert canvas to blob and append as image
                      await new Promise((resolve) => canvas.toBlob((blob) => {
                        if (blob) {
                          // give a filename
                          fd.set('image', blob, 'edited-image.png');
                        }
                        resolve();
                      }, 'image/png'));
                    }

                    try {
                      const resp = await fetch('/auction_system/items/edit_item.php', { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
                      const contentType = resp.headers.get('Content-Type') || '';
                      if (contentType.indexOf('application/json') === -1) { const txt = await resp.text(); throw new Error('Server error: ' + txt); }
                      const data = await resp.json();
                      if (!data.success) {
                        alert((data.errors || ['Could not update item']).join('\n'));
                        return;
                      }
                      // update page DOM fields
                      const it = data.item;
                      document.querySelector('.item-hero-copy h2').textContent = it.title;
                      const priceEl = document.getElementById('current-price'); if (priceEl) priceEl.textContent = Number(it.current_price || it.starting_price).toFixed(2);
                      const imgEl = document.getElementById('item-main-image'); if (imgEl && it.image_url) imgEl.src = it.image_url;
                      closeModal();
                      const toast = document.createElement('div'); toast.className='toast toast-success'; toast.textContent = 'Listing updated'; document.body.appendChild(toast); setTimeout(()=>toast.remove(),2000);
                    } catch (err) {
                      console.error('Edit submit error', err);
                      alert(err.message || 'An error occurred');
                    }
                  });
                })();
              </script>
            <?php endif; ?>
          <?php else: ?>
            <p><strong>This auction has ended.</strong></p>
          <?php endif; ?>
        <?php else: ?>
          <p><a href="/auction_system/auth/login.php">Login</a> to place a bid.</p>
        <?php endif; ?>

            <p class="form-note">Auto-bid keeps you competitive up to your maximum without manual updates.</p>
        <p class="form-note">Auto-bid keeps you competitive up to your maximum without manual updates.</p>
        <?php
        // Payment section for auction winner
        if (!$is_active && isset($_SESSION['user_id'])) {
            // Check if the user is the winner
            $isWinner = (int)($_SESSION['user_id'] ?? 0) === (int)($item['winner_id'] ?? 0);
            // Check if payment exists
            $hasPaid = false;
            if ($isWinner) {
                $stmt = $conn->prepare("SELECT id FROM payments WHERE item_id=? AND buyer_id=?");
                $stmt->bind_param('ii', $item_id, $_SESSION['user_id']);
                $stmt->execute();
                $res = $stmt->get_result();
                $hasPaid = (bool)$res->fetch_assoc();
            }

            if ($isWinner && !$hasPaid) {
                echo '<a class="btn" href="/auction_system/payment/index.php?item_id=' . $item_id . '">Proceed to Payment</a>';
            } elseif ($isWinner && $hasPaid) {
                echo '<span class="status-pill active">Payment Complete</span>';
            }
        }
        ?>
      </aside>
    </section>

    <section class="item-history-grid">
      <section class="create-card bid-history">
        <div class="section-head compact-head">
          <div>
            <span class="page-chip">Bid history</span>
            <h3>Activity Feed</h3>
          </div>
        </div>
        <div id="bid-history-list">
          <p>Loading bid history...</p>
        </div>
        <div class="live-feed" aria-live="polite">
          <h4>⚔️ Live Auction Room</h4>
          <div id="live-bid-feed">
            <p>Waiting for live bid events...</p>
          </div>
        </div>
      </section>

      <section class="create-card review-card">
        <div class="section-head compact-head">
          <div>
            <span class="page-chip">Seller reviews</span>
            <h3>Buyer Feedback</h3>
          </div>
        </div>
        <div id="seller-reviews-summary" data-seller-id="<?= (int) $item['user_id'] ?>">
          <p>Loading seller reviews...</p>
        </div>
        <?php if ($can_review): ?>
          <form id="review-form" class="review-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="seller_id" value="<?= (int) $item['user_id'] ?>">
            <label>
              Rating
              <select name="rating" required>
                <option value="5">5 - Excellent</option>
                <option value="4">4 - Good</option>
                <option value="3">3 - Average</option>
                <option value="2">2 - Fair</option>
                <option value="1">1 - Poor</option>
              </select>
            </label>
            <label>
              Comment
              <textarea name="comment" rows="4" placeholder="Share your experience with this seller"></textarea>
            </label>
            <button type="submit" class="btn">Submit Review</button>
          </form>
          <div id="review-status" class="notice"></div>
        <?php else: ?>
          <p class="notice">You can leave a review after participating in the auction or once it ends.</p>
        <?php endif; ?>
      </section>
    </section>
  </div>
</main>

<script>
  const ITEM_ID = <?= json_encode($item_id) ?>;
  const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
  const AUCTION_ACTIVE = <?= json_encode($is_active) ?>;
</script>

<?php include(__DIR__ . '/../includes/footer.php'); ?>
