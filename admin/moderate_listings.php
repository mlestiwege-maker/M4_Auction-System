<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/rbac_helpers.php';

require_admin();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $item_id = (int) ($_POST['item_id'] ?? 0);
    
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('CSRF token mismatch');
    }
    
    if ($action === 'remove') {
        $stmt = $conn->prepare('UPDATE items SET status=? WHERE id=?');
        $status = 'removed';
        $stmt->bind_param('si', $status, $item_id);
        if ($stmt->execute()) {
            $message = "✓ Item removed from marketplace";
        }
    }
}

$query = "SELECT i.id, i.title, i.status, i.created_at, u.name as seller_name FROM items i JOIN users u ON i.user_id = u.id ORDER BY i.created_at DESC LIMIT 50";
$result = $conn->query($query);
$items = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
}
?>

<main class="container">
    <section class="admin-section">
        <h1>Moderate Listings</h1>
        
        <?php if ($message): ?>
            <div class="notice-success" style="background: #d4edda; padding: 15px; border-radius: 4px; margin: 20px 0;"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
            <thead style="background: #f0f0f0;">
                <tr>
                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">Title</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">Seller</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">Status</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">Created</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="5" style="text-align: center; padding: 20px;">No listings found.</td></tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <tr style="border-bottom: 1px solid #ddd;">
                            <td style="padding: 12px;"><a href="/auction_system/items/view_item.php?id=<?= (int) $item['id'] ?>"><?= htmlspecialchars(substr($item['title'], 0, 50)) ?></a></td>
                            <td style="padding: 12px;"><?= htmlspecialchars($item['seller_name']) ?></td>
                            <td style="padding: 12px;"><span style="background: #<?= $item['status'] === 'active' ? '28a745' : 'ff9800'; ?>; color: white; padding: 4px 8px; border-radius: 4px; display: inline-block;"><?= htmlspecialchars($item['status']) ?></span></td>
                            <td style="padding: 12px;"><?= htmlspecialchars($item['created_at']) ?></td>
                            <td style="padding: 12px;">
                                <?php if ($item['status'] !== 'removed'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                        <button type="submit" name="action" value="remove" style="background: #dc3545; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer;">Remove</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color: #999; font-style: italic;">Removed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
