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

$bidRes = $conn->query("SELECT b.bid_amount, u.name FROM bids b LEFT JOIN users u ON b.user_id=u.id WHERE b.item_id={$item_id} ORDER BY b.bid_amount DESC LIMIT 1");
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
  <div id="item" data-item-id="<?= $item_id ?>">
    <?php if (!empty($item['image_url'])): ?>
      <img src="/auction_system/<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['title']) ?>">
    <?php endif; ?>
    <h2><?= htmlspecialchars($item['title']) ?></h2>
    <p><?= nl2br(htmlspecialchars($item['description'])) ?></p>
    <p>Seller: <?= htmlspecialchars($item['seller'] ?? 'Unknown') ?></p>
    <?php if (!empty($item['category'])): ?>
      <p>Category: <strong><?= htmlspecialchars($item['category']) ?></strong></p>
    <?php endif; ?>
    <p>
      Seller rating:
      <strong><?= number_format((float) ($sellerRating['average'] ?? 0), 2) ?>/5</strong>
      <span class="muted">(<?= (int) ($sellerRating['total'] ?? 0) ?> reviews)</span>
    </p>
    <p>Current Price: $<span id="current-price"><?= number_format($current_price,2) ?></span></p>
    <p>Highest Bidder: <span id="highest-bidder"><?= $highest_bidder ? htmlspecialchars($highest_bidder) : 'No bids yet' ?></span></p>
    <p>Time left: <span id="time-left" data-end-time="<?= htmlspecialchars($item['end_time'] ?? '') ?>"><?php if (!empty($item['end_time'])) { echo 'Loading...'; } else { echo 'N/A'; } ?></span></p>
    <?php if (!$is_active): ?>
      <p>
        Auction Winner:
        <strong>
          <?= !empty($item['winner_name']) ? htmlspecialchars($item['winner_name']) : 'No bids placed' ?>
        </strong>
        <?php if (!empty($_SESSION['user_id']) && (int) $_SESSION['user_id'] === (int) ($item['winner_id'] ?? 0)): ?>
          <span class="status-pill active">You won</span>
        <?php elseif (!empty($item['winner_name'])): ?>
          <span class="status-pill ended">Won by another bidder</span>
        <?php endif; ?>
      </p>
    <?php endif; ?>

    <?php if (isset($_SESSION['user_id'])): ?>
      <?php if ($is_active): ?>
      <form id="bid-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="number" name="amount" id="bid-amount" step="0.01" placeholder="Your bid" required>
        <button type="submit">Place Bid</button>
      </form>

      <h3>Auto-bid (Max bid)
        <small style="font-weight:normal">Set a maximum automatic bid. System will outbid others up to your max.</small>
      </h3>
      <form id="autobid-form">
        <input type="number" name="max_bid" id="max-bid" step="0.01" placeholder="Your maximum bid" required>
        <button type="submit">Enable Auto-Bid</button>
      </form>
      <div id="autobid-status"></div>
      <?php else: ?>
        <p><strong>This auction has ended.</strong></p>
      <?php endif; ?>
    <?php else: ?>
      <p><a href="/auction_system/auth/login.php">Login</a> to place a bid.</p>
    <?php endif; ?>

    <?php if (isset($_SESSION['user_id'])): ?>
      <p class="card-actions" style="margin-top: 14px;">
        <button class="btn secondary watchlist-toggle" data-item-id="<?= $item_id ?>" data-action="<?= $watched ? 'remove' : 'add' ?>">
          <?= $watched ? 'Remove from Watchlist' : 'Add to Watchlist' ?>
        </button>
        <?php if ((int) ($_SESSION['user_id'] ?? 0) === (int) ($item['user_id'] ?? 0)): ?>
          <a class="btn" href="/auction_system/items/edit_item.php?id=<?= $item_id ?>">Edit Item</a>
          <a class="btn secondary" href="/auction_system/items/delete_item.php?id=<?= $item_id ?>">Delete Item</a>
        <?php endif; ?>
      </p>
    <?php endif; ?>

    <section class="bid-history">
      <h3>Bid History</h3>
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
      <h3>Seller Reviews</h3>
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
  </div>
</main>

<script>
  const ITEM_ID = <?= json_encode($item_id) ?>;
  const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
  const AUCTION_ACTIVE = <?= json_encode($is_active) ?>;
</script>

<?php include(__DIR__ . '/../includes/footer.php'); ?>
