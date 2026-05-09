<?php
include(__DIR__ . '/../config/db.php');
include(__DIR__ . '/../includes/auction_helpers.php');
auction_finalize_ended_items($conn);
include(__DIR__ . '/../includes/header.php');

$user_id = intval($_SESSION['user_id'] ?? 0);
$q = trim($_GET['q'] ?? '');
$min_price = floatval($_GET['min_price'] ?? 0);
$max_price = floatval($_GET['max_price'] ?? 0);
$status = $_GET['status'] ?? 'all';
$sort = $_GET['sort'] ?? 'newest';
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

if (!function_exists('auction_item_image_url')) {
  function auction_item_image_url(array $row): string
  {
    $raw = trim((string) ($row['image_url'] ?? ''));

    if ($raw !== '') {
      if (preg_match('/^https?:\/\//i', $raw)) {
        return $raw;
      }

      $localPath = __DIR__ . '/../' . ltrim($raw, '/');
      if (is_file($localPath)) {
        return '/auction_system/' . ltrim($raw, '/');
      }
    }

    $title = strtolower((string) ($row['title'] ?? ''));
    $category = strtolower((string) ($row['category'] ?? 'other'));

    $fallbacks = [
      'electronics' => '/auction_system/assets/uploads/fallbacks/electronics.jpg',
      'fashion' => '/auction_system/assets/uploads/fallbacks/fashion.jpg',
      'home & garden' => '/auction_system/assets/uploads/fallbacks/home-garden.jpg',
      'collectibles' => '/auction_system/assets/uploads/fallbacks/collectibles.jpg',
      'vehicles' => '/auction_system/assets/uploads/fallbacks/vehicles.jpg',
      'other' => '/auction_system/assets/uploads/fallbacks/other.jpg',
    ];

    if (str_contains($title, 'ipad')) {
      return '/auction_system/assets/uploads/fallbacks/electronics.jpg';
    }
    if (str_contains($title, 'playstation') || str_contains($title, 'ps5')) {
      return '/auction_system/assets/uploads/fallbacks/electronics.jpg';
    }
    if (str_contains($title, 'headphones')) {
      return '/auction_system/assets/uploads/fallbacks/electronics.jpg';
    }

    return $fallbacks[$category] ?? $fallbacks['other'];
  }
}

$sql = 'SELECT i.id, i.title, i.category, i.current_price, i.starting_price, i.end_time, i.image_url';
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
?>
<main>
  <section class="hero" style="margin-bottom: 22px;">
    <div>
      <h2>All Auctions</h2>
      <p>Browse active listings, compare prices, and jump into live bidding before the timer runs out.</p>
    </div>
  </section>

  <section class="create-card filter-card">
    <h3>Search & Filters</h3>
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
      <button type="submit" class="btn">Apply Filters</button>
      <a class="btn secondary" href="/auction_system/items/all_items.php">Reset</a>
    </form>
  </section>

  <section class="create-card" style="margin: 18px 0;">
    <h3>🔥 Trending Auctions</h3>
    <p class="muted" style="margin-top: 4px;">Most bid-on live listings right now.</p>
    <div class="trending-grid">
      <?php if ($trending): ?>
        <?php while ($t = $trending->fetch_assoc()): ?>
          <article class="trending-card">
            <h4><?= htmlspecialchars($t['title']) ?></h4>
            <div class="trending-meta">
              <span><?= htmlspecialchars($t['category'] ?: 'General') ?></span>
              <span><?= (int) $t['bid_count'] ?> bids</span>
            </div>
            <p><strong>$<?= number_format((float) ($t['current_price'] ?: $t['starting_price']), 2) ?></strong></p>
            <a class="btn" href="/auction_system/items/view_item.php?id=<?= (int) $t['id'] ?>">Join Auction</a>
          </article>
        <?php endwhile; ?>
      <?php endif; ?>
    </div>
  </section>

  <section class="auction-grid">
    <?php while ($row = $res->fetch_assoc()): ?>
      <?php
        $ended = !empty($row['end_time']) && (new DateTime() > new DateTime($row['end_time']));
        $secondsLeft = !$ended && !empty($row['end_time']) ? (strtotime($row['end_time']) - time()) : 0;
        $isEndingSoon = $secondsLeft > 0 && $secondsLeft <= 3600;
      ?>
      <article class="auction-card <?= $isEndingSoon ? 'ending-soon' : '' ?>">
        <img src="<?= htmlspecialchars(auction_item_image_url($row)) ?>" alt="<?= htmlspecialchars($row['title']) ?>" loading="lazy" referrerpolicy="no-referrer">
        <div class="auction-card-content">
          <h3><?= htmlspecialchars($row['title']) ?></h3>
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
          <p class="card-actions">
            <a class="btn" href="/auction_system/items/view_item.php?id=<?= $row['id'] ?>">View Item</a>
            <?php if (isset($_SESSION['user_id'])): ?>
              <button class="btn secondary watchlist-toggle" data-item-id="<?= $row['id'] ?>" data-action="<?= !empty($row['watched']) ? 'remove' : 'add' ?>">
                <?= !empty($row['watched']) ? 'Remove Watchlist' : 'Add Watchlist' ?>
              </button>
            <?php endif; ?>
          </p>
        </div>
      </article>
    <?php endwhile; ?>
  </section>
</main>

<?php include(__DIR__ . '/../includes/footer.php'); ?>
