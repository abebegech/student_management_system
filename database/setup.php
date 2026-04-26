<?php
/**
 * Database Setup Script for Pro-Level Student Management System
 * This script creates all necessary tables and inserts initial data
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$host = 'localhost';
$dbname = 'student_system';
$username = 'root';
$password = '';

try {
    // Connect to MySQL without specifying database first
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database '$dbname' created or already exists.<br>";
    
    // Connect to the specific database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Read and execute the schema file
    $schemaFile = __DIR__ . '/schema.sql';
    if (file_exists($schemaFile)) {
        $schema = file_get_contents($schemaFile);
        
        // Split the schema into individual statements
        $statements = array_filter(array_map('trim', explode(';', $schema)));
        
        foreach ($statements as $statement) {
            if (!empty($statement) && !preg_match('/^--/', $statement)) {
                try {
                    $pdo->exec($statement);
                    echo "✓ Executed: " . substr($statement, 0, 50) . "...<br>";
                } catch (PDOException $e) {
                    echo "✗ Error in statement: " . $e->getMessage() . "<br>";
                    echo "Statement: " . $statement . "<br><br>";
                }
            }
        }
        
        echo "<br><strong>Database setup completed successfully!</strong><br>";
        echo "Default admin user created:<br>";
        echo "Username: admin<br>";
        echo "Password: password<br>";
        
    } else {
        echo "Error: Schema file not found at $schemaFile";
    }
    
} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage();
}
?>
