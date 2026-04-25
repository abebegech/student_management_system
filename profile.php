<?php
session_start();
require_once '../includes/Auth.php';
require_once '../includes/Database.php';

$auth = new Auth();
$auth->requireRole('teacher');

$db = Database::getInstance();
$userId = $auth->getUserId();

// Get teacher profile
$teacher = $db->fetch("
    SELECT u.*, d.name as department_name, d.code as department_code
    FROM users u
    LEFT JOIN departments d ON u.id = d.head_of_department
    WHERE u.id = :user_id AND u.role = 'teacher'
", ['user_id' => $userId]);

// Handle profile update
if (isset($_POST['update_profile'])) {
    try {
        $data = [
            'first_name' => $_POST['first_name'],
            'last_name' => $_POST['last_name'],
            'phone' => $_POST['phone'] ?? '',
            'email' => $_POST['email'],
            'id' => $userId
        ];
        
        $sql = "UPDATE users SET first_name = :first_name, last_name = :last_name, phone = :phone, email = :email, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        
        $result = $db->update($sql, $data);
        
        if ($result > 0) {
            $auth->logActivity($userId, 'update_profile', 'users', $userId);
            $message = "Profile updated successfully!";
            
            // Update session data
            $_SESSION['first_name'] = $data['first_name'];
            $_SESSION['last_name'] = $data['last_name'];
            $_SESSION['full_name'] = $data['first_name'] . ' ' . $data['last_name'];
            $_SESSION['email'] = $data['email'];
        }
        
    } catch (Exception $e) {
        $error = "Failed to update profile: " . $e->getMessage();
    }
}

// Handle password change
if (isset($_POST['change_password'])) {
    try {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        if ($newPassword !== $confirmPassword) {
            throw new Exception("New passwords do not match");
        }
        
        if (strlen($newPassword) < 6) {
            throw new Exception("Password must be at least 6 characters long");
        }
        
        $result = $auth->changePassword($userId, $currentPassword, $newPassword);
        
        if ($result) {
            $message = "Password changed successfully!";
        } else {
            $error = "Current password is incorrect";
        }
        
    } catch (Exception $e) {
        $error = "Failed to change password: " . $e->getMessage();
    }
}

// Get teaching statistics
$teaching_stats = $db->fetch("
    SELECT 
        COUNT(DISTINCT c.id) as total_courses,
        COUNT(DISTINCT e.student_id) as total_students,
        COUNT(g.id) as total_grades,
        AVG(g.score) as avg_grade_score
    FROM courses c
    LEFT JOIN enrollments e ON c.id = e.course_id AND e.status = 'enrolled'
    LEFT JOIN grades g ON e.id = g.enrollment_id
    WHERE c.teacher_id = :teacher_id
", ['teacher_id' => $userId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | BDU Student System</title>
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
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: bold;
            flex-shrink: 0;
        }
        
        .profile-info h1 {
            margin: 0 0 0.5rem 0;
            color: var(--text);
            font-size: 2rem;
        }
        
        .profile-info p {
            margin: 0.25rem 0;
            color: var(--muted);
            font-size: 1.1rem;
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
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 0.5rem;
            color: var(--text);
            font-weight: 600;
        }
        
        .form-group input {
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--bg);
            color: var(--text);
            font-size: 1rem;
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
        .btn-danger { background: var(--danger); color: white; }
        
        .section-divider {
            margin: 2rem 0;
            padding: 1rem 0;
            border-top: 1px solid var(--border);
        }
        
        @media (max-width: 1024px) {
            .sidebar-layout {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
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
                    <li><a href="analytics.php">📈 Analytics</a></li>
                    <li><a href="reports.php">📊 Reports</a></li>
                    <li><a href="#" class="active">👤 Profile</a></li>
                    <li><a href="../logout.php">🚪 Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header style="margin-bottom: 2rem;">
                <h1 style="margin: 0; color: var(--text);">My Profile</h1>
                <p style="margin: 0.5rem 0 0 0; color: var(--muted);">Manage your personal information and account settings</p>
            </header>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Profile Header -->
            <div class="card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($teacher['first_name'], 0, 1) . substr($teacher['last_name'], 0, 1)); ?>
                    </div>
                    <div class="profile-info">
                        <h1><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></h1>
                        <p><strong>Department:</strong> <?php echo htmlspecialchars($teacher['department_name'] ?? 'Not Assigned'); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($teacher['email']); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($teacher['phone'] ?? 'Not Provided'); ?></p>
                        <p><strong>Member Since:</strong> <?php echo date('F j, Y', strtotime($teacher['created_at'])); ?></p>
                        <p><strong>Last Login:</strong> <?php echo $teacher['last_login'] ? date('M j, Y H:i', strtotime($teacher['last_login'])) : 'Never'; ?></p>
                    </div>
                </div>
            </div>

            <!-- Teaching Statistics -->
            <div class="card">
                <h2>📊 Teaching Statistics</h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $teaching_stats['total_courses']; ?></div>
                        <div class="stat-label">Active Courses</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $teaching_stats['total_students']; ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $teaching_stats['total_grades']; ?></div>
                        <div class="stat-label">Grades Posted</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $teaching_stats['avg_grade_score'] ? number_format($teaching_stats['avg_grade_score'], 1) : 'N/A'; ?></div>
                        <div class="stat-label">Avg Grade Score</div>
                    </div>
                </div>
            </div>

            <!-- Edit Profile -->
            <div class="card">
                <h2>✏️ Edit Profile</h2>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($teacher['first_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($teacher['last_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($teacher['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($teacher['phone'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                </form>
            </div>

            <!-- Change Password -->
            <div class="card">
                <h2>🔒 Change Password</h2>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" minlength="6" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" minlength="6" required>
                        </div>
                    </div>
                    
                    <div style="margin-top: 1rem;">
                        <button type="submit" name="change_password" class="btn btn-danger">Change Password</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
