# Pro-Level Student Management System

A comprehensive, role-based student management system with advanced features including real-time analytics, PDF generation, and automated workflows.

## 🚀 Features

### 🔐 Advanced Authentication & RBAC
- **Single Login Page**: Redirects users based on their role (Student/Teacher/Admin)
- **Role-Based Access Control**: Granular permissions for each user type
- **Secure Password Hashing**: Uses PHP's password_hash() function
- **Activity Logging**: Complete audit trail of all user actions

### 📊 Real-Time Data Visualization
- **Chart.js Integration**: Beautiful, interactive charts
- **Attendance Trends**: Line charts showing attendance patterns
- **Grade Distribution**: Bar charts for class performance
- **Department Analytics**: Pie charts for student distribution
- **Live Statistics**: Real-time dashboard updates

### 📄 Automated Document Generation
- **Student ID Cards**: Professional ID cards with QR codes
- **Academic Transcripts**: Complete academic records with GPA calculations
- **Course Reports**: Detailed performance analytics
- **Financial Reports**: Tuition and fee summaries
- **PDF Export**: High-quality PDF generation using Dompdf

### 🔍 Smart Search & Filtering
- **AJAX-Powered Search**: Instant results without page reload
- **Multi-Field Search**: Search by name, ID, email, course code
- **Advanced Filters**: Filter by department, year, status, role
- **Live Pagination**: Smooth navigation through large datasets

### 🛡️ Security & Data Integrity
- **PDO Prepared Statements**: SQL injection protection
- **Activity Logging**: Track all database changes
- **Database Backup**: One-click backup and restore functionality
- **Session Security**: Secure session management
- **Input Validation**: Comprehensive data sanitization

## 🏗️ Architecture

### Database Design
- **Foreign Key Relationships**: Data integrity enforcement
- **Normalized Schema**: Efficient data storage
- **Audit Tables**: Complete activity tracking
- **Backup Support**: Easy data export/import

### Role-Based Views
- **Student Dashboard**: Grades, attendance, course materials
- **Teacher Dashboard**: Grade input, attendance, analytics
- **Admin Dashboard**: User management, system logs, financial oversight

## 📋 System Requirements

- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher
- **Web Server**: Apache or Nginx
- **Extensions**: PDO, MySQL, GD, JSON, mbstring
- **Composer**: For dependency management

## 🚀 Installation

### 1. Clone/Download the Project
```bash
git clone <repository-url>
cd student-system
```

### 2. Install Dependencies
```bash
composer install
```

### 3. Database Setup
1. Create a MySQL database named `student_system`
2. Run the database setup script:
   ```bash
   http://localhost/student-system/database/setup.php
   ```
3. Or manually import the schema:
   ```bash
   mysql -u root -p student_system < database/schema.sql
   ```

### 4. Configure Database
Edit the database connection settings in `includes/Database.php` if needed:
```php
private $host = 'localhost';
private $dbname = 'student_system';
private $username = 'root';
private $password = '';
```

### 5. Set File Permissions
```bash
chmod -R 755 assets/
chmod -R 755 uploads/
chmod -R 755 assets/pdf/
chmod -R 755 assets/backups/
```

### 6. Access the System
- **Login URL**: `http://localhost/student-system/login.php`
- **Default Admin**: 
  - Username: `admin`
  - Password: `password`

## 🎯 Quick Start

### For Admins
1. Login with default admin credentials
2. Navigate to **User Management** to create teachers and students
3. Set up departments and courses
4. Monitor system activity through the dashboard

### For Teachers
1. Login with assigned credentials
2. View assigned courses and student lists
3. Mark attendance and input grades
4. Generate performance reports

### For Students
1. Login with assigned credentials
2. View grades and attendance records
3. Access course materials
4. Download academic transcripts

## 📁 Project Structure

