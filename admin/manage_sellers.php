<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/rbac_helpers.php';

require_admin();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = (int) ($_POST['user_id'] ?? 0);
    
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('CSRF token mismatch');
    }
    
    if ($action === 'approve' && approve_seller($user_id)) {
        $message = "✓ Seller approved successfully";
    } elseif ($action === 'reject' && reject_seller($user_id)) {
        $message = "✓ Seller request rejected";
    }
}

$pending_sellers = get_pending_sellers();
$approved_sellers = get_users_by_role('seller');
?>

<main class="container">
    <section class="admin-section">
        <h1>Manage Seller Requests</h1>
        
        <?php if ($message): ?>
            <div class="notice-success" style="background: #d4edda; padding: 15px; border-radius: 4px; margin: 20px 0;"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <h2>Pending Seller Requests (<?= count($pending_sellers) ?>)</h2>
        
        <?php if (empty($pending_sellers)): ?>
            <p>No pending seller requests.</p>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
                <thead style="background: #f0f0f0;">
                    <tr>
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">Name</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">Email</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">Requested</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_sellers as $seller): ?>
                        <tr style="border-bottom: 1px solid #ddd;">
                            <td style="padding: 12px;"><?= htmlspecialchars($seller['name']) ?></td>
                            <td style="padding: 12px;"><?= htmlspecialchars($seller['email']) ?></td>
                            <td style="padding: 12px;"><?= htmlspecialchars($seller['created_at']) ?></td>
                            <td style="padding: 12px;">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="user_id" value="<?= (int) $seller['id'] ?>">
                                    <button type="submit" name="action" value="approve" style="background: #28a745; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer;">Approve</button>
                                    <button type="submit" name="action" value="reject" style="background: #dc3545; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; margin-left: 4px;">Reject</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h2 style="margin-top: 40px;">Approved Sellers (<?= count($approved_sellers) ?>)</h2>
        <?php if (empty($approved_sellers)): ?>
            <p>No approved sellers yet.</p>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
                <thead style="background: #f0f0f0;">
                    <tr>
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">Name</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">Email</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">Joined</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($approved_sellers as $seller): ?>
                        <tr style="border-bottom: 1px solid #ddd;">
                            <td style="padding: 12px;"><?= htmlspecialchars($seller['name']) ?></td>
                            <td style="padding: 12px;"><?= htmlspecialchars($seller['email']) ?></td>
                            <td style="padding: 12px;"><?= htmlspecialchars($seller['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
