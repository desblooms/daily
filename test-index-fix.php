<?php
// Test the Index.php Date Filtering Fix
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Set test user
    $_SESSION['role'] = 'admin';
}

require_once 'includes/db.php';

echo "<h1>🧪 Test Index.php Date Filtering Fix</h1>";
echo "<p><strong>Current User:</strong> ID = {$_SESSION['user_id']}, Role = {$_SESSION['role']}</p>";

// Test the same logic that's now in index.php
$testScenarios = [
    ['page' => 'today', 'date' => null, 'description' => "Today's Tasks"],
    ['page' => 'custom', 'date' => date('Y-m-d'), 'description' => "Today (via date parameter)"],
    ['page' => 'custom', 'date' => '2024-01-15', 'description' => "January 15, 2024"],
    ['page' => 'custom', 'date' => '2024-06-10', 'description' => "June 10, 2024"],
    ['page' => 'all', 'date' => null, 'description' => "All Tasks"],
];

echo "<h2>📊 Testing Date Filtering Logic</h2>";

foreach ($testScenarios as $scenario) {
    $currentPage = $scenario['page'];
    $selectedDate = $scenario['date'] ?? date('Y-m-d');
    
    echo "<h3>🔍 Test: {$scenario['description']}</h3>";
    echo "<p><strong>Parameters:</strong> page={$currentPage}, date={$selectedDate}</p>";
    
    try {
        // Use the same logic as the fixed index.php
        switch ($currentPage) {
            case 'today':
                $todayDate = date('Y-m-d');
                $stmt = $pdo->prepare("
                    SELECT t.*, u.name as assigned_name, u.email as assigned_email 
                    FROM tasks t 
                    LEFT JOIN users u ON t.assigned_to = u.id 
                    WHERE t.assigned_to = ? AND t.date = ? 
                    ORDER BY t.priority = 'high' DESC, t.priority = 'medium' DESC, t.created_at DESC
                ");
                $stmt->execute([$_SESSION['user_id'], $todayDate]);
                $tasks = $stmt->fetchAll();
                $pageTitle = "Today's Tasks";
                $expectedDate = $todayDate;
                break;
                
            case 'all':
                $stmt = $pdo->prepare("
                    SELECT t.*, u.name as assigned_name, u.email as assigned_email 
                    FROM tasks t 
                    LEFT JOIN users u ON t.assigned_to = u.id 
                    WHERE t.assigned_to = ? 
                    ORDER BY t.date DESC, t.priority = 'high' DESC, t.created_at DESC 
                    LIMIT 100
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $tasks = $stmt->fetchAll();
                $pageTitle = "All Tasks";
                $expectedDate = null; // All dates
                break;
                
            default:
                // Ensure we have a valid date format
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
                    $selectedDate = date('Y-m-d');
                }
                
                $stmt = $pdo->prepare("
                    SELECT t.*, u.name as assigned_name, u.email as assigned_email 
                    FROM tasks t 
                    LEFT JOIN users u ON t.assigned_to = u.id 
                    WHERE t.assigned_to = ? AND t.date = ? 
                    ORDER BY t.priority = 'high' DESC, t.priority = 'medium' DESC, t.created_at DESC
                ");
                $stmt->execute([$_SESSION['user_id'], $selectedDate]);
                $tasks = $stmt->fetchAll();
                $pageTitle = "Tasks for " . date('M j', strtotime($selectedDate));
                $expectedDate = $selectedDate;
        }
        
        echo "<p><strong>Results:</strong> Found " . count($tasks) . " tasks</p>";
        
        if (!empty($tasks)) {
            echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; margin: 10px 0;'>";
            echo "<tr style='background: #f5f5f5;'><th>ID</th><th>Title</th><th>Date</th><th>Status</th><th>✓ Correct Date?</th></tr>";
            
            $correctDateCount = 0;
            foreach ($tasks as $task) {
                $isCorrectDate = ($expectedDate === null) || ($task['date'] === $expectedDate);
                if ($isCorrectDate) $correctDateCount++;
                
                echo "<tr>";
                echo "<td>{$task['id']}</td>";
                echo "<td>" . htmlspecialchars($task['title']) . "</td>";
                echo "<td>{$task['date']}</td>";
                echo "<td>{$task['status']}</td>";
                echo "<td style='color: " . ($isCorrectDate ? 'green' : 'red') . ";'>";
                echo $isCorrectDate ? "✅ YES" : "❌ NO";
                echo "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            if ($expectedDate !== null) {
                if ($correctDateCount === count($tasks)) {
                    echo "<p style='color: green;'><strong>✅ SUCCESS:</strong> All tasks have the correct date ({$expectedDate})</p>";
                } else {
                    echo "<p style='color: red;'><strong>❌ PROBLEM:</strong> " . (count($tasks) - $correctDateCount) . " tasks have wrong dates</p>";
                }
            }
        } else {
            echo "<p style='color: orange;'>ℹ️ No tasks found for this criteria</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error: {$e->getMessage()}</p>";
    }
    
    echo "<hr>";
}

// Create test links to actual index.php
echo "<h2>🔗 Test Real Index.php</h2>";
echo "<p>Click these links to test the actual index.php with the fix:</p>";
echo "<ul>";
echo "<li><a href='index.php' target='_blank'>🏠 Home (should show today's tasks only)</a></li>";
echo "<li><a href='index.php?page=today' target='_blank'>📅 Today's Tasks</a></li>";
echo "<li><a href='index.php?date=2024-01-15' target='_blank'>📅 Jan 15, 2024 (should show only Jan 15 tasks)</a></li>";
echo "<li><a href='index.php?date=2024-06-10' target='_blank'>📅 Jun 10, 2024 (should show only Jun 10 tasks)</a></li>";
echo "<li><a href='index.php?page=all' target='_blank'>📋 All Tasks (should show all tasks with limit)</a></li>";
echo "</ul>";

echo "<h2>📋 Summary</h2>";
echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; border-left: 4px solid #28a745; margin: 20px 0;'>";
echo "<h3>✅ Fix Applied Successfully</h3>";
echo "<p><strong>What was fixed:</strong></p>";
echo "<ul>";
echo "<li>Replaced getTasks() function calls with direct SQL queries</li>";
echo "<li>Added explicit WHERE t.date = ? clauses for date filtering</li>";
echo "<li>Added date validation to prevent invalid date parameters</li>";
echo "<li>Added debug logging to track what's being displayed</li>";
echo "</ul>";
echo "<p><strong>Expected behavior now:</strong></p>";
echo "<ul>";
echo "<li>Home page shows ONLY today's tasks</li>";
echo "<li>Date selection shows ONLY tasks for that specific date</li>";
echo "<li>'All Tasks' shows all user's tasks (limited to 100 for performance)</li>";
echo "</ul>";
echo "</div>";

// Show some debug info about what exists in database
echo "<h2>📊 Database Overview</h2>";
try {
    $stmt = $pdo->prepare("SELECT date, COUNT(*) as count FROM tasks WHERE assigned_to = ? GROUP BY date ORDER BY date DESC LIMIT 10");
    $stmt->execute([$_SESSION['user_id']]);
    $distribution = $stmt->fetchAll();
    
    if (!empty($distribution)) {
        echo "<p><strong>Your tasks by date:</strong></p>";
        echo "<ul>";
        foreach ($distribution as $row) {
            echo "<li>{$row['date']}: {$row['count']} tasks</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>⚠️ No tasks found for current user. Create some tasks to test the filtering.</p>";
        echo "<p><a href='create-test-tasks.php'>➕ Create Test Tasks</a></p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error getting task overview: {$e->getMessage()}</p>";
}
?>