<?php
include(__DIR__ . '/../config/db.php');
include(__DIR__ . '/../includes/auction_helpers.php');
auction_finalize_ended_items($conn);
include(__DIR__ . '/../includes/header.php');

$user_id = intval($_SESSION['user_id'] ?? 0);
$q = trim($_GET['q'] ?? '');
$mine = isset($_GET['mine']) && $_GET['mine'] === '1';
$min_price = floatval($_GET['min_price'] ?? 0);
$max_price = floatval($_GET['max_price'] ?? 0);
$status = $_GET['status'] ?? 'all';
$sort = $_GET['sort'] ?? 'newest';
$allowedSorts = ['newest', 'price_asc', 'price_desc', 'ending_soon'];
if (!in_array($sort, $allowedSorts, true)) {
  $sort = 'newest';
}
$category = trim($_GET['category'] ?? 'all');

if (!function_exists('auction_bind_params')) {
  function auction_bind_params(mysqli_stmt $stmt, string $types, array &$params): bool
  {
    $bind = [$types];
    foreach ($params as $index => &$value) {
      $bind[$index + 1] = &$value;
    }
    return call_user_func_array([$stmt, 'bind_param'], $bind);
  }
}

$sql = 'SELECT i.id, i.title, i.category, i.current_price, i.starting_price, i.end_time, i.image_url, i.user_id, i.description, COALESCE((SELECT ROUND(AVG(rating),2) FROM reviews r WHERE r.seller_id = i.user_id), 0) AS seller_rating';
$sql .= $user_id ? ', CASE WHEN w.id IS NULL THEN 0 ELSE 1 END AS watched' : ', 0 AS watched';
$sql .= ' FROM items i';
if ($user_id) {
  $sql .= ' LEFT JOIN watchlist w ON w.item_id = i.id AND w.user_id = ?';
}

$where = [];
$types = '';
$params = [];

if ($q !== '') {
  $where[] = '(i.title LIKE ? OR i.description LIKE ?)';
  $like = '%' . $q . '%';
  $types .= 'ss';
  $params[] = $like;
  $params[] = $like;
}

if ($min_price > 0) {
  $where[] = 'COALESCE(i.current_price, i.starting_price) >= ?';
  $types .= 'd';
  $params[] = $min_price;
}

if ($max_price > 0) {
  $where[] = 'COALESCE(i.current_price, i.starting_price) <= ?';
  $types .= 'd';
  $params[] = $max_price;
}

if ($status === 'active') {
  $where[] = '(i.end_time IS NULL OR i.end_time > NOW())';
} elseif ($status === 'ended') {
  $where[] = '(i.end_time IS NOT NULL AND i.end_time <= NOW())';
}

if ($category !== '' && $category !== 'all') {
  $where[] = 'i.category = ?';
  $types .= 's';
  $params[] = $category;
}

// If the user asked to only show their listings, constrain by session user id directly
if ($mine && $user_id) {
  // use direct integer injection (safe since $user_id is int from session)
  $where[] = 'i.user_id = ' . intval($user_id);
}

if ($where) {
  $sql .= ' WHERE ' . implode(' AND ', $where);
}

switch ($sort) {
  case 'price_asc':
    $sql .= ' ORDER BY COALESCE(i.current_price, i.starting_price) ASC';
    break;
  case 'price_desc':
    $sql .= ' ORDER BY COALESCE(i.current_price, i.starting_price) DESC';
    break;
  case 'ending_soon':
    $sql .= ' ORDER BY CASE WHEN i.end_time IS NULL THEN 1 ELSE 0 END, i.end_time ASC';
    break;
  default:
    $sql .= ' ORDER BY i.created_at DESC';
}

$stmt = $conn->prepare($sql);
if ($stmt && $params) {
  if ($user_id) {
    $types = 'i' . $types;
    array_unshift($params, $user_id);
  }
  auction_bind_params($stmt, $types, $params);
  $stmt->execute();
  $res = $stmt->get_result();
} else {
  if ($stmt && $user_id) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
  } else {
    $res = $conn->query($sql);
  }
}

