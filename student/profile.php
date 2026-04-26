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
    SELECT s.*, u.username, u.email, u.phone, u.first_name, u.last_name, d.name as department_name
    FROM students s
    JOIN users u ON s.user_id = u.id
    JOIN departments d ON s.department_id = d.id
    WHERE s.user_id = :user_id
", ['user_id' => $userId]);

if (!$student) {
    header('Location: ../login.php');
    exit;
}

// Handle profile update
if (isset($_POST['update_profile'])) {
    try {
        $db->beginTransaction();
        
        // Update user information
        $userData = [
            'first_name' => $_POST['first_name'],
            'last_name' => $_POST['last_name'],
            'email' => $_POST['email'],
            'phone' => $_POST['phone'] ?? null,
            'user_id' => $userId
        ];
        
        $userSql = "UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email, phone = :phone, updated_at = CURRENT_TIMESTAMP WHERE id = :user_id";
        $db->update($userSql, $userData);
        
        // Update student information
        $studentData = [
            'address' => $_POST['address'] ?? null,
            'date_of_birth' => $_POST['date_of_birth'] ?? null,
            'gender' => $_POST['gender'] ?? null,
            'nationality' => $_POST['nationality'] ?? null,
            'emergency_contact_name' => $_POST['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $_POST['emergency_contact_phone'] ?? null,
            'student_record_id' => $student['id']
        ];
        
        $studentSql = "UPDATE students SET address = :address, date_of_birth = :date_of_birth, gender = :gender, nationality = :nationality, emergency_contact_name = :emergency_contact_name, emergency_contact_phone = :emergency_contact_phone, updated_at = CURRENT_TIMESTAMP WHERE id = :student_record_id";
        $db->update($studentSql, $studentData);
        
        $db->commit();
        
        $message = "Profile updated successfully!";
        
        // Refresh student data
        $student = $db->fetch("
            SELECT s.*, u.username, u.email, u.phone, u.first_name, u.last_name, d.name as department_name
            FROM students s
            JOIN users u ON s.user_id = u.id
            JOIN departments d ON s.department_id = d.id
            WHERE s.user_id = :user_id
        ", ['user_id' => $userId]);
        
    } catch (Exception $e) {
        $db->rollback();
        $error = "Failed to update profile: " . $e->getMessage();
    }
}

// Handle password change
if (isset($_POST['change_password'])) {
    try {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Verify current password
        $user = $db->fetch("SELECT password_hash FROM users WHERE id = :id", ['id' => $userId]);
        if (!password_verify($currentPassword, $user['password_hash'])) {
            throw new Exception("Current password is incorrect");
        }
        
        // Validate new password
        if ($newPassword !== $confirmPassword) {
            throw new Exception("New passwords do not match");
        }
        
        if (strlen($newPassword) < 8) {
            throw new Exception("Password must be at least 8 characters long");
        }
        
        // Update password
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $db->update("UPDATE users SET password_hash = :password_hash WHERE id = :id", 
                   ['password_hash' => $passwordHash, 'id' => $userId]);
        
        $message = "Password changed successfully!";
        
    } catch (Exception $e) {
        $error = "Failed to change password: " . $e->getMessage();
    }
}

// Get student statistics
$enrolled_courses = $db->fetch("
    SELECT COUNT(*) as count FROM enrollments 
    WHERE student_id = :student_id AND status = 'enrolled'
", ['student_id' => $student['id']]);

$attendance_stats = $db->fetch("
    SELECT 
        COUNT(*) as total_sessions,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count
    FROM attendance 
    WHERE student_id = :student_id
", ['student_id' => $student['id']]);

$attendance_rate = $attendance_stats['total_sessions'] > 0 ? 
    round(($attendance_stats['present_count'] / $attendance_stats['total_sessions']) * 100, 1) : 0;

$grades_count = $db->fetch("
    SELECT COUNT(*) as count FROM grades g
    JOIN enrollments e ON g.enrollment_id = e.id
    WHERE e.student_id = :student_id
", ['student_id' => $student['id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | BDU Student System</title>
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
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
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
        
        .form-group input, .form-group select, .form-group textarea {
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--bg);
            color: var(--text);
        }
        
        .section-divider {
            margin: 2rem 0;
            padding: 1rem 0;
            border-top: 1px solid var(--border);
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
                    <li><a href="attendance.php">📅 Attendance</a></li>
                    <li><a href="courses.php">📚 Courses</a></li>
                    <li><a href="financial.php">💰 Financial</a></li>
                    <li><a href="documents.php">📄 Documents</a></li>
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

            <!-- Profile Overview -->
            <div class="card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                    </div>
                    <div class="profile-info">
                        <h1><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h1>
                        <p><strong>Student ID:</strong> <?php echo htmlspecialchars($student['student_id']); ?></p>
                        <p><strong>Department:</strong> <?php echo htmlspecialchars($student['department_name']); ?></p>
                        <p><strong>Year:</strong> Year <?php echo $student['year_level']; ?> • Semester <?php echo $student['semester']; ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($student['phone'] ?? 'Not provided'); ?></p>
                        <p><strong>Status:</strong> <span style="color: #10B981; font-weight: bold;"><?php echo ucfirst($student['status']); ?></span></p>
                    </div>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $student['gpa']; ?></div>
                        <div class="stat-label">Current GPA</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $enrolled_courses['count']; ?></div>
                        <div class="stat-label">Enrolled Courses</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $attendance_rate; ?>%</div>
                        <div class="stat-label">Attendance Rate</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $grades_count['count']; ?></div>
                        <div class="stat-label">Total Grades</div>
                    </div>
                </div>
            </div>

            <!-- Edit Profile Form -->
            <div class="card">
                <h2>✏️ Edit Profile Information</h2>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($student['first_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($student['last_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($student['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo htmlspecialchars($student['date_of_birth'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender">
                                <option value="">Select Gender</option>
                                <option value="male" <?php echo ($student['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo ($student['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo ($student['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="nationality">Nationality</label>
                            <input type="text" id="nationality" name="nationality" value="<?php echo htmlspecialchars($student['nationality'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="emergency_contact_name">Emergency Contact Name</label>
                            <input type="text" id="emergency_contact_name" name="emergency_contact_name" value="<?php echo htmlspecialchars($student['emergency_contact_name'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="emergency_contact_phone">Emergency Contact Phone</label>
                            <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" value="<?php echo htmlspecialchars($student['emergency_contact_phone'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($student['address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn btn-primary">💾 Update Profile</button>
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
                            <input type="password" id="new_password" name="new_password" required minlength="8">
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                        </div>
                    </div>
                    
                    <div style="margin-top: 1rem; color: var(--muted); font-size: 0.875rem;">
                        <p>Password must be at least 8 characters long.</p>
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-secondary" style="margin-top: 1rem;">🔐 Change Password</button>
                </form>
            </div>

            <!-- Account Information -->
            <div class="card">
                <h2>ℹ️ Account Information</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                    <div>
                        <h3 style="color: var(--text); margin: 0 0 0.5rem 0;">Academic Details</h3>
                        <ul style="color: var(--muted); font-size: 0.875rem; margin: 0; padding-left: 1.5rem;">
                            <li><strong>Student ID:</strong> <?php echo htmlspecialchars($student['student_id']); ?></li>
                            <li><strong>Department:</strong> <?php echo htmlspecialchars($student['department_name']); ?></li>
                            <li><strong>Year Level:</strong> Year <?php echo $student['year_level']; ?></li>
                            <li><strong>Semester:</strong> <?php echo $student['semester']; ?></li>
                            <li><strong>GPA:</strong> <?php echo $student['gpa']; ?></li>
                            <li><strong>Status:</strong> <?php echo ucfirst($student['status']); ?></li>
                        </ul>
                    </div>
                    
                    <div>
                        <h3 style="color: var(--text); margin: 0 0 0.5rem 0;">System Access</h3>
                        <ul style="color: var(--muted); font-size: 0.875rem; margin: 0; padding-left: 1.5rem;">
                            <li><strong>Username:</strong> <?php echo htmlspecialchars($student['username']); ?></li>
                            <li><strong>Role:</strong> Student</li>
                            <li><strong>Account Type:</strong> Student Account</li>
                            <li><strong>Portal Access:</strong> Full Student Portal</li>
                            <li><strong>Last Login:</strong> <?php echo $student['last_login'] ? date('M j, Y H:i', strtotime($student['last_login'])) : 'Never'; ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
