# User Authentication System

A complete PHP-based user authentication system with email/SMS OTP verification, password reset functionality, and comprehensive logging.

## 🚀 Features

- **Secure User Registration** with CAPTCHA protection
- **Email/SMS OTP Verification** for account activation
- **Login with Email or Phone Number**
- **Password Reset** with OTP verification
- **Admin Dashboard** for system monitoring
- **Comprehensive Logging** of all user activities
- **Modern Dark UI** with responsive design
- **AJAX-powered OTP Resend** functionality
- **Security Features**: CAPTCHA, password hashing, SQL injection prevention

## 📋 Requirements

- **PHP 7.4+**
- **MySQL 5.7+** or **MariaDB 10.2+**
- **Apache/Nginx** web server
- **Composer** (for dependency management)
- **Gmail Account** (for email sending)
- **Twilio Account** (for SMS sending)

### Required PHP Extensions
- `mysqli`
- `gd` (for CAPTCHA)
- `mbstring`
- `openssl`
- `curl`

## 🛠 Installation & Setup

### Step 1: Download & Extract
1. Download or clone this project to your web server directory
2. For XAMPP: Extract to `C:\xampp\htdocs\login\`
3. For other servers: Extract to your web root directory

### Step 2: Install Dependencies
```bash
# Navigate to project directory
cd /path/to/your/project

