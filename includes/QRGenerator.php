<?php
/**
 * QR Code Generator for Student Management System
 * Generates unique QR codes for students and attendance tracking
 */

class QRGenerator {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Generate unique QR code data for a student
     */
    public function generateStudentQRCode($studentId) {
        try {
            // Get student information
            $student = $this->db->fetch("
                SELECT s.student_id, s.user_id, u.first_name, u.last_name, d.name as department
                FROM students s
                JOIN users u ON s.user_id = u.id
                JOIN departments d ON s.department_id = d.id
                WHERE s.id = :student_id
            ", ['student_id' => $studentId]);
            
            if (!$student) {
                throw new Exception("Student not found");
            }
            
            // Generate unique QR data with timestamp and random token
            $qrData = [
                'type' => 'student_attendance',
                'student_id' => $student['student_id'],
                'user_id' => $student['user_id'],
                'name' => $student['first_name'] . ' ' . $student['last_name'],
                'department' => $student['department'],
                'timestamp' => time(),
                'token' => bin2hex(random_bytes(16)),
                'expires' => time() + 3600 // QR code expires in 1 hour
            ];
            
            // Save QR data to database for validation
            $this->saveQRData($student['user_id'], $qrData);
            
            return base64_encode(json_encode($qrData));
            
        } catch (Exception $e) {
            throw new Exception("Failed to generate QR code: " . $e->getMessage());
        }
    }
    
    /**
     * Generate QR code for course attendance
     */
    public function generateCourseQRCode($courseId, $date = null) {
        try {
            $date = $date ?? date('Y-m-d');
            
            // Get course information
            $course = $this->db->fetch("
                SELECT c.course_code, c.course_name, d.name as department
                FROM courses c
                JOIN departments d ON c.department_id = d.id
                WHERE c.id = :course_id
            ", ['course_id' => $courseId]);
            
            if (!$course) {
                throw new Exception("Course not found");
            }
            
            // Generate unique QR data for course attendance
            $qrData = [
                'type' => 'course_attendance',
                'course_id' => $courseId,
                'course_code' => $course['course_code'],
                'course_name' => $course['course_name'],
                'department' => $course['department'],
                'date' => $date,
                'timestamp' => time(),
                'token' => bin2hex(random_bytes(16)),
                'expires' => time() + 7200 // QR code expires in 2 hours
            ];
            
            // Save QR data to database
            $this->saveQRData(null, $qrData);
            
            return base64_encode(json_encode($qrData));
            
        } catch (Exception $e) {
            throw new Exception("Failed to generate course QR code: " . $e->getMessage());
        }
    }
    
    /**
     * Validate QR code data
     */
    public function validateQRCode($qrData) {
        try {
            $decoded = json_decode(base64_decode($qrData), true);
            
            if (!$decoded || !isset($decoded['token'])) {
                throw new Exception("Invalid QR code format");
            }
            
            // Check if QR code has expired
            if (isset($decoded['expires']) && time() > $decoded['expires']) {
                throw new Exception("QR code has expired");
            }
            
            // Validate against database
            $storedData = $this->getStoredQRData($decoded['token']);
            
            if (!$storedData) {
                throw new Exception("QR code not found in database");
            }
            
            // Compare stored data with provided data
            $storedDecoded = json_decode($storedData['qr_data'], true);
            
            if ($storedDecoded['timestamp'] !== $decoded['timestamp'] || 
                $storedDecoded['token'] !== $decoded['token']) {
                throw new Exception("QR code validation failed");
            }
            
            return $decoded;
            
        } catch (Exception $e) {
            throw new Exception("QR code validation failed: " . $e->getMessage());
        }
    }
    
    /**
     * Mark attendance using QR code
     */
    public function markAttendanceByQR($qrData, $courseId, $markedBy) {
        try {
            $validatedData = $this->validateQRCode($qrData);
            
            if ($validatedData['type'] !== 'student_attendance') {
                throw new Exception("Invalid QR code type for attendance");
            }
            
            // Get student ID from QR data
            $student = $this->db->fetch("
                SELECT id FROM students WHERE user_id = :user_id
            ", ['user_id' => $validatedData['user_id']]);
            
            if (!$student) {
                throw new Exception("Student not found");
            }
            
            // Check if student is enrolled in the course
            $enrollment = $this->db->fetch("
                SELECT id FROM enrollments 
                WHERE student_id = :student_id AND course_id = :course_id AND status = 'enrolled'
            ", ['student_id' => $student['id'], 'course_id' => $courseId]);
            
            if (!$enrollment) {
                throw new Exception("Student is not enrolled in this course");
            }
            
            // Check if attendance already marked for today
            $today = date('Y-m-d');
            $existing = $this->db->fetch("
                SELECT id FROM attendance 
                WHERE student_id = :student_id AND course_id = :course_id AND attendance_date = :date
            ", ['student_id' => $student['id'], 'course_id' => $courseId, 'date' => $today]);
            
            if ($existing) {
                throw new Exception("Attendance already marked for today");
            }
            
            // Mark attendance as present
            $attendanceData = [
                'student_id' => $student['id'],
                'course_id' => $courseId,
                'attendance_date' => $today,
                'status' => 'present',
                'marked_by' => $markedBy,
                'notes' => 'Marked via QR code scan'
            ];
            
            $sql = "INSERT INTO attendance (student_id, course_id, attendance_date, status, marked_by, notes) 
                    VALUES (:student_id, :course_id, :attendance_date, :status, :marked_by, :notes)";
            
            $attendanceId = $this->db->insert($sql, $attendanceData);
            
            // Log the activity
            $auth = new Auth();
            $auth->logActivity($markedBy, 'mark_attendance_qr', 'attendance', $attendanceId, null, $attendanceData);
            
            return [
                'success' => true,
                'message' => 'Attendance marked successfully',
                'student_name' => $validatedData['name'],
                'student_id' => $validatedData['student_id']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Save QR data to database
     */
    private function saveQRData($userId, $qrData) {
        try {
            $sql = "INSERT INTO qr_codes (user_id, qr_data, token, expires_at, created_at) 
                    VALUES (:user_id, :qr_data, :token, :expires_at, CURRENT_TIMESTAMP)";
            
            $params = [
                'user_id' => $userId,
                'qr_data' => json_encode($qrData),
                'token' => $qrData['token'],
                'expires_at' => date('Y-m-d H:i:s', $qrData['expires'])
            ];
            
            $this->db->insert($sql, $params);
            
        } catch (Exception $e) {
            throw new Exception("Failed to save QR data: " . $e->getMessage());
        }
    }
    
    /**
     * Get stored QR data
     */
    private function getStoredQRData($token) {
        try {
            $sql = "SELECT qr_data FROM qr_codes WHERE token = :token AND expires_at > CURRENT_TIMESTAMP";
            return $this->db->fetch($sql, ['token' => $token]);
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Clean up expired QR codes
     */
    public function cleanupExpiredQRCodes() {
        try {
            $sql = "DELETE FROM qr_codes WHERE expires_at < CURRENT_TIMESTAMP";
            return $this->db->delete($sql);
        } catch (Exception $e) {
            return 0;
        }
    }
}
?>
