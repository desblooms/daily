<?php
// test-apis.php - Put this in your root directory
session_start();

// Mock session for testing (remove in production)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
    $_SESSION['user_name'] = 'Test User';
}

$tests = [
    'Users API - Get Active Users' => 'api/users.php?action=get_active_users',
    'Users API - Get User Stats' => 'api/users.php?action=get_user_stats',
    'Tasks API - Get Tasks' => 'api/tasks.php?action=get_tasks',
    'Tasks API - Get Task Details' => 'api/tasks.php?action=get_task_details&id=1'
];

echo "<h2>API Test Results</h2>";

foreach ($tests as $name => $url) {
    echo "<h3>$name</h3>";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => 'Cookie: ' . $_SERVER['HTTP_COOKIE'] ?? ''
        ]
    ]);
    
    $result = @file_get_contents("http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/$url", false, $context);
    
    if ($result === false) {
        echo "<div style='color: red;'>❌ Failed to connect</div>";
    } else {
        $data = json_decode($result, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "<div style='color: green;'>✅ Valid JSON Response</div>";
            echo "<pre>" . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) . "</pre>";
        } else {
            echo "<div style='color: red;'>❌ Invalid JSON Response</div>";
            echo "<pre>" . htmlspecialchars(substr($result, 0, 500)) . "</pre>";
        }
    }
    echo "<hr>";
}
?>