<?php
$db_user = "auctionhub";
$db_pass = "auction_password";
$db_name = "auction_db";

// Try TCP first to avoid Linux socket path issues, then fallback to localhost socket.
$conn = @new mysqli("127.0.0.1", $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    echo "TCP connection failed: " . $conn->connect_error . "<br>";
    $conn = @new mysqli("localhost", $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        echo "Socket connection failed: " . $conn->connect_error . "<br>";
    } else {
        echo "Socket connection succeeded!<br>";
    }
} else {
    echo "TCP connection succeeded!<br>";
}

// If connection succeeds, set charset and test a simple query
if (!$conn->connect_error) {
    $conn->set_charset("utf8mb4");
    $result = $conn->query("SELECT 1");
    if ($result) {
        echo "Query succeeded! Result: ";
        $row = $result->fetch_row();
        echo $row[0];
        $result->free();
    } else {
        echo "Query failed: " . $conn->error;
    }
    $conn->close();
} else {
    echo "Could not connect to database.";
}
?>
