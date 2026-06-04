<?php
include(__DIR__ . '/../config/db.php');
include(__DIR__ . '/../includes/auction_helpers.php');
auction_finalize_ended_items($conn);
include(__DIR__ . '/../includes/header.php');

if (!isset($_SESSION['user_id'])) {
    echo '<main><p>Please <a href="/auction_system/auth/login.php">login</a> to view your watchlist.</p></main>';
    include(__DIR__ . '/../includes/footer.php');
    exit;
}

$user_id = intval($_SESSION['user_id']);
$stmt = $conn->prepare(
    'SELECT i.id, i.title, i.current_price, i.starting_price, i.end_time, i.image_url
     FROM watchlist w
     JOIN items i ON w.item_id = i.id
    WHERE w.user_id = ?
     ORDER BY w.added_time DESC'
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
?>

<main>
  <section class="watchlist-hero">
    <div class="watchlist-kicker">AH AuctionHub • Watchlist</div>
    <h2>Your Watchlist</h2>
    <p>Keep track of auctions you care about and jump back in before they close.</p>
    <div class="watchlist-summary">
      <span class="watchlist-pill"><?= $res->num_rows ?> saved items</span>
      <span class="watchlist-pill">Quick remove enabled</span>
      <span class="watchlist-pill">Live status updates</span>
    </div>
  </section>

  <?php if ($res->num_rows === 0): ?>
    <div class="create-card"><p>No items saved yet. Add items from the auction cards or item page.</p></div>
  <?php else: ?>
    <section class="watchlist-grid">
      <?php while ($row = $res->fetch_assoc()): ?>
        <?php $ended = !empty($row['end_time']) && (new DateTime() > new DateTime($row['end_time'])); ?>
        <?php $imageUrl = auction_item_image_url($row); ?>
        <article class="watchlist-card">
          <?php if (!empty($imageUrl)): ?>
            <img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($row['title']) ?>">
          <?php endif; ?>
          <h3><?= htmlspecialchars($row['title']) ?></h3>
          <p><strong>$<?= number_format($row['current_price'] ?: $row['starting_price'], 2) ?></strong></p>
          <div class="auction-status">
            <span class="status-pill <?= $ended ? 'ended' : 'active' ?>"><?= $ended ? 'Ended' : 'Active' ?></span>
            <?php if (!empty($row['end_time'])): ?>
              <span><?= htmlspecialchars($row['end_time']) ?></span>
            <?php endif; ?>
          </div>
          <p>
            <a class="btn" href="/auction_system/items/view_item.php?id=<?= $row['id'] ?>">View Item</a>
            <button class="btn secondary watchlist-toggle" data-item-id="<?= $row['id'] ?>" data-action="remove">Remove</button>
          </p>
        </article>
      <?php endwhile; ?>
    </section>
  <?php endif; ?>
</main>

<script>
  const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
</script>

<?php include(__DIR__ . '/../includes/footer.php'); ?>
