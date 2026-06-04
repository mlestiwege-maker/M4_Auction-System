<?php
include(__DIR__ . '/../config/db.php');
include(__DIR__ . '/../includes/auction_helpers.php');
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

$stmt = $conn->prepare('SELECT * FROM items WHERE id=? LIMIT 1');
$stmt->bind_param('i', $item_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) {
    echo '<main><p>Item not found.</p></main>';
    include(__DIR__ . '/../includes/footer.php');
    exit;
}

// Ensure only the item owner may edit
if (!isset($_SESSION['user_id']) || (int) $item['user_id'] !== (int) $_SESSION['user_id']) {
  echo '<main><p>Forbidden: you do not have permission to edit this item.</p></main>';
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
    $remove_image = !empty($_POST['remove_image']);

    $image_path = trim((string) ($item['image_url'] ?? ''));
    if (!empty($_FILES['image']['name'])) {
        if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Error uploading image.';
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['image']['tmp_name']);
            finfo_close($finfo);

            $allowed = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($mime, $allowed, true)) {
                $errors[] = 'Unsupported image type.';
            } else {
                $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            }
                $categorySlug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $category), '-'));
                $fn = $categorySlug . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
                $destDir = __DIR__ . '/../assets/uploads/originals/' . $categorySlug;
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                }
                $dest = $destDir . '/' . $fn;
                if (!move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                    $errors[] = 'Failed to move uploaded file.';
                } else {
                    $image_path = 'assets/uploads/originals/' . $categorySlug . '/' . $fn;
                }
        }
          } elseif ($remove_image) {
            $image_path = '';
    }

    if (empty($errors)) {
        if (empty($image_path)) {
            $image_path = auction_category_fallback_image($category, $title);
        }
        $update = $conn->prepare('UPDATE items SET title=?, category=?, description=?, starting_price=?, current_price=?, image_url=?, end_time=? WHERE id=?');
        $current_price = max((float) $item['current_price'], $start_price);
        $update->bind_param('sssddssi', $title, $category, $description, $start_price, $current_price, $image_path, $end_time, $item_id);
        if ($update->execute()) {
        // If this is an AJAX request, return JSON instead of redirecting
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        if ($isAjax) {
          $resp = [
            'success' => true,
            'item' => [
              'id' => (int) $item_id,
              'title' => $title,
              'category' => $category,
              'description' => $description,
              'starting_price' => (float) $start_price,
              'current_price' => (float) $current_price,
              'image_url' => auction_item_image_url(['image_url' => $image_path, 'category' => $category, 'title' => $title]),
              'end_time' => $end_time,
            ],
          ];
          header('Content-Type: application/json');
          echo json_encode($resp);
          exit;
        }
        header('Location: /auction_system/items/view_item.php?id=' . $item_id);
        exit;
        } else {
            $errors[] = 'A database error occurred. Please try again.';
        }
    }
}

  // If this is an AJAX POST that had errors, return them as JSON
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if ($isAjax && !empty($errors)) {
      header('Content-Type: application/json');
      echo json_encode(['success' => false, 'errors' => $errors]);
      exit;
    }
  }
?>

<main>
  <section class="hero item-hero">
    <div>
      <span class="page-chip">Edit listing</span>
      <h2>Edit Listing</h2>
      <p>Update your title, category, image, and auction duration.</p>
      <div class="auction-status">
        <span class="status-pill active">Keep bids flowing</span>
        <span class="status-pill">Refresh title</span>
        <span class="status-pill">Swap image</span>
      </div>
    </div>
    <div class="metric-card">
      <h3>Current listing</h3>
      <p><strong><?= htmlspecialchars($item['title']) ?></strong></p>
      <p>Price: $<?= number_format((float) $item['starting_price'], 2) ?></p>
      <p>Category: <?= htmlspecialchars($item['category'] ?? 'Uncategorized') ?></p>
    </div>
  </section>

  <div class="create-card item-form-card">
    <div class="section-head">
      <div>
        <span class="page-chip">Item details</span>
        <h2>Refresh this auction without losing momentum</h2>
      </div>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="notice notice-error"><?php foreach ($errors as $e) echo '<p>' . htmlspecialchars($e) . '</p>'; ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="item-form">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="item_id" value="<?= (int) $item_id ?>">
      <div class="item-form-grid">
        <div class="form-stack">
          <label>Title
            <input type="text" name="title" value="<?= htmlspecialchars($item['title']) ?>" required>
          </label>
          <label>Category
            <select name="category" required>
              <?php foreach (['Electronics','Fashion','Home & Garden','Collectibles','Vehicles','Other'] as $cat): ?>
                <option value="<?= htmlspecialchars($cat) ?>" <?= ($item['category'] ?? '') === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Description
            <textarea name="description" rows="7"><?= htmlspecialchars($item['description']) ?></textarea>
          </label>
          <label>Start Price
            <input type="number" name="start_price" step="0.01" value="<?= htmlspecialchars($item['starting_price']) ?>" required>
          </label>
          <label>End Time
            <input type="datetime-local" name="end_time" value="<?= htmlspecialchars(date('Y-m-d\TH:i', strtotime($item['end_time']))) ?>" required>
          </label>
        </div>

        <div class="form-stack form-side-card">
          <label>Replace image
            <input type="file" name="image" accept="image/*">
          </label>
          <label class="checkbox-row">
            <input type="checkbox" name="remove_image" value="1">
            <span>Remove current image and use the category fallback</span>
          </label>
          <p class="form-note">Uploading a new image keeps the listing fresh for returning bidders.</p>
          <?php $imageUrl = auction_item_image_url($item); ?>
          <?php if (!empty($imageUrl)): ?>
            <div class="image-preview">
              <img src="<?= htmlspecialchars($imageUrl) ?>" alt="Current listing image">
            </div>
          <?php endif; ?>
          <div class="form-note-block">
            <strong>Tip:</strong> Update the title only if it still reflects the product accurately. Trust is the best conversion trick.
          </div>
        </div>
      </div>

      <div class="card-actions">
        <button type="submit" class="btn">Save Changes</button>
        <a class="btn secondary" href="/auction_system/items/view_item.php?id=<?= (int) $item_id ?>">Preview item</a>
      </div>
    </form>
  </div>
</main>

<?php include(__DIR__ . '/../includes/footer.php'); ?>
