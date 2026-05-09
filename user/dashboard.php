<?php
include(__DIR__ . '/../config/db.php');
include(__DIR__ . '/../includes/auction_helpers.php');
auction_finalize_ended_items($conn);
include(__DIR__ . '/../includes/header.php');

if (!isset($_SESSION['user_id'])) {
    echo '<main><p>Please <a href="/auction_system/auth/login.php">login</a> to view your dashboard.</p></main>';
    include(__DIR__ . '/../includes/footer.php');
    exit;
}

$user_id = intval($_SESSION['user_id']);

$userStmt = $conn->prepare('SELECT name, email, created_at FROM users WHERE id=? LIMIT 1');
$userStmt->bind_param('i', $user_id);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();

$itemsStmt = $conn->prepare(
  'SELECT i.id, i.title, i.category, i.current_price, i.starting_price, i.end_time, i.image_url, i.status,
            (SELECT COUNT(*) FROM bids b WHERE b.item_id = i.id) AS bid_count,
        (SELECT MAX(bid_amount) FROM bids b WHERE b.item_id = i.id) AS top_bid
     FROM items i WHERE i.user_id=? ORDER BY i.created_at DESC'
);
$itemsStmt->bind_param('i', $user_id);
$itemsStmt->execute();
$sellerItems = $itemsStmt->get_result();

$watchlistCountStmt = $conn->prepare('SELECT COUNT(*) AS total FROM watchlist WHERE user_id=?');
$watchlistCountStmt->bind_param('i', $user_id);
$watchlistCountStmt->execute();
$watchlistCount = (int)($watchlistCountStmt->get_result()->fetch_assoc()['total'] ?? 0);

$myBidsStmt = $conn->prepare('SELECT COUNT(*) AS total FROM bids WHERE user_id=?');
$myBidsStmt->bind_param('i', $user_id);
$myBidsStmt->execute();
$myBidCount = (int)($myBidsStmt->get_result()->fetch_assoc()['total'] ?? 0);

$endedStmt = $conn->prepare(
  "SELECT COUNT(*) AS total, COALESCE(SUM(current_price), 0) AS earnings
   FROM items
   WHERE user_id=? AND (status='ended' OR (end_time IS NOT NULL AND end_time < NOW()))"
);
$endedStmt->bind_param('i', $user_id);
$endedStmt->execute();
$endedStats = $endedStmt->get_result()->fetch_assoc() ?: ['total' => 0, 'earnings' => 0];
$endedCount = (int) ($endedStats['total'] ?? 0);
$estimatedEarnings = (float) ($endedStats['earnings'] ?? 0);
?>

<main>
  <section class="hero" style="margin-bottom: 22px;">
    <div>
      <h2>Seller Dashboard</h2>
      <p>Track your auctions, bids, and engagement from one clean control panel.</p>
    </div>
    <div class="metric-card">
      <h3><?= htmlspecialchars($user['name'] ?? 'User') ?></h3>
      <p><?= htmlspecialchars($user['email'] ?? '') ?></p>
      <div class="auction-status">
        <span class="status-pill active"><?= $watchlistCount ?> Watchlisted</span>
        <span class="status-pill active"><?= $myBidCount ?> Bids Placed</span>
      </div>
    </div>
  </section>

  <section class="feature-grid">
    <article class="feature">
      <h3>Active Listings</h3>
      <p><?= (int)$sellerItems->num_rows ?> items listed by you.</p>
    </article>
    <article class="feature">
      <h3>Watchlist</h3>
      <p><?= $watchlistCount ?> saved items for later.</p>
    </article>
    <article class="feature">
      <h3>Bidding Activity</h3>
      <p><?= $myBidCount ?> total bids placed across all auctions.</p>
    </article>
    <article class="feature">
      <h3>Sold / Ended Auctions</h3>
      <p><?= $endedCount ?> auctions finished with an estimated earnings total of $<?= number_format($estimatedEarnings, 2) ?>.</p>
    </article>
  </section>

  <section style="margin-top: 24px;">
    <h2>Your Auctions</h2>
    <?php if ($sellerItems->num_rows === 0): ?>
      <div class="create-card"><p>You haven’t posted any items yet. Start your first auction from the sell page.</p></div>
    <?php else: ?>
      <div class="auction-grid">
        <?php while ($row = $sellerItems->fetch_assoc()): ?>
          <?php $ended = !empty($row['end_time']) && (new DateTime() > new DateTime($row['end_time'])); ?>
          <article class="auction-card">
            <?php if (!empty($row['image_url'])): ?>
              <img src="/auction_system/<?= htmlspecialchars($row['image_url']) ?>" alt="<?= htmlspecialchars($row['title']) ?>">
            <?php endif; ?>
            <h3><?= htmlspecialchars($row['title']) ?></h3>
            <?php if (!empty($row['category'])): ?>
              <p class="muted"><?= htmlspecialchars($row['category']) ?></p>
            <?php endif; ?>
            <p><strong>$<?= number_format($row['current_price'] ?: $row['starting_price'], 2) ?></strong></p>
            <div class="auction-status">
              <span class="status-pill <?= $ended ? 'ended' : 'active' ?>"><?= $ended ? 'Ended' : 'Active' ?></span>
              <span><?= (int)$row['bid_count'] ?> bids</span>
            </div>
            <p>Top bid: $<?= number_format((float)($row['top_bid'] ?? $row['starting_price']), 2) ?></p>
            <p class="card-actions">
              <a class="btn" href="/auction_system/items/view_item.php?id=<?= $row['id'] ?>">Open Auction</a>
              <a class="btn secondary" href="/auction_system/items/edit_item.php?id=<?= $row['id'] ?>">Edit</a>
              <a class="btn secondary" href="/auction_system/items/delete_item.php?id=<?= $row['id'] ?>">Delete</a>
            </p>
          </article>
        <?php endwhile; ?>
      </div>
    <?php endif; ?>
  </section>
</main>

<?php include(__DIR__ . '/../includes/footer.php'); ?>
