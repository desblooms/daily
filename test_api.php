<?php
// Test script to check API endpoints and session
session_start();

// Simulate admin session for testing
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['user_name'] = 'Test Admin';

echo "<h2>API Test Script</h2>";
echo "<h3>Session Information:</h3>";
echo "<ul>";
echo "<li>User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "</li>";
echo "<li>Role: " . ($_SESSION['role'] ?? 'Not set') . "</li>";
echo "<li>User Name: " . ($_SESSION['user_name'] ?? 'Not set') . "</li>";
echo "</ul>";

echo "<h3>Testing Users API:</h3>";

// Test the users API directly
try {
    // Set up the environment as if we're making an API call
    $_GET['action'] = 'get_active_users';
    
    // Capture output
    ob_start();
    
    // Include the API file
    include 'api/users.php';
    
    $output = ob_get_clean();
    
    echo "<strong>API Response:</strong><br>";
    echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>";
    echo htmlspecialchars($output);
    echo "</pre>";
    
    // Try to decode JSON
    $decoded = json_decode($output, true);
    if ($decoded) {
        echo "<h4>Parsed JSON:</h4>";
        echo "<pre>";
        print_r($decoded);
        echo "</pre>";
        
        if (isset($decoded['users'])) {
            echo "<h4>Users for Dropdown:</h4>";
            foreach ($decoded['users'] as $user) {
                echo "• ID: {$user['id']} - {$user['name']} ({$user['email']})<br>";
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error testing API: " . $e->getMessage();
}

echo "<br><br>";
echo "<strong>Next Steps:</strong><br>";
echo "1. Check the debug_users.php file to see users in database<br>";
echo "2. Make sure users exist and are active<br>";
echo "3. Check browser console for JavaScript errors<br>";
?>