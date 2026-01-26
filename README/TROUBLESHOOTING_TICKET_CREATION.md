# Troubleshooting Ticket Creation

## Common Issues and Solutions

### Issue: "Create Ticket" button doesn't work

#### Step 1: Check Browser Console
1. Open browser Developer Tools (F12)
2. Go to Console tab
3. Try to create a ticket
4. Look for any JavaScript errors

**Common errors:**
- `arkidevsSupport is not defined` - Scripts not loading
- `ajaxUrl is undefined` - Localization issue
- `nonce is undefined` - Nonce not being passed

#### Step 2: Verify User Role
1. Go to WordPress Admin → Users
2. Find your customer account
3. Check that the role is set to "Customer" (arkidevs_customer)
4. If not, change it and save

#### Step 3: Verify Scripts Are Loading
1. Open browser Developer Tools (F12)
2. Go to Network tab
3. Refresh the page
4. Look for these files:
   - `customer.js`
   - `common.js`
   - Check if they return 200 status

#### Step 4: Check AJAX Endpoint
1. Open browser Developer Tools (F12)
2. Go to Network tab
3. Try to create a ticket
4. Look for request to `admin-ajax.php`
5. Check the response:
   - If 403: Permission issue
   - If 400: Bad request (check nonce)
   - If 500: Server error (check PHP error logs)

#### Step 5: Verify Nonce
The nonce should be available in JavaScript as `arkidevsSupport.nonce`. Check console:
```javascript
console.log(arkidevsSupport);
```

Should show:
```javascript
{
  ajaxUrl: "...",
  nonce: "..."
}
```

### Issue: Permission Denied Error

**Solution:**
1. Make sure user has `arkidevs_customer` role
2. Go to Users → Edit User
3. Set role to "Customer"
4. Save and try again

### Issue: Scripts Not Loading

**Solution:**
1. Make sure you're on `/support/` page or page with shortcode
2. Make sure you're logged in
3. Check if rewrite rules are flushed (Settings → Permalinks → Save)
4. Hard refresh page (Ctrl+F5)

### Issue: Form Not Showing

**Solution:**
1. Click "Create Ticket" button
2. Form should appear
3. If not, check browser console for JavaScript errors
4. Check if CSS file is loading (`.hidden` class should hide form initially)

## Manual Testing Steps

1. **Login as Customer:**
   - Go to `/support/` or customer dashboard page
   - Make sure you're logged in with customer role

2. **Click Create Ticket:**
   - Button should show form
   - Form should have Subject, Priority, Description fields

3. **Fill Form:**
   - Subject: "Test Ticket"
   - Description: "This is a test"
   - Priority: Select any

4. **Submit:**
   - Click "Submit Ticket"
   - Button should show "Submitting..."
   - Should see success message
   - Page should reload showing new ticket

## Debug Mode

To enable detailed logging, check browser console. The updated code includes console.log statements that will help identify the issue.

