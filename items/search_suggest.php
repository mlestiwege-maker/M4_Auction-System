<?php
include(__DIR__ . '/../config/db.php');
include(__DIR__ . '/../includes/auction_helpers.php');
header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');
if ($q === '') {
    echo json_encode(['success' => true, 'items' => []]);
    exit;
}

$like = '%' . $q . '%';
$params = [$like, $like];
$types = 'ss';
$sql = "SELECT i.id, i.title, i.category, COALESCE(i.current_price, i.starting_price) AS price, i.image_url
        FROM items i
        WHERE (i.title LIKE ? OR i.description LIKE ?) AND (i.status IS NULL OR i.status='active')";

if ($category !== '' && $category !== 'all') {
    $sql .= ' AND i.category = ?';
    $types .= 's';
    $params[] = $category;
}

$sql .= ' ORDER BY i.created_at DESC LIMIT 8';

$stmt = $conn->prepare($sql);
if ($stmt) {
    // bind params dynamically
    $bind_names = [];
    $bind_names[] = $types;
    for ($i = 0; $i < count($params); $i++) {
        $bind_name = 'bind' . $i;
        $$bind_name = $params[$i];
        $bind_names[] = &$$bind_name;
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
    $stmt->execute();
    $res = $stmt->get_result();
    $items = [];
    while ($row = $res->fetch_assoc()) {
        $items[] = [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'category' => $row['category'],
            'price' => number_format((float) $row['price'], 2),
            'image_url' => auction_item_image_url($row)
        ];
    }
    echo json_encode(['success' => true, 'items' => $items]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Search suggestions are temporarily unavailable']);
