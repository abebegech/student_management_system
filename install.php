<?php
/**
 * Quick Database Installation Script
 * Run this in your browser: http://localhost/student-system/install.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$host = 'localhost';
$dbname = 'student_system';
$username = 'root';
$password = '';

try {
    // Connect to MySQL without specifying database first
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>🚀 Installing Pro-Level Student Management System</h2>";
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Database '$dbname' created or already exists.<br>";
    
    // Connect to the specific database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Drop old tables if they exist
    $oldTables = ['admin', 'students'];
    foreach ($oldTables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
        echo "✓ Dropped old table '$table'<br>";
    }
    
    // Create users table
    $pdo->exec("
        CREATE TABLE users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('student', 'teacher', 'admin') NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            phone VARCHAR(20),
            profile_photo VARCHAR(255),
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL
        )
    ");
    echo "✓ Created 'users' table<br>";
    
    // Create departments table
    $pdo->exec("
        CREATE TABLE departments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            code VARCHAR(10) UNIQUE NOT NULL,
            description TEXT,
            head_of_department INT,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✓ Created 'departments' table<br>";
    
    // Create students table
    $pdo->exec("
        CREATE TABLE students (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT UNIQUE NOT NULL,
            student_id VARCHAR(20) UNIQUE NOT NULL,
            department_id INT NOT NULL,
            year_level INT NOT NULL CHECK (year_level BETWEEN 1 AND 4),
            semester INT NOT NULL DEFAULT 1 CHECK (semester BETWEEN 1 AND 2),
            gpa DECIMAL(3,2) DEFAULT 0.00,
            admission_date DATE,
            status ENUM('active', 'inactive', 'graduated', 'suspended') DEFAULT 'active',
            emergency_contact_name VARCHAR(100),
            emergency_contact_phone VARCHAR(20),
            address TEXT,
            date_of_birth DATE,
            gender ENUM('male', 'female', 'other'),
            nationality VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT
        )
    ");
    echo "✓ Created 'students' table<br>";
    
    // Create courses table
    $pdo->exec("
        CREATE TABLE courses (
            id INT PRIMARY KEY AUTO_INCREMENT,
            course_code VARCHAR(10) UNIQUE NOT NULL,
            course_name VARCHAR(100) NOT NULL,
            department_id INT NOT NULL,
            credits INT NOT NULL CHECK (credits > 0),
            description TEXT,
            teacher_id INT NOT NULL,
            semester INT NOT NULL CHECK (semester BETWEEN 1 AND 2),
            year_level INT NOT NULL CHECK (year_level BETWEEN 1 AND 4),
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT,
            FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE RESTRICT
        )
    ");
    echo "✓ Created 'courses' table<br>";
    
    // Create enrollments table
    $pdo->exec("
        CREATE TABLE enrollments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            student_id INT NOT NULL,
            course_id INT NOT NULL,
            enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('enrolled', 'dropped', 'completed', 'failed') DEFAULT 'enrolled',
            final_grade DECIMAL(5,2) NULL,
            UNIQUE KEY unique_enrollment (student_id, course_id),
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
        )
    ");
    echo "✓ Created 'enrollments' table<br>";
    
    // Create grades table
    $pdo->exec("
        CREATE TABLE grades (
            id INT PRIMARY KEY AUTO_INCREMENT,
            enrollment_id INT NOT NULL,
            assessment_type ENUM('quiz', 'assignment', 'midterm', 'final', 'project', 'participation') NOT NULL,
            assessment_name VARCHAR(100) NOT NULL,
            score DECIMAL(5,2) NOT NULL CHECK (score >= 0 AND score <= 100),
            max_score DECIMAL(5,2) NOT NULL DEFAULT 100.00,
            weight DECIMAL(3,2) NOT NULL DEFAULT 1.00 CHECK (weight > 0),
            graded_by INT NOT NULL,
            graded_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            comments TEXT,
            FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE,
            FOREIGN KEY (graded_by) REFERENCES users(id) ON DELETE RESTRICT
        )
    ");
    echo "✓ Created 'grades' table<br>";
    
    // Create attendance table
    $pdo->exec("
        CREATE TABLE attendance (
            id INT PRIMARY KEY AUTO_INCREMENT,
            student_id INT NOT NULL,
            course_id INT NOT NULL,
            attendance_date DATE NOT NULL,
            status ENUM('present', 'absent', 'late', 'excused') NOT NULL,
            marked_by INT NOT NULL,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_attendance (student_id, course_id, attendance_date),
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
            FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE RESTRICT
        )
    ");
    echo "✓ Created 'attendance' table<br>";
    
    // Create activity_logs table
    $pdo->exec("
        CREATE TABLE activity_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            action VARCHAR(100) NOT NULL,
            table_name VARCHAR(50),
            record_id INT,
            old_values JSON,
            new_values JSON,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "✓ Created 'activity_logs' table<br>";
    
    // Create financial_records table
    $pdo->exec("
        CREATE TABLE financial_records (
            id INT PRIMARY KEY AUTO_INCREMENT,
            student_id INT NOT NULL,
            fee_type ENUM('tuition', 'lab', 'library', 'sports', 'other') NOT NULL,
            amount DECIMAL(10,2) NOT NULL CHECK (amount > 0),
            due_date DATE NOT NULL,
            paid_date DATE NULL,
            status ENUM('pending', 'paid', 'overdue', 'partial') DEFAULT 'pending',
            paid_amount DECIMAL(10,2) DEFAULT 0.00,
            payment_method VARCHAR(50),
            receipt_number VARCHAR(50),
            description TEXT,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
        )
    ");
    echo "✓ Created 'financial_records' table<br>";
    
    // Insert default admin user with password "password"
    $passwordHash = password_hash('password', PASSWORD_DEFAULT);
    $pdo->exec("
        INSERT INTO users (username, email, password_hash, role, first_name, last_name) 
        VALUES ('admin', 'admin@bdu.edu', '$passwordHash', 'admin', 'System', 'Administrator')
    ");
    echo "✓ Created default admin user (username: admin, password: password)<br>";
    
    // Insert sample departments
    $pdo->exec("
        INSERT INTO departments (name, code, description) VALUES 
        ('Computer Science', 'CS', 'Department of Computer Science and Technology'),
        ('Business Administration', 'BA', 'School of Business and Management'),
        ('Engineering', 'ENG', 'Faculty of Engineering and Technology'),
        ('Medicine', 'MED', 'College of Medicine and Health Sciences')
    ");
    echo "✓ Inserted sample departments<br>";
    
    // Insert sample courses
    $pdo->exec("
        INSERT INTO courses (course_code, course_name, department_id, credits, description, teacher_id, semester, year_level) VALUES 
        ('CS101', 'Introduction to Programming', 1, 3, 'Fundamentals of programming using Python', 1, 1, 1),
        ('CS201', 'Data Structures', 1, 4, 'Advanced data structures and algorithms', 1, 1, 2),
        ('BA101', 'Business Fundamentals', 2, 3, 'Introduction to business concepts', 1, 1, 1),
        ('ENG101', 'Engineering Mathematics', 3, 4, 'Mathematical foundations for engineering', 1, 1, 1)
    ");
    echo "✓ Inserted sample courses<br>";
    
    echo "<h3 style='color: #10B981;'>🎉 Installation completed successfully!</h3>";
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ol>";
    echo "<li>Go to <a href='login.php'>login.php</a></li>";
    echo "<li>Login with: <strong>admin</strong> / <strong>password</strong></li>";
    echo "<li>Change the default password after first login</li>";
    echo "</ol>";
    
    echo "<p style='margin-top: 20px;'><a href='login.php' style='background: #4F46E5; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login →</a></p>";
    
} catch (PDOException $e) {
    echo "<h3 style='color: #EF4444;'>❌ Installation failed</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check:</p>";
    echo "<ul>";
    echo "<li>MySQL server is running</li>";
    echo "<li>Database credentials are correct</li>";
    echo "<li>User has CREATE DATABASE permissions</li>";
    echo "</ul>";
}
?>
