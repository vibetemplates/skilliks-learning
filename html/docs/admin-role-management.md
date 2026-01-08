# Admin Role Management Guide

## Overview
The SkillikS platform includes functionality for global administrators to grant or revoke admin privileges to other users. This feature is available in the Admin panel under User Management.

## Accessing Role Management
1. Navigate to **Admin > User Management** (requires global admin access)
2. You'll see a list of all users with their current roles

## Granting Admin Access
To make another user a global administrator:

1. Find the user in the user list
2. Look for the "Global Role" column
3. Click the green **Grant** button next to users who are currently "Regular User"
4. A modal will appear asking for an optional reason
5. Enter a reason (recommended for audit trail) and click **Grant Access**

## Revoking Admin Access
To remove admin privileges:

1. Find the admin user in the user list
2. Look for the "Global Role" column showing the red "Global Admin" badge
3. Click the red **Revoke** button
4. A modal will appear asking for an optional reason
5. Enter a reason and click **Revoke Access**

## Important Notes
- You cannot change your own admin status (prevents accidental lockout)
- All role changes are logged with timestamp and the admin who made the change
- The reason field helps maintain an audit trail

## Role Capabilities

### Global Admin
- Full access to Admin panel
- Can manage all users across all communities
- Can grant/revoke admin privileges
- Can manage system-wide settings
- Can view all projects, courses, and activities

### Regular User
- Standard platform access
- Can join communities and projects
- Cannot access Admin panel
- Cannot modify other users' roles

## Database Structure
The system uses two methods to track admin status:
1. `users.global_role` field (set to 'admin' or 'user')
2. `global_admins` table for audit trail (tracks who granted access and when)

## Security Features
- Only existing global admins can grant admin access
- All changes are logged for security auditing
- Self-modification protection prevents accidental lockouts
- Role changes take effect immediately