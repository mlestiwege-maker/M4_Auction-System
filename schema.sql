-- AuctionHub Database Schema
-- Complete database setup for the auction marketplace system

-- Users Table
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Items Table
CREATE TABLE IF NOT EXISTS items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  category VARCHAR(100) DEFAULT '',
  current_price DECIMAL(10,2) NOT NULL,
  starting_price DECIMAL(10,2) NOT NULL,
  user_id INT NOT NULL,
  end_time DATETIME NOT NULL,
  image_url VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  status VARCHAR(20) DEFAULT 'active',
  winner_id INT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (winner_id) REFERENCES users(id)
);

-- Bids Table
CREATE TABLE IF NOT EXISTS bids (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_id INT NOT NULL,
  user_id INT NOT NULL,
  bid_amount DECIMAL(10,2) NOT NULL,
  bid_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (item_id) REFERENCES items(id),
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Auto-Bids Table
CREATE TABLE IF NOT EXISTS auto_bids (
  user_id INT NOT NULL,
  item_id INT NOT NULL,
  max_bid DECIMAL(10,2) NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, item_id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (item_id) REFERENCES items(id)
);

-- Watchlist Table
CREATE TABLE IF NOT EXISTS watchlist (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  item_id INT NOT NULL,
  added_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (item_id) REFERENCES items(id)
);

-- Reviews Table
CREATE TABLE IF NOT EXISTS reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_id INT,
  reviewer_id INT NOT NULL,
  seller_id INT NOT NULL,
  rating TINYINT NOT NULL,
  comment TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (reviewer_id) REFERENCES users(id),
  FOREIGN KEY (seller_id) REFERENCES users(id),
  FOREIGN KEY (item_id) REFERENCES items(id)
);

-- Notifications Table
CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  notification_type VARCHAR(50) NOT NULL,
  message TEXT NOT NULL,
  item_id INT,
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (item_id) REFERENCES items(id)
);
