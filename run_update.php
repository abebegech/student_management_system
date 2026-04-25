<?php
/**
 * Database Update Script for Advanced Features
 * Run this in your browser: http://localhost/student-system/run_update.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🚀 Updating Database for Advanced Features</h2>";

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
    
    // Read and execute the update script
    $updateScript = file_get_contents('database/update_schema.sql');
    
    // Split by semicolons and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $updateScript)));
    
    foreach ($statements as $statement) {
        if (!empty($statement) && !preg_match('/^--/', $statement)) {
            try {
                $pdo->exec($statement);
                echo "✓ Executed: " . substr($statement, 0, 50) . "...<br>";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'already exists') === false && 
                    strpos($e->getMessage(), 'Duplicate column') === false) {
                    echo "❌ Error: " . $e->getMessage() . "<br>";
                } else {
                    echo "ℹ️ " . substr($statement, 0, 50) . "... (already exists)<br>";
                }
            }
        }
    }
    
    echo "<h3 style='color: #10B981;'>🎉 Database update completed successfully!</h3>";
    echo "<p><strong>Advanced features are now ready:</strong></p>";
    echo "<ul>";
    echo "<li>✅ QR Attendance System with unique QR codes</li>";
    echo "<li>✅ Financial Ledger with PDF receipt generation</li>";
    echo "<li>✅ Class Assignment with conflict checking</li>";
    echo "<li>✅ Enhanced Student/Parent Portal</li>";
    echo "</ul>";
    
    echo "<p style='margin-top: 20px;'><a href='login.php' style='background: #4F46E5; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login →</a></p>";
    
} catch (PDOException $e) {
    echo "<h3 style='color: #EF4444;'>❌ Database update failed</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check:</p>";
    echo "<ul>";
    echo "<li>MySQL server is running</li>";
    echo "<li>Database credentials are correct</li>";
    echo "<li>Database exists</li>";
    echo "</ul>";
}
?>
