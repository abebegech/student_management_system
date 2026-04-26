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

// Get all grades with course information
$grades = $db->fetchAll("
    SELECT g.*, c.course_code, c.course_name, c.credits, d.name as department_name
    FROM grades g
    JOIN enrollments e ON g.enrollment_id = e.id
    JOIN courses c ON e.course_id = c.id
    JOIN departments d ON c.department_id = d.id
    WHERE e.student_id = :student_id
    ORDER BY g.graded_date DESC
", ['student_id' => $student['id']]);

// Calculate GPA
$total_grade_points = 0;
$total_credits = 0;

foreach ($grades as $grade) {
    $percentage = ($grade['score'] / $grade['max_score']) * 100;
    $grade_point = 0;
    
    if ($percentage >= 90) $grade_point = 4.0;
    elseif ($percentage >= 85) $grade_point = 3.7;
    elseif ($percentage >= 80) $grade_point = 3.3;
    elseif ($percentage >= 75) $grade_point = 3.0;
    elseif ($percentage >= 70) $grade_point = 2.7;
    elseif ($percentage >= 65) $grade_point = 2.3;
    elseif ($percentage >= 60) $grade_point = 2.0;
    elseif ($percentage >= 55) $grade_point = 1.7;
    elseif ($percentage >= 50) $grade_point = 1.3;
    elseif ($percentage >= 45) $grade_point = 1.0;
    else $grade_point = 0.0;
    
    $total_grade_points += $grade_point * $grade['credits'];
    $total_credits += $grade['credits'];
}

$calculated_gpa = $total_credits > 0 ? round($total_grade_points / $total_credits, 2) : 0.00;

// Group grades by course
$grades_by_course = [];
foreach ($grades as $grade) {
    $grades_by_course[$grade['course_code']][] = $grade;
}

// Calculate course averages
$course_averages = [];
foreach ($grades_by_course as $courseCode => $courseGrades) {
    $totalScore = 0;
    $totalMaxScore = 0;
    
    foreach ($courseGrades as $grade) {
        $totalScore += $grade['score'];
        $totalMaxScore += $grade['max_score'];
    }
    
    $course_averages[$courseCode] = ($totalMaxScore > 0) ? round(($totalScore / $totalMaxScore) * 100, 1) : 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Grades | BDU Student System</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .course-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: transform 0.2s;
        }
        
        .course-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .course-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .course-title {
            font-size: 1.25rem;
            font-weight: bold;
            color: var(--text);
        }
        
        .course-average {
            font-size: 1.5rem;
            font-weight: bold;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            color: white;
        }
        
        .average-excellent { background: #10B981; }
        .average-good { background: #3B82F6; }
        .average-average { background: #F59E0B; }
        .average-poor { background: #EF4444; }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 1rem;
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
                    <li><a href="#" class="active">📝 Grades</a></li>
                    <li><a href="attendance.php">📅 Attendance</a></li>
                    <li><a href="courses.php">📚 Courses</a></li>
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
                <h1 style="margin: 0; color: var(--text);">My Grades</h1>
                <p style="margin: 0.5rem 0 0 0; color: var(--muted);">View your academic performance and grade history</p>
            </header>

            <!-- Academic Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $calculated_gpa; ?></div>
                    <div class="stat-label">Current GPA</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($grades); ?></div>
                    <div class="stat-label">Total Grades</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($grades_by_course); ?></div>
                    <div class="stat-label">Courses</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">
                        <?php 
                        $avgPercentage = count($grades) > 0 ? 
                            round(array_sum(array_map(function($g) { return ($g['score'] / $g['max_score']) * 100; }, $grades)) / count($grades), 1) : 0;
                        echo $avgPercentage . '%';
                        ?>
                    </div>
                    <div class="stat-label">Average Score</div>
                </div>
            </div>

            <!-- Grade Distribution Chart -->
            <div class="card">
                <h2>📊 Grade Distribution</h2>
                <div class="chart-container">
                    <canvas id="gradeChart"></canvas>
                </div>
            </div>

            <!-- Course Performance -->
            <div class="card">
                <h2>📚 Course Performance</h2>
                <?php if (empty($grades_by_course)): ?>
                    <p style="color: var(--muted); text-align: center; padding: 2rem 0;">No grades recorded yet.</p>
                <?php else: ?>
                    <?php foreach ($grades_by_course as $courseCode => $courseGrades): ?>
                        <div class="course-card">
                            <div class="course-header">
                                <div>
                                    <div class="course-title"><?php echo htmlspecialchars($courseCode); ?></div>
                                    <p style="color: var(--muted); margin: 0.25rem 0;"><?php echo htmlspecialchars($courseGrades[0]['course_name']); ?></p>
                                </div>
                                <div class="course-average <?php 
                                    $avg = $course_averages[$courseCode];
                                    echo $avg >= 80 ? 'average-excellent' : 
                                         ($avg >= 60 ? 'average-good' : 
                                         ($avg >= 40 ? 'average-average' : 'average-poor'));
                                ?>">
                                    <?php echo $course_averages[$courseCode]; ?>%
                                </div>
                            </div>
                            
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Assessment</th>
                                        <th>Type</th>
                                        <th>Score</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($courseGrades as $grade): ?>
                                        <tr>
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
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Detailed Grades Table -->
            <div class="card">
                <h2>📋 All Grades</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Assessment</th>
                            <th>Type</th>
                            <th>Score</th>
                            <th>Grade</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($grades)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 2rem; color: var(--muted);">
                                    No grades recorded yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($grades as $grade): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($grade['course_code']); ?></td>
                                    <td><?php echo htmlspecialchars($grade['assessment_name']); ?></td>
                                    <td><?php echo ucfirst($grade['assessment_type']); ?></td>
                                    <td><?php echo $grade['score']; ?>/<?php echo $grade['max_score']; ?></td>
                                    <td>
                                        <?php 
                                        $percentage = ($grade['score'] / $grade['max_score']) * 100;
                                        $letterGrade = '';
                                        if ($percentage >= 90) $letterGrade = 'A';
                                        elseif ($percentage >= 85) $letterGrade = 'A-';
                                        elseif ($percentage >= 80) $letterGrade = 'B+';
                                        elseif ($percentage >= 75) $letterGrade = 'B';
                                        elseif ($percentage >= 70) $letterGrade = 'B-';
                                        elseif ($percentage >= 65) $letterGrade = 'C+';
                                        elseif ($percentage >= 60) $letterGrade = 'C';
                                        elseif ($percentage >= 55) $letterGrade = 'C-';
                                        elseif ($percentage >= 50) $letterGrade = 'D';
                                        else $letterGrade = 'F';
                                        
                                        $gradeClass = $percentage >= 80 ? 'grade-excellent' : 
                                                     ($percentage >= 60 ? 'grade-good' : 
                                                     ($percentage >= 40 ? 'grade-average' : 'grade-poor'));
                                        ?>
                                        <span class="<?php echo $gradeClass; ?>"><?php echo $letterGrade; ?></span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($grade['graded_date'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
        // Grade Distribution Chart
        const gradeCtx = document.getElementById('gradeChart').getContext('2d');
        const gradeChart = new Chart(gradeCtx, {
            type: 'doughnut',
            data: {
                labels: ['A (90-100%)', 'B (70-89%)', 'C (50-69%)', 'D/F (<50%)'],
                datasets: [{
                    data: [
                        <?php 
                        $excellent = 0; $good = 0; $average = 0; $poor = 0;
                        foreach ($grades as $grade) {
                            $percentage = ($grade['score'] / $grade['max_score']) * 100;
                            if ($percentage >= 90) $excellent++;
                            elseif ($percentage >= 70) $good++;
                            elseif ($percentage >= 50) $average++;
                            else $poor++;
                        }
                        echo "$excellent, $good, $average, $poor";
                        ?>
                    ],
                    backgroundColor: ['#10B981', '#3B82F6', '#F59E0B', '#EF4444'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#6B7280' }
                    }
                }
            }
        });
    </script>
</body>
</html>
