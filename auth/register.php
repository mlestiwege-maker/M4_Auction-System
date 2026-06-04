<?php
include(__DIR__ . '/../config/db.php');
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!$name) $errors[] = 'Name is required';
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';
    if ($password !== $confirm_password) $errors[] = 'Passwords do not match';

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $role = 'buyer';  // New users default to buyer role
        $stmt = $conn->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('ssss', $name, $email, $hash, $role);
        if ($stmt->execute()) {
            $success = true;
        } else {
            if ($conn->errno === 1062) {
                $errors[] = 'Email already registered';
            } else {
                $errors[] = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<?php include(__DIR__ . '/../includes/header.php'); ?>

<main class="auth-page">
    <div class="auth-layout">
        <section class="auth-hero" aria-label="Registration highlights">
            <div class="auth-kicker">AH AuctionHub</div>
            <h2>Create your account.</h2>
            <p>Join the marketplace and start buying, selling, and tracking auctions from one polished dashboard.</p>

            <div class="auth-points">
                <div class="auth-point"><span>🚀</span><div><strong>Quick setup</strong><span>Register in a few easy steps.</span></div></div>
                <div class="auth-point"><span>📋</span><div><strong>Seller ready</strong><span>Post listings and start selling fast.</span></div></div>
                <div class="auth-point"><span>🔒</span><div><strong>Secure by design</strong><span>Your account is protected from day one.</span></div></div>
            </div>
        </section>

        <section class="auth-card">
            <h2>Create Your Account</h2>
            <p>Join AuctionHub to start bidding or selling</p>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <strong>Success!</strong> Your account has been created. <a href="/auction_system/auth/login.php">Login here</a>
            </div>
        <?php else: ?>
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
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" placeholder="Enter your full name" required>
                </div>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="you@example.com" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="At least 6 characters" required minlength="6">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter your password" required minlength="6">
                </div>
                <button type="submit" class="btn">Create Account</button>
            </form>

            <p class="auth-footer">
                Already have an account? <a href="/auction_system/auth/login.php">Sign in</a>
            </p>
        <?php endif; ?>
        </section>
    </div>
</main>

<?php include(__DIR__ . '/../includes/footer.php'); ?>