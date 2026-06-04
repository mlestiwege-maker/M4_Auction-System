
<?php
include(__DIR__ . '/../config/db.php');
include(__DIR__ . '/../includes/auction_helpers.php');
include(__DIR__ . '/../includes/header.php');

$isLoggedIn = isset($_SESSION['user_id']);
$currentUserId = $isLoggedIn ? (int) $_SESSION['user_id'] : 0;

$item_id = intval($_GET['item_id'] ?? 0);
if (!$item_id) {
    $summaryResult = $conn->query("SELECT COUNT(*) AS total_payments, COALESCE(SUM(amount), 0) AS total_revenue, COALESCE(AVG(amount), 0) AS avg_payment FROM payments");
    $summary = $summaryResult ? $summaryResult->fetch_assoc() : ['total_payments' => 0, 'total_revenue' => 0, 'avg_payment' => 0];

    $recentPayments = $conn->query("SELECT p.id, p.amount, p.payment_method, p.payment_status, p.created_at, i.title FROM payments p LEFT JOIN items i ON p.item_id = i.id ORDER BY p.created_at DESC LIMIT 5");
    ?>
    <main>
        <section class="hero item-hero">
            <div>
                <span class="page-chip">Payments</span>
                <h2>Payment Center</h2>
                <p>View your payment history and complete a purchase from any item that you have won.</p>
                <div class="auction-status">
                    <span class="status-pill active">Secure checkout</span>
                    <span class="status-pill">Simulated payment methods</span>
                    <span class="status-pill">Receipts & history</span>
                </div>
            </div>
        </section>

        <div class="create-card">
            <div class="section-head compact-head">
                <div>
                    <span class="page-chip">Quick actions</span>
                    <h3>What would you like to do?</h3>
                </div>
            </div>
            <div class="card-actions">
                <a class="btn" href="/auction_system/payment/history.php">View Payment History</a>
                <a class="btn secondary" href="/auction_system/items/all_items.php">Browse Auctions</a>
                <a class="btn secondary" href="/auction_system/user/dashboard.php">Go to Dashboard</a>
                <?php if (!$isLoggedIn): ?>
                    <a class="btn secondary" href="/auction_system/auth/login.php">Login to pay</a>
                <?php endif; ?>
            </div>
            <p class="form-note">To pay for an item, open the ended auction you won and click <strong>Proceed to Payment</strong>.</p>
        </div>

        <div class="create-card">
            <div class="section-head compact-head">
                <div>
                    <span class="page-chip">Payment overview</span>
                    <h3>Platform transaction summary</h3>
                </div>
            </div>

            <div class="dashboard-stats">
                <div class="dashboard-stat">
                    <strong><?= (int) ($summary['total_payments'] ?? 0) ?></strong>
                    <span>Total payments</span>
                </div>
                <div class="dashboard-stat">
                    <strong>$<?= number_format((float) ($summary['total_revenue'] ?? 0), 2) ?></strong>
                    <span>Total revenue</span>
                </div>
                <div class="dashboard-stat">
                    <strong>$<?= number_format((float) ($summary['avg_payment'] ?? 0), 2) ?></strong>
                    <span>Average payment</span>
                </div>
            </div>

            <div class="section-head compact-head">
                <div>
                    <span class="page-chip">Recent activity</span>
                    <h3>Latest transactions</h3>
                </div>
            </div>

            <?php if ($recentPayments && $recentPayments->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $recentPayments->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['title'] ?? 'Auction item') ?></td>
                                    <td class="font-strong">$<?= number_format((float) $row['amount'], 2) ?></td>
                                    <td><?= ucfirst(str_replace('_', ' ', $row['payment_method'])) ?></td>
                                    <td><span class="status-pill <?= $row['payment_status'] === 'completed' ? 'active' : '' ?>"><?= ucfirst($row['payment_status']) ?></span></td>
                                    <td><?= date('M j, Y g:i a', strtotime($row['created_at'])) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="form-note">No payments have been recorded yet. Once an auction is won and paid, the transaction will appear here.</p>
            <?php endif; ?>
        </div>
    </main>
    <?php
    include(__DIR__ . '/../includes/footer.php');
    exit;
}

