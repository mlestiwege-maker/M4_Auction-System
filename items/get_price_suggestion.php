<?php
header('Content-Type: application/json');
include(__DIR__ . '/../config/db.php');

$category = trim($_GET['category'] ?? '');
$title = trim($_GET['title'] ?? '');

if ($category === '' || $title === '') {
    echo json_encode(['success' => false, 'message' => 'category and title required']);
    exit;
}

$like = '%' . $title . '%';

$stmt = $conn->prepare(
    "SELECT COALESCE(current_price, starting_price) AS price
     FROM items
     WHERE category = ? AND (title LIKE ? OR description LIKE ?)
     ORDER BY created_at DESC
     LIMIT 30"
);
$stmt->bind_param('sss', $category, $like, $like);
$stmt->execute();
$res = $stmt->get_result();

$prices = [];
while ($row = $res->fetch_assoc()) {
    $prices[] = (float) $row['price'];
}

if (count($prices) < 2) {
    $fallback = $conn->prepare(
        "SELECT COALESCE(current_price, starting_price) AS price
         FROM items
         WHERE category = ?
         ORDER BY created_at DESC
         LIMIT 30"
    );
    $fallback->bind_param('s', $category);
    $fallback->execute();
    $fallbackRes = $fallback->get_result();
    while ($row = $fallbackRes->fetch_assoc()) {
        $prices[] = (float) $row['price'];
    }
}

if (empty($prices)) {
    echo json_encode(['success' => false, 'message' => 'no similar data']);
    exit;
}

sort($prices);
$min = min($prices);
$max = max($prices);
$avg = array_sum($prices) / count($prices);
$median = $prices[(int) floor((count($prices) - 1) / 2)];
$recommended = max($min, min($median, $max));

echo json_encode([
    'success' => true,
    'count' => count($prices),
    'min' => round($min, 2),
    'max' => round($max, 2),
    'avg' => round($avg, 2),
    'recommended' => round($recommended, 2)
]);