```
student-system/
├── admin/                  # Admin-specific pages
│   ├── dashboard.php      # Admin dashboard
│   ├── backup.php         # Database backup/restore
│   └── ...
├── student/               # Student-specific pages
│   ├── dashboard.php      # Student dashboard
│   └── ...
├── teacher/               # Teacher-specific pages
│   ├── dashboard.php      # Teacher dashboard
│   └── ...
├── api/                   # API endpoints
│   └── search.php         # AJAX search API
├── includes/              # Core classes
│   ├── Auth.php          # Authentication & RBAC
│   ├── Database.php      # Database connection
│   └── PDFGenerator.php  # PDF generation
├── database/              # Database files
│   ├── schema.sql        # Database schema
│   └── setup.php         # Setup script
├── assets/                # Static assets
│   ├── pdf/              # Generated PDFs
│   ├── backups/          # Database backups
│   └── reports/          # System reports
├── css/                   # Stylesheets
├── js/                    # JavaScript files
├── uploads/               # File uploads
├── login.php             # Unified login page
├── logout.php            # Logout handler
└── README.md             # This file
```

## 🔧 Configuration

### Database Configuration
Update database settings in `includes/Database.php`:
```php
private $host = 'your_host';
private $dbname = 'your_database';
private $username = 'your_username';
private $password = 'your_password';
```

### Email Configuration (Optional)
For email notifications, configure PHPMailer in your scripts:
```php
$mail->Host = 'smtp.example.com';
$mail->Port = 587;
$mail->Username = 'your_email@example.com';
$mail->Password = 'your_password';
```

## 🎨 Customization

### Adding New Roles
1. Update the `role` ENUM in the `users` table
2. Modify the `Auth.php` permissions array
3. Create role-specific dashboard directories
4. Update the login redirect logic

### Custom PDF Templates
Edit the HTML templates in `includes/PDFGenerator.php`:
- `getIDCardTemplate()` - Student ID cards
- `getTranscriptTemplate()` - Academic transcripts
- `getCourseReportTemplate()` - Course reports
- `getFinancialReportTemplate()` - Financial reports

### Adding New Charts
Add Chart.js configurations to dashboard files:
```javascript
new Chart(ctx, {
    type: 'chart-type',
    data: { /* your data */ },
    options: { /* chart options */ }
});
```

## 🔒 Security Considerations

1. **Change Default Passwords**: Update the default admin password
2. **Database Security**: Use strong database credentials
3. **File Permissions**: Restrict access to sensitive directories
4. **HTTPS**: Enable SSL in production
5. **Regular Backups**: Schedule automatic database backups
6. **Input Validation**: All inputs are sanitized using PDO

## 📊 API Documentation

### Search API
**Endpoint**: `GET /api/search.php`
**Parameters**:
- `q` (string): Search query
- `type` (string): Search type (students, courses, users, grades)
- `filters` (array): Additional filters
- `page` (int): Page number
- `limit` (int): Results per page

**Example**:
```javascript
fetch('/api/search.php?q=john&type=students&filters[department]=CS')
    .then(response => response.json())
    .then(data => console.log(data));
```

## 🐛 Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Check MySQL server is running
   - Verify database credentials
   - Ensure database exists

2. **PDF Generation Fails**
   - Install Composer dependencies
   - Check write permissions for assets/pdf/
   - Verify Dompdf is installed

3. **File Upload Issues**
   - Check upload directory permissions
   - Verify PHP upload limits
   - Ensure file size limits are appropriate

4. **Login Redirects Not Working**
   - Check session configuration
   - Verify role assignments in database
   - Ensure proper file permissions

### Error Logging
Check PHP error logs for detailed debugging:
```bash
tail -f /var/log/php_errors.log
```

## 🔄 Updates & Maintenance

### Regular Maintenance Tasks
1. **Database Backups**: Weekly automated backups
2. **Log Cleanup**: Archive old activity logs
3. **File Cleanup**: Remove temporary files
4. **Security Updates**: Keep dependencies updated

### Version Updates
1. Backup current system
2. Update database schema if needed
3. Replace files with new versions
4. Run any migration scripts
5. Test functionality

## 📞 Support

For issues and questions:
1. Check the troubleshooting section
2. Review the error logs
3. Verify database structure
4. Test with default configurations

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

---

**Note**: This is a comprehensive student management system designed for educational institutions. Always ensure proper data privacy and security measures are in place when handling student information.