# Install PHP dependencies
composer install
```

### Step 3: Database Setup
1. **Create Database:**
```sql
CREATE DATABASE user_auth CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. **Create Tables:**
```sql
USE user_auth;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(100) NOT NULL,
    email VARCHAR(100) NULL,
    mobile VARCHAR(15) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    is_verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_mobile (mobile),
    INDEX idx_is_verified (is_verified)
);

-- OTP reset table
CREATE TABLE otp_reset (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    otp_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
);

-- System logs table
CREATE TABLE system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NOT NULL,
    user_id INT NULL,
    action_type VARCHAR(50) NOT NULL DEFAULT 'INFO',
    action_description TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'INFO',
    session_id VARCHAR(128) NULL,
    user_agent TEXT NULL,
    request_method VARCHAR(10) NULL,
    request_uri VARCHAR(255) NULL,
    additional_data JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_timestamp (timestamp),
    INDEX idx_ip_address (ip_address),
    INDEX idx_user_id (user_id),
    INDEX idx_action_type (action_type),
    INDEX idx_status (status)
);

--password--
CREATE TABLE password_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### Step 4: Configure Application (database, mail, SMS)
1. Copy the example config and create your local config file (not committed):
```bash
cp config.example.php config_local.php
```

2. Open `config_local.php` and set your values:
```php
return [
    'db' => [
        'host' => 'localhost',
        'username' => 'root',
        'password' => '',
        'database' => 'user_auth',
        'charset' => 'utf8',
    ],
    'mail' => [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => 'your-email@gmail.com',
        'password' => 'your-app-password',
        'from_email' => 'your-email@gmail.com',
        'from_name' => 'Election',
        'encryption' => 'tls',
    ],
    'twilio' => [
        'account_sid' => 'your_twilio_account_sid',
        'auth_token' => 'your_twilio_auth_token',
        'from_number' => '+1234567890',
    ],
    'admin' => [
        'password' => 'change_this_admin_password',
    ],
];
```

### Step 5: Configure Email Settings
Set the `mail` section in `config_local.php` (no edits in `send_email.php` needed). The app reads SMTP settings from the config file.

**Getting Gmail App Password:**
1. Enable 2-Factor Authentication on your Gmail account
2. Go to Google Account Settings → Security → App Passwords
3. Generate a new app password for "Mail"
4. Use this 16-character password in the code

### Step 6: Configure SMS Settings
Set the `twilio` section in `config_local.php` (no edits in `send_sms.php` needed). The app reads Twilio settings from the config file.

**Getting Twilio Credentials:**
1. Sign up at [twilio.com](https://www.twilio.com)
2. Get a phone number from Twilio Console
3. Find your Account SID and Auth Token in the dashboard

## 🎯 Usage

### For Users

1. **Registration:**
   - Go to `register.php`
   - Fill in full name, phone number, and password
   - Email is optional
   - Choose OTP delivery method (email or SMS)
   - Complete CAPTCHA and submit

2. **OTP Verification:**
   - Enter the 6-digit OTP received via email/SMS
   - Use "Resend OTP" if needed

3. **Login:**
   - Go to `login.php`
   - Enter email or phone number
   - Enter password and complete CAPTCHA

4. **Password Reset:**
   - Click "Forgot Password" on login page
   - Enter email or phone number
   - Verify OTP and set new password

### For Administrators

1. **Admin Dashboard:**
   - Go to `admin_logs.php`
   - Admin password is configured in `config_local.php` under `admin.password`
   - View system logs, filter by user, action type, etc.

## 📁 File Structure

```
login/
├── login.php                      # Login page
├── register.php                   # Registration page  
├── home.php                       # Protected home page
├── logout.php                     # Logout functionality
├── verify-otp.php                 # OTP verification for registration
├── verify.php                     # Resend verification OTP
├── forgot-password.php            # Password reset form
├── handle_forgot_password.php     # Password reset processing
├── verify-password-otp.php        # Password reset OTP verification  
├── reset-password-form.php        # New password form
├── resend_otp_ajax.php            # AJAX OTP resend handler
├── resend_password_otp_ajax.php   # AJAX password reset OTP resend
├── admin_logs.php                 # Admin dashboard
├── db.php                         # Database connection
├── logger.php                     # Logging functions
├── send_email.php                 # Email sending functionality
├── send_sms.php                   # SMS sending functionality  
├── captcha.php                    # CAPTCHA generation
├── style.css                      # CSS styling
├── config.example.php             # Example configuration (copy to config_local.php)
├── images/
│   └── logo.png                   # Logo image
├── composer.json                  # PHP dependencies
├── composer.lock                  # Locked dependency versions
└── vendor/                        # Composer packages
```

## 🔧 Configuration

### Security Settings

1. **Admin Password**: Set in `config_local.php` under `admin.password`. Do not commit real credentials.

2. **Session Security** (add to `db.php`):
```php
// Secure session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
```

### Customization

1. **App Name/Title**: Edit HTML titles and email templates
2. **Logo**: Replace `images/logo.png` with your logo
3. **Styling**: Modify `style.css` for custom design
4. **Email Templates**: Edit email content in `send_email.php`
5. **SMS Templates**: Edit SMS content in `send_sms.php`

## 🔍 How It Works

### Registration Flow
1. User fills registration form with validation
2. System checks if user already exists
3. CAPTCHA verification
4. Database transaction begins
5. User record created (unverified)
6. OTP generated and stored (hashed)
7. OTP sent via email/SMS
8. If sending fails, transaction rolls back (no orphaned accounts)
9. User redirected to OTP verification page

### Login Flow  
1. User enters email/phone and password
2. CAPTCHA verification
3. System finds user by email OR phone
4. Password verification with `password_verify()`
5. Check if account is verified
6. Session variables set on success
7. All attempts logged for security

### Password Reset Flow
1. User enters email/phone number
2. CAPTCHA verification  
3. System verifies account exists and is verified
4. OTP generated and sent
5. User enters OTP for verification
6. New password form shown
7. Password updated with proper hashing

### Logging System
- All user actions logged to database
- Includes IP address, user agent, timestamps
- Failed attempts tracked for security
- Admin dashboard for monitoring

## 🛡 Security Features

1. **Password Security**: 
   - Passwords hashed with `password_hash()`
   - Minimum length and complexity requirements

2. **SQL Injection Prevention**:
   - All database queries use prepared statements
   - Input sanitization with `filter_input()`

3. **CAPTCHA Protection**:
   - Prevents automated bot attacks
   - Required for registration, login, and password reset

4. **Session Security**:
   - Secure session management
   - Session regeneration on login

5. **Input Validation**:
   - Server-side validation for all inputs
   - Email format validation
   - Phone number format validation

6. **Comprehensive Logging**:
   - All actions logged for audit trail
   - Failed attempt monitoring
   - IP address tracking

## 🚨 Troubleshooting

### Common Issues

1. **"Connection failed" Error:**
   - Check database credentials in `db.php`
   - Ensure MySQL service is running
   - Verify database name exists

2. **"Class 'PHPMailer' not found":**
   ```bash
   composer install
   ```

3. **CAPTCHA not showing:**
   - Ensure GD extension is enabled: `php -m | grep -i gd`
   - Check file permissions for CAPTCHA image generation

4. **Email not sending:**
   - Verify Gmail credentials
   - Check if "Less secure app access" is enabled (not recommended)
   - Use Gmail App Password instead

5. **SMS not sending:**
   - Verify Twilio credentials
   - Check Twilio account balance
   - Ensure phone number format is correct (+91xxxxxxxxxx)

### Database Issues

1. **Reset Database:**
```sql
DROP DATABASE user_auth;
CREATE DATABASE user_auth CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- Then run the table creation queries again
```

2. **Clear All Data:**
```sql
TRUNCATE TABLE system_logs;
TRUNCATE TABLE otp_reset;  
TRUNCATE TABLE users;
```

## 📊 Admin Functions

### View System Statistics
The admin dashboard provides:
- Total logs count
- Recent activity (24 hours)
- Success/failure ratios
- User activity monitoring

### Filter Logs
Filter by:
- Action type (LOGIN, REGISTRATION, etc.)
- Status (SUCCESS, FAILURE, WARNING, INFO)
- User ID
- IP address
- Time range

### Security Monitoring
- Failed login attempts by IP
- Suspicious activity detection
- User verification status
- Session tracking

## 🔄 Maintenance

### Regular Tasks

1. **Clean Old Logs** (run monthly):
```sql
DELETE FROM system_logs WHERE timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

