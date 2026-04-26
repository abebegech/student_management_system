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
    SELECT s.*, u.first_name, u.last_name, u.email, u.phone, d.name as department_name
    FROM students s
    JOIN users u ON s.user_id = u.id
    JOIN departments d ON s.department_id = d.id
    WHERE s.user_id = :user_id
", ['user_id' => $userId]);

if (!$student) {
    $_SESSION['error'] = "Student profile not found.";
    header("Location: ../login.php");
    exit;
}

// Get enrolled courses
$enrolled_courses = $db->fetchAll("
    SELECT c.*, e.enrollment_date, e.status as enrollment_status, e.final_grade
    FROM courses c
    JOIN enrollments e ON c.id = e.course_id
    WHERE e.student_id = :student_id AND e.status = 'enrolled'
    ORDER BY c.course_name
", ['student_id' => $student['id']]);

// Get recent grades
$recent_grades = $db->fetchAll("
    SELECT g.*, c.course_code, c.course_name, g.assessment_type, g.score, g.max_score, g.graded_date
    FROM grades g
    JOIN enrollments e ON g.enrollment_id = e.id
    JOIN courses c ON e.course_id = c.id
    WHERE e.student_id = :student_id
    ORDER BY g.graded_date DESC
    LIMIT 10
", ['student_id' => $student['id']]);

// Get attendance summary
$attendance_summary = $db->fetchAll("
    SELECT c.course_code, c.course_name,
           COUNT(*) as total_sessions,
           SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
           SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
           ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as attendance_percentage
    FROM attendance a
    JOIN courses c ON a.course_id = c.id
    WHERE a.student_id = :student_id
    GROUP BY c.id, c.course_code, c.course_name
    ORDER BY attendance_percentage DESC
", ['student_id' => $student['id']]);

// Calculate overall attendance
$total_sessions = array_sum(array_column($attendance_summary, 'total_sessions'));
$total_present = array_sum(array_column($attendance_summary, 'present_count'));
$overall_attendance = $total_sessions > 0 ? round(($total_present / $total_sessions) * 100, 2) : 0;

// Get financial records
$financial_records = $db->fetchAll("
    SELECT * FROM financial_records 
    WHERE student_id = :student_id 
    ORDER BY due_date DESC
    LIMIT 5
", ['student_id' => $student['id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | BDU Student System</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../js/premium-ui.js"></script>
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
        
        .student-profile {
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
            grid-template-columns: 1fr 1fr;
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
        
        .attendance-bar {
            background: var(--border);
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
        }
        
        .attendance-fill {
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
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .student-profile {
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
                    <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                </div>
                <h3 style="margin: 0; color: var(--text);"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h3>
                <p style="margin: 0.25rem 0 0 0; color: var(--muted); font-size: 0.875rem;">
                    ID: <?php echo htmlspecialchars($student['student_id']); ?>
                </p>
            </div>
            
            <nav>
                <ul class="sidebar-nav">
                    <li><a href="#" class="active">📊 Dashboard</a></li>
                    <li><a href="profile.php">👤 Profile</a></li>
                    <li><a href="grades.php">📚 Grades</a></li>
                    <li><a href="attendance.php">📅 Attendance</a></li>
                    <li><a href="courses.php">🎓 Courses</a></li>
                    <li><a href="financial.php">💰 Financial</a></li>
                    <li><a href="documents.php">📄 Documents</a></li>
                    <li><a href="../logout.php">🚪 Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Student Profile Card with DRAMATIC Premium UI -->
            <section class="student-profile reveal-dramatic">
                <div style="display: flex; justify-content: center; align-items: center; gap: 2rem; flex-wrap: wrap;">
                    <div class="cube-3d">
                        <div class="cube-face front"><?php echo strtoupper(substr($student['first_name'], 0, 1)); ?></div>
                        <div class="cube-face back"><?php echo strtoupper(substr($student['last_name'], 0, 1)); ?></div>
                        <div class="cube-face right">🎓</div>
                        <div class="cube-face left">📚</div>
                        <div class="cube-face top">⭐</div>
                        <div class="cube-face bottom">🎯</div>
                    </div>
                    <div class="sphere-3d">
                        <div class="sphere-face"></div>
                        <div class="sphere-face"></div>
                        <div class="sphere-face"></div>
                        <div class="sphere-face"></div>
                        <div class="sphere-face"></div>
                        <div class="sphere-face"></div>
                    </div>
                    <div class="pyramid-3d">
                        <div class="pyramid-face front"></div>
                        <div class="pyramid-face back"></div>
                        <div class="pyramid-face left"></div>
                        <div class="pyramid-face right"></div>
                    </div>
                </div>
                <div class="profile-info">
                    <h2 class="text-3d" style="margin: 2rem 0 1rem 0;"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h2>
                    <p><strong>Student ID:</strong> <?php echo htmlspecialchars($student['student_id']); ?></p>
                    <p><strong>Department:</strong> <?php echo htmlspecialchars($student['department_name']); ?></p>
                    <p><strong>Year:</strong> <?php echo htmlspecialchars($student['year_level']); ?> | <strong>Semester:</strong> <?php echo htmlspecialchars($student['semester']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></p>
                </div>
            </section>

            <!-- Statistics Cards with DRAMATIC Premium UI -->
            <section class="stats-grid reveal-dramatic">
                <div class="stat-card stat-card-3d btn-particle-burst">
                    <div class="stat-label">Current GPA</div>
                    <div class="stat-value text-gradient"><?php echo number_format($student['gpa'], 2); ?></div>
                    <div style="color: var(--muted); font-size: 0.875rem;">Academic performance</div>
                </div>
                
                <div class="stat-card stat-card-3d btn-particle-burst">
                    <div class="stat-label">Attendance Rate</div>
                    <div class="stat-value text-gradient"><?php echo $overall_attendance; ?>%</div>
                    <div style="color: var(--muted); font-size: 0.875rem;">Overall attendance</div>
                </div>
                
                <div class="stat-card stat-card-3d btn-particle-burst">
                    <div class="stat-label">Enrolled Courses</div>
                    <div class="stat-value text-gradient"><?php echo count($enrolled_courses); ?></div>
                    <div style="color: var(--muted); font-size: 0.875rem;">Active courses</div>
                </div>
                
                <div class="stat-card stat-card-3d btn-particle-burst">
                    <div class="stat-label">Pending Fees</div>
                    <div class="stat-value text-gradient">
                        <?php 
                        $pending_fees = array_filter($financial_records, fn($r) => $r['status'] === 'pending');
                        echo count($pending_fees); 
                        ?>
                    </div>
                    <div style="color: var(--muted); font-size: 0.875rem;">Unpaid fees</div>
                </div>
            </section>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Enrolled Courses -->
                <div class="card">
                    <h3>📚 Enrolled Courses</h3>
                    <?php if (empty($enrolled_courses)): ?>
                        <p style="color: var(--muted); text-align: center; padding: 2rem 0;">No courses enrolled</p>
                    <?php else: ?>
                        <ul class="course-list">
                            <?php foreach ($enrolled_courses as $course): ?>
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
                                                <?php echo $course['credits']; ?> credits
                                            </span>
                                            <div style="color: var(--muted); font-size: 0.75rem;">
                                                <?php echo date('M j', strtotime($course['enrollment_date'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <!-- Recent Grades -->
                <div class="card">
                    <h3>📊 Recent Grades</h3>
                    <?php if (empty($recent_grades)): ?>
                        <p style="color: var(--muted); text-align: center; padding: 2rem 0;">No grades available</p>
                    <?php else: ?>
                        <?php foreach ($recent_grades as $grade): ?>
                            <div class="grade-item">
                                <div>
                                    <strong><?php echo htmlspecialchars($grade['course_code']); ?></strong>
                                    <div style="color: var(--muted); font-size: 0.875rem;">
                                        <?php echo htmlspecialchars($grade['assessment_name']); ?>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div class="grade-score">
                                        <?php echo $grade['score']; ?>/<?php echo $grade['max_score']; ?>
                                    </div>
                                    <div style="color: var(--muted); font-size: 0.75rem;">
                                        <?php echo date('M j', strtotime($grade['graded_date'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Attendance Overview -->
            <div class="card" style="margin-bottom: 2rem;">
                <h3>📅 Attendance Overview</h3>
                <?php if (empty($attendance_summary)): ?>
                    <p style="color: var(--muted); text-align: center; padding: 2rem 0;">No attendance data available</p>
                <?php else: ?>
                    <div style="margin-bottom: 1.5rem;">
                        <canvas id="attendanceChart" width="400" height="200"></canvas>
                    </div>
                    
                    <?php foreach ($attendance_summary as $attendance): ?>
                        <div style="margin-bottom: 1rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <strong><?php echo htmlspecialchars($attendance['course_code']); ?></strong>
                                <span style="color: <?php echo $attendance['attendance_percentage'] >= 75 ? 'var(--success)' : 'var(--danger)'; ?>;">
                                    <?php echo $attendance['attendance_percentage']; ?>%
                                </span>
                            </div>
                            <div class="attendance-bar">
                                <div class="attendance-fill" style="width: <?php echo $attendance['attendance_percentage']; ?>%;"></div>
                            </div>
                            <div style="color: var(--muted); font-size: 0.75rem; margin-top: 0.25rem;">
                                Present: <?php echo $attendance['present_count']; ?>/<?php echo $attendance['total_sessions']; ?> sessions
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Financial Summary -->
            <div class="card">
                <h3>💰 Financial Summary</h3>
                <?php if (empty($financial_records)): ?>
                    <p style="color: var(--muted); text-align: center; padding: 2rem 0;">No financial records</p>
                <?php else: ?>
                    <?php foreach ($financial_records as $record): ?>
                        <div class="grade-item">
                            <div>
                                <strong><?php echo htmlspecialchars(ucfirst($record['fee_type'])); ?></strong>
                                <div style="color: var(--muted); font-size: 0.875rem;">
                                    Due: <?php echo date('M j, Y', strtotime($record['due_date'])); ?>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="color: <?php 
                                    echo match($record['status']) {
                                        'paid' => 'var(--success)',
                                        'overdue' => 'var(--danger)',
                                        'pending' => 'var(--warning)',
                                        default => 'var(--muted)'
                                    }; 
                                ?>; font-weight: bold;">
                                    $<?php echo number_format($record['amount'], 2); ?>
                                </div>
                                <div style="color: var(--muted); font-size: 0.75rem; text-transform: uppercase;">
                                    <?php echo htmlspecialchars($record['status']); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Attendance Chart
        const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
        const attendanceChart = new Chart(attendanceCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($attendance_summary, 'course_code')); ?>,
                datasets: [{
                    label: 'Attendance %',
                    data: <?php echo json_encode(array_column($attendance_summary, 'attendance_percentage')); ?>,
                    backgroundColor: <?php echo $overall_attendance >= 75 ? "'#10B981'" : "'#EF4444'"; ?>,
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
    </script>
</body>
</html>
