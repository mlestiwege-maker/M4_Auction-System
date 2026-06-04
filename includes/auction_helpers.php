<?php

if (!function_exists('auction_category_fallback_image')) {
    function auction_category_fallback_image(string $category, string $title = ''): string
    {
        $category = strtolower(trim($category));
        $title = strtolower(trim($title));

        $category = match ($category) {
            'home and garden' => 'home & garden',
            'home_garden' => 'home & garden',
            default => $category,
        };

        $specificMatches = [
            'electronics' => [
                'ipad' => '/auction_system/assets/uploads/originals/electronics/ipad-air-2024.jpeg',
                'headphones' => '/auction_system/assets/uploads/originals/electronics/headphones.jpeg',
                'playstation' => '/auction_system/assets/uploads/originals/electronics/ps5-console.jpeg',
                'ps5' => '/auction_system/assets/uploads/originals/electronics/ps5-console.jpeg',
            ],
            'fashion' => [
                'jacket' => '/auction_system/assets/uploads/originals/fashion/leather-jacket.jpeg',
                'coat' => '/auction_system/assets/uploads/originals/fashion/leather-jacket.jpeg',
                'puffer' => '/auction_system/assets/uploads/originals/fashion/leather-jacket.jpeg',
                'handbag' => '/auction_system/assets/uploads/originals/fashion/designer-handbag.jpeg',
                'bag' => '/auction_system/assets/uploads/originals/fashion/designer-handbag.jpeg',
                'purse' => '/auction_system/assets/uploads/originals/fashion/designer-handbag.jpeg',
            ],
            'home & garden' => [
                'lamp' => '/auction_system/assets/uploads/originals/home-garden/desk-lamp.jpeg',
                'desk lamp' => '/auction_system/assets/uploads/originals/home-garden/desk-lamp.jpeg',
                'plant' => '/auction_system/assets/uploads/originals/home-garden/indoor-plants.jpeg',
                'plants' => '/auction_system/assets/uploads/originals/home-garden/indoor-plants.jpeg',
            ],
            'collectibles' => [
                'booster box' => '/auction_system/assets/uploads/originals/collectibles/pokemon-booster-box.jpeg',
                'pokemon' => '/auction_system/assets/uploads/originals/collectibles/pokemon-booster-box.jpeg',
                'comic' => '/auction_system/assets/uploads/originals/collectibles/comic-books-bundle.jpeg',
                'comics' => '/auction_system/assets/uploads/originals/collectibles/comic-books-bundle.jpeg',
            ],
            'vehicles' => [
                'helmet' => '/auction_system/assets/uploads/originals/vehicles/bicycle-helmet.jpeg',
                'bike' => '/auction_system/assets/uploads/originals/vehicles/bicycle-helmet.jpeg',
                'phone holder' => '/auction_system/assets/uploads/originals/vehicles/car-phone-holder.jpeg',
                'holder' => '/auction_system/assets/uploads/originals/vehicles/car-phone-holder.jpeg',
            ],
        ];

        $categoryDefaults = [
            'electronics' => '/auction_system/assets/uploads/originals/electronics/electronics-iphone-orange.jpeg',
            'fashion' => '/auction_system/assets/uploads/fallbacks/fashion.jpg',
            'home & garden' => '/auction_system/assets/uploads/fallbacks/home-garden.jpg',
            'collectibles' => '/auction_system/assets/uploads/fallbacks/collectibles.jpg',
            'vehicles' => '/auction_system/assets/uploads/fallbacks/vehicles.jpg',
            'other' => '/auction_system/assets/uploads/fallbacks/other.jpg',
        ];

        if ($category !== '' && isset($specificMatches[$category])) {
            foreach ($specificMatches[$category] as $needle => $path) {
                if (str_contains($title, $needle)) {
                    return $path;
                }
            }

            return $categoryDefaults[$category] ?? $categoryDefaults['other'];
        }

        foreach ($specificMatches as $matchers) {
            foreach ($matchers as $needle => $path) {
                if (str_contains($title, $needle)) {
                    return $path;
                }
            }
        }

        return $categoryDefaults[$category] ?? $categoryDefaults['other'];
    }
}

if (!function_exists('auction_item_image_url')) {
    function auction_item_image_url(array $row): string
    {
        $raw = trim((string) ($row['image_url'] ?? ''));

        if ($raw !== '') {
            if (preg_match('/^https?:\/\//i', $raw)) {
                return $raw;
            }

            $publicPath = $raw;
            if (str_starts_with($raw, '/auction_system/')) {
                $localRelativePath = substr($raw, strlen('/auction_system/'));
                $publicPath = $raw;
            } elseif (str_starts_with($raw, '/')) {
                $localRelativePath = ltrim($raw, '/');
                $publicPath = '/auction_system/' . $localRelativePath;
            } else {
                $localRelativePath = ltrim($raw, '/');
                $publicPath = '/auction_system/' . $localRelativePath;
            }

            $localPath = __DIR__ . '/../' . $localRelativePath;
            if (is_file($localPath)) {
                return $publicPath;
            }
        }

        return auction_category_fallback_image((string) ($row['category'] ?? 'other'), (string) ($row['title'] ?? ''));
    }
}

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
