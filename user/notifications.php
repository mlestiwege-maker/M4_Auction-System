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
  <section class="hero" style="margin-bottom: 22px;">
    <div>
      <h2>Notifications</h2>
      <p>Stay on top of outbids, auction wins, and auction endings without refreshing the page.</p>
    </div>
    <div class="metric-card">
      <h3>Live alerts</h3>
      <p>Auto-refreshed every few seconds for a near real-time feel.</p>
      <button class="btn secondary" id="mark-all-read">Mark all as read</button>
    </div>
  </section>

  <div class="create-card">
    <?php if ($res->num_rows === 0): ?>
      <p>No notifications yet.</p>
    <?php else: ?>
      <ul class="notification-list" id="notification-list">
        <?php while ($row = $res->fetch_assoc()): ?>
          <li class="notification-item <?= $row['is_read'] ? 'read' : 'unread' ?>" data-id="<?= (int) $row['id'] ?>">
            <div>
              <strong><?= htmlspecialchars(ucfirst($row['type'])) ?></strong>
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
