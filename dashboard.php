<?php
session_start();
require_once '../includes/Auth.php';
require_once '../includes/Database.php';

$auth = new Auth();
$auth->requireRole('teacher');

$db = Database::getInstance();
$userId = $auth->getUserId();

// Get teacher information
$teacher = $db->fetch("
    SELECT u.*, d.name as department_name
    FROM users u
    LEFT JOIN departments d ON u.id = d.head_of_department
    WHERE u.id = :user_id AND u.role = 'teacher'
", ['user_id' => $userId]);

// Get courses taught by this teacher
$courses = $db->fetchAll("
    SELECT c.*, COUNT(e.student_id) as enrolled_students
    FROM courses c
    LEFT JOIN enrollments e ON c.id = e.course_id AND e.status = 'enrolled'
    WHERE c.teacher_id = :teacher_id AND c.is_active = 1
    GROUP BY c.id
    ORDER BY c.course_name
", ['teacher_id' => $userId]);

// Get recent grading activity
$recent_grades = $db->fetchAll("
    SELECT g.*, c.course_code, c.course_name, 
           CONCAT(u.first_name, ' ', u.last_name) as student_name,
           s.student_id
    FROM grades g
    JOIN enrollments e ON g.enrollment_id = e.id
    JOIN courses c ON e.course_id = c.id
    JOIN students s ON e.student_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE c.teacher_id = :teacher_id
    ORDER BY g.graded_date DESC
    LIMIT 10
", ['teacher_id' => $userId]);

// Get attendance statistics for teacher's courses
$attendance_stats = $db->fetchAll("
    SELECT c.course_code, c.course_name,
           COUNT(DISTINCT a.student_id) as total_students,
           COUNT(*) as total_sessions,
           SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
           SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
           ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as attendance_percentage
    FROM courses c
    LEFT JOIN attendance a ON c.id = a.course_id
    WHERE c.teacher_id = :teacher_id
    GROUP BY c.id, c.course_code, c.course_name
    ORDER BY attendance_percentage DESC
", ['teacher_id' => $userId]);

// Get grade distribution for teacher's courses
$grade_distribution = $db->fetchAll("
    SELECT c.course_code, c.course_name,
           AVG(g.score) as avg_score,
           MIN(g.score) as min_score,
           MAX(g.score) as max_score,
           COUNT(g.id) as total_grades
    FROM courses c
    JOIN enrollments e ON c.id = e.course_id
    JOIN grades g ON e.id = g.enrollment_id
    WHERE c.teacher_id = :teacher_id
    GROUP BY c.id, c.course_code, c.course_name
    ORDER BY avg_score DESC
", ['teacher_id' => $userId]);

// Get pending grading tasks
$pending_grading = $db->fetchAll("
    SELECT c.course_code, c.course_name, 
           COUNT(DISTINCT e.student_id) as students_needing_grades,
           COUNT(g.id) as graded_submissions
    FROM courses c
    JOIN enrollments e ON c.id = e.course_id AND e.status = 'enrolled'
    LEFT JOIN grades g ON e.id = g.enrollment_id
    WHERE c.teacher_id = :teacher_id
    GROUP BY c.id, c.course_code, c.course_name
    HAVING students_needing_grades > graded_submissions OR graded_submissions = 0
    ORDER BY students_needing_grades DESC
", ['teacher_id' => $userId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard | BDU Student System</title>
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
        
        .teacher-profile {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
        }
        
        .profile-info h2 {
            margin: 0 0 0.5rem 0;
            color: var(--text);
        }
        
        .profile-info p {
            margin: 0.25rem 0;
            color: var(--muted);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary);
            margin: 0.5rem 0;
        }
        
        .stat-label {
            color: var(--muted);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
        }
        
        .card h3 {
            margin: 0 0 1rem 0;
            color: var(--text);
        }
        
        .course-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .course-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
        }
        
        .course-item:last-child {
            border-bottom: none;
        }
        
        .grade-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
        }
        
        .grade-item:last-child {
            border-bottom: none;
        }
        
        .grade-score {
            font-weight: bold;
            color: var(--primary);
        }
        
        .pending-alert {
            background: var(--warning);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        @media (max-width: 1024px) {
            .sidebar-layout {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .teacher-profile {
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
                <div class="profile-avatar" style="margin: 0 auto 1rem;">
                    <?php echo strtoupper(substr($teacher['first_name'], 0, 1) . substr($teacher['last_name'], 0, 1)); ?>
                </div>
                <h3 style="margin: 0; color: var(--text);"><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></h3>
                <p style="margin: 0.25rem 0 0 0; color: var(--muted); font-size: 0.875rem;">
                    Faculty Member
                </p>
            </div>
            
            <nav>
                <ul class="sidebar-nav">
                    <li><a href="#" class="active">📊 Dashboard</a></li>
                    <li><a href="courses.php">📚 My Courses</a></li>
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
            <!-- Teacher Profile Card -->
            <section class="teacher-profile">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($teacher['first_name'], 0, 1) . substr($teacher['last_name'], 0, 1)); ?>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></h2>
                    <p><strong>Department:</strong> <?php echo htmlspecialchars($teacher['department_name'] ?? 'Not Assigned'); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($teacher['email']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($teacher['phone'] ?? 'Not Provided'); ?></p>
                </div>
            </section>

            <!-- Statistics Cards -->
            <section class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Courses</div>
                    <div class="stat-value"><?php echo count($courses); ?></div>
                    <div style="color: var(--muted); font-size: 0.875rem;">Active courses</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Total Students</div>
                    <div class="stat-value"><?php echo array_sum(array_column($courses, 'enrolled_students')); ?></div>
                    <div style="color: var(--muted); font-size: 0.875rem;">Across all courses</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Pending Grades</div>
                    <div class="stat-value">
                        <?php 
                        $total_pending = array_sum(array_column($pending_grading, 'students_needing_grades')) - 
                                       array_sum(array_column($pending_grading, 'graded_submissions'));
                        echo max(0, $total_pending);
                        ?>
                    </div>
                    <div style="color: var(--muted); font-size: 0.875rem;">Awaiting grading</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Avg Attendance</div>
                    <div class="stat-value">
                        <?php 
                        $avg_attendance = count($attendance_stats) > 0 ? 
                            round(array_sum(array_column($attendance_stats, 'attendance_percentage')) / count($attendance_stats), 1) : 0;
                        echo $avg_attendance . '%';
                        ?>
                    </div>
                    <div style="color: var(--muted); font-size: 0.875rem;">Across courses</div>
                </div>
            </section>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- My Courses -->
                <div class="card">
                    <h3>📚 My Courses</h3>
                    <?php if (empty($courses)): ?>
                        <p style="color: var(--muted); text-align: center; padding: 2rem 0;">No courses assigned</p>
                    <?php else: ?>
                        <ul class="course-list">
                            <?php foreach ($courses as $course): ?>
                                <li class="course-item">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <strong><?php echo htmlspecialchars($course['course_code']); ?></strong>
                                            <div style="color: var(--muted); font-size: 0.875rem;">
                                                <?php echo htmlspecialchars($course['course_name']); ?>
                                            </div>
                                        </div>
                                        <div style="text-align: right;">
                                            <span style="color: var(--primary); font-weight: bold;">
                                                <?php echo $course['enrolled_students']; ?> students
                                            </span>
                                            <div style="color: var(--muted); font-size: 0.75rem;">
                                                <?php echo $course['credits']; ?> credits
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <!-- Pending Grading -->
                <div class="card">
                    <h3>📝 Pending Grading</h3>
                    <?php if (empty($pending_grading)): ?>
                        <p style="color: var(--success); text-align: center; padding: 2rem 0;">All grades up to date!</p>
                    <?php else: ?>
                        <?php foreach ($pending_grading as $pending): ?>
                            <div class="grade-item">
                                <div>
                                    <strong><?php echo htmlspecialchars($pending['course_code']); ?></strong>
                                    <div style="color: var(--muted); font-size: 0.875rem;">
                                        <?php echo htmlspecialchars($pending['course_name']); ?>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <?php 
                                    $needs_grading = $pending['students_needing_grades'] - $pending['graded_submissions'];
                                    if ($needs_grading > 0): 
                                    ?>
                                        <span class="pending-alert">
                                            <?php echo $needs_grading; ?> pending
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Grade Distribution Chart -->
            <div class="card" style="margin-bottom: 2rem;">
                <h3>📊 Grade Distribution by Course</h3>
                <?php if (empty($grade_distribution)): ?>
                    <p style="color: var(--muted); text-align: center; padding: 2rem 0;">No grade data available</p>
                <?php else: ?>
                    <canvas id="gradeChart" width="400" height="200"></canvas>
                <?php endif; ?>
            </div>

            <!-- Recent Grading Activity -->
            <div class="card" style="margin-bottom: 2rem;">
                <h3>📈 Recent Grading Activity</h3>
                <?php if (empty($recent_grades)): ?>
                    <p style="color: var(--muted); text-align: center; padding: 2rem 0;">No recent grading activity</p>
                <?php else: ?>
                    <?php foreach ($recent_grades as $grade): ?>
                        <div class="grade-item">
                            <div>
                                <strong><?php echo htmlspecialchars($grade['student_name']); ?></strong>
                                <div style="color: var(--muted); font-size: 0.875rem;">
                                    <?php echo htmlspecialchars($grade['course_code']); ?> - <?php echo htmlspecialchars($grade['assessment_name']); ?>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div class="grade-score">
                                    <?php echo $grade['score']; ?>/<?php echo $grade['max_score']; ?>
                                </div>
                                <div style="color: var(--muted); font-size: 0.75rem;">
                                    <?php echo date('M j, H:i', strtotime($grade['graded_date'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Attendance Overview -->
            <div class="card">
                <h3>📅 Attendance Overview</h3>
                <?php if (empty($attendance_stats)): ?>
                    <p style="color: var(--muted); text-align: center; padding: 2rem 0;">No attendance data available</p>
                <?php else: ?>
                    <canvas id="attendanceChart" width="400" height="200"></canvas>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Grade Distribution Chart
        <?php if (!empty($grade_distribution)): ?>
        const gradeCtx = document.getElementById('gradeChart').getContext('2d');
        const gradeChart = new Chart(gradeCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($grade_distribution, 'course_code')); ?>,
                datasets: [{
                    label: 'Average Score',
                    data: <?php echo json_encode(array_column($grade_distribution, 'avg_score')); ?>,
                    backgroundColor: '#4F46E5',
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            },
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
        <?php if (!empty($attendance_stats)): ?>
        const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
        const attendanceChart = new Chart(attendanceCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($attendance_stats, 'course_code')); ?>,
                datasets: [{
                    label: 'Attendance %',
                    data: <?php echo json_encode(array_column($attendance_stats, 'attendance_percentage')); ?>,
                    borderColor: '#10B981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            },
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
    </script>
</body>
</html>
