<?php
/**
 * PDF Generation Class for Student Management System
 * Handles ID cards, transcripts, and report generation using Dompdf
 */

require_once 'Database.php';

class PDFGenerator {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Generate Student ID Card
     */
    public function generateStudentIDCard($studentId) {
        try {
            // Get student information
            $student = $this->db->fetch("
                SELECT s.*, u.first_name, u.last_name, u.email, d.name as department_name,
                       d.code as department_code
                FROM students s
                JOIN users u ON s.user_id = u.id
                JOIN departments d ON s.department_id = d.id
                WHERE s.id = :student_id
            ", ['student_id' => $studentId]);
            
            if (!$student) {
                throw new Exception("Student not found");
            }
            
            // Generate QR code data
            $qrData = json_encode([
                'student_id' => $student['student_id'],
                'name' => $student['first_name'] . ' ' . $student['last_name'],
                'department' => $student['department_name'],
                'year' => $student['year_level'],
                'valid_until' => date('Y-m-d', strtotime('+1 year'))
            ]);
            
            $html = $this->getIDCardTemplate($student, $qrData);
            
            return $this->generatePDF($html, 'student_id_card_' . $student['student_id']);
            
        } catch (Exception $e) {
            throw new Exception("Failed to generate ID card: " . $e->getMessage());
        }
    }
    
    /**
     * Generate Student Transcript
     */
    public function generateStudentTranscript($studentId) {
        try {
            // Get student information
            $student = $this->db->fetch("
                SELECT s.*, u.first_name, u.last_name, u.email, d.name as department_name
                FROM students s
                JOIN users u ON s.user_id = u.id
                JOIN departments d ON s.department_id = d.id
                WHERE s.id = :student_id
            ", ['student_id' => $studentId]);
            
            if (!$student) {
                throw new Exception("Student not found");
            }
            
            // Get course enrollments and grades
            $courses = $this->db->fetchAll("
                SELECT c.course_code, c.course_name, c.credits, e.final_grade,
                       e.enrollment_date, e.status as enrollment_status,
                       AVG(g.score) as avg_score, COUNT(g.id) as grade_count
                FROM enrollments e
                JOIN courses c ON e.course_id = c.id
                LEFT JOIN grades g ON e.id = g.enrollment_id
                WHERE e.student_id = :student_id
                GROUP BY e.id, c.id
                ORDER BY e.enrollment_date
            ", ['student_id' => $studentId]);
            
            // Calculate GPA and statistics
            $totalCredits = 0;
            $weightedGPA = 0;
            $completedCourses = 0;
            
            foreach ($courses as $course) {
                if ($course['enrollment_status'] === 'completed' && $course['final_grade'] !== null) {
                    $totalCredits += $course['credits'];
                    $weightedGPA += $course['final_grade'] * $course['credits'];
                    $completedCourses++;
                }
            }
            
            $calculatedGPA = $totalCredits > 0 ? round($weightedGPA / $totalCredits, 2) : 0;
            
            $html = $this->getTranscriptTemplate($student, $courses, [
                'total_credits' => $totalCredits,
                'gpa' => $calculatedGPA,
                'completed_courses' => $completedCourses,
                'generated_date' => date('F j, Y')
            ]);
            
            return $this->generatePDF($html, 'transcript_' . $student['student_id']);
            
        } catch (Exception $e) {
            throw new Exception("Failed to generate transcript: " . $e->getMessage());
        }
    }
    
    /**
     * Generate Course Report
     */
    public function generateCourseReport($courseId, $reportType = 'summary') {
        try {
            // Get course information
            $course = $this->db->fetch("
                SELECT c.*, d.name as department_name,
                       CONCAT(u.first_name, ' ', u.last_name) as teacher_name
                FROM courses c
                JOIN departments d ON c.department_id = d.id
                JOIN users u ON c.teacher_id = u.id
                WHERE c.id = :course_id
            ", ['course_id' => $courseId]);
            
            if (!$course) {
                throw new Exception("Course not found");
            }
            
            // Get enrolled students
            $students = $this->db->fetchAll("
                SELECT s.student_id, CONCAT(u.first_name, ' ', u.last_name) as student_name,
                       u.email, e.final_grade, e.enrollment_date,
                       AVG(g.score) as avg_score, COUNT(g.id) as grade_count
                FROM enrollments e
                JOIN students s ON e.student_id = s.id
                JOIN users u ON s.user_id = u.id
                LEFT JOIN grades g ON e.id = g.enrollment_id
                WHERE e.course_id = :course_id
                GROUP BY e.id, s.id
                ORDER BY u.last_name, u.first_name
            ", ['course_id' => $courseId]);
            
            // Get attendance statistics
            $attendanceStats = $this->db->fetchAll("
                SELECT a.status, COUNT(*) as count
                FROM attendance a
                WHERE a.course_id = :course_id
                GROUP BY a.status
            ", ['course_id' => $courseId]);
            
            $attendanceData = [];
            foreach ($attendanceStats as $stat) {
                $attendanceData[$stat['status']] = $stat['count'];
            }
            
            $html = $this->getCourseReportTemplate($course, $students, $attendanceData, $reportType);
            
            return $this->generatePDF($html, 'course_report_' . $course['course_code']);
            
        } catch (Exception $e) {
            throw new Exception("Failed to generate course report: " . $e->getMessage());
        }
    }
    
    /**
     * Generate Financial Report
     */
    public function generateFinancialReport($studentId = null, $dateRange = []) {
        try {
            $sql = "SELECT fr.*, s.student_id, CONCAT(u.first_name, ' ', u.last_name) as student_name,
                           d.name as department_name
                    FROM financial_records fr
                    JOIN students s ON fr.student_id = s.id
                    JOIN users u ON s.user_id = u.id
                    JOIN departments d ON s.department_id = d.id
                    WHERE 1=1";
            
            $params = [];
            
            if ($studentId) {
                $sql .= " AND fr.student_id = :student_id";
                $params['student_id'] = $studentId;
            }
            
            if (!empty($dateRange['start'])) {
                $sql .= " AND fr.due_date >= :start_date";
                $params['start_date'] = $dateRange['start'];
            }
            
            if (!empty($dateRange['end'])) {
                $sql .= " AND fr.due_date <= :end_date";
                $params['end_date'] = $dateRange['end'];
            }
            
            $sql .= " ORDER BY fr.due_date DESC";
            
            $records = $this->db->fetchAll($sql, $params);
            
            // Calculate summary statistics
            $summary = [
                'total_amount' => array_sum(array_column($records, 'amount')),
                'paid_amount' => array_sum(array_column($records, 'paid_amount')),
                'pending_amount' => 0,
                'overdue_amount' => 0,
                'total_records' => count($records)
            ];
            
            foreach ($records as $record) {
                if ($record['status'] === 'pending') {
                    $summary['pending_amount'] += $record['amount'];
                } elseif ($record['status'] === 'overdue') {
                    $summary['overdue_amount'] += $record['amount'];
                }
            }
            
            $html = $this->getFinancialReportTemplate($records, $summary, $studentId, $dateRange);
            
            $filename = $studentId ? 'financial_report_' . $records[0]['student_id'] : 'financial_report_all';
            return $this->generatePDF($html, $filename);
            
        } catch (Exception $e) {
            throw new Exception("Failed to generate financial report: " . $e->getMessage());
        }
    }
    
    /**
     * Generate PDF from HTML
     */
    private function generatePDF($html, $filename) {
        // Include Dompdf autoloader
        require_once __DIR__ . '/../vendor/dompdf/autoload.php';
        
        // Create new Dompdf instance
        $dompdf = new Dompdf\Dompdf([
            'defaultFont' => 'Arial',
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true
        ]);
        
        // Load HTML
        $dompdf->loadHtml($html);
        
        // Set paper size and orientation
        $dompdf->setPaper('A4', 'portrait');
        
        // Render the HTML as PDF
        $dompdf->render();
        
        // Save PDF to file
        $outputDir = __DIR__ . '/../assets/pdf/';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        $filePath = $outputDir . $filename . '_' . date('Y-m-d_H-i-s') . '.pdf';
        file_put_contents($filePath, $dompdf->output());
        
        return $filePath;
    }
    
    /**
     * ID Card HTML Template
     */
    private function getIDCardTemplate($student, $qrData) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { margin: 0; padding: 20px; font-family: Arial, sans-serif; }
                .id-card {
                    width: 350px;
                    height: 200px;
                    border: 2px solid #4F46E5;
                    border-radius: 10px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 20px;
                    position: relative;
                }
                .id-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 15px;
                }
                .logo {
                    font-size: 24px;
                    font-weight: bold;
                }
                .photo {
                    width: 60px;
                    height: 60px;
                    border-radius: 50%;
                    background: white;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #4F46E5;
                    font-weight: bold;
                    font-size: 20px;
                }
                .student-info h3 {
                    margin: 0 0 10px 0;
                    font-size: 18px;
                }
                .student-info p {
                    margin: 5px 0;
                    font-size: 12px;
                }
                .qr-code {
                    position: absolute;
                    bottom: 10px;
                    right: 10px;
                    width: 60px;
                    height: 60px;
                    background: white;
                    padding: 5px;
                    border-radius: 5px;
                }
                .valid-until {
                    position: absolute;
                    bottom: 10px;
                    left: 20px;
                    font-size: 10px;
                }
            </style>
        </head>
        <body>
            <div class='id-card'>
                <div class='id-header'>
                    <div class='logo'>BDU</div>
                    <div class='photo'>" . strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)) . "</div>
                </div>
                <div class='student-info'>
                    <h3>{$student['first_name']} {$student['last_name']}</h3>
                    <p><strong>ID:</strong> {$student['student_id']}</p>
                    <p><strong>Dept:</strong> {$student['department_name']}</p>
                    <p><strong>Year:</strong> {$student['year_level']}</p>
                </div>
                <div class='qr-code'>
                    <!-- QR Code would be generated here -->
                    <div style='width: 100%; height: 100%; background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 8px; text-align: center;'>QR</div>
                </div>
                <div class='valid-until'>Valid until: " . date('M j, Y', strtotime('+1 year')) . "</div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Transcript HTML Template
     */
    private function getTranscriptTemplate($student, $courses, $stats) {
        $coursesHtml = '';
        foreach ($courses as $course) {
            $grade = $course['final_grade'] ?? 'N/A';
            $coursesHtml .= "
            <tr>
                <td>{$course['course_code']}</td>
                <td>{$course['course_name']}</td>
                <td>{$course['credits']}</td>
                <td>" . ($course['avg_score'] ?? 'N/A') . "</td>
                <td>{$grade}</td>
                <td>" . ucfirst($course['enrollment_status']) . "</td>
            </tr>";
        }
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; }
                .header { text-align: center; margin-bottom: 30px; }
                .header h1 { color: #4F46E5; margin: 0; }
                .student-info { margin-bottom: 30px; }
                .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
                .info-item { margin-bottom: 10px; }
                .info-item strong { color: #4F46E5; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #4F46E5; color: white; }
                .summary { background: #f8f9fa; padding: 20px; border-radius: 5px; }
                .signature { margin-top: 50px; text-align: right; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>Official Transcript</h1>
                <p>Bahir Dar University</p>
            </div>
            
            <div class='student-info'>
                <h2>Student Information</h2>
                <div class='info-grid'>
                    <div class='info-item'><strong>Name:</strong> {$student['first_name']} {$student['last_name']}</div>
                    <div class='info-item'><strong>Student ID:</strong> {$student['student_id']}</div>
                    <div class='info-item'><strong>Department:</strong> {$student['department_name']}</div>
                    <div class='info-item'><strong>Year Level:</strong> {$student['year_level']}</div>
                </div>
            </div>
            
            <h2>Academic Record</h2>
            <table>
                <thead>
                    <tr>
                        <th>Course Code</th>
                        <th>Course Name</th>
                        <th>Credits</th>
                        <th>Avg Score</th>
                        <th>Final Grade</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    {$coursesHtml}
                </tbody>
            </table>
            
            <div class='summary'>
                <h2>Academic Summary</h2>
                <div class='info-grid'>
                    <div class='info-item'><strong>Total Credits:</strong> {$stats['total_credits']}</div>
                    <div class='info-item'><strong>GPA:</strong> {$stats['gpa']}</div>
                    <div class='info-item'><strong>Completed Courses:</strong> {$stats['completed_courses']}</div>
                    <div class='info-item'><strong>Generated:</strong> {$stats['generated_date']}</div>
                </div>
            </div>
            
            <div class='signature'>
                <p>_________________________</p>
                <p>Registrar Signature</p>
                <p>Bahir Dar University</p>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Course Report HTML Template
     */
    private function getCourseReportTemplate($course, $students, $attendanceData, $reportType) {
        $studentsHtml = '';
        foreach ($students as $student) {
            $studentsHtml .= "
            <tr>
                <td>{$student['student_id']}</td>
                <td>{$student['student_name']}</td>
                <td>{$student['email']}</td>
                <td>" . ($student['avg_score'] ?? 'N/A') . "</td>
                <td>" . ($student['final_grade'] ?? 'N/A') . "</td>
                <td>" . ucfirst($student['enrollment_status']) . "</td>
            </tr>";
        }
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; }
                .header { text-align: center; margin-bottom: 30px; }
                .header h1 { color: #4F46E5; margin: 0; }
                .course-info { margin-bottom: 30px; }
                .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
                .info-item { margin-bottom: 10px; }
                .info-item strong { color: #4F46E5; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #4F46E5; color: white; }
                .attendance-stats { background: #f8f9fa; padding: 20px; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>Course Report</h1>
                <p>Bahir Dar University</p>
            </div>
            
            <div class='course-info'>
                <h2>Course Information</h2>
                <div class='info-grid'>
                    <div class='info-item'><strong>Course Code:</strong> {$course['course_code']}</div>
                    <div class='info-item'><strong>Course Name:</strong> {$course['course_name']}</div>
                    <div class='info-item'><strong>Department:</strong> {$course['department_name']}</div>
                    <div class='info-item'><strong>Instructor:</strong> {$course['teacher_name']}</div>
                    <div class='info-item'><strong>Credits:</strong> {$course['credits']}</div>
                    <div class='info-item'><strong>Enrolled Students:</strong> " . count($students) . "</div>
                </div>
            </div>
            
            <h2>Student Performance</h2>
            <table>
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Avg Score</th>
                        <th>Final Grade</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    {$studentsHtml}
                </tbody>
            </table>
            
            <div class='attendance-stats'>
                <h2>Attendance Overview</h2>
                <div class='info-grid'>
                    <div class='info-item'><strong>Present:</strong> " . ($attendanceData['present'] ?? 0) . "</div>
                    <div class='info-item'><strong>Absent:</strong> " . ($attendanceData['absent'] ?? 0) . "</div>
                    <div class='info-item'><strong>Late:</strong> " . ($attendanceData['late'] ?? 0) . "</div>
                    <div class='info-item'><strong>Excused:</strong> " . ($attendanceData['excused'] ?? 0) . "</div>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Financial Report HTML Template
     */
    private function getFinancialReportTemplate($records, $summary, $studentId, $dateRange) {
        $recordsHtml = '';
        foreach ($records as $record) {
            $recordsHtml .= "
            <tr>
                <td>{$record['student_id']}</td>
                <td>{$record['student_name']}</td>
                <td>" . ucfirst($record['fee_type']) . "</td>
                <td>\${$record['amount']}</td>
                <td>{$record['due_date']}</td>
                <td>\${$record['paid_amount']}</td>
                <td>" . ucfirst($record['status']) . "</td>
            </tr>";
        }
        
        $title = $studentId ? 'Student Financial Report' : 'Financial Report - All Students';
        $dateRangeText = '';
        if (!empty($dateRange['start']) && !empty($dateRange['end'])) {
            $dateRangeText = " ({$dateRange['start']} to {$dateRange['end']})";
        }
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; }
                .header { text-align: center; margin-bottom: 30px; }
                .header h1 { color: #4F46E5; margin: 0; }
                .summary { background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 30px; }
                .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
                .info-item { margin-bottom: 10px; }
                .info-item strong { color: #4F46E5; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #4F46E5; color: white; }
                .status-paid { color: #10B981; font-weight: bold; }
                .status-pending { color: #F59E0B; font-weight: bold; }
                .status-overdue { color: #EF4444; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>{$title}{$dateRangeText}</h1>
                <p>Bahir Dar University</p>
                <p>Generated on: " . date('F j, Y') . "</p>
            </div>
            
            <div class='summary'>
                <h2>Financial Summary</h2>
                <div class='info-grid'>
                    <div class='info-item'><strong>Total Records:</strong> {$summary['total_records']}</div>
                    <div class='info-item'><strong>Total Amount:</strong> \${$summary['total_amount']}</div>
                    <div class='info-item'><strong>Paid Amount:</strong> \${$summary['paid_amount']}</div>
                    <div class='info-item'><strong>Pending Amount:</strong> \${$summary['pending_amount']}</div>
                    <div class='info-item'><strong>Overdue Amount:</strong> \${$summary['overdue_amount']}</div>
                </div>
            </div>
            
            <h2>Financial Records</h2>
            <table>
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Fee Type</th>
                        <th>Amount</th>
                        <th>Due Date</th>
                        <th>Paid Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    {$recordsHtml}
                </tbody>
            </table>
        </body>
        </html>";
    }
}
?>