$trendingSql =
  "SELECT i.id, i.title, i.category, i.current_price, i.starting_price, i.image_url,
          COUNT(b.id) AS bid_count
   FROM items i
   LEFT JOIN bids b ON b.item_id = i.id
   WHERE (i.status IS NULL OR i.status='active')";

if ($category !== '' && $category !== 'all') {
  $trendingSql .= ' AND i.category = ?';
}

$trendingSql .= ' GROUP BY i.id ORDER BY bid_count DESC, i.current_price DESC LIMIT 4';

$trendingStmt = $conn->prepare($trendingSql);
if ($trendingStmt && $category !== '' && $category !== 'all') {
  $trendingStmt->bind_param('s', $category);
  $trendingStmt->execute();
  $trending = $trendingStmt->get_result();
} elseif ($trendingStmt) {
  $trendingStmt->execute();
  $trending = $trendingStmt->get_result();
} else {
  $trending = $conn->query(
    "SELECT i.id, i.title, i.category, i.current_price, i.starting_price, i.image_url,
            COUNT(b.id) AS bid_count
     FROM items i
     LEFT JOIN bids b ON b.item_id = i.id
     WHERE (i.status IS NULL OR i.status='active')
     GROUP BY i.id
     ORDER BY bid_count DESC, i.current_price DESC
     LIMIT 4"
  );
}
?>
<main>
  <section class="hero item-hero">
    <div>
      <span class="page-chip">Browse auctions</span>
      <h2>All Auctions</h2>
      <p>Browse active listings, compare prices, and jump into live bidding before the timer runs out.</p>
      <div class="auction-status">
        <span class="status-pill active">Live bidding</span>
        <span class="status-pill">Watchlists</span>
        <span class="status-pill">Smart search</span>
      </div>
    </div>
    <div class="metric-card">
      <h3>Browse smarter</h3>
      <p>Filter by category, price, and status, then jump straight to items with the strongest momentum.</p>
      <div class="auction-status">
        <span class="status-pill">Trending</span>
        <span class="status-pill">Ending soon</span>
        <span class="status-pill">Top rated</span>
      </div>
    </div>
  </section>

  <section class="dashboard-stats" aria-label="Browse summary">
    <div class="dashboard-stat"><strong class="value"><?= $res instanceof mysqli_result ? (int) $res->num_rows : 0 ?></strong><span class="label">Listings shown</span></div>
    <div class="dashboard-stat"><strong class="value"><?= $trending ? (int) $trending->num_rows : 0 ?></strong><span class="label">Trending now</span></div>
    <div class="dashboard-stat"><strong class="value"><?= $category !== 'all' ? htmlspecialchars($category) : 'All' ?></strong><span class="label">Current category</span></div>
    <div class="dashboard-stat"><strong class="value"><?= $status === 'all' ? 'Any status' : ucfirst($status) ?></strong><span class="label">Current filter</span></div>
  </section>

  <section class="create-card filter-card">
    <div class="section-head compact-head">
      <div>
        <span class="page-chip">Search & filters</span>
        <h3>Refine the marketplace</h3>
      </div>
    </div>
    <p class="form-note">Use the filters to narrow the view, or clear them and explore everything live on the marketplace.</p>
    <form method="GET" class="filter-form">
      <input type="text" name="q" placeholder="Search title or description" value="<?= htmlspecialchars($q) ?>">
      <select name="category">
        <option value="all" <?= $category === 'all' ? 'selected' : '' ?>>All categories</option>
        <option value="Electronics" <?= $category === 'Electronics' ? 'selected' : '' ?>>Electronics</option>
        <option value="Fashion" <?= $category === 'Fashion' ? 'selected' : '' ?>>Fashion</option>
        <option value="Home & Garden" <?= $category === 'Home & Garden' ? 'selected' : '' ?>>Home & Garden</option>
        <option value="Collectibles" <?= $category === 'Collectibles' ? 'selected' : '' ?>>Collectibles</option>
        <option value="Vehicles" <?= $category === 'Vehicles' ? 'selected' : '' ?>>Vehicles</option>
        <option value="Other" <?= $category === 'Other' ? 'selected' : '' ?>>Other</option>
      </select>
      <input type="number" name="min_price" step="0.01" placeholder="Min price" value="<?= htmlspecialchars($_GET['min_price'] ?? '') ?>">
      <input type="number" name="max_price" step="0.01" placeholder="Max price" value="<?= htmlspecialchars($_GET['max_price'] ?? '') ?>">
      <select name="status">
        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="ended" <?= $status === 'ended' ? 'selected' : '' ?>>Ended</option>
      </select>
      <select name="sort">
        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
        <option value="ending_soon" <?= $sort === 'ending_soon' ? 'selected' : '' ?>>Ending soon</option>
        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to high</option>
        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to low</option>
      </select>
      <label style="display:inline-flex;align-items:center;gap:.5rem">
        <input type="checkbox" name="mine" value="1" <?= $mine ? 'checked' : '' ?>>
        <span style="font-weight:600">My listings</span>
      </label>
      <button type="submit" class="btn">Apply Filters</button>
      <a class="btn secondary" href="/auction_system/items/all_items.php">Reset</a>
    </form>
  </section>

  <?php if ($category === 'Fashion'): ?>
    <section class="create-card">
      <div class="section-head compact-head">
        <div>
          <span class="page-chip">Fashion showcase</span>
          <h3>Featured fashion looks</h3>
        </div>
      </div>
      <p class="muted muted-tight">A quick preview of the four fashion images you added.</p>
      <div class="fashion-showcase-grid">
        <?php
          $fashionShowcase = [
            ['src' => '/auction_system/assets/uploads/originals/fashion/fashion-1.jpeg', 'label' => 'Look 1'],
            ['src' => '/auction_system/assets/uploads/originals/fashion/fashion-2.jpeg', 'label' => 'Look 2'],
            ['src' => '/auction_system/assets/uploads/originals/fashion/fashion-3.jpeg', 'label' => 'Look 3'],
            ['src' => '/auction_system/assets/uploads/originals/fashion/fashion-4.jpeg', 'label' => 'Look 4'],
          ];
        ?>
        <?php foreach ($fashionShowcase as $look): ?>
          <article class="fashion-showcase-card">
            <img src="<?= htmlspecialchars($look['src']) ?>" alt="<?= htmlspecialchars($look['label']) ?>" loading="lazy">
            <div class="fashion-showcase-body">
              <h4><?= htmlspecialchars($look['label']) ?></h4>
              <p>Fashion original</p>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <section class="create-card">
    <div class="section-head compact-head">
      <div>
        <span class="page-chip">Trending</span>
        <h3>🔥 Trending Auctions</h3>
      </div>
    </div>
    <p class="muted muted-tight">Most bid-on live listings right now.</p>
    <div class="trending-grid">
      <?php if ($trending): ?>
        <?php while ($t = $trending->fetch_assoc()): ?>
          <article class="trending-card">
            <img class="trending-thumb" src="<?= htmlspecialchars(auction_item_image_url($t)) ?>" alt="<?= htmlspecialchars($t['title']) ?>" loading="lazy">
            <div style="color:red; font-size:0.8rem;">DEBUG</div>
            <?php // Debug: echo htmlspecialchars(auction_item_image_url($t)); ?>
            <h4><?= htmlspecialchars($t['title']) ?></h4>
            <div class="trending-meta">
              <span><?= htmlspecialchars($t['category'] ?: 'General') ?></span>
              <span><?= (int) $t['bid_count'] ?> bids</span>
            </div>
            <p class="seller-rating">⭐ Top momentum</p>
            <p><strong>$<?= number_format((float) ($t['current_price'] ?: $t['starting_price']), 2) ?></strong></p>
            <a class="btn" href="/auction_system/items/view_item.php?id=<?= (int) $t['id'] ?>">Join Auction</a>
          </article>
        <?php endwhile; ?>
      <?php endif; ?>
    </div>
  </section>

  <section class="auction-grid">
    <?php if ($res && $res->num_rows > 0): ?>
      <?php while ($row = $res->fetch_assoc()): ?>
        <?php
          $ended = !empty($row['end_time']) && (new DateTime() > new DateTime($row['end_time']));
          $secondsLeft = !$ended && !empty($row['end_time']) ? (strtotime($row['end_time']) - time()) : 0;
          $isEndingSoon = $secondsLeft > 0 && $secondsLeft <= 3600;
        ?>
        <article class="auction-card <?= $isEndingSoon ? 'ending-soon' : '' ?>" 
           data-item-id="<?= (int) $row['id'] ?>"
           data-item-title="<?= htmlspecialchars($row['title'], ENT_QUOTES) ?>"
           data-item-category="<?= htmlspecialchars($row['category'] ?? '', ENT_QUOTES) ?>"
           data-item-description="<?= htmlspecialchars($row['description'] ?? '', ENT_QUOTES) ?>"
           data-item-start-price="<?= htmlspecialchars((string) ($row['starting_price'] ?? ''), ENT_QUOTES) ?>"
           data-item-end-time="<?= htmlspecialchars(!empty($row['end_time']) ? date('Y-m-d\TH:i', strtotime($row['end_time'])) : '', ENT_QUOTES) ?>"
           data-item-image="<?= htmlspecialchars(auction_item_image_url($row), ENT_QUOTES) ?>">
          <button class="card-fav" data-item-id="<?= (int) $row['id'] ?>" data-action="<?= !empty($row['watched']) ? 'remove' : 'add' ?>" aria-pressed="<?= !empty($row['watched']) ? 'true' : 'false' ?>"><?= !empty($row['watched']) ? '♥' : '♡' ?></button>
          <?php if ($user_id && $user_id === (int) $row['user_id']): ?>
            <button type="button" class="card-image-edit" data-quick-edit-open="1"
              data-item-id="<?= (int) $row['id'] ?>"
              data-item-title="<?= htmlspecialchars($row['title'], ENT_QUOTES) ?>"
              data-item-category="<?= htmlspecialchars($row['category'] ?? '', ENT_QUOTES) ?>"
              data-item-description="<?= htmlspecialchars($row['description'] ?? '', ENT_QUOTES) ?>"
              data-item-start-price="<?= htmlspecialchars((string) ($row['starting_price'] ?? ''), ENT_QUOTES) ?>"
              data-item-end-time="<?= htmlspecialchars(!empty($row['end_time']) ? date('Y-m-d\TH:i', strtotime($row['end_time'])) : '', ENT_QUOTES) ?>"
              data-item-image="<?= htmlspecialchars(auction_item_image_url($row), ENT_QUOTES) ?>" aria-label="Edit listing">
              ✎
            </button>
          <?php endif; ?>
            <img src="<?= htmlspecialchars(auction_item_image_url($row)) ?>" alt="<?= htmlspecialchars($row["title"]) ?>" loading="lazy" referrerpolicy="no-referrer" style="width:100%; aspect-ratio:4/3; object-fit:cover;">
          <div class="auction-card-content">
            <h3><?= htmlspecialchars($row['title']) ?></h3>
            <div class="seller-meta">
              <span class="seller-rating">⭐ <?= number_format((float) ($row['seller_rating'] ?? 0), 2) ?></span>
            </div>
            <?php if (!empty($row['category'])): ?>
              <p class="muted"><?= htmlspecialchars($row['category']) ?></p>
            <?php endif; ?>
            <p class="auction-price"><strong>$<?= number_format($row['current_price'] ?: $row['starting_price'],2) ?></strong></p>
            <div class="auction-status">
              <span class="status-pill <?= $ended ? 'ended' : 'active' ?>"><?= $ended ? 'Ended' : 'Active' ?></span>
              <?php if (!empty($row['end_time'])): ?>
                <span><?= htmlspecialchars($row['end_time']) ?></span>
                <?php if (!$ended): ?>
                  <span class="countdown-badge live-countdown" data-end-time="<?= htmlspecialchars($row['end_time']) ?>">
                    <?= $isEndingSoon ? 'Ending soon' : 'Live' ?>
                  </span>
                <?php endif; ?>
              <?php endif; ?>
            </div>
            <p class="form-note">Tap into the live auction, save it to your watchlist, or jump in if the price looks right.</p>
            <p class="card-actions">
              <a class="btn" href="/auction_system/items/view_item.php?id=<?= $row['id'] ?>">View Item</a>
              <?php if (isset($_SESSION['user_id'])): ?>
                <button class="btn secondary watchlist-toggle" data-item-id="<?= $row['id'] ?>" data-action="<?= !empty($row['watched']) ? 'remove' : 'add' ?>">
                  <?= !empty($row['watched']) ? 'Remove Watchlist' : 'Add Watchlist' ?>
                </button>
              <?php endif; ?>
              <?php if ($user_id && $user_id === (int) $row['user_id']): ?>
                <button type="button" class="btn tertiary quick-edit-trigger" 
                  data-quick-edit-open="1"
                  data-item-id="<?= (int) $row['id'] ?>"
                  data-item-title="<?= htmlspecialchars($row['title'], ENT_QUOTES) ?>"
                  data-item-category="<?= htmlspecialchars($row['category'] ?? '', ENT_QUOTES) ?>"
                  data-item-description="<?= htmlspecialchars($row['description'] ?? '', ENT_QUOTES) ?>"
                  data-item-start-price="<?= htmlspecialchars((string) ($row['starting_price'] ?? ''), ENT_QUOTES) ?>"
                  data-item-end-time="<?= htmlspecialchars(!empty($row['end_time']) ? date('Y-m-d\TH:i', strtotime($row['end_time'])) : '', ENT_QUOTES) ?>"
                  data-item-image="<?= htmlspecialchars(auction_item_image_url($row), ENT_QUOTES) ?>">
                  Edit
                </button>
                <a class="btn" href="/auction_system/items/edit_item.php?id=<?= (int) $row['id'] ?>">Full editor</a>
              <?php endif; ?>
            </p>
          </div>
        </article>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="create-card auction-empty">
        <h3>No auctions match your filters yet</h3>
        <p class="form-note">Try clearing the filters or expanding the category search to discover more listings.</p>
        <div class="card-actions">
          <a class="btn" href="/auction_system/items/all_items.php">Show everything</a>
          <a class="btn secondary" href="/auction_system/items/create_item.php">Create a listing</a>
        </div>
      </div>
    <?php endif; ?>
  </section>
