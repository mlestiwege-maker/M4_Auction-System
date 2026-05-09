<?php
header('Content-Type: application/json');
include(__DIR__ . '/../config/db.php');

$item_id = intval($_GET['item_id'] ?? 0);
if (!$item_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid item']);
    exit;
}

$stmt = $conn->prepare(
    'SELECT b.bid_amount, b.bid_time, u.name
     FROM bids b
     LEFT JOIN users u ON b.user_id = u.id
     WHERE b.item_id = ?
     ORDER BY b.bid_amount DESC, b.bid_time DESC
     LIMIT 20'
);
$stmt->bind_param('i', $item_id);
$stmt->execute();
$res = $stmt->get_result();

$bids = [];
while ($row = $res->fetch_assoc()) {
    $bids[] = [
        'amount' => (float) $row['bid_amount'],
        'created_at' => $row['bid_time'],
        'name' => $row['name'] ?? 'Unknown'
    ];
}

echo json_encode(['success' => true, 'bids' => $bids]);
