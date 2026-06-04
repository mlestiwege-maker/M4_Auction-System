<?php
include(__DIR__ . '/../config/db.php');
include(__DIR__ . '/../includes/auction_helpers.php');
auction_finalize_ended_items($conn);
include(__DIR__ . '/../includes/header.php');

if (!isset($_SESSION['user_id'])) {
    echo '<main><p>Please <a href="/auction_system/auth/login.php">login</a> to view your dashboard.</p></main>';
    include(__DIR__ . '/../includes/footer.php');
    exit;
}

$user_id = intval($_SESSION['user_id']);

$userStmt = $conn->prepare('SELECT name, email, created_at FROM users WHERE id=? LIMIT 1');
$userStmt->bind_param('i', $user_id);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();

$itemsStmt = $conn->prepare(
  'SELECT i.id, i.title, i.category, i.description, i.current_price, i.starting_price, i.end_time, i.image_url, i.status,
            (SELECT COUNT(*) FROM bids b WHERE b.item_id = i.id) AS bid_count,
        (SELECT MAX(bid_amount) FROM bids b WHERE b.item_id = i.id) AS top_bid
     FROM items i WHERE i.user_id=? ORDER BY i.created_at DESC'
);
$itemsStmt->bind_param('i', $user_id);
$itemsStmt->execute();
$sellerItems = $itemsStmt->get_result();

$watchlistCountStmt = $conn->prepare('SELECT COUNT(*) AS total FROM watchlist WHERE user_id=?');
$watchlistCountStmt->bind_param('i', $user_id);
$watchlistCountStmt->execute();
$watchlistCount = (int)($watchlistCountStmt->get_result()->fetch_assoc()['total'] ?? 0);

$myBidsStmt = $conn->prepare('SELECT COUNT(*) AS total FROM bids WHERE user_id=?');
$myBidsStmt->bind_param('i', $user_id);
$myBidsStmt->execute();
$myBidCount = (int)($myBidsStmt->get_result()->fetch_assoc()['total'] ?? 0);

$endedStmt = $conn->prepare(
  "SELECT COUNT(*) AS total, COALESCE(SUM(current_price), 0) AS earnings
   FROM items
   WHERE user_id=? AND (status='ended' OR (end_time IS NOT NULL AND end_time < NOW()))"
);
$endedStmt->bind_param('i', $user_id);
$endedStmt->execute();
$endedStats = $endedStmt->get_result()->fetch_assoc() ?: ['total' => 0, 'earnings' => 0];
$endedCount = (int) ($endedStats['total'] ?? 0);
$estimatedEarnings = (float) ($endedStats['earnings'] ?? 0);

$imageErrors = [];
$imageMessages = [];

