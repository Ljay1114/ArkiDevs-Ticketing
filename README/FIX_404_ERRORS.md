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


=== Arkidevs Support ===
Contributors: arkidevs
Tags: support, tickets, time tracking, customer service
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Complete WordPress ticketing system with time tracking, role-based access, and email integration.

== Description ==

Arkidevs Support is a comprehensive ticketing system plugin for WordPress that allows you to manage customer support tickets efficiently.

== Features ==

* **Agent Dashboard** - View and manage all tickets (opened, in progress, closed)
* **Customer Dashboard** - Customers can view their submitted tickets
* **Role-Based Access** - Three user roles: Admin, Agent, and Customer
* **Time Tracking** - Track hours availed, spent, and remaining for each customer
* **Priority System** - Assign priority levels to tickets
* **Email Integration** - Create tickets via email (support@arkidevs.com) or dashboard
* **Ticket Management** - Full CRUD operations for tickets
* **Status Management** - Track ticket status (open, in progress, closed, etc.)

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/arkidevs-support` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The plugin will automatically create necessary database tw4ables and user roles

== Frequently Asked Questions ==

= How do I assign users to roles? =

Go to Users > All Users and edit a user. You'll see new role options: Agent and Customer.

= How do customers create tickets? =

Customers can create tickets from their dashboard or by sending an email to support@arkidevs.com

= How does time tracking work? =

Admins can allocate hours to customers. When agents work on tickets, time is tracked and deducted from the customer's available hours.

== Changelog ==

= 1.0.0 =
* Initial release
* Agent dashboard
* Customer dashboard
* Role-based authentication
* Time tracking system
* Priority handling
* Email ticket creation

== Upgrade Notice ==

= 1.0.0 =
Initial release of Arkidevs Support plugin.


