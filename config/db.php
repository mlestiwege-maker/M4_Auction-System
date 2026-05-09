<?php
$conn = new mysqli("localhost", "auctionhub", "auction_password", "auction_db");

// If connection fails, redirect to setup
if ($conn->connect_error) {
    // Don't die, instead redirect to setup page
    // But only if we're not already on setup.php
    if (basename($_SERVER['PHP_SELF']) !== 'setup.php' && 
        basename($_SERVER['PHP_SELF']) !== 'login.php' && 
        basename($_SERVER['PHP_SELF']) !== 'register.php' &&
        strpos($_SERVER['REQUEST_URI'], 'setup.php') === false) {
        
        // Check if database exists but connection fails
        $check_conn = @new mysqli("localhost", "auctionhub", "auction_password");
        if (!$check_conn->connect_error) {
            // Create database if it doesn't exist
            $check_conn->query("CREATE DATABASE IF NOT EXISTS auction_db");
            $check_conn->close();
            // Redirect to setup
            header("Location: /auction_system/setup.php");
            exit;
        } else {
            // MySQL not running, redirect to setup
            header("Location: /auction_system/setup.php");
            exit;
        }
    }
    
    $conn = null; // So we can check isset($conn) later
} else {
    $conn->set_charset("utf8mb4");
}
?>
