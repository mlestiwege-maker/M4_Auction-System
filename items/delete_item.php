<?php
session_start();
include(__DIR__ . '/../config/db.php');
include(__DIR__ . '/../includes/header.php');

if (!isset($_SESSION['user_id'])) {
    echo '<main><p>Please <a href="/auction_system/auth/login.php">login</a> to delete an item.</p></main>';
    include(__DIR__ . '/../includes/footer.php');
    exit;
}

$item_id = (int) ($_GET['id'] ?? $_POST['item_id'] ?? 0);
if (!$item_id) {
    echo '<main><p>Missing item.</p></main>';
    include(__DIR__ . '/../includes/footer.php');
    exit;
}

$stmt = $conn->prepare('SELECT id, title FROM items WHERE id=? AND user_id=? LIMIT 1');
$stmt->bind_param('ii', $item_id, $_SESSION['user_id']);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) {
    echo '<main><p>Item not found or you do not have permission to delete it.</p></main>';
    include(__DIR__ . '/../includes/footer.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo '<main><p>Invalid CSRF token.</p></main>';
        include(__DIR__ . '/../includes/footer.php');
        exit;
    }

    $delete = $conn->prepare('DELETE FROM items WHERE id=? AND user_id=?');
    $delete->bind_param('ii', $item_id, $_SESSION['user_id']);
    if ($delete->execute()) {
        header('Location: /auction_system/user/dashboard.php');
        exit;
    }
    echo '<main><p>Could not delete item.</p></main>';
    include(__DIR__ . '/../includes/footer.php');
    exit;
}
?>

<main>
  <div class="create-card">
    <h2>Delete Item</h2>
    <p>Are you sure you want to permanently delete <strong><?= htmlspecialchars($item['title']) ?></strong>?</p>
    <form method="POST" class="inline-form">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="item_id" value="<?= (int) $item_id ?>">
      <button type="submit" class="btn">Yes, delete it</button>
      <a class="btn secondary" href="/auction_system/items/view_item.php?id=<?= (int) $item_id ?>">Cancel</a>
    </form>
  </div>
</main>

<?php include(__DIR__ . '/../includes/footer.php'); ?>
