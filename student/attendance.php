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

// Get attendance records with course information
$attendance = $db->fetchAll("
    SELECT a.*, c.course_code, c.course_name, d.name as department_name
    FROM attendance a
    JOIN courses c ON a.course_id = c.id
    JOIN departments d ON c.department_id = d.id
    WHERE a.student_id = :student_id
    ORDER BY a.attendance_date DESC
", ['student_id' => $student['id']]);

// Calculate attendance statistics
$attendance_stats = [
    'total' => count($attendance),
    'present' => 0,
    'absent' => 0,
    'late' => 0,
    'excused' => 0
];

foreach ($attendance as $record) {
    $attendance_stats[$record['status']]++;
}

$attendance_rate = $attendance_stats['total'] > 0 ? 
    round(($attendance_stats['present'] / $attendance_stats['total']) * 100, 1) : 0;

// Group attendance by course
$attendance_by_course = [];
foreach ($attendance as $record) {
    $attendance_by_course[$record['course_code']][] = $record;
}

// Calculate course attendance rates
$course_rates = [];
foreach ($attendance_by_course as $courseCode => $courseAttendance) {
    $present = 0;
    foreach ($courseAttendance as $record) {
        if ($record['status'] === 'present') $present++;
    }
    $course_rates[$courseCode] = [
        'rate' => round(($present / count($courseAttendance)) * 100, 1),
        'total' => count($courseAttendance),
        'present' => $present
    ];
}

// Get monthly attendance trend
$monthly_trend = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthly_trend[$month] = [
        'month' => date('M Y', strtotime("-$i months")),
        'present' => 0,
        'total' => 0
    ];
}

foreach ($attendance as $record) {
    $month = date('Y-m', strtotime($record['attendance_date']));
    if (isset($monthly_trend[$month])) {
        $monthly_trend[$month]['total']++;
        if ($record['status'] === 'present') {
            $monthly_trend[$month]['present']++;
        }
    }
}

