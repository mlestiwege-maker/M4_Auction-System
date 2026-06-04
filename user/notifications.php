<?php
include(__DIR__ . '/../config/db.php');
include(__DIR__ . '/../includes/auction_helpers.php');
include(__DIR__ . '/../includes/header.php');

if (!isset($_SESSION['user_id'])) {
    echo '<main><p>Please <a href="/auction_system/auth/login.php">login</a> to see your notifications.</p></main>';
    include(__DIR__ . '/../includes/footer.php');
    exit;
}

auction_finalize_ended_items($conn);

$user_id = (int) $_SESSION['user_id'];
$stmt = $conn->prepare(
  'SELECT id, notification_type AS type, message,
      CASE WHEN item_id IS NULL THEN "" ELSE CONCAT("/auction_system/items/view_item.php?id=", item_id) END AS link,
      is_read, created_at
     FROM notifications
     WHERE user_id=?
     ORDER BY created_at DESC, id DESC'
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
?>

<main>
  <section class="hero item-hero">
    <div>
      <span class="page-chip">Inbox</span>
      <h2>Notifications</h2>
      <p>Stay on top of outbids, auction wins, and auction endings without refreshing the page.</p>
      <div class="auction-status">
        <span class="status-pill active">Live alerts</span>
        <span class="status-pill">Outbid</span>
        <span class="status-pill">Wins</span>
        <span class="status-pill">Auction end</span>
      </div>
    </div>
    <div class="metric-card">
      <h3>Live alerts</h3>
      <p>Auto-refreshed every few seconds for a near real-time feel.</p>
      <div class="card-actions">
        <button class="btn secondary" id="mark-all-read">Mark all as read</button>
      </div>
    </div>
  </section>

  <section class="dashboard-stats" aria-label="Notification summary">
    <div class="dashboard-stat"><strong><?= (int) $res->num_rows ?></strong><span>Total alerts</span></div>
    <div class="dashboard-stat"><strong><?= (int) max(0, $res->num_rows - 0) ?></strong><span>Visible now</span></div>
    <div class="dashboard-stat"><strong>Real time</strong><span>Auto refreshed</span></div>
    <div class="dashboard-stat"><strong>Inbox</strong><span>Always on</span></div>
  </section>

  <div class="create-card">
    <?php if ($res->num_rows === 0): ?>
      <div class="item-fallback-art">
        <span class="page-chip">Quiet inbox</span>
        <h3>No notifications yet</h3>
        <p>Once you start bidding or watching items, alerts will appear here automatically.</p>
      </div>
    <?php else: ?>
      <ul class="notification-list" id="notification-list">
        <?php while ($row = $res->fetch_assoc()): ?>
          <li class="notification-item <?= $row['is_read'] ? 'read' : 'unread' ?>" data-id="<?= (int) $row['id'] ?>">
            <div>
              <div class="review-meta">
                <strong><?= htmlspecialchars(ucfirst($row['type'])) ?></strong>
                <span><?= $row['is_read'] ? 'Read' : 'Unread' ?></span>
              </div>
              <p><?= htmlspecialchars($row['message']) ?></p>
              <small><?= htmlspecialchars($row['created_at']) ?></small>
            </div>
            <div class="card-actions">
              <?php if (!empty($row['link'])): ?>
                <a class="btn" href="<?= htmlspecialchars($row['link']) ?>">Open</a>
              <?php endif; ?>
              <?php if (!$row['is_read']): ?>
                <button class="btn secondary mark-read" data-id="<?= (int) $row['id'] ?>">Mark read</button>
              <?php endif; ?>
            </div>
          </li>
        <?php endwhile; ?>
      </ul>
    <?php endif; ?>
  </div>
</main>

<script>
  const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
</script>

<?php include(__DIR__ . '/../includes/footer.php'); ?>
