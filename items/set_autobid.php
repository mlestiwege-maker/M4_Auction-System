<?php
session_start();
header('Content-Type: application/json');
include(__DIR__ . '/../config/db.php');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'message'=>'Login required']); exit;
}

$user_id = intval($_SESSION['user_id']);
$item_id = intval($_POST['item_id'] ?? 0);
$max_bid = floatval($_POST['max_bid'] ?? 0);

if (!$item_id || $max_bid <= 0) {
    echo json_encode(['success'=>false,'message'=>'Invalid input']); exit;
}

// CSRF
if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode(['success'=>false,'message'=>'Invalid CSRF token']); exit;
}

// insert or update auto_bids
$stmt = $conn->prepare('REPLACE INTO auto_bids (user_id, item_id, max_bid, updated_at) VALUES (?, ?, ?, NOW())');
$stmt->bind_param('iid', $user_id, $item_id, $max_bid);
if ($stmt->execute()) {
    echo json_encode(['success'=>true,'message'=>'Auto-bid set to ' . number_format($max_bid,2)]);
} else {
    echo json_encode(['success'=>false,'message'=>'Database error. Please try again.']);
}
