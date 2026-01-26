# Public Support Page Setup

The customer support dashboard is now available as a **public-facing page** that can be accessed directly via URL.

## Public URLs

After activation and flushing rewrite rules, the support system is accessible at:

### Main Dashboard
- **URL**: `yoursite.com/support/`
- Shows the customer dashboard with ticket list and creation form
- Requires login (shows login prompt if not logged in)

### Individual Ticket View
- **URL**: `yoursite.com/support/ticket/123/` (where 123 is the ticket ID)
- Shows a specific ticket with replies
- Requires login and permission to view that ticket

## Setup Instructions

### Step 1: Activate the Plugin
1. Go to **Plugins** in WordPress admin
2. Activate "Arkidevs Support"
3. This automatically registers the rewrite rules

### Step 2: Flush Rewrite Rules
**Important**: You need to flush rewrite rules after activation:

1. Go to **Settings → Permalinks** in WordPress admin
2. Just click **"Save Changes"** (don't change anything)
3. This flushes the rewrite rules and activates the `/support/` route

### Step 3: Test the Public Page
1. Visit: `yoursite.com/support/`
2. You should see the support dashboard

## Alternative: Using Shortcode (Still Available)

You can still use the shortcode method if you prefer:

1. Create a new page (e.g., "Support" or "My Tickets")
2. Add the shortcode: `[arkidevs_customer_dashboard]`
3. Publish the page
4. Access via the page URL

## Customizing the URL

To change the URL from `/support/` to something else (e.g., `/tickets/` or `/my-support/`):

1. Edit `includes/dashboards/class-customer-dashboard.php`
2. Find the `add_rewrite_rules()` function
3. Change `'^support/?$'` to `'^your-url/?$'`
4. Change `'^support/ticket/([0-9]+)/?$'` to `'^your-url/ticket/([0-9]+)/?$'`
5. Flush rewrite rules again (Settings → Permalinks → Save Changes)

## Notes

- **Login Required**: While the page is publicly accessible, creating and viewing tickets requires user login
- **User Roles**: Only users with the "Customer" role can create tickets
- **Permissions**: Customers can only view their own tickets
- **Pretty Permalinks**: The clean URLs require WordPress permalinks to be enabled. If using plain permalinks, it falls back to query strings like `/support/?ticket_id=123`

## Troubleshooting

### 404 Error on /support/

**Solution**: Flush rewrite rules
1. Go to Settings → Permalinks
2. Click "Save Changes"
3. Try accessing `/support/` again

### Page Shows But Looks Broken

**Solution**: Check if theme is compatible
- The page uses WordPress's standard `get_header()` and `get_footer()`
- Make sure your theme has these functions
- If issues persist, use the shortcode method instead

### URLs Not Working with Plain Permalinks

**Solution**: Enable pretty permalinks
1. Go to Settings → Permalinks
2. Select any option except "Plain"
3. Click "Save Changes"

The system will automatically use query strings as fallback if plain permalinks are enabled, but pretty permalinks are recommended.

