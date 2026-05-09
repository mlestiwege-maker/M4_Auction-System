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
<main>
    <section class="hero">
        <div>
            <h2>🏆 Welcome to AuctionHub</h2>
            <p>Experience the thrill of live bidding, discover incredible deals, and build trust through our modern marketplace. Real-time updates, smart auto-bidding, and a polished experience.</p>
            <div class="actions">
                <a class="btn" href="/auction_system/items/all_items.php">🔍 Browse Auctions</a>
                <a class="btn secondary" href="/auction_system/items/create_item.php">📤 Sell an Item</a>
            </div>
        </div>
        <div class="metric-card">
            <h3>Why Choose AuctionHub?</h3>
            <p>We combine cutting-edge technology with a user-centric design to deliver the best auction experience.</p>
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

    <section class="create-card" style="margin-bottom: 2rem;">
        <h2 style="margin-bottom: .25rem;">🔥 Trending Auctions</h2>
        <p class="text-muted" style="margin-bottom: 1rem;">Most active auctions based on live bid activity.</p>
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

    <section style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; padding: 3rem 2rem; border-radius: 16px; margin: 3rem 0; text-align: center;">
        <h2 style="margin-bottom: 1rem; color: white;">Ready to Start Bidding?</h2>
        <p style="margin-bottom: 1.5rem; font-size: 1.05rem; opacity: 0.95;">Join thousands of buyers and sellers on the most trusted auction platform.</p>
        <div class="actions" style="justify-content: center;">
            <a class="btn secondary" href="/auction_system/auth/register.php" style="color: var(--primary); border-color: white; background: white;">Create Free Account</a>
            <a class="btn" href="/auction_system/items/all_items.php" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid white;">Browse Now</a>
        </div>
    </section>

    <section style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem; margin: 3rem 0; text-align: center;">
        <div>
            <h3 style="font-size: 2.5rem; color: var(--primary); margin-bottom: 0.5rem;">1000+</h3>
            <p style="color: var(--text-light);">Active Auctions</p>
        </div>
        <div>
            <h3 style="font-size: 2.5rem; color: var(--primary); margin-bottom: 0.5rem;">5000+</h3>
            <p style="color: var(--text-light);">Happy Users</p>
        </div>
        <div>
            <h3 style="font-size: 2.5rem; color: var(--primary); margin-bottom: 0.5rem;">$100K+</h3>
            <p style="color: var(--text-light);">In Items Sold</p>
        </div>
    </section>
</main>
<?php include(__DIR__ . '/includes/footer.php'); ?>
