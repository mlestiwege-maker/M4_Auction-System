<?php
require_once __DIR__ . '/../config/db.php';
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
    
    if ($action === 'deactivate' && deactivate_user($user_id)) {
        $message = "✓ User deactivated";
    } elseif ($action === 'reactivate' && reactivate_user($user_id)) {
        $message = "✓ User reactivated";
    }
}

$result = $conn->query("SELECT id, name, email, role, created_at, is_active FROM users ORDER BY created_at DESC");
$users = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
?>

<main class="container">
    <section class="admin-section">
        <h1>Manage Users</h1>
        
        <?php if ($message): ?>
            <div class="notice-success" style="background: #d4edda; padding: 15px; border-radius: 4px; margin: 20px 0;"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
            <thead style="background: #f0f0f0;">
                <tr>
                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">Name</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">Email</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">Role</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">Joined</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">Status</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="6" style="text-align: center; padding: 20px;">No users found.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <tr style="border-bottom: 1px solid #ddd;">
                            <td style="padding: 12px;"><?= htmlspecialchars($user['name']) ?></td>
                            <td style="padding: 12px;"><?= htmlspecialchars($user['email']) ?></td>
                            <td style="padding: 12px;"><span style="background: #<?= $user['role'] === 'admin' ? 'e74c3c' : ($user['role'] === 'seller' ? '27ae60' : '3498db'); ?>; color: white; padding: 4px 8px; border-radius: 4px; display: inline-block;"><?= htmlspecialchars(ucfirst($user['role'])) ?></span></td>
                            <td style="padding: 12px;"><?= htmlspecialchars($user['created_at']) ?></td>
                            <td style="padding: 12px;"><span style="color: <?= $user['is_active'] ? '#27ae60' : '#e74c3c'; ?>; font-weight: bold;"><?= $user['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                            <td style="padding: 12px;">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                    <?php if ($user['is_active']): ?>
                                        <button type="submit" name="action" value="deactivate" style="background: #ff9800; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer;">Deactivate</button>
                                    <?php else: ?>
                                        <button type="submit" name="action" value="reactivate" style="background: #28a745; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer;">Reactivate</button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
