<?php
session_start();
require_once '../includes/Auth.php';
require_once '../includes/Database.php';

$auth = new Auth();
$auth->requireRole('teacher');

$db = Database::getInstance();
$userId = $auth->getUserId();

// Get courses taught by this teacher
$courses = $db->fetchAll("
    SELECT c.id, c.course_code, c.course_name, d.name as department_name,
           COUNT(e.student_id) as enrolled_students
    FROM courses c
    JOIN departments d ON c.department_id = d.id
    LEFT JOIN enrollments e ON c.id = e.course_id AND e.status = 'enrolled'
    WHERE c.teacher_id = :teacher_id AND c.is_active = 1
    GROUP BY c.id
    ORDER BY c.course_name
", ['teacher_id' => $userId]);

// Get selected course
$selected_course_id = $_GET['course'] ?? $courses[0]['id'] ?? null;
$selected_course = null;
$enrolled_students = [];
$attendance_records = [];

if ($selected_course_id) {
    // Get course details
    $selected_course = $db->fetch("
        SELECT c.*, d.name as department_name
        FROM courses c
        JOIN departments d ON c.department_id = d.id
        WHERE c.id = :course_id AND c.teacher_id = :teacher_id
    ", ['course_id' => $selected_course_id, 'teacher_id' => $userId]);
    
    // Get enrolled students
    $enrolled_students = $db->fetchAll("
        SELECT s.id as student_id, s.student_id as student_number,
               CONCAT(u.first_name, ' ', u.last_name) as student_name,
               u.email
        FROM enrollments e
        JOIN students s ON e.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE e.course_id = :course_id AND e.status = 'enrolled'
        ORDER BY u.last_name, u.first_name
    ", ['course_id' => $selected_course_id]);
    
    // Get today's attendance
    $today = date('Y-m-d');
    $attendance_records = $db->fetchAll("
        SELECT a.student_id, a.status, a.notes
        FROM attendance a
        WHERE a.course_id = :course_id AND a.attendance_date = :today
    ", ['course_id' => $selected_course_id, 'today' => $today]);
    
    // Create attendance map for easy lookup
    $attendance_map = [];
    foreach ($attendance_records as $record) {
        $attendance_map[$record['student_id']] = $record;
    }
}

// Handle attendance submission
if (isset($_POST['submit_attendance'])) {
    try {
        $attendance_date = $_POST['attendance_date'];
        $marked_by = $userId;
        
        $db->beginTransaction();
        
        foreach ($_POST['attendance'] as $student_id => $data) {
            // Check if attendance already exists for this date
            $existing = $db->fetch("
                SELECT id FROM attendance 
                WHERE student_id = :student_id AND course_id = :course_id AND attendance_date = :date
            ", [
                'student_id' => $student_id,
                'course_id' => $selected_course_id,
                'date' => $attendance_date
            ]);
            
            $attendance_data = [
                'student_id' => $student_id,
                'course_id' => $selected_course_id,
                'attendance_date' => $attendance_date,
                'status' => $data['status'],
                'marked_by' => $marked_by,
                'notes' => $data['notes'] ?? ''
            ];
            
            if ($existing) {
                // Update existing record
                $sql = "UPDATE attendance SET status = :status, notes = :notes, marked_by = :marked_by 
                        WHERE student_id = :student_id AND course_id = :course_id AND attendance_date = :date";
                $db->update($sql, $attendance_data);
            } else {
                // Insert new record
                $sql = "INSERT INTO attendance (student_id, course_id, attendance_date, status, marked_by, notes) 
                        VALUES (:student_id, :course_id, :attendance_date, :status, :marked_by, :notes)";
                $db->insert($sql, $attendance_data);
            }
        }
        
        $db->commit();
        $auth->logActivity($userId, 'mark_attendance', 'attendance', $selected_course_id, null, ['date' => $attendance_date]);
        
        $message = "Attendance marked successfully!";
        
        // Refresh data
        header("Location: attendance.php?course=$selected_course_id&success=1");
        exit;
        
    } catch (Exception $e) {
        $db->rollback();
        $error = "Failed to mark attendance: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Attendance | BDU Student System</title>
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
        
        .course-selector {
            margin-bottom: 2rem;
        }
        
        .course-selector select, .course-selector input {
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--bg);
            color: var(--text);
            font-size: 1rem;
        }
        
        .attendance-form {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
        }
        
        .date-selector {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .date-selector label {
            font-weight: 600;
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
        
        .attendance-options {
            display: flex;
            gap: 0.5rem;
        }
        
        .attendance-option {
            flex: 1;
            text-align: center;
            padding: 0.5rem;
            border: 2px solid var(--border);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .attendance-option input[type="radio"] {
            display: none;
        }
        
        .attendance-option.present {
            background: #D1FAE5;
            border-color: #10B981;
            color: #065F46;
        }
        
        .attendance-option.absent {
            background: #FEE2E2;
            border-color: #EF4444;
            color: #991B1B;
        }
        
        .attendance-option.late {
            background: #FEF3C7;
            border-color: #F59E0B;
            color: #92400E;
        }
        
        .attendance-option.excused {
            background: #E0E7FF;
            border-color: #6366F1;
            color: #3730A3;
        }
        
        .attendance-option:hover {
            transform: translateY(-2px);
        }
        
        .notes-input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--border);
            border-radius: 4px;
            background: var(--bg);
            color: var(--text);
            font-size: 0.875rem;
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
        .btn-success { background: #10B981; color: white; }
        
        .attendance-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .summary-card {
            text-align: center;
            padding: 1rem;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
        }
        
        .summary-value {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .summary-present { color: #10B981; }
        .summary-absent { color: #EF4444; }
        .summary-late { color: #F59E0B; }
        .summary-excused { color: #6366F1; }
        
        .summary-label {
            font-size: 0.875rem;
            color: var(--muted);
        }
        
        @media (max-width: 1024px) {
            .sidebar-layout {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .attendance-options {
                flex-direction: column;
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
                    <li><a href="#" class="active">📅 Mark Attendance</a></li>
                    <li><a href="analytics.php">📈 Analytics</a></li>
                    <li><a href="reports.php">📊 Reports</a></li>
                    <li><a href="profile.php">👤 Profile</a></li>
                    <li><a href="../logout.php">🚪 Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header style="margin-bottom: 2rem;">
                <h1 style="margin: 0; color: var(--text);">Mark Attendance</h1>
                <p style="margin: 0.5rem 0 0 0; color: var(--muted);">Track student attendance for your courses</p>
            </header>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">Attendance marked successfully!</div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Course Selection -->
            <div class="course-selector">
                <label for="course_select" style="display: block; margin-bottom: 0.5rem; color: var(--text); font-weight: 600;">Select Course:</label>
                <select id="course_select" onchange="window.location.href='attendance.php?course=' + this.value">
                    <?php foreach ($courses as $course): ?>
                        <option value="<?php echo $course['id']; ?>" 
                                <?php echo $selected_course_id == $course['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($selected_course): ?>
                <form method="POST" class="attendance-form">
                    <!-- Date Selection -->
                    <div class="date-selector">
                        <label for="attendance_date">Attendance Date:</label>
                        <input type="date" id="attendance_date" name="attendance_date" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                        <button type="submit" name="submit_attendance" class="btn btn-success">💾 Save Attendance</button>
                    </div>

                    <!-- Attendance Summary -->
                    <div class="attendance-summary">
                        <div class="summary-card">
                            <div class="summary-value summary-present" id="present-count">0</div>
                            <div class="summary-label">Present</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-value summary-absent" id="absent-count">0</div>
                            <div class="summary-label">Absent</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-value summary-late" id="late-count">0</div>
                            <div class="summary-label">Late</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-value summary-excused" id="excused-count">0</div>
                            <div class="summary-label">Excused</div>
                        </div>
                    </div>

                    <!-- Students Table -->
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Attendance Status</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($enrolled_students)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 2rem; color: var(--muted);">
                                        No students enrolled in this course.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($enrolled_students as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['student_number']); ?></td>
                                        <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                        <td>
                                            <div class="attendance-options">
                                                <label class="attendance-option present">
                                                    <input type="radio" name="attendance[<?php echo $student['student_id']; ?>][status]" 
                                                           value="present" 
                                                           <?php echo ($attendance_map[$student['student_id']]['status'] ?? 'present') === 'present' ? 'checked' : ''; ?>
                                                           onchange="updateSummary()">
                                                    ✓ Present
                                                </label>
                                                <label class="attendance-option absent">
                                                    <input type="radio" name="attendance[<?php echo $student['student_id']; ?>][status]" 
                                                           value="absent"
                                                           <?php echo ($attendance_map[$student['student_id']]['status'] ?? '') === 'absent' ? 'checked' : ''; ?>
                                                           onchange="updateSummary()">
                                                    ✗ Absent
                                                </label>
                                                <label class="attendance-option late">
                                                    <input type="radio" name="attendance[<?php echo $student['student_id']; ?>][status]" 
                                                           value="late"
                                                           <?php echo ($attendance_map[$student['student_id']]['status'] ?? '') === 'late' ? 'checked' : ''; ?>
                                                           onchange="updateSummary()">
                                                    ⏰ Late
                                                </label>
                                                <label class="attendance-option excused">
                                                    <input type="radio" name="attendance[<?php echo $student['student_id']; ?>][status]" 
                                                           value="excused"
                                                           <?php echo ($attendance_map[$student['student_id']]['status'] ?? '') === 'excused' ? 'checked' : ''; ?>
                                                           onchange="updateSummary()">
                                                    📋 Excused
                                                </label>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="text" 
                                                   name="attendance[<?php echo $student['student_id']; ?>][notes]" 
                                                   class="notes-input" 
                                                   placeholder="Add notes..."
                                                   value="<?php echo htmlspecialchars($attendance_map[$student['student_id']]['notes'] ?? ''); ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </form>

            <?php else: ?>
                <div class="card">
                    <h2>📅 No Course Selected</h2>
                    <p style="color: var(--muted); text-align: center; padding: 2rem 0;">
                        Please select a course from the dropdown above to mark attendance.
                    </p>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function updateSummary() {
            const radios = document.querySelectorAll('input[type="radio"]:checked');
            const summary = {
                present: 0,
                absent: 0,
                late: 0,
                excused: 0
            };
            
            radios.forEach(radio => {
                if (radio.value) {
                    summary[radio.value]++;
                }
            });
            
            document.getElementById('present-count').textContent = summary.present;
            document.getElementById('absent-count').textContent = summary.absent;
            document.getElementById('late-count').textContent = summary.late;
            document.getElementById('excused-count').textContent = summary.excused;
        }
        
        // Initialize summary on page load
        document.addEventListener('DOMContentLoaded', updateSummary);
    </script>
</body>
</html>
