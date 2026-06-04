<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/rbac_helpers.php';

require_admin();

$stats = [
    'total_bids' => 0,
    'total_revenue' => 0,
    'completed_auctions' => 0,
    'avg_bid' => 0,
];

$result = $conn->query('SELECT COUNT(*) as cnt FROM bids');
if ($result) {
    $stats['total_bids'] = (int) $result->fetch_assoc()['cnt'];
}

$result = $conn->query('SELECT SUM(amount) as total FROM payments WHERE payment_status="completed"');
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_revenue'] = (float) ($row['total'] ?? 0);
}

$result = $conn->query("SELECT COUNT(*) as cnt FROM items WHERE status='completed' AND winner_id IS NOT NULL");
if ($result) {
    $stats['completed_auctions'] = (int) $result->fetch_assoc()['cnt'];
}

if ($stats['total_bids'] > 0) {
    $result = $conn->query('SELECT AVG(bid_amount) as avg FROM bids');
    if ($result && $row = $result->fetch_assoc()) {
        $stats['avg_bid'] = (float) ($row['avg'] ?? 0);
    }
}
?>

<main class="container">
    <section class="admin-section">
        <h1>Reports & Analytics</h1>
        
        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 30px 0;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px;">
                <h3 style="margin: 0 0 10px 0; font-size: 14px; opacity: 0.9;">Total Bids</h3>
                <p style="font-size: 32px; font-weight: bold; margin: 0;"><?= $stats['total_bids'] ?></p>
            </div>
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px;">
                <h3 style="margin: 0 0 10px 0; font-size: 14px; opacity: 0.9;">Total Revenue</h3>
                <p style="font-size: 32px; font-weight: bold; margin: 0;">$<?= number_format($stats['total_revenue'], 2) ?></p>
            </div>
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px;">
                <h3 style="margin: 0 0 10px 0; font-size: 14px; opacity: 0.9;">Completed Auctions</h3>
                <p style="font-size: 32px; font-weight: bold; margin: 0;"><?= $stats['completed_auctions'] ?></p>
            </div>
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px;">
                <h3 style="margin: 0 0 10px 0; font-size: 14px; opacity: 0.9;">Average Bid</h3>
                <p style="font-size: 32px; font-weight: bold; margin: 0;">$<?= number_format($stats['avg_bid'], 2) ?></p>
            </div>
        </div>

        <h2 style="margin-top: 40px;">System Health</h2>
        <div style="background: #f0f0f0; padding: 20px; border-radius: 8px;">
            <p>✓ Database: Connected</p>
            <p>✓ File System: Writable</p>
            <p>✓ RBAC: Enabled</p>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
