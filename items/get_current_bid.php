<?php
header('Content-Type: application/json');
include(__DIR__ . '/../config/db.php');

$item_id = intval($_GET['item_id'] ?? 0);
if (!$item_id) {
    echo json_encode(['error' => 'no_item']);
    exit;
}

$res = $conn->query("SELECT b.bid_amount, u.name FROM bids b LEFT JOIN users u ON b.user_id=u.id WHERE b.item_id={$item_id} ORDER BY b.bid_amount DESC LIMIT 1");
$highest = $res ? $res->fetch_assoc() : null;
if ($highest) {
    echo json_encode(['price' => (float)$highest['bid_amount'], 'bidder' => $highest['name']]);
    exit;
}

$stmt = $conn->prepare('SELECT starting_price FROM items WHERE id=?');
$stmt->bind_param('i', $item_id);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
echo json_encode(['price' => (float)$r['starting_price'], 'bidder' => null]);
