<?php
include(__DIR__ . '/../config/db.php');
include(__DIR__ . '/../includes/auction_helpers.php');
include(__DIR__ . '/../includes/rbac_helpers.php');
include(__DIR__ . '/../includes/header.php');

if (!isset($_SESSION['user_id'])) {
    echo '<main><p>You must <a href="/auction_system/auth/login.php">login</a> to create an item.</p></main>';
    include(__DIR__ . '/../includes/footer.php');
    exit;
}

// Check seller role
if (!is_seller()) {
    ?>
    <main class="container">
        <section class="auth-page">
            <div class="auth-card" style="max-width: 600px; margin: 60px auto;">
                <h2>Seller Access Required</h2>
                <p>To create and sell items on AuctionHub, you need to be approved as a seller.</p>
                
                <div class="info-box" style="background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 4px; margin: 20px 0;">
                    <strong>Next steps:</strong>
                    <ol>
                        <li>Visit your <a href="/auction_system/seller/request_approval.php">Seller Request Page</a></li>
                        <li>Submit your seller application</li>
                        <li>Wait for admin approval</li>
                        <li>Start creating listings!</li>
                    </ol>
                </div>
                
                <a href="/auction_system/seller/request_approval.php" class="btn" style="background: #27ae60; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block;">Request Seller Status</a>
            </div>
        </section>
    </main>
    <?php
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
    $start_price = (float) ($_POST['start_price'] ?? 0);
    $end_time = trim($_POST['end_time'] ?? '');

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

            $allowed = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($mime, $allowed, true)) {
                $errors[] = 'Unsupported image type.';
            } else {
                $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
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
        }
    }

    if (empty($errors)) {
        $user_id = (int) $_SESSION['user_id'];
        if ($image_path === null) {
            $image_path = auction_category_fallback_image($category, $title);
        }

        $stmt = $conn->prepare('INSERT INTO items (user_id, title, category, description, starting_price, current_price, image_url, end_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('isssddss', $user_id, $title, $category, $description, $start_price, $start_price, $image_path, $end_time);

        if ($stmt->execute()) {
            header('Location: /auction_system/items/all_items.php');
            exit;
        }

        $errors[] = 'A database error occurred. Please try again.';
    }
}
?>
<main>
    <section class="hero item-hero">
        <div>
            <span class="page-chip">Sell item</span>
            <h2>Create Listing</h2>
            <p>Upload an item, set your starting price, and launch your auction in seconds.</p>
            <div class="auction-status">
                <span class="status-pill active">Smart pricing</span>
                <span class="status-pill">Image preview</span>
                <span class="status-pill">Responsive listing</span>
            </div>
        </div>
        <div class="metric-card">
            <h3>Launch checklist</h3>
            <p>Add a strong title, choose the right category, and finish with a realistic starting price.</p>
            <div class="auction-status">
                <span class="status-pill">Title</span>
                <span class="status-pill">Category</span>
                <span class="status-pill">Image</span>
                <span class="status-pill">End time</span>
            </div>
        </div>
    </section>

    <div class="create-card item-form-card">
        <div class="section-head">
            <div>
                <span class="page-chip">Item details</span>
                <h2>Create your listing</h2>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="notice notice-error"><?php foreach ($errors as $e) echo '<p>' . htmlspecialchars($e) . '</p>'; ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="item-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="item-form-grid">
                <div class="form-stack">
                    <label>Title
                        <input type="text" name="title" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" placeholder="e.g. Vintage wristwatch">
                    </label>
                    <label>Category
                        <select name="category" required>
                            <option value="">Select category</option>
                            <option value="Electronics" <?= (($_POST['category'] ?? '') === 'Electronics') ? 'selected' : '' ?>>Electronics</option>
                            <option value="Fashion" <?= (($_POST['category'] ?? '') === 'Fashion') ? 'selected' : '' ?>>Fashion</option>
                            <option value="Home & Garden" <?= (($_POST['category'] ?? '') === 'Home & Garden') ? 'selected' : '' ?>>Home & Garden</option>
                            <option value="Collectibles" <?= (($_POST['category'] ?? '') === 'Collectibles') ? 'selected' : '' ?>>Collectibles</option>
                            <option value="Vehicles" <?= (($_POST['category'] ?? '') === 'Vehicles') ? 'selected' : '' ?>>Vehicles</option>
                            <option value="Other" <?= (($_POST['category'] ?? '') === 'Other') ? 'selected' : '' ?>>Other</option>
                        </select>
                    </label>
                    <label>Description
                        <textarea name="description" rows="7" placeholder="Include condition, key features, and what buyers should know."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </label>
                    <label>Start Price
                        <input type="number" name="start_price" step="0.01" required value="<?= htmlspecialchars($_POST['start_price'] ?? '') ?>" placeholder="0.00">
                    </label>
                    <div id="smart-price-suggestion" class="smart-suggestion">Enter title + category to get smart price suggestion.</div>
                    <label>End Time
                        <input type="datetime-local" name="end_time" required value="<?= htmlspecialchars($_POST['end_time'] ?? '') ?>">
                    </label>
                </div>

                <div class="form-stack form-side-card">
                    <div>
                        <label>Image
                            <input type="file" name="image" accept="image/*">
                        </label>
                        <p class="form-note">High-quality images get more bids. Preview updates instantly.</p>
                    </div>
                    <div id="image-preview" class="image-preview">
                        <img id="image-preview-img" alt="Image preview">
                    </div>
                    <div class="form-note-block">
                        <strong>Pro Tip:</strong> Clear titles and competitive starting prices attract more bids.
                    </div>
                </div>
            </div>

            <div class="card-actions">
                <button type="submit" class="btn">Create Listing</button>
                <a class="btn secondary" href="/auction_system/items/all_items.php">Preview marketplace</a>
            </div>
        </form>
    </div>
</main>

<?php include(__DIR__ . '/../includes/footer.php'); ?>
