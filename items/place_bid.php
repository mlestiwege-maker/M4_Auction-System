<?php
session_start();
header('Content-Type: application/json');
include(__DIR__ . '/../config/db.php');
include(__DIR__ . '/../includes/auction_helpers.php');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to bid.']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$item_id = intval($_POST['item_id'] ?? 0);
$amount = floatval($_POST['amount'] ?? 0);

// CSRF check
if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
    exit;
}

// check auction still active
$stmtCheck = $conn->prepare('SELECT end_time FROM items WHERE id=?');
$stmtCheck->bind_param('i', $item_id);
$stmtCheck->execute();
$rowCheck = $stmtCheck->get_result()->fetch_assoc();
if ($rowCheck && $rowCheck['end_time']) {
    $now = new DateTime('now');
    $end = new DateTime($rowCheck['end_time']);
    if ($now > $end) {
        echo json_encode(['success' => false, 'message' => 'Auction has ended.']);
        exit;
    }
}

if (!$item_id || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit;
}

// get current highest
$itemMetaStmt = $conn->prepare('SELECT title, user_id, starting_price FROM items WHERE id=? LIMIT 1');
$itemMetaStmt->bind_param('i', $item_id);
$itemMetaStmt->execute();
$itemMeta = $itemMetaStmt->get_result()->fetch_assoc();
if (!$itemMeta) {
    echo json_encode(['success' => false, 'message' => 'Item not found.']);
    exit;
}

$prevBidStmt = $conn->prepare(
    'SELECT b.user_id, b.bid_amount, u.name
     FROM bids b
     LEFT JOIN users u ON u.id = b.user_id
     WHERE b.item_id=?
     ORDER BY b.bid_amount DESC, b.bid_time ASC
     LIMIT 1'
);
$prevBidStmt->bind_param('i', $item_id);
$prevBidStmt->execute();
$prevHighest = $prevBidStmt->get_result()->fetch_assoc();

$current = $prevHighest ? (float) $prevHighest['bid_amount'] : (float) $itemMeta['starting_price'];

if ($amount <= $current) {
    echo json_encode(['success' => false, 'message' => 'Your bid must be higher than the current price.']);
    exit;
}

// insert new bid
$stmt = $conn->prepare('INSERT INTO bids (user_id, item_id, bid_amount) VALUES (?, ?, ?)');
$stmt->bind_param('iid', $user_id, $item_id, $amount);
if ($stmt->execute()) {
    // update item's current_price
    $u = $conn->prepare('UPDATE items SET current_price=? WHERE id=?');
    $u->bind_param('di', $amount, $item_id);
    $u->execute();

    if ($prevHighest && (int) $prevHighest['user_id'] !== $user_id) {
        auction_create_notification(
            $conn,
            (int) $prevHighest['user_id'],
            'outbid',
            'You were outbid on "' . $itemMeta['title'] . '". Current bid: $' . number_format($amount, 2) . '.',
            $item_id
        );
    }

    if ((int) $itemMeta['user_id'] !== $user_id) {
        auction_create_notification(
            $conn,
            (int) $itemMeta['user_id'],
            'new_bid',
            'New bid on your item "' . $itemMeta['title'] . '": $' . number_format($amount, 2) . '.',
            $item_id
        );
    }

    // After successful manual bid, check for auto-bidders and attempt automatic counter-bids
    // Minimal increment
    $min_increment = 1.00;
    $currentHighestUserId = $user_id;

    $continue = true;
    $safety = 0;
    while ($continue && $safety < 20) {
        $safety++;
        // find highest auto_bid by other users whose max_bid >= current + increment
        $cur = $amount;
        $sql = "SELECT ab.user_id, ab.max_bid, u.name FROM auto_bids ab JOIN users u ON ab.user_id=u.id WHERE ab.item_id=? AND ab.user_id<>? AND ab.max_bid >= ? ORDER BY ab.max_bid DESC LIMIT 1";
        $q = $conn->prepare($sql);
        $need = $cur + $min_increment;
        $q->bind_param('iid', $item_id, $user_id, $need);
        $q->execute();
        $resA = $q->get_result()->fetch_assoc();
        if ($resA) {
            // determine autobid amount: min(max_bid, cur + min_increment)
            $auto_amount = min(floatval($resA['max_bid']), $cur + $min_increment);
            // insert auto bid
            $ins = $conn->prepare('INSERT INTO bids (user_id, item_id, bid_amount) VALUES (?, ?, ?)');
            $ins->bind_param('iid', $resA['user_id'], $item_id, $auto_amount);
            if ($ins->execute()) {
                if ($currentHighestUserId && (int) $currentHighestUserId !== (int) $resA['user_id']) {
                    auction_create_notification(
                        $conn,
                        (int) $currentHighestUserId,
                        'outbid',
                        'You were outbid on "' . $itemMeta['title'] . '". Current bid: $' . number_format($auto_amount, 2) . '.',
                        $item_id
                    );
                }
                $currentHighestUserId = (int) $resA['user_id'];
                $amount = $auto_amount;
                $u2 = $conn->prepare('UPDATE items SET current_price=? WHERE id=?');
                $u2->bind_param('di', $amount, $item_id);
                $u2->execute();
                // loop to see if someone else has higher auto max
                continue;
            } else {
                // stop if insert fails
                $continue = false;
            }
        } else {
            $continue = false;
        }
    }

    echo json_encode(['success' => true, 'message' => 'Bid placed successfully.', 'price' => $amount]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
