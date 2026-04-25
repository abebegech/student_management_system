<?php
session_start();
require_once '../includes/Auth.php';
require_once '../includes/Database.php';
require_once '../includes/ScheduleConflictChecker.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();
$conflictChecker = new ScheduleConflictChecker();

// Handle class assignment creation
if (isset($_POST['assign_class'])) {
    try {
        $courseId = $_POST['course_id'];
        $teacherId = $_POST['teacher_id'];
        $roomId = $_POST['room_id'];
        $dayOfWeek = $_POST['day_of_week'];
        $startTime = $_POST['start_time'];
        $endTime = $_POST['end_time'];
        $semester = $_POST['semester'];
        $yearLevel = $_POST['year_level'];
        $academicYear = $_POST['academic_year'];
        
        $result = $conflictChecker->createSchedule(
            $courseId, $teacherId, $roomId, $dayOfWeek, 
            $startTime, $endTime, $semester, $yearLevel, $academicYear
        );
        
        if ($result['success']) {
            $message = $result['message'];
            $auth->logActivity($auth->getUserId(), 'create_class_schedule', 'class_schedules', $result['schedule_id']);
        } else {
            $error = $result['message'];
            $conflicts = $conflictChecker->formatConflicts($result['conflicts'] ?? []);
        }
        
    } catch (Exception $e) {
        $error = "Failed to assign class: " . $e->getMessage();
    }
}

// Handle AJAX conflict checking
if (isset($_POST['check_conflicts'])) {
    header('Content-Type: application/json');
    
    try {
        $courseId = $_POST['course_id'];
        $teacherId = $_POST['teacher_id'];
        $roomId = $_POST['room_id'];
        $dayOfWeek = $_POST['day_of_week'];
        $startTime = $_POST['start_time'];
        $endTime = $_POST['end_time'];
        $semester = $_POST['semester'];
        $academicYear = $_POST['academic_year'];
        
        $conflicts = $conflictChecker->checkConflicts(
            $courseId, $teacherId, $roomId, $dayOfWeek, 
            $startTime, $endTime, $semester, $academicYear
        );
        
        $response = [
            'has_conflicts' => !empty($conflicts),
            'conflicts' => $conflictChecker->formatConflicts($conflicts)
        ];
        
        echo json_encode($response);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// Get existing schedules
$schedules = $db->fetchAll("
    SELECT cs.*, c.course_code, c.course_name, d.name as department_name,
           CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
           r.room_number, r.building
    FROM class_schedules cs
    JOIN courses c ON cs.course_id = c.id
    JOIN departments d ON c.department_id = d.id
    JOIN users u ON cs.teacher_id = u.id
    JOIN rooms r ON cs.room_id = r.id
    WHERE cs.is_active = 1
    ORDER BY cs.day_of_week, cs.start_time
");

// Get dropdown data
$courses = $db->fetchAll("
    SELECT c.id, c.course_code, c.course_name, d.name as department_name
    FROM courses c
    JOIN departments d ON c.department_id = d.id
    WHERE c.is_active = 1
    ORDER BY c.course_code
");

$teachers = $db->fetchAll("
    SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) as full_name, d.name as department_name
    FROM users u
    LEFT JOIN departments d ON u.id = d.head_of_department
    WHERE u.role = 'teacher' AND u.is_active = 1
    ORDER BY u.last_name, u.first_name
");

$rooms = $db->fetchAll("
    SELECT * FROM rooms WHERE is_active = 1 ORDER BY room_number
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Assignment | BDU Student System</title>
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
        
        .form-group input, .form-group select {
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--bg);
            color: var(--text);
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
        .btn-success { background: #10B981; color: white; }
        .btn-danger { background: #EF4444; color: white; }
        
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
        
        .conflict-warning {
            background: #FEF3C7;
            border: 1px solid #F59E0B;
            color: #92400E;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .conflict-error {
            background: #FEE2E2;
            border: 1px solid #EF4444;
            color: #991B1B;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .conflict-item {
            margin-bottom: 0.5rem;
            padding-left: 1rem;
            position: relative;
        }
        
        .conflict-item::before {
            content: "•";
            position: absolute;
            left: 0;
            color: #EF4444;
            font-weight: bold;
        }
        
        .time-slots {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .time-slot {
            padding: 0.5rem;
            text-align: center;
            border: 1px solid var(--border);
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .time-slot:hover {
            background: var(--primary);
            color: white;
        }
        
        .schedule-grid {
            display: grid;
            grid-template-columns: 80px repeat(5, 1fr);
            gap: 1px;
            background: var(--border);
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .schedule-header {
            background: var(--primary);
            color: white;
            padding: 0.75rem;
            text-align: center;
            font-weight: bold;
        }
        
        .schedule-cell {
            background: var(--card-bg);
            padding: 0.5rem;
            min-height: 60px;
            font-size: 0.875rem;
        }
        
        .schedule-item {
            background: var(--primary);
            color: white;
            padding: 0.25rem;
            border-radius: 4px;
            margin-bottom: 0.25rem;
            font-size: 0.75rem;
        }
        
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
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
                    <li><a href="students.php">🎓 Students</a></li>
                    <li><a href="teachers.php">👨‍🏫 Teachers</a></li>
                    <li><a href="courses.php">📚 Courses</a></li>
                    <li><a href="departments.php">🏢 Departments</a></li>
                    <li><a href="financial_ledger.php">💰 Financial Ledger</a></li>
                    <li><a href="reports.php">📈 Reports</a></li>
                    <li><a href="logs.php">📋 Activity Logs</a></li>
                    <li><a href="#" class="active">📅 Class Assignment</a></li>
                    <li><a href="backup.php">💾 Backup</a></li>
                    <li><a href="../logout.php">🚪 Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header style="margin-bottom: 2rem;">
                <h1 style="margin: 0; color: var(--text);">Class Assignment</h1>
                <p style="margin: 0.5rem 0 0 0; color: var(--muted);">Assign classes with automatic conflict detection</p>
            </header>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Assignment Form -->
            <div class="card">
                <h2>📅 Assign New Class</h2>
                
                <?php if (!empty($conflicts)): ?>
                    <div class="conflict-error">
                        <strong>⚠️ Scheduling Conflicts Detected:</strong>
                        <?php foreach ($conflicts as $type => $conflictList): ?>
                            <div style="margin-top: 0.5rem;">
                                <strong><?php echo ucfirst($type); ?> Conflicts:</strong>
                                <?php foreach ($conflictList as $conflict): ?>
                                    <div class="conflict-item"><?php echo htmlspecialchars($conflict['message']); ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="assignmentForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="course">Course</label>
                            <select id="course" name="course_id" required onchange="checkConflicts()">
                                <option value="">Select Course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>">
                                        <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="teacher">Teacher</label>
                            <select id="teacher" name="teacher_id" required onchange="checkConflicts()">
                                <option value="">Select Teacher</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>">
                                        <?php echo htmlspecialchars($teacher['full_name'] . ' (' . ($teacher['department_name'] ?? 'No Department') . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="room">Room</label>
                            <select id="room" name="room_id" required onchange="checkConflicts()">
                                <option value="">Select Room</option>
                                <?php foreach ($rooms as $room): ?>
                                    <option value="<?php echo $room['id']; ?>">
                                        <?php echo htmlspecialchars($room['room_number'] . ' - ' . $room['building'] . ' (Capacity: ' . $room['capacity'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="day_of_week">Day</label>
                            <select id="day_of_week" name="day_of_week" required onchange="checkConflicts()">
                                <option value="">Select Day</option>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                                <option value="Sunday">Sunday</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="start_time">Start Time</label>
                            <select id="start_time" name="start_time" required onchange="checkConflicts()">
                                <option value="">Select Time</option>
                                <?php for ($hour = 8; $hour <= 20; $hour++): ?>
                                    <?php for ($min = 0; $min < 60; $min += 30): ?>
                                        <option value="<?php echo sprintf('%02d:%02d:00', $hour, $min); ?>">
                                            <?php echo sprintf('%02d:%02d', $hour, $min); ?>
                                        </option>
                                    <?php endfor; ?>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="end_time">End Time</label>
                            <select id="end_time" name="end_time" required onchange="checkConflicts()">
                                <option value="">Select Time</option>
                                <?php for ($hour = 8; $hour <= 20; $hour++): ?>
                                    <?php for ($min = 0; $min < 60; $min += 30): ?>
                                        <option value="<?php echo sprintf('%02d:%02d:00', $hour, $min); ?>">
                                            <?php echo sprintf('%02d:%02d', $hour, $min); ?>
                                        </option>
                                    <?php endfor; ?>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="semester">Semester</label>
                            <select id="semester" name="semester" required onchange="checkConflicts()">
                                <option value="">Select Semester</option>
                                <option value="Fall">Fall</option>
                                <option value="Spring">Spring</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="year_level">Year Level</label>
                            <select id="year_level" name="year_level" required>
                                <option value="">Select Year</option>
                                <option value="1">Year 1</option>
                                <option value="2">Year 2</option>
                                <option value="3">Year 3</option>
                                <option value="4">Year 4</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="academic_year">Academic Year</label>
                            <input type="text" id="academic_year" name="academic_year" value="2024-2025" required>
                        </div>
                    </div>
                    
                    <div id="conflictStatus"></div>
                    
                    <button type="submit" name="assign_class" class="btn btn-primary" id="submitBtn">Assign Class</button>
                </form>
            </div>

            <!-- Schedule Overview -->
            <div class="card">
                <h2>📊 Current Schedule Overview</h2>
                
                <div class="schedule-grid">
                    <div class="schedule-header">Time</div>
                    <div class="schedule-header">Monday</div>
                    <div class="schedule-header">Tuesday</div>
                    <div class="schedule-header">Wednesday</div>
                    <div class="schedule-header">Thursday</div>
                    <div class="schedule-header">Friday</div>
                    
                    <?php
                    $timeSlots = [];
                    for ($hour = 8; $hour <= 20; $hour++) {
                        for ($min = 0; $min < 60; $min += 30) {
                            $timeSlots[] = sprintf('%02d:%02d', $hour, $min);
                        }
                    }
                    
                    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
                    
                    foreach ($timeSlots as $time): ?>
                        <div class="schedule-header"><?php echo $time; ?></div>
                        <?php foreach ($days as $day): ?>
                            <div class="schedule-cell">
                                <?php
                                $slotSchedules = array_filter($schedules, function($schedule) use ($day, $time) {
                                    $scheduleTime = substr($schedule['start_time'], 0, 5);
                                    return $schedule['day_of_week'] === $day && $scheduleTime === $time;
                                });
                                
                                foreach ($slotSchedules as $schedule): ?>
                                    <div class="schedule-item">
                                        <?php echo htmlspecialchars($schedule['course_code']); ?><br>
                                        <?php echo htmlspecialchars($schedule['room_number']); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Detailed Schedule List -->
            <div class="card">
                <h2>📋 Detailed Schedule</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Teacher</th>
                            <th>Room</th>
                            <th>Day</th>
                            <th>Time</th>
                            <th>Semester</th>
                            <th>Year</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($schedules)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 2rem; color: var(--muted);">
                                    No classes scheduled yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($schedules as $schedule): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($schedule['course_code'] . ' - ' . $schedule['course_name']); ?></td>
                                    <td><?php echo htmlspecialchars($schedule['teacher_name']); ?></td>
                                    <td><?php echo htmlspecialchars($schedule['room_number'] . ' - ' . $schedule['building']); ?></td>
                                    <td><?php echo htmlspecialchars($schedule['day_of_week']); ?></td>
                                    <td><?php echo substr($schedule['start_time'], 0, 5) . ' - ' . substr($schedule['end_time'], 0, 5); ?></td>
                                    <td><?php echo htmlspecialchars($schedule['semester']); ?></td>
                                    <td>Year <?php echo $schedule['year_level']; ?></td>
                                    <td>
                                        <button class="btn btn-secondary btn-sm" onclick="editSchedule(<?php echo $schedule['id']; ?>)">Edit</button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteSchedule(<?php echo $schedule['id']; ?>)">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
        let conflictCheckTimeout;
        
        function checkConflicts() {
            const form = document.getElementById('assignmentForm');
            const formData = new FormData(form);
            
            // Add conflict check flag
            formData.set('check_conflicts', '1');
            
            // Clear previous timeout
            if (conflictCheckTimeout) {
                clearTimeout(conflictCheckTimeout);
            }
            
            // Add loading state
            document.getElementById('conflictStatus').innerHTML = '<div class="loading">Checking for conflicts...</div>';
            
            // Debounce the check
            conflictCheckTimeout = setTimeout(() => {
                fetch('class_assignment.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    displayConflictStatus(data);
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('conflictStatus').innerHTML = '';
                });
            }, 500);
        }
        
        function displayConflictStatus(data) {
            const statusDiv = document.getElementById('conflictStatus');
            const submitBtn = document.getElementById('submitBtn');
            
            if (data.error) {
                statusDiv.innerHTML = '<div class="conflict-error">Error: ' + data.error + '</div>';
                submitBtn.disabled = false;
                return;
            }
            
            if (data.has_conflicts) {
                let html = '<div class="conflict-error"><strong>⚠️ Conflicts Detected:</strong>';
                
                for (const [type, conflicts] of Object.entries(data.conflicts)) {
                    html += '<div style="margin-top: 0.5rem;"><strong>' + type.charAt(0).toUpperCase() + type.slice(1) + ' Conflicts:</strong>';
                    conflicts.forEach(conflict => {
                        html += '<div class="conflict-item">' + conflict.message + '</div>';
                    });
                    html += '</div>';
                }
                
                html += '</div>';
                statusDiv.innerHTML = html;
                submitBtn.disabled = true;
            } else {
                statusDiv.innerHTML = '<div class="alert alert-success">✅ No conflicts detected</div>';
                submitBtn.disabled = false;
            }
        }
        
        function editSchedule(scheduleId) {
            // Implementation for editing schedule
            alert('Edit functionality coming soon!');
        }
        
        function deleteSchedule(scheduleId) {
            if (confirm('Are you sure you want to delete this schedule?')) {
                // Implementation for deleting schedule
                alert('Delete functionality coming soon!');
            }
        }
        
        // Auto-check conflicts when form fields change
        document.querySelectorAll('#assignmentForm select, #assignmentForm input').forEach(element => {
            element.addEventListener('change', function() {
                // Only check if all required fields are filled
                const requiredFields = ['course_id', 'teacher_id', 'room_id', 'day_of_week', 'start_time', 'end_time', 'semester'];
                const allFilled = requiredFields.every(fieldId => {
                    const field = document.getElementById(fieldId.replace('_', ''));
                    return field && field.value;
                });
                
                if (allFilled) {
                    checkConflicts();
                }
            });
        });
    </script>
</body>
</html>
