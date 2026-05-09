<?php
header('Content-Type: application/json');
include(__DIR__ . '/../config/db.php');

$seller_id = (int) ($_GET['seller_id'] ?? 0);
if (!$seller_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid seller']);
    exit;
}

$summaryStmt = $conn->prepare('SELECT COUNT(*) AS total, COALESCE(ROUND(AVG(rating), 2), 0) AS average FROM reviews WHERE seller_id=?');
$summaryStmt->bind_param('i', $seller_id);
$summaryStmt->execute();
$summary = $summaryStmt->get_result()->fetch_assoc() ?: ['total' => 0, 'average' => 0];

$stmt = $conn->prepare(
    'SELECT r.rating, r.comment, r.created_at, u.name AS reviewer_name
     FROM reviews r
     LEFT JOIN users u ON u.id = r.reviewer_id
     WHERE r.seller_id=?
     ORDER BY r.created_at DESC, r.id DESC
     LIMIT 10'
);
$stmt->bind_param('i', $seller_id);
$stmt->execute();
$res = $stmt->get_result();

$reviews = [];
while ($row = $res->fetch_assoc()) {
    $reviews[] = $row;
}

echo json_encode([
    'success' => true,
    'total' => (int) $summary['total'],
    'average' => (float) $summary['average'],
    'reviews' => $reviews
]);
