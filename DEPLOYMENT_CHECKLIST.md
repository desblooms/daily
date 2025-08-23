# Production Deployment Checklist

## Pre-Deployment Tasks

### ✅ 1. Code Cleanup
- [x] Remove all test files (test-*.php, test-*.html, debug-*.php)
- [x] Remove backup files (*-old.php, *-backup.php, *-debug.php)
- [x] Remove demo and sample files
- [x] Clean up unused includes and emergency fixes

### ✅ 2. Configuration Setup
- [x] Create production configuration file (config.php)
- [x] Disable debug mode in API files
- [x] Set proper error reporting (errors logged, not displayed)
- [x] Configure database connection for production

### ✅ 3. Function Dependencies
- [x] Move getEnhancedAnalytics function to includes/functions.php
- [x] Verify all required functions are available
- [x] Test API endpoints for functionality

## Database Setup

### 🔲 4. Database Schema Migration
- [ ] Run `database/calendar.sql` (base schema)
- [ ] Run `database/task_enhancements.sql` (enhanced features)
- [ ] Run `database/campaign_schema.sql` (campaign features)  
- [ ] Run `database/campaign_media_schema.sql` (media features)
- [ ] Verify all tables created successfully
- [ ] Check foreign key constraints are working

### 🔲 5. Database Optimization
- [ ] Add proper indexes for performance
- [ ] Set up database backups
- [ ] Configure database maintenance tasks

## File System Setup

### 🔲 6. Directory Structure
- [ ] Create `uploads/` directory with proper permissions (755)
- [ ] Create `uploads/campaigns/` subdirectory
- [ ] Create `uploads/tasks/` subdirectory  
- [ ] Create `uploads/attachments/` subdirectory
- [ ] Create `logs/` directory for application logs
- [ ] Set proper file permissions for web server

### 🔲 7. Security Configuration
- [ ] Set `.htaccess` files to protect sensitive directories
- [ ] Configure file upload restrictions
- [ ] Set up proper session handling
- [ ] Configure HTTPS (SSL certificate)

## Production Testing

### 🔲 8. Core Functionality Testing
- [ ] Test user login/logout
- [ ] Test task creation and management
- [ ] Test file upload functionality
- [ ] Test admin dashboard features
- [ ] Test user dashboard features
- [ ] Test API endpoints with actual data

### 🔲 9. Enhanced Features Testing
- [ ] Test enhanced task manager JavaScript
- [ ] Test work output sharing
- [ ] Test file attachments and thumbnails
- [ ] Test progress tracking
- [ ] Test notifications system

### 🔲 10. Performance Testing
- [ ] Test with multiple concurrent users
- [ ] Verify database query performance
- [ ] Check file upload/download speeds
- [ ] Monitor memory usage and server load

## Security Verification

### 🔲 11. Security Checklist
- [ ] Verify input sanitization in all forms
- [ ] Test SQL injection protection
- [ ] Test file upload security (type/size restrictions)
- [ ] Verify user authentication and authorization
- [ ] Test session management and timeout
- [ ] Check for XSS vulnerabilities

## Final Steps

### 🔲 12. Production Deployment
- [ ] Create database backup before deployment
- [ ] Deploy files to production server
- [ ] Update database configuration for production
- [ ] Test all functionality on production environment
- [ ] Set up monitoring and logging
- [ ] Create admin user accounts
- [ ] Document any production-specific configurations

### 🔲 13. Post-Deployment
- [ ] Monitor application logs for errors
- [ ] Verify all features work as expected
- [ ] Check performance metrics
- [ ] Set up automated backups
- [ ] Create maintenance schedule
- [ ] Document rollback procedures

## Required Files for Production

### Core System Files
- `index.php` - Main dashboard
- `login.php` - Authentication
- `task.php` - Task management page
- `profile.php` - User profile management
- `admin-dashboard.php` - Original admin interface
- `enhanced-admin-dashboard.php` - Enhanced admin interface  
- `enhanced-user-dashboard.php` - Enhanced user interface

### API Files
- `api/tasks.php` - Task management API
- `api/users.php` - User management API
- `api/attachments.php` - File attachment API
- `api/notifications.php` - Notifications API
- `api/analytics.php` - Analytics data API

### Include Files
- `includes/db.php` - Database connection
- `includes/auth.php` - Authentication functions
- `includes/functions.php` - Core application functions
- `includes/tasks.php` - Task-specific functions

### Asset Files
- `assets/css/tailwind.css` - Styling
- `assets/js/app.js` - Main JavaScript
- `assets/js/admin.js` - Admin JavaScript
- `assets/js/enhanced-task-manager.js` - Enhanced task management
- `assets/js/global-task-manager.js` - Global task functions

### Database Files
- `database/calendar.sql` - Base schema
- `database/task_enhancements.sql` - Enhanced features schema
- `database/campaign_schema.sql` - Campaign features
- `database/campaign_media_schema.sql` - Media features

### Configuration Files
- `config.php` - Production configuration
- `.htaccess` - Web server configuration (to be created)

## Environment Variables (if applicable)
```env
DB_HOST=localhost
DB_NAME=u345095192_dailycalendar
DB_USER=u345095192_dailycalendar
DB_PASS=Daily@788
APP_ENV=production
DEBUG_MODE=false
LOG_LEVEL=error
```

## Notes
1. Ensure PHP 7.4+ is installed with required extensions (PDO, GD, etc.)
2. Configure proper file permissions on the server
3. Set up regular database backups
4. Monitor disk space for uploads directory
5. Consider implementing caching for better performance
6. Set up log rotation to prevent log files from growing too large