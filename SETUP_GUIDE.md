# AuctionHub Setup Guide

## ⚠️ Important: Start MariaDB First

Before anything else, your MariaDB/MySQL database service must be running.

### Step 1: Start MariaDB Database

**On Linux/WSL:**
```bash
sudo service mariadb start
```

Or if using MySQL:
```bash
sudo service mysql start
```

To verify it's running:
```bash
mysql -u root -e "SELECT VERSION();"
```

You should see output like: `10.6.X-MariaDB` or `8.0.X`

---

## Step 2: Run the Setup Wizard (Automatic)

Once MariaDB is running, follow these steps:

### Option A: Using Web Browser (Guided Setup)

1. Make sure PHP server is running:
   ```bash
   cd /home/mufutumari/Desktop/M4
   php -S localhost:8000
   ```

2. Open browser and go to:
   ```
   http://localhost:8000/auction_system/setup.php
   ```

3. Click "Check MySQL Connection →" to start setup
4. Follow the 3 steps to complete initialization
5. Once complete, click "Launch Platform 🚀"

### Option B: Manual SQL Setup (If Wizard Fails)

If the web wizard doesn't work, run these commands manually:

```bash
# Create database
mysql -u root -e "CREATE DATABASE auction_db;"

# Create tables  
cd /home/mufutumari/Desktop/M4/auction_system
mysql -u root auction_db < schema.sql

# Load sample data (optional but recommended for testing)
mysql -u root auction_db < seed_data.sql
```

Then verify:
```bash
mysql -u root auction_db -e "SHOW TABLES;"
```

You should see 8 tables: users, items, bids, auto_bids, watchlist, reviews, notifications

---

## Step 3: Start PHP Development Server

```bash
cd /home/mufutumari/Desktop/M4
php -S localhost:8000
```

Server should show:
```
Development Server started...
Listening on: http://localhost:8000
```

---

## Step 4: Access the Marketplace

Open your browser and navigate to:

- **Landing Page**: `http://localhost:8000/auction_system/index.html`
- **Platform Home**: `http://localhost:8000/auction_system/index.php`
- **Quick Demo**: `http://localhost:8000/auction_system/demo-login.php`

---

## Demo Accounts (After Loading Sample Data)

If you loaded the sample data, use these accounts:

| Username | Password | Role |
|----------|----------|------|
| john | password123 | Seller |
| jane | password123 | Buyer |
| mike | password123 | Collector |
| sarah | password123 | Tech Buyer |
| alex | password123 | Vintage Seller |

---

## Troubleshooting

### "Can't connect to local server" Error

**Problem**: `ERROR 2002 (HY000): Can't connect to local server through socket`

**Solution**:
```bash
# Ensure MariaDB is running
sudo service mariadb status

# If not running, start it
sudo service mariadb start

# If socket error persists, check socket location
ls -la /run/mysqld/mysqld.sock
```

### "Access denied" Error

**Problem**: `ERROR 1045 (28000): Access denied for user 'root'`

**Solution**:
```bash
# Check if MySQL root has a password
mysql -u root

# If prompted for password, use:
# (By default, root has no password)

# If connection works, check database creation
mysql -u root -e "SHOW DATABASES;"
```

### "Table already exists" Warning

This is normal if setup.php is run multiple times. It simply skips existing tables and continues.

### PHP Pages Show Blank/Error

1. Check PHP error logs:
   ```bash
   php -S localhost:8000 > php_error.log 2>&1
   ```

2. Check if database connection is working:
   ```bash
   mysql -u root auction_db -e "SELECT * FROM users LIMIT 1;"
   ```

3. Ensure tables were created:
   ```bash
   mysql -u root auction_db -e "SHOW TABLES;"
   ```

---

## Complete Fresh Setup (Nuclear Option)

If everything is broken, start fresh:

```bash
# Stop PHP server (Ctrl+C if running)

# Reset database
sudo service mariadb start
mysql -u root -e "DROP DATABASE IF EXISTS auction_db;"

# Run setup wizard again or manual commands above
mysql -u root -e "CREATE DATABASE auction_db;"
cd /home/mufutumari/Desktop/M4/auction_system
mysql -u root auction_db < schema.sql
mysql -u root auction_db < seed_data.sql

# Restart PHP server
cd /home/mufutumari/Desktop/M4
php -S localhost:8000
```

---

## File Locations

- **Project Root**: `/home/mufutumari/Desktop/M4/`
- **Auction System**: `/home/mufutumari/Desktop/M4/auction_system/`
- **Database Schema**: `/home/mufutumari/Desktop/M4/auction_system/schema.sql`
- **Sample Data**: `/home/mufutumari/Desktop/M4/auction_system/seed_data.sql`
- **PHP Source**: `/home/mufutumari/Desktop/M4/auction_system/` (all .php files)
- **CSS/JS**: `/home/mufutumari/Desktop/M4/auction_system/assets/`

---

## System Architecture

```
┌─────────────────────┐
│   Browser (Client   │
│   http://localhost  │
│   :8000)            │
└──────────┬──────────┘
           │
           ↓
┌─────────────────────────────┐
│   PHP Dev Server            │
│   (php -S localhost:8000)   │
└──────────┬──────────────────┘
           │
           ↓
┌─────────────────────┐
│   PHP Backend       │
│   (index.php,       │
│   items/*.php,      │
│   user/*.php)       │
└──────────┬──────────┘
           │
           ↓
┌─────────────────────┐
│   MySQL/MariaDB     │
│   (auction_db)      │
│   - 8 tables        │
│   - 30+ records     │
└─────────────────────┘
```

---

## Features Available After Setup

✅ **Buyer Features**
- Browse 10+ active auctions
- Place real-time bids
- Auto-bidding system
- Watchlist/favorites
- Notifications center
- Seller reviews

✅ **Seller Features**
- Create new auctions  
- Upload item images
- Edit/delete listings
- Seller dashboard
- Earnings tracking
- Bid notifications

✅ **Platform Features**
- 6 item categories
- Advanced search/filters
- Real-time price updates
- 5-star rating system
- Security (CSRF tokens, password hashing)
- Responsive mobile design

---

## Next Steps

1. ✅ Create database & load schema
2. ✅ Load sample data  
3. ✅ Start PHP server
4. 👉 **Browse the platform** at http://localhost:8000/auction_system/
5. 👉 **Try demo account** at http://localhost:8000/auction_system/demo-login.php
6. 👉 **Create your account** to test full functionality
7. 👉 **Start bidding!** 🎯

---

## Support

For detailed information, see:
- [README.md](README.md) - Project overview & features
- [features.html](features.html) - Feature showcase
- [help.html](help.html) - FAQ & help
- [about.html](about.html) - About the platform

Enjoy AuctionHub! 🏆
