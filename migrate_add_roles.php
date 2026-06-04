<?php
require_once __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>RBAC Migration</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; }
            .warning { color: #d9534f; background: #f2dede; padding: 15px; border-radius: 4px; }
            .info { color: #31708f; background: #d9edf7; padding: 15px; border-radius: 4px; }
            button { background: #5cb85c; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; }
        </style>
    </head>
    <body>
        <h2>RBAC Migration: Add Roles to Users</h2>
        <div class="info">
            <strong>This will:</strong>
            <ul>
                <li>Add <code>role</code> column (buyer, seller, admin)</li>
                <li>Add <code>seller_status</code> column (pending, approved, rejected)</li>
                <li>Add <code>is_active</code> column for user deactivation</li>
                <li>Set first user as <strong>admin</strong></li>
                <li>Set all other users as <strong>buyers</strong></li>
            </ul>
        </div>
        <form method="POST">
            <button type="submit">Run Migration</button>
        </form>
    </body>
    </html>
    <?php
    exit;
}

try {
    // Check if role column already exists
    $check = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='users' AND COLUMN_NAME='role'");
    if ($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN role ENUM('buyer', 'seller', 'admin') DEFAULT 'buyer' AFTER password");
        echo "✓ Added role column<br>";
    } else {
        echo "✓ role column already exists<br>";
    }

    // Check if seller_status column exists
    $check = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='users' AND COLUMN_NAME='seller_status'");
    if ($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN seller_status ENUM('pending', 'approved', 'rejected') DEFAULT NULL AFTER role");
        echo "✓ Added seller_status column<br>";
    } else {
        echo "✓ seller_status column already exists<br>";
    }

    // Check if is_active column exists
    $check = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='users' AND COLUMN_NAME='is_active'");
    if ($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER seller_status");
        echo "✓ Added is_active column<br>";
    } else {
        echo "✓ is_active column already exists<br>";
    }

    // Set first user as admin
    $adminCheck = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role='admin'");
    $adminRow = $adminCheck->fetch_assoc();
    if ($adminRow['cnt'] == 0) {
        $firstUser = $conn->query("SELECT id FROM users ORDER BY id ASC LIMIT 1");
        if ($firstUser && $row = $firstUser->fetch_assoc()) {
            $conn->query("UPDATE users SET role='admin' WHERE id=" . (int)$row['id']);
            echo "✓ Promoted user ID " . (int)$row['id'] . " to admin<br>";
        }
    }

    // Set all users without role to buyer
    $conn->query("UPDATE users SET role='buyer' WHERE role IS NULL OR role=''");
    echo "✓ Ensured all users have a role assigned<br>";

    ?>
    <div style="background: #dff0d8; color: #3c763d; padding: 15px; border-radius: 4px; margin-top: 20px;">
        <strong>✓ Migration complete!</strong><br>
        <p>RBAC is now enabled!<br>
        <a href="/auction_system/index.php">Return to home</a> | <a href="/auction_system/admin/dashboard.php">Go to Admin Dashboard</a></p>
    </div>
    <?php
} catch (Exception $e) {
    die("Error: " . htmlspecialchars($e->getMessage()));
}
?>
