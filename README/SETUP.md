# Arkidevs Support Plugin - Setup Guide

## Installation

1. Upload the plugin to `/wp-content/plugins/arkidevs-support/`
2. Activate the plugin through the WordPress admin
3. The plugin will automatically:
   - Create custom user roles (Agent, Customer)
   - Create necessary database tables
   - Set up default options

## Configuration

### 1. User Roles

After activation, you can assign roles to users:
- **Administrator**: Full access to all features
- **Support Agent**: Can manage tickets, track time, view all tickets
- **Customer**: Can create tickets, view their own tickets

To assign roles:
1. Go to Users > All Users
2. Edit a user
3. Select the appropriate role (Support Agent or Customer)

### 2. Customer Dashboard

To enable the customer dashboard, create a new WordPress page and add this shortcode:

```
[arkidevs_customer_dashboard]
```

Alternatively, create a page with slug `customer-dashboard` and the plugin will automatically detect it.

### 3. Time Tracking

Admins can allocate hours to customers:
- Go to the admin dashboard
- Allocate hours through the customer management interface (can be extended with custom admin page)

Hours are automatically tracked when agents start/stop timers on tickets.

### 4. Email Integration

The plugin supports ticket creation via email. To enable:

1. Set up email forwarding to `support@arkidevs.com` (or configure your support email in plugin settings)
2. Set up a WordPress cron job to check the email inbox (requires IMAP library)
3. Configure IMAP settings (see `includes/email/class-email-listener.php` for implementation details)

**Note**: Full email integration requires additional setup with an IMAP library like `webklex/php-imap`. The basic structure is in place, but you'll need to:

1. Install the IMAP library via Composer
2. Configure IMAP credentials
3. Set up a cron job to call `Arkidevs_Support_Email_Listener::check_email_inbox()`

## Usage

### For Customers

1. Log in to your WordPress account
2. Visit the Customer Dashboard page
3. Click "Create Ticket" to submit a new support request
4. View your tickets and time tracking information

### For Agents

1. Log in to WordPress
2. Go to Support > Tickets in the admin menu
3. View tickets by status (Open, In Progress, Closed)
4. Click on a ticket to:
   - View details
   - Update status/priority
   - Start/stop time tracking
   - Add replies
   - Close tickets

### For Administrators

1. Full access to all features
2. Can allocate hours to customers
3. Can view all tickets and reports
4. Access to admin dashboard with statistics

## Priority Levels

- **Low**: Non-urgent issues
- **Medium**: Normal priority (default)
- **High**: Important issues
- **Critical**: Urgent issues requiring immediate attention

## Ticket Statuses

- **Open**: Newly created ticket
- **In Progress**: Ticket is being worked on
- **Pending**: Waiting for customer response or information
- **Closed**: Ticket is resolved and closed
- **Resolved**: Ticket is resolved but not yet closed

## Time Tracking

- Agents can start/stop timers on tickets
- Time is automatically tracked and deducted from customer's allocated hours
- Customers can view their hours summary:
  - Hours Allocated
  - Hours Spent
  - Hours Remaining

## Support

For issues or questions, please contact support@arkidevs.com