// Get item details
$stmt = $conn->prepare("SELECT i.*, u.name as seller, w.name as winner_name FROM items i LEFT JOIN users u ON i.user_id=u.id LEFT JOIN users w ON i.winner_id=w.id WHERE i.id=?");
$stmt->bind_param('i', $item_id);
$stmt->execute();
$res = $stmt->get_result();
$item = $res->fetch_assoc();
if (!$item) {
    echo '<main><p>Item not found.</p></main>';
    include(__DIR__ . '/../includes/footer.php');
    exit;
}

// Check if auction ended
$is_active = true;
if (!empty($item['end_time'])) {
    $now = new DateTime('now');
    $end = new DateTime($item['end_time']);
    if ($now > $end) {
        $is_active = false;
    }
}

if (!$isLoggedIn) {
    ?>
    <main>
        <section class="hero item-hero">
            <div>
                <span class="page-chip">Payment Center</span>
                <h2>Login Required</h2>
                <p>You can browse the Payments page, but you need to sign in to complete a checkout.</p>
            </div>
        </section>

        <div class="create-card">
            <p class="form-note">Please log in, then open the auction you won to complete payment.</p>
            <div class="card-actions">
                <a class="btn" href="/auction_system/auth/login.php">Login</a>
                <a class="btn secondary" href="/auction_system/payment/history.php">Payment History</a>
            </div>
        </div>
    </main>
    <?php
    include(__DIR__ . '/../includes/footer.php');
    exit;
}

// Check if user is the winner
$is_winner = (isset($item['winner_id']) && (int)$item['winner_id'] === $currentUserId);

// Check if payment already exists
$paymentStmt = $conn->prepare("SELECT id FROM payments WHERE item_id=? AND buyer_id=?");
$paymentStmt->bind_param('ii', $item_id, $currentUserId);
$paymentStmt->execute();
$paymentRes = $paymentStmt->get_result();
$hasPaid = ($paymentRes->num_rows > 0);