2. **Remove Expired OTPs** (run daily):
```sql
DELETE FROM otp_reset WHERE expires_at < NOW();
```

3. **Remove Unverified Users** (run weekly):
```sql
DELETE FROM users WHERE is_verified = 0 AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
```

## 🎨 Customization Guide

### Changing Colors/Theme
Edit `style.css`:
```css
/* Main colors */
body { background-color: #000; }           /* Background */
.form-container { background-color: #121212; } /* Form background */
button { background-color: #385185; }      /* Button color */
```

### Adding New Fields
1. Add to registration form in `register.php`
2. Update database table structure
3. Modify validation logic
4. Update insert queries

### Custom Email Templates
Edit `send_email.php`:
```php
$mail->Subject = 'Your Custom Subject';
$mail->Body = 'Your custom HTML email template with OTP: ' . $otp;
```

## 📱 Mobile Responsiveness

The system is mobile-friendly with:
- Responsive design that works on all devices
- Touch-friendly buttons and inputs
- Optimized forms for mobile keyboards
- Fast loading on slower connections

## 🔐 Production Deployment

### Security Checklist
- [ ] Change admin password
- [ ] Enable HTTPS
- [ ] Set secure session settings
- [ ] Configure proper file permissions
- [ ] Enable PHP error logging (disable display)
- [ ] Set up regular database backups
- [ ] Configure firewall rules
- [ ] Update all credentials from defaults

### Performance Optimization
- [ ] Enable PHP OPcache
- [ ] Configure MySQL for production
- [ ] Set up CDN for static assets
- [ ] Enable gzip compression
- [ ] Optimize images

## 📞 Support

For issues or questions:
1. Check the troubleshooting section above
2. Verify all configuration settings
3. Check server error logs
4. Ensure all dependencies are installed

## 📄 License

This project is open-source and available for educational and commercial use.

## 👥 Team Collaboration Guide

### Team Workflow & Branching Strategy

This project uses a **Git Flow** approach to ensure safe collaboration among team members:

#### Branch Structure
```
main (production-ready code)
├── develop (integration branch)
├── feature/feature-name (new features)
├── hotfix/issue-description (urgent fixes)
└── release/version-number (release preparation)
```

#### For Team Members

1. **Clone the Repository**
```bash
git clone https://github.com/7-dante-7/Election.git
cd Election
git checkout develop  # Switch to develop branch
```

2. **Creating a New Feature**
```bash
# Create and switch to feature branch
git checkout develop
git pull origin develop
git checkout -b feature/your-feature-name

# Work on your feature...
# Make commits with descriptive messages
git add .
git commit -m "Add user profile management feature"

# Push your feature branch
git push origin feature/your-feature-name
```

3. **Pull Request Process**
- Create a Pull Request from your feature branch to `develop`
- Add descriptive title and description
- Request review from at least 2 team members
- Wait for approval before merging
- Delete feature branch after merging

#### Branch Protection Rules (Recommended)
- **main**: Require PR reviews, no direct pushes
- **develop**: Require PR reviews from 1+ team members
- **feature branches**: Free for individual development

### 🔧 Development Environment Setup

#### Prerequisites for All Team Members
```bash
# Required software
- PHP 7.4+ with extensions: mysqli, gd, mbstring, openssl, curl
- MySQL 5.7+ or MariaDB 10.2+
- Apache/Nginx web server
- Composer for dependency management
- Git for version control
```

#### Local Setup Steps
1. **Clone and Setup**
```bash
git clone https://github.com/7-dante-7/Election.git
cd Election
composer install
```

2. **Database Configuration**
```sql
-- Each developer should use their own database
CREATE DATABASE user_auth_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- Run the table creation queries from README
```

3. **Environment Configuration**
```php
// Create db_config_local.php (add to .gitignore)
$host = "localhost";
$username = "your_local_db_user";
$password = "your_local_db_password";  
$database = "user_auth_dev";  // Each dev has own DB
```

### 📋 Code Standards & Best Practices

#### Naming Conventions
- **Files**: `snake_case.php` (e.g., `handle_forgot_password.php`)
- **Functions**: `camelCase()` (e.g., `sendOtpEmail()`)
- **Variables**: `$snake_case` (e.g., `$user_id`)
- **Classes**: `PascalCase` (e.g., `UserManager`)
- **Constants**: `UPPER_SNAKE_CASE` (e.g., `MAX_LOGIN_ATTEMPTS`)

