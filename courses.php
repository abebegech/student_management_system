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
    SELECT c.*, d.name as department_name,
           COUNT(e.student_id) as enrolled_students
    FROM courses c
    JOIN departments d ON c.department_id = d.id
    LEFT JOIN enrollments e ON c.id = e.course_id AND e.status = 'enrolled'
    WHERE c.teacher_id = :teacher_id AND c.is_active = 1
    GROUP BY c.id
    ORDER BY c.course_name
", ['teacher_id' => $userId]);

// Get enrolled students for each course
$course_students = [];
foreach ($courses as $course) {
    $students = $db->fetchAll("
        SELECT s.student_id, CONCAT(u.first_name, ' ', u.last_name) as student_name,
               u.email, e.enrollment_date, e.final_grade
        FROM enrollments e
        JOIN students s ON e.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE e.course_id = :course_id AND e.status = 'enrolled'
        ORDER BY u.last_name, u.first_name
    ", ['course_id' => $course['id']]);
    
    $course_students[$course['id']] = $students;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses | BDU Student System</title>
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
        
        .course-grid {
            display: grid;
            gap: 1.5rem;
        }
        
        .course-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .course-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .course-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }
        
        .course-info h3 {
            margin: 0 0 0.5rem 0;
            color: var(--primary);
            font-size: 1.25rem;
        }
        
        .course-info p {
            margin: 0.25rem 0;
            color: var(--muted);
            font-size: 0.875rem;
        }
        
        .course-stats {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .stat {
            text-align: center;
            padding: 0.5rem 1rem;
            background: var(--bg);
            border-radius: 6px;
            border: 1px solid var(--border);
        }
        
        .stat-value {
            font-size: 1.25rem;
            font-weight: bold;
            color: var(--primary);
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: var(--muted);
        }
        
        .student-list {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 0.5rem;
        }
        
        .student-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem;
            border-bottom: 1px solid var(--border);
        }
        
        .student-item:last-child {
            border-bottom: none;
        }
        
        .student-name {
            font-weight: 500;
        }
        
        .student-grade {
            background: var(--primary);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.875rem;
        }
        
        .btn-primary { background: var(--primary); color: white; }
        .btn-secondary { background: var(--secondary); color: white; }
        .btn-success { background: #10B981; color: white; }
        .btn-warning { background: #F59E0B; color: white; }
        
        .course-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        @media (max-width: 1024px) {
            .sidebar-layout {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .course-stats {
                flex-direction: column;
                gap: 0.5rem;
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
                    <li><a href="#" class="active">📚 My Courses</a></li>
                    <li><a href="grades.php">📝 Grade Students</a></li>
                    <li><a href="attendance.php">📅 Mark Attendance</a></li>
                    <li><a href="analytics.php">📈 Analytics</a></li>
                    <li><a href="reports.php">📊 Reports</a></li>
                    <li><a href="profile.php">👤 Profile</a></li>
                    <li><a href="../logout.php">🚪 Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header style="margin-bottom: 2rem;">
                <h1 style="margin: 0; color: var(--text);">My Courses</h1>
                <p style="margin: 0.5rem 0 0 0; color: var(--muted);">Manage your courses and track student progress</p>
            </header>

            <?php if (empty($courses)): ?>
                <div class="card">
                    <h2>📚 No Courses Assigned</h2>
                    <p style="color: var(--muted); text-align: center; padding: 2rem 0;">
                        You haven't been assigned any courses yet. Please contact the administrator to get course assignments.
                    </p>
                </div>
            <?php else: ?>
                <div class="course-grid">
                    <?php foreach ($courses as $course): ?>
                        <div class="course-card">
                            <div class="course-header">
                                <div class="course-info">
                                    <h3><?php echo htmlspecialchars($course['course_code']); ?></h3>
                                    <p><?php echo htmlspecialchars($course['course_name']); ?></p>
                                    <p><?php echo htmlspecialchars($course['department_name']); ?> • <?php echo $course['credits']; ?> credits</p>
                                </div>
                            </div>
                            
                            <div class="course-stats">
                                <div class="stat">
                                    <div class="stat-value"><?php echo $course['enrolled_students']; ?></div>
                                    <div class="stat-label">Students</div>
                                </div>
                                <div class="stat">
                                    <div class="stat-value">Year <?php echo $course['year_level']; ?></div>
                                    <div class="stat-label">Level</div>
                                </div>
                                <div class="stat">
                                    <div class="stat-value"><?php echo $course['semester'] == 1 ? 'Fall' : 'Spring'; ?></div>
                                    <div class="stat-label">Semester</div>
                                </div>
                            </div>
                            
                            <div class="student-list">
                                <?php if (empty($course_students[$course['id']])): ?>
                                    <p style="color: var(--muted); text-align: center; padding: 1rem;">No students enrolled</p>
                                <?php else: ?>
                                    <?php foreach ($course_students[$course['id']] as $student): ?>
                                        <div class="student-item">
                                            <div class="student-name">
                                                <?php echo htmlspecialchars($student['student_name']); ?>
                                                <div style="font-size: 0.75rem; color: var(--muted);">
                                                    <?php echo htmlspecialchars($student['student_id']); ?>
                                                </div>
                                            </div>
                                            <div>
                                                <?php if ($student['final_grade']): ?>
                                                    <span class="student-grade"><?php echo $student['final_grade']; ?></span>
                                                <?php else: ?>
                                                    <span style="color: var(--muted); font-size: 0.75rem;">No grade</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="course-actions">
                                <a href="grades.php?course=<?php echo $course['id']; ?>" class="btn btn-primary">📝 Grade</a>
                                <a href="attendance.php?course=<?php echo $course['id']; ?>" class="btn btn-success">📅 Attendance</a>
                                <a href="reports.php?course=<?php echo $course['id']; ?>" class="btn btn-secondary">📊 Report</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
