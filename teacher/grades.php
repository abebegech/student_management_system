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
$enrolled_students = [];
$grades = [];

if ($selected_course_id) {
    // Get course details
    $selected_course = $db->fetch("
        SELECT c.*, d.name as department_name
        FROM courses c
        JOIN departments d ON c.department_id = d.id
        WHERE c.id = :course_id AND c.teacher_id = :teacher_id
    ", ['course_id' => $selected_course_id, 'teacher_id' => $userId]);
    
    // Get enrolled students
    $enrolled_students = $db->fetchAll("
        SELECT e.id as enrollment_id, s.id as student_id, s.student_id as student_number,
               CONCAT(u.first_name, ' ', u.last_name) as student_name,
               u.email, e.final_grade
        FROM enrollments e
        JOIN students s ON e.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE e.course_id = :course_id AND e.status = 'enrolled'
        ORDER BY u.last_name, u.first_name
    ", ['course_id' => $selected_course_id]);
    
    // Get existing grades
    if (!empty($enrolled_students)) {
        $enrollment_ids = array_column($enrolled_students, 'enrollment_id');
        $placeholders = str_repeat('?,', count($enrollment_ids) - 1) . '?';
        
        $grades = $db->fetchAll("
            SELECT g.*, e.student_id
            FROM grades g
            JOIN enrollments e ON g.enrollment_id = e.id
            WHERE g.enrollment_id IN ($placeholders)
            ORDER BY g.graded_date DESC
        ", $enrollment_ids);
    }
}

// Handle grade submission
if (isset($_POST['submit_grade'])) {
    try {
        $data = [
            'enrollment_id' => $_POST['enrollment_id'],
            'assessment_type' => $_POST['assessment_type'],
            'assessment_name' => $_POST['assessment_name'],
            'score' => $_POST['score'],
            'max_score' => $_POST['max_score'],
            'weight' => $_POST['weight'],
            'graded_by' => $userId,
            'comments' => $_POST['comments'] ?? ''
        ];
        
        $sql = "INSERT INTO grades (enrollment_id, assessment_type, assessment_name, score, max_score, weight, graded_by, comments) 
                VALUES (:enrollment_id, :assessment_type, :assessment_name, :score, :max_score, :weight, :graded_by, :comments)";
        
        $gradeId = $db->insert($sql, $data);
        $auth->logActivity($userId, 'add_grade', 'grades', $gradeId, null, $data);
        
        $message = "Grade added successfully!";
        
        // Refresh data
        header("Location: grades.php?course=$selected_course_id&success=1");
        exit;
        
    } catch (Exception $e) {
        $error = "Failed to add grade: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Students | BDU Student System</title>
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
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 0.5rem;
            color: var(--text);
            font-weight: 600;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--bg);
            color: var(--text);
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
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
        
        .grade-form {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .grade-history {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .grade-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem;
            border-bottom: 1px solid var(--border);
        }
        
        .grade-item:last-child {
            border-bottom: none;
        }
        
        .grade-score {
            font-weight: bold;
            color: var(--primary);
        }
        
        @media (max-width: 1024px) {
            .sidebar-layout {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
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
                    <li><a href="#" class="active">📝 Grade Students</a></li>
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
                <h1 style="margin: 0; color: var(--text);">Grade Students</h1>
                <p style="margin: 0.5rem 0 0 0; color: var(--muted);">Manage student grades and assessments</p>
            </header>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">Grade added successfully!</div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Course Selection -->
            <div class="course-selector">
                <label for="course_select" style="display: block; margin-bottom: 0.5rem; color: var(--text); font-weight: 600;">Select Course:</label>
                <select id="course_select" onchange="window.location.href='grades.php?course=' + this.value">
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

                <!-- Add Grade Form -->
                <div class="card">
                    <h2>➕ Add New Grade</h2>
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="student">Student</label>
                                <select id="student" name="enrollment_id" required>
                                    <option value="">Select Student</option>
                                    <?php foreach ($enrolled_students as $student): ?>
                                        <option value="<?php echo $student['enrollment_id']; ?>">
                                            <?php echo htmlspecialchars($student['student_name'] . ' (' . $student['student_number'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="assessment_type">Assessment Type</label>
                                <select id="assessment_type" name="assessment_type" required>
                                    <option value="">Select Type</option>
                                    <option value="quiz">Quiz</option>
                                    <option value="assignment">Assignment</option>
                                    <option value="midterm">Midterm</option>
                                    <option value="final">Final</option>
                                    <option value="project">Project</option>
                                    <option value="participation">Participation</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="assessment_name">Assessment Name</label>
                                <input type="text" id="assessment_name" name="assessment_name" placeholder="e.g., Quiz 1, Midterm Exam" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="score">Score</label>
                                <input type="number" id="score" name="score" min="0" step="0.01" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="max_score">Max Score</label>
                                <input type="number" id="max_score" name="max_score" min="1" value="100" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="weight">Weight</label>
                                <input type="number" id="weight" name="weight" min="0.1" step="0.1" value="1.0" required>
                            </div>
                        </div>
                        
                        <div class="form-group" style="margin-top: 1rem;">
                            <label for="comments">Comments (Optional)</label>
                            <textarea id="comments" name="comments" rows="3" placeholder="Add any comments about this assessment..."></textarea>
                        </div>
                        
                        <button type="submit" name="submit_grade" class="btn btn-primary">Add Grade</button>
                    </form>
                </div>

                <!-- Recent Grades -->
                <div class="card">
                    <h2>📊 Recent Grades</h2>
                    <div class="grade-history">
                        <?php if (empty($grades)): ?>
                            <p style="color: var(--muted); text-align: center; padding: 2rem 0;">No grades recorded yet for this course.</p>
                        <?php else: ?>
                            <?php foreach ($grades as $grade): ?>
                                <div class="grade-item">
                                    <div>
                                        <strong><?php echo htmlspecialchars($grade['assessment_name']); ?></strong>
                                        <div style="color: var(--muted); font-size: 0.875rem;">
                                            <?php echo ucfirst($grade['assessment_type']); ?> • 
                                            <?php echo date('M j, Y H:i', strtotime($grade['graded_date'])); ?>
                                        </div>
                                        <?php if (!empty($grade['comments'])): ?>
                                            <div style="color: var(--muted); font-size: 0.75rem; margin-top: 0.25rem;">
                                                "<?php echo htmlspecialchars($grade['comments']); ?>"
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="grade-score">
                                        <?php echo $grade['score']; ?>/<?php echo $grade['max_score']; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>
                <div class="card">
                    <h2>📚 No Course Selected</h2>
                    <p style="color: var(--muted); text-align: center; padding: 2rem 0;">
                        Please select a course from the dropdown above to start grading students.
                    </p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
