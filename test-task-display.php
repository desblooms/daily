<?php
// Test task display functionality
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set test session (replace with actual admin/user IDs)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Replace with actual user ID
    $_SESSION['role'] = 'admin'; // or 'user'
}

require_once 'includes/db.php';
require_once 'includes/functions.php';

echo "<h2>Task Display Debug Test</h2>";
echo "<p><strong>Current User:</strong> ID={$_SESSION['user_id']}, Role={$_SESSION['role']}</p>";

// Test 1: Check if tasks table exists and has data
echo "<h3>1. Database Structure Test</h3>";
try {
    $stmt = $pdo->query("DESCRIBE tasks");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✅ Tasks table exists with columns: " . implode(', ', array_column($columns, 'Field')) . "<br>";
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tasks");
    $count = $stmt->fetchColumn();
    echo "📊 Total tasks in database: {$count}<br>";
    
    if ($count > 0) {
        $stmt = $pdo->query("SELECT COUNT(*) as today FROM tasks WHERE date = CURDATE()");
        $todayCount = $stmt->fetchColumn();
        echo "📅 Tasks for today: {$todayCount}<br>";
        
        $stmt = $pdo->query("SELECT DISTINCT date FROM tasks ORDER BY date DESC LIMIT 5");
        $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "📆 Recent task dates: " . implode(', ', $dates) . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

// Test 2: Test getTasks function from functions.php
echo "<h3>2. getTasks Function Test</h3>";
try {
    $todayTasks = getTasks($_SESSION['user_id'], date('Y-m-d'));
    echo "📋 Today's tasks for user {$_SESSION['user_id']}: " . count($todayTasks) . " tasks<br>";
    
    if (!empty($todayTasks)) {
        echo "<ul>";
        foreach ($todayTasks as $task) {
            echo "<li>#{$task['id']}: {$task['title']} - Status: {$task['status']}</li>";
        }
        echo "</ul>";
    } else {
        echo "ℹ️ No tasks found for today<br>";
    }
    
    // Test all tasks for this user
    $allTasks = getTasks($_SESSION['user_id']);
    echo "📋 All tasks for user {$_SESSION['user_id']}: " . count($allTasks) . " tasks<br>";
    
} catch (Exception $e) {
    echo "❌ getTasks function error: " . $e->getMessage() . "<br>";
}

// Test 3: Test API endpoint
echo "<h3>3. API Endpoint Test</h3>";
try {
    $testDate = date('Y-m-d');
    $apiUrl = 'api/tasks.php?action=get_tasks&date=' . $testDate;
    
    echo "<p>Testing API: <a href='{$apiUrl}' target='_blank'>{$apiUrl}</a></p>";
    
    // Simulate API call
    $_GET['action'] = 'get_tasks';
    $_GET['date'] = $testDate;
    
    ob_start();
    include 'api/tasks.php';
    $apiResponse = ob_get_clean();
    
    echo "<p><strong>API Response:</strong></p>";
    echo "<pre>" . htmlspecialchars($apiResponse) . "</pre>";
    
    $data = json_decode($apiResponse, true);
    if ($data && isset($data['success'])) {
        if ($data['success']) {
            $taskCount = isset($data['tasks']) ? count($data['tasks']) : 0;
            echo "✅ API returned {$taskCount} tasks<br>";
        } else {
            echo "❌ API error: " . ($data['message'] ?? 'Unknown error') . "<br>";
        }
    } else {
        echo "❌ Invalid API response format<br>";
    }
    
} catch (Exception $e) {
    echo "❌ API test error: " . $e->getMessage() . "<br>";
}

// Test 4: Check user stats
echo "<h3>4. User Stats Test</h3>";
try {
    $stats = getUserStats($_SESSION['user_id']);
    echo "📊 User stats: ";
    echo "Total: {$stats['total']}, ";
    echo "Pending: {$stats['pending']}, ";
    echo "In Progress: {$stats['in_progress']}, ";
    echo "Done: {$stats['done']}<br>";
} catch (Exception $e) {
    echo "❌ getUserStats error: " . $e->getMessage() . "<br>";
}

// Test 5: Sample task queries
echo "<h3>5. Sample Task Queries</h3>";
try {
    // Check if user has any tasks assigned
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userTaskCount = $stmt->fetchColumn();
    echo "👤 Tasks assigned to current user: {$userTaskCount}<br>";
    
    // Check recent tasks
    $stmt = $pdo->prepare("
        SELECT id, title, date, status 
        FROM tasks 
        WHERE assigned_to = ? 
        ORDER BY date DESC 
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recentTasks = $stmt->fetchAll();
    
    echo "🕒 Recent tasks for user:<br>";
    foreach ($recentTasks as $task) {
        echo "- {$task['date']}: {$task['title']} ({$task['status']})<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Sample query error: " . $e->getMessage() . "<br>";
}

echo "<br><p><strong>Debug completed.</strong> If tasks are not showing, check the issues identified above.</p>";
?>