<?php
session_start();
require_once '../includes/Auth.php';
require_once '../includes/Database.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();

// Get financial summary
$financial_summary = $db->fetch("
    SELECT 
        SUM(amount) as total_amount,
        SUM(paid_amount) as paid_amount,
        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount,
        SUM(CASE WHEN status = 'overdue' THEN amount ELSE 0 END) as overdue_amount,
        COUNT(*) as total_records
    FROM financial_records
");

// Get recent financial records
$records = $db->fetchAll("
    SELECT fr.*, s.student_id, CONCAT(u.first_name, ' ', u.last_name) as student_name, d.name as department_name
    FROM financial_records fr
    JOIN students s ON fr.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN departments d ON s.department_id = d.id
    ORDER BY fr.due_date DESC
    LIMIT 20
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial | BDU Student System</title>
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
        
        .status-paid { color: #10B981; font-weight: bold; }
        .status-pending { color: #F59E0B; font-weight: bold; }
        .status-overdue { color: #EF4444; font-weight: bold; }
        .status-partial { color: #3B82F6; font-weight: bold; }
        
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
                    <li><a href="departments.php">🏢 Departments</a></li>
                    <li><a href="#" class="active">💰 Financial</a></li>
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
                <h1 style="margin: 0; color: var(--text);">Financial Management</h1>
                <p style="margin: 0.5rem 0 0 0; color: var(--muted);">Monitor tuition payments and financial records</p>
            </header>

            <!-- Financial Summary -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Amount</div>
                    <div class="stat-value">$<?php echo number_format($financial_summary['total_amount'] ?? 0, 2); ?></div>
                    <div style="color: var(--muted); font-size: 0.875rem;">All fees</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Paid Amount</div>
                    <div class="stat-value">$<?php echo number_format($financial_summary['paid_amount'] ?? 0, 2); ?></div>
                    <div style="color: var(--muted); font-size: 0.875rem;">Collected</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Pending</div>
                    <div class="stat-value">$<?php echo number_format($financial_summary['pending_amount'] ?? 0, 2); ?></div>
                    <div style="color: var(--muted); font-size: 0.875rem;">Awaiting payment</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Overdue</div>
                    <div class="stat-value">$<?php echo number_format($financial_summary['overdue_amount'] ?? 0, 2); ?></div>
                    <div style="color: var(--muted); font-size: 0.875rem;">Past due</div>
                </div>
            </div>

            <!-- Financial Records -->
            <div class="card">
                <h2>💰 Recent Financial Records</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Fee Type</th>
                            <th>Amount</th>
                            <th>Due Date</th>
                            <th>Paid</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 2rem; color: var(--muted);">
                                    No financial records found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($records as $record): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($record['student_id']); ?></td>
                                    <td><?php echo htmlspecialchars($record['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($record['department_name']); ?></td>
                                    <td><?php echo ucfirst($record['fee_type']); ?></td>
                                    <td>$<?php echo number_format($record['amount'], 2); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($record['due_date'])); ?></td>
                                    <td>$<?php echo number_format($record['paid_amount'], 2); ?></td>
                                    <td><span class="status-<?php echo $record['status']; ?>"><?php echo ucfirst($record['status']); ?></span></td>
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
