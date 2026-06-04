<?php
include(__DIR__ . '/../config/db.php');
include(__DIR__ . '/../includes/header.php');

$seller_id = (int) ($_GET['seller_id'] ?? 0);
if (!$seller_id) {
    echo '<main><p>No seller selected.</p></main>';
    include(__DIR__ . '/../includes/footer.php');
    exit;
}

$sellerStmt = $conn->prepare('SELECT id, name FROM users WHERE id=? LIMIT 1');
$sellerStmt->bind_param('i', $seller_id);
$sellerStmt->execute();
$seller = $sellerStmt->get_result()->fetch_assoc();
?>

<main>
  <section class="hero item-hero">
    <div>
      <span class="page-chip">Seller feedback</span>
      <h2>Seller Reviews</h2>
      <p>Feedback for <?= htmlspecialchars($seller['name'] ?? 'Seller') ?></p>
      <div class="auction-status">
        <span class="status-pill active">Buyer trust</span>
        <span class="status-pill">Ratings</span>
        <span class="status-pill">Comments</span>
      </div>
    </div>
    <div class="metric-card">
      <h3>Review feed</h3>
      <p>Buyer experiences, displayed in one clean place so reputation is easy to scan.</p>
    </div>
  </section>

  <section class="dashboard-stats" aria-label="Seller review summary">
    <div class="dashboard-stat"><strong>Live</strong><span>Buyer feedback</span></div>
    <div class="dashboard-stat"><strong>Trusted</strong><span>Seller profile</span></div>
    <div class="dashboard-stat"><strong>Up to date</strong><span>Real-time summary</span></div>
    <div class="dashboard-stat"><strong><?= htmlspecialchars($seller['name'] ?? 'Seller') ?></strong><span>Current seller</span></div>
  </section>

  <div class="create-card review-card" id="seller-review-summary" data-seller-id="<?= $seller_id ?>">
    <p>Loading reviews...</p>
  </div>
</main>

<script>
  const SELLER_ID = <?= (int) $seller_id ?>;
</script>

<?php include(__DIR__ . '/../includes/footer.php'); ?>
