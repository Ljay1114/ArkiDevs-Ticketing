# Troubleshooting: Ticket Submission Not Working

## Common Issues and Fixes

### Issue 1: JavaScript Not Loading

**Symptoms:**
- Form doesn't respond when clicking "Submit Ticket"
- No error messages appear
- Button doesn't do anything

**Fix:**
1. Check browser console for JavaScript errors (F12 → Console tab)
2. Verify scripts are loaded:
   - View page source (Ctrl+U)
   - Search for `arkidevs-support-customer.js`
   - Should see script tag loading the file

3. Clear browser cache and WordPress cache
4. Make sure jQuery is loaded (should be by default in WordPress)

### Issue 2: AJAX Error or 403 Forbidden

**Symptoms:**
- Error message: "Permission denied" or "403 Forbidden"
- Error in browser console about nonce/security

**Fix:**
1. Make sure you're logged in as a user with "Customer" role
2. Check user permissions:
   - Go to Users → All Users
   - Edit your user
   - Role should be "Customer"
3. Try logging out and back in
4. Clear WordPress cache/transients

### Issue 3: Form Validation Errors

**Symptoms:**
- Error message about required fields
- Form doesn't submit

**Fix:**
- Make sure Subject field is filled
- Make sure Description field is filled
- Both are required fields

### Issue 4: Database/Server Error

**Symptoms:**
- Error message: "Failed to create ticket"
- Server error 500

**Fix:**
1. Check WordPress debug log:
   - Enable WP_DEBUG in wp-config.php
   - Check error log for specific errors
2. Verify database tables exist:
   - Go to phpMyAdmin or database tool
   - Check for tables:
     - `wp_arkidevs_tickets`
     - `wp_arkidevs_ticket_replies`
     - `wp_arkidevs_time_logs`
     - `wp_arkidevs_customer_hours`
3. If tables don't exist, deactivate and reactivate the plugin

### Issue 5: Scripts Not Enqueued on Frontend

**Symptoms:**
- JavaScript file not loading
- `arkidevsSupport` object undefined in console

**Fix:**
1. Make sure the page has the shortcode: `[arkidevs_customer_dashboard]`
2. Scripts only load on pages with the shortcode
3. Make sure you're logged in (scripts only load for logged-in users)

## Debugging Steps

### Step 1: Check Browser Console

1. Open browser developer tools (F12)
2. Go to Console tab
3. Try submitting the form
4. Look for any JavaScript errors

### Step 2: Check Network Tab

1. Open browser developer tools (F12)
2. Go to Network tab
3. Try submitting the form
4. Look for the AJAX request: `admin-ajax.php`
5. Check:
   - Request status (should be 200)
   - Response data
   - Any error messages

### Step 3: Verify AJAX Handler

Check if the AJAX action is registered:
1. In browser console, type: `wp.ajax.settings.url`
2. Should show admin-ajax.php URL
3. Or check Network tab for the request URL

### Step 4: Test with Simple Form

Add this to test if JavaScript is working:

```javascript
jQuery(document).ready(function($) {
    console.log('jQuery loaded:', typeof $ !== 'undefined');
    console.log('arkidevsSupport:', typeof arkidevsSupport !== 'undefined' ? arkidevsSupport : 'NOT LOADED');
    $('#create-ticket-form').on('submit', function(e) {
        console.log('Form submitted!');
        e.preventDefault();
    });
});
```

## Quick Test Checklist

- [ ] User is logged in
- [ ] User has "Customer" role
- [ ] Page has the shortcode `[arkidevs_customer_dashboard]`
- [ ] JavaScript files are loading (check page source)
- [ ] jQuery is available
- [ ] No JavaScript errors in console
- [ ] AJAX request is being sent (check Network tab)
- [ ] Database tables exist
- [ ] Plugin is activated

## Still Not Working?

1. Enable WordPress debugging:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```
   Check `/wp-content/debug.log` for errors

2. Check server error logs

3. Verify file permissions are correct

4. Try deactivating other plugins to check for conflicts

5. Try switching to a default WordPress theme temporarily


