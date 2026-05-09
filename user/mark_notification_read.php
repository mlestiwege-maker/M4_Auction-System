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

$user_id = (int) $_SESSION['user_id'];
$notification_id = (int) ($_POST['notification_id'] ?? 0);

if (!$notification_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid notification']);
    exit;
}

$stmt = $conn->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?');
$stmt->bind_param('ii', $notification_id, $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