foreach ($monthly_trend as $month => &$data) {
    $data['rate'] = $data['total'] > 0 ? round(($data['present'] / $data['total']) * 100, 1) : 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance | BDU Student System</title>
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
        
        .status-present { 
            color: #10B981; 
            font-weight: bold; 
            background: #D1FAE5;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            display: inline-block;
        }
        .status-absent { 
            color: #EF4444; 
            font-weight: bold; 
            background: #FEE2E2;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            display: inline-block;
        }
        .status-late { 
            color: #F59E0B; 
            font-weight: bold; 
            background: #FEF3C7;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            display: inline-block;
        }
        .status-excused { 
            color: #6366F1; 
            font-weight: bold; 
            background: #E0E7FF;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            display: inline-block;
        }
        
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
        
        .attendance-rate {
            font-size: 1.5rem;
            font-weight: bold;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            color: white;
        }
        
        .rate-excellent { background: #10B981; }
        .rate-good { background: #3B82F6; }
        .rate-average { background: #F59E0B; }
        .rate-poor { background: #EF4444; }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 1rem;
        }
        
        .attendance-calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: var(--border);
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 1rem;
        }
        
        .calendar-header {
            background: var(--primary);
            color: white;
            padding: 0.5rem;
            text-align: center;
            font-weight: bold;
            font-size: 0.875rem;
        }
        
        .calendar-day {
            background: var(--card-bg);
            padding: 0.5rem;
            min-height: 60px;
            font-size: 0.75rem;
            text-align: center;
        }
        
        .day-present { background: #D1FAE5; }
        .day-absent { background: #FEE2E2; }
        .day-late { background: #FEF3C7; }
        .day-excused { background: #E0E7FF; }
        
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
                    <li><a href="grades.php">📝 Grades</a></li>
                    <li><a href="#" class="active">📅 Attendance</a></li>
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
                <h1 style="margin: 0; color: var(--text);">My Attendance</h1>
                <p style="margin: 0.5rem 0 0 0; color: var(--muted);">Track your attendance record and patterns</p>
            </header>

            <!-- Attendance Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $attendance_rate; ?>%</div>
                    <div class="stat-label">Overall Rate</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $attendance_stats['present']; ?></div>
                    <div class="stat-label">Present</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $attendance_stats['absent']; ?></div>
                    <div class="stat-label">Absent</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $attendance_stats['late']; ?></div>
                    <div class="stat-label">Late</div>
                </div>
            </div>

            <!-- Attendance Charts -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
                <div class="card">
                    <h2>📊 Attendance Overview</h2>
                    <div class="chart-container">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                </div>
                
                <div class="card">
                    <h2>📈 Monthly Trend</h2>
                    <div class="chart-container">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Course Attendance -->
            <div class="card">
                <h2>📚 Course Attendance</h2>
                <?php if (empty($attendance_by_course)): ?>
                    <p style="color: var(--muted); text-align: center; padding: 2rem 0;">No attendance records found.</p>
                <?php else: ?>
                    <?php foreach ($attendance_by_course as $courseCode => $courseAttendance): ?>
                        <div class="course-card">
                            <div class="course-header">
                                <div>
                                    <div class="course-title"><?php echo htmlspecialchars($courseCode); ?></div>
                                    <p style="color: var(--muted); margin: 0.25rem 0;"><?php echo htmlspecialchars($courseAttendance[0]['course_name']); ?></p>
                                </div>
                                <div class="attendance-rate <?php 
                                    $rate = $course_rates[$courseCode]['rate'];
                                    echo $rate >= 90 ? 'rate-excellent' : 
                                         ($rate >= 75 ? 'rate-good' : 
                                         ($rate >= 60 ? 'rate-average' : 'rate-poor'));
                                ?>">
                                    <?php echo $course_rates[$courseCode]['rate']; ?>%
                                </div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                                <div style="text-align: center;">
                                    <div style="font-size: 1.25rem; font-weight: bold; color: #10B981;"><?php echo $course_rates[$courseCode]['present']; ?></div>
                                    <div style="color: var(--muted); font-size: 0.875rem;">Present</div>
                                </div>
                                <div style="text-align: center;">
                                    <div style="font-size: 1.25rem; font-weight: bold; color: #EF4444;"><?php echo $course_rates[$courseCode]['total'] - $course_rates[$courseCode]['present']; ?></div>
                                    <div style="color: var(--muted); font-size: 0.875rem;">Missed</div>
                                </div>
                                <div style="text-align: center;">
                                    <div style="font-size: 1.25rem; font-weight: bold;"><?php echo $course_rates[$courseCode]['total']; ?></div>
                                    <div style="color: var(--muted); font-size: 0.875rem;">Total</div>
                                </div>
                            </div>
                            
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($courseAttendance, 0, 5) as $record): ?>
                                        <tr>
                                            <td><?php echo date('M j, Y', strtotime($record['attendance_date'])); ?></td>
                                            <td><span class="status-<?php echo $record['status']; ?>"><?php echo ucfirst($record['status']); ?></span></td>
                                            <td><?php echo htmlspecialchars($record['notes'] ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (count($courseAttendance) > 5): ?>
                                        <tr>
                                            <td colspan="3" style="text-align: center; color: var(--muted);">
                                                ... and <?php echo count($courseAttendance) - 5; ?> more records
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Detailed Attendance Log -->
            <div class="card">
                <h2>📋 Detailed Attendance Log</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Course</th>
                            <th>Status</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($attendance)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 2rem; color: var(--muted;">
                                    No attendance records found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($attendance as $record): ?>
                                <tr>
                                    <td><?php echo date('M j, Y', strtotime($record['attendance_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($record['course_code']); ?></td>
                                    <td><span class="status-<?php echo $record['status']; ?>"><?php echo ucfirst($record['status']); ?></span></td>
                                    <td><?php echo htmlspecialchars($record['notes'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
        // Attendance Overview Chart
        const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
        const attendanceChart = new Chart(attendanceCtx, {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Absent', 'Late', 'Excused'],
                datasets: [{
                    data: [
                        <?php echo $attendance_stats['present']; ?>,
                        <?php echo $attendance_stats['absent']; ?>,
                        <?php echo $attendance_stats['late']; ?>,
                        <?php echo $attendance_stats['excused']; ?>
                    ],
                    backgroundColor: ['#10B981', '#EF4444', '#F59E0B', '#6366F1'],
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

        // Monthly Trend Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        const trendChart = new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthly_trend, 'month')); ?>,
                datasets: [{
                    label: 'Attendance Rate (%)',
                    data: <?php echo json_encode(array_column($monthly_trend, 'rate')); ?>,
                    borderColor: '#4F46E5',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
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
                        ticks: { color: '#6B7280' },
                        grid: { color: '#374151' }
                    },
                    x: {
                        ticks: { color: '#6B7280' },
                        grid: { display: false }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    </script>
</body>
</html>
