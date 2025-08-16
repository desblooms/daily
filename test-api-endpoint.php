<?php
// Simple test to check if the API endpoint is accessible
echo "<h2>API Endpoint Test</h2>\n";

// Test if the API file exists
$apiFile = __DIR__ . '/api/tasks.php';
if (file_exists($apiFile)) {
    echo "<p>✓ API file exists: $apiFile</p>\n";
} else {
    echo "<p>✗ API file NOT found: $apiFile</p>\n";
}

// Test if we can include it
try {
    echo "<p>Testing API accessibility...</p>\n";
    
    // Set up minimal environment for API
    session_start();
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
    $_SESSION['user_name'] = 'Test Admin';
    
    // Test a simple GET request to the API
    $_GET['action'] = 'get_tasks';
    $_GET['date'] = date('Y-m-d');
    
    echo "<p>Calling API with action=get_tasks...</p>\n";
    
    // Capture output
    ob_start();
    include $apiFile;
    $output = ob_get_clean();
    
    echo "<p>✓ API responded successfully</p>\n";
    echo "<p>Response:</p>\n";
    echo "<pre>" . htmlspecialchars($output) . "</pre>\n";
    
} catch (Exception $e) {
    echo "<p>✗ API error: " . $e->getMessage() . "</p>\n";
} catch (Error $e) {
    echo "<p>✗ Fatal error: " . $e->getMessage() . "</p>\n";
}

echo "<hr><p><a href='admin-dashboard.php'>Back to Admin Dashboard</a></p>\n";
?>