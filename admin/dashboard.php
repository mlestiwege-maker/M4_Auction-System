<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/rbac_helpers.php';

// Require admin role
require_admin();

// Get statistics
$stats = [];
$stats['total_users'] = 0;
$stats['total_sellers'] = 0;
$stats['total_listings'] = 0;
$stats['pending_sellers'] = count(get_pending_sellers());
$stats['active_auctions'] = 0;

// Query stats
$result = $conn->query('SELECT COUNT(*) as cnt FROM users');
if ($result) {
    $stats['total_users'] = (int) $result->fetch_assoc()['cnt'];
}

$result = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role='seller'");
if ($result) {
    $stats['total_sellers'] = (int) $result->fetch_assoc()['cnt'];
}

$result = $conn->query("SELECT COUNT(*) as cnt FROM items WHERE status='active'");
if ($result) {
    $stats['active_auctions'] = (int) $result->fetch_assoc()['cnt'];
}

$result = $conn->query("SELECT COUNT(*) as cnt FROM items");
if ($result) {
    $stats['total_listings'] = (int) $result->fetch_assoc()['cnt'];
}
?>

<main class="container">
    <section class="admin-dashboard">
        <h1>Admin Dashboard</h1>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Users</h3>
                <p class="stat-number"><?= $stats['total_users'] ?></p>
            </div>
            <div class="stat-card">
                <h3>Sellers</h3>
                <p class="stat-number"><?= $stats['total_sellers'] ?></p>
            </div>
            <div class="stat-card">
                <h3>Active Auctions</h3>
                <p class="stat-number"><?= $stats['active_auctions'] ?></p>
            </div>
            <div class="stat-card" style="background: #fff3cd;">
                <h3>Pending Seller Requests</h3>
                <p class="stat-number" style="color: #ff9800;"><?= $stats['pending_sellers'] ?></p>
            </div>
        </div>

        <div class="admin-menu">
            <h2>Admin Actions</h2>
            <ul>
                <li><a href="/auction_system/admin/manage_sellers.php" class="btn">👤 Manage Seller Requests</a></li>
                <li><a href="/auction_system/admin/moderate_listings.php" class="btn">📝 Moderate Listings</a></li>
                <li><a href="/auction_system/admin/manage_users.php" class="btn">👥 Manage Users</a></li>
                <li><a href="/auction_system/admin/reports.php" class="btn">📊 View Reports</a></li>
            </ul>
        </div>
    </section>
</main>

<style>
.admin-dashboard {
    padding: 20px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 30px 0;
}

.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.stat-card h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    opacity: 0.9;
}

.stat-number {
    font-size: 32px;
    font-weight: bold;
    margin: 0;
}

.admin-menu {
    background: white;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #ddd;
    margin-top: 30px;
}

.admin-menu ul {
    list-style: none;
    padding: 0;
    margin: 20px 0 0 0;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.admin-menu li {
    margin: 0;
}

.admin-menu .btn {
    display: block;
    padding: 12px 16px;
    background: #667eea;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    text-align: center;
    transition: background 0.3s;
}

.admin-menu .btn:hover {
    background: #5568d3;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
