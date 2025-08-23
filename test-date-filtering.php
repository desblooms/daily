<?php
// Test Date Filtering Functionality
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set test session if not logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
}

require_once 'includes/db.php';
require_once 'includes/functions.php';

echo "<h1>🗓️ Date Filtering Test</h1>";
echo "<p><strong>Current User:</strong> ID = {$_SESSION['user_id']}, Role = {$_SESSION['role']}</p>";

// Test different date scenarios
$testDates = [
    'today' => date('Y-m-d'),
    'yesterday' => date('Y-m-d', strtotime('-1 day')),
    'tomorrow' => date('Y-m-d', strtotime('+1 day')),
    'next_week' => date('Y-m-d', strtotime('+7 days'))
];

echo "<h2>📊 Task Distribution by Date</h2>";

// First, let's see the overall distribution
try {
    $stmt = $pdo->prepare("SELECT date, COUNT(*) as count FROM tasks WHERE assigned_to = ? GROUP BY date ORDER BY date DESC LIMIT 10");
    $stmt->execute([$_SESSION['user_id']]);
    $distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>";
    echo "<tr style='background: #f5f5f5;'><th>Date</th><th>Task Count</th></tr>";
    
    if (empty($distribution)) {
        echo "<tr><td colspan='2'>No tasks found for current user</td></tr>";
    } else {
        foreach ($distribution as $row) {
            echo "<tr><td>{$row['date']}</td><td>{$row['count']}</td></tr>";
        }
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error getting task distribution: {$e->getMessage()}</p>";
}

echo "<h2>🔍 Date Filtering Tests</h2>";

foreach ($testDates as $label => $testDate) {
    echo "<h3>Testing: {$label} ({$testDate})</h3>";
    
    try {
        // Test the getTasks function with specific date
        $tasks = getTasks($_SESSION['user_id'], $testDate);
        echo "<p><strong>Result:</strong> Found " . count($tasks) . " tasks for {$testDate}</p>";
        
        if (!empty($tasks)) {
            echo "<ul>";
            foreach ($tasks as $task) {
                echo "<li>#{$task['id']}: {$task['title']} - Date: {$task['date']} - Status: {$task['status']}</li>";
            }
            echo "</ul>";
        }
        
        // Also test via direct SQL to compare
        $stmt = $pdo->prepare("SELECT id, title, date, status FROM tasks WHERE assigned_to = ? AND date = ? ORDER BY id");
        $stmt->execute([$_SESSION['user_id'], $testDate]);
        $directTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($tasks) !== count($directTasks)) {
            echo "<p style='color: orange;'>⚠️ Warning: getTasks() returned " . count($tasks) . " tasks but direct SQL returned " . count($directTasks) . " tasks</p>";
        } else {
            echo "<p style='color: green;'>✅ Function and direct SQL match</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error testing {$testDate}: {$e->getMessage()}</p>";
    }
    
    echo "<hr>";
}

// Test the index.php logic simulation
echo "<h2>🏠 Index.php Logic Simulation</h2>";

$testScenarios = [
    ['page' => 'today', 'date' => null],
    ['page' => 'custom', 'date' => date('Y-m-d')],
    ['page' => 'custom', 'date' => date('Y-m-d', strtotime('-1 day'))],
    ['page' => 'all', 'date' => null]
];

foreach ($testScenarios as $scenario) {
    $currentPage = $scenario['page'];
    $selectedDate = $scenario['date'] ?? date('Y-m-d');
    
    echo "<h3>Scenario: page={$currentPage}, date={$selectedDate}</h3>";
    
    // Simulate index.php logic
    switch ($currentPage) {
        case 'today':
            $tasks = getTasks($_SESSION['user_id'], date('Y-m-d'));
            $pageTitle = "Today's Tasks";
            break;
        case 'all':
            $tasks = getTasks($_SESSION['user_id']);
            $pageTitle = "All Tasks";
            break;
        default:
            $tasks = getTasks($_SESSION['user_id'], $selectedDate);
            $pageTitle = "Tasks for " . date('M j', strtotime($selectedDate));
    }
    
    echo "<p><strong>{$pageTitle}:</strong> " . count($tasks) . " tasks</p>";
    
    if ($currentPage !== 'all' && !empty($tasks)) {
        // Check if all returned tasks are for the correct date
        $wrongDateTasks = array_filter($tasks, function($task) use ($selectedDate, $currentPage) {
            $expectedDate = ($currentPage === 'today') ? date('Y-m-d') : $selectedDate;
            return $task['date'] !== $expectedDate;
        });
        
        if (!empty($wrongDateTasks)) {
            echo "<p style='color: red;'>❌ Found " . count($wrongDateTasks) . " tasks with wrong dates:</p>";
            foreach ($wrongDateTasks as $task) {
                echo "<p style='margin-left: 20px;'>- Task #{$task['id']}: Expected {$selectedDate}, got {$task['date']}</p>";
            }
        } else {
            echo "<p style='color: green;'>✅ All tasks have correct date</p>";
        }
    }
}

// Create test task for today if none exist
echo "<h2>🔧 Create Test Data</h2>";
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND date = ?");
$stmt->execute([$_SESSION['user_id'], date('Y-m-d')]);
$todayCount = $stmt->fetchColumn();

if ($todayCount == 0) {
    echo "<p>No tasks for today. Creating a test task...</p>";
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO tasks (title, details, date, assigned_to, created_by, status, priority) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            'Test Task for Today',
            'This task was created to test date filtering',
            date('Y-m-d'),
            $_SESSION['user_id'],
            $_SESSION['user_id'],
            'Pending',
            'medium'
        ]);
        
        if ($result) {
            echo "<p style='color: green;'>✅ Test task created for today</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Failed to create test task: {$e->getMessage()}</p>";
    }
}

echo "<h2>🔗 Quick Links</h2>";
echo "<p>";
echo "<a href='index.php'>🏠 Home (Today)</a> | ";
echo "<a href='index.php?page=today'>📅 Today's Tasks</a> | ";
echo "<a href='index.php?page=all'>📋 All Tasks</a> | ";
echo "<a href='index.php?date=" . date('Y-m-d', strtotime('-1 day')) . "'>⬅️ Yesterday</a> | ";
echo "<a href='index.php?date=" . date('Y-m-d', strtotime('+1 day')) . "'>➡️ Tomorrow</a>";
echo "</p>";

echo "<h2>💡 Troubleshooting Tips</h2>";
echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 5px; border-left: 4px solid #2196F3;'>";
echo "<p><strong>If tasks aren't showing for specific dates:</strong></p>";
echo "<ul>";
echo "<li>Check if tasks are actually assigned to that date in the database</li>";
echo "<li>Verify the user has tasks assigned to them</li>";
echo "<li>Check if you're looking at the right page (today vs specific date)</li>";
echo "<li>Make sure the date format is correct (YYYY-MM-DD)</li>";
echo "</ul>";
echo "</div>";
?>