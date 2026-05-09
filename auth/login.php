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

<main style="max-width: 500px; margin: 3rem auto; padding: 0 1.5rem;">
    <div class="panel" style="margin-top: 2rem;">
        <h2 style="text-align: center; margin-bottom: 1.5rem; color: var(--primary);">Welcome Back!</h2>
        <p style="text-align: center; color: var(--text-light); margin-bottom: 2rem;">Sign in to continue to AuctionHub</p>

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
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="you@example.com" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>
            <button type="submit" class="btn" style="width: 100%;">Sign In</button>
        </form>

        <p style="text-align: center; margin-top: 1.5rem; color: var(--text-light);">
            Don't have an account? <a href="/auction_system/auth/register.php" style="color: var(--primary);">Create One</a>
        </p>
    </div>
</main>

<?php include(__DIR__ . '/../includes/footer.php'); ?>