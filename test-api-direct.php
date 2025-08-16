<?php
echo "<h1>Direct API Test</h1>";

// Start session and set up admin user
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['user_name'] = 'Test Admin';

echo "<p>Session setup complete</p>";

// Set up POST data for testing
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';

// Test data
$testData = [
    'action' => 'update_task',
    'task_id' => 1,
    'assigned_to' => 2,
    'reassign_reason' => 'Direct test reassignment'
];

echo "<h3>Test Data:</h3>";
echo "<pre>" . json_encode($testData, JSON_PRETTY_PRINT) . "</pre>";

// Mock the php://input for the API
$GLOBALS['mockInput'] = json_encode($testData);

// Create a temporary stream for php://input
stream_wrapper_unregister("php");
stream_wrapper_register("php", "MockPHPInputStream");

class MockPHPInputStream {
    private $index = 0;
    
    public function stream_open($path, $mode, $options, &$opened_path) {
        return true;
    }
    
    public function stream_read($count) {
        if ($this->index >= strlen($GLOBALS['mockInput'])) {
            return false;
        }
        
        $result = substr($GLOBALS['mockInput'], $this->index, $count);
        $this->index += strlen($result);
        return $result;
    }
    
    public function stream_eof() {
        return $this->index >= strlen($GLOBALS['mockInput']);
    }
    
    public function stream_stat() {
        return [];
    }
}

echo "<h3>Attempting to call API directly...</h3>";

try {
    // Capture output
    ob_start();
    
    // Include the API
    include 'api/tasks.php';
    
    $output = ob_get_clean();
    
    echo "<h3>API Response:</h3>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
    
    // Try to parse as JSON
    $response = json_decode($output, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "<h3>Parsed Response:</h3>";
        echo "<pre>" . json_encode($response, JSON_PRETTY_PRINT) . "</pre>";
        
        if (isset($response['success'])) {
            if ($response['success']) {
                echo "<p style='color: green;'>✅ API call successful!</p>";
            } else {
                echo "<p style='color: red;'>❌ API returned error: " . htmlspecialchars($response['message']) . "</p>";
            }
        }
    } else {
        echo "<p style='color: orange;'>⚠️ Response is not valid JSON</p>";
    }
    
} catch (Exception $e) {
    ob_end_clean();
    echo "<p style='color: red;'>❌ Exception: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>File: " . $e->getFile() . "</p>";
    echo "<p>Line: " . $e->getLine() . "</p>";
} catch (Error $e) {
    ob_end_clean();
    echo "<p style='color: red;'>❌ Fatal Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>File: " . $e->getFile() . "</p>";
    echo "<p>Line: " . $e->getLine() . "</p>";
}

// Restore php:// wrapper
stream_wrapper_restore("php");

echo "<hr>";
echo "<p><a href='check-syntax.php'>Check Syntax</a></p>";
echo "<p><a href='admin-dashboard.php'>Back to Admin Dashboard</a></p>";
?>