# RBAC Setup & Administration Guide

## Complete Setup Instructions

### 1. Database Migration
- Open: http://localhost/auction_system/migrate_add_roles.php
- Click "Run Migration"
- Adds: role, seller_status, is_active columns to users table

### 2. Verify Installation  
```sql
SELECT id, name, role, seller_status FROM users LIMIT 5;
```
Should show role='admin' for first user.

### 3. Access Control
- Admin: /admin/dashboard.php
- Seller: /seller/dashboard.php  
- Buyer: /seller/request_approval.php to become seller

## Admin Pages

1. **dashboard.php** - Overview with key metrics
2. **manage_sellers.php** - Approve/reject sellers
3. **manage_users.php** - Deactivate/reactivate accounts
4. **moderate_listings.php** - Remove inappropriate items
5. **reports.php** - System analytics

## User Workflows

### Convert to Seller
1. User: Go to /seller/request_approval.php
2. Click: "Submit Seller Request"
3. Admin: Review in /admin/manage_sellers.php
4. Admin: Click "Approve"
5. User: Gains seller access

## Helper Functions

```php
get_user_role($user_id)      // Get role
is_admin($user_id)           // Boolean
is_seller($user_id)          // Boolean
require_admin()              // Protect page
require_seller()             // Protect page
```

## Troubleshooting

**Cannot access admin:** Login as first user
**Seller request missing:** Re-run migration  
**Permission denied:** Check column exists in database

## Database Schema

```sql
role ENUM('buyer', 'seller', 'admin') DEFAULT 'buyer'
seller_status ENUM('pending', 'approved', 'rejected')
is_active TINYINT(1) DEFAULT 1
```

