<?php
session_start();
require_once '../includes/Auth.php';
require_once '../includes/Database.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();

// Get dashboard statistics
$stats = [
    'total_students' => $db->count("SELECT COUNT(*) FROM students WHERE status = 'active'"),
    'total_teachers' => $db->count("SELECT COUNT(*) FROM users WHERE role = 'teacher' AND is_active = 1"),
    'total_courses' => $db->count("SELECT COUNT(*) FROM courses WHERE is_active = 1"),
    'total_departments' => $db->count("SELECT COUNT(*) FROM departments"),
    'avg_gpa' => $db->fetch("SELECT ROUND(AVG(gpa), 2) as avg_gpa FROM students WHERE status = 'active' AND gpa > 0")['avg_gpa'] ?? 0,
    'attendance_rate' => $db->fetch("SELECT ROUND(AVG((present_count / total_sessions) * 100), 2) as avg_att FROM (SELECT COUNT(*) as total_sessions, SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count FROM attendance GROUP BY student_id) as attendance_stats WHERE total_sessions > 0")['avg_att'] ?? 0,
    'total_fees_collected' => $db->fetch("SELECT SUM(paid_amount) as total FROM financial_records WHERE status = 'paid'")['total'] ?? 0
];

// Get recent activity logs
$recent_activities = $db->fetchAll("
    SELECT al.*, CONCAT(u.first_name, ' ', u.last_name) as user_name 
    FROM activity_logs al 
    JOIN users u ON al.user_id = u.id 
    ORDER BY al.created_at DESC 
    LIMIT 10
");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | BDU Student System</title>
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }
        
        .stat-value {
            font-size: 2.5rem;
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
        
        .activity-log {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
        }
        
        .activity-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-meta {
            font-size: 0.75rem;
            color: var(--muted);
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
                    <li><a href="#" class="active">📊 Dashboard</a></li>
                    <li><a href="users.php">👥 User Management</a></li>
                    <li><a href="students.php">🎓 Students</a></li>
                    <li><a href="teachers.php">👨‍🏫 Teachers</a></li>
                    <li><a href="courses.php">📚 Courses</a></li>
                    <li><a href="departments.php">🏢 Departments</a></li>
                    <li><a href="financial.php">💰 Financial</a></li>
                    <li><a href="reports.php">📈 Reports</a></li>
                    <li><a href="logs.php">📋 Activity Logs</a></li>
                    <li><a href="backup.php">💾 Backup</a></li>
                    <li><a href="../logout.php">🚪 Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header style="margin-bottom: 2rem; text-align: center;">
                <div style="display: flex; justify-content: center; align-items: center; gap: 3rem; flex-wrap: wrap;">
                    <div class="cube-3d">
                        <div class="cube-face front">🎓</div>
                        <div class="cube-face back">📊</div>
                        <div class="cube-face right">👥</div>
                        <div class="cube-face left">📚</div>
                        <div class="cube-face top">💰</div>
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
                <h1 class="text-3d" style="margin: 2rem 0 0.5rem 0;">Admin Dashboard</h1>
                <p style="margin: 0; color: var(--muted); font-size: 1.1rem;">System overview and analytics</p>
            </header>

            <!-- Statistics Cards with DRAMATIC 3D Effects -->
            <section class="stats-grid reveal-dramatic">
                <div class="stat-card stat-card-3d btn-particle-burst">
                    <div class="stat-label">Total Students</div>
                    <div class="stat-value text-gradient"><?php echo number_format($stats['total_students']); ?></div>
                    <div style="color: var(--muted); font-size: 0.875rem;">Active enrollments</div>
                </div>
                
                <div class="stat-card stat-card-3d btn-particle-burst">
                    <div class="stat-label">Total Teachers</div>
                    <div class="stat-value text-gradient"><?php echo number_format($stats['total_teachers']); ?></div>
                    <div style="color: var(--muted); font-size: 0.875rem;">Active faculty</div>
                </div>
                
                <div class="stat-card stat-card-3d btn-particle-burst">
                    <div class="stat-label">Total Courses</div>
                    <div class="stat-value text-gradient"><?php echo number_format($stats['total_courses']); ?></div>
                    <div style="color: var(--muted); font-size: 0.875rem;">Offered courses</div>
                </div>
                
                <div class="stat-card stat-card-3d btn-particle-burst">
                    <div class="stat-label">Total Departments</div>
                    <div class="stat-value text-gradient"><?php echo number_format($stats['total_departments']); ?></div>
                    <div style="color: var(--muted); font-size: 0.875rem;">Academic units</div>
                </div>
                
                <div class="stat-card stat-card-3d btn-particle-burst">
                    <div class="stat-label">Average GPA</div>
                    <div class="stat-value text-gradient"><?php echo $stats['avg_gpa']; ?></div>
                    <div style="color: var(--muted); font-size: 0.875rem;">Across all students</div>
                </div>
                
                <div class="stat-card stat-card-3d btn-particle-burst">
                    <div class="stat-label">Attendance Rate</div>
                    <div class="stat-value text-gradient"><?php echo $stats['attendance_rate']; ?>%</div>
                    <div style="color: var(--muted); font-size: 0.875rem;">Average attendance</div>
                </div>
                
                <div class="stat-card stat-card-3d btn-particle-burst">
                    <div class="stat-label">Fees Collected</div>
                    <div class="stat-value text-gradient">$<?php echo number_format($stats['total_fees_collected'], 2); ?></div>
                    <div style="color: var(--muted); font-size: 0.875rem;">Total revenue</div>
                </div>
            </section>

            <!-- Two Column Layout -->
            <div style="display: grid; grid-template-columns: 1fr 350px; gap: 2rem; align-items: start;">
                <!-- Main Content Column -->
                <div>
                    <!-- Quick Actions -->
                    <section class="card-3d reveal-dramatic" style="background: var(--card-bg); border: 2px solid var(--border); border-radius: 20px; padding: 2rem; margin-bottom: 2rem;">
                        <h3 style="margin: 0 0 1.5rem 0; color: var(--text); font-size: 1.25rem;">Quick Actions</h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                            <a href="users.php?action=add" class="btn primary" style="text-decoration: none; display: block; text-align: center;">➕ Add User</a>
                            <a href="students.php?action=add" class="btn secondary" style="text-decoration: none; display: block; text-align: center;">🎓 Add Student</a>
                            <a href="courses.php?action=add" class="btn secondary" style="text-decoration: none; display: block; text-align: center;">📚 Add Course</a>
                            <a href="reports.php" class="btn ghost" style="text-decoration: none; display: block; text-align: center;">📊 Generate Reports</a>
                            <a href="backup.php" class="btn ghost" style="text-decoration: none; display: block; text-align: center;">💾 Backup Database</a>
                            <a href="logs.php" class="btn ghost" style="text-decoration: none; display: block; text-align: center;">📋 View Logs</a>
                        </div>
                    </section>
                </div>

                <!-- Sidebar Column - Recent Activity -->
                <div>
                    <!-- Recent Activity Log with Premium UI -->
                    <div class="activity-log card-3d reveal-dramatic" style="background: var(--card-bg); border: 2px solid var(--border); border-radius: 20px; padding: 2rem; position: sticky; top: 2rem;">
                        <h3 style="margin: 0 0 1.5rem 0; color: var(--text); font-size: 1.25rem;">Recent Activity</h3>
                        <?php if (empty($recent_activities)): ?>
                            <p style="color: var(--muted); text-align: center; padding: 2rem 0;">No recent activity</p>
                        <?php else: ?>
                            <div style="max-height: 600px; overflow-y: auto;">
                                <?php foreach ($recent_activities as $activity): ?>
                                    <div class="activity-item" style="padding: 1rem 0; border-bottom: 1px solid var(--border);">
                                        <div style="display: flex; justify-content: space-between; align-items: start;">
                                            <div>
                                                <strong style="color: var(--primary-light);"><?php echo htmlspecialchars($activity['user_name']); ?></strong>
                                                <span style="color: var(--muted); margin-left: 0.5rem; display: block; margin-top: 0.25rem;"><?php echo htmlspecialchars($activity['action']); ?></span>
                                                <?php if ($activity['table_name']): ?>
                                                    <span style="color: var(--muted); font-size: 0.85rem; display: block; margin-top: 0.25rem;">
                                                        in <?php echo htmlspecialchars($activity['table_name']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="activity-meta" style="color: var(--muted); font-size: 0.85rem; margin-top: 0.5rem;">
                                            <?php echo date('M j, Y H:i', strtotime($activity['created_at'])); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Auto-refresh activity every 30 seconds
        setInterval(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
