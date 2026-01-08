# Cron Job and Notification System Setup

## Overview

This document describes the automatic process checking and browser notification system for the sprint dashboard.

## Components

### 1. Cron Job Script
- **Location**: `/var/www/html/cron/check-executing-prompts.php`
- **Purpose**: Automatically checks the status of executing prompts every minute
- **Features**:
  - Queries all prompts with 'executing' status
  - Calls the check-coder API for each prompt
  - Updates database with results (test-ready or failed)
  - Creates notification records for browser alerts

### 2. Database Schema
- **prompt_notifications table**: Tracks pending notifications
  - `prompt_id`: Link to the prompt
  - `status`: completed or failed
  - `notified`: Boolean flag
  - `notified_at`: Timestamp when notified
- **users table additions**:
  - `notification_enabled`: User preference for notifications
  - `notification_sound`: User preference for notification sounds

### 3. Browser Notification System
- **Location**: JavaScript in `sprint-dashboard.php`
- **Features**:
  - Requests notification permission on first visit
  - Polls for new notifications every 30 seconds
  - Shows browser notifications when prompts complete
  - Plays optional sound alert
  - Clicking notification scrolls to the prompt

### 4. Automatic Status Updates
- **Polling System**: JavaScript polls every 15 seconds for executing prompts
- **Visual Feedback**: Shows real-time status of executing prompts
- **Auto-refresh**: Page refreshes when prompts complete

## Setup Instructions

### 1. Install Cron Job

Add the following line to your crontab:

```bash
# Edit crontab
crontab -e

# Add this line (runs every minute)
* * * * * /usr/bin/php /var/www/html/cron/check-executing-prompts.php >> /var/log/check-executing-prompts.log 2>&1
```

### 2. Create Log File

```bash
# Create log file with proper permissions
sudo touch /var/log/check-executing-prompts.log
sudo chown www-data:www-data /var/log/check-executing-prompts.log
```

### 3. Test Cron Job

```bash
# Run manually to test
php /var/www/html/cron/check-executing-prompts.php
```

## How It Works

1. **User sends prompt to Dev System**
   - Prompt status set to 'executing'
   - PID and temp file stored in database

2. **Cron job runs every minute**
   - Checks all executing prompts
   - Calls check-coder API for status
   - Updates database with results
   - Creates notification records

3. **Browser polls for notifications**
   - Every 30 seconds, checks for new notifications
   - Shows browser notification with prompt details
   - Marks notification as read

4. **Real-time status updates**
   - Sprint dashboard shows executing prompts
   - Updates every 15 seconds
   - Auto-refreshes when prompts complete

## API Endpoints

### Check Notifications
- **URL**: `/htmx/check-notifications.php`
- **Method**: GET
- **Returns**: JSON with pending notifications

### Mark Notification Read
- **URL**: `/htmx/mark-notification-read.php`
- **Method**: POST
- **Parameters**: notification_id

### Check Executing Prompts
- **URL**: `/htmx/check-executing-prompts.php`
- **Method**: GET
- **Parameters**: sprint_id
- **Returns**: HTML status summary

## Troubleshooting

### Cron Not Running
1. Check cron service: `sudo service cron status`
2. Check cron logs: `grep CRON /var/log/syslog`
3. Verify script permissions: `ls -la /var/www/html/cron/check-executing-prompts.php`

### Notifications Not Showing
1. Check browser permissions for notifications
2. Ensure HTTPS is enabled (notifications require secure context)
3. Check browser console for errors
4. Verify notification_enabled is TRUE in users table

### Database Errors
1. Run migration: `php /var/www/html/migrations/061_create_prompt_notifications_table.sql`
2. Check table exists: `SHOW TABLES LIKE 'prompt_notifications';`
3. Verify columns exist: `DESCRIBE prompt_notifications;`

## Security Considerations

1. Cron job runs with web server permissions
2. API key is hardcoded (consider environment variable)
3. Notifications only sent to project managers/owners
4. All endpoints require authentication