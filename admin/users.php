<?php
session_start();
require_once '../includes/Auth.php';
require_once '../includes/Database.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();

// Handle form submissions
if (isset($_POST['add_user'])) {
    try {
        // Check for duplicate username
        $existingUsername = $db->fetch("SELECT id FROM users WHERE username = :username", 
                                      ['username' => $_POST['username']]);
        if ($existingUsername) {
            throw new Exception("Username '{$_POST['username']}' already exists");
        }
        
        // Check for duplicate email
        $existingEmail = $db->fetch("SELECT id FROM users WHERE email = :email", 
                                    ['email' => $_POST['email']]);
        if ($existingEmail) {
            throw new Exception("Email '{$_POST['email']}' already exists");
        }
        
        $data = [
            'username' => $_POST['username'],
            'email' => $_POST['email'],
            'password_hash' => password_hash($_POST['password'], PASSWORD_DEFAULT),
            'role' => $_POST['role'],
            'first_name' => $_POST['first_name'],
            'last_name' => $_POST['last_name'],
            'phone' => $_POST['phone'] ?? null
        ];
        
        $sql = "INSERT INTO users (username, email, password_hash, role, first_name, last_name, phone) 
                VALUES (:username, :email, :password_hash, :role, :first_name, :last_name, :phone)";
        
        $userId = $db->insert($sql, $data);
        
        // Create student record if role is student
        if ($_POST['role'] === 'student') {
            // Generate unique student ID
            $studentId = 'STU' . date('Y') . str_pad($userId, 4, '0', STR_PAD_LEFT);
            
            $studentData = [
                'user_id' => $userId,
                'student_id' => $studentId,
                'department_id' => 1, // Default to first department
                'year_level' => 1,
                'semester' => 1,
                'gpa' => 0.00,
                'admission_date' => date('Y-m-d'),
                'status' => 'active'
            ];
            
            $studentSql = "INSERT INTO students (user_id, student_id, department_id, year_level, semester, gpa, admission_date, status) 
                          VALUES (:user_id, :student_id, :department_id, :year_level, :semester, :gpa, :admission_date, :status)";
            
            $db->insert($studentSql, $studentData);
        }
        
        $auth->logActivity($auth->getUserId(), 'create_user', 'users', $userId, null, $data);
        
        $message = "User created successfully!";
        
        // Redirect to clear form
        header("Location: users.php?success=1");
        exit;
        
    } catch (Exception $e) {
        $error = "Failed to create user: " . $e->getMessage();
    }
}

// Get all users
$users = $db->fetchAll("
    SELECT id, username, email, role, first_name, last_name, phone, is_active, created_at, last_login
    FROM users 
    ORDER BY created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | BDU Student System</title>
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
        
        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: bold;
        }
        
        .role-admin { background: #EF4444; color: white; }
        .role-teacher { background: #F59E0B; color: white; }
        .role-student { background: #10B981; color: white; }
        
        .status-active { color: #10B981; }
        .status-inactive { color: #EF4444; }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text);
            font-weight: 600;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--bg);
            color: var(--text);
        }
        
        .btn {
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
        .btn-danger { background: var(--danger); color: white; }
        
        @media (max-width: 1024px) {
            .sidebar-layout {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .form-grid {
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
                    <li><a href="#" class="active">👥 User Management</a></li>
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
            <header style="margin-bottom: 2rem;">
                <h1 style="margin: 0; color: var(--text);">User Management</h1>
                <p style="margin: 0.5rem 0 0 0; color: var(--muted);">Manage system users and permissions</p>
            </header>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Add User Form -->
            <div class="card">
                <h2>➕ Add New User</h2>
                <form method="POST" class="form-grid">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Role</label>
                        <select id="role" name="role" required>
                            <option value="">Select Role</option>
                            <option value="student">Student</option>
                            <option value="teacher">Teacher</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="phone">Phone (Optional)</label>
                        <input type="tel" id="phone" name="phone">
                    </div>
                    
                    <div style="grid-column: 1 / -1;">
                        <button type="submit" name="add_user" class="btn btn-primary">Create User</button>
                    </div>
                </form>
            </div>

            <!-- Users Table -->
            <div class="card">
                <h2>📋 All Users</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="role-badge role-<?php echo $user['role']; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-secondary" style="padding: 0.25rem 0.75rem; font-size: 0.875rem;">Edit</button>
                                    <?php if ($user['id'] != $auth->getUserId()): ?>
                                        <button class="btn btn-danger" style="padding: 0.25rem 0.75rem; font-size: 0.875rem;">Delete</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