#### Commit Message Format
```
type(scope): brief description

feat(auth): add two-factor authentication
fix(login): resolve session timeout issue
docs(readme): update installation instructions
style(css): improve mobile responsiveness
refactor(db): optimize database queries
test(unit): add user registration tests
```

#### Code Review Checklist
- [ ] Code follows project naming conventions
- [ ] No hardcoded credentials or sensitive data
- [ ] Proper error handling and logging
- [ ] SQL injection prevention (prepared statements)
- [ ] Input validation and sanitization
- [ ] Comments for complex logic
- [ ] No debug code left in production

### 🚀 Deployment Workflow

#### Development → Staging → Production

1. **Development**
   - Work on feature branches
   - Merge to `develop` via PR
   - Test on local environment

2. **Staging** (develop branch)
   - Integration testing
   - Team review and testing
   - Bug fixes merged here

3. **Production** (main branch)
   - Create release branch from develop
   - Final testing and version tagging
   - Merge to main after approval

### 🔒 Security Guidelines for Team

#### Credential Management
```bash
# NEVER commit actual credentials
# Use environment variables or config files in .gitignore

# Example: .env file (add to .gitignore)
DB_HOST=localhost
DB_USER=your_user
DB_PASS=your_password
TWILIO_SID=your_twilio_sid
TWILIO_TOKEN=your_twilio_token
GMAIL_USER=your_email@gmail.com
GMAIL_PASS=your_app_password
```

#### .gitignore Additions
```gitignore
# Environment files
.env
db_config_local.php
config_local.php
config_production.php

# Development files
*.log
.DS_Store
Thumbs.db

# IDE files
.vscode/
.idea/
*.swp
*.swo
vendor/
```

### 🧪 Testing Strategy

#### Manual Testing Checklist
- [ ] Registration with email/SMS verification
- [ ] Login with email and phone number
- [ ] Password reset functionality
- [ ] CAPTCHA verification
- [ ] Admin dashboard access
- [ ] Mobile responsiveness
- [ ] Cross-browser compatibility

#### Automated Testing (Future Enhancement)
```bash
# PHPUnit setup for unit tests
composer require --dev phpunit/phpunit
mkdir tests
```

### 📞 Communication & Issue Management

#### Daily Workflow
1. **Morning Sync** (15 mins)
   - What did you work on yesterday?
   - What will you work on today?
   - Any blockers or dependencies?

2. **Code Reviews**
   - Review PRs within 24 hours
   - Provide constructive feedback
   - Test the changes locally when needed

3. **Issue Tracking**
   - Use GitHub Issues for bug reports
   - Use GitHub Projects for feature planning
   - Label issues: `bug`, `feature`, `enhancement`, `documentation`

#### Emergency Hotfix Process
```bash
# For critical production bugs
git checkout main
git pull origin main
git checkout -b hotfix/critical-bug-description

# Fix the issue
git add .
git commit -m "hotfix: resolve critical security vulnerability"
git push origin hotfix/critical-bug-description

# Create PR to main AND develop
# Get immediate review and merge
```

### 🔄 Database Migration Strategy

#### Schema Changes
```sql
-- Always create migration scripts
-- migrations/001_add_user_profile_table.sql
CREATE TABLE user_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    avatar_url VARCHAR(255),
    bio TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

#### Version Control for DB
- Keep migration scripts in `migrations/` folder
- Document all schema changes
- Test migrations on development data
- Backup production before applying migrations

### 📊 Monitoring & Maintenance

#### Code Quality Tools
```bash
# PHP CodeSniffer for code standards
composer require --dev squizlabs/php_codesniffer

# PHP Mess Detector for code quality
composer require --dev phpmd/phpmd
```

#### Performance Monitoring
- Monitor database query performance
- Track user registration/login success rates
- Monitor email/SMS delivery rates
- Log and analyze error patterns

### 🆘 Troubleshooting Common Issues

#### Merge Conflicts
```bash
# When conflicts occur
git checkout develop
git pull origin develop
git checkout your-feature-branch
git rebase develop

# Resolve conflicts manually
git add .
git rebase --continue
git push --force-with-lease origin your-feature-branch
```

#### Database Sync Issues
```bash
# Reset local database
mysql -u root -p
DROP DATABASE user_auth_dev;
CREATE DATABASE user_auth_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
# Re-run table creation queries
```

#### Composer Dependencies
```bash
# If vendor issues occur
rm -rf vendor composer.lock
composer install
```

### 📝 Documentation Responsibilities

#### Each team member should maintain:
- [ ] Code comments for complex functions
- [ ] API documentation for new endpoints
- [ ] Update README for new features
- [ ] Create/update user guides
- [ ] Document configuration changes

---

**Made with ❤️ by our awesome team of 4 developers!** 🚀
