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
  <section class="hero" style="margin-bottom: 22px;">
    <div>
      <h2>Seller Reviews</h2>
      <p>Feedback for <?= htmlspecialchars($seller['name'] ?? 'Seller') ?></p>
    </div>
  </section>

  <div class="create-card" id="seller-review-summary" data-seller-id="<?= $seller_id ?>">
    <p>Loading reviews...</p>
  </div>
</main>

<script>
  const SELLER_ID = <?= (int) $seller_id ?>;
</script>

<?php include(__DIR__ . '/../includes/footer.php'); ?>
