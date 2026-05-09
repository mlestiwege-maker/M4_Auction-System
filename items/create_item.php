<?php
include(__DIR__ . '/../config/db.php');
include(__DIR__ . '/../includes/header.php');

if (!isset($_SESSION['user_id'])) {
    echo '<main><p>You must <a href="/auction_system/auth/login.php">login</a> to create an item.</p></main>';
    include(__DIR__ . '/../includes/footer.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
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

    $image_path = null;
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
        $user_id = intval($_SESSION['user_id']);
        $stmt = $conn->prepare('INSERT INTO items (user_id, title, category, description, starting_price, current_price, image_url, end_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('isssddss', $user_id, $title, $category, $description, $start_price, $start_price, $image_path, $end_time);
        if ($stmt->execute()) {
            header('Location: /auction_system/items/all_items.php');
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
            <h2>Create Auction Listing</h2>
            <p>Upload an item, set a starting price, and launch a polished live auction in minutes.</p>
        </div>
    </section>

    <div class="create-card">
    <h2>Item Details</h2>
  <?php if (!empty($errors)): ?>
        <div class="notice" style="color: var(--danger)"><?php foreach ($errors as $e) echo '<p>' . htmlspecialchars($e) . '</p>'; ?></div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <label>Title<br><input type="text" name="title" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"></label><br>
        <label>Category<br>
            <select name="category" required>
                <option value="">Select category</option>
                                <option value="Electronics" <?= (($_POST['category'] ?? '') === 'Electronics') ? 'selected' : '' ?>>Electronics</option>
                                <option value="Fashion" <?= (($_POST['category'] ?? '') === 'Fashion') ? 'selected' : '' ?>>Fashion</option>
                                <option value="Home & Garden" <?= (($_POST['category'] ?? '') === 'Home & Garden') ? 'selected' : '' ?>>Home & Garden</option>
                                <option value="Collectibles" <?= (($_POST['category'] ?? '') === 'Collectibles') ? 'selected' : '' ?>>Collectibles</option>
                                <option value="Vehicles" <?= (($_POST['category'] ?? '') === 'Vehicles') ? 'selected' : '' ?>>Vehicles</option>
                                <option value="Other" <?= (($_POST['category'] ?? '') === 'Other') ? 'selected' : '' ?>>Other</option>
            </select>
        </label><br>
        <label>Description<br><textarea name="description"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea></label><br>
        <label>Start Price<br><input type="number" name="start_price" step="0.01" required value="<?= htmlspecialchars($_POST['start_price'] ?? '') ?>"></label>
        <div id="smart-price-suggestion" class="smart-suggestion">Enter title + category to get smart price suggestion.</div><br>
        <label>End Time<br><input type="datetime-local" name="end_time" required value="<?= htmlspecialchars($_POST['end_time'] ?? '') ?>"></label><br>
        <label>Image<br><input type="file" name="image" accept="image/*"></label>
        <div id="image-preview" class="image-preview">
            <img id="image-preview-img" alt="Image preview">
        </div>
        <br>
    <button type="submit">Create Auction</button>
  </form>
    </div>
</main>

<?php include(__DIR__ . '/../includes/footer.php'); ?>
