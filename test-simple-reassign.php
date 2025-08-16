<?php
// Simple test for reassignment API
session_start();

// Mock session for testing (remove in production)
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['user_name'] = 'Test Admin';

echo "<h1>Reassignment API Test</h1>";

// Test data
$testData = [
    'action' => 'update_task',
    'task_id' => 1,  // Make sure this task exists
    'assigned_to' => 2,  // Make sure this user exists
    'reassign_reason' => 'Test reassignment from simple test'
];

echo "<h3>Test Data:</h3>";
echo "<pre>" . json_encode($testData, JSON_PRETTY_PRINT) . "</pre>";

echo "<h3>Making API Call...</h3>";

// Make the API call
$url = 'http://localhost/daily/api/tasks.php';
$postData = json_encode($testData);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $postData
    ]
]);

$result = file_get_contents($url, false, $context);

echo "<h3>Response:</h3>";
if ($result === false) {
    echo "<p style='color: red;'>Error: Failed to make API call</p>";
} else {
    echo "<pre>" . htmlspecialchars($result) . "</pre>";
    
    $response = json_decode($result, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "<h3>Parsed Response:</h3>";
        echo "<pre>" . json_encode($response, JSON_PRETTY_PRINT) . "</pre>";
        
        if (isset($response['success'])) {
            if ($response['success']) {
                echo "<p style='color: green;'>✅ API call successful!</p>";
            } else {
                echo "<p style='color: red;'>❌ API call failed: " . htmlspecialchars($response['message']) . "</p>";
            }
        }
    } else {
        echo "<p style='color: orange;'>⚠️ Response is not valid JSON</p>";
    }
}

echo "<hr>";
echo "<p><a href='test-reassign-api.html'>Try HTML Test Page</a></p>";
echo "<p><a href='admin-dashboard.php'>Back to Admin Dashboard</a></p>";
?>