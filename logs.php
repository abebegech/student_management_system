<?php
session_start();
require_once '../includes/Auth.php';
require_once '../includes/Database.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();

// Get pagination parameters
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Get filters
$action_filter = $_GET['action'] ?? '';
$user_filter = $_GET['user'] ?? '';
$date_filter = $_GET['date'] ?? '';

// Build query
$where = ["1=1"];
$params = [];

if (!empty($action_filter)) {
    $where[] = "al.action LIKE :action";
    $params['action'] = "%$action_filter%";
}

if (!empty($user_filter)) {
    $where[] = "CONCAT(u.first_name, ' ', u.last_name) LIKE :user";
    $params['user'] = "%$user_filter%";
}

if (!empty($date_filter)) {
    $where[] = "DATE(al.created_at) = :date";
    $params['date'] = $date_filter;
}

$where_clause = implode(' AND ', $where);

// Get total count
$total_count = $db->count("
    SELECT COUNT(*) 
    FROM activity_logs al 
    JOIN users u ON al.user_id = u.id 
    WHERE $where_clause
", $params);

// Get activity logs
$logs = $db->fetchAll("
    SELECT al.*, CONCAT(u.first_name, ' ', u.last_name) as user_name, u.username
    FROM activity_logs al 
    JOIN users u ON al.user_id = u.id 
    WHERE $where_clause
    ORDER BY al.created_at DESC 
    LIMIT :limit OFFSET :offset
", array_merge($params, ['limit' => $limit, 'offset' => $offset]));

// Get unique actions for filter dropdown
$actions = $db->fetchAll("SELECT DISTINCT action FROM activity_logs ORDER BY action");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs | BDU Student System</title>
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
        
        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 8px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            margin-bottom: 0.5rem;
            color: var(--text);
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .filter-group input, .filter-group select {
            padding: 0.5rem;
            border: 1px solid var(--border);
            border-radius: 4px;
            background: var(--bg);
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
        
        .action-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
            background: var(--primary);
            color: white;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }
        
        .pagination a, .pagination span {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--border);
            border-radius: 4px;
            text-decoration: none;
            color: var(--text);
        }
        
        .pagination a:hover {
            background: var(--primary);
            color: white;
        }
        
        .pagination .current {
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
            
            .filters {
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
                    <li><a href="reports.php">📈 Reports</a></li>
                    <li><a href="#" class="active">📋 Activity Logs</a></li>
                    <li><a href="backup.php">💾 Backup</a></li>
                    <li><a href="../logout.php">🚪 Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header style="margin-bottom: 2rem;">
                <h1 style="margin: 0; color: var(--text);">Activity Logs</h1>
                <p style="margin: 0.5rem 0 0 0; color: var(--muted);">Monitor system activity and user actions</p>
            </header>

            <!-- Filters -->
            <div class="filters">
                <div class="filter-group">
                    <label for="action">Action</label>
                    <select id="action" name="action" onchange="filterLogs()">
                        <option value="">All Actions</option>
                        <?php foreach ($actions as $action): ?>
                            <option value="<?php echo htmlspecialchars($action['action']); ?>" 
                                    <?php echo $action_filter === $action['action'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($action['action']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="user">User</label>
                    <input type="text" id="user" name="user" placeholder="Search user..." 
                           value="<?php echo htmlspecialchars($user_filter); ?>" onchange="filterLogs()">
                </div>
                
                <div class="filter-group">
                    <label for="date">Date</label>
                    <input type="date" id="date" name="date" 
                           value="<?php echo htmlspecialchars($date_filter); ?>" onchange="filterLogs()">
                </div>
                
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button class="btn btn-secondary" onclick="clearFilters()">Clear Filters</button>
                </div>
            </div>

            <!-- Activity Logs Table -->
            <div class="card">
                <h2>📋 System Activity</h2>
                <?php if (empty($logs)): ?>
                    <p style="color: var(--muted); text-align: center; padding: 2rem 0;">
                        No activity logs found matching your criteria.
                    </p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Table</th>
                                <th>IP Address</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo date('M j, Y H:i:s', strtotime($log['created_at'])); ?></td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($log['user_name']); ?></strong>
                                            <div style="font-size: 0.75rem; color: var(--muted);">
                                                @<?php echo htmlspecialchars($log['username']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="action-badge">
                                            <?php echo htmlspecialchars($log['action']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['table_name'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></td>
                                    <td>
                                        <?php if ($log['record_id']): ?>
                                            ID: <?php echo $log['record_id']; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Pagination -->
                    <div class="pagination">
                        <?php
                        $total_pages = ceil($total_count / $limit);
                        $current_page = $page;
                        
                        // Previous
                        if ($current_page > 1):
                            $prev_params = $_GET;
                            $prev_params['page'] = $current_page - 1;
                            $prev_query = http_build_query($prev_params);
                        ?>
                            <a href="?<?php echo $prev_query; ?>">← Previous</a>
                        <?php endif; ?>
                        
                        <!-- Page numbers -->
                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                            if ($i == $current_page):
                        ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <?php 
                                $page_params = $_GET;
                                $page_params['page'] = $i;
                                $page_query = http_build_query($page_params);
                                ?>
                                <a href="?<?php echo $page_query; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        // Next
                        <?php if ($current_page < $total_pages):
                            $next_params = $_GET;
                            $next_params['page'] = $current_page + 1;
                            $next_query = http_build_query($next_params);
                        ?>
                            <a href="?<?php echo $next_query; ?>">Next →</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function filterLogs() {
            const action = document.getElementById('action').value;
            const user = document.getElementById('user').value;
            const date = document.getElementById('date').value;
            
            const params = new URLSearchParams();
            if (action) params.set('action', action);
            if (user) params.set('user', user);
            if (date) params.set('date', date);
            
            window.location.href = '?' + params.toString();
        }
        
        function clearFilters() {
            window.location.href = 'logs.php';
        }
    </script>
</body>
</html>
