-- Database Updates for Advanced Features
-- Add tables for QR codes, class schedules, and enhanced financial tracking

-- QR Codes table for attendance tracking
CREATE TABLE IF NOT EXISTS qr_codes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    qr_data TEXT NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
);

-- Class Schedules table for conflict checking
CREATE TABLE IF NOT EXISTS class_schedules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_id INT NOT NULL,
    teacher_id INT NOT NULL,
    room_id INT NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    semester ENUM('Fall', 'Spring') NOT NULL,
    year_level INT NOT NULL,
    academic_year VARCHAR(9) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_schedule (course_id, day_of_week, start_time, end_time, semester, academic_year),
    INDEX idx_teacher_schedule (teacher_id, day_of_week, start_time, end_time),
    INDEX idx_room_schedule (room_id, day_of_week, start_time, end_time)
);

-- Rooms table for class scheduling
CREATE TABLE IF NOT EXISTS rooms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_number VARCHAR(20) UNIQUE NOT NULL,
    building VARCHAR(50) NOT NULL,
    capacity INT NOT NULL,
    room_type ENUM('Lecture', 'Lab', 'Seminar', 'Computer Lab', 'Studio') NOT NULL,
    equipment TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_room_number (room_number)
);

-- Enhanced Financial Records with receipt tracking
CREATE TABLE IF NOT EXISTS financial_receipts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    financial_record_id INT NOT NULL,
    receipt_number VARCHAR(50) UNIQUE NOT NULL,
    receipt_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_method VARCHAR(50),
    transaction_id VARCHAR(100),
    generated_by INT NOT NULL,
    pdf_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (financial_record_id) REFERENCES financial_records(id) ON DELETE CASCADE,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_receipt_number (receipt_number),
    INDEX idx_financial_record (financial_record_id)
);

-- Parent/Student Access table for portal permissions
CREATE TABLE IF NOT EXISTS parent_student_access (
    id INT PRIMARY KEY AUTO_INCREMENT,
    parent_user_id INT NOT NULL,
    student_user_id INT NOT NULL,
    relationship ENUM('Father', 'Mother', 'Guardian', 'Other') NOT NULL,
    access_level ENUM('Full', 'Limited', 'Grades Only', 'Financial Only') DEFAULT 'Limited',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_parent_student (parent_user_id, student_user_id),
    INDEX idx_parent (parent_user_id),
    INDEX idx_student (student_user_id)
);

-- Insert sample rooms
INSERT IGNORE INTO rooms (room_number, building, capacity, room_type, equipment) VALUES
('101', 'Main Building', 50, 'Lecture', 'Projector, Whiteboard'),
('102', 'Main Building', 30, 'Seminar', 'Projector, Whiteboard'),
('201', 'Science Building', 40, 'Lab', 'Lab Equipment, Safety Equipment'),
('301', 'Tech Building', 35, 'Computer Lab', 'Computers, Projector'),
('401', 'Arts Building', 25, 'Studio', 'Art Supplies, Easels');

-- Create sample class schedules
INSERT IGNORE INTO class_schedules (course_id, teacher_id, room_id, day_of_week, start_time, end_time, semester, year_level, academic_year)
SELECT 
    c.id as course_id,
    c.teacher_id,
    (SELECT id FROM rooms ORDER BY RAND() LIMIT 1) as room_id,
    CASE FLOOR(RAND() * 5) 
        WHEN 0 THEN 'Monday'
        WHEN 1 THEN 'Tuesday'
        WHEN 2 THEN 'Wednesday'
        WHEN 3 THEN 'Thursday'
        WHEN 4 THEN 'Friday'
    END as day_of_week,
    CASE FLOOR(RAND() * 4)
        WHEN 0 THEN '08:00:00'
        WHEN 1 THEN '10:00:00'
        WHEN 2 THEN '14:00:00'
        WHEN 3 THEN '16:00:00'
    END as start_time,
    CASE FLOOR(RAND() * 4)
        WHEN 0 THEN '09:30:00'
        WHEN 1 THEN '11:30:00'
        WHEN 2 THEN '15:30:00'
        WHEN 3 THEN '17:30:00'
    END as end_time,
    CASE c.semester WHEN 1 THEN 'Fall' ELSE 'Spring' END as semester,
    c.year_level,
    '2024-2025' as academic_year
FROM courses c
WHERE c.is_active = 1
LIMIT 10;

-- Update financial_records table to add receipt tracking
ALTER TABLE financial_records 
ADD COLUMN receipt_id INT NULL,
ADD FOREIGN KEY (receipt_id) REFERENCES financial_receipts(id) ON DELETE SET NULL;