function auction_dashboard_handle_upload(mysqli $conn, array $itemForImage, array $file, array &$errors, ?string &$relativePath = null): void
{
  if (empty($file['name'])) {
    $errors[] = 'Please choose an image to upload.';
    return;
  }

  if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $errors[] = 'Error uploading image.';
    return;
  }

  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mime = finfo_file($finfo, $file['tmp_name']);
  finfo_close($finfo);

  $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
  if (!in_array($mime, $allowed, true)) {
    $errors[] = 'Unsupported image type.';
    return;
  }

  $categorySlug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', (string) $itemForImage['category']), '-'));
  $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
  if ($ext === '') {
    $ext = $mime === 'image/png' ? 'png' : ($mime === 'image/gif' ? 'gif' : ($mime === 'image/webp' ? 'webp' : 'jpg'));
  }

  $dir = __DIR__ . '/../assets/uploads/originals/' . $categorySlug;
  if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
  }

  $filename = $categorySlug . '-' . (int) $itemForImage['id'] . '-' . time() . '.' . $ext;
  $dest = $dir . '/' . $filename;

  if (!move_uploaded_file($file['tmp_name'], $dest)) {
    $errors[] = 'Failed to move uploaded file.';
    return;
  }

  $relativePath = 'assets/uploads/originals/' . $categorySlug . '/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['image_manager_submit'])) {
  if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $imageErrors[] = 'Invalid CSRF token.';
  }

  $itemId = (int) ($_POST['item_id'] ?? 0);
  if (!$itemId) {
    $imageErrors[] = 'Please choose an item.';
  }

  $stmt = $conn->prepare('SELECT id, title, category FROM items WHERE id=? AND user_id=? LIMIT 1');
  $stmt->bind_param('ii', $itemId, $user_id);
  $stmt->execute();
  $itemForImage = $stmt->get_result()->fetch_assoc();

  if (!$itemForImage) {
    $imageErrors[] = 'Item not found.';
  }

  if (empty($imageErrors)) {
    $relative = null;
    auction_dashboard_handle_upload($conn, $itemForImage, $_FILES['image'], $imageErrors, $relative);
    if (!empty($relative)) {
      $update = $conn->prepare('UPDATE items SET image_url=? WHERE id=? AND user_id=?');
      $update->bind_param('sii', $relative, $itemId, $user_id);
      if ($update->execute()) {
        $imageMessages[] = 'Updated image for "' . $itemForImage['title'] . '".';
      } else {
        $imageErrors[] = 'Database update failed.';
      }
    }
  }
}

$quickEditErrors = [];
$quickEditMessages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_edit_submit'])) {
  if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $quickEditErrors[] = 'Invalid CSRF token.';
  }

  $itemId = (int) ($_POST['item_id'] ?? 0);
  if (!$itemId) {
    $quickEditErrors[] = 'Please choose an item.';
  }

  $stmt = $conn->prepare('SELECT id, title, category, image_url FROM items WHERE id=? AND user_id=? LIMIT 1');
  $stmt->bind_param('ii', $itemId, $user_id);
  $stmt->execute();
  $itemForEdit = $stmt->get_result()->fetch_assoc();

  if (!$itemForEdit) {
    $quickEditErrors[] = 'Item not found or you do not own it.';
  }

  $title = trim($_POST['title'] ?? '');
  $category = trim($_POST['category'] ?? '');
  $description = trim($_POST['description'] ?? '');
  $startPrice = (float) ($_POST['start_price'] ?? 0);
  $endTime = $_POST['end_time'] ?? '';

  if ($title === '') {
    $quickEditErrors[] = 'Title is required.';
  }
  if ($category === '') {
    $quickEditErrors[] = 'Category is required.';
  }
  if ($startPrice <= 0) {
    $quickEditErrors[] = 'Price must be greater than 0.';
  }
  if ($endTime === '') {
    $quickEditErrors[] = 'End time is required.';
  }

  $imagePath = trim((string) ($itemForEdit['image_url'] ?? ''));
  if (empty($quickEditErrors) && !empty($_FILES['image']['name'])) {
    $relative = null;
    auction_dashboard_handle_upload($conn, $itemForEdit, $_FILES['image'], $quickEditErrors, $relative);
    if (!empty($relative)) {
      $imagePath = $relative;
    }
  } elseif (empty($quickEditErrors) && !empty($_POST['remove_image'])) {
    $imagePath = '';
  }

  if (empty($quickEditErrors)) {
    if (empty($imagePath)) {
      $imagePath = auction_category_fallback_image($category, $title);
    }

    $currentPrice = max((float) ($itemForEdit['current_price'] ?? 0), $startPrice);
    $update = $conn->prepare('UPDATE items SET title=?, category=?, description=?, starting_price=?, current_price=?, image_url=?, end_time=? WHERE id=? AND user_id=?');
    $update->bind_param('sssddssii', $title, $category, $description, $startPrice, $currentPrice, $imagePath, $endTime, $itemId, $user_id);

    if ($update->execute()) {
      $quickEditMessages[] = 'Updated "' . $title . '" successfully.';
    } else {
      $quickEditErrors[] = 'Database update failed.';
    }
  }
}

