<?php
// Test script for enhanced dashboards
session_start();

// Set up test session data
$_SESSION['user_id'] = 1;
$_SESSION['user_name'] = 'Test User';
$_SESSION['role'] = 'admin';

echo "Testing Enhanced Dashboards...\n\n";

echo "1. Testing includes...\n";
try {
    require_once 'includes/db.php';
    echo "✓ Database connection included\n";
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
}

try {
    require_once 'includes/auth.php';
    echo "✓ Auth functions included\n";
} catch (Exception $e) {
    echo "✗ Auth functions failed: " . $e->getMessage() . "\n";
}

try {
    require_once 'includes/functions.php';
    echo "✓ Core functions included\n";
} catch (Exception $e) {
    echo "✗ Core functions failed: " . $e->getMessage() . "\n";
}

echo "\n2. Testing functions...\n";

// Test getEnhancedAnalytics function
if (function_exists('getEnhancedAnalytics')) {
    try {
        $analytics = getEnhancedAnalytics();
        echo "✓ getEnhancedAnalytics function works\n";
        echo "  - Total tasks: " . ($analytics['total_tasks'] ?? 0) . "\n";
    } catch (Exception $e) {
        echo "✗ getEnhancedAnalytics failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "✗ getEnhancedAnalytics function not found\n";
}

// Test getAllUsers function
if (function_exists('getAllUsers')) {
    try {
        $users = getAllUsers();
        echo "✓ getAllUsers function works\n";
        echo "  - Found " . count($users) . " users\n";
    } catch (Exception $e) {
        echo "✗ getAllUsers failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "✗ getAllUsers function not found\n";
}

// Test getRecentActivities function
if (function_exists('getRecentActivities')) {
    try {
        $activities = getRecentActivities(5);
        echo "✓ getRecentActivities function works\n";
        echo "  - Found " . count($activities) . " activities\n";
    } catch (Exception $e) {
        echo "✗ getRecentActivities failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "✗ getRecentActivities function not found\n";
}

echo "\n3. Testing enhanced dashboards...\n";

// Test if files exist and are readable
$adminDashboard = 'enhanced-admin-dashboard.php';
$userDashboard = 'enhanced-user-dashboard.php';

if (file_exists($adminDashboard) && is_readable($adminDashboard)) {
    echo "✓ Enhanced Admin Dashboard file exists and is readable\n";
} else {
    echo "✗ Enhanced Admin Dashboard file not found or not readable\n";
}

if (file_exists($userDashboard) && is_readable($userDashboard)) {
    echo "✓ Enhanced User Dashboard file exists and is readable\n";
} else {
    echo "✗ Enhanced User Dashboard file not found or not readable\n";
}

// Test JavaScript file
$jsFile = 'assets/js/enhanced-task-manager.js';
if (file_exists($jsFile) && is_readable($jsFile)) {
    echo "✓ Enhanced Task Manager JS file exists and is readable\n";
} else {
    echo "✗ Enhanced Task Manager JS file not found or not readable\n";
}

echo "\nTest completed!\n";
echo "You can now try accessing:\n";
echo "- Admin Dashboard: http://yoursite.com/enhanced-admin-dashboard.php\n";
echo "- User Dashboard: http://yoursite.com/enhanced-user-dashboard.php\n";
?>