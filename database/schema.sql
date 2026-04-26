-- Pro-Level Student Management System Database Schema
-- Created for advanced RBAC, data visualization, and automated features

-- Drop existing tables if they exist (for fresh setup)
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS attendance;
DROP TABLE IF EXISTS grades;
DROP TABLE IF EXISTS enrollments;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS departments;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS admin;

-- Users table for RBAC authentication
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
    last_login TIMESTAMP NULL,
    INDEX idx_role (role),
    INDEX idx_email (email),
    INDEX idx_username (username)
);

-- Departments table
CREATE TABLE departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(10) UNIQUE NOT NULL,
    description TEXT,
    head_of_department INT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (head_of_department) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_code (code)
);

-- Students table (linked to users)
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
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT,
    INDEX idx_student_id (student_id),
    INDEX idx_department (department_id),
    INDEX idx_status (status)
);

-- Courses table
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
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_course_code (course_code),
    INDEX idx_department (department_id),
    INDEX idx_teacher (teacher_id)
);

-- Enrollments table (students enrolled in courses)
CREATE TABLE enrollments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('enrolled', 'dropped', 'completed', 'failed') DEFAULT 'enrolled',
    final_grade DECIMAL(5,2) NULL,
    UNIQUE KEY unique_enrollment (student_id, course_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_course (course_id),
    INDEX idx_status (status)
);

-- Grades table
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
    FOREIGN KEY (graded_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_enrollment (enrollment_id),
    INDEX idx_type (assessment_type),
    INDEX idx_graded_by (graded_by)
);

-- Attendance table
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
    FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_student (student_id),
    INDEX idx_course (course_id),
    INDEX idx_date (attendance_date),
    INDEX idx_status (status)
);

-- Activity Logs table for security and auditing
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
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_table (table_name),
    INDEX idx_created (created_at)
);

-- Financial records for tuition and fees
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
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_student (student_id),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date)
);

-- Insert default admin user
INSERT INTO users (username, email, password_hash, role, first_name, last_name) VALUES 
('admin', 'admin@bdu.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System', 'Administrator');

-- Insert sample departments
INSERT INTO departments (name, code, description) VALUES 
('Computer Science', 'CS', 'Department of Computer Science and Technology'),
('Business Administration', 'BA', 'School of Business and Management'),
('Engineering', 'ENG', 'Faculty of Engineering and Technology'),
('Medicine', 'MED', 'College of Medicine and Health Sciences');

-- Insert sample courses
INSERT INTO courses (course_code, course_name, department_id, credits, description, teacher_id, semester, year_level) VALUES 
('CS101', 'Introduction to Programming', 1, 3, 'Fundamentals of programming using Python', 2, 1, 1),
('CS201', 'Data Structures', 1, 4, 'Advanced data structures and algorithms', 2, 1, 2),
('BA101', 'Business Fundamentals', 2, 3, 'Introduction to business concepts', 2, 1, 1),
('ENG101', 'Engineering Mathematics', 3, 4, 'Mathematical foundations for engineering', 2, 1, 1);

-- Create views for common queries
CREATE VIEW student_summary AS
SELECT 
    s.id,
    s.student_id,
    CONCAT(u.first_name, ' ', u.last_name) AS full_name,
    u.email,
    d.name AS department,
    s.year_level,
    s.semester,
    s.gpa,
    s.status,
    COUNT(DISTINCT e.course_id) AS enrolled_courses
FROM students s
JOIN users u ON s.user_id = u.id
JOIN departments d ON s.department_id = d.id
LEFT JOIN enrollments e ON s.id = e.student_id AND e.status = 'enrolled'
GROUP BY s.id;

CREATE VIEW course_summary AS
SELECT 
    c.id,
    c.course_code,
    c.course_name,
    d.name AS department,
    CONCAT(u.first_name, ' ', u.last_name) AS teacher_name,
    c.credits,
    COUNT(DISTINCT e.student_id) AS enrolled_students
FROM courses c
JOIN departments d ON c.department_id = d.id
JOIN users u ON c.teacher_id = u.id
LEFT JOIN enrollments e ON c.id = e.course_id AND e.status = 'enrolled'
WHERE c.is_active = TRUE
GROUP BY c.id;

CREATE VIEW attendance_summary AS
SELECT 
    s.id AS student_id,
    s.student_id AS student_number,
    CONCAT(u.first_name, ' ', u.last_name) AS student_name,
    c.course_code,
    COUNT(*) AS total_sessions,
    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_count,
    SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) AS absent_count,
    ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) AS attendance_percentage
FROM students s
JOIN users u ON s.user_id = u.id
JOIN attendance a ON s.id = a.student_id
JOIN courses c ON a.course_id = c.id
GROUP BY s.id, c.id;
