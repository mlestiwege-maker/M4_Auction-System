<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/rbac_helpers.php';

require_seller();

$user_id = (int) $_SESSION['user_id'];

$stats = ['total_listings' => 0, 'active_listings' => 0, 'total_sales' => 0, 'total_revenue' => 0];

$result = $conn->query("SELECT COUNT(*) as cnt FROM items WHERE user_id=" . $user_id);
if ($result) {
    $stats['total_listings'] = (int) $result->fetch_assoc()['cnt'];
}

$result = $conn->query("SELECT COUNT(*) as cnt FROM items WHERE user_id=" . $user_id . " AND status='active'");
if ($result) {
    $stats['active_listings'] = (int) $result->fetch_assoc()['cnt'];
}
?>

<main class="container">
    <section class="seller-dashboard" style="padding: 20px;">
        <h1>Seller Dashboard</h1>
        
        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 30px 0;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px;">
                <h3 style="margin: 0 0 10px 0; font-size: 14px;">Active Listings</h3>
                <p style="font-size: 32px; font-weight: bold; margin: 0;"><?= $stats['active_listings'] ?></p>
            </div>
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px;">
                <h3 style="margin: 0 0 10px 0; font-size: 14px;">Total Listings</h3>
                <p style="font-size: 32px; font-weight: bold; margin: 0;"><?= $stats['total_listings'] ?></p>
            </div>
        </div>

        <div style="margin: 30px 0;">
            <a href="/auction_system/items/create_item.php" style="background: #27ae60; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block;">+ Create New Listing</a>
            <a href="/auction_system/items/all_items.php" style="background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin-left: 10px;">View All Items</a>
        </div>

        <p>Welcome to your seller dashboard! You can manage your listings and track sales from here.</p>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
