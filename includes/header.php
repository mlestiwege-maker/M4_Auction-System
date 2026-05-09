<?php
if (session_status() === PHP_SESSION_NONE) session_start();
// ensure a CSRF token exists for forms
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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
    <link rel="stylesheet" href="/auction_system/assets/css/style.css">
    <script src="/auction_system/assets/js/script.js" defer></script>
</head>
<body>
<header class="main-header">
    <div class="header-container">
        <div class="logo">
            <h1>🏆 AuctionHub</h1>
        </div>
        <nav class="main-nav">
            <a href="/auction_system/index.php" class="nav-link">Home</a>
            <a href="/auction_system/items/all_items.php" class="nav-link">Browse</a>
            <a href="/auction_system/items/create_item.php" class="nav-link">Sell</a>
            <a href="/auction_system/features.html" class="nav-link">Features</a>
            <a href="/auction_system/about.html" class="nav-link">About</a>
            <button type="button" class="nav-link theme-toggle" id="theme-toggle" aria-label="Toggle dark mode">🌙 Dark</button>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="/auction_system/user/notifications.php" class="nav-link nav-notifications">
                    🔔 Notifications
                    <span id="notification-badge" class="notification-badge"<?= $unread_notifications > 0 ? '' : ' style="display:none"' ?>><?= (int) $unread_notifications ?></span>
                </a>
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

