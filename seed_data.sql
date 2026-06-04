-- AuctionHub Seed Data (Sample Data for Quick Testing)
-- This SQL file contains sample users, items, bids, reviews, and notifications
-- Run this after creating your database tables to populate with demo data

-- Sample Users (password: password123 for all - already hashed)
INSERT INTO users (name, email, password) VALUES
('John Seller', 'john@example.com', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36gGUUe.'),
('Jane Buyer', 'jane@example.com', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36gGUUe.'),
('Mike Collector', 'mike@example.com', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36gGUUe.'),
('Sarah Tech', 'sarah@example.com', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36gGUUe.'),
('Alex Vintage', 'alex@example.com', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36gGUUe.');

-- Sample Items (Electronics)
INSERT INTO items (title, description, category, current_price, starting_price, user_id, end_time, image_url, status, winner_id) VALUES
('Apple iPad Air 2024', 'Brand new iPad Air with M2 chip, 256GB storage, Space Gray color. Perfect for creative work and productivity.', 'Electronics', 450.00, 350.00, 1, DATE_ADD(NOW(), INTERVAL 7 DAY), 'assets/uploads/originals/electronics/electronics-primary.jpg', 'active', NULL),
('Sony Wireless Headphones', 'High-quality Sony WH-1000XM5 noise-cancelling headphones. Excellent sound quality and 30-hour battery life.', 'Electronics', 280.00, 200.00, 1, DATE_ADD(NOW(), INTERVAL 5 DAY), 'assets/uploads/originals/electronics/headphones.jpeg', 'active', NULL),
('PlayStation 5 Console', 'Latest PS5 with 825GB SSD. Includes controller and HDMI cable. Mint condition.', 'Electronics', 500.00, 400.00, 2, DATE_ADD(NOW(), INTERVAL 3 DAY), 'assets/uploads/originals/electronics/ps5-console.jpeg', 'active', NULL);

-- Sample Items (Fashion)
INSERT INTO items (title, description, category, current_price, starting_price, user_id, end_time, image_url, status, winner_id) VALUES
('Vintage Leather Jacket', 'Classic brown vintage leather jacket from the 1980s. Size Medium. Well-preserved condition.', 'Fashion', 85.00, 50.00, 3, DATE_ADD(NOW(), INTERVAL 4 DAY), 'assets/uploads/originals/fashion/leather-jacket.jpeg', 'active', NULL),
('Designer Handbag', 'Authentic Louis Vuitton Neverfull MM handbag in Damier Ebene print. Perfect for everyday use.', 'Fashion', 750.00, 600.00, 2, DATE_ADD(NOW(), INTERVAL 6 DAY), 'assets/uploads/originals/fashion/designer-handbag.jpeg', 'active', NULL);

-- Sample Items (Home & Garden)
INSERT INTO items (title, description, category, current_price, starting_price, user_id, end_time, image_url, status, winner_id) VALUES
('Vintage Desk Lamp', 'Beautiful brass vintage desk lamp from the 1970s. Fully functional with new bulb included.', 'Home & Garden', 45.00, 30.00, 4, DATE_ADD(NOW(), INTERVAL 2 DAY), 'assets/uploads/originals/home-garden/desk-lamp.jpeg', 'active', NULL),
('Indoor Plant Collection', 'Set of 5 healthy indoor plants including monstera, pothos, and snake plants in decorative pots.', 'Home & Garden', 120.00, 80.00, 5, DATE_ADD(NOW(), INTERVAL 8 DAY), 'assets/uploads/originals/home-garden/indoor-plants.jpeg', 'active', NULL);

-- Sample Items (Collectibles)
INSERT INTO items (title, description, category, current_price, starting_price, user_id, end_time, image_url, status, winner_id) VALUES
('Pokemon Base Set Booster Box', 'Original sealed 1999 Pokemon Base Set booster box. Highly collectible and hard to find in mint condition.', 'Collectibles', 2500.00, 2000.00, 3, DATE_ADD(NOW(), INTERVAL 10 DAY), 'assets/uploads/originals/collectibles/pokemon-booster-box.jpeg', 'active', NULL),
('Vintage Comic Books Bundle', 'Lot of 15 vintage comic books from 1960s-1980s including Batman, Superman, and Amazing Spider-Man.', 'Collectibles', 350.00, 250.00, 1, DATE_ADD(NOW(), INTERVAL 5 DAY), 'assets/uploads/originals/collectibles/comic-books-bundle.jpeg', 'active', NULL);

-- Sample Items (Vehicles - small items)
INSERT INTO items (title, description, category, current_price, starting_price, user_id, end_time, image_url, status, winner_id) VALUES
('Bicycle Helmet - Bell Safety', 'High-quality bicycle helmet by Bell. Orange color, size medium. Excellent protection and comfort.', 'Vehicles', 65.00, 45.00, 4, DATE_ADD(NOW(), INTERVAL 4 DAY), 'assets/uploads/originals/vehicles/bicycle-helmet.jpeg', 'active', NULL),
('Car Phone Holder', 'Premium magnetic car phone holder for dashboard. Compatible with all smartphones. Easy installation.', 'Vehicles', 22.00, 15.00, 5, DATE_ADD(NOW(), INTERVAL 3 DAY), 'assets/uploads/originals/vehicles/car-phone-holder.jpeg', 'active', NULL);

-- Sample Bids
INSERT INTO bids (item_id, user_id, bid_amount, bid_time) VALUES
(1, 2, 380.00, DATE_SUB(NOW(), INTERVAL 10 HOUR)),
(1, 3, 410.00, DATE_SUB(NOW(), INTERVAL 8 HOUR)),
(1, 4, 450.00, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(2, 3, 220.00, DATE_SUB(NOW(), INTERVAL 15 HOUR)),
(2, 4, 250.00, DATE_SUB(NOW(), INTERVAL 6 HOUR)),
(2, 5, 280.00, DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(3, 5, 450.00, DATE_SUB(NOW(), INTERVAL 12 HOUR)),
(3, 3, 470.00, DATE_SUB(NOW(), INTERVAL 4 HOUR)),
(3, 2, 500.00, DATE_SUB(NOW(), INTERVAL 30 MINUTE)),
(5, 2, 70.00, DATE_SUB(NOW(), INTERVAL 8 HOUR)),
(5, 1, 85.00, DATE_SUB(NOW(), INTERVAL 2 HOUR));

-- Sample Auto-Bids
INSERT INTO auto_bids (item_id, user_id, max_bid) VALUES
(1, 2, 500.00),
(2, 3, 300.00),
(3, 5, 550.00),
(5, 1, 120.00);

-- Sample Watchlist
INSERT INTO watchlist (item_id, user_id, added_time) VALUES
(1, 3, NOW()),
(1, 5, NOW()),
(2, 2, NOW()),
(3, 4, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(5, 4, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(6, 2, NOW()),
(7, 3, DATE_SUB(NOW(), INTERVAL 3 DAY));

-- Sample Reviews (Only users who participated in auctions)
INSERT INTO reviews (item_id, reviewer_id, seller_id, rating, comment) VALUES
(1, 2, 1, 5, 'Excellent seller! Item was as described. Fast shipping and great packaging.'),
(1, 3, 1, 4, 'Good condition. Minor scratch on screen protector, but otherwise perfect.'),
(2, 3, 1, 5, 'Outstanding quality! Would buy from this seller again.'),
(2, 4, 1, 5, 'Amazing product and very professional seller.'),
(3, 5, 2, 4, 'Great item, delivered safely. Seller was communicative.'),
(5, 2, 3, 5, 'Beautiful jacket! Exactly as pictured. Highly recommend!'),
(6, 1, 2, 5, 'Seller communicated well and item arrived in perfect condition.'),
(7, 3, 4, 4, 'Nice lamp, works perfectly. Vintage charm intact.');

-- Sample Notifications
INSERT INTO notifications (user_id, notification_type, message, item_id, created_at, is_read) VALUES
-- Outbid notifications
(2, 'outbid', 'You have been outbid on iPad Air 2024', 1, DATE_SUB(NOW(), INTERVAL 2 HOUR), 1),
(3, 'outbid', 'You have been outbid on iPad Air 2024', 1, DATE_SUB(NOW(), INTERVAL 8 HOUR), 1),
(5, 'outbid', 'You have been outbid on Sony Wireless Headphones', 2, DATE_SUB(NOW(), INTERVAL 1 HOUR), 1),

-- Won notifications
(4, 'won', 'Congratulations! You won the auction for Sony Wireless Headphones', 2, DATE_SUB(NOW(), INTERVAL 1 HOUR), 0),
(2, 'won', 'Congratulations! You won the auction for PlayStation 5 Console', 3, DATE_SUB(NOW(), INTERVAL 30 MINUTE), 0),

-- New bid notifications
(1, 'new_bid', 'New bid placed on your item: iPad Air 2024', 1, DATE_SUB(NOW(), INTERVAL 2 HOUR), 1),
(1, 'new_bid', 'New bid placed on your item: iPad Air 2024', 1, DATE_SUB(NOW(), INTERVAL 8 HOUR), 1),
(2, 'new_bid', 'New bid placed on your item: PlayStation 5 Console', 3, DATE_SUB(NOW(), INTERVAL 4 HOUR), 1),

-- Auction ended notifications
(1, 'auction_ended', 'Your auction for Apple iPad Air 2024 has ended', 1, DATE_SUB(NOW(), INTERVAL 30 MINUTE), 1),
(1, 'auction_ended', 'Your auction for Sony Wireless Headphones has ended', 2, DATE_SUB(NOW(), INTERVAL 15 MINUTE), 1);

-- Optional: If you want ended auction examples with winners already set
-- UPDATE items SET status='ended', winner_id=4 WHERE id=2;
-- UPDATE items SET status='ended', winner_id=2 WHERE id=3;

-- Display confirmation message
SELECT 'Seed data successfully inserted into AuctionHub database!' AS Message;
SELECT COUNT(*) as 'Total Users' FROM users;
SELECT COUNT(*) as 'Total Items' FROM items;
SELECT COUNT(*) as 'Total Bids' FROM bids;
SELECT COUNT(*) as 'Total Reviews' FROM reviews;
SELECT COUNT(*) as 'Total Notifications' FROM notifications;
