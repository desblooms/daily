<?php
// Simple script to set up test session for API testing
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

session_start();

// Set up test session variables
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['user_name'] = 'Test Admin';
$_SESSION['last_activity'] = time();

echo json_encode([
    'success' => true,
    'message' => 'Test session created successfully',
    'session_data' => [
        'user_id' => $_SESSION['user_id'],
        'role' => $_SESSION['role'],
        'user_name' => $_SESSION['user_name']
    ]
]);
?>