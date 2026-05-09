<?php
session_start();
header('Content-Type: application/json');
include(__DIR__ . '/../config/db.php');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit;
}

if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$reviewer_id = (int) $_SESSION['user_id'];
$seller_id = (int) ($_POST['seller_id'] ?? 0);
$rating = (int) ($_POST['rating'] ?? 0);
$comment = trim($_POST['comment'] ?? '');

if (!$seller_id || $rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Invalid review data']);
    exit;
}

if ($seller_id === $reviewer_id) {
    echo json_encode(['success' => false, 'message' => 'You cannot review yourself']);
    exit;
}

$exists = $conn->prepare('SELECT id FROM reviews WHERE reviewer_id=? AND seller_id=? LIMIT 1');
$exists->bind_param('ii', $reviewer_id, $seller_id);
$exists->execute();
if ($exists->get_result()->fetch_assoc()) {
    echo json_encode(['success' => false, 'message' => 'You already reviewed this seller']);
    exit;
}

$stmt = $conn->prepare('INSERT INTO reviews (reviewer_id, seller_id, rating, comment) VALUES (?, ?, ?, ?)');
$stmt->bind_param('iiis', $reviewer_id, $seller_id, $rating, $comment);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Review submitted']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
