# Role-Based Login System - Complete Setup Guide

## Overview

The role-based login system automatically redirects users to their appropriate dashboard based on their assigned role after login.

## Three User Roles

### 1. Administrator
- **Login Redirect**: Admin Dashboard (`/wp-admin/admin.php?page=arkidevs-support-admin`)
- **Access**: Full system access
- **Can**: View all tickets, manage everything, allocate hours, track time

### 2. Support Agent  
- **Login Redirect**: Agent Dashboard (`/wp-admin/admin.php?page=arkidevs-support-agent`)
- **Access**: Ticket management
- **Can**: View/manage tickets, track time, update status/priority, add replies

### 3. Customer
- **Login Redirect**: Customer Support Page (`/support/`)
- **Access**: Self-service support
- **Can**: Create tickets, view own tickets, track hours, add replies

## How It Works

### Login Flow

1. **User logs in** via WordPress login page (`/wp-login.php`)
2. **System checks** user's role
3. **Automatic redirect** to appropriate dashboard:
   - Administrator → Admin Dashboard
   - Support Agent → Agent Dashboard
   - Customer → `/support/` page

### Access Control

- **Customers** cannot access `/wp-admin/` - automatically redirected to `/support/`
- **Agents/Admins** cannot access `/support/` - automatically redirected to their dashboard
- **Role verification** happens on every page load

## Setup Instructions

### Step 1: Assign Roles to Users

1. Go to **Users → All Users** in WordPress admin
2. For each user:
   - Click **"Edit"**
   - Scroll to **"Role"** dropdown
   - Select appropriate role:
     - **Support Agent** - For staff managing tickets
     - **Customer** - For clients needing support
   - Click **"Update User"**

### Step 2: Test Login Redirects

**Test Administrator:**
1. Log out
2. Log in as Administrator
3. Should redirect to: `/wp-admin/admin.php?page=arkidevs-support-admin`

**Test Support Agent:**
1. Create a user with "Support Agent" role
2. Log out
3. Log in as that agent
4. Should redirect to: `/wp-admin/admin.php?page=arkidevs-support-agent`

**Test Customer:**
1. Create a user with "Customer" role
2. Log out
3. Log in as that customer
4. Should redirect to: `/support/`

### Step 3: Verify Access Restrictions

**Customer Restrictions:**
1. Log in as Customer
2. Try accessing `/wp-admin/`
3. Should be redirected to `/support/`

**Agent/Admin Restrictions:**
1. Log in as Agent or Admin
2. Try accessing `/support/`
3. Should be redirected to their dashboard

## Login Options

### Option 1: Standard WordPress Login
- **URL**: `yoursite.com/wp-login.php`
- Works for all users
- Redirects based on role after login

### Option 2: Support Page Login
- **URL**: `yoursite.com/support/`
- If not logged in, shows login prompt
- After login, stays on support page (for customers)

### Option 3: Login Widget (Optional)
- Go to **Appearance → Widgets**
- Add "Support Login" widget to sidebar
- Shows login link for non-logged-in users

## Role Capabilities

### Administrator Capabilities
- ✅ Full system access
- ✅ View all tickets
- ✅ Manage all tickets
- ✅ Allocate hours to customers
- ✅ Track time on any ticket
- ✅ Access admin dashboard
- ✅ Access agent dashboard
- ✅ Can preview customer dashboard

### Support Agent Capabilities
- ✅ View all tickets
- ✅ Update ticket status/priority
- ✅ Track time on tickets
- ✅ Add replies to tickets
- ✅ Close tickets
- ✅ Access agent dashboard
- ❌ Cannot allocate hours (admin only)
- ❌ Cannot access customer dashboard

### Customer Capabilities
- ✅ Create tickets
- ✅ View own tickets only
- ✅ View time tracking (allocated/spent/remaining)
- ✅ Add replies to own tickets
- ✅ Access customer dashboard (`/support/`)
- ❌ Cannot access admin area
- ❌ Cannot view other customers' tickets
- ❌ Cannot manage tickets

## Security Features

### Automatic Redirects
- Customers trying to access admin → Redirected to `/support/`
- Agents/Admins accessing `/support/` → Redirected to their dashboard
- Prevents unauthorized access

### Role Verification
- All AJAX requests verify user role
- Database queries filter by user role
- Permission checks on all operations

### Session Management
- Uses WordPress built-in authentication
- Secure nonce verification
- Proper logout handling

## Troubleshooting

### User Not Redirected Correctly

**Problem**: User logs in but goes to wrong page

**Solutions**:
1. Check user role assignment
2. Clear browser cache
3. Log out and log back in
4. Verify redirect URLs are correct

### Customer Can Access Admin

**Problem**: Customer sees WordPress admin

**Solutions**:
1. Verify plugin is activated
2. Check customer role is set correctly
3. Clear WordPress cache
4. Check for plugin conflicts

### Redirect Loop

**Problem**: Page keeps redirecting

**Solutions**:
1. Check user has correct role
2. Verify redirect URLs exist
3. Check for conflicting redirects in other plugins
4. Clear browser cache and cookies

## Testing Checklist

- [ ] Administrator can log in → Admin Dashboard
- [ ] Support Agent can log in → Agent Dashboard
- [ ] Customer can log in → `/support/` page
- [ ] Customer cannot access `/wp-admin/` (redirected)
- [ ] Agent/Admin cannot access `/support/` (redirected)
- [ ] Login redirects work for all roles
- [ ] Users see only tickets they have permission to view
- [ ] Role-based access control works correctly

## Best Practices

1. **Always assign correct roles** when creating users
2. **Regular audits** - Check user roles periodically
3. **Test logins** - Verify redirects work for each role
4. **Security** - Never give customers admin/agent roles unnecessarily
5. **Documentation** - Keep track of user roles

## Customization

### Change Redirect URLs

Edit `includes/auth/class-auth-redirect.php`:

```php
// Change customer redirect
return home_url( '/your-custom-url/' );

// Change agent redirect
return admin_url( 'admin.php?page=your-custom-page' );
```

### Disable Auto-Redirects

Comment out redirect hooks in `class-auth-redirect.php` if you want to disable automatic redirects.