if (!$is_active && $is_winner && !$hasPaid) {
    // Show payment form
?>
<main>
    <section class="hero item-hero">
        <div>
            <span class="page-chip">Payment Required</span>
            <h2>Complete Your Purchase</h2>
            <p>You won the auction for "<?= htmlspecialchars($item['title']) ?>". Please select a payment method to complete the transaction.</p>
            <div class="auction-status">
                <span class="status-pill active">Auction Won</span>
                <span class="status-pill">Amount: $<?= number_format($item['current_price'], 2) ?></span>
            </div>
        </div>
    </section>

    <div class="create-card">
        <div class="section-head compact-head">
            <div>
                <span class="page-chip">Payment Method</span>
                <h3>How would you like to pay?</h3>
            </div>
        </div>
        <form method="POST" action="process.php" class="item-form">
            <input type="hidden" name="item_id" value="<?= $item_id ?>">
            <div class="form-stack">
                <label>
                    Payment Method
                    <select name="payment_method" required>
                        <option value="">Select payment method</option>
                        <option value="visa">Visa/MasterCard</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="ecocash">EcoCash</option>
                        <option value="pay_on_delivery">Pay on Delivery (Cash)</option>
                    </select>
                </label>
                <div id="payment-details" class="form-note-block">
                    <!-- Payment method specific details will be loaded via JavaScript -->
                </div>
            </div>
            <div class="card-actions">
                <button type="submit" class="btn">Process Payment</button>
                <a class="btn secondary" href="/auction_system/items/view_item.php?item_id=<?= $item_id ?>">Back to Item</a>
            </div>
        </form>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const paymentMethodSelect = document.querySelector('select[name="payment_method"]');
    const paymentDetailsDiv = document.getElementById('payment-details');

    paymentMethodSelect.addEventListener('change', function() {
        const method = this.value;
        let details = '';

        switch(method) {
            case 'visa':
                details = `
                    <label>
                        Card Number
                        <input type="text" name="card_number" placeholder="1234 5678 9012 3456" pattern="[0-9]{4}[ ]?[0-9]{4}[ ]?[0-9]{4}[ ]?[0-9]{4}" required>
                    </label>
                    <label>
                        Expiry Date
                        <input type="month" name="expiry_date" required>
                    </label>
                    <label>
                        CVV
                        <input type="text" name="cvv" placeholder="123" pattern="[0-9]{3}" required>
                    </label>
                    <p class="form-note">This is a simulation. No actual card details are stored.</p>
                `;
                break;
            case 'bank_transfer':
                details = `
                    <label>
                        Bank Name
                        <input type="text" name="bank_name" placeholder="Your Bank Name" required>
                    </label>
                    <label>
                        Account Number
                        <input type="text" name="account_number" placeholder="Your Account Number" required>
                    </label>
                    <label>
                        SWIFT/BIC
                        <input type="text" name="swift" placeholder="SWIFT/BIC Code" required>
                    </label>
                    <p class="form-note">Please transfer the amount to the auction house account. Use your item ID as reference.</p>
                `;
                break;
            case 'ecocash':
                details = `
                    <label>
                        EcoCash Number
                        <input type="text" name="ecocash_number" placeholder="+263 7X XXX XXX" pattern="[+]?[0-9]{10,12}" required>
                    </label>
                    <label>
                        Transaction ID
                        <input type="text" name="transaction_id" placeholder="Enter EcoCash Transaction ID after payment" required>
                    </label>
                    <p class="form-note">Send payment to EcoCash merchant code: 123456. Use your item ID as reference.</p>
                `;
                break;
            case 'pay_on_delivery':
                details = `
                    <label>
                        Delivery Address
                        <textarea name="delivery_address" rows="3" placeholder="Enter your delivery address" required></textarea>
                    </label>
                    <p class="form-note">Pay in cash when the item is delivered to your address.</p>
                `;
                break;
            default:
                details = '';
        }

        paymentDetailsDiv.innerHTML = details;
    });
});
</script>

<?php
} elseif ($hasPaid) {
    // Show payment receipt
?>
<main>
    <section class="hero item-hero">
        <div>
            <span class="page-chip">Payment Complete</span>
            <h2>Thank You for Your Purchase!</h2>
            <p>Your payment for "<?= htmlspecialchars($item['title']) ?>" has been successfully processed.</p>
            <div class="auction-status">
                <span class="status-pill active">Payment Received</span>
                <span class="status-pill">Amount: $<?= number_format($item['current_price'], 2) ?></span>
            </div>
        </div>
    </section>

    <div class="create-card">
        <div class="section-head compact-head">
            <div>
                <span class="page-chip">Transaction Details</span>
                <h3>Payment Receipt</h3>
            </div>
        </div>
        <div class="form-note-block">
            <p><strong>Transaction ID:</strong> AUCTION-<?= str_pad($item_id, 8, '0', STR_PAD_LEFT) ?></p>
            <p><strong>Item:</strong> <?= htmlspecialchars($item['title']) ?></p>
            <p><strong>Amount Paid:</strong> $<?= number_format($item['current_price'], 2) ?></p>
            <p><strong>Payment Method:</strong> <?= ucfirst(str_replace('_', ' ', $item['payment_method'] ?? 'Unknown')) ?></p>
            <p><strong>Date:</strong> <?= date('F j, Y, g:i a') ?></p>
            <p><strong>Status:</strong> <span class="status-pill active">Completed</span></p>
        </div>
        <div class="card-actions text-center">
            <a class="btn" href="/auction_system/items/view_item.php?item_id=<?= $item_id ?>">View Item</a>
            <a class="btn secondary" href="/auction_system/payment/history.php">Payment History</a>
        </div>
    </div>
</main>

<?php
} else {
    // User not winner or auction not ended
    echo '<main><p>You are not eligible to make a payment for this item.</p>';
    echo '<a class="btn" href="/auction_system/items/all_items.php">Browse Auctions</a></main>';
}

include(__DIR__ . '/../includes/footer.php');
?>
