<?php
include(__DIR__ . '/../config/db.php');
include(__DIR__ . '/../includes/header.php');

if (!isset($_SESSION['user_id'])) {
    echo '<main><p>Please <a href="/auction_system/auth/login.php">login</a> to view analytics.</p></main>';
    include(__DIR__ . '/../includes/footer.php');
    exit;
}

$totalBids = (int) (($conn->query('SELECT COUNT(*) AS total FROM bids')->fetch_assoc()['total'] ?? 0));
$activeUsers = (int) (($conn->query('SELECT COUNT(DISTINCT user_id) AS total FROM bids')->fetch_assoc()['total'] ?? 0));
$activeAuctions = (int) (($conn->query("SELECT COUNT(*) AS total FROM items WHERE status='active' OR end_time > NOW()")->fetch_assoc()['total'] ?? 0));

$topCategoriesRes = $conn->query(
    "SELECT category, COUNT(*) AS total
     FROM items
     GROUP BY category
     ORDER BY total DESC
     LIMIT 6"
);
$categoryLabels = [];
$categoryCounts = [];
while ($topCategoriesRes && $row = $topCategoriesRes->fetch_assoc()) {
    $categoryLabels[] = $row['category'] ?: 'Other';
    $categoryCounts[] = (int) $row['total'];
}

$mostBidOn = $conn->query(
    "SELECT i.title, COUNT(b.id) AS total
     FROM items i
     LEFT JOIN bids b ON b.item_id = i.id
     GROUP BY i.id
     ORDER BY total DESC
     LIMIT 1"
)->fetch_assoc();

$endingSoonRes = $conn->query(
    "SELECT title, current_price, end_time
     FROM items
     WHERE end_time > NOW()
     ORDER BY end_time ASC
     LIMIT 5"
);
?>
<main>
  <section class="hero" style="margin-bottom: 18px;">
    <div>
      <h2>Analytics Dashboard</h2>
      <p>Track platform growth, bidding activity, and category performance with live startup-style metrics.</p>
    </div>
  </section>

  <section class="feature-grid">
    <article class="feature"><h3>Total Bids</h3><p><?= $totalBids ?></p></article>
    <article class="feature"><h3>Active Bidders</h3><p><?= $activeUsers ?></p></article>
    <article class="feature"><h3>Active Auctions</h3><p><?= $activeAuctions ?></p></article>
    <article class="feature"><h3>Most Bid-On Item</h3><p><?= htmlspecialchars($mostBidOn['title'] ?? 'N/A') ?></p></article>
  </section>

  <section class="create-card" style="margin-bottom: 16px;">
    <h3>📊 Top Categories</h3>
    <canvas id="categoryChart" height="100"></canvas>
  </section>

  <section class="create-card">
    <h3>⏳ Ending Soon</h3>
    <div class="trending-grid">
      <?php if ($endingSoonRes): ?>
        <?php while ($row = $endingSoonRes->fetch_assoc()): ?>
          <article class="trending-card">
            <h4><?= htmlspecialchars($row['title']) ?></h4>
            <p><strong>$<?= number_format((float) $row['current_price'], 2) ?></strong></p>
            <p class="countdown-badge live-countdown" data-end-time="<?= htmlspecialchars($row['end_time']) ?>">Live</p>
          </article>
        <?php endwhile; ?>
      <?php endif; ?>
    </div>
  </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  (() => {
    const labels = <?= json_encode($categoryLabels) ?>;
    const data = <?= json_encode($categoryCounts) ?>;
    const ctx = document.getElementById('categoryChart');
    if (!ctx || !labels.length) return;

    new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Listings by category',
          data,
          borderWidth: 1,
          backgroundColor: ['#667eea', '#764ba2', '#f093fb', '#4caf50', '#f59e0b', '#22c55e']
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { display: false }
        },
        scales: {
          y: { beginAtZero: true }
        }
      }
    });
  })();
</script>

<?php include(__DIR__ . '/../includes/footer.php'); ?>