</main>

<?php include(__DIR__ . '/../includes/footer.php'); ?>

  <div class="quick-edit-modal" id="quick-edit-modal" aria-hidden="true">
    <div class="quick-edit-modal-backdrop" data-quick-edit-close></div>
    <div class="quick-edit-modal-content" role="dialog" aria-modal="true" aria-labelledby="quick-edit-title">
      <div class="quick-edit-modal-head">
        <div>
          <span class="page-chip">Quick edit</span>
          <h3 id="quick-edit-title">Edit listing</h3>
          <p class="form-note" id="quick-edit-subtitle">Update the selected item without leaving the browse page.</p>
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
                <canvas id="quick-edit-canvas" aria-label="Image preview"></canvas>
                <div class="quick-edit-canvas-controls">
                  <button type="button" id="quick-rotate-left" class="btn secondary">⟲</button>
                  <button type="button" id="quick-rotate-right" class="btn secondary">⟳</button>
                </div>
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
      const canvas = document.getElementById('quick-edit-canvas');
      const imagePreview = canvas; // legacy name used below
      const rotateLeft = document.getElementById('quick-rotate-left');
      const rotateRight = document.getElementById('quick-rotate-right');
      const itemIdField = document.getElementById('quick-edit-item-id');
      const subtitle = document.getElementById('quick-edit-subtitle');
      const removeImage = document.getElementById('quick-edit-remove-image');
      let canvasCtx = canvas && canvas.getContext ? canvas.getContext('2d') : null;
      let imgObj = null;
      let imgRotation = 0; // degrees
      let imageModified = false;

      function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
      }

      function fitCanvasSize(img) {
        // set canvas to a reasonable max width while preserving aspect
        const maxW = 420;
        const ratio = img.width / img.height;
        const w = Math.min(maxW, img.width);
        const h = Math.round(w / ratio);
        canvas.width = w;
        canvas.height = h;
      }

      function drawCanvasImage() {
        if (!canvasCtx || !imgObj) return;
        const w = canvas.width;
        const h = canvas.height;
        canvasCtx.save();
        canvasCtx.clearRect(0,0,w,h);
        // rotate around center
        canvasCtx.translate(w/2, h/2);
        canvasCtx.rotate(imgRotation * Math.PI / 180);
        // draw image centered
        // compute scale to fit
        const scale = Math.max(w / imgObj.width, h / imgObj.height);
        const dw = imgObj.width * scale;
        const dh = imgObj.height * scale;
        canvasCtx.drawImage(imgObj, -dw/2, -dh/2, dw, dh);
        canvasCtx.restore();
      }

      async function loadSrcToCanvas(src) {
        return new Promise((resolve, reject) => {
          const i = new Image();
          i.crossOrigin = 'anonymous';
          i.onload = () => {
            imgObj = i;
            imgRotation = 0;
            fitCanvasSize(i);
            drawCanvasImage();
            imageModified = false;
            resolve();
          };
          i.onerror = reject;
          i.src = src;
        });
      }

      document.querySelectorAll('[data-quick-edit-open]').forEach((button) => {
        button.addEventListener('click', () => {
          itemIdField.value = button.dataset.itemId || '';
          titleField.value = button.dataset.itemTitle || '';
          categoryField.value = button.dataset.itemCategory || 'Other';
          startPriceField.value = button.dataset.itemStartPrice || '';
          endTimeField.value = button.dataset.itemEndTime || '';
          descField.value = button.dataset.itemDescription || '';
          const imgSrc = button.dataset.itemImage || '/auction_system/assets/uploads/fallbacks/other.jpg';
          // try to draw into canvas
          loadSrcToCanvas(imgSrc).catch(() => {
            // fallback: clear canvas
            if (canvasCtx) { canvasCtx.clearRect(0,0,canvas.width,canvas.height); }
          });
          subtitle.textContent = `Editing ${button.dataset.itemTitle || 'listing'} — change title, price, category, description, or image.`;
          removeImage.checked = false;
          modal.classList.add('is-open');
          modal.setAttribute('aria-hidden', 'false');
          titleField.focus();
        });
      });
      // auto-open if ?open=ID is present
      try {
        const params = new URLSearchParams(window.location.search);
        const openId = params.get('open');
        if (openId) {
          const target = document.querySelector('[data-quick-edit-open][data-item-id="' + openId + '"]');
          if (target) target.click();
        }
      } catch (e) { /* ignore */ }

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

      // AJAX submit for quick-edit form
      const quickEditForm = document.getElementById('quick-edit-form');
      if (quickEditForm) {
        // client-side image preview for file input (draw to canvas)
        const fileInput = quickEditForm.querySelector('input[type="file"][name="image"]');
        if (fileInput && canvas) {
          fileInput.addEventListener('change', () => {
            const f = fileInput.files && fileInput.files[0];
            if (!f) return;
            const reader = new FileReader();
            reader.onload = () => {
              loadSrcToCanvas(String(reader.result)).then(() => { imageModified = true; }).catch(() => {});
            };
            reader.readAsDataURL(f);
          });
        }

        if (rotateLeft && rotateRight) {
          rotateLeft.addEventListener('click', () => {
            imgRotation = (imgRotation - 90) % 360;
            drawCanvasImage();
            imageModified = true;
          });
          rotateRight.addEventListener('click', () => {
            imgRotation = (imgRotation + 90) % 360;
            drawCanvasImage();
            imageModified = true;
          });
        }

        quickEditForm.addEventListener('submit', async (e) => {
          e.preventDefault();
          const form = new FormData();
          // append fields manually so we can conditionally include canvas blob
          new FormData(quickEditForm).forEach((v,k) => {
            if (k === 'image') return; // we'll manage image separately
            form.append(k, v);
          });
          // if canvas was modified, convert to blob and append as 'image'
          if (imageModified && canvas.toBlob) {
            await new Promise((res) => canvas.toBlob((blob) => { if (blob) form.append('image', blob, 'edited.png'); res(); }, 'image/png'));
          } else {
            // otherwise include any selected file from the input
            const fileInputLocal = quickEditForm.querySelector('input[type="file"][name="image"]');
            if (fileInputLocal && fileInputLocal.files && fileInputLocal.files[0]) {
              form.append('image', fileInputLocal.files[0]);
            }
          }
          try {
            const resp = await fetch('/auction_system/items/edit_item.php', {
              method: 'POST',
              credentials: 'same-origin',
              headers: { 'X-Requested-With': 'XMLHttpRequest' },
              body: form,
            });

            const contentType = resp.headers.get('Content-Type') || '';
            let data;
            if (contentType.indexOf('application/json') !== -1) {
              data = await resp.json();
            } else {
              const txt = await resp.text();
              throw new Error('Unexpected server response: ' + txt);
            }
            if (!data.success) {
              // show errors in modal
              let errHtml = document.getElementById('quick-edit-errors');
              if (!errHtml) {
                errHtml = document.createElement('div');
                errHtml.id = 'quick-edit-errors';
                errHtml.className = 'notice notice-error';
                quickEditForm.prepend(errHtml);
              }
              errHtml.innerHTML = (data.errors || ['An unknown error occurred']).map(e => '<p>' + escapeHtml(e) + '</p>').join('');
              return;
            }

            // Update the card in the DOM
            const item = data.item;
            const card = document.querySelector('[data-item-id="' + item.id + '"]');
            if (card) {
              // title
              const h3 = card.querySelector('h3');
              if (h3) h3.textContent = item.title;
              // price
              const priceEl = card.querySelector('.auction-price strong');
              if (priceEl) priceEl.textContent = '$' + Number(item.current_price || item.starting_price).toFixed(2);
              // category
              const catEl = card.querySelector('.muted');
              if (catEl) catEl.textContent = item.category || '';
              // image
              const img = card.querySelector('img');
              if (img && item.image_url) img.src = item.image_url;
              // update data attributes so future edits use latest values
              card.dataset.itemTitle = item.title;
              card.dataset.itemCategory = item.category;
              card.dataset.itemDescription = item.description || '';
              card.dataset.itemStartPrice = item.starting_price;
              card.dataset.itemEndTime = item.end_time || '';
              card.dataset.itemImage = item.image_url || '';
            }
            closeModal();
          } catch (err) {
            console.error('Quick edit error', err);
            let errHtml = document.getElementById('quick-edit-errors');
            if (!errHtml) {
              errHtml = document.createElement('div');
              errHtml.id = 'quick-edit-errors';
              errHtml.className = 'notice notice-error';
              quickEditForm.prepend(errHtml);
            }
            errHtml.innerHTML = '<p>' + escapeHtml(err.message || 'An unexpected error occurred') + '</p>';
          }
        });
      }
    })();
  </script>
