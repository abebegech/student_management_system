<?php
session_start();
require_once '../includes/Auth.php';
require_once '../includes/Database.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();

// Get all departments with student and course counts
$departments = $db->fetchAll("
    SELECT d.*, 
           CONCAT(u.first_name, ' ', u.last_name) as head_name,
           COUNT(DISTINCT s.id) as student_count,
           COUNT(DISTINCT c.id) as course_count
    FROM departments d
    LEFT JOIN users u ON d.head_of_department = u.id
    LEFT JOIN students s ON d.id = s.department_id AND s.status = 'active'
    LEFT JOIN courses c ON d.id = c.department_id AND c.is_active = 1
    WHERE d.is_active = 1
    GROUP BY d.id
    ORDER BY d.name
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departments | BDU Student System</title>
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
                    <li><a href="#" class="active">🏢 Departments</a></li>
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
            <header style="margin-bottom: 2rem;">
                <h1 style="margin: 0; color: var(--text);">Departments</h1>
                <p style="margin: 0.5rem 0 0 0; color: var(--muted);">Manage academic departments and programs</p>
            </header>

            <!-- Departments Table -->
            <div class="card">
                <h2>🏢 All Departments</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Head of Department</th>
                            <th>Students</th>
                            <th>Courses</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($departments)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 2rem; color: var(--muted);">
                                    No departments found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($departments as $dept): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($dept['code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($dept['name']); ?></td>
                                    <td><?php echo htmlspecialchars($dept['head_name'] ?? 'Not assigned'); ?></td>
                                    <td><?php echo $dept['student_count']; ?></td>
                                    <td><?php echo $dept['course_count']; ?></td>
                                    <td><?php echo htmlspecialchars(substr($dept['description'] ?? '', 0, 50)) . (strlen($dept['description'] ?? '') > 50 ? '...' : ''); ?></td>
                                    <td>
                                        <button class="btn btn-secondary" style="padding: 0.25rem 0.75rem; font-size: 0.875rem;">View</button>
                                        <button class="btn btn-secondary" style="padding: 0.25rem 0.75rem; font-size: 0.875rem;">Edit</button>
                                    </td>
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
