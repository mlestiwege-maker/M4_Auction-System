<?php

if (!function_exists('auction_create_notification')) {
    function auction_create_notification(mysqli $conn, int $user_id, string $type, string $message, ?int $item_id = null): void
    {
        if ($user_id <= 0) {
            return;
        }

        $stmt = $conn->prepare('INSERT INTO notifications (user_id, notification_type, message, item_id) VALUES (?, ?, ?, ?)');
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('issi', $user_id, $type, $message, $item_id);
        $stmt->execute();
    }
}

if (!function_exists('auction_finalize_ended_items')) {
    function auction_finalize_ended_items(mysqli $conn): void
    {
        $items = $conn->query(
            "SELECT id, user_id, title
             FROM items
             WHERE (status IS NULL OR status = 'active')
               AND end_time IS NOT NULL
               AND end_time <= NOW()"
        );

        if (!$items) {
            return;
        }

        while ($item = $items->fetch_assoc()) {
            $item_id = (int) $item['id'];
            $start_price_stmt = $conn->prepare('SELECT starting_price FROM items WHERE id=? LIMIT 1');
            $start_price_stmt->bind_param('i', $item_id);
            $start_price_stmt->execute();
            $start_price = (float) ($start_price_stmt->get_result()->fetch_assoc()['starting_price'] ?? 0);

            $winnerStmt = $conn->prepare(
                 'SELECT b.user_id, b.bid_amount, u.name
                 FROM bids b
                 LEFT JOIN users u ON u.id = b.user_id
                 WHERE b.item_id = ?
                  ORDER BY b.bid_amount DESC, b.bid_time ASC
                 LIMIT 1'
            );
            $winnerStmt->bind_param('i', $item_id);
            $winnerStmt->execute();
            $winner = $winnerStmt->get_result()->fetch_assoc();

            $status = 'ended';
            $winner_id = $winner ? (int) $winner['user_id'] : null;
            $current_price = $winner ? (float) $winner['bid_amount'] : $start_price;

            $update = $conn->prepare('UPDATE items SET status=?, winner_id=?, current_price=? WHERE id=?');
            $update->bind_param('sidi', $status, $winner_id, $current_price, $item_id);
            $update->execute();

            auction_create_notification(
                $conn,
                (int) $item['user_id'],
                'auction_ended',
                'Your auction "' . $item['title'] . '" has ended.',
                $item_id
            );

            if ($winner_id) {
                auction_create_notification(
                    $conn,
                    $winner_id,
                    'won',
                    'You won "' . $item['title'] . '" for $' . number_format($current_price, 2) . '.',
                    $item_id
                );
            }
        }
    }
}
