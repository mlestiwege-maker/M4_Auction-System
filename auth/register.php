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
        $stmt = $conn->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
        $stmt->bind_param('sss', $name, $email, $hash);
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

<main style="max-width: 500px; margin: 3rem auto; padding: 0 1.5rem;">
    <div class="panel" style="margin-top: 2rem;">
        <h2 style="text-align: center; margin-bottom: 1.5rem; color: var(--primary);">Create Your Account</h2>
        <p style="text-align: center; color: var(--text-light); margin-bottom: 2rem;">Join AuctionHub and start bidding or selling today!</p>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <strong>Success!</strong> Your account has been created. <a href="/auction_system/auth/login.php" style="color: var(--primary);">Login here</a>
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
                <button type="submit" class="btn" style="width: 100%;">Create Account</button>
            </form>

            <p style="text-align: center; margin-top: 1.5rem; color: var(--text-light);">
                Already have an account? <a href="/auction_system/auth/login.php" style="color: var(--primary);">Sign In</a>
            </p>
        <?php endif; ?>
    </div>
</main>

<?php include(__DIR__ . '/../includes/footer.php'); ?>