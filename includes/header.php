<?php
if (session_status() === PHP_SESSION_NONE) session_start();
// ensure a CSRF token exists for forms
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Load RBAC helpers
require_once __DIR__ . '/rbac_helpers.php';

$cssVersion = @filemtime(__DIR__ . '/../assets/css/style.css') ?: time();
$jsVersion = @filemtime(__DIR__ . '/../assets/js/script.js') ?: time();

$unread_notifications = 0;
if (isset($_SESSION['user_id']) && isset($conn) && $conn instanceof mysqli) {
    $countStmt = $conn->prepare('SELECT COUNT(*) AS total FROM notifications WHERE user_id=? AND is_read=0');
    if ($countStmt) {
        $uid = (int) $_SESSION['user_id'];
        $countStmt->bind_param('i', $uid);
        if ($countStmt->execute()) {
            $unread_notifications = (int) ($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AuctionHub - Smart Marketplace</title>
    <script>
        window.CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
        window.NOTIFICATION_COUNT = <?= (int) $unread_notifications ?>;
    </script>
    <link rel="stylesheet" href="/auction_system/assets/css/style.css?v=<?= (int) $cssVersion ?>">
    <script src="/auction_system/assets/js/script.js?v=<?= (int) $jsVersion ?>" defer></script>
</head>
<body>
    <a class="skip-link" href="#main" tabindex="0">Skip to content</a>
<header class="main-header">
    <div class="header-container">
        <a class="logo brand-mark" href="/auction_system/index.php" aria-label="AuctionHub home">
            <span class="brand-badge" aria-hidden="true">AH</span>
            <span class="brand-copy">
                <strong>AuctionHub</strong>
                <small>Built for your marketplace vision</small>
            </span>
        </a>
        <form action="/auction_system/items/all_items.php" method="GET" class="header-search" role="search">
            <input id="header-search-input" type="search" name="q" placeholder="Search auctions, e.g., iPhone, watch..." aria-label="Search auctions" autocomplete="off">
            <select name="category" aria-label="Category">
                <option value="all">All categories</option>
                <option value="Electronics">Electronics</option>
                <option value="Fashion">Fashion</option>
                <option value="Home & Garden">Home & Garden</option>
                <option value="Collectibles">Collectibles</option>
                <option value="Vehicles">Vehicles</option>
                <option value="Other">Other</option>
            </select>
            <button class="btn" type="submit">Search</button>
            <div id="search-suggestions" class="search-suggestions" aria-hidden="true"></div>
        </form>
        <nav class="main-nav">
            <a href="/auction_system/index.php" class="nav-link">Home</a>
            <a href="/auction_system/items/all_items.php" class="nav-link">Browse</a>
            <?php if (isset($_SESSION['user_id']) && is_seller()): ?>
                <a href="/auction_system/items/create_item.php" class="nav-link">Sell Item</a>
            <?php elseif (isset($_SESSION['user_id']) && has_permission('request_seller_status')): ?>
                <a href="/auction_system/seller/request_approval.php" class="nav-link">Become Seller</a>
            <?php else: ?>
                <a href="/auction_system/seller/request_approval.php" class="nav-link">Become Seller</a>
            <?php endif; ?>
            <a href="/auction_system/payment/index.php" class="nav-link">Payments</a>
            <a href="/auction_system/features.html" class="nav-link">Features</a>
            <a href="/auction_system/about.html" class="nav-link">About</a>
            <button type="button" class="nav-link theme-toggle" id="theme-toggle" aria-label="Toggle dark mode">🌙 Dark</button>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="/auction_system/user/notifications.php" class="nav-link nav-notifications">
                    🔔 Notifications
                    <span id="notification-badge" class="notification-badge"<?= $unread_notifications > 0 ? '' : ' hidden' ?>><?= (int) $unread_notifications ?></span>
                </a>
                <?php if (is_admin()): ?>
                    <a href="/auction_system/admin/dashboard.php" class="nav-link" style="color: #e74c3c;">👨‍💼 Admin</a>
                <?php elseif (is_seller()): ?>
                    <a href="/auction_system/seller/dashboard.php" class="nav-link" style="color: #27ae60;">📊 Seller</a>
                <?php endif; ?>
                <a href="/auction_system/user/dashboard.php" class="nav-link">Dashboard</a>
                <a href="/auction_system/user/analytics.php" class="nav-link">📊 Analytics</a>
                <a href="/auction_system/user/watchlist.php" class="nav-link">❤️ Watchlist</a>
                <a href="/auction_system/auth/logout.php" class="nav-link nav-logout">Logout</a>
            <?php else: ?>
                <a href="/auction_system/auth/login.php" class="nav-link nav-login">Login</a>
                <a href="/auction_system/auth/register.php" class="nav-link nav-register">Register</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

