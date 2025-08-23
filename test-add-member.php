<?php
// Test file to debug Add Member functionality
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set test admin session for testing
$_SESSION['user_id'] = 1;  // Assuming admin user ID is 1
$_SESSION['role'] = 'admin';

echo "<h2>Add Member Debug Test</h2>";

// Test 1: Database Connection
echo "<h3>1. Testing Database Connection</h3>";
try {
    require_once 'includes/db.php';
    echo "✅ Database connection: SUCCESS<br>";
    echo "PDO object: " . (isset($pdo) ? "EXISTS" : "MISSING") . "<br>";
} catch (Exception $e) {
    echo "❌ Database connection: FAILED - " . $e->getMessage() . "<br>";
}

// Test 2: Required Files
echo "<h3>2. Testing Required Files</h3>";
$files = [
    'includes/auth.php',
    'includes/functions.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✅ {$file}: EXISTS<br>";
        try {
            require_once $file;
        } catch (Exception $e) {
            echo "❌ {$file}: LOAD ERROR - " . $e->getMessage() . "<br>";
        }
    } else {
        echo "❌ {$file}: NOT FOUND<br>";
    }
}

// Test 3: Test User Creation API Call
echo "<h3>3. Testing User Creation API</h3>";

$testData = [
    'action' => 'create_user',
    'name' => 'Test User',
    'email' => 'test' . time() . '@example.com',
    'password' => 'testpassword123',
    'role' => 'user',
    'department' => 'IT'
];

// Simulate the API call
try {
    $_POST = $testData;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    
    // Capture output
    ob_start();
    include 'api/users.php';
    $output = ob_get_clean();
    
    echo "API Response: <pre>" . htmlspecialchars($output) . "</pre>";
    
    // Try to decode JSON
    $response = json_decode($output, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "JSON Parse: ✅ SUCCESS<br>";
        echo "Success: " . ($response['success'] ? 'YES' : 'NO') . "<br>";
        echo "Message: " . ($response['message'] ?? 'N/A') . "<br>";
    } else {
        echo "❌ JSON Parse Error: " . json_last_error_msg() . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ API Test Error: " . $e->getMessage() . "<br>";
}

// Test 4: Check Database Tables
echo "<h3>4. Testing Database Tables</h3>";
if (isset($pdo)) {
    try {
        // Check if users table exists and has correct structure
        $stmt = $pdo->query("DESCRIBE users");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Users table structure:<br>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>{$column['Field']}</td>";
            echo "<td>{$column['Type']}</td>";
            echo "<td>{$column['Null']}</td>";
            echo "<td>{$column['Key']}</td>";
            echo "<td>{$column['Default']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Count existing users
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1");
        $userCount = $stmt->fetchColumn();
        echo "<br>Active users count: {$userCount}<br>";
        
    } catch (Exception $e) {
        echo "❌ Database table check error: " . $e->getMessage() . "<br>";
    }
}

// Test 5: Check Session
echo "<h3>5. Testing Session</h3>";
echo "Session ID: " . session_id() . "<br>";
echo "User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "<br>";
echo "Role: " . ($_SESSION['role'] ?? 'NOT SET') . "<br>";

// Test 6: Check logActivity function
echo "<h3>6. Testing logActivity Function</h3>";
if (function_exists('logActivity')) {
    echo "✅ logActivity function: EXISTS<br>";
} else {
    echo "❌ logActivity function: NOT FOUND<br>";
}

echo "<p><strong>Test completed. Check the results above to identify issues.</strong></p>";
?>