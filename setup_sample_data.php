<?php
/**
 * Setup Sample Data for Testing Advanced Features
 * Run this in your browser: http://localhost/student-system/setup_sample_data.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🚀 Setting Up Sample Data for Testing</h2>";

// Database configuration
$host = 'localhost';
$dbname = 'student_system';
$username = 'root';
$password = '';

try {
    // Connect to database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✓ Connected to database '$dbname'<br>";
    
    // Get current users
    $users = $pdo->query("SELECT id, username, role FROM users WHERE is_active = 1")->fetchAll();
    $teachers = array_filter($users, function($user) { return $user['role'] === 'teacher'; });
    $students = array_filter($users, function($user) { return $user['role'] === 'student'; });
    
    echo "✓ Found " . count($teachers) . " teachers and " . count($students) . " students<br>";
    
    // Create sample courses if none exist
    $existingCourses = $pdo->query("SELECT COUNT(*) as count FROM courses")->fetch()['count'];
    
    if ($existingCourses == 0) {
        echo "<h3>Creating Sample Courses</h3>";
        
        $sampleCourses = [
            ['CS101', 'Introduction to Programming', 1, 3, 'Learn basic programming concepts'],
            ['CS201', 'Data Structures', 1, 4, 'Advanced data structures and algorithms'],
            ['MATH101', 'Calculus I', 2, 4, 'Introduction to differential and integral calculus'],
            ['ENG101', 'English Composition', 2, 3, 'Academic writing and communication'],
            ['PHYS101', 'Physics I', 3, 4, 'Mechanics and thermodynamics'],
            ['CHEM101', 'Chemistry I', 3, 3, 'General chemistry principles']
        ];
        
        foreach ($sampleCourses as $index => $course) {
            // Assign teacher (cycle through available teachers)
            $teacherId = !empty($teachers) ? $teachers[$index % count($teachers)]['id'] : 1;
            $departmentId = ($index % 3) + 1; // Cycle through departments 1-3
            
            $sql = "INSERT INTO courses (course_code, course_name, department_id, credits, description, teacher_id, semester, year_level, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)";
            
            $pdo->prepare($sql)->execute([
                $course[0], $course[1], $departmentId, $course[2], $course[3], $teacherId, 1, 1
            ]);
            
            echo "✓ Created course: {$course[0]} - {$course[1]}<br>";
        }
    } else {
        echo "✓ Courses already exist in database<br>";
    }
    
    // Enroll students in courses
    $courses = $pdo->query("SELECT id, course_code FROM courses WHERE is_active = 1")->fetchAll();
    $studentRecords = $pdo->query("SELECT id, user_id FROM students WHERE status = 'active'")->fetchAll();
    
    if (!empty($studentRecords) && !empty($courses)) {
        echo "<h3>Enrolling Students in Courses</h3>";
        
        foreach ($studentRecords as $student) {
            // Enroll each student in 2-4 random courses
            $enrollmentCount = rand(2, min(4, count($courses)));
            $selectedCourses = array_rand($courses, $enrollmentCount);
            
            if (!is_array($selectedCourses)) {
                $selectedCourses = [$selectedCourses];
            }
            
            foreach ($selectedCourses as $courseIndex) {
                $course = $courses[$courseIndex];
                
                // Check if already enrolled
                $existing = $pdo->prepare("SELECT COUNT(*) as count FROM enrollments WHERE student_id = ? AND course_id = ?")
                                ->execute([$student['id'], $course['id']])
                                ->fetch()['count'];
                
                if ($existing == 0) {
                    $pdo->prepare("INSERT INTO enrollments (student_id, course_id, status) VALUES (?, ?, 'enrolled')")
                            ->execute([$student['id'], $course['id']]);
                    
                    echo "✓ Enrolled student in {$course['course_code']}<br>";
                }
            }
        }
    }
    
    // Create sample financial records
    echo "<h3>Creating Sample Financial Records</h3>";
    
    $feeTypes = ['tuition', 'lab', 'library', 'sports'];
    
    foreach ($studentRecords as $student) {
        foreach ($feeTypes as $feeType) {
            // Check if record already exists
            $existing = $pdo->prepare("SELECT COUNT(*) as count FROM financial_records WHERE student_id = ? AND fee_type = ?")
                            ->execute([$student['id'], $feeType])
                            ->fetch()['count'];
            
            if ($existing == 0) {
                $amount = match($feeType) {
                    'tuition' => rand(2000, 5000),
                    'lab' => rand(200, 800),
                    'library' => rand(100, 300),
                    'sports' => rand(50, 200)
                };
                
                $dueDate = date('Y-m-d', strtotime('+30 days'));
                $createdBy = $teachers[0]['id'] ?? 1;
                
                $pdo->prepare("INSERT INTO financial_records (student_id, fee_type, amount, due_date, created_by) VALUES (?, ?, ?, ?, ?)")
                        ->execute([$student['id'], $feeType, $amount, $dueDate, $createdBy]);
                
                echo "✓ Created {$feeType} fee record for student<br>";
            }
        }
    }
    
    // Create some sample grades
    echo "<h3>Creating Sample Grades</h3>";
    
    $assessmentTypes = ['quiz', 'assignment', 'midterm', 'final'];
    $assessmentNames = ['Quiz 1', 'Assignment 1', 'Midterm Exam', 'Final Exam'];
    
    $enrollments = $pdo->query("
        SELECT e.id, e.student_id, c.course_code 
        FROM enrollments e 
        JOIN courses c ON e.course_id = c.id 
        WHERE e.status = 'enrolled'
    ")->fetchAll();
    
    foreach ($enrollments as $enrollment) {
        foreach ($assessmentTypes as $index => $assessmentType) {
            // Random chance to create grade (70% probability)
            if (rand(1, 100) <= 70) {
                $score = rand(65, 95);
                $maxScore = 100;
                $gradedBy = $teachers[0]['id'] ?? 1;
                
                $pdo->prepare("
                    INSERT INTO grades (enrollment_id, assessment_type, assessment_name, score, max_score, graded_by) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ")->execute([
                    $enrollment['id'], 
                    $assessmentType, 
                    $assessmentNames[$index], 
                    $score, 
                    $maxScore, 
                    $gradedBy
                ]);
                
                echo "✓ Created grade for {$enrollment['course_code']}<br>";
            }
        }
    }
    
    echo "<h3 style='color: #10B981;'>🎉 Sample data setup completed!</h3>";
    echo "<p><strong>What's now available:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Sample courses assigned to teachers</li>";
    echo "<li>✅ Students enrolled in courses</li>";
    echo "<li>✅ Financial records for all students</li>";
    echo "<li>✅ Sample grades and assessments</li>";
    echo "</ul>";
    
    echo "<p style='margin-top: 20px;'><strong>Next steps:</strong></p>";
    echo "<ol>";
    echo "<li>Login as a teacher - you should see courses in the dropdown</li>";
    echo "<li>Try the QR Scanner feature</li>";
    echo "<li>Login as a student - check the enhanced portal</li>";
    echo "<li>Login as admin - explore financial ledger and class assignment</li>";
    echo "</ol>";
    
    echo "<p style='margin-top: 20px;'><a href='login.php' style='background: #4F46E5; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login →</a></p>";
    
} catch (PDOException $e) {
    echo "<h3 style='color: #EF4444;'>❌ Setup failed</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>
