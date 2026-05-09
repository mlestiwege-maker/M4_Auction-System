# AuctionHub - Online Marketplace & Bidding Platform

A modern, full-featured auction system built with PHP, MySQL, JavaScript, and CSS. Real-time bidding, auto-bidding, seller dashboard, reviews, notifications, and more.

## 🚀 Quick Start

### Prerequisites
- PHP 7.4+ (with built-in web server or Apache)
- MySQL/MariaDB 5.7+
- Web browser (Chrome, Firefox, Safari, Edge)
- Git (optional)

### Step 1: Set Up Database
```bash
# Start MySQL/MariaDB
sudo service mariadb start

# Create database and tables
cd /home/mufutumari/Desktop/M4/auction_system
mysql -u root < schema.sql

# (Optional) Load sample data for testing
mysql -u root auction_db < seed_data.sql
```

### Step 2: Start PHP Development Server
```bash
# Navigate to project root
cd /home/mufutumari/Desktop/M4

# Start PHP server on localhost:8000
php -S localhost:8000
```

### Step 3: Open in Browser
Open your browser and go to:
```
http://localhost:8000/auction_system/index.html
```

Or directly to the platform:
```
http://localhost:8000/auction_system/index.php
```

### ⚡ One-Command Launch (Recommended)

From the project folder:

```bash
cd /home/mufutumari/Desktop/M4/auction_system
chmod +x run.sh stop.sh reset-db.sh
./run.sh
```

If you also want demo/sample records loaded:

```bash
./run.sh --seed
```

To stop the PHP server from another terminal:

```bash
./stop.sh
```

Reset database for a clean demo run (drops and recreates `auction_db`):

```bash
./reset-db.sh
```

Non-interactive reset (CI/demo automation):

```bash
./reset-db.sh --yes
```

---

## 📁 Project Structure

```
auction_system/
├── index.html              # Landing page (static)
├── index.php               # Platform home (dynamic)
├── features.html           # Features showcase
├── help.html               # Help & FAQ
├── about.html              # About page
├── contact.html            # Contact form
├── privacy.html            # Privacy policy
├── terms.html              # Terms of service
├── schema.sql              # Database schema
├── seed_data.sql           # Sample data (~30 records)
├── auth/
│   ├── login.php
│   ├── register.php
│   └── logout.php
├── items/
│   ├── create_item.php     # Post new auction
│   ├── edit_item.php       # Edit your listings
│   ├── delete_item.php     # Delete your listings
│   ├── all_items.php       # Browse auctions (with filters)
│   ├── view_item.php       # Item detail & bidding
│   ├── place_bid.php       # AJAX bid submission
│   └── finalize_auctions.php
├── reviews/
│   ├── submit_review.php
│   ├── get_seller_reviews.php
│   ├── list_reviews.php
├── user/
│   ├── dashboard.php       # Seller dashboard
│   ├── watchlist.php       # Saved items
│   ├── notifications.php   # Alert center
│   ├── get_notifications.php
│   └── mark_notification_read.php
├── includes/
│   ├── header.php
│   ├── footer.php
│   └── auction_helpers.php
├── config/
│   └── db.php              # Database connection
└── assets/
    ├── css/style.css
    ├── js/script.js
    └── uploads/
```

---

## ✨ Key Features

### User Features
- ✅ User registration & login
- ✅ Real-time live bidding with countdown timer
- ✅ Auto-bidding (bid automatically up to max price)
- ✅ Watchlist (save & track favorite items)
- ✅ Notifications (outbid, won, new bid alerts)
- ✅ 5-star seller reviews & ratings
- ✅ Advanced search & filtering
- ✅ Browse by 6 categories

### Seller Features
- ✅ Create auctions with images
- ✅ Edit & delete listings
- ✅ Seller dashboard with metrics
- ✅ Track earnings & bid activity
- ✅ View seller rating summary
- ✅ Winner badges on completed auctions

### Platform Features
- ✅ CSRF protection on all forms
- ✅ Real-time AJAX updates (no page refresh)
- ✅ Responsive mobile-friendly design
- ✅ Professional gradient UI theme

---

## 📊 Technologies

