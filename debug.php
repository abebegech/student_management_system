<?php
/**
 * Debug script to check database and authentication
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 Debug Login Issue</h2>";

// Test database connection
try {
    require_once 'includes/Database.php';
    $db = Database::getInstance();
    echo "✓ Database connection successful<br>";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
    exit;
}

// Check if users table exists and has data
try {
    $users = $db->fetchAll("SELECT * FROM users");
    echo "✓ Found " . count($users) . " users in database<br>";
    
    foreach ($users as $user) {
        echo "<br><strong>User ID:</strong> " . $user['id'] . "<br>";
        echo "<strong>Username:</strong> " . htmlspecialchars($user['username']) . "<br>";
        echo "<strong>Email:</strong> " . htmlspecialchars($user['email']) . "<br>";
        echo "<strong>Role:</strong> " . htmlspecialchars($user['role']) . "<br>";
        echo "<strong>Active:</strong> " . ($user['is_active'] ? 'Yes' : 'No') . "<br>";
        echo "<strong>Password Hash:</strong> " . substr($user['password_hash'], 0, 20) . "...<br>";
        
        // Test password verification
        if (password_verify('password', $user['password_hash'])) {
            echo "✓ Password 'password' verifies correctly<br>";
        } else {
            echo "❌ Password 'password' does NOT verify<br>";
        }
        
        echo "<hr>";
    }
} catch (Exception $e) {
    echo "❌ Error querying users: " . $e->getMessage() . "<br>";
}

// Test authentication directly
echo "<h3>🧪 Test Authentication</h3>";
try {
    require_once 'includes/Auth.php';
    $auth = new Auth();
    
    $testResult = $auth->login('admin', 'password');
    echo "Auth login result: " . ($testResult ? 'SUCCESS' : 'FAILED') . "<br>";
    
    if ($testResult) {
        echo "✓ Session data:<br>";
        echo "User ID: " . $_SESSION['user_id'] . "<br>";
        echo "Username: " . $_SESSION['username'] . "<br>";
        echo "Role: " . $_SESSION['role'] . "<br>";
        echo "Logged in: " . ($_SESSION['logged_in'] ? 'Yes' : 'No') . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Authentication test failed: " . $e->getMessage() . "<br>";
}

// Test the exact SQL query from Auth.php
echo "<h3>🔎 Test SQL Query</h3>";
try {
    $sql = "SELECT id, username, email, password_hash, role, first_name, last_name, is_active 
            FROM users 
            WHERE (username = :username OR email = :email) AND is_active = 1";
    
    $user = $db->fetch($sql, ['username' => 'admin', 'email' => 'admin']);
    
    if ($user) {
        echo "✓ SQL query found user:<br>";
        echo "ID: " . $user['id'] . "<br>";
        echo "Username: " . htmlspecialchars($user['username']) . "<br>";
        echo "Active: " . ($user['is_active'] ? 'Yes' : 'No') . "<br>";
        
        if (password_verify('password', $user['password_hash'])) {
            echo "✓ Password verification successful<br>";
        } else {
            echo "❌ Password verification failed<br>";
        }
    } else {
        echo "❌ SQL query found no user<br>";
    }
    
} catch (Exception $e) {
    echo "❌ SQL query failed: " . $e->getMessage() . "<br>";
    echo "SQL: " . $sql . "<br>";
    echo "Params: " . json_encode(['username' => 'admin', 'email' => 'admin']) . "<br>";
}

echo "<br><a href='login.php'>← Back to Login</a>";
?>
