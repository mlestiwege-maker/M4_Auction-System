<?php
include(__DIR__ . '/config/db.php');
$homeTrending = $conn->query(
    "SELECT i.id, i.title, i.category, i.current_price, i.starting_price, COUNT(b.id) AS bid_count
     FROM items i
     LEFT JOIN bids b ON b.item_id = i.id
     WHERE (i.status IS NULL OR i.status = 'active')
     GROUP BY i.id
     ORDER BY bid_count DESC, i.current_price DESC
     LIMIT 3"
);
include(__DIR__ . '/includes/header.php');
?>
<main id="main" class="home-main">
    <section class="hero">
        <div>
            <h2>🏆 Welcome to AuctionHub</h2>
            <p>Experience real-time bidding, discover incredible deals, and build trust in a premium marketplace. Smart auto-bidding, live updates, and a polished experience.</p>
            <div class="actions">
                <a class="btn" href="/auction_system/items/all_items.php">🔍 Browse Auctions</a>
                <a class="btn secondary" href="/auction_system/items/create_item.php">📤 Sell an Item</a>
            </div>
        </div>
        <div class="metric-card">
            <h3>Why Choose AuctionHub?</h3>
            <p>We combine modern technology with clean design to deliver a marketplace that feels distinct and polished.</p>
            <div class="auction-status">
                <span class="status-pill active">⚡ Live Bidding</span>
                <span class="status-pill active">🤖 Auto-Bidding</span>
                <span class="status-pill active">✨ Professional UI</span>
            </div>
        </div>
    </section>

    <section class="feature-grid" aria-label="Platform features">
        <article class="feature">
            <h3>⚡ Real-Time Bidding</h3>
            <p>Countdown timers, instant bid updates every 2 seconds, and live price tracking keep auctions dynamic and exciting.</p>
        </article>
        <article class="feature">
            <h3>🤖 Smart Auto-Bidding</h3>
            <p>Set your maximum bid and let our system automatically stay competitive on your behalf, up to your chosen limit.</p>
        </article>
        <article class="feature">
            <h3>👤 Seller Dashboard</h3>
            <p>Manage listings, track bids in real-time, monitor earnings, and grow your reputation with buyer reviews.</p>
        </article>
        <article class="feature">
            <h3>❤️ Watchlist & Track</h3>
            <p>Save your favorite items to your watchlist and get instant notifications when prices drop or auctions end.</p>
        </article>
        <article class="feature">
            <h3>⭐ Reviews & Ratings</h3>
            <p>Build trust with a 5-star rating system. Buyers can review sellers and leave detailed feedback on purchases.</p>
        </article>
        <article class="feature">
            <h3>🔔 Smart Notifications</h3>
            <p>Get real-time alerts for outbid updates, auction wins, seller reviews, and other important marketplace events.</p>
        </article>
    </section>

    <section class="create-card trending-section">
        <h2>🔥 Trending Auctions</h2>
        <p class="text-muted">Most active auctions based on live bid activity.</p>
        <div class="trending-grid">
            <?php if ($homeTrending): ?>
                <?php while ($item = $homeTrending->fetch_assoc()): ?>
                    <article class="trending-card">
                        <h3><?= htmlspecialchars($item['title']) ?></h3>
                        <div class="trending-meta">
                            <span><?= htmlspecialchars($item['category'] ?: 'General') ?></span>
                            <span><?= (int) $item['bid_count'] ?> bids</span>
                        </div>
                        <p><strong>$<?= number_format((float) ($item['current_price'] ?: $item['starting_price']), 2) ?></strong></p>
                        <a class="btn" href="/auction_system/items/view_item.php?id=<?= (int) $item['id'] ?>">Open Auction</a>
                    </article>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="cta-hero-section">
        <h2>Ready to Start Bidding?</h2>
        <p>Join thousands of buyers and sellers on the most trusted auction platform.</p>
        <div class="actions">
            <a class="btn secondary" href="/auction_system/auth/register.php">Create Free Account</a>
            <a class="btn" href="/auction_system/items/all_items.php">Browse Now</a>
        </div>
    </section>

    <section class="stats-grid" aria-label="Marketplace stats">
        <div class="stat-card">
            <h3>1000+</h3>
            <p>Active Auctions</p>
        </div>
        <div class="stat-card">
            <h3>5000+</h3>
            <p>Happy Users</p>
        </div>
        <div class="stat-card">
            <h3>$100K+</h3>
            <p>In Items Sold</p>
        </div>
    </section>
</main>
<?php include(__DIR__ . '/includes/footer.php'); ?>
