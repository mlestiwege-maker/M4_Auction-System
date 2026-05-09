<?php
session_start();
header('Content-Type: application/json');
include(__DIR__ . '/../config/db.php');
include(__DIR__ . '/../includes/auction_helpers.php');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit;
}

auction_finalize_ended_items($conn);

$user_id = (int) $_SESSION['user_id'];
$limit = max(1, min(20, (int) ($_GET['limit'] ?? 10)));

$stmt = $conn->prepare(
    'SELECT id, notification_type AS type, message,
            CASE WHEN item_id IS NULL THEN "" ELSE CONCAT("/auction_system/items/view_item.php?id=", item_id) END AS link,
            is_read, created_at
     FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC, id DESC
     LIMIT ?'
);
$stmt->bind_param('ii', $user_id, $limit);
$stmt->execute();
$res = $stmt->get_result();

$notifications = [];
while ($row = $res->fetch_assoc()) {
    $notifications[] = $row;
}

$countStmt = $conn->prepare('SELECT COUNT(*) AS total FROM notifications WHERE user_id=? AND is_read=0');
$countStmt->bind_param('i', $user_id);
$countStmt->execute();
$unread = (int) ($countStmt->get_result()->fetch_assoc()['total'] ?? 0);

echo json_encode(['success' => true, 'unread' => $unread, 'notifications' => $notifications]);
