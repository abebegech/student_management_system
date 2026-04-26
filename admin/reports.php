<?php
session_start();
require_once '../includes/Auth.php';
require_once '../includes/Database.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();

// Get report statistics
$report_stats = [
    'total_students' => $db->count("SELECT COUNT(*) FROM students WHERE status = 'active'"),
    'total_teachers' => $db->count("SELECT COUNT(*) FROM users WHERE role = 'teacher' AND is_active = 1"),
    'total_courses' => $db->count("SELECT COUNT(*) FROM courses WHERE is_active = 1"),
    'avg_gpa' => $db->fetch("SELECT ROUND(AVG(gpa), 2) as avg_gpa FROM students WHERE status = 'active' AND gpa > 0")['avg_gpa'] ?? 0,
    'total_enrollments' => $db->count("SELECT COUNT(*) FROM enrollments WHERE status = 'enrolled'"),
    'total_fees' => $db->fetch("SELECT SUM(amount) as total FROM financial_records")['total'] ?? 0
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | BDU Student System</title>
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
        
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .report-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .report-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .report-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .report-title {
            font-size: 1.25rem;
            font-weight: bold;
            color: var(--text);
            margin-bottom: 0.5rem;
        }
        
        .report-desc {
            color: var(--muted);
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-item {
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
        
        @media (max-width: 1024px) {
            .sidebar-layout {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .reports-grid {
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
                    <li><a href="dashboard.php">📊 Dashboard</a></li>
                    <li><a href="users.php">👥 User Management</a></li>
                    <li><a href="students.php">🎓 Students</a></li>
                    <li><a href="teachers.php">👨‍🏫 Teachers</a></li>
                    <li><a href="courses.php">📚 Courses</a></li>
                    <li><a href="departments.php">🏢 Departments</a></li>
                    <li><a href="financial.php">💰 Financial</a></li>
                    <li><a href="#" class="active">📈 Reports</a></li>
                    <li><a href="logs.php">📋 Activity Logs</a></li>
                    <li><a href="backup.php">💾 Backup</a></li>
                    <li><a href="../logout.php">🚪 Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header style="margin-bottom: 2rem;">
                <h1 style="margin: 0; color: var(--text);">Reports & Analytics</h1>
                <p style="margin: 0.5rem 0 0 0; color: var(--muted);">Generate comprehensive system reports</p>
            </header>

            <!-- Quick Stats -->
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value"><?php echo $report_stats['total_students']; ?></div>
                    <div class="stat-label">Active Students</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $report_stats['total_teachers']; ?></div>
                    <div class="stat-label">Teachers</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $report_stats['total_courses']; ?></div>
                    <div class="stat-label">Courses</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $report_stats['avg_gpa']; ?></div>
                    <div class="stat-label">Average GPA</div>
                </div>
            </div>

            <!-- Available Reports -->
            <div class="card">
                <h2>📊 Available Reports</h2>
                <div class="reports-grid">
                    <div class="report-card">
                        <div class="report-icon">👥</div>
                        <div class="report-title">Student Reports</div>
                        <div class="report-desc">Generate student ID cards, transcripts, and academic records</div>
                        <button class="btn btn-primary">Generate Reports</button>
                    </div>
                    
                    <div class="report-card">
                        <div class="report-icon">📚</div>
                        <div class="report-title">Course Analytics</div>
                        <div class="report-desc">View course performance, enrollment statistics, and grade distributions</div>
                        <button class="btn btn-primary">View Analytics</button>
                    </div>
                    
                    <div class="report-card">
                        <div class="report-icon">💰</div>
                        <div class="report-title">Financial Reports</div>
                        <div class="report-desc">Tuition collection, fee analysis, and payment summaries</div>
                        <button class="btn btn-primary">Generate Report</button>
                    </div>
                    
                    <div class="report-card">
                        <div class="report-icon">📈</div>
                        <div class="report-title">Attendance Reports</div>
                        <div class="report-desc">Student attendance patterns, class participation rates</div>
                        <button class="btn btn-primary">Generate Report</button>
                    </div>
                    
                    <div class="report-card">
                        <div class="report-icon">🎯</div>
                        <div class="report-title">Performance Analytics</div>
                        <div class="report-desc">Grade trends, GPA analysis, department comparisons</div>
                        <button class="btn btn-primary">View Analytics</button>
                    </div>
                    
                    <div class="report-card">
                        <div class="report-icon">📋</div>
                        <div class="report-title">System Summary</div>
                        <div class="report-desc">Complete system overview with all statistics and metrics</div>
                        <button class="btn btn-primary">Generate Summary</button>
                    </div>
                </div>
            </div>

            <!-- Report History -->
            <div class="card">
                <h2>📜 Recent Reports</h2>
                <p style="color: var(--muted); text-align: center; padding: 2rem 0;">
                    No reports generated yet. Use the options above to create your first report.
                </p>
            </div>
        </main>
    </div>
</body>
</html>
