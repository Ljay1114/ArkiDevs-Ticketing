# Arkidevs Support Plugin - User Guide

## How the System Works

This guide explains how to use the ticketing system from both Admin and Customer perspectives.

---

## 👨‍💼 ADMIN PERSPECTIVE

As an Administrator, you have full control over the entire support system.

### 1. **Accessing the Admin Dashboard**

After logging in as an admin:
- Go to **Support** in the WordPress admin menu
- You'll see the **Admin Dashboard** with statistics:
  - Total Tickets
  - Open Tickets
  - In Progress Tickets
  - Closed Tickets

### 2. **Managing Tickets (Agent View)**

Click **"View All Tickets"** or go to **Support > Tickets** to access the Agent Dashboard.

**Ticket List View:**
- **Tabs** at the top filter tickets by status:
  - **Open**: Newly created tickets waiting to be assigned
  - **In Progress**: Tickets currently being worked on
  - **Closed**: Resolved tickets

**Each ticket shows:**
- Ticket number (e.g., #TKT-ABC123-20240101)
- Subject line
- Priority badge (Low, Medium, High, Critical)
- Status badge (Open, In Progress, Closed, etc.)
- Customer name
- Time since creation

### 3. **Viewing and Managing a Single Ticket**

Click on any ticket to open the detailed view.

**Ticket Details Page includes:**
- Full ticket description
- Customer information
- Current status and priority
- All replies/communication history
- Time tracking information

**Actions you can take:**
1. **Change Status**: Use the dropdown to update status (Open → In Progress → Closed)
2. **Change Priority**: Adjust priority level based on urgency
3. **Time Tracking**: 
   - Click **"Start Timer"** when you begin working on the ticket
   - Click **"Stop Timer"** when you're done (time is automatically logged)
4. **Add Replies**: Respond to the customer directly from the ticket
5. **Close Ticket**: Mark the ticket as resolved

### 4. **Time Tracking System**

**How it works:**
- When you start a timer, it begins tracking time
- When you stop the timer, the time is:
  - Recorded in the time log
  - Deducted from the customer's allocated hours
  - Displayed on the ticket

**Time is displayed as:**
- Hours and minutes (e.g., "2.5 hrs" or "30 min")

### 5. **Allocating Hours to Customers**

Admins can allocate support hours to customers (this requires custom admin interface extension or can be done programmatically).

**Example workflow:**
1. Identify which customer needs hours
2. Allocate hours (e.g., 10 hours)
3. As agents work on tickets, hours are automatically deducted
4. Customer can see remaining hours in their dashboard

### 6. **Email Notifications**

The system automatically sends emails when:
- A new ticket is created
- Ticket status changes
- A reply is added to a ticket
- Ticket is closed

---

## 👤 CUSTOMER PERSPECTIVE

As a Customer, you can create tickets, view your tickets, and track your support hours.

### 1. **Accessing Your Dashboard**

1. Log in to your WordPress account
2. Navigate to the **Customer Dashboard** page (created with the `[arkidevs_customer_dashboard]` shortcode)
3. You'll see:
   - Your time tracking summary
   - Option to create new tickets
   - List of all your tickets

### 2. **Time Tracking Summary**

At the top of your dashboard, you'll see three boxes:

**Hours Allocated:**
- Total hours you've been granted for support

**Hours Spent:**
- Hours already used by agents working on your tickets
- Automatically updated when agents start/stop timers

**Hours Remaining:**
- Available hours you have left
- Calculated as: Allocated - Spent

**Example:**
- Allocated: 20.00 hours
- Spent: 5.50 hours
- Remaining: 14.50 hours

### 3. **Creating a New Ticket**

**Step-by-step:**
1. Click the **"Create Ticket"** button
2. A form appears with:
   - **Subject**: Brief description of your issue
   - **Priority**: Select from Low, Medium, High, or Critical
     - **Low**: Non-urgent issues
     - **Medium**: Normal priority (default)
     - **High**: Important issues
     - **Critical**: Urgent issues requiring immediate attention
   - **Description**: Detailed explanation of your issue
3. Fill in all fields and click **"Submit Ticket"**
4. You'll receive a confirmation email with your ticket number

### 4. **Viewing Your Tickets**

Your tickets are displayed in a table showing:
- **Ticket #**: Unique ticket number
- **Subject**: What the ticket is about
- **Status**: Current status (Open, In Progress, Closed, etc.)
- **Priority**: Priority level
- **Created**: When you created the ticket
- **Actions**: "View" button to see details

### 5. **Viewing a Single Ticket**

Click **"View"** on any ticket to see full details.

**Ticket View includes:**
- Ticket number and subject
- Current status and priority badges
- **Time Spent**: How many hours agents have worked on this ticket
- Full description
- **Replies Section**: All communication between you and support
  - Shows who wrote each reply (Agent or You)
  - Timestamp for each reply

**Adding a Reply:**
1. If the ticket is not closed, you'll see a reply form
2. Type your message in the textarea
3. Click **"Submit Reply"**
4. Your reply is added to the conversation
5. Support agents are notified via email

### 6. **Ticket Statuses Explained**

**Open**: 
- Ticket has been created and is waiting for an agent to pick it up

**In Progress**: 
- An agent is actively working on your ticket

**Pending**: 
- Waiting for your response or additional information

**Closed**: 
- Ticket has been resolved and closed
- You can still view it but cannot add new replies
- Create a new ticket if you have follow-up questions

**Resolved**: 
- Issue is resolved but ticket is not yet closed

### 7. **Email Notifications**

You'll automatically receive emails when:
- ✅ Your ticket is created (with ticket number)
- ✅ Ticket status changes
- ✅ An agent replies to your ticket
- ✅ Your ticket is closed

**Email includes:**
- Ticket number and subject
- Current status
- Link to view the ticket in your dashboard

### 8. **Creating Tickets via Email**

You can also create tickets by sending an email to **support@arkidevs.com** (requires IMAP setup).

**How it works:**
- Send an email with your issue description
- Subject line becomes the ticket subject
- Email body becomes the ticket description
- System automatically:
  - Creates a ticket
  - Associates it with your account (based on your email)
  - Sends you a confirmation email with ticket number

**Priority hints in email subject:**
- Use `[Critical]` or "urgent" for critical priority
- Use `[High]` for high priority
- Use `[Low]` for low priority
- Default is Medium if no priority indicator

---

## 🔄 Complete Workflow Example

### Scenario: Customer Reports a Bug

1. **Customer Action:**
   - Logs into customer dashboard
   - Clicks "Create Ticket"
   - Fills in: Subject: "Login button not working", Priority: High, Description: "When I click login, nothing happens..."
   - Submits ticket
   - Receives email confirmation with ticket #TKT-XYZ789

2. **System Action:**
   - Ticket is created with status "Open"
   - Ticket appears in agent dashboard
   - Customer hours summary remains unchanged (no time spent yet)

3. **Agent Action:**
   - Sees new ticket in "Open" tab
   - Clicks on ticket to view details
   - Changes status to "In Progress"
   - Clicks "Start Timer" to begin tracking time
   - Reviews the issue, tests the login button
   - Adds reply: "I've identified the issue. Working on a fix now."
   - Stops timer (2 hours logged)

4. **System Action:**
   - 2 hours deducted from customer's allocated hours
   - Customer's "Hours Spent" increases by 2
   - "Hours Remaining" decreases by 2
   - Customer receives email notification about the reply

5. **Customer Action:**
   - Receives email about agent reply
   - Logs into dashboard, views ticket
   - Sees agent's reply and notices 2 hours have been spent
   - Sees ticket status is now "In Progress"
   - Can reply if needed

6. **Agent Action (Continued):**
   - Fixes the bug
   - Adds final reply: "Issue has been fixed. Please test and let me know if it works."
   - Changes status to "Pending" (waiting for customer confirmation)

7. **Customer Action:**
   - Tests the fix
   - Confirms it works
   - Adds reply: "Yes, it's working now. Thank you!"

8. **Agent Action:**
   - Changes status to "Resolved"
   - Clicks "Close Ticket"
   - Customer receives email notification

9. **Final State:**
   - Ticket status: Closed
   - Total time spent: 2 hours
   - Customer hours updated accordingly
   - Both can view ticket history for reference

---

## 📊 Key Features Summary

### For Admins/Agents:
- ✅ View all tickets across all customers
- ✅ Filter tickets by status (Open, In Progress, Closed)
- ✅ Update ticket status and priority
- ✅ Track time spent on each ticket
- ✅ Add replies to tickets
- ✅ Assign tickets to agents
- ✅ Close/resolve tickets
- ✅ View comprehensive statistics

### For Customers:
- ✅ Create support tickets
- ✅ View all your tickets
- ✅ Track support hours (allocated, spent, remaining)
- ✅ See time spent on each ticket
- ✅ Add replies to open tickets
- ✅ View ticket history
- ✅ Receive email notifications
- ✅ Create tickets via email or dashboard

---

## 💡 Tips

**For Admins:**
- Always start the timer when working on a ticket to accurately track time
- Remember to stop the timer when done
- Update ticket status regularly so customers know the progress
- Use priority levels to organize work efficiently

**For Customers:**
- Provide detailed descriptions when creating tickets
- Choose appropriate priority levels (don't mark everything as Critical)
- Check your remaining hours regularly
- Reply promptly when agents ask questions
- Create new tickets for new issues (don't reopen closed tickets)

---

## 🔒 Security & Permissions

- **Customers** can only see and manage their own tickets
- **Agents** can see all tickets but can only track time for tickets they work on
- **Admins** have full access to everything
- All actions are logged and tracked
- Email notifications are sent automatically for transparency


