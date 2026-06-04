<?php
header('Content-Type: application/json; charset=utf-8');

function try_db_connection(): ?mysqli
{
    $hosts = ['127.0.0.1', 'localhost'];

    foreach ($hosts as $host) {
        $conn = @new mysqli($host, 'auctionhub', 'auction_password', 'auction_db');
        if (!$conn->connect_error) {
            $conn->set_charset('utf8mb4');
            return $conn;
        }
    }

    return null;
}

$response = [
    'status' => 'ok',
    'app' => 'auction-system',
    'time' => date(DATE_ATOM),
    'database' => [
        'status' => 'down',
    ],
];

$conn = try_db_connection();

if ($conn instanceof mysqli) {
    $response['database'] = [
        'status' => 'up',
        'server' => $conn->server_info,
    ];
    $conn->close();
    http_response_code(200);
} else {
    $response['status'] = 'degraded';
    http_response_code(503);
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);