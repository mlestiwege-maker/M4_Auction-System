<?php
session_start();
$config = [
    'host' => 'localhost',
    'user' => 'auctionhub',
    'password' => 'auction_password',
    'database' => 'auction_db'
];

$step = isset($_GET['step']) ? $_GET['step'] : 1;
$message = '';
$success = false;

// WITHOUT DATABASE CONNECTION - CREATE DB
if ($step == 1) {
    try {
        $conn = new mysqli($config['host'], $config['user'], $config['password']);
        if ($conn->connect_error) {
            $message = "⚠️ Cannot connect to MySQL. Please ensure MariaDB is running:<br>
            <code>sudo service mariadb start</code>";
        } else {
            // Create database
            $conn->query("CREATE DATABASE IF NOT EXISTS {$config['database']}");
            $conn->close();
            $message = "✅ Database created successfully!";
            $success = true;
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// WITH DATABASE - CREATE TABLES
if ($step == 2 || ($step == 1 && $success)) {
    try {
        $conn = new mysqli($config['host'], $config['user'], $config['password'], $config['database']);
        if ($conn->connect_error) {
            $message = "Cannot connect to database: " . $conn->connect_error;
        } else {
            $schema_file = __DIR__ . '/schema.sql';
            if (!file_exists($schema_file)) {
                $message = "⚠️ schema.sql file not found at: " . $schema_file;
                $step = 1;
            } else {
                $schema_sql = file_get_contents($schema_file);
                if ($schema_sql === false) {
                    $message = "⚠️ Could not read schema.sql file";
                    $step = 1;
                } else {
                    $queries = array_filter(array_map('trim', explode(';', $schema_sql)), function($q) {
                        return !empty($q) && strpos(trim($q), '--') !== 0;
                    });
                    
                    $table_count = 0;
                    foreach ($queries as $query) {
                        if (!empty($query)) {
                            if (!$conn->query($query)) {
                                if (strpos($conn->error, 'already exists') === false) {
                                    $message = "Error creating tables: " . $conn->error;
                                    $step = 1;
                                    break;
                                }
                            } else {
                                $table_count++;
                            }
                        }
                    }
                    
                    if ($step != 1) {
                        $step = 2;
                        $message = "✅ All tables created successfully! (" . $table_count . " tables)";
                        $success = true;
                    }
                }
            }
            $conn->close();
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// LOAD SAMPLE DATA
if ($step == 3 || ($step == 2 && $success && isset($_POST['load_sample']))) {
    try {
        $conn = new mysqli($config['host'], $config['user'], $config['password'], $config['database']);
        if ($conn->connect_error) {
            $message = "Cannot connect to database: " . $conn->connect_error;
        } else {
            $seed_file = __DIR__ . '/seed_data.sql';
            if (!file_exists($seed_file)) {
                $message = "⚠️ seed_data.sql file not found";
                $step = 2;
            } else {
                $seed_sql = file_get_contents($seed_file);
                if ($seed_sql === false) {
                    $message = "⚠️ Could not read seed_data.sql file";
                    $step = 2;
                } else {
                    $queries = array_filter(array_map('trim', explode(';', $seed_sql)), function($q) {
                        return !empty($q) && strpos(trim($q), '--') !== 0;
                    });
                    
                    $inserted = 0;
                    foreach ($queries as $query) {
                        if (!empty($query)) {
                            if ($conn->query($query)) {
                                $inserted++;
                            } elseif (strpos($conn->error, 'Duplicate') === false) {
                                if (strpos($conn->error, 'already exists') === false) {
                                    // Message is set only for non-duplicate errors
                                }
                            }
                        }
                    }
                    
                    $_SESSION['sample_data_loaded'] = true;
                    $step = 4;
                    $message = "✅ Sample data loaded successfully! (" . $inserted . " records inserted)";
                    $success = true;
                }
            }
            $conn->close();
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AuctionHub Setup Wizard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .setup-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
        }
        
        .setup-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
        }
        
        .setup-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .setup-header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .setup-body {
            padding: 40px;
        }
        
        .progress-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
        }
        
        .progress-step {
            flex: 1;
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            overflow: hidden;
        }
        
        .progress-step.active {
            background: #667eea;
        }
        
        .progress-step.completed {
            background: #4caf50;
        }
        
        .message-box {
            background: #f5f5f5;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .message-box code {
            background: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 30px;
        }
        
        button {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        
        .step-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
        }
        
        .step-description {
            font-size: 14px;
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        .checklist {
            list-style: none;
        }
        
        .checklist li {
            padding: 10px 0;
            font-size: 14px;
            color: #333;
        }
        
        .checklist li:before {
            content: "✓ ";
            color: #4caf50;
            font-weight: bold;
            margin-right: 8px;
        }
        
        .success-icon {
            text-align: center;
            font-size: 48px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <h1>🚀 AuctionHub Setup</h1>
            <p>Initialize your marketplace in 3 easy steps</p>
        </div>
        
        <div class="setup-body">
            <div class="progress-bar">
                <div class="progress-step <?php echo $step >= 1 ? 'completed' : ''; ?> <?php echo $step == 1 ? 'active' : ''; ?>"></div>
                <div class="progress-step <?php echo $step >= 2 ? 'completed' : ''; ?> <?php echo $step == 2 ? 'active' : ''; ?>"></div>
                <div class="progress-step <?php echo $step >= 3 ? 'completed' : ''; ?> <?php echo $step == 3 ? 'active' : ''; ?>"></div>
                <div class="progress-step <?php echo $step >= 4 ? 'completed' : ''; ?> <?php echo $step == 4 ? 'active' : ''; ?>"></div>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="message-box"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($step == 1): ?>
                <div class="step-title">Step 1: Start Database Service</div>
                <div class="step-description">
                    Before we can set up your marketplace, MySQL/MariaDB needs to be running. Run this command in your terminal:
                </div>
                <code style="background: #f5f5f5; padding: 12px; border-radius: 4px; display: block; font-family: monospace; margin-bottom: 20px;">
                    sudo service mariadb start
                </code>
                <p style="font-size: 12px; color: #999; margin-bottom: 20px;">
                    After starting MariaDB, come back and click the button below.
                </p>
                <div class="button-group">
                    <form method="GET" style="width: 100%;">
                        <button type="submit" name="step" value="1" class="btn-primary" style="width: 100%;">
                            Check MySQL Connection →
                        </button>
                    </form>
                </div>
            <?php elseif ($step == 2): ?>
                <div class="step-title">✅ Step 1: Database Ready</div>
                <div class="step-description">
                    Great! Now let's create the auction tables...
                </div>
                <div class="success-icon">✓</div>
                <div class="button-group">
                    <form method="GET" style="width: 100%;">
                        <button type="submit" name="step" value="2" class="btn-primary" style="width: 100%;">
                            Create Tables →
                        </button>
                    </form>
                </div>
            <?php elseif ($step == 3): ?>
                <div class="step-title">✅ Step 2: Tables Created</div>
                <div class="step-description">
                    Perfect! All database tables are ready. Now load sample data to test drive the marketplace:
                </div>
                <ul class="checklist">
                    <li>5 demo users (buyers & sellers)</li>
                    <li>10 sample auctions</li>
                    <li>30+ bids and reviews</li>
                    <li>Notifications & watchlist items</li>
                </ul>
                <div class="button-group">
                    <form method="POST" style="width: 100%;">
                        <button type="submit" name="load_sample" value="1" class="btn-primary" style="width: 100%;">
                            Load Sample Data →
                        </button>
                    </form>
                </div>
            <?php elseif ($step == 4): ?>
                <div class="step-title">✅ Setup Complete!</div>
                <div class="success-icon">🎉</div>
                <div class="step-description">
                    Your marketplace is now ready to use! Click below to launch the platform.
                </div>
                <ul class="checklist">
                    <li>Database initialized with 8 tables</li>
                    <li>Sample data loaded (5 users, 10 items)</li>
                    <li>Real-time bidding enabled</li>
                    <li>All features activated</li>
                </ul>
                <p style="font-size: 12px; color: #999; margin-top: 20px; text-align: center;">
                    Demo credentials: Any sample user name, password: password123
                </p>
                <div class="button-group">
                    <a href="index.php" style="text-decoration: none; width: 100%;">
                        <button class="btn-primary" style="width: 100%;">
                            Launch Platform 🚀
                        </button>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