| Layer | Technology |
|-------|-----------|
| **Frontend** | HTML5, CSS3, Vanilla JavaScript |
| **Backend** | PHP 7+ with MySQLi |
| **Database** | MySQL/MariaDB |
| **Architecture** | Server-rendered with AJAX |
| **Real-time** | Polling (8s notifications, 2s bids, 5s bid history) |

---

## 🎯 Usage

### As a Buyer
1. Register/Login
2. Browse auctions at `/items/all_items.php`
3. Filter by category, price, or search
4. Click item to view details
5. Place bid or set auto-bid
6. Add to watchlist
7. View notifications for updates
8. Leave review after winning

### As a Seller
1. Register/Login
2. Go to "Sell Item" to create auction
3. Set title, description, category, price, image, duration
4. Monitor bids in real-time
5. View dashboard for earnings metrics
6. Edit or delete listings before any bids
7. See seller rating from buyer reviews

---

## 🗄️ Database Schema

**8 Tables:**
1. **users** - User accounts (name, email, password_hash)
2. **items** - Auction listings (title, description, category, prices, status, winner_id)
3. **bids** - Individual bids (item_id, user_id, bid_amount, bid_time)
4. **auto_bids** - Auto-bidding settings (max_bid per user per item)
5. **watchlist** - Saved items (user_id, item_id)
6. **reviews** - Seller ratings (reviewer_id, seller_id, rating, comment)
7. **notifications** - User alerts (type: outbid/won/new_bid/auction_ended)
8. **Additional fields:** items.winner_id, items.status, items.category

Run `schema.sql` to create all tables.

---

## 🔒 Security

- Prepared statements (MySQLi) for SQL injection prevention
- Password hashing with PHP password_hash()
- CSRF tokens on all state-changing forms
- Session-based authentication
- Input validation & sanitization
- Prepared statement parameter binding

---

## 💾 Sample Data

Run `seed_data.sql` to populate with:
- 5 demo users
- 10 sample items across all categories
- ~30 realistic bids
- ~10 sample reviews
- ~15 notifications

Great for testing all features without manual setup!

---

## 🐛 Troubleshooting

| Issue | Solution |
|-------|----------|
| **"Connection failed"** | MySQL not running. Run: `sudo service mariadb start` |
| **`ERROR 1698 (28000): Access denied for user 'root'@'localhost'`** | Use `./run.sh` (it auto-falls back to `sudo mysql` on socket-auth systems) |
| **404 errors** | Make sure you're running from `/M4` directory: `php -S localhost:8000` from that path |
| **Database not found** | Run `schema.sql`: `mysql -u root < schema.sql` |
| **Images not uploading** | Create `uploads/` folder with write permissions |
| **AJAX not working** | Clear browser cache, check JS console for errors |
| **`GET /favicon.ico` 404 in terminal** | Harmless browser request; optional to ignore or add a favicon file |

---

## 📝 API Endpoints (AJAX)

```
POST /items/place_bid.php              - Submit a bid
POST /items/finalize_auctions.php      - Close expired auctions
POST /items/toggle_watchlist.php       - Add/remove watchlist
GET  /user/get_notifications.php       - Fetch user notifications
POST /user/mark_notification_read.php  - Mark notification read
POST /reviews/submit_review.php        - Submit seller review
GET  /reviews/get_seller_reviews.php   - Fetch seller ratings
GET  /items/get_bid_history.php        - Bid history updates
```

All endpoints require CSRF token in POST requests.

---

## 📄 Files Summary

**HTML Pages (7):** index.html, features.html, help.html, about.html, contact.html, privacy.html, terms.html

**PHP Backend (20+):** Login, register, item creation/editing/deletion, bidding, watchlist, notifications, dashboard, reviews

**Database (2):** schema.sql, seed_data.sql

**Assets:** style.css (responsive design), script.js (AJAX & real-time updates), uploads/ (user and fallback images)

---

## ✅ Submission Checklist

- ✅ **HTML** - 7 pages included
- ✅ **PHP** - 20+ backend files
- ✅ **JavaScript** - AJAX polling, form handling
- ✅ **CSS** - Responsive grid, cards, mobile-friendly
- ✅ **Database** - MySQL schema + sample data
- ✅ **Features** - Real-time bidding, auto-bid, reviews, notifications, dashboard
- ✅ **Security** - CSRF protection, prepared statements, password hashing

---

## 📞 Support

For issues or questions, refer to help.html or contact.html pages.
