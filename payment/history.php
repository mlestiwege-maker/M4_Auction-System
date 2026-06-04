<?php
include(__DIR__ . '/../config/db.php');
include(__DIR__ . '/../includes/auction_helpers.php');
include(__DIR__ . '/../includes/header.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: /auction_system/auth/login.php');
    exit;
}

$user_id = intval($_SESSION['user_id']);
?>
<main>
    <section class="hero item-hero">
        <div>
            <span class="page-chip">Payment History</span>
            <h2>Your Transactions</h2>
            <p>View your completed and pending payments.</p>
        </div>
    </section>

    <div class="create-card">
        <div class="section-head compact-head">
            <div>
                <span class="page-chip">Transaction History</span>
                <h3>All Payments</h3>
            </div>
        </div>
        <?php
        $stmt = $conn->prepare("SELECT p.*, i.title, i.image_url FROM payments p LEFT JOIN items i ON p.item_id=i.id WHERE p.buyer_id=? ORDER BY p.created_at DESC");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        ?>
        <?php if ($res->num_rows > 0): ?>
        <div class="auction-grid">
            <?php while ($row = $res->fetch_assoc()): ?>
            <article class="auction-card">
                <?php $imageUrl = !empty($row['image_url']) ? htmlspecialchars($row['image_url']) : auction_category_fallback_image('', $row['title']); ?>
                <img class="image-thumb" src="<?= $imageUrl ?>" alt="<?= htmlspecialchars($row['title']) ?>" loading="lazy" referrerpolicy="no-referrer">
                <div class="auction-card-content">
                    <h3><?= htmlspecialchars($row['title']) ?></h3>
                    <div class="auction-price"><strong>$<?= number_format($row['amount'],2) ?></strong></div>
                    <div class="auction-status">
                        <span class="status-pill <?= $row['payment_status'] === 'completed' ? 'active' : 'pending' ?>">
                            <?= ucfirst($row['payment_status']) ?>
                        </span>
                        <span class="status-pill"><?= ucfirst(str_replace('_', ' ', $row['payment_method'])) ?></span>
                    </div>
                    <p class="form-note">Transaction ID: AUCTION-<?= str_pad($row['id'], 8, '0', STR_PAD_LEFT) ?></p>
                    <p class="form-note">Date: <?= date('F j, Y, g:i a', strtotime($row['created_at'])) ?></p>
                </div>
            </article>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="create-card centered">
            <h3>No payment history yet</h3>
            <p class="form-note">You haven't made any payments yet. Win an auction and complete payment to see your transaction history here.</p>
            <div class="card-actions">
                <a class="btn" href="/auction_system/items/all_items.php">Browse Auctions</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<?php include(__DIR__ . '/../includes/footer.php'); ?>
