<?php
include(__DIR__ . '/../config/db.php');
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: /auction_system/');
  exit;
}

$item_id = intval($_POST['item_id'] ?? 0);
$amount = floatval($_POST['amount'] ?? 0);
$method = trim($_POST['payment_method'] ?? 'simulated');
$reference = trim($_POST['reference'] ?? '');

if (empty($_SESSION['user_id']) || !$item_id || $amount <= 0) {
  echo '<main><p>Invalid payment request.</p></main>';
  exit;
}

// Simulate processing delay and status
usleep(200000); // 0.2s

$txRef = strtoupper(uniqid('TX')) . rand(100,999);
$status = 'completed';

// Insert payment record
$stmt = $conn->prepare('INSERT INTO payments (item_id, buyer_id, amount, payment_method, transaction_reference, payment_status) VALUES (?, ?, ?, ?, ?, ?)');
$buyer = (int) $_SESSION['user_id'];
$stmt->bind_param('iissss', $item_id, $buyer, $amount, $method, $txRef, $status);
if (!$stmt->execute()) {
  echo '<main><p>Failed to record payment: ' . htmlspecialchars($conn->error) . '</p></main>';
  exit;
}
$payment_id = $stmt->insert_id;

// Optionally create an order record
$itemRes = $conn->query("SELECT user_id FROM items WHERE id={$item_id} LIMIT 1");
$seller_id = $itemRes ? (int) ($itemRes->fetch_assoc()['user_id'] ?? 0) : 0;
if ($seller_id) {
  $oStmt = $conn->prepare('INSERT INTO orders (item_id, buyer_id, seller_id, delivery_status) VALUES (?, ?, ?, ?)');
  $delivery = 'pending';
  $oStmt->bind_param('iiis', $item_id, $buyer, $seller_id, $delivery);
  $oStmt->execute();
}

// Fetch item and seller details
$paymentStmt = $conn->prepare("SELECT p.*, i.title, i.current_price, u.name as seller_name FROM payments p LEFT JOIN items i ON p.item_id=i.id LEFT JOIN users u ON i.user_id=u.id WHERE p.id=?");
$paymentStmt->bind_param('i', $payment_id);
$paymentStmt->execute();
$paymentRes = $paymentStmt->get_result();
$payment = $paymentRes->fetch_assoc();

$_SESSION['payment_id'] = $payment_id;
$_SESSION['last_payment'] = $payment;

// Display receipt inline
include(__DIR__ . '/../includes/header.php');
?>
<main>
    <section class="hero item-hero">
        <div>
            <span class="page-chip">Payment Receipt</span>
            <h2>Transaction Confirmation</h2>
            <p>Your payment has been processed successfully. Thank you for your purchase!</p>
            <div class="auction-status">
                <span class="status-pill active"><?= ucfirst($payment['payment_status']) ?></span>
            </div>
        </div>
    </section>

    <div class="create-card">
        <div class="section-head compact-head">
            <div>
                <span class="page-chip">Receipt Details</span>
                <h3>Transaction Information</h3>
            </div>
        </div>
        
        <div class="receipt-card">
            <div class="two-col-grid">
                <div>
                    <p class="muted">Transaction ID</p>
                    <p class="font-strong"><?= htmlspecialchars($payment['transaction_reference']) ?></p>
                </div>
                <div>
                    <p class="muted">Status</p>
                    <p class="font-strong"><?= ucfirst($payment['payment_status']) ?></p>
                </div>
            </div>

            <div class="two-col-grid">
                <div>
                    <p class="muted">Item Purchased</p>
                    <p><?= htmlspecialchars($payment['title'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="muted">Payment Method</p>
                    <p><?= ucfirst(str_replace('_', ' ', $payment['payment_method'])) ?></p>
                </div>
            </div>

            <div class="two-col-grid">
                <div>
                    <p class="muted">Amount Paid</p>
                    <p class="font-strong amount-highlight">$<?= number_format($payment['amount'], 2) ?></p>
                </div>
                <div>
                    <p class="muted">Date & Time</p>
                    <p><?= date('F j, Y, g:i a', strtotime($payment['created_at'])) ?></p>
                </div>
            </div>

            <?php if (!empty($payment['seller_name'])): ?>
            <div class="top-border">
                <p class="muted">Seller</p>
                <p><?= htmlspecialchars($payment['seller_name']) ?></p>
            </div>
            <?php endif; ?>
        </div>

        <div class="card-actions">
            <a href="/auction_system/items/all_items.php" class="btn btn-primary">Browse More Items</a>
            <a href="/auction_system/index.php" class="btn btn-secondary">Return Home</a>
        </div>
    </div>
</main>

<?php include(__DIR__ . '/../includes/footer.php'); ?>
