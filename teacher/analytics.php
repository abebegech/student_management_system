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
    SELECT c.id, c.course_code, c.course_name, d.name as department_name
    FROM courses c
    JOIN departments d ON c.department_id = d.id
    WHERE c.teacher_id = :teacher_id AND c.is_active = 1
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
    
    // Get grade distribution data
    $grade_distribution = $db->fetchAll("
        SELECT 
            CASE 
                WHEN g.score >= 90 THEN 'A'
                WHEN g.score >= 80 THEN 'B'
                WHEN g.score >= 70 THEN 'C'
                WHEN g.score >= 60 THEN 'D'
                ELSE 'F'
            END as grade_letter,
            COUNT(*) as count
        FROM grades g
        JOIN enrollments e ON g.enrollment_id = e.id
        WHERE e.course_id = :course_id
        GROUP BY grade_letter
        ORDER BY grade_letter
    ", ['course_id' => $selected_course_id]);
    
    // Get attendance statistics
    $attendance_stats = $db->fetchAll("
        SELECT a.status, COUNT(*) as count
        FROM attendance a
        WHERE a.course_id = :course_id
        GROUP BY a.status
    ", ['course_id' => $selected_course_id]);
    
    // Get assessment type performance
    $assessment_performance = $db->fetchAll("
        SELECT g.assessment_type, AVG(g.score) as avg_score, COUNT(*) as count
        FROM grades g
        JOIN enrollments e ON g.enrollment_id = e.id
        WHERE e.course_id = :course_id
        GROUP BY g.assessment_type
        ORDER BY avg_score DESC
    ", ['course_id' => $selected_course_id]);
    
    // Get student performance summary
    $student_performance = $db->fetchAll("
        SELECT s.student_id, CONCAT(u.first_name, ' ', u.last_name) as student_name,
               AVG(g.score) as avg_score, COUNT(g.id) as grade_count,
               SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
               COUNT(a.id) as total_attendance
        FROM students s
        JOIN users u ON s.user_id = u.id
        JOIN enrollments e ON s.id = e.student_id
        LEFT JOIN grades g ON e.id = g.enrollment_id
        LEFT JOIN attendance a ON s.id = a.student_id AND a.course_id = :course_id
        WHERE e.course_id = :course_id AND e.status = 'enrolled'
        GROUP BY s.id, u.first_name, u.last_name
        ORDER BY avg_score DESC NULLS LAST
    ", ['course_id' => $selected_course_id]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics | BDU Student System</title>
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
        
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
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
            padding: 1rem;
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary);
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
        
        .performance-bar {
            background: var(--border);
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.25rem;
        }
        
        .performance-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            transition: width 0.3s ease;
        }
        
        @media (max-width: 1024px) {
            .sidebar-layout {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .analytics-grid {
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
                    <li><a href="#" class="active">📈 Analytics</a></li>
                    <li><a href="reports.php">📊 Reports</a></li>
                    <li><a href="profile.php">👤 Profile</a></li>
                    <li><a href="../logout.php">🚪 Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header style="margin-bottom: 2rem;">
                <h1 style="margin: 0; color: var(--text);">Course Analytics</h1>
                <p style="margin: 0.5rem 0 0 0; color: var(--muted);">Analyze student performance and attendance patterns</p>
            </header>

            <!-- Course Selection -->
            <div class="course-selector">
                <label for="course_select" style="display: block; margin-bottom: 0.5rem; color: var(--text); font-weight: 600;">Select Course:</label>
                <select id="course_select" onchange="window.location.href='analytics.php?course=' + this.value">
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

                <!-- Analytics Charts -->
                <div class="analytics-grid">
                    <!-- Grade Distribution -->
                    <div class="card">
                        <h2>📊 Grade Distribution</h2>
                        <div class="chart-container">
                            <canvas id="gradeChart"></canvas>
                        </div>
                    </div>

                    <!-- Attendance Overview -->
                    <div class="card">
                        <h2>📅 Attendance Overview</h2>
                        <div class="chart-container">
                            <canvas id="attendanceChart"></canvas>
                        </div>
                    </div>

                    <!-- Assessment Performance -->
                    <div class="card">
                        <h2>📝 Assessment Performance</h2>
                        <div class="chart-container">
                            <canvas id="assessmentChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Student Performance Table -->
                <div class="card">
                    <h2>👥 Student Performance Summary</h2>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Average Score</th>
                                <th>Grades Count</th>
                                <th>Attendance Rate</th>
                                <th>Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($student_performance)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 2rem; color: var(--muted);">
                                        No student data available for this course.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($student_performance as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                        <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                        <td>
                                            <?php if ($student['avg_score']): ?>
                                                <?php echo number_format($student['avg_score'], 1); ?>%
                                            <?php else: ?>
                                                <span style="color: var(--muted);">No grades</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $student['grade_count']; ?></td>
                                        <td>
                                            <?php if ($student['total_attendance'] > 0): ?>
                                                <?php 
                                                $attendance_rate = ($student['present_count'] / $student['total_attendance']) * 100;
                                                echo number_format($attendance_rate, 1); ?>%
                                            <?php else: ?>
                                                <span style="color: var(--muted);">No data</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($student['avg_score']): ?>
                                                <div class="performance-bar">
                                                    <div class="performance-fill" style="width: <?php echo min(100, $student['avg_score']); ?>%;"></div>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: var(--muted);">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php else: ?>
                <div class="card">
                    <h2>📈 No Course Selected</h2>
                    <p style="color: var(--muted); text-align: center; padding: 2rem 0;">
                        Please select a course from the dropdown above to view analytics.
                    </p>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Grade Distribution Chart
        <?php if ($selected_course && !empty($grade_distribution)): ?>
        const gradeCtx = document.getElementById('gradeChart').getContext('2d');
        const gradeChart = new Chart(gradeCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($grade_distribution, 'grade_letter')); ?>,
                datasets: [{
                    label: 'Number of Students',
                    data: <?php echo json_encode(array_column($grade_distribution, 'count')); ?>,
                    backgroundColor: [
                        '#10B981', // A
                        '#3B82F6', // B
                        '#F59E0B', // C
                        '#F97316', // D
                        '#EF4444'  // F
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#6B7280'
                        },
                        grid: {
                            color: '#374151'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#6B7280'
                        },
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        <?php endif; ?>

        // Attendance Chart
        <?php if ($selected_course && !empty($attendance_stats)): ?>
        const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
        const attendanceChart = new Chart(attendanceCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($attendance_stats, 'status')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($attendance_stats, 'count')); ?>,
                    backgroundColor: [
                        '#10B981', // present
                        '#EF4444', // absent
                        '#F59E0B', // late
                        '#6366F1'  // excused
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#6B7280'
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        // Assessment Performance Chart
        <?php if ($selected_course && !empty($assessment_performance)): ?>
        const assessmentCtx = document.getElementById('assessmentChart').getContext('2d');
        const assessmentChart = new Chart(assessmentCtx, {
            type: 'radar',
            data: {
                labels: <?php echo json_encode(array_map('ucfirst', array_column($assessment_performance, 'assessment_type'))); ?>,
                datasets: [{
                    label: 'Average Score',
                    data: <?php echo json_encode(array_column($assessment_performance, 'avg_score')); ?>,
                    backgroundColor: 'rgba(79, 70, 229, 0.2)',
                    borderColor: '#4F46E5',
                    borderWidth: 2,
                    pointBackgroundColor: '#4F46E5'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    r: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            color: '#6B7280'
                        },
                        grid: {
                            color: '#374151'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
