# RBAC Developer Reference

## Quick Helper Functions

### Role Checking
```php
get_user_role($user_id)           // Returns: admin|seller|buyer
is_admin($user_id)                // TRUE/FALSE
is_seller($user_id)               // TRUE/FALSE
has_seller_approval($user_id)     // TRUE/FALSE
```

### Access Control (Page Protection)
```php
require_admin()                   // Redirect if not admin
require_seller()                  // Redirect if not seller
require_buyer()                   // Redirect if not logged in
```

### Seller Management
```php
get_pending_sellers()             // Array
get_users_by_role('seller')       // Array
approve_seller($user_id)          // Process approval
reject_seller($user_id)           // Reject request
```

### User Management
```php
deactivate_user($user_id)         // Set is_active=0
reactivate_user($user_id)         // Set is_active=1
```

## Page Protection Pattern

```php
<?php
require_once __DIR__ . '/../includes/rbac_helpers.php';
require_admin();  // Protect page

// Your page code here
?>
```

## CSRF Protection Pattern

```html
<form method="POST">
    <input type="hidden" name="csrf_token" 
           value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <button type="submit">Action</button>
</form>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('CSRF token mismatch');
    }
    // Process form
}
?>
```

## Role-Based Conditional UI

```php
<?php if (is_admin()) { ?>
    <--host Admin content -->
<?php } elseif (is_seller()) { ?>
    <--host Seller content -->
<?php } else { ?>
    <--host Buyer content -->
<?php } ?>
```

## Testing Your Addition

1. Add RBAC check at top: `require_admin()`
2. Test as admin: Should work
3. Test as buyer: Should be blocked
4. Check CSRF tokens on forms
5. Verify no PHP errors

