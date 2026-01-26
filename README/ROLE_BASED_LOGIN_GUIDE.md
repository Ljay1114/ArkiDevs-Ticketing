# Role-Based Login System Guide

## Overview

The Arkidevs Support plugin implements a comprehensive role-based login system with three distinct user roles, each with their own dashboard and access levels.

## User Roles

### 1. **Administrator**
- **Full Access**: Complete control over the entire system
- **Dashboard**: Admin Dashboard (`/wp-admin/admin.php?page=arkidevs-support-admin`)
- **Capabilities**:
  - View all tickets
  - Manage all tickets
  - Allocate hours to customers
  - Track time on any ticket
  - Assign tickets to agents
  - View system statistics
  - Access agent dashboard features

### 2. **Support Agent**
- **Ticket Management**: Can manage and respond to tickets
- **Dashboard**: Agent Dashboard (`/wp-admin/admin.php?page=arkidevs-support-agent`)
- **Capabilities**:
  - View all tickets
  - Update ticket status and priority
  - Track time on tickets
  - Add replies to tickets
  - Close tickets
  - View tickets by status (Open, In Progress, Closed)

### 3. **Customer**
- **Self-Service**: Can create and view their own tickets
- **Dashboard**: Customer Dashboard (`/support/`)
- **Capabilities**:
  - Create new tickets
  - View their own tickets
  - View time tracking (allocated, spent, remaining)
  - Add replies to their tickets
  - View ticket history

## Login Redirects

### Automatic Redirects After Login

When users log in, they are automatically redirected based on their role:

- **Administrator** → Admin Dashboard
- **Support Agent** → Agent Dashboard  
- **Customer** → Customer Support Page (`/support/`)

### How It Works

1. User logs in via WordPress login page (`/wp-login.php`)
2. System checks user's role
3. User is redirected to their appropriate dashboard
4. If user tries to access wrong area, they're redirected automatically

## Access Control

### Customer Restrictions

- **Cannot access WordPress admin area**
  - If a customer tries to access `/wp-admin/`, they're redirected to `/support/`
  - Prevents customers from seeing admin interface

- **Can only view their own tickets**
  - Customers see only tickets they created
  - Cannot access other customers' tickets

### Agent/Admin Restrictions

- **Redirected from customer dashboard**
  - If an agent or admin visits `/support/`, they're redirected to their admin dashboard
  - This keeps the customer dashboard for customers only

### Admin Privileges

- **Can access everything**
  - Admins can access admin dashboard, agent dashboard, and customer dashboard (for preview)
  - Full system access

## Setting Up User Roles

### Step 1: Create Users

1. Go to **Users → Add New** in WordPress admin
2. Fill in user details:
   - Username
   - Email
   - Password
3. **Important**: Select the appropriate role:
   - **Support Agent** - For staff who manage tickets
   - **Customer** - For clients who need support
4. Click **"Add New User"**

### Step 2: Assign Roles to Existing Users

1. Go to **Users → All Users**
2. Find the user you want to modify
3. Click **"Edit"**
4. Scroll to **"Role"** dropdown
5. Select:
   - **Support Agent** - For ticket management
   - **Customer** - For ticket creation
6. Click **"Update User"**

### Step 3: Test Login

1. Log out of WordPress
2. Log in with a test user for each role
3. Verify redirects work correctly:
   - Customer → `/support/`
   - Agent → Agent Dashboard
   - Admin → Admin Dashboard

## Login URLs

### Standard WordPress Login
- **URL**: `yoursite.com/wp-login.php`
- Works for all users
- Redirects based on role after login

### Customer Support Login
- **URL**: `yoursite.com/support/`
- If not logged in, shows login prompt
- After login, stays on support page

## Security Features

### Role Verification
- All actions verify user roles before allowing access
- AJAX requests check user capabilities
- Database queries filter by user role

### Session Management
- Uses WordPress's built-in authentication
- Secure nonce verification for all AJAX requests
- Proper permission checks on all operations

## Troubleshooting

### User Can't Access Their Dashboard

**Issue**: User logs in but doesn't get redirected correctly

**Solutions**:
1. Check user role:
   - Go to Users → All Users
   - Edit user
   - Verify role is correct
2. Clear browser cache
3. Try logging out and back in
4. Check if redirect URL is correct

### Customer Can Access Admin Area

**Issue**: Customer can see WordPress admin

**Solution**: 
- This shouldn't happen - customers are automatically redirected
- If it does, check that the plugin is activated
- Verify customer role is set correctly

### Wrong Redirect After Login

**Issue**: User goes to wrong dashboard

**Solutions**:
1. Check user role assignment
2. Verify redirect logic in `includes/auth/class-auth-redirect.php`
3. Clear WordPress cache
4. Check for plugin conflicts

## Testing Checklist

- [ ] Administrator can log in and access admin dashboard
- [ ] Support Agent can log in and access agent dashboard
- [ ] Customer can log in and access `/support/` page
- [ ] Customer cannot access `/wp-admin/` (redirected to `/support/`)
- [ ] Agent/Admin cannot access `/support/` (redirected to their dashboard)
- [ ] Login redirects work correctly for all roles
- [ ] Users can only see tickets they have permission to view

## Best Practices

1. **Role Assignment**: Always assign the correct role when creating users
2. **Regular Audits**: Periodically check user roles to ensure they're correct
3. **Test Logins**: Test login flow for each role type
4. **Security**: Never give customers admin or agent roles unless necessary
5. **Documentation**: Keep track of which users have which roles

## Customization

### Change Redirect URLs

Edit `includes/auth/class-auth-redirect.php`:

```php
// Change customer redirect
return home_url( '/your-custom-url/' );

// Change agent redirect  
return admin_url( 'admin.php?page=your-custom-page' );
```

### Add Custom Role Checks

You can add additional role checks in the security class:

```php
// In includes/helpers/class-security.php
case 'your_custom_capability':
    return in_array( 'your_role', $user->roles, true );
```

