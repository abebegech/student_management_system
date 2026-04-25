<?php
session_start();
require_once '../includes/Auth.php';
require_once '../includes/Database.php';
require_once '../includes/PDFGenerator.php';

$auth = new Auth();
$auth->requireRole('student');

$db = Database::getInstance();
$userId = $auth->getUserId();

// Get student information
$student = $db->fetch("
    SELECT s.*, u.first_name, u.last_name, d.name as department_name
    FROM students s
    JOIN users u ON s.user_id = u.id
    JOIN departments d ON s.department_id = d.id
    WHERE s.user_id = :user_id
", ['user_id' => $userId]);

if (!$student) {
    header('Location: ../login.php');
    exit;
}

// Handle document generation
if (isset($_POST['generate_transcript'])) {
    try {
        $pdfGen = new PDFGenerator();
        $transcript = $pdfGen->generateStudentTranscript($student['id']);
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="transcript_' . $student['student_id'] . '.pdf"');
        echo $transcript;
        exit;
        
    } catch (Exception $e) {
        $error = "Failed to generate transcript: " . $e->getMessage();
    }
}

if (isset($_POST['generate_id_card'])) {
    try {
        $pdfGen = new PDFGenerator();
        $idCard = $pdfGen->generateStudentIDCard($student['id']);
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="id_card_' . $student['student_id'] . '.pdf"');
        echo $idCard;
        exit;
        
    } catch (Exception $e) {
        $error = "Failed to generate ID card: " . $e->getMessage();
    }
}

if (isset($_POST['generate_attendance_report'])) {
    try {
        $attendanceReport = generateAttendanceReport($student['id'], $db);
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="attendance_report_' . $student['student_id'] . '.pdf"');
        echo $attendanceReport;
        exit;
        
    } catch (Exception $e) {
        $error = "Failed to generate attendance report: " . $e->getMessage();
    }
}

if (isset($_POST['generate_financial_statement'])) {
    try {
        $financialStatement = generateFinancialStatement($student['id'], $db);
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="financial_statement_' . $student['student_id'] . '.pdf"');
        echo $financialStatement;
        exit;
        
    } catch (Exception $e) {
        $error = "Failed to generate financial statement: " . $e->getMessage();
    }
}

function generateAttendanceReport($studentId, $db) {
    // Get attendance data
    $attendanceData = $db->fetchAll("
        SELECT a.*, c.course_code, c.course_name,
               CONCAT(u.first_name, ' ', u.last_name) as student_name,
               s.student_id as student_number
        FROM attendance a
        JOIN courses c ON a.course_id = c.id
        JOIN students s ON a.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE a.student_id = :student_id
        ORDER BY a.attendance_date DESC, c.course_code
    ", ['student_id' => $studentId]);
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #4F46E5; padding-bottom: 20px; }
            .header h1 { color: #4F46E5; margin: 0; font-size: 2rem; }
            .header p { margin: 5px 0; color: #6B7280; }
            .student-info { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; }
            .info-section { background: #f8f9fa; padding: 20px; border-radius: 8px; }
            .info-section h3 { margin: 0 0 15px 0; color: #4F46E5; border-bottom: 1px solid #e5e7eb; padding-bottom: 10px; }
            .info-item { margin-bottom: 10px; display: flex; justify-content: space-between; }
            .info-label { font-weight: 600; color: #374151; }
            .info-value { color: #6B7280; }
            .summary-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
            .stat-card { text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px; }
            .stat-value { font-size: 1.5rem; font-weight: bold; color: #4F46E5; }
            .stat-label { font-size: 0.875rem; color: #6B7280; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #e5e7eb; padding: 12px; text-align: left; }
            th { background: #4F46E5; color: white; }
            .status-present { color: #10B981; font-weight: bold; }
            .status-absent { color: #EF4444; font-weight: bold; }
            .status-late { color: #F59E0B; font-weight: bold; }
            .status-excused { color: #6366F1; font-weight: bold; }
            .footer { margin-top: 50px; text-align: center; color: #6B7280; font-size: 0.875rem; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>Attendance Report</h1>
            <p>Bahir Dar University Student Management System</p>
            <p>Generated on: " . date('F j, Y') . "</p>
        </div>
        
        <div class='student-info'>
            <div class='info-section'>
                <h3>Student Information</h3>
                <div class='info-item'>
                    <span class='info-label'>Student ID:</span>
                    <span class='info-value'>" . htmlspecialchars($attendanceData[0]['student_number'] ?? 'N/A') . "</span>
                </div>
                <div class='info-item'>
                    <span class='info-label'>Name:</span>
                    <span class='info-value'>" . htmlspecialchars($attendanceData[0]['student_name'] ?? 'N/A') . "</span>
                </div>
            </div>
        </div>
        
        <div class='summary-stats'>
            <div class='stat-card'>
                <div class='stat-value'>" . count($attendanceData) . "</div>
                <div class='stat-label'>Total Sessions</div>
            </div>
            <div class='stat-card'>
                <div class='stat-value'>" . count(array_filter($attendanceData, fn($a) => $a['status'] === 'present')) . "</div>
                <div class='stat-label'>Present</div>
            </div>
            <div class='stat-card'>
                <div class='stat-value'>" . count(array_filter($attendanceData, fn($a) => $a['status'] === 'absent')) . "</div>
                <div class='stat-label'>Absent</div>
            </div>
            <div class='stat-card'>
                <div class='stat-value'>" . round((count(array_filter($attendanceData, fn($a) => $a['status'] === 'present')) / count($attendanceData)) * 100, 1) . "%</div>
                <div class='stat-label'>Attendance Rate</div>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Course</th>
                    <th>Status</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>";
    
    foreach ($attendanceData as $record) {
        $html .= "
                <tr>
                    <td>" . date('M j, Y', strtotime($record['attendance_date'])) . "</td>
                    <td>" . htmlspecialchars($record['course_code'] . ' - ' . $record['course_name']) . "</td>
                    <td class='status-" . $record['status'] . "'>" . ucfirst($record['status']) . "</td>
                    <td>" . htmlspecialchars($record['notes'] ?? '') . "</td>
                </tr>";
    }
    
    $html .= "
            </tbody>
        </table>
        
        <div class='footer'>
            <p>This document serves as an official attendance record.</p>
            <p>Generated on: " . date('F j, Y H:i:s') . "</p>
        </div>
    </body>
    </html>";
    
    return $html;
}

function generateFinancialStatement($studentId, $db) {
    // Get financial data
    $financialData = $db->fetchAll("
        SELECT fr.*, s.student_id, CONCAT(u.first_name, ' ', u.last_name) as student_name,
               d.name as department_name
        FROM financial_records fr
        JOIN students s ON fr.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN departments d ON s.department_id = d.id
        WHERE fr.student_id = :student_id
        ORDER BY fr.due_date DESC
    ", ['student_id' => $studentId]);
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #4F46E5; padding-bottom: 20px; }
            .header h1 { color: #4F46E5; margin: 0; font-size: 2rem; }
            .header p { margin: 5px 0; color: #6B7280; }
            .student-info { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; }
            .info-section { background: #f8f9fa; padding: 20px; border-radius: 8px; }
            .info-section h3 { margin: 0 0 15px 0; color: #4F46E5; border-bottom: 1px solid #e5e7eb; padding-bottom: 10px; }
            .info-item { margin-bottom: 10px; display: flex; justify-content: space-between; }
            .info-label { font-weight: 600; color: #374151; }
            .info-value { color: #6B7280; }
            .summary-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
            .stat-card { text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px; }
            .stat-value { font-size: 1.5rem; font-weight: bold; color: #4F46E5; }
            .stat-label { font-size: 0.875rem; color: #6B7280; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #e5e7eb; padding: 12px; text-align: left; }
            th { background: #4F46E5; color: white; }
            .status-paid { color: #10B981; font-weight: bold; }
            .status-pending { color: #F59E0B; font-weight: bold; }
            .status-overdue { color: #EF4444; font-weight: bold; }
            .footer { margin-top: 50px; text-align: center; color: #6B7280; font-size: 0.875rem; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>Financial Statement</h1>
            <p>Bahir Dar University Student Management System</p>
            <p>Generated on: " . date('F j, Y') . "</p>
        </div>
        
        <div class='student-info'>
            <div class='info-section'>
                <h3>Student Information</h3>
                <div class='info-item'>
                    <span class='info-label'>Student ID:</span>
                    <span class='info-value'>" . htmlspecialchars($financialData[0]['student_id'] ?? 'N/A') . "</span>
                </div>
                <div class='info-item'>
                    <span class='info-label'>Name:</span>
                    <span class='info-value'>" . htmlspecialchars($financialData[0]['student_name'] ?? 'N/A') . "</span>
                </div>
            </div>
        </div>
        
        <div class='summary-stats'>
            <div class='stat-card'>
                <div class='stat-value'>$" . number_format(array_sum(array_column($financialData, 'amount')), 2) . "</div>
                <div class='stat-label'>Total Amount</div>
            </div>
            <div class='stat-card'>
                <div class='stat-value'>$" . number_format(array_sum(array_column($financialData, 'paid_amount')), 2) . "</div>
                <div class='stat-label'>Paid Amount</div>
            </div>
            <div class='stat-card'>
                <div class='stat-value'>$" . number_format(array_sum(array_column($financialData, 'amount')) - array_sum(array_column($financialData, 'paid_amount')), 2) . "</div>
                <div class='stat-label'>Balance Due</div>
            </div>
            <div class='stat-card'>
                <div class='stat-value'>" . count(array_filter($financialData, fn($f) => $f['status'] === 'paid')) . "</div>
                <div class='stat-label'>Paid Records</div>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Fee Type</th>
                    <th>Description</th>
                    <th>Amount</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Due Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>";
    
    foreach ($financialData as $record) {
        $html .= "
                <tr>
                    <td>" . ucfirst($record['fee_type']) . "</td>
                    <td>" . htmlspecialchars($record['description'] ?? 'N/A') . "</td>
                    <td>$" . number_format($record['amount'], 2) . "</td>
                    <td>$" . number_format($record['paid_amount'], 2) . "</td>
                    <td>$" . number_format($record['amount'] - $record['paid_amount'], 2) . "</td>
                    <td>" . date('M j, Y', strtotime($record['due_date'])) . "</td>
                    <td class='status-" . $record['status'] . "'>" . ucfirst($record['status']) . "</td>
                </tr>";
    }
    
    $html .= "
            </tbody>
        </table>
        
        <div class='footer'>
            <p>This document serves as an official financial statement.</p>
            <p>Generated on: " . date('F j, Y H:i:s') . "</p>
        </div>
    </body>
    </html>";
    
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Documents | BDU Student System</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .sidebar-layout {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }
        
        .sidebar {
            background: var(--card-bg);
            border-right: 1px solid var(--border);
            padding: 1.5rem;
        }
        
        .sidebar-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-nav li {
            margin-bottom: 0.5rem;
        }
        
        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: var(--text);
            text-decoration: none;
            border-radius: 6px;
            transition: background 0.2s;
        }
        
        .sidebar-nav a:hover, .sidebar-nav a.active {
            background: var(--primary);
            color: white;
        }
        
        .main-content {
            padding: 2rem;
            background: var(--bg);
        }
        
        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .card h2 {
            margin: 0 0 1rem 0;
            color: var(--text);
        }
        
        .document-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .document-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            overflow: hidden;
        }
        
        .document-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.15);
        }
        
        .document-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .document-title {
            font-size: 1.25rem;
            font-weight: bold;
            color: var(--text);
            margin-bottom: 0.5rem;
        }
        
        .document-desc {
            color: var(--muted);
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            line-height: 1.4;
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary { background: var(--primary); color: white; }
        .btn-secondary { background: var(--secondary); color: white; }
        .btn-success { background: #10B981; color: white; }
        
        .recent-documents {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th, .table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        .table th {
            background: var(--primary);
            color: white;
        }
        
        @media (max-width: 1024px) {
            .sidebar-layout {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .document-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar-layout">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div style="text-align: center; margin-bottom: 2rem;">
                <div class="profile-avatar" style="margin: 0 auto 1rem; width: 60px; height: 60px; background: linear-gradient(135deg, var(--primary), var(--secondary)); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; font-weight: bold;">
                    <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                </div>
                <h3 style="margin: 0; color: var(--text);"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h3>
                <p style="margin: 0.25rem 0 0 0; color: var(--muted); font-size: 0.875rem;">
                    Student ID: <?php echo htmlspecialchars($student['student_id']); ?>
                </p>
            </div>
            
            <nav>
                <ul class="sidebar-nav">
                    <li><a href="dashboard.php">📊 Dashboard</a></li>
                    <li><a href="grades.php">📝 Grades</a></li>
                    <li><a href="attendance.php">📅 Attendance</a></li>
                    <li><a href="courses.php">📚 Courses</a></li>
                    <li><a href="financial.php">💰 Financial</a></li>
                    <li><a href="#" class="active">📄 Documents</a></li>
                    <li><a href="profile.php">👤 Profile</a></li>
                    <li><a href="../logout.php">🚪 Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header style="margin-bottom: 2rem;">
                <h1 style="margin: 0; color: var(--text);">My Documents</h1>
                <p style="margin: 0.5rem 0 0 0; color: var(--muted);">Generate and download your academic and financial documents</p>
            </header>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Available Documents -->
            <div class="card">
                <h2>📄 Available Documents</h2>
                <div class="document-grid">
                    <div class="document-card">
                        <div class="document-icon">📜</div>
                        <div class="document-title">Academic Transcript</div>
                        <div class="document-desc">Complete academic record with all courses, grades, and GPA calculation</div>
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="generate_transcript" class="btn btn-primary">Download Transcript</button>
                        </form>
                    </div>
                    
                    <div class="document-card">
                        <div class="document-icon">🆔</div>
                        <div class="document-title">Student ID Card</div>
                        <div class="document-desc">Official student identification card with photo and details</div>
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="generate_id_card" class="btn btn-primary">Download ID Card</button>
                        </form>
                    </div>
                    
                    <div class="document-card">
                        <div class="document-icon">📅</div>
                        <div class="document-title">Attendance Report</div>
                        <div class="document-desc">Detailed attendance record with statistics and course breakdown</div>
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="generate_attendance_report" class="btn btn-primary">Download Report</button>
                        </form>
                    </div>
                    
                    <div class="document-card">
                        <div class="document-icon">💰</div>
                        <div class="document-title">Financial Statement</div>
                        <div class="document-desc">Complete financial record with fee breakdown and payment history</div>
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="generate_financial_statement" class="btn btn-primary">Download Statement</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Document Information -->
            <div class="card">
                <h2>ℹ️ Document Information</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                    <div>
                        <h3 style="color: var(--text); margin: 0 0 0.5rem 0;">📜 Academic Transcript</h3>
                        <ul style="color: var(--muted); font-size: 0.875rem; margin: 0; padding-left: 1.5rem;">
                            <li>All enrolled courses</li>
                            <li>Grade breakdown by assessment</li>
                            <li>Cumulative GPA calculation</li>
                            <li>Academic standing</li>
                        </ul>
                    </div>
                    
                    <div>
                        <h3 style="color: var(--text); margin: 0 0 0.5rem 0;">🆔 Student ID Card</h3>
                        <ul style="color: var(--muted); font-size: 0.875rem; margin: 0; padding-left: 1.5rem;">
                            <li>Student photo and details</li>
                            <li>Student ID number</li>
                            <li>Department information</li>
                            <li>Academic year</li>
                        </ul>
                    </div>
                    
                    <div>
                        <h3 style="color: var(--text); margin: 0 0 0.5rem 0;">📅 Attendance Report</h3>
                        <ul style="color: var(--muted); font-size: 0.875rem; margin: 0; padding-left: 1.5rem;">
                            <li>Attendance by course</li>
                            <li>Monthly attendance trends</li>
                            <li>Attendance statistics</li>
                            <li>Excused absences</li>
                        </ul>
                    </div>
                    
                    <div>
                        <h3 style="color: var(--text); margin: 0 0 0.5rem 0;">💰 Financial Statement</h3>
                        <ul style="color: var(--muted); font-size: 0.875rem; margin: 0; padding-left: 1.5rem;">
                            <li>Fee breakdown by type</li>
                            <li>Payment history</li>
                            <li>Outstanding balance</li>
                            <li>Due dates</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Document Usage Guidelines -->
            <div class="card">
                <h2>📋 Document Usage Guidelines</h2>
                <div style="color: var(--muted); line-height: 1.6;">
                    <h3 style="color: var(--text); margin: 1rem 0 0.5rem 0;">When to Use These Documents:</h3>
                    <ul style="margin: 0 0 1rem 0; padding-left: 1.5rem;">
                        <li><strong>Academic Transcript:</strong> For job applications, further education, scholarships</li>
                        <li><strong>Student ID Card:</strong> For campus access, exams, library services</li>
                        <li><strong>Attendance Report:</strong> For visa applications, academic progress review</li>
                        <li><strong>Financial Statement:</strong> For scholarship applications, payment verification</li>
                    </ul>
                    
                    <h3 style="color: var(--text); margin: 1rem 0 0.5rem 0;">Document Validity:</h3>
                    <ul style="margin: 0 0 1rem 0; padding-left: 1.5rem;">
                        <li>All documents are generated with current data</li>
                        <li>Documents include generation timestamp</li>
                        <li>Official for the current academic year</li>
                        <li>Can be re-generated as needed</li>
                    </ul>
                    
                    <h3 style="color: var(--text); margin: 1rem 0 0.5rem 0;">Technical Requirements:</h3>
                    <ul style="margin: 0; padding-left: 1.5rem;">
                        <li>PDF format for universal compatibility</li>
                        <li>Print-friendly formatting</li>
                        <li>Digital signatures included where applicable</li>
                        <li>Watermarked for authenticity</li>
                    </ul>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
