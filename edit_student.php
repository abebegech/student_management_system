<?php
session_start();
require_once '../includes/Auth.php';
require_once '../includes/Database.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();

// Get student ID from URL
$studentId = $_GET['id'] ?? null;

if (!$studentId) {
    header('Location: students.php');
    exit;
}

// Get student details
$student = $db->fetch("
    SELECT s.*, u.username, u.email, u.phone, u.first_name, u.last_name,
           d.name as department_name
    FROM students s
    JOIN users u ON s.user_id = u.id
    JOIN departments d ON s.department_id = d.id
    WHERE s.id = :id
", ['id' => $studentId]);

if (!$student) {
    header('Location: students.php');
    exit;
}

// Handle form submission
if (isset($_POST['update_student'])) {
    try {
        $db->beginTransaction();
        
        // Update user information
        $userData = [
            'first_name' => $_POST['first_name'],
            'last_name' => $_POST['last_name'],
            'email' => $_POST['email'],
            'phone' => $_POST['phone'] ?? null,
            'user_id' => $student['user_id']
        ];
        
        $userSql = "UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email, phone = :phone, updated_at = CURRENT_TIMESTAMP WHERE id = :user_id";
        $db->update($userSql, $userData);
        
        // Update student information
        $studentData = [
            'student_id' => $_POST['student_id'],
            'department_id' => $_POST['department_id'],
            'year_level' => $_POST['year_level'],
            'semester' => $_POST['semester'],
            'gpa' => $_POST['gpa'],
            'status' => $_POST['status'],
            'emergency_contact_name' => $_POST['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $_POST['emergency_contact_phone'] ?? null,
            'address' => $_POST['address'] ?? null,
            'date_of_birth' => $_POST['date_of_birth'] ?? null,
            'gender' => $_POST['gender'] ?? null,
            'nationality' => $_POST['nationality'] ?? null,
            'student_record_id' => $studentId
        ];
        
        $studentSql = "UPDATE students SET student_id = :student_id, department_id = :department_id, year_level = :year_level, semester = :semester, gpa = :gpa, status = :status, emergency_contact_name = :emergency_contact_name, emergency_contact_phone = :emergency_contact_phone, address = :address, date_of_birth = :date_of_birth, gender = :gender, nationality = :nationality, updated_at = CURRENT_TIMESTAMP WHERE id = :student_record_id";
        $db->update($studentSql, $studentData);
        
        // Update password if provided
        if (!empty($_POST['new_password'])) {
            if ($_POST['new_password'] === $_POST['confirm_password']) {
                $passwordHash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                $db->update("UPDATE users SET password_hash = :password_hash WHERE id = :user_id", 
                           ['password_hash' => $passwordHash, 'user_id' => $student['user_id']]);
            } else {
                throw new Exception("Passwords do not match");
            }
        }
        
        $db->commit();
        $auth->logActivity($auth->getUserId(), 'update_student', 'students', $studentId, null, array_merge($userData, $studentData));
        
        $message = "Student information updated successfully!";
        
        // Refresh student data
        $student = $db->fetch("
            SELECT s.*, u.username, u.email, u.phone, u.first_name, u.last_name,
                   d.name as department_name
            FROM students s
            JOIN users u ON s.user_id = u.id
            JOIN departments d ON s.department_id = d.id
            WHERE s.id = :id
        ", ['id' => $studentId]);
        
    } catch (Exception $e) {
        $db->rollback();
        $error = "Failed to update student: " . $e->getMessage();
    }
}

// Get departments for dropdown
$departments = $db->fetchAll("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student | BDU Student System</title>
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
                    <li><a href="users.php">👥 User Management</a></li>
                    <li><a href="#" class="active">🎓 Students</a></li>
                    <li><a href="teachers.php">👨‍🏫 Teachers</a></li>
                    <li><a href="courses.php">📚 Courses</a></li>
                    <li><a href="departments.php">🏢 Departments</a></li>
                    <li><a href="financial_ledger.php">💰 Financial Ledger</a></li>
                    <li><a href="reports.php">📈 Reports</a></li>
                    <li><a href="logs.php">📋 Activity Logs</a></li>
                    <li><a href="class_assignment.php">📅 Class Assignment</a></li>
                    <li><a href="backup.php">💾 Backup</a></li>
                    <li><a href="../logout.php">🚪 Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header style="margin-bottom: 2rem;">
                <h1 style="margin: 0; color: var(--text);">Edit Student</h1>
                <p style="margin: 0.5rem 0 0 0; color: var(--muted);">Update student information and academic records</p>
            </header>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <!-- Personal Information -->
                <div class="card">
                    <h2>👤 Personal Information</h2>
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
                            <label for="address">Address</label>
                            <textarea id="address" name="address" rows="2"><?php echo htmlspecialchars($student['address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Academic Information -->
                <div class="card">
                    <h2>🎓 Academic Information</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="student_id">Student ID</label>
                            <input type="text" id="student_id" name="student_id" value="<?php echo htmlspecialchars($student['student_id']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="department">Department</label>
                            <select id="department" name="department_id" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?php echo $department['id']; ?>" 
                                            <?php echo $student['department_id'] == $department['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($department['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="year_level">Year Level</label>
                            <select id="year_level" name="year_level" required>
                                <option value="1" <?php echo $student['year_level'] == 1 ? 'selected' : ''; ?>>Year 1</option>
                                <option value="2" <?php echo $student['year_level'] == 2 ? 'selected' : ''; ?>>Year 2</option>
                                <option value="3" <?php echo $student['year_level'] == 3 ? 'selected' : ''; ?>>Year 3</option>
                                <option value="4" <?php echo $student['year_level'] == 4 ? 'selected' : ''; ?>>Year 4</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="semester">Semester</label>
                            <select id="semester" name="semester" required>
                                <option value="1" <?php echo $student['semester'] == 1 ? 'selected' : ''; ?>>Semester 1</option>
                                <option value="2" <?php echo $student['semester'] == 2 ? 'selected' : ''; ?>>Semester 2</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="gpa">GPA</label>
                            <input type="number" id="gpa" name="gpa" step="0.01" min="0" max="4" value="<?php echo $student['gpa']; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" required>
                                <option value="active" <?php echo $student['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $student['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="graduated" <?php echo $student['status'] === 'graduated' ? 'selected' : ''; ?>>Graduated</option>
                                <option value="suspended" <?php echo $student['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Emergency Contact -->
                <div class="card">
                    <h2>🆘 Emergency Contact</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="emergency_contact_name">Contact Name</label>
                            <input type="text" id="emergency_contact_name" name="emergency_contact_name" value="<?php echo htmlspecialchars($student['emergency_contact_name'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="emergency_contact_phone">Contact Phone</label>
                            <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" value="<?php echo htmlspecialchars($student['emergency_contact_phone'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- Password Change -->
                <div class="card">
                    <h2>🔒 Password Change</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" placeholder="Leave blank to keep current password">
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password">
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="card">
                    <button type="submit" name="update_student" class="btn btn-primary">💾 Update Student</button>
                    <a href="view_student.php?id=<?php echo $studentId; ?>" class="btn btn-secondary">← Cancel</a>
                </div>
            </form>
        </main>
    </div>
</body>
</html>
