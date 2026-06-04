<?php
session_start();
include(__DIR__ . '/../config/db.php');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $stmt = $conn->prepare('SELECT id, name, password FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            header('Location: /auction_system/index.php');
            exit;
        } else {
            $errors[] = 'Invalid email or password';
        }
    } else {
        $errors[] = 'Please fill in all fields';
    }
}
?>
<?php include(__DIR__ . '/../includes/header.php'); ?>

<main class="auth-page">
    <div class="auth-layout">
        <section class="auth-hero" aria-label="Login highlights">
            <div class="auth-kicker">AH AuctionHub</div>
            <h2>Welcome back.</h2>
            <p>Sign in to keep bidding, track your watchlist, and stay ahead of the action without missing a beat.</p>

            <div class="auth-points">
                <div class="auth-point"><span>⚡</span><div><strong>Live auctions</strong><span>Jump back into active bidding instantly.</span></div></div>
                <div class="auth-point"><span>🔔</span><div><strong>Instant alerts</strong><span>See outbid, win, and auction-end notifications fast.</span></div></div>
                <div class="auth-point"><span>❤️</span><div><strong>Saved favorites</strong><span>Pick up where you left off in your watchlist.</span></div></div>
            </div>
        </section>

        <section class="auth-card">
            <h2>Welcome Back!</h2>
            <p>Sign in to continue bidding</p>

        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul style="margin: 0; padding-left: 1.25rem;">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" class="contact-form">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="you@example.com" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>
            <button type="submit" class="btn">Sign In</button>
        </form>

            <p class="auth-footer">
                Don't have an account? <a href="/auction_system/auth/register.php">Create one</a>
            </p>
        </section>
    </div>
</main>

<?php include(__DIR__ . '/../includes/footer.php'); ?>