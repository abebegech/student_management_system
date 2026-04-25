<?php
session_start();
require_once '../includes/Auth.php';
require_once '../includes/Database.php';

$auth = new Auth();
$auth->requireRole('teacher');

$db = Database::getInstance();
$userId = $auth->getUserId();

// Get courses taught by this teacher
$courses = $db->fetchAll("
    SELECT c.id, c.course_code, c.course_name, d.name as department_name,
           COUNT(e.student_id) as enrolled_students
    FROM courses c
    JOIN departments d ON c.department_id = d.id
    LEFT JOIN enrollments e ON c.id = e.course_id AND e.status = 'enrolled'
    WHERE c.teacher_id = :teacher_id AND c.is_active = 1
    GROUP BY c.id
    ORDER BY c.course_name
", ['teacher_id' => $userId]);

// Get selected course
$selected_course_id = $_GET['course'] ?? $courses[0]['id'] ?? null;
$selected_course = null;

if ($selected_course_id) {
    // Get course details
    $selected_course = $db->fetch("
        SELECT c.*, d.name as department_name
        FROM courses c
        JOIN departments d ON c.department_id = d.id
        WHERE c.id = :course_id AND c.teacher_id = :teacher_id
    ", ['course_id' => $selected_course_id, 'teacher_id' => $userId]);
}

// Handle report generation
if (isset($_POST['generate_report'])) {
    require_once '../includes/PDFGenerator.php';
    
    try {
        $pdfGen = new PDFGenerator();
        $reportType = $_POST['report_type'];
        
        switch ($reportType) {
            case 'course':
                $filePath = $pdfGen->generateCourseReport($selected_course_id);
                $message = "Course report generated successfully!";
                break;
            case 'attendance':
                // Generate attendance report
                $filePath = generateAttendanceReport($selected_course_id, $db, $auth);
                $message = "Attendance report generated successfully!";
                break;
            case 'grades':
                // Generate grades report
                $filePath = generateGradesReport($selected_course_id, $db, $auth);
                $message = "Grades report generated successfully!";
                break;
        }
        
        if (isset($filePath) && file_exists($filePath)) {
            // Offer download
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
        }
        
    } catch (Exception $e) {
        $error = "Failed to generate report: " . $e->getMessage();
    }
}

function generateAttendanceReport($courseId, $db, $auth) {
    // Get attendance data
    $attendanceData = $db->fetchAll("
        SELECT a.*, s.student_id, CONCAT(u.first_name, ' ', u.last_name) as student_name,
               c.course_code, c.course_name
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN courses c ON a.course_id = c.id
        WHERE a.course_id = :course_id
        ORDER BY a.attendance_date DESC, u.last_name, u.first_name
    ", ['course_id' => $courseId]);
    
    // Generate HTML for PDF
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .header { text-align: center; margin-bottom: 30px; }
            .header h1 { color: #4F46E5; margin: 0; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #4F46E5; color: white; }
            .status-present { color: #10B981; font-weight: bold; }
            .status-absent { color: #EF4444; font-weight: bold; }
            .status-late { color: #F59E0B; font-weight: bold; }
            .status-excused { color: #6366F1; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>Attendance Report</h1>
            <p>" . htmlspecialchars($attendanceData[0]['course_code'] . ' - ' . $attendanceData[0]['course_name']) . "</p>
            <p>Generated on: " . date('F j, Y') . "</p>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>";
    
    foreach ($attendanceData as $record) {
        $html .= "
                <tr>
                    <td>" . date('M j, Y', strtotime($record['attendance_date'])) . "</td>
                    <td>" . htmlspecialchars($record['student_id']) . "</td>
                    <td>" . htmlspecialchars($record['student_name']) . "</td>
                    <td class='status-" . $record['status'] . "'>" . ucfirst($record['status']) . "</td>
                    <td>" . htmlspecialchars($record['notes'] ?? '') . "</td>
                </tr>";
    }
    
    $html .= "
            </tbody>
        </table>
    </body>
    </html>";
    
    // Save to file
    $filename = 'assets/reports/attendance_report_' . $courseId . '_' . date('Y-m-d') . '.html';
    file_put_contents($filename, $html);
    
    return $filename;
}

function generateGradesReport($courseId, $db, $auth) {
    // Get grades data
    $gradesData = $db->fetchAll("
        SELECT g.*, s.student_id, CONCAT(u.first_name, ' ', u.last_name) as student_name,
               c.course_code, c.course_name
        FROM grades g
        JOIN enrollments e ON g.enrollment_id = e.id
        JOIN students s ON e.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN courses c ON e.course_id = c.id
        WHERE e.course_id = :course_id
        ORDER BY u.last_name, u.first_name, g.graded_date DESC
    ", ['course_id' => $courseId]);
    
    // Generate HTML for PDF
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .header { text-align: center; margin-bottom: 30px; }
            .header h1 { color: #4F46E5; margin: 0; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #4F46E5; color: white; }
            .grade-high { color: #10B981; font-weight: bold; }
            .grade-medium { color: #F59E0B; font-weight: bold; }
            .grade-low { color: #EF4444; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>Grades Report</h1>
            <p>" . htmlspecialchars($gradesData[0]['course_code'] . ' - ' . $gradesData[0]['course_name']) . "</p>
            <p>Generated on: " . date('F j, Y') . "</p>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Assessment</th>
                    <th>Type</th>
                    <th>Score</th>
                    <th>Max Score</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>";
    
    foreach ($gradesData as $record) {
        $percentage = ($record['score'] / $record['max_score']) * 100;
        $gradeClass = $percentage >= 80 ? 'grade-high' : ($percentage >= 60 ? 'grade-medium' : 'grade-low');
        
        $html .= "
                <tr>
                    <td>" . htmlspecialchars($record['student_id']) . "</td>
                    <td>" . htmlspecialchars($record['student_name']) . "</td>
                    <td>" . htmlspecialchars($record['assessment_name']) . "</td>
                    <td>" . ucfirst($record['assessment_type']) . "</td>
                    <td class='$gradeClass'>" . $record['score'] . "</td>
                    <td>" . $record['max_score'] . "</td>
                    <td>" . date('M j, Y', strtotime($record['graded_date'])) . "</td>
                </tr>";
    }
    
    $html .= "
            </tbody>
        </table>
    </body>
    </html>";
    
    // Save to file
    $filename = 'assets/reports/grades_report_' . $courseId . '_' . date('Y-m-d') . '.html';
    file_put_contents($filename, $html);
    
    return $filename;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | BDU Student System</title>
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
        
        .course-selector {
            margin-bottom: 2rem;
        }
        
        .course-selector select {
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--bg);
            color: var(--text);
            font-size: 1rem;
            min-width: 300px;
        }
        
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .report-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .report-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .report-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .report-title {
            font-size: 1.25rem;
            font-weight: bold;
            color: var(--text);
            margin-bottom: 0.5rem;
        }
        
        .report-desc {
            color: var(--muted);
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
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
        
        @media (max-width: 1024px) {
            .sidebar-layout {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .reports-grid {
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
                    <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1)); ?>
                </div>
                <h3 style="margin: 0; color: var(--text);"><?php echo htmlspecialchars($_SESSION['full_name']); ?></h3>
                <p style="margin: 0.25rem 0 0 0; color: var(--muted); font-size: 0.875rem;">
                    Faculty Member
                </p>
            </div>
            
            <nav>
                <ul class="sidebar-nav">
                    <li><a href="dashboard.php">📊 Dashboard</a></li>
                    <li><a href="courses.php">📚 My Courses</a></li>
                    <li><a href="grades.php">📝 Grade Students</a></li>
                    <li><a href="attendance.php">📅 Mark Attendance</a></li>
                    <li><a href="analytics.php">📈 Analytics</a></li>
                    <li><a href="#" class="active">📊 Reports</a></li>
                    <li><a href="profile.php">👤 Profile</a></li>
                    <li><a href="../logout.php">🚪 Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header style="margin-bottom: 2rem;">
                <h1 style="margin: 0; color: var(--text);">Course Reports</h1>
                <p style="margin: 0.5rem 0 0 0; color: var(--muted);">Generate comprehensive reports for your courses</p>
            </header>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <!-- Course Selection -->
            <div class="course-selector">
                <label for="course_select" style="display: block; margin-bottom: 0.5rem; color: var(--text); font-weight: 600;">Select Course:</label>
                <select id="course_select" onchange="window.location.href='reports.php?course=' + this.value">
                    <?php foreach ($courses as $course): ?>
                        <option value="<?php echo $course['id']; ?>" 
                                <?php echo $selected_course_id == $course['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($selected_course): ?>
                <!-- Course Info -->
                <div class="card">
                    <h2>📚 <?php echo htmlspecialchars($selected_course['course_code']); ?> - <?php echo htmlspecialchars($selected_course['course_name']); ?></h2>
                    <p style="color: var(--muted);"><?php echo htmlspecialchars($selected_course['department_name']); ?> • <?php echo $selected_course['credits']; ?> credits</p>
                </div>

                <!-- Available Reports -->
                <div class="card">
                    <h2>📊 Available Reports</h2>
                    <form method="POST">
                        <input type="hidden" name="course_id" value="<?php echo $selected_course_id; ?>">
                        
                        <div class="reports-grid">
                            <div class="report-card">
                                <div class="report-icon">📋</div>
                                <div class="report-title">Course Report</div>
                                <div class="report-desc">Complete course overview with student performance and statistics</div>
                                <button type="submit" name="generate_report" value="course" class="btn btn-primary">Generate Report</button>
                            </div>
                            
                            <div class="report-card">
                                <div class="report-icon">📅</div>
                                <div class="report-title">Attendance Report</div>
                                <div class="report-desc">Detailed attendance records and patterns for all students</div>
                                <button type="submit" name="generate_report" value="attendance" class="btn btn-success">Generate Report</button>
                            </div>
                            
                            <div class="report-card">
                                <div class="report-icon">📝</div>
                                <div class="report-title">Grades Report</div>
                                <div class="report-desc">Comprehensive grade book with all assessments and scores</div>
                                <button type="submit" name="generate_report" value="grades" class="btn btn-secondary">Generate Report</button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Recent Reports -->
                <div class="card">
                    <h2>📜 Generated Reports</h2>
                    <p style="color: var(--muted); text-align: center; padding: 2rem 0;">
                        No reports generated yet. Use the options above to create your first report.
                    </p>
                </div>

            <?php else: ?>
                <div class="card">
                    <h2>📊 No Course Selected</h2>
                    <p style="color: var(--muted); text-align: center; padding: 2rem 0;">
                        Please select a course from the dropdown above to generate reports.
                    </p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
