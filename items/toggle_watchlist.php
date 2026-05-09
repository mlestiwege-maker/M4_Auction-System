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

$user_id = intval($_SESSION['user_id']);
$item_id = intval($_POST['item_id'] ?? 0);
$action = $_POST['action'] ?? 'toggle';

if (!$item_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid item']);
    exit;
}

$stmt = $conn->prepare('SELECT id FROM watchlist WHERE user_id=? AND item_id=? LIMIT 1');
$stmt->bind_param('ii', $user_id, $item_id);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();

if ($existing) {
    if ($action === 'add') {
        echo json_encode(['success' => true, 'watched' => true, 'message' => 'Already in watchlist']);
        exit;
    }
    $del = $conn->prepare('DELETE FROM watchlist WHERE id=? AND user_id=?');
    $del->bind_param('ii', $existing['id'], $user_id);
    $del->execute();
    echo json_encode(['success' => true, 'watched' => false, 'message' => 'Removed from watchlist']);
    exit;
}

if ($action === 'remove') {
    echo json_encode(['success' => true, 'watched' => false, 'message' => 'Not in watchlist']);
    exit;
}

$ins = $conn->prepare('INSERT INTO watchlist (user_id, item_id) VALUES (?, ?)');
$ins->bind_param('ii', $user_id, $item_id);
if ($ins->execute()) {
    echo json_encode(['success' => true, 'watched' => true, 'message' => 'Added to watchlist']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