$imageItemsStmt = $conn->prepare('SELECT id, title, category, image_url FROM items WHERE user_id=? ORDER BY category, title');
$imageItemsStmt->bind_param('i', $user_id);
$imageItemsStmt->execute();
$imageItems = $imageItemsStmt->get_result();

?>

<main>
  <style>
    .dashboard-hero {
      display: grid;
      grid-template-columns: 1.15fr .85fr;
      gap: 1.5rem;
      padding: 2rem;
      border-radius: 20px;
      margin-bottom: 1.5rem;
      background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
      color: white;
      box-shadow: 0 18px 40px rgba(15,23,42,0.14);
    }
    .dashboard-hero .metric-card {
      background: rgba(255,255,255,0.14);
      border: 1px solid rgba(255,255,255,0.18);
      color: white;
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
    }
    .dashboard-hero .status-pill {
      background: rgba(255,255,255,0.16);
      color: white;
      border-color: rgba(255,255,255,0.22);
    }
    .dashboard-stats {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 1rem;
      margin: 1rem 0 1.5rem;
    }
    .dashboard-stat {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 1rem 1.1rem;
      box-shadow: 0 10px 24px rgba(15,23,42,0.06);
    }
    .dashboard-stat strong { display:block; font-size: 1.5rem; color: var(--primary); margin-bottom: .2rem; }
    .dashboard-stat span { color: var(--text-light); }
    .dashboard-actions { display:flex; gap:.75rem; flex-wrap:wrap; margin-top: .8rem; }
    .quick-edit-toggle {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 100%;
      margin-top: .4rem;
    }
    .quick-edit-trigger {
      width: 100%;
      margin-top: .8rem;
    }
    .quick-edit-modal {
      position: fixed;
      inset: 0;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 1rem;
      z-index: 2000;
    }
    .quick-edit-modal.is-open { display: flex; }
    .quick-edit-modal-backdrop {
      position: absolute;
      inset: 0;
      background: rgba(15, 23, 42, 0.58);
      backdrop-filter: blur(5px);
    }
    .quick-edit-modal-content {
      position: relative;
      width: min(920px, 100%);
      max-height: 90vh;
      overflow: auto;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 24px;
      box-shadow: var(--shadow-lg);
      padding: 1.25rem;
    }
    .quick-edit-modal-head {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 1rem;
      margin-bottom: 1rem;
    }
    .quick-edit-close {
      border: 0;
      background: transparent;
      color: var(--text-light);
      font-size: 1.8rem;
      line-height: 1;
      cursor: pointer;
    }
    .quick-edit-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: .75rem;
    }
    .quick-edit-grid .full { grid-column: 1 / -1; }
    .quick-edit-grid input,
    .quick-edit-grid select,
    .quick-edit-grid textarea {
      width: 100%;
      padding: .75rem .85rem;
    }
    .quick-edit-grid textarea { min-height: 92px; }
    .quick-edit-grid .card-actions { margin-top: .5rem; grid-column: 1 / -1; }
    @media (max-width: 900px) { .dashboard-hero, .dashboard-stats { grid-template-columns: 1fr; } }
    .quick-edit-side {
      background: var(--surface-alt);
      border: 1px solid var(--border);
      border-radius: 18px;
      padding: 1rem;
    }
    .quick-edit-preview img {
      width: 100%;
      aspect-ratio: 4 / 3;
      object-fit: cover;
      border-radius: 14px;
      border: 1px solid var(--border);
      margin-bottom: .75rem;
    }
    @media (max-width: 720px) { .quick-edit-grid { grid-template-columns: 1fr; } }
  </style>

  <section class="dashboard-hero">
    <div>
      <div class="hero-chip">AH AuctionHub • Seller Dashboard</div>
      <h2>Your Dashboard</h2>
      <p>Track auctions, bids, and earnings in one place.</p>
      <div class="dashboard-actions">
        <a class="btn secondary" href="/auction_system/items/create_item.php">Create Listing</a>
        <a class="btn secondary" href="/auction_system/items/all_items.php">Browse Market</a>
        <a class="btn secondary" href="#manage-images">Manage Images</a>
      </div>
    </div>
    <div class="metric-card">
      <h3><?= htmlspecialchars($user['name'] ?? 'User') ?></h3>
      <p><?= htmlspecialchars($user['email'] ?? '') ?></p>
      <div class="auction-status">
        <span class="status-pill active"><?= $watchlistCount ?> Watchlisted</span>
        <span class="status-pill active"><?= $myBidCount ?> Bids Placed</span>
      </div>
    </div>
  </section>

  <section class="dashboard-stats">
    <div class="dashboard-stat"><strong><?= (int)$sellerItems->num_rows ?></strong><span>Active listings</span></div>
    <div class="dashboard-stat"><strong><?= $watchlistCount ?></strong><span>Watchlisted items</span></div>
    <div class="dashboard-stat"><strong><?= $myBidCount ?></strong><span>Total bids placed</span></div>
    <div class="dashboard-stat"><strong>$<?= number_format($estimatedEarnings, 2) ?></strong><span>Estimated earnings</span></div>
  </section>

  <section class="create-card mt-lg" id="manage-images">
    <div class="section-head compact-head">
      <div>
        <span class="page-chip">Image manager</span>
        <h3>Update item images from the dashboard</h3>
      </div>
    </div>
    <p class="form-note">Only the item owner can change the title, price, or picture. Use the form below to swap an image, or open the edit page for full listing updates.</p>

    <?php if (!empty($imageErrors)): ?>
      <div class="notice notice-error"><?php foreach ($imageErrors as $e) echo '<p>' . htmlspecialchars($e) . '</p>'; ?></div>
    <?php endif; ?>
    <?php if (!empty($imageMessages)): ?>
      <div class="notice notice-success"><?php foreach ($imageMessages as $m) echo '<p>' . htmlspecialchars($m) . '</p>'; ?></div>
    <?php endif; ?>
    <?php if (!empty($quickEditErrors)): ?>
      <div class="notice notice-error"><?php foreach ($quickEditErrors as $e) echo '<p>' . htmlspecialchars($e) . '</p>'; ?></div>
    <?php endif; ?>
    <?php if (!empty($quickEditMessages)): ?>
      <div class="notice notice-success"><?php foreach ($quickEditMessages as $m) echo '<p>' . htmlspecialchars($m) . '</p>'; ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="item-form">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <div class="item-form-grid">
        <div class="form-stack">
          <label>Item
            <select name="item_id" required>
              <option value="">Choose an item</option>
              <?php if ($imageItems): ?>
                <?php while ($imageRow = $imageItems->fetch_assoc()): ?>
                  <option value="<?= (int) $imageRow['id'] ?>"><?= htmlspecialchars($imageRow['category'] . ' — ' . $imageRow['title']) ?></option>
                <?php endwhile; ?>
              <?php endif; ?>
            </select>
          </label>
          <label>New image
            <input type="file" name="image" accept="image/*" required>
          </label>
          <button type="submit" name="image_manager_submit" value="1" class="btn">Save image</button>
        </div>

        <div class="form-stack form-side-card">
          <div class="image-manager-preview">
            <img src="/auction_system/assets/uploads/originals/fashion/fashion-1.jpeg" alt="Example image manager preview">
          </div>
          <div class="form-note-block">
            <strong>Tip:</strong> This is the browser-friendly way to replace images without editing files by hand.
          </div>
        </div>
      </div>
    </form>

    <div class="image-manager-grid mt-md">
      <?php
        if ($imageItems) {
            $imageItems->data_seek(0);
            while ($imageRow = $imageItems->fetch_assoc()):
              $imageUrl = auction_item_image_url($imageRow);
      ?>
        <article class="image-manager-card">
          <strong><?= htmlspecialchars($imageRow['title']) ?></strong>
          <p class="muted"><?= htmlspecialchars($imageRow['category']) ?></p>
          <div class="image-manager-preview">
            <img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($imageRow['title']) ?>">
            <span class="image-manager-category-badge"><?= htmlspecialchars($imageRow['category']) ?></span>
          </div>
          <p class="form-note"><?= htmlspecialchars($imageRow['image_url'] ?: 'Uses category fallback') ?></p>
          <div class="image-manager-actions">
            <a class="btn secondary" href="/auction_system/items/edit_item.php?id=<?= (int) $imageRow['id'] ?>">Edit title, price & image</a>
          </div>
        </article>
      <?php
            endwhile;
        }
      ?>
    </div>
  </section>

  <section class="mt-lg">
    <h2>Your Auctions</h2>
    <?php if ($sellerItems->num_rows === 0): ?>
      <div class="create-card"><p>You haven’t posted any items yet. Start your first auction from the sell page.</p></div>
    <?php else: ?>
      <div class="auction-grid">
        <?php while ($row = $sellerItems->fetch_assoc()): ?>
          <?php $ended = !empty($row['end_time']) && (new DateTime() > new DateTime($row['end_time'])); ?>
          <?php $imageUrl = auction_item_image_url($row); ?>
          <article class="auction-card dashboard-auction-card">
            <?php if (!empty($imageUrl)): ?>
              <img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($row['title']) ?>">
            <?php endif; ?>
            <div class="auction-card-content">
              <h3><?= htmlspecialchars($row['title']) ?></h3>
              <?php if (!empty($row['category'])): ?>
                <p class="muted"><?= htmlspecialchars($row['category']) ?></p>
              <?php endif; ?>
              <p><strong>$<?= number_format($row['current_price'] ?: $row['starting_price'], 2) ?></strong></p>
              <div class="auction-status">
                <span class="status-pill <?= $ended ? 'ended' : 'active' ?>"><?= $ended ? 'Ended' : 'Active' ?></span>
                <span><?= (int)$row['bid_count'] ?> bids</span>
              </div>
              <p class="form-note">Top bid: $<?= number_format((float)($row['top_bid'] ?? $row['starting_price']), 2) ?></p>
              <div class="card-actions">
                <a class="btn" href="/auction_system/items/view_item.php?id=<?= $row['id'] ?>">Open Auction</a>
                <a class="btn secondary" href="/auction_system/items/edit_item.php?id=<?= $row['id'] ?>">Edit listing</a>
                <a class="btn secondary" href="/auction_system/items/delete_item.php?id=<?= $row['id'] ?>">Delete</a>
              </div>
              <button type="button" class="btn secondary quick-edit-trigger"
                data-quick-edit-open="1"
                data-item-id="<?= (int) $row['id'] ?>"
                data-item-title="<?= htmlspecialchars($row['title'], ENT_QUOTES) ?>"
                data-item-category="<?= htmlspecialchars($row['category'] ?? '', ENT_QUOTES) ?>"
                data-item-description="<?= htmlspecialchars($row['description'] ?? '', ENT_QUOTES) ?>"
                data-item-start-price="<?= htmlspecialchars((string) ($row['starting_price'] ?? '')) ?>"
                data-item-end-time="<?= htmlspecialchars(date('Y-m-d\TH:i', strtotime($row['end_time']))) ?>"
                data-item-image="<?= htmlspecialchars($imageUrl, ENT_QUOTES) ?>">
                Quick edit this listing
              </button>
            </div>
          </article>
        <?php endwhile; ?>
      </div>
    <?php endif; ?>
  </section>

  <div class="quick-edit-modal" id="quick-edit-modal" aria-hidden="true">
    <div class="quick-edit-modal-backdrop" data-quick-edit-close></div>
    <div class="quick-edit-modal-content" role="dialog" aria-modal="true" aria-labelledby="quick-edit-title">
      <div class="quick-edit-modal-head">
        <div>
          <span class="page-chip">Quick edit</span>
          <h3 id="quick-edit-title">Edit listing</h3>
          <p class="form-note" id="quick-edit-subtitle">Update the selected item without leaving the dashboard.</p>
        </div>
        <button type="button" class="quick-edit-close" data-quick-edit-close aria-label="Close quick edit">&times;</button>
      </div>

      <form method="POST" enctype="multipart/form-data" class="item-form" id="quick-edit-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="item_id" id="quick-edit-item-id" value="">
        <div class="quick-edit-grid">
          <div class="form-stack">
            <label class="full">Title
              <input type="text" name="title" id="quick-edit-title-field" required>
            </label>
            <label>Category
              <select name="category" id="quick-edit-category-field" required>
                <?php foreach (['Electronics','Fashion','Home & Garden','Collectibles','Vehicles','Other'] as $cat): ?>
                  <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>Start price
              <input type="number" name="start_price" id="quick-edit-start-price-field" step="0.01" required>
            </label>
            <label class="full">End time
              <input type="datetime-local" name="end_time" id="quick-edit-end-time-field" required>
            </label>
            <label class="full">Description
              <textarea name="description" id="quick-edit-description-field" rows="5"></textarea>
            </label>
          </div>

          <div class="quick-edit-side">
            <div class="quick-edit-preview">
              <img id="quick-edit-image-preview" src="" alt="Current listing image">
            </div>
            <label>Replace image
              <input type="file" name="image" accept="image/*">
            </label>
            <label class="checkbox-row">
              <input type="checkbox" name="remove_image" value="1" id="quick-edit-remove-image">
              <span>Remove current image and use category fallback</span>
            </label>
            <p class="form-note">You can replace the image now, or remove it entirely and let the category fallback show.</p>
          </div>

          <div class="card-actions full">
            <button type="submit" name="quick_edit_submit" value="1" class="btn">Save changes</button>
            <button type="button" class="btn secondary" data-quick-edit-close>Cancel</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <script>
    (function () {
      const modal = document.getElementById('quick-edit-modal');
      const titleField = document.getElementById('quick-edit-title-field');
      const categoryField = document.getElementById('quick-edit-category-field');
      const startPriceField = document.getElementById('quick-edit-start-price-field');
      const endTimeField = document.getElementById('quick-edit-end-time-field');
      const descField = document.getElementById('quick-edit-description-field');
      const imagePreview = document.getElementById('quick-edit-image-preview');
      const itemIdField = document.getElementById('quick-edit-item-id');
      const subtitle = document.getElementById('quick-edit-subtitle');
      const removeImage = document.getElementById('quick-edit-remove-image');

      function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
      }

      document.querySelectorAll('[data-quick-edit-open]').forEach((button) => {
        button.addEventListener('click', () => {
          itemIdField.value = button.dataset.itemId || '';
          titleField.value = button.dataset.itemTitle || '';
          categoryField.value = button.dataset.itemCategory || 'Other';
          startPriceField.value = button.dataset.itemStartPrice || '';
          endTimeField.value = button.dataset.itemEndTime || '';
          descField.value = button.dataset.itemDescription || '';
          imagePreview.src = button.dataset.itemImage || '/auction_system/assets/uploads/fallbacks/other.jpg';
          subtitle.textContent = `Editing ${button.dataset.itemTitle || 'listing'} — change title, price, category, description, or image.`;
          removeImage.checked = false;
          modal.classList.add('is-open');
          modal.setAttribute('aria-hidden', 'false');
          titleField.focus();
        });
      });

      modal.querySelectorAll('[data-quick-edit-close]').forEach((el) => {
        el.addEventListener('click', closeModal);
      });

      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
          closeModal();
        }
      });

      modal.addEventListener('click', (event) => {
        if (event.target === modal) {
          closeModal();
        }
      });
    })();
  </script>
</main>

<?php include(__DIR__ . '/../includes/footer.php'); ?>
