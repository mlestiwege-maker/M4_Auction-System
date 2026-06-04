# RBAC Testing Guide

## Test Scenarios

### Test 1: Database Migration
1. Open: http://localhost/auction_system/migrate_add_roles.php
2. Click "Run Migration"
3. ✓ Should see: "Migration complete!"

### Test 2: Admin Access
1. Login as first user (admin)
2. Navigate to: /admin/dashboard.php
3. ✓ Should see: Admin dashboard

### Test 3: Buyer Blocked
1. Login as regular user (buyer)
2. Try direct access: /admin/dashboard.php
3. ✓ Should be: Blocked/redirected

### Test 4: Seller Workflow
1. Create new account (buyer)
2. Request seller: /seller/request_approval.php
3. Admin approves: /admin/manage_sellers.php
4. User accesses: /seller/dashboard.php
5. ✓ Should work: Seller sees dashboard

### Test 5: User Deactivation
1. Admin goes to: /admin/manage_users.php
2. Click: "Deactivate"
3. ✓ User cannot login

## Verification Commands

```sql
-- Check roles
SELECT id, name, role FROM users;

-- Find pending sellers
SELECT id, name, seller_status FROM users WHERE seller_status='pending';

-- Check admin
SELECT id, name FROM users WHERE role='admin';
```

## Acceptance Criteria

- [x] Migration completes
- [x] Admin access works
- [x] Buyer blocked from admin
- [x] Seller approval workflow works  
- [x] User deactivation works
- [x] No PHP/SQL errors

