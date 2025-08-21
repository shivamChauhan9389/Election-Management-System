# Hierarchical Login System - Implementation Guide

## Overview
This implementation adds a hierarchical role system where HOD users can also login as assistants, but not vice versa. Users with multiple roles will see a role selection popup after successful password verification.

## New Features

### 1. **Multiple Roles per User**
- Users can now have multiple roles assigned
- HOD users can also have assistant role
- Assistant users cannot have HOD role (maintains hierarchy)

### 2. **Role Selection Popup**
- After successful login, users with multiple roles see a popup
- Users can choose which role to login with
- Single-role users proceed directly to dashboard

### 3. **Enhanced Security**
- Role verification happens after password authentication
- Session management maintains role context
- Comprehensive logging of role selections

## Database Changes

### New Table: `user_roles`
```sql
CREATE TABLE user_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role VARCHAR(20) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_role (user_id, role)
);
```

### Migration
- Existing users are automatically migrated to the new system
- Their current role is preserved in the `user_roles` table
- No data loss occurs during migration

## Setup Instructions

### Step 1: Create Roles Table
Run the setup script to create the new table and migrate existing users:
```bash
http://localhost/Election/create_roles_table.php
```

### Step 2: Add Assistant Role to HOD Users (Optional)
To test the hierarchical functionality, add assistant role to existing HOD users:
```bash
http://localhost/Election/add_assistant_role_to_hod.php
```

### Step 3: Test the System
1. Login with an HOD user who has both roles
2. You should see the role selection popup
3. Choose between HOD or Assistant role
4. Access the system with the selected role

## New Files Created

1. **`create_roles_table.php`** - Database setup and migration
2. **`role_helper.php`** - Helper functions for role management
3. **`select_role.php`** - Role selection popup page
4. **`add_assistant_role_to_hod.php`** - Add assistant role to HOD users
5. **`logout.php`** - Enhanced logout functionality

## Modified Files

1. **`login.php`** - Added role checking logic
2. **`register.php`** - Updated to use new roles system
3. **`role_manager.php`** - Enhanced with new role functions
4. **`home/home.php`** - Added role helper inclusion

## How It Works

### Login Flow
1. User enters credentials
2. System verifies password and account status
3. **NEW**: System checks if user has multiple roles
4. **If multiple roles**: Redirect to role selection popup
5. **If single role**: Proceed directly to dashboard
6. User selects role (if applicable)
7. Session is created with selected role
8. User accesses system with chosen permissions

### Role Hierarchy
- **Admin** → Can access all roles
- **HOD** → Can access HOD, Employee, Assistant roles
- **Employee** → Can access Employee role only
- **Assistant** → Can access Assistant role only

### Security Features
- Role verification happens after password authentication
- Users can only select roles they actually have
- Session maintains role context throughout
- Comprehensive logging of all role selections

## Testing

### Test User Credentials
After running the setup, a test user is created:
- **Email**: testhod@example.com
- **Mobile**: +919999999999
- **Password**: Test123!
- **Roles**: HOD + Assistant

### Test Scenarios
1. **Single Role User**: Login should proceed directly to dashboard
2. **Multiple Role User**: Login should show role selection popup
3. **Role Switching**: User can logout and login with different role
4. **Access Control**: Role-based page access should work correctly

## Troubleshooting

### Common Issues
1. **Role selection not showing**: Check if user has multiple roles in `user_roles` table
2. **Access denied errors**: Verify role permissions in `role_manager.php`
3. **Database errors**: Ensure `user_roles` table exists and has correct structure

### Debug Steps
1. Check database connection
2. Verify `user_roles` table structure
3. Check user roles in database
4. Review error logs
5. Verify session variables

## Benefits

1. **Flexibility**: HOD users can work in both capacities
2. **Security**: Role verification maintains access control
3. **User Experience**: Clear role selection interface
4. **Audit Trail**: Complete logging of role selections
5. **Scalability**: Easy to add new roles in the future

## Future Enhancements

1. **Role Switching**: Allow users to switch roles without logout
2. **Role Permissions**: Granular permissions per role
3. **Role Templates**: Predefined role combinations
4. **Role History**: Track role usage over time
5. **Admin Interface**: Manage user roles through admin panel

## Support

For issues or questions about the hierarchical login system:
1. Check the database structure
2. Verify user roles in `user_roles` table
3. Review error logs
4. Test with the provided test user
5. Check browser console for JavaScript errors

