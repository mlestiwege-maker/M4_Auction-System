<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/rbac_helpers.php';

// Signed-in sellers should go straight to their dashboard.
if (isset($_SESSION['user_id']) && is_seller()) {
    header('Location: /auction_system/seller/dashboard.php');
    exit;
}

$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? (int) $_SESSION['user_id'] : 0;
$user_details = [
    'role' => 'buyer',
    'seller_status' => null,
];

if ($is_logged_in && isset($conn) && $conn instanceof mysqli) {
    $stmt = $conn->prepare('SELECT role, seller_status FROM users WHERE id=? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $dbUser = $result->fetch_assoc();
            if ($dbUser) {
                $user_details = array_merge($user_details, $dbUser);
            }
        }
    }
}

$message = '';
$status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$is_logged_in) {
        $status = 'warning';
        $message = 'Please log in or register before submitting a seller request.';
    } elseif (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('CSRF token mismatch');
    }

    if ($is_logged_in && $user_details['seller_status'] === 'pending') {
        $status = 'info';
        $message = 'Your seller request is already pending. Please wait for admin approval.';
    } elseif ($is_logged_in && isset($conn) && $conn instanceof mysqli) {
        $stmt = $conn->prepare('UPDATE users SET seller_status=? WHERE id=?');
        $seller_status = 'pending';
        $stmt->bind_param('si', $seller_status, $user_id);
        if ($stmt->execute()) {
            $status = 'success';
            $message = '✓ Your seller request has been submitted!';
            $user_details['seller_status'] = 'pending';
        }
    }
}
?>

<main class="container">
    <section class="seller-request" style="padding: 30px; max-width: 980px; margin: 0 auto;">
        <div style="display: grid; gap: 1.25rem; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); align-items: start;">
            <div style="background: linear-gradient(135deg, rgba(39,174,96,0.12), rgba(15,118,110,0.08)); padding: 28px; border-radius: 16px; border: 1px solid rgba(39,174,96,0.22); box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);">
                <span class="page-chip" style="background:#e8f8ee; color:#16784a;">Seller onboarding</span>
                <h1 style="margin-top: 0.85rem;">Become a Seller</h1>
                <p style="font-size: 1.02rem; line-height: 1.7; color: var(--text-light, #475569);">
                    Start listing auctions, manage bids, and track sales from your own seller dashboard.
                </p>

                <div style="display:flex; flex-wrap:wrap; gap:.75rem; margin-top: 1.25rem;">
                    <?php if (!$is_logged_in): ?>
                        <a href="/auction_system/auth/login.php?redirect=%2Fauction_system%2Fseller%2Frequest_approval.php" class="btn">Log In</a>
                        <a href="/auction_system/auth/register.php" class="btn secondary">Create Account</a>
                    <?php elseif ($user_details['seller_status'] === 'pending'): ?>
                        <a href="/auction_system/index.php" class="btn secondary">Back to Home</a>
                    <?php else: ?>
                        <a href="#seller-request-form" class="btn">Submit Request</a>
                        <a href="/auction_system/items/create_item.php" class="btn secondary">Preview Sell Flow</a>
                    <?php endif; ?>
                </div>
            </div>

            <div style="background: var(--surface, #fff); padding: 28px; border-radius: 16px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08); border: 1px solid var(--border, #e2e8f0);">
                <h2 style="margin-top: 0; color: #27ae60;">Why become a seller?</h2>
                <ul style="line-height: 1.9; padding-left: 1.25rem;">
                    <li>Create unlimited auctions</li>
                    <li>Reach thousands of buyers</li>
                    <li>Track sales and earnings</li>
                    <li>See bid activity in real time</li>
                    <li>Build reputation with buyer reviews</li>
                </ul>

                <?php if ($message): ?>
                    <div style="background: #<?= $status === 'success' ? 'd4edda' : ($status === 'warning' ? 'fff3cd' : 'd1ecf1'); ?>; color: #<?= $status === 'success' ? '155724' : ($status === 'warning' ? '856404' : '0c5460'); ?>; padding: 15px; border-radius: 8px; margin: 20px 0; border: 1px solid #<?= $status === 'success' ? 'c3e6cb' : ($status === 'warning' ? 'ffe08a' : 'bee5eb'); ?>;">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <?php if ($is_logged_in && $user_details['seller_status'] === 'pending'): ?>
                    <div style="background: #fff3cd; border-left: 4px solid #ff9800; padding: 20px; border-radius: 8px; margin: 20px 0;">
                        <strong>Status: Pending Review</strong>
                        <p>Your seller request is being reviewed by our admin team.</p>
                    </div>
                <?php elseif ($is_logged_in): ?>
                    <form id="seller-request-form" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <button type="submit" style="background: #27ae60; color: white; padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; width: 100%;">Submit Seller Request</button>
                    </form>
                <?php else: ?>
                    <div style="background: #f8fafc; border: 1px dashed #cbd5e1; padding: 20px; border-radius: 8px; margin: 20px 0;">
                        <strong>Not signed in yet?</strong>
                        <p>Create an account or log in first, then come back here to submit your seller request.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
