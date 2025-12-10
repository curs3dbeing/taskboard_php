# Task Planner - Fullstack PHP Application

A full-featured task planning application built with PHP, MySQL, HTML, CSS, and JavaScript. Users can register, login, manage their tasks, and recover forgotten passwords via email.

## Features

- **User Authentication**
  - Registration with validation (username length, email format, password strength)
  - Login with username or email
  - Password hashing using PHP's `password_hash()`
  - Session management

- **Password Recovery**
  - Forgot password functionality
  - Email-based password reset with secure tokens
  - Token expiration (1 hour)

- **Task Management (CRUD)**
  - Create new tasks with name and description
  - View all your tasks in a beautiful grid layout
  - Edit existing tasks
  - Delete tasks with confirmation
  - Tasks are user-specific (users can only see their own tasks)

- **Responsive Design**
  - Modern, clean UI
  - Fully responsive for mobile, tablet, and desktop
  - Smooth animations and transitions

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher (or MariaDB)
- Web server (Apache/Nginx)
- PHP extensions: PDO, PDO_MySQL
- Mail server configured (for password recovery)

## Installation

1. **Clone or download the project** to your web server directory (e.g., `htdocs`, `www`, or `public_html`)

2. **Create the database:**
   ```bash
   mysql -u root -p < database.sql
   ```
   Or import `database.sql` using phpMyAdmin or your preferred MySQL client.

3. **Configure the database connection:**
   Edit `config.php` and update these constants:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'task_planner');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   ```

4. **Configure email settings (for password recovery):**
   Edit `config.php` and update SMTP settings:
   ```php
   define('SMTP_HOST', 'smtp.gmail.com');
   define('SMTP_PORT', 587);
   define('SMTP_USER', 'your-email@gmail.com');
   define('SMTP_PASS', 'your-app-password');
   define('SMTP_FROM_EMAIL', 'your-email@gmail.com');
   define('SMTP_FROM_NAME', 'Task Planner');
   ```
   
   **Note:** For Gmail, you'll need to:
   - Enable 2-factor authentication
   - Generate an App Password (not your regular password)
   - Use that App Password in `SMTP_PASS`

5. **Configure site URL:**
   Edit `config.php` and update:
   ```php
   define('SITE_URL', 'http://localhost/course');
   ```
   Change this to match your actual domain/path.

6. **Set proper file permissions:**
   ```bash
   chmod 755 *.php
   ```

## Usage

1. **Access the application:**
   Navigate to `http://localhost/course` (or your configured path)

2. **Register a new account:**
   - Click "Register here" on the login page
   - Fill in username (3-50 characters), email, and password (minimum 8 characters)
   - Click "Register"

3. **Login:**
   - Use your username or email and password
   - You'll be redirected to your dashboard

4. **Manage tasks:**
   - Click "+ New Task" to create a task
   - Click the edit icon to modify a task
   - Click the delete icon to remove a task
   - All tasks are displayed in a responsive grid

5. **Password recovery:**
   - Click "Forgot your password?" on the login page
   - Enter your email address
   - Check your email for the reset link
   - Click the link and set a new password

## File Structure

```
course/
├── config.php              # Database and application configuration
├── database.sql            # Database schema
├── index.php              # Entry point (redirects to login/dashboard)
├── login.php              # Login page
├── register.php           # Registration page
├── logout.php             # Logout handler
├── forgot_password.php    # Password recovery request
├── reset_password.php     # Password reset form
├── dashboard.php          # Main task management page
├── save_task.php          # Create/update task handler
├── delete_task.php        # Delete task handler
├── styles.css             # All CSS styles
├── script.js              # JavaScript functionality
└── README.md              # This file
```

## Security Features

- Password hashing using `password_hash()` with bcrypt
- Prepared statements to prevent SQL injection
- Session-based authentication
- User-specific task access (users can only see their own tasks)
- CSRF protection through session tokens (can be enhanced)
- Input validation and sanitization
- Secure password reset tokens with expiration

## Customization

### Password Requirements
Edit `config.php`:
```php
define('MIN_PASSWORD_LENGTH', 8);
define('MIN_USERNAME_LENGTH', 3);
define('MAX_USERNAME_LENGTH', 50);
```

### Session Lifetime
Edit `config.php`:
```php
define('SESSION_LIFETIME', 3600); // seconds
```

### Styling
Modify `styles.css` to change colors, fonts, and layout. CSS variables are defined at the top for easy customization.

## Troubleshooting

**Database connection error:**
- Check database credentials in `config.php`
- Ensure MySQL service is running
- Verify database exists

**Email not sending:**
- Check SMTP settings in `config.php`
- For Gmail, ensure App Password is used (not regular password)
- Check PHP `mail()` function is enabled
- Consider using PHPMailer library for better email support

**Session issues:**
- Ensure PHP sessions are enabled
- Check file permissions
- Verify `session_start()` is called

## License

This project is open source and available for educational purposes.

## Notes

- For production use, consider:
  - Using PHPMailer or similar library for better email delivery
  - Adding CSRF tokens to forms
  - Implementing rate limiting
  - Using HTTPS
  - Adding more comprehensive input validation
  - Implementing proper error logging

