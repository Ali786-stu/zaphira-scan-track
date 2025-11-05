# Zaphira Attendance System - PHP Backend API

A complete PHP backend API for the Zaphira Attendance System, providing user authentication, attendance tracking, user management, and department management with MySQL database integration.

## 📋 Table of Contents

1. [Features](#features)
2. [Prerequisites](#prerequisites)
3. [Installation](#installation)
4. [Database Setup](#database-setup)
5. [Configuration](#configuration)
6. [API Documentation](#api-documentation)
7. [Security Features](#security-features)
8. [Error Handling](#error-handling)
9. [Testing](#testing)
10. [Deployment](#deployment)
11. [Troubleshooting](#troubleshooting)

## ✨ Features

### 🔐 Authentication System
- User registration and login
- Session-based authentication
- Role-based access control (Admin/Employee)
- Password hashing with bcrypt
- Activity logging
- Session timeout management

### ⏰ Attendance Management
- Daily check-in/check-out functionality
- Attendance history tracking
- Monthly attendance reports
- Duplicate check-in prevention
- Total hours calculation

### 👥 User Management (Admin Only)
- View all users with filtering and search
- Get detailed user information
- Update user profiles
- Delete users with safety checks
- User statistics and activity monitoring

### 🏢 Department Management (Admin Only)
- Create, update, and delete departments
- View department employee counts
- Department assignment to users
- Search and filter departments

### 🛡️ Security Features
- SQL injection prevention via prepared statements
- XSS protection with input sanitization
- CSRF protection considerations
- Rate limiting on sensitive operations
- CORS configuration
- Secure session management

## 📋 Prerequisites

### Required Software
- **PHP** 7.4 or higher
- **MySQL** 5.7 or higher / MariaDB 10.2 or higher
- **Apache** or **Nginx** web server
- **Composer** (for dependency management, optional)

### PHP Extensions
- `pdo_mysql` (for database connection)
- `json` (for API responses)
- `mbstring` (for string handling)
- `openssl` (for security functions)
- `session` (for session management)

## 🚀 Installation

### 1. Clone or Download
```bash
# If using git
git clone <repository-url>
cd zaphira-scan-track/api

# Or download and extract the API files to your web directory
```

### 2. Set File Permissions
```bash
# Set appropriate permissions (adjust based on your server setup)
chmod 755 .
chmod 644 *.php
chmod 600 .env
chmod 755 utils/ auth/ attendance/ users/ departments/ database/
```

### 3. Web Server Configuration

#### Apache (.htaccess included)
```apache
# Enable rewrite engine
RewriteEngine On

# Block direct access to sensitive files
<Files ".env">
    Require all denied
</Files>

<Files "database/*">
    Require all denied
</Files>

# Custom error pages
ErrorDocument 404 /api/error.php
ErrorDocument 500 /api/error.php
```

#### Nginx Configuration
```nginx
location /api {
    root /path/to/zaphira-scan-track;
    index index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Block access to sensitive files
    location ~ /\.env {
        deny all;
    }

    location ~ /database/ {
        deny all;
    }
}
```

## 🗄️ Database Setup

### 1. Create Database
```sql
CREATE DATABASE zaphira_attendance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Create Database User (Recommended)
```sql
CREATE USER 'zaphira_user'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT ALL PRIVILEGES ON zaphira_attendance.* TO 'zaphira_user'@'localhost';
FLUSH PRIVILEGES;
```

### 3. Import Schema
```bash
# Command line
mysql -u root -p zaphira_attendance < database/schema.sql

# Or using phpMyAdmin/MySQL Workbench
# 1. Open database/schema.sql
# 2. Copy and execute the SQL content
```

### 4. Verify Installation
After importing the schema, you should have:
- `departments` table with default "Administration" department
- `users` table with default admin user
- `attendance` table for tracking
- `activity_logs` table for audit trail

**Default Admin Account:**
- Email: `admin@zaphira.com`
- Password: `admin123`

⚠️ **Important:** Change the default admin password immediately after first login!

## ⚙️ Configuration

### 1. Environment Configuration
```bash
# Copy the example environment file
cp .env.example .env

# Edit the configuration
nano .env
```

### 2. Update .env Values
```bash
# Database Configuration
DB_HOST=localhost
DB_NAME=zaphira_attendance
DB_USER=zaphira_user
DB_PASS=your_strong_password

# Security Configuration
SESSION_LIFETIME=3600
CORS_ORIGIN=http://localhost:5173

# Application Settings
APP_ENV=production
DEBUG=false
```

### 3. Verify Configuration
- Ensure database credentials are correct
- Set CORS_ORIGIN to your frontend URL
- Adjust SESSION_LIFETIME as needed (default: 1 hour)

## 📚 API Documentation

### Base URL
```
Development: http://localhost/zaphira-scan-track/api
Production:  https://yourdomain.com/api
```

### Response Format
All API responses follow this format:

#### Success Response
```json
{
    "success": true,
    "message": "Operation completed successfully",
    "data": {
        // Response data here
    },
    "pagination": {  // Optional for paginated responses
        "total": 100,
        "limit": 20,
        "offset": 0
    }
}
```

#### Error Response
```json
{
    "success": false,
    "error": "Error message description",
    "error_code": "ERROR_CODE"
}
```

### Authentication Endpoints

#### POST /auth/login.php
Login user and create session.

**Request Body:**
```json
{
    "email": "user@example.com",
    "password": "userpassword"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "role": "employee",
            "department_id": 1,
            "department_name": "Engineering"
        },
        "session_info": {
            "session_id": "abc123...",
            "login_time": "2024-01-15 09:00:00"
        }
    }
}
```

#### POST /auth/register.php
Register new user account.

**Request Body:**
```json
{
    "name": "Jane Smith",
    "email": "jane@example.com",
    "password": "password123",
    "role": "employee",
    "department_id": 1
}
```

#### GET /auth/check-session.php
Validate current session and return user data.

**Headers:**
```
Cookie: PHPSESSID=your_session_id
```

#### GET /auth/logout.php
Destroy user session and logout.

### Attendance Endpoints

#### POST /attendance/checkin.php
Record employee check-in (requires authentication).

**Headers:**
```
Cookie: PHPSESSID=your_session_id
```

#### POST /attendance/checkout.php
Record employee check-out (requires authentication).

#### GET /attendance/viewSelf.php
Get personal attendance history (requires authentication).

**Query Parameters:**
- `month` (optional): Filter by month (1-12)
- `year` (optional): Filter by year
- `limit` (optional): Number of records (default: 30)
- `offset` (optional): Pagination offset

#### GET /attendance/viewAll.php
Get all attendance records (admin only).

**Query Parameters:**
- `user_id` (optional): Filter by user
- `department_id` (optional): Filter by department
- `status` (optional): Filter by status (complete/checked_in/absent)
- `search` (optional): Search by user name/email
- `limit` (optional): Number of records (default: 50)

### User Management Endpoints

#### GET /users/list.php
List all users (admin only).

**Query Parameters:**
- `department_id` (optional): Filter by department
- `role` (optional): Filter by role (admin/employee)
- `search` (optional): Search by name/email
- `status` (optional): Filter by activity status
- `limit` (optional): Number of records (default: 50)

#### GET /users/getSingle.php
Get single user details.

**Query Parameters:**
- `id` (required): User ID

#### POST /users/update.php
Update user information.

**Request Body:**
```json
{
    "id": 1,
    "name": "Updated Name",
    "email": "updated@example.com",
    "role": "admin",
    "department_id": 2,
    "password": "newpassword123"
}
```

#### DELETE /users/delete.php
Delete user (admin only).

**Query Parameters:**
- `id` (required): User ID

### Department Management Endpoints

#### GET /departments/list.php
List all departments.

**Query Parameters:**
- `search` (optional): Search by department name
- `include_count` (optional): Include employee counts (true/false)

#### POST /departments/create.php
Create new department (admin only).

**Request Body:**
```json
{
    "name": "Marketing"
}
```

#### POST /departments/update.php
Update department (admin only).

**Request Body:**
```json
{
    "id": 1,
    "name": "Updated Department Name"
}
```

#### DELETE /departments/delete.php
Delete department (admin only).

**Query Parameters:**
- `id` (required): Department ID

## 🛡️ Security Features

### Input Validation
- All inputs are sanitized using `htmlspecialchars()`
- Email validation with `filter_var()`
- Length validation for string inputs
- Required field validation

### Password Security
- Passwords hashed using `password_hash()` with bcrypt (cost 12)
- Password strength requirements (8+ chars, letters + numbers)
- Secure password change workflow

### Session Security
- Secure session configuration (httpOnly, secure, samesite)
- Session fixation protection
- Session timeout management
- Session regeneration on login

### Database Security
- Prepared statements for all SQL queries
- SQL injection prevention
- Connection error handling
- Transaction support for data integrity

### Rate Limiting
- Login attempts: 5 per minute per IP
- Registration attempts: 3 per hour per IP
- Check-in/out: 10 per hour per user
- User updates: 10 per 5 minutes per user

### CORS Configuration
- Configurable origin whitelist
- Proper headers for frontend integration
- Options request handling

## 🚨 Error Handling

### HTTP Status Codes
- `200`: Success
- `400`: Bad Request (validation errors)
- `401`: Unauthorized (not logged in)
- `403`: Forbidden (insufficient permissions)
- `404`: Not Found
- `429`: Too Many Requests (rate limit exceeded)
- `500`: Internal Server Error
- `503`: Service Unavailable

### Common Error Codes
- `VALIDATION_ERROR`: Input validation failed
- `AUTH_REQUIRED`: Authentication required
- `INSUFFICIENT_PERMISSIONS`: Role-based access denied
- `USER_NOT_FOUND`: User does not exist
- `EMAIL_EXISTS`: Email already registered
- `INVALID_CREDENTIALS`: Wrong email/password
- `SESSION_EXPIRED`: Session timeout
- `RATE_LIMIT_EXCEEDED`: Too many requests

### Error Logging
All errors are logged to PHP error log with detailed information for debugging. In production, set `DEBUG=false` to prevent sensitive information leakage.

## 🧪 Testing

### Manual Testing Checklist

#### Authentication Tests
1. ✅ User registration with valid data
2. ✅ User registration with duplicate email
3. ✅ User login with correct credentials
4. ✅ User login with wrong credentials
5. ✅ Session validation
6. ✅ User logout
7. ✅ Session timeout

#### Attendance Tests
1. ✅ Employee check-in
2. ✅ Duplicate check-in prevention
3. ✅ Employee check-out
4. ✅ Check-out without check-in
5. ✅ Personal attendance history
6. ✅ Admin view all attendance
7. ✅ Attendance filtering and search

#### User Management Tests
1. ✅ List all users (admin)
2. ✅ Get single user details
3. ✅ Update user profile
4. ✅ Change user password
5. ✅ Role assignment (admin)
6. ✅ User deletion with safety checks

#### Department Tests
1. ✅ List departments
2. ✅ Create new department
3. ✅ Update department
4. ✅ Delete department (no users)
5. ✅ Prevent deletion with assigned users

### API Testing with curl

#### Test Login
```bash
curl -X POST http://localhost/api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@zaphira.com","password":"admin123"}' \
  -c cookies.txt
```

#### Test Check-in
```bash
curl -X POST http://localhost/api/attendance/checkin.php \
  -H "Content-Type: application/json" \
  -b cookies.txt
```

#### Test Get Users
```bash
curl -X GET "http://localhost/api/users/list.php?limit=10" \
  -b cookies.txt
```

## 🚀 Deployment

### Production Setup

#### 1. Server Requirements
- PHP 8.0+ recommended
- MySQL 8.0+ or MariaDB 10.5+
- SSL certificate (HTTPS)
- Firewall configuration

#### 2. Security Hardening
```bash
# Set secure file permissions
find . -type f -name "*.php" -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod 600 .env

# Hide .env from web access
echo "Deny from all" > .htaccess
```

#### 3. Environment Configuration
```bash
# Production .env settings
APP_ENV=production
DEBUG=false
CORS_ORIGIN=https://yourdomain.com
SESSION_LIFETIME=7200
```

#### 4. Web Server Configuration
- Enable HTTPS
- Configure security headers
- Set up log rotation
- Enable PHP error logging
- Configure backup systems

#### 5. Database Security
- Use dedicated database user
- Limit database privileges
- Enable binary logging
- Set up regular backups
- Monitor query performance

### Backup Strategy

#### Database Backup
```bash
# Daily backup script
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u backup_user -p zaphira_attendance > backup_$DATE.sql
gzip backup_$DATE.sql
```

#### File Backup
```bash
# Backup application files
tar -czf api_backup_$DATE.tar.gz .
```

## 🔧 Troubleshooting

### Common Issues

#### 1. Database Connection Errors
```
Error: "Database connection failed"
```
**Solutions:**
- Check database credentials in .env
- Verify database server is running
- Ensure database user has proper privileges
- Check firewall settings

#### 2. Session Issues
```
Error: "Session expired" or "No active session"
```
**Solutions:**
- Check session.save_path in php.ini
- Verify folder permissions for session storage
- Clear browser cookies
- Check session lifetime settings

#### 3. CORS Errors
```
Error: "CORS policy: No 'Access-Control-Allow-Origin' header"
```
**Solutions:**
- Update CORS_ORIGIN in .env
- Check if OPTIONS requests are handled
- Verify frontend URL is correct

#### 4. Permission Denied Errors
```
Error: "Access denied. Insufficient permissions"
```
**Solutions:**
- Check user role in database
- Verify middleware is properly included
- Ensure user is logged in

#### 5. 404 Errors
```
Error: "Not Found" or "Method not allowed"
```
**Solutions:**
- Check URL spelling and case
- Verify HTTP method (GET/POST/DELETE)
- Check web server rewrite rules
- Ensure files exist and are readable

### Debug Mode
Enable debug mode for detailed error information:

```bash
# In .env
DEBUG=true
APP_ENV=development
```

**⚠️ Warning:** Never enable debug mode in production!

### Log Locations
- PHP Error Log: `/var/log/php_errors.log` or system log
- Web Server Log: `/var/log/apache2/error.log` or `/var/log/nginx/error.log`
- MySQL Log: `/var/log/mysql/error.log`

### Performance Optimization

#### Database Indexes
Ensure proper indexes are created:
```sql
-- Check existing indexes
SHOW INDEX FROM users;
SHOW INDEX FROM attendance;

-- Add missing indexes if needed
CREATE INDEX idx_user_email ON users(email);
CREATE INDEX idx_attendance_user_date ON attendance(user_id, date);
```

#### PHP Configuration
```ini
# Recommended PHP settings for production
memory_limit = 256M
max_execution_time = 30
max_input_time = 60
upload_max_filesize = 10M
post_max_size = 12M
```

## 📞 Support

For issues and support:
1. Check the troubleshooting section above
2. Review error logs for detailed information
3. Verify all configuration settings
4. Test with a clean installation if needed

## 📄 License

This project is part of the Zaphira Attendance System. Please refer to the main project license for usage terms.

---

**Last Updated:** January 2024
**Version:** 1.0.0
**Compatibility:** PHP 7.4+, MySQL 5.7+