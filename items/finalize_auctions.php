<?php
header('Content-Type: application/json');
include(__DIR__ . '/../config/db.php');
include(__DIR__ . '/../includes/auction_helpers.php');

auction_finalize_ended_items($conn);

echo json_encode(['success' => true]);
