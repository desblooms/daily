<?php
// Comprehensive Task Display Diagnostic Tool
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Task Display Diagnostic Tool</h1>";

// Check session first
if (!isset($_SESSION['user_id'])) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24; margin: 10px 0;'>";
    echo "❌ <strong>SESSION ISSUE:</strong> No user logged in. Please login first.<br>";
    echo "For testing, you can manually set session: \$_SESSION['user_id'] = 1; \$_SESSION['role'] = 'admin';";
    echo "</div>";
    
    // Set test session for diagnostic purposes
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
    echo "<p>🧪 <strong>Test session set:</strong> User ID = 1, Role = admin</p>";
}

require_once 'includes/db.php';
require_once 'includes/functions.php';

echo "<p><strong>Current Session:</strong> User ID = {$_SESSION['user_id']}, Role = {$_SESSION['role']}</p>";

// 1. Database Structure Check
echo "<h2>1. 📊 Database Structure Check</h2>";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'tasks'");
    if ($stmt->rowCount() == 0) {
        echo "<span style='color: red;'>❌ CRITICAL: 'tasks' table does not exist!</span><br>";
    } else {
        echo "✅ Tasks table exists<br>";
        
        // Check table structure
        $stmt = $pdo->query("DESCRIBE tasks");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $requiredColumns = ['id', 'title', 'date', 'assigned_to', 'status'];
        
        foreach ($requiredColumns as $col) {
            if (in_array($col, $columns)) {
                echo "✅ Column '{$col}' exists<br>";
            } else {
                echo "<span style='color: red;'>❌ MISSING: Column '{$col}' not found</span><br>";
            }
        }
    }
} catch (Exception $e) {
    echo "<span style='color: red;'>❌ Database error: {$e->getMessage()}</span><br>";
}

// 2. Data Availability Check
echo "<h2>2. 📋 Data Availability Check</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks");
    $totalTasks = $stmt->fetchColumn();
    echo "📊 Total tasks in database: <strong>{$totalTasks}</strong><br>";
    
    if ($totalTasks > 0) {
        // Check tasks by date
        $stmt = $pdo->query("SELECT date, COUNT(*) as count FROM tasks GROUP BY date ORDER BY date DESC LIMIT 7");
        $tasksByDate = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Tasks by Date (Last 7 days with tasks):</h3>";
        foreach ($tasksByDate as $row) {
            echo "📅 {$row['date']}: {$row['count']} tasks<br>";
        }
        
        // Check today's tasks
        $todayDate = date('Y-m-d');
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE date = ?");
        $stmt->execute([$todayDate]);
        $todayTasks = $stmt->fetchColumn();
        echo "<strong>📅 Today ({$todayDate}): {$todayTasks} tasks</strong><br>";
        
        // Check tasks assigned to current user
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userTasks = $stmt->fetchColumn();
        echo "👤 Tasks assigned to current user: <strong>{$userTasks}</strong><br>";
        
        // Check today's tasks for current user
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND date = ?");
        $stmt->execute([$_SESSION['user_id'], $todayDate]);
        $userTodayTasks = $stmt->fetchColumn();
        echo "🎯 Today's tasks for current user: <strong>{$userTodayTasks}</strong><br>";
        
        if ($userTodayTasks == 0) {
            echo "<div style='background: #fff3cd; padding: 10px; border-radius: 5px; color: #856404; margin: 10px 0;'>";
            echo "⚠️ <strong>POTENTIAL ISSUE:</strong> No tasks assigned to current user for today. This could explain why tasks aren't showing.";
            echo "</div>";
        }
    } else {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24; margin: 10px 0;'>";
        echo "❌ <strong>CRITICAL ISSUE:</strong> No tasks exist in the database!<br>";
        echo "This is why no tasks are showing. You need to create some tasks first.";
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<span style='color: red;'>❌ Data check error: {$e->getMessage()}</span><br>";
}

// 3. Function Test
echo "<h2>3. 🔧 Function Test</h2>";
try {
    if (function_exists('getTasks')) {
        echo "✅ getTasks function exists<br>";
        
        // Test with today's date
        $todayTasks = getTasks($_SESSION['user_id'], date('Y-m-d'));
        echo "📋 getTasks() returned: " . count($todayTasks) . " tasks for today<br>";
        
        if (!empty($todayTasks)) {
            echo "<h4>Today's Tasks Found:</h4>";
            foreach ($todayTasks as $task) {
                echo "- #{$task['id']}: {$task['title']} (Status: {$task['status']})<br>";
            }
        }
        
        // Test without date (all tasks)
        $allTasks = getTasks($_SESSION['user_id']);
        echo "📋 getTasks() returned: " . count($allTasks) . " total tasks for user<br>";
    } else {
        echo "<span style='color: red;'>❌ getTasks function does not exist</span><br>";
    }
    
    if (function_exists('getUserStats')) {
        echo "✅ getUserStats function exists<br>";
        $stats = getUserStats($_SESSION['user_id']);
        echo "📊 User stats: Total: {$stats['total']}, Pending: {$stats['pending']}, Done: {$stats['done']}<br>";
    } else {
        echo "<span style='color: red;'>❌ getUserStats function does not exist</span><br>";
    }
} catch (Exception $e) {
    echo "<span style='color: red;'>❌ Function test error: {$e->getMessage()}</span><br>";
}

// 4. API Test
echo "<h2>4. 🌐 API Test</h2>";
try {
    // Test the tasks API
    $testDate = date('Y-m-d');
    
    // Backup current GET parameters
    $originalGet = $_GET;
    
    // Set up test parameters
    $_GET = [
        'action' => 'get_tasks',
        'date' => $testDate
    ];
    
    ob_start();
    include 'api/tasks.php';
    $apiResponse = ob_get_clean();
    
    // Restore original GET
    $_GET = $originalGet;
    
    echo "🌐 API Response for today ({$testDate}):<br>";
    echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 5px; max-height: 200px; overflow-y: auto;'>";
    echo htmlspecialchars($apiResponse);
    echo "</pre>";
    
    $data = json_decode($apiResponse, true);
    if ($data && isset($data['success'])) {
        if ($data['success']) {
            $taskCount = isset($data['tasks']) ? count($data['tasks']) : 0;
            echo "✅ API successfully returned {$taskCount} tasks<br>";
        } else {
            echo "<span style='color: red;'>❌ API returned error: " . ($data['message'] ?? 'Unknown') . "</span><br>";
        }
    } else {
        echo "<span style='color: red;'>❌ API returned invalid JSON response</span><br>";
    }
    
} catch (Exception $e) {
    echo "<span style='color: red;'>❌ API test error: {$e->getMessage()}</span><br>";
}

// 5. Sample Data Creation
if ($totalTasks == 0) {
    echo "<h2>5. 🔧 Create Sample Task</h2>";
    echo "<p>Since no tasks exist, let's create a sample task for testing:</p>";
    
    try {
        $sampleTask = [
            'title' => 'Sample Task for Testing',
            'details' => 'This is a test task to verify task display functionality',
            'date' => date('Y-m-d'),
            'assigned_to' => $_SESSION['user_id'],
            'created_by' => $_SESSION['user_id'],
            'status' => 'Pending',
            'priority' => 'medium'
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO tasks (title, details, date, assigned_to, created_by, status, priority) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $sampleTask['title'],
            $sampleTask['details'],
            $sampleTask['date'],
            $sampleTask['assigned_to'],
            $sampleTask['created_by'],
            $sampleTask['status'],
            $sampleTask['priority']
        ]);
        
        if ($result) {
            $newTaskId = $pdo->lastInsertId();
            echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; color: #155724; margin: 10px 0;'>";
            echo "✅ <strong>Sample task created successfully!</strong><br>";
            echo "Task ID: {$newTaskId}<br>";
            echo "Title: {$sampleTask['title']}<br>";
            echo "Date: {$sampleTask['date']}<br>";
            echo "Now try refreshing your main page to see if tasks appear.";
            echo "</div>";
        }
    } catch (Exception $e) {
        echo "<span style='color: red;'>❌ Failed to create sample task: {$e->getMessage()}</span><br>";
    }
}

// 6. Common Issues Summary
echo "<h2>6. 📋 Common Issues Summary</h2>";
echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 5px; border-left: 4px solid #2196F3;'>";
echo "<strong>Most Common Reasons Tasks Don't Show:</strong><br>";
echo "1. No tasks created in database<br>";
echo "2. Tasks not assigned to current user<br>";
echo "3. Tasks assigned to different dates<br>";
echo "4. User not logged in properly<br>";
echo "5. Database connection issues<br>";
echo "6. Function errors in includes/functions.php<br>";
echo "</div>";

echo "<h2>🔧 Next Steps</h2>";
echo "<ol>";
echo "<li>Check the diagnostic results above</li>";
echo "<li>If no tasks exist, create some tasks</li>";
echo "<li>If tasks exist but aren't assigned to you, check task assignments</li>";
echo "<li>Test with: <a href='test-frontend-tasks.html'>Frontend Task Test</a></li>";
echo "<li>Test the main page: <a href='index.php'>Daily Calendar Home</a></li>";
echo "</ol>";
?>