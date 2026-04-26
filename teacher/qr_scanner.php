<?php
session_start();
require_once '../includes/Auth.php';
require_once '../includes/Database.php';
require_once '../includes/QRGenerator.php';

$auth = new Auth();
$auth->requireRole('teacher');

$db = Database::getInstance();
$userId = $auth->getUserId();
$qrGen = new QRGenerator();

// Get courses taught by this teacher
$courses = $db->fetchAll("
    SELECT c.id, c.course_code, c.course_name, d.name as department_name
    FROM courses c
    JOIN departments d ON c.department_id = d.id
    WHERE c.teacher_id = :teacher_id AND c.is_active = 1
    ORDER BY c.course_name
", ['teacher_id' => $userId]);

// Get selected course
$selected_course_id = $_GET['course'] ?? $courses[0]['id'] ?? null;
$selected_course = null;

if ($selected_course_id) {
    $selected_course = $db->fetch("
        SELECT c.*, d.name as department_name
        FROM courses c
        JOIN departments d ON c.department_id = d.id
        WHERE c.id = :course_id AND c.teacher_id = :teacher_id
    ", ['course_id' => $selected_course_id, 'teacher_id' => $userId]);
}

// Handle QR code generation for course
if (isset($_POST['generate_course_qr'])) {
    try {
        $qrCode = $qrGen->generateCourseQRCode($selected_course_id);
        $message = "Course QR code generated successfully!";
    } catch (Exception $e) {
        $error = "Failed to generate QR code: " . $e->getMessage();
    }
}

// Handle QR code scanning
if (isset($_POST['scan_qr'])) {
    $qrData = $_POST['qr_data'];
    
    try {
        $result = $qrGen->markAttendanceByQR($qrData, $selected_course_id, $userId);
        
        if ($result['success']) {
            $message = $result['message'];
            $student_info = [
                'name' => $result['student_name'],
                'id' => $result['student_id']
            ];
        } else {
            $error = $result['message'];
        }
    } catch (Exception $e) {
        $error = "Failed to process QR code: " . $e->getMessage();
    }
}

// Get today's attendance for this course
if ($selected_course) {
    $today_attendance = $db->fetchAll("
        SELECT a.*, s.student_id, CONCAT(u.first_name, ' ', u.last_name) as student_name
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE a.course_id = :course_id AND a.attendance_date = CURRENT_DATE
        ORDER BY a.created_at DESC
    ", ['course_id' => $selected_course_id]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Scanner | BDU Student System</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/qr-scanner@1.4.2/qr-scanner.umd.min.js"></script>
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
        
        .course-selector select {
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--bg);
            color: var(--text);
            font-size: 1rem;
            min-width: 300px;
        }
        
        .scanner-container {
            background: var(--card-bg);
            border: 2px dashed var(--border);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
            position: relative;
        }
        
        .scanner-container.active {
            border-color: var(--primary);
            background: rgba(79, 70, 229, 0.05);
        }
        
        #qr-video {
            width: 100%;
            max-width: 500px;
            height: 300px;
            border-radius: 8px;
            background: #000;
        }
        
        .scanner-controls {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1rem;
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
        
        .qr-display {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .qr-code-container {
            text-align: center;
            padding: 2rem;
            background: white;
            border-radius: 8px;
            border: 1px solid var(--border);
        }
        
        .qr-code-container img {
            max-width: 200px;
            height: auto;
        }
        
        .attendance-log {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .attendance-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            border-bottom: 1px solid var(--border);
        }
        
        .attendance-item:last-child {
            border-bottom: none;
        }
        
        .attendance-success {
            color: #10B981;
            font-weight: bold;
        }
        
        .manual-input {
            margin-top: 1rem;
            padding: 1rem;
            background: var(--bg);
            border-radius: 8px;
            border: 1px solid var(--border);
        }
        
        .manual-input textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--card-bg);
            color: var(--text);
            font-family: monospace;
            resize: vertical;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            text-align: center;
            padding: 1rem;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 8px;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary);
        }
        
        .stat-label {
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
            
            .qr-display {
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
                    <li><a href="#" class="active">📱 QR Scanner</a></li>
                    <li><a href="profile.php">👤 Profile</a></li>
                    <li><a href="../logout.php">🚪 Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header style="margin-bottom: 2rem;">
                <h1 style="margin: 0; color: var(--text);">QR Attendance Scanner</h1>
                <p style="margin: 0.5rem 0 0 0; color: var(--muted);">Scan student QR codes for quick attendance marking</p>
            </header>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Course Selection -->
            <div class="course-selector">
                <label for="course_select" style="display: block; margin-bottom: 0.5rem; color: var(--text); font-weight: 600;">Select Course:</label>
                <select id="course_select" onchange="window.location.href='qr_scanner.php?course=' + this.value">
                    <?php foreach ($courses as $course): ?>
                        <option value="<?php echo $course['id']; ?>" 
                                <?php echo $selected_course_id == $course['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($selected_course): ?>
                <!-- Course Info -->
                <div class="card">
                    <h2>📚 <?php echo htmlspecialchars($selected_course['course_code']); ?> - <?php echo htmlspecialchars($selected_course['course_name']); ?></h2>
                    <p style="color: var(--muted);"><?php echo htmlspecialchars($selected_course['department_name']); ?> • <?php echo date('l, F j, Y'); ?></p>
                </div>

                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo count($today_attendance); ?></div>
                        <div class="stat-label">Scanned Today</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">
                            <?php 
                            $total_students = $db->fetch("SELECT COUNT(*) as count FROM enrollments WHERE course_id = ? AND status = 'enrolled'", [$selected_course_id])['count'];
                            echo $total_students;
                            ?>
                        </div>
                        <div class="stat-label">Total Students</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">
                            <?php 
                            $scanned_percentage = $total_students > 0 ? round((count($today_attendance) / $total_students) * 100, 1) : 0;
                            echo $scanned_percentage . '%';
                            ?>
                        </div>
                        <div class="stat-label">Coverage</div>
                    </div>
                </div>

                <!-- QR Scanner -->
                <div class="card">
                    <h2>📱 QR Scanner</h2>
                    <div class="scanner-container" id="scanner-container">
                        <video id="qr-video"></video>
                        <div class="scanner-controls">
                            <button class="btn btn-primary" id="start-scan">Start Scanner</button>
                            <button class="btn btn-danger" id="stop-scan" style="display: none;">Stop Scanner</button>
                        </div>
                        
                        <div class="manual-input">
                            <p style="margin-bottom: 0.5rem; color: var(--muted);">Or enter QR code manually:</p>
                            <textarea id="manual-qr" rows="3" placeholder="Paste QR code data here..."></textarea>
                            <button class="btn btn-secondary" onclick="processManualQR()">Process QR Code</button>
                        </div>
                    </div>
                </div>

                <!-- QR Code Display -->
                <div class="card">
                    <h2>📋 Course QR Code</h2>
                    <form method="POST">
                        <div style="text-align: center; margin-bottom: 1rem;">
                            <p style="color: var(--muted);">Generate a QR code for students to scan for this course</p>
                            <button type="submit" name="generate_course_qr" class="btn btn-primary">Generate Course QR Code</button>
                        </div>
                    </form>
                    
                    <?php if (isset($qrCode)): ?>
                        <div class="qr-code-container">
                            <h3>Course QR Code</h3>
                            <div id="course-qr-code"></div>
                            <p style="color: var(--muted); font-size: 0.875rem; margin-top: 1rem;">
                                Valid for 2 hours • Generated: <?php echo date('H:i'); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Today's Attendance Log -->
                <div class="card">
                    <h2>📊 Today's Attendance Log</h2>
                    <div class="attendance-log">
                        <?php if (empty($today_attendance)): ?>
                            <p style="color: var(--muted); text-align: center; padding: 2rem 0;">No attendance scanned yet today</p>
                        <?php else: ?>
                            <?php foreach ($today_attendance as $attendance): ?>
                                <div class="attendance-item">
                                    <div>
                                        <strong><?php echo htmlspecialchars($attendance['student_name']); ?></strong>
                                        <div style="color: var(--muted); font-size: 0.875rem;">
                                            <?php echo htmlspecialchars($attendance['student_id']); ?> • 
                                            <?php echo date('H:i', strtotime($attendance['created_at'])); ?>
                                        </div>
                                    </div>
                                    <div class="attendance-success">
                                        ✓ Present
                                        <?php if ($attendance['notes'] === 'Marked via QR code scan'): ?>
                                            <div style="font-size: 0.75rem;">QR</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>
                <div class="card">
                    <h2>📱 No Course Selected</h2>
                    <p style="color: var(--muted); text-align: center; padding: 2rem 0;">
                        Please select a course from the dropdown above to start scanning QR codes.
                    </p>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        let scanner = null;
        let isScanning = false;
        
        // QR Scanner functionality
        document.getElementById('start-scan').addEventListener('click', function() {
            startScanner();
        });
        
        document.getElementById('stop-scan').addEventListener('click', function() {
            stopScanner();
        });
        
        function startScanner() {
            const video = document.getElementById('qr-video');
            const scannerContainer = document.getElementById('scanner-container');
            
            scannerContainer.classList.add('active');
            document.getElementById('start-scan').style.display = 'none';
            document.getElementById('stop-scan').style.display = 'inline-block';
            
            scanner = new QrScanner(
                video,
                result => {
                    processQRResult(result);
                },
                error => {
                    console.error(error);
                }
            );
            
            scanner.start().then(() => {
                isScanning = true;
            }).catch(error => {
                console.error('Failed to start scanner:', error);
                alert('Camera access denied or not available. Please use manual input.');
                stopScanner();
            });
        }
        
        function stopScanner() {
            if (scanner) {
                scanner.stop();
                scanner = null;
            }
            
            const scannerContainer = document.getElementById('scanner-container');
            scannerContainer.classList.remove('active');
            document.getElementById('start-scan').style.display = 'inline-block';
            document.getElementById('stop-scan').style.display = 'none';
            isScanning = false;
        }
        
        function processQRResult(qrData) {
            // Submit the QR data via AJAX
            const formData = new FormData();
            formData.append('scan_qr', '1');
            formData.append('qr_data', qrData);
            formData.append('course_id', '<?php echo $selected_course_id; ?>');
            
            fetch('qr_scanner.php?course=<?php echo $selected_course_id; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                // Reload page to show updated attendance
                window.location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to process QR code. Please try again.');
            });
            
            // Stop scanner after successful scan
            if (isScanning) {
                stopScanner();
            }
        }
        
        function processManualQR() {
            const manualQR = document.getElementById('manual-qr').value.trim();
            if (!manualQR) {
                alert('Please enter QR code data');
                return;
            }
            
            processQRResult(manualQR);
        }
        
        // Generate QR code if available
        <?php if (isset($qrCode)): ?>
        function generateQRCode(text) {
            // Simple QR code generation using a service or library
            const qrContainer = document.getElementById('course-qr-code');
            qrContainer.innerHTML = `
                <div style="background: white; padding: 20px; border-radius: 8px; display: inline-block;">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(text)}" 
                         alt="Course QR Code" />
                </div>
            `;
        }
        
        generateQRCode('<?php echo $qrCode; ?>');
        <?php endif; ?>
    </script>
</body>
</html>
