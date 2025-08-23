<?php
echo "<h1>Component Test</h1>";

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h3>1. Testing Database Connection...</h3>";
try {
    require_once 'includes/db.php';
    if (isset($pdo) && $pdo) {
        echo "<p style='color: green;'>✅ Database connection successful</p>";
        // Test a simple query
        $stmt = $pdo->query("SELECT 1 as test");
        $result = $stmt->fetch();
        echo "<p style='color: green;'>✅ Database query test: " . $result['test'] . "</p>";
    } else {
        echo "<p style='color: red;'>❌ Database connection failed - PDO not set</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h3>2. Testing Auth...</h3>";
try {
    require_once 'includes/auth.php';
    echo "<p style='color: green;'>✅ Auth file loaded</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Auth error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h3>3. Testing Functions...</h3>";
try {
    require_once 'includes/functions.php';
    echo "<p style='color: green;'>✅ Functions file loaded</p>";
    
    if (function_exists('logActivity')) {
        echo "<p style='color: green;'>✅ logActivity function exists</p>";
    } else {
        echo "<p style='color: red;'>❌ logActivity function missing</p>";
    }
    
    if (function_exists('createNotification')) {
        echo "<p style='color: green;'>✅ createNotification function exists</p>";
    } else {
        echo "<p style='color: red;'>❌ createNotification function missing</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Functions error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h3>4. Testing Session...</h3>";
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['user_name'] = 'Test Admin';

echo "<p>Session variables set:</p>";
echo "<ul>";
echo "<li>user_id: " . ($_SESSION['user_id'] ?? 'not set') . "</li>";
echo "<li>role: " . ($_SESSION['role'] ?? 'not set') . "</li>";
echo "<li>user_name: " . ($_SESSION['user_name'] ?? 'not set') . "</li>";
echo "</ul>";

echo "<h3>5. Testing Sample Task Query...</h3>";
try {
    if (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT * FROM tasks LIMIT 1");
        $stmt->execute();
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($task) {
            echo "<p style='color: green;'>✅ Sample task found:</p>";
            echo "<pre>" . json_encode($task, JSON_PRETTY_PRINT) . "</pre>";
        } else {
            echo "<p style='color: orange;'>⚠️ No tasks found in database</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Task query error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h3>6. Testing Sample User Query...</h3>";
try {
    if (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE is_active = TRUE LIMIT 3");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($users) {
            echo "<p style='color: green;'>✅ Sample users found:</p>";
            foreach ($users as $user) {
                echo "<p>ID: {$user['id']}, Name: {$user['name']}, Role: {$user['role']}</p>";
            }
        } else {
            echo "<p style='color: orange;'>⚠️ No active users found in database</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ User query error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><a href='test-api-direct.php'>Test API Direct</a></p>";
echo "<p><a href='check-syntax.php'>Check Syntax</a></p>";
?>