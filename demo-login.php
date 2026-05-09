<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: /auction_system/index.php");
    exit;
}

$demo_users = [
    'john' => ['id' => 1, 'name' => 'John Seller', 'role' => 'Seller', 'emoji' => '👨‍💼'],
    'jane' => ['id' => 2, 'name' => 'Jane Buyer', 'role' => 'Active Buyer', 'emoji' => '👩‍💼'],
    'mike' => ['id' => 3, 'name' => 'Mike Collector', 'role' => 'Collector', 'emoji' => '👨‍🎓'],
    'sarah' => ['id' => 4, 'name' => 'Sarah Tech', 'role' => 'Tech Buyer', 'emoji' => '👩‍💻'],
    'alex' => ['id' => 5, 'name' => 'Alex Vintage', 'role' => 'Vintage Seller', 'emoji' => '🎨'],
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $demo_user = $_POST['demo_user'] ?? '';
    if (array_key_exists($demo_user, $demo_users)) {
        $_SESSION['user_id'] = $demo_users[$demo_user]['id'];
        $_SESSION['username'] = $demo_user;
        $_SESSION['name'] = $demo_users[$demo_user]['name'];
        header("Location: /auction_system/index.php");
        exit;
    }
}
?>
<?php include(__DIR__ . '/../includes/header.php'); ?>

<main style="min-height: calc(100vh - 200px);">
    <section class="hero" style="text-align: center; padding: 6rem 2rem 4rem; background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%); color: white; position: relative; overflow: hidden;">
        <div style="position: relative; z-index: 2;">
            <h1 style="font-size: 2.5rem; margin-bottom: 1rem; text-shadow: 0 2px 4px rgba(0,0,0,0.2);">Experience AuctionHub</h1>
            <p style="font-size: 1.3rem; opacity: 0.95; max-width: 800px; margin: 0 auto 2rem; line-height: 1.6;">Try our premium auction platform with demo accounts - no registration required. Explore buying, selling, bidding, and all marketplace features instantly.</p>
            <div style="max-width: 500px; margin: 0 auto;">
                <a href="/auction_system/auth/register.php" class="btn" style="padding: 1rem 2.5rem; font-size: 1.1rem; background: white; color: var(--primary); border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">Create Your Account</a>
                <a href="/auction_system/index.php" class="btn secondary" style="padding: 1rem 2.5rem; font-size: 1.1rem; margin-left: 1rem; border-color: white; color: white;">Or Browse as Guest</a>
            </div>
        </div>
        <div style="position: absolute; bottom: 0; left: 0; right: 0; height: 200px; background: linear-gradient(to top, transparent, rgba(0,0,0,0.1));"></div>
    </section>

    <section style="max-width: 1200px; margin: 0 auto; padding: 0 2rem 4rem;">
        <div style="text-align: center; margin-bottom: 3rem;">
            <h2 style="color: var(--primary); margin-bottom: 1rem;">Choose Your Demo Experience</h2>
            <p style="color: var(--text-light); max-width: 700px; margin: 0 auto;">Select a pre-configured demo account to instantly explore AuctionHub's features. All accounts use password: <strong style="color: var(--primary);">password123</strong></p>
        </div>

        <div class="panel" style="margin-bottom: 2.5rem;">
            <div class="grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.5rem;">
                <?php foreach ($demo_users as $key => $user): ?>
                <form method="POST" style="background: var(--surface); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: 1px solid var(--border); transition: all 0.3s ease; position: relative;">
                    <div style="position: absolute; top: 1rem; right: 1rem; background: var(--primary); color: white; width: 2.5rem; height: 2.5rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.9rem;">
                        <span style="font-weight: 600;">Demo</span>
                    </div>
                    <button type="submit" name="demo_user" value="<?= $key ?>" style="width: 100%; padding: 2.5rem; text-align: left; border: none; background: none; cursor: pointer; display: flex; flex-direction: column; align-items: flex-start; gap: 1rem;">
                        <div style="display: flex; align-items: center; gap: 1rem; width: 100%;">
                            <div style="font-size: 3.5rem;"><?= $user['emoji'] ?></div>
                            <div>
                                <div style="font-size: 1.5rem; font-weight: 700; color: var(--text-dark);"><?= $user['name'] ?></div>
                                <div style="font-size: 1rem; color: var(--primary); font-weight: 600;"><?= $user['role'] ?></div>
                            </div>
                        </div>
                        <div style="width: 100%; text-align: right; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border); color: var(--text-light); font-size: 0.9rem;">
                            Login with password123
                        </div>
                    </button>
                </form>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="panel">
            <h2 style="color: var(--primary); margin-bottom: 1.5rem; text-align: center;">What You'll Experience</h2>
            <div class="grid-3" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem;">
                <div style="text-align: center; padding: 1.5rem; background: var(--surface-light); border-radius: 12px; border: 1px solid var(--border);">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🛍️</div>
                    <h3 style="color: var(--primary); margin-bottom: 1rem;">Buyer Experience</h3>
                    <ul style="text-align: left; line-height: 1.8; color: var(--text-dark);">
                        <li>✓ Browse live auctions across categories</li>
                        <li>✓ Place bids and use auto-bidding</li>
                        <li>✓ Track items on your watchlist</li>
                        <li>✓ Receive real-time notifications</li>
                        <li>✓ Review sellers and build reputation</li>
                    </ul>
                </div>
                <div style="text-align: center; padding: 1.5rem; background: var(--surface-light); border-radius: 12px; border: 1px solid var(--border);">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🏪</div>
                    <h3 style="color: var(--primary); margin-bottom: 1rem;">Seller Experience</h3>
                    <ul style="text-align: left; line-height: 1.8; color: var(--text-dark);">
                        <li>✓ List items for auction</li>
                        <li>✓ Manage active listings</li>
                        <li>✓ Monitor bids in real-time</li>
                        <li>✓ Track sales and earnings</li>
                        <li>✓ Respond to buyer feedback</li>
                    </ul>
                </div>
                <div style="text-align: center; padding: 1.5rem; background: var(--surface-light); border-radius: 12px; border: 1px solid var(--border);">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">📊</div>
                    <h3 style="color: var(--primary); margin-bottom: 1rem;">Dashboard & Tools</h3>
                    <ul style="text-align: left; line-height: 1.8; color: var(--text-dark);">
                        <li>✓ Personal analytics dashboard</li>
                        <li>✓ Bidding history and insights</li>
                        <li>✓ Account management</li>
                        <li>✓ Notification center</li>
                        <li>✓ Secure payments & escrow</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include(__DIR__ . '/../includes/footer.php'); ?>