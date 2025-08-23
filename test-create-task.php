<?php
// Test script to debug task creation
session_start();

// Set test session
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['user_name'] = 'Test Admin';

require_once 'includes/db.php';

echo "<h2>Task Creation Test</h2>\n";

try {
    // Test database connection
    echo "<p>✓ Database connection: OK</p>\n";
    
    // Check if users exist
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE is_active = TRUE LIMIT 5");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    echo "<p>✓ Found " . count($users) . " active users:</p>\n";
    foreach ($users as $user) {
        echo "<p>  - User {$user['id']}: {$user['name']} ({$user['email']})</p>\n";
    }
    
    // Test task creation
    $testData = [
        'title' => 'Test Task - ' . date('Y-m-d H:i:s'),
        'details' => 'This is a test task created by the debug script',
        'assigned_to' => $users[0]['id'], // Assign to first user
        'date' => date('Y-m-d'),
        'priority' => 'medium',
        'estimated_hours' => 2.0
    ];
    
    echo "<h3>Testing Task Creation</h3>\n";
    echo "<p>Test data:</p>\n";
    echo "<pre>" . print_r($testData, true) . "</pre>\n";
    
    // Use the same logic as tasks-simple.php
    $sql = "
        INSERT INTO tasks (title, details, assigned_to, date, created_by, updated_by, priority, estimated_hours, due_time, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
    ";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        trim($testData['title']),
        trim($testData['details']),
        (int)$testData['assigned_to'],
        $testData['date'],
        $_SESSION['user_id'],
        $_SESSION['user_id'],
        $testData['priority'],
        $testData['estimated_hours'],
        null
    ]);
    
    if ($result) {
        $taskId = $pdo->lastInsertId();
        echo "<p>✓ Task created successfully! Task ID: {$taskId}</p>\n";
        
        // Verify task was created
        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();
        
        echo "<p>✓ Task verification:</p>\n";
        echo "<pre>" . print_r($task, true) . "</pre>\n";
        
    } else {
        echo "<p>✗ Failed to create task</p>\n";
        echo "<p>Error info:</p>\n";
        echo "<pre>" . print_r($stmt->errorInfo(), true) . "</pre>\n";
    }
    
} catch (Exception $e) {
    echo "<p>✗ Error: " . $e->getMessage() . "</p>\n";
    echo "<p>File: " . $e->getFile() . "</p>\n";
    echo "<p>Line: " . $e->getLine() . "</p>\n";
}

echo "<hr><p><a href='admin-dashboard.php'>Back to Admin Dashboard</a></p>\n";
?>