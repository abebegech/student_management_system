<?php
session_start();
require_once '../includes/Auth.php';
require_once '../includes/Database.php';

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

// Get enrolled courses
$enrolled_courses = $db->fetchAll("
    SELECT e.*, c.course_code, c.course_name, c.credits, c.description, d.name as department_name,
           CONCAT(u.first_name, ' ', u.last_name) as teacher_name, u.email as teacher_email
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    JOIN departments d ON c.department_id = d.id
    JOIN users u ON c.teacher_id = u.id
    WHERE e.student_id = :student_id AND e.status = 'enrolled'
    ORDER BY c.course_code
", ['student_id' => $student['id']]);

// Get course statistics
$total_credits = array_sum(array_column($enrolled_courses, 'credits'));
$total_courses = count($enrolled_courses);

// Get grades for each course
$course_grades = [];
foreach ($enrolled_courses as $course) {
    $grades = $db->fetchAll("
        SELECT g.*, a.assessment_name, a.assessment_type
        FROM grades g
        JOIN enrollments e ON g.enrollment_id = e.id
        WHERE e.course_id = :course_id AND e.student_id = :student_id
        ORDER BY g.graded_date DESC
    ", ['course_id' => $course['course_id'], 'student_id' => $student['id']]);
    
    $course_grades[$course['course_id']] = $grades;
}

// Calculate course averages
$course_averages = [];
foreach ($course_grades as $courseCode => $grades) {
    if (!empty($grades)) {
        $totalScore = 0;
        $totalMaxScore = 0;
        
        foreach ($grades as $grade) {
            $totalScore += $grade['score'];
            $totalMaxScore += $grade['max_score'];
        }
        
        $course_averages[$courseCode] = ($totalMaxScore > 0) ? round(($totalScore / $totalMaxScore) * 100, 1) : 0;
    } else {
        $course_averages[$courseCode] = 0;
    }
}

// Get available courses for enrollment
$available_courses = $db->fetchAll("
    SELECT c.*, d.name as department_name
    FROM courses c
    JOIN departments d ON c.department_id = d.id
    WHERE c.is_active = 1 
    AND c.id NOT IN (
        SELECT course_id FROM enrollments WHERE student_id = :student_id AND status = 'enrolled'
    )
    ORDER BY c.course_code
", ['student_id' => $student['id']]);

// Handle course enrollment
if (isset($_POST['enroll_course'])) {
    try {
        $courseId = $_POST['course_id'];
        
        // Check if already enrolled
        $existing = $db->fetch("
            SELECT id FROM enrollments 
            WHERE student_id = :student_id AND course_id = :course_id
        ", ['student_id' => $student['id'], 'course_id' => $courseId]);
        
        if ($existing) {
            throw new Exception("Already enrolled in this course");
        }
        
        // Check course availability and prerequisites
        $course = $db->fetch("
            SELECT * FROM courses WHERE id = :course_id AND is_active = 1
        ", ['course_id' => $courseId]);
        
        if (!$course) {
            throw new Exception("Course not available");
        }
        
        // Check if student meets year level requirement
        if ($student['year_level'] < $course['year_level']) {
            throw new Exception("This course requires Year " . $course['year_level'] . " or higher");
        }
        
        // Enroll in course
        $enrollmentData = [
            'student_id' => $student['id'],
            'course_id' => $courseId,
            'enrollment_date' => date('Y-m-d H:i:s'),
            'status' => 'enrolled'
        ];
        
        $sql = "INSERT INTO enrollments (student_id, course_id, enrollment_date, status) 
                VALUES (:student_id, :course_id, :enrollment_date, :status)";
        
        $enrollmentId = $db->insert($sql, $enrollmentData);
        
        $auth->logActivity($userId, 'enroll_course', 'enrollments', $enrollmentId, null, $enrollmentData);
        
        $message = "Successfully enrolled in course!";
        
        // Redirect to refresh data
        header("Location: courses.php?success=1");
        exit;
        
    } catch (Exception $e) {
        $error = "Failed to enroll: " . $e->getMessage();
    }
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
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
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
        
        .course-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .course-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            overflow: hidden;
        }
        
        .course-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.15);
        }
        
        .course-header {
            margin-bottom: 1rem;
        }
        
        .course-code {
            font-size: 1.25rem;
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }
        
        .course-name {
            font-size: 1rem;
            color: var(--text);
            margin-bottom: 0.5rem;
        }
        
        .course-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            color: var(--muted);
        }
        
        .teacher-info {
            margin-bottom: 1rem;
        }
        
        .teacher-name {
            font-weight: 600;
            color: var(--text);
        }
        
        .performance-bar {
            background: var(--border);
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 1rem;
        }
        
        .performance-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            transition: width 0.3s ease;
        }
        
        .grade-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: bold;
            color: white;
        }
        
        .grade-excellent { background: #10B981; }
        .grade-good { background: #3B82F6; }
        .grade-average { background: #F59E0B; }
        .grade-none { background: #6B7280; }
        
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
        .btn-sm { padding: 0.5rem 1rem; font-size: 0.875rem; }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text);
            font-weight: 600;
        }
        
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--bg);
            color: var(--text);
        }
        
        @media (max-width: 1024px) {
            .sidebar-layout {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .course-grid {
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
                    <li><a href="#" class="active">📚 Courses</a></li>
                    <li><a href="financial.php">💰 Financial</a></li>
                    <li><a href="documents.php">📄 Documents</a></li>
                    <li><a href="profile.php">👤 Profile</a></li>
                    <li><a href="../logout.php">🚪 Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header style="margin-bottom: 2rem;">
                <h1 style="margin: 0; color: var(--text);">My Courses</h1>
                <p style="margin: 0.5rem 0 0 0; color: var(--muted);">View enrolled courses and manage your academic schedule</p>
            </header>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Course Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_courses; ?></div>
                    <div class="stat-label">Enrolled Courses</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_credits; ?></div>
                    <div class="stat-label">Total Credits</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">
                        <?php 
                        $avgGrade = count($course_averages) > 0 ? 
                            round(array_sum($course_averages) / count($course_averages), 1) : 0;
                        echo $avgGrade . '%';
                        ?>
                    </div>
                    <div class="stat-label">Average Grade</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $student['year_level']; ?></div>
                    <div class="stat-label">Current Year</div>
                </div>
            </div>

            <!-- Enrolled Courses -->
            <div class="card">
                <h2>📚 Enrolled Courses</h2>
                <?php if (empty($enrolled_courses)): ?>
                    <p style="color: var(--muted); text-align: center; padding: 2rem 0;">You are not enrolled in any courses yet.</p>
                <?php else: ?>
                    <div class="course-grid">
                        <?php foreach ($enrolled_courses as $course): ?>
                            <div class="course-card">
                                <div class="grade-badge <?php 
                                    $avg = $course_averages[$course['course_code']];
                                    echo $avg >= 80 ? 'grade-excellent' : 
                                         ($avg >= 60 ? 'grade-good' : 
                                         ($avg > 0 ? 'grade-average' : 'grade-none'));
                                ?>">
                                    <?php echo $avg > 0 ? $avg . '%' : 'NG'; ?>
                                </div>
                                
                                <div class="course-header">
                                    <div class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></div>
                                    <div class="course-name"><?php echo htmlspecialchars($course['course_name']); ?></div>
                                </div>
                                
                                <div class="course-meta">
                                    <span><?php echo $course['credits']; ?> credits</span>
                                    <span><?php echo htmlspecialchars($course['department_name']); ?></span>
                                </div>
                                
                                <div class="teacher-info">
                                    <div class="teacher-name"><?php echo htmlspecialchars($course['teacher_name']); ?></div>
                                    <div style="color: var(--muted); font-size: 0.875rem;"><?php echo htmlspecialchars($course['teacher_email']); ?></div>
                                </div>
                                
                                <div class="performance-bar">
                                    <div class="performance-fill" style="width: <?php echo $course_averages[$course['course_code']]; ?>%;"></div>
                                </div>
                                
                                <p style="color: var(--muted); font-size: 0.875rem; margin-bottom: 1rem;">
                                    <?php echo htmlspecialchars(substr($course['description'] ?? '', 0, 100)); ?>...
                                </p>
                                
                                <div style="display: flex; gap: 0.5rem;">
                                    <a href="view_course.php?id=<?php echo $course['course_id']; ?>" class="btn btn-primary btn-sm">View Details</a>
                                    <a href="course_grades.php?id=<?php echo $course['course_id']; ?>" class="btn btn-secondary btn-sm">Grades</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Available Courses for Enrollment -->
            <?php if (!empty($available_courses)): ?>
                <div class="card">
                    <h2>➕ Available Courses</h2>
                    <form method="POST">
                        <div class="form-group">
                            <label for="course_id">Select a course to enroll:</label>
                            <select id="course_id" name="course_id" required>
                                <option value="">Choose a course...</option>
                                <?php foreach ($available_courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>">
                                        <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                        (<?php echo $course['credits']; ?> credits, Year <?php echo $course['year_level']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" name="enroll_course" class="btn btn-primary">Enroll in Course</button>
                    </form>
                    
                    <div style="margin-top: 2rem;">
                        <h3>📋 Course Catalog</h3>
                        <div class="course-grid">
                            <?php foreach ($available_courses as $course): ?>
                                <div class="course-card" style="opacity: 0.8;">
                                    <div class="course-header">
                                        <div class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></div>
                                        <div class="course-name"><?php echo htmlspecialchars($course['course_name']); ?></div>
                                    </div>
                                    
                                    <div class="course-meta">
                                        <span><?php echo $course['credits']; ?> credits</span>
                                        <span>Year <?php echo $course['year_level']; ?></span>
                                    </div>
                                    
                                    <p style="color: var(--muted); font-size: 0.875rem;">
                                        <?php echo htmlspecialchars($course['department_name']); ?>
                                    </p>
                                    
                                    <p style="color: var(--muted); font-size: 0.875rem; margin-top: 0.5rem;">
                                        <?php echo htmlspecialchars(substr($course['description'] ?? '', 0, 80)); ?>...
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Course Schedule -->
            <div class="card">
                <h2>📅 Course Schedule</h2>
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
                                <td colspan="5" style="text-align: center; padding: 2rem; color: var(--muted;">
                                    No courses enrolled.
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
        </main>
    </div>
</body>
</html>
