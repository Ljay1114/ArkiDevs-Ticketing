# Fix "Page Can't Be Found" Errors

## Problem

You're seeing "page can't be found" errors when trying to access the dashboards. This happens because:

1. **Customer Dashboard**: Requires a WordPress page to be created with the shortcode
2. **Admin Dashboard**: May not be registered correctly or plugin not activated

## Solution

### Fix 1: Customer Dashboard (404 Error)

**You need to create a WordPress page first!**

1. Go to **Pages > Add New** in WordPress admin
2. Create a new page with:
   - **Title**: "Customer Dashboard" (or any name you prefer)
   - **Content**: Add this shortcode: `[arkidevs_customer_dashboard]`
   - **Permalink/Slug**: Set it to `customer-dashboard` (important!)
3. **Publish** the page

**Alternative Method (if slug doesn't work):**
- Create the page with any slug/name
- Copy the page URL
- Use that URL to access the dashboard

### Fix 2: Admin Dashboard (Page Not Found)

If the admin dashboard shows 404:

1. **Check if plugin is activated:**
   - Go to **Plugins** in WordPress admin
   - Make sure "Arkidevs Support" is activated

2. **Clear permalinks:**
   - Go to **Settings > Permalinks**
   - Just click "Save Changes" (don't change anything)
   - This flushes rewrite rules

3. **Check admin menu:**
   - After activation, you should see "Support" in the admin sidebar
   - If not visible, check user permissions (must be Administrator or Agent)

### Fix 3: Quick Test

To verify everything is working:

**For Admin:**
- URL should be: `yoursite.com/wp-admin/admin.php?page=arkidevs-support-admin`
- Or: `yoursite.com/wp-admin/admin.php?page=arkidevs-support-agent`

**For Customer:**
- After creating the page, URL will be: `yoursite.com/customer-dashboard/`
- Or whatever slug you used for the page

### Fix 4: If Still Not Working

Check these common issues:

1. **User Role:**
   - Admin must have "Administrator" role
   - Agent must have "Support Agent" role  
   - Customer must have "Customer" role
   - Go to **Users > All Users** to assign roles

2. **Plugin Folder Name:**
   - Make sure the plugin folder is named `arkidevs-support` or matches your main plugin file name

3. **File Permissions:**
   - Ensure all plugin files are readable by WordPress

4. **Error Logs:**
   - Check WordPress debug log for PHP errors
   - Enable WP_DEBUG in wp-config.php if needed


