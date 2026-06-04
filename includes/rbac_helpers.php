<?php
/**
 * Role-Based Access Control (RBAC) Helpers
 * Define role permissions and check functions
 */

const ROLE_PERMISSIONS = [
    'admin' => [
        'view_admin_dashboard',
        'moderate_listings',
        'manage_users',
        'view_reports',
        'manage_sellers',
        'view_all_listings',
        'create_listings',
        'bid_on_listings',
        'view_notifications',
    ],
    'seller' => [
        'create_listings',
        'edit_own_listings',
        'view_seller_dashboard',
        'bid_on_listings',
        'view_notifications',
        'view_sales',
    ],
    'buyer' => [
        'view_listings',
        'bid_on_listings',
        'view_notifications',
        'request_seller_status',
    ],
];

function has_permission($permission, $user_id = null) {
    if (!isset($_SESSION['user_id']) && $user_id === null) {
        return false;
    }
    
    $user_id = $user_id ?? $_SESSION['user_id'];
    $user_role = get_user_role($user_id);
    
    return in_array($permission, ROLE_PERMISSIONS[$user_role] ?? []);
}

function has_role($roles, $user_id = null) {
    if (!isset($_SESSION['user_id']) && $user_id === null) {
        return false;
    }
    
    $user_id = $user_id ?? $_SESSION['user_id'];
    $user_role = get_user_role($user_id);
    
    $roles = is_array($roles) ? $roles : [$roles];
    return in_array($user_role, $roles);
}

function get_user_role($user_id) {
    global $conn;
    // If the DB connection is not available, default to 'buyer'
    if (!isset($conn) || !($conn instanceof mysqli)) {
        return 'buyer';
    }
    
    $stmt = $conn->prepare('SELECT role FROM users WHERE id=? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                return $row['role'] ?? 'buyer';
            }
        }
    }
    return 'buyer';
}

function is_admin($user_id = null) {
    return has_role('admin', $user_id);
}

function is_seller($user_id = null) {
    return has_role(['seller', 'admin'], $user_id);
}

function require_admin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /auction_system/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
    
    if (!is_admin()) {
        http_response_code(403);
        die('<main><p>Access denied. Admin role required.</p></main>');
    }
}

function require_seller() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /auction_system/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
    
    if (!is_seller()) {
        http_response_code(403);
        die('<main><p>Access denied. Seller role required.</p></main>');
    }
}

function get_users_by_role($role, $active_only = true) {
    global $conn;
    $users = [];
    
    $query = 'SELECT id, name, email, role, seller_status, is_active, created_at FROM users WHERE role=?';
    if ($active_only) {
        $query .= ' AND is_active=1';
    }
    $query .= ' ORDER BY created_at DESC';
    
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param('s', $role);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
        }
    }
    
    return $users;
}

function get_pending_sellers() {
    global $conn;
    $sellers = [];
    
    $query = 'SELECT id, name, email, seller_status, created_at FROM users WHERE seller_status="pending" ORDER BY created_at ASC';
    
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $sellers[] = $row;
        }
    }
    
    return $sellers;
}

function approve_seller($user_id) {
    global $conn;
    
    $stmt = $conn->prepare('UPDATE users SET role="seller", seller_status="approved" WHERE id=?');
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        return $stmt->execute();
    }
    
    return false;
}

function reject_seller($user_id) {
    global $conn;
    
    $stmt = $conn->prepare('UPDATE users SET role="buyer", seller_status="rejected" WHERE id=?');
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        return $stmt->execute();
    }
    
    return false;
}

function deactivate_user($user_id) {
    global $conn;
    
    $stmt = $conn->prepare('UPDATE users SET is_active=0 WHERE id=?');
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        return $stmt->execute();
    }
    
    return false;
}

function reactivate_user($user_id) {
    global $conn;
    
    $stmt = $conn->prepare('UPDATE users SET is_active=1 WHERE id=?');
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        return $stmt->execute();
    }
    
    return false;
}
?>
