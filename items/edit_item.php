<?php
include(__DIR__ . '/../config/db.php');
include(__DIR__ . '/../includes/header.php');

if (!isset($_SESSION['user_id'])) {
    echo '<main><p>Please <a href="/auction_system/auth/login.php">login</a> to edit an item.</p></main>';
    include(__DIR__ . '/../includes/footer.php');
    exit;
}

$item_id = (int) ($_GET['id'] ?? $_POST['item_id'] ?? 0);
if (!$item_id) {
    echo '<main><p>Missing item.</p></main>';
    include(__DIR__ . '/../includes/footer.php');
    exit;
}

$stmt = $conn->prepare('SELECT * FROM items WHERE id=? AND user_id=? LIMIT 1');
$stmt->bind_param('ii', $item_id, $_SESSION['user_id']);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) {
    echo '<main><p>Item not found or you do not have permission to edit it.</p></main>';
    include(__DIR__ . '/../includes/footer.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = 'Invalid CSRF token.';
    }

    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $start_price = floatval($_POST['start_price'] ?? 0);
    $end_time = $_POST['end_time'] ?? '';

    if ($title === '') $errors[] = 'Title is required.';
    if ($category === '') $errors[] = 'Category is required.';
    if ($start_price <= 0) $errors[] = 'Start price must be greater than 0.';
    if ($end_time === '') $errors[] = 'End time is required.';

    $image_path = $item['image_url'];
    if (!empty($_FILES['image']['name'])) {
        if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Error uploading image.';
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['image']['tmp_name']);
            finfo_close($finfo);
            $allowed = ['image/jpeg','image/png','image/gif'];
            if (!in_array($mime, $allowed)) {
                $errors[] = 'Unsupported image type.';
            } else {
                $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $fn = bin2hex(random_bytes(12)) . '.' . $ext;
                $dest = __DIR__ . '/../assets/uploads/' . $fn;
                if (!move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                    $errors[] = 'Failed to move uploaded file.';
                } else {
                    $image_path = 'assets/uploads/' . $fn;
                }
            }
        }
    }

    if (empty($errors)) {
        $update = $conn->prepare('UPDATE items SET title=?, category=?, description=?, starting_price=?, current_price=?, image_url=?, end_time=? WHERE id=? AND user_id=?');
        $current_price = max((float) $item['current_price'], $start_price);
        $update->bind_param('sssddssii', $title, $category, $description, $start_price, $current_price, $image_path, $end_time, $item_id, $_SESSION['user_id']);
        if ($update->execute()) {
            header('Location: /auction_system/items/view_item.php?id=' . $item_id);
            exit;
        } else {
            $errors[] = 'A database error occurred. Please try again.';
        }
    }
}
?>

<main>
  <section class="hero" style="margin-bottom: 22px;">
    <div>
      <h2>Edit Auction Listing</h2>
      <p>Update your listing details, category, image, and auction end time.</p>
    </div>
  </section>

  <div class="create-card">
    <h2>Item Details</h2>
    <?php if (!empty($errors)): ?>
      <div class="notice" style="color: var(--danger)"><?php foreach ($errors as $e) echo '<p>' . htmlspecialchars($e) . '</p>'; ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="item_id" value="<?= (int) $item_id ?>">
      <label>Title<br><input type="text" name="title" value="<?= htmlspecialchars($item['title']) ?>" required></label><br>
      <label>Category<br>
        <select name="category" required>
          <?php foreach (['Electronics','Fashion','Home & Garden','Collectibles','Vehicles','Other'] as $cat): ?>
            <option value="<?= htmlspecialchars($cat) ?>" <?= ($item['category'] ?? '') === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
          <?php endforeach; ?>
        </select>
      </label><br>
      <label>Description<br><textarea name="description"><?= htmlspecialchars($item['description']) ?></textarea></label><br>
      <label>Start Price<br><input type="number" name="start_price" step="0.01" value="<?= htmlspecialchars($item['starting_price']) ?>" required></label><br>
      <label>End Time<br><input type="datetime-local" name="end_time" value="<?= htmlspecialchars(date('Y-m-d\TH:i', strtotime($item['end_time']))) ?>" required></label><br>
      <label>Image<br><input type="file" name="image" accept="image/*"></label><br>
      <button type="submit">Save Changes</button>
    </form>
  </div>
</main>

<?php include(__DIR__ . '/../includes/footer.php'); ?>
