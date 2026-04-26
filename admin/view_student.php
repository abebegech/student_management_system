<?php
session_start();
require_once '../includes/Auth.php';
require_once '../includes/Database.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();

// Get student ID from URL
$studentId = $_GET['id'] ?? null;

if (!$studentId) {
    header('Location: students.php');
    exit;
}

// Get student details
$student = $db->fetch("
    SELECT s.*, u.username, u.email, u.phone, u.first_name, u.last_name, u.created_at as user_created_at,
           d.name as department_name
    FROM students s
    JOIN users u ON s.user_id = u.id
    JOIN departments d ON s.department_id = d.id
    WHERE s.id = :id
", ['id' => $studentId]);

if (!$student) {
    header('Location: students.php');
    exit;
}

// Get enrolled courses
$enrolled_courses = $db->fetchAll("
    SELECT e.*, c.course_code, c.course_name, c.credits, d.name as department_name,
           CONCAT(u.first_name, ' ', u.last_name) as teacher_name
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    JOIN departments d ON c.department_id = d.id
    JOIN users u ON c.teacher_id = u.id
    WHERE e.student_id = :student_id AND e.status = 'enrolled'
    ORDER BY c.course_code
", ['student_id' => $studentId]);

// Get recent grades
$recent_grades = $db->fetchAll("
    SELECT g.*, c.course_code, c.course_name
    FROM grades g
    JOIN enrollments e ON g.enrollment_id = e.id
    JOIN courses c ON e.course_id = c.id
    WHERE e.student_id = :student_id
    ORDER BY g.graded_date DESC
    LIMIT 10
", ['student_id' => $studentId]);

// Get attendance statistics
$attendance_stats = $db->fetch("
    SELECT 
        COUNT(*) as total_sessions,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count,
        SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused_count
    FROM attendance a
    WHERE a.student_id = :student_id
", ['student_id' => $studentId]);

// Get financial records
$financial_records = $db->fetchAll("
    SELECT fr.*, d.name as department_name
    FROM financial_records fr
    JOIN students s ON fr.student_id = s.id
    JOIN departments d ON s.department_id = d.id
    WHERE fr.student_id = :student_id
    ORDER BY fr.due_date DESC
", ['student_id' => $studentId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Student | BDU Student System</title>
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
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            font-weight: bold;
            flex-shrink: 0;
        }
        
        .profile-info h1 {
            margin: 0 0 0.5rem 0;
            color: var(--text);
            font-size: 1.8rem;
        }
        
        .profile-info p {
            margin: 0.25rem 0;
            color: var(--muted);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: var(--muted);
            font-size: 0.875rem;
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
        
        .grade-excellent { color: #10B981; font-weight: bold; }
        .grade-good { color: #3B82F6; font-weight: bold; }
        .grade-average { color: #F59E0B; font-weight: bold; }
        .grade-poor { color: #EF4444; font-weight: bold; }
        
        .status-paid { color: #10B981; font-weight: bold; }
        .status-pending { color: #F59E0B; font-weight: bold; }
        .status-overdue { color: #EF4444; font-weight: bold; }
        .status-partial { color: #3B82F6; font-weight: bold; }
        
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
            
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar-layout">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div style="text-align: center; margin-bottom: 2rem;">
                <div class="logo-circle" style="margin: 0 auto 1rem;">BDU</div>
                <h3 style="margin: 0; color: var(--text);">Admin Panel</h3>
                <p style="margin: 0.25rem 0 0 0; color: var(--muted); font-size: 0.875rem;">
                    <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </p>
            </div>
            
            <nav>
                <ul class="sidebar-nav">
                    <li><a href="dashboard.php">📊 Dashboard</a></li>
                    <li><a href="users.php">👥 User Management</a></li>
                    <li><a href="#" class="active">🎓 Students</a></li>
                    <li><a href="teachers.php">👨‍🏫 Teachers</a></li>
                    <li><a href="courses.php">📚 Courses</a></li>
                    <li><a href="departments.php">🏢 Departments</a></li>
                    <li><a href="financial_ledger.php">💰 Financial Ledger</a></li>
                    <li><a href="reports.php">📈 Reports</a></li>
                    <li><a href="logs.php">📋 Activity Logs</a></li>
                    <li><a href="class_assignment.php">📅 Class Assignment</a></li>
                    <li><a href="backup.php">💾 Backup</a></li>
                    <li><a href="../logout.php">🚪 Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header style="margin-bottom: 2rem;">
                <h1 style="margin: 0; color: var(--text);">View Student Profile</h1>
                <p style="margin: 0.5rem 0 0 0; color: var(--muted);">Detailed student information and academic records</p>
            </header>

            <!-- Profile Header -->
            <div class="card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                    </div>
                    <div class="profile-info">
                        <h1><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h1>
                        <p><strong>Student ID:</strong> <?php echo htmlspecialchars($student['student_id']); ?></p>
                        <p><strong>Department:</strong> <?php echo htmlspecialchars($student['department_name']); ?></p>
                        <p><strong>Year:</strong> Year <?php echo $student['year_level']; ?> • Semester <?php echo $student['semester']; ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($student['phone'] ?? 'Not provided'); ?></p>
                        <p><strong>Status:</strong> <span style="color: #10B981; font-weight: bold;"><?php echo ucfirst($student['status']); ?></span></p>
                        <p><strong>Member Since:</strong> <?php echo date('F j, Y', strtotime($student['user_created_at'])); ?></p>
                    </div>
                </div>
                
                <div style="margin-top: 1rem;">
                    <a href="edit_student.php?id=<?php echo $student['id']; ?>" class="btn btn-primary">✏️ Edit Student</a>
                    <a href="students.php" class="btn btn-secondary">← Back to Students</a>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $student['gpa']; ?></div>
                    <div class="stat-label">Current GPA</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($enrolled_courses); ?></div>
                    <div class="stat-label">Enrolled Courses</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">
                        <?php 
                        $attendance_rate = $attendance_stats['total_sessions'] > 0 ? 
                            round(($attendance_stats['present_count'] / $attendance_stats['total_sessions']) * 100, 1) : 0;
                        echo $attendance_rate . '%';
                        ?>
                    </div>
                    <div class="stat-label">Attendance Rate</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($recent_grades); ?></div>
                    <div class="stat-label">Total Grades</div>
                </div>
            </div>

            <!-- Recent Grades -->
            <div class="card">
                <h2>📝 Recent Grades</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Assessment</th>
                            <th>Type</th>
                            <th>Score</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_grades)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 2rem; color: var(--muted);">
                                    No grades recorded yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_grades as $grade): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($grade['course_code']); ?></td>
                                    <td><?php echo htmlspecialchars($grade['assessment_name']); ?></td>
                                    <td><?php echo ucfirst($grade['assessment_type']); ?></td>
                                    <td>
                                        <?php 
                                        $percentage = ($grade['score'] / $grade['max_score']) * 100;
                                        $gradeClass = $percentage >= 80 ? 'grade-excellent' : 
                                                     ($percentage >= 60 ? 'grade-good' : 
                                                     ($percentage >= 40 ? 'grade-average' : 'grade-poor'));
                                        ?>
                                        <span class="<?php echo $gradeClass; ?>">
                                            <?php echo $grade['score']; ?>/<?php echo $grade['max_score']; ?> (<?php echo round($percentage); ?>%)
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($grade['graded_date'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Enrolled Courses -->
            <div class="card">
                <h2>📚 Enrolled Courses</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Course Code</th>
                            <th>Course Name</th>
                            <th>Credits</th>
                            <th>Teacher</th>
                            <th>Department</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($enrolled_courses)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 2rem; color: var(--muted);">
                                    Not enrolled in any courses.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($enrolled_courses as $course): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($course['course_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                    <td><?php echo $course['credits']; ?></td>
                                    <td><?php echo htmlspecialchars($course['teacher_name']); ?></td>
                                    <td><?php echo htmlspecialchars($course['department_name']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Financial Records -->
            <div class="card">
                <h2>💰 Financial Records</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Fee Type</th>
                            <th>Amount</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Due Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($financial_records)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 2rem; color: var(--muted;">
                                    No financial records found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($financial_records as $record): ?>
                                <tr>
                                    <td><?php echo ucfirst($record['fee_type']); ?></td>
                                    <td>$<?php echo number_format($record['amount'], 2); ?></td>
                                    <td>$<?php echo number_format($record['paid_amount'], 2); ?></td>
                                    <td>$<?php echo number_format($record['amount'] - $record['paid_amount'], 2); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($record['due_date'])); ?></td>
                                    <td><span class="status-<?php echo $record['status']; ?>"><?php echo ucfirst($record['status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
