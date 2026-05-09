<?php
include(__DIR__ . '/config/db.php');

if ($conn && !$conn->connect_error) {
    echo "✅ Database connection successful!<br>";
    echo "Database: " . $conn->database . "<br>";
    
    $result = $conn->query("SELECT COUNT(*) as total FROM users");
    $row = $result->fetch_assoc();
    echo "Total users in database: " . $row['total'] . "<br>";
    
    $items = $conn->query("SELECT COUNT(*) as total FROM items");
    $items_row = $items->fetch_assoc();
    echo "Total items in database: " . $items_row['total'] . "<br>";
    
    $conn->close();
} else {
    echo "❌ Database connection failed!<br>";
    if ($conn) {
        echo "Error: " . $conn->connect_error . "<br>";
    } else {
        echo "Connection object is null<br>";
    }
}
?>
