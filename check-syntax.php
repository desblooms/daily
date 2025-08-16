<?php
echo "<h1>PHP Syntax Check</h1>";

// Check syntax of tasks.php
echo "<h3>Checking api/tasks.php syntax...</h3>";

$output = [];
$return_var = 0;

// Check PHP syntax
exec('php -l api/tasks.php 2>&1', $output, $return_var);

echo "<pre>";
foreach ($output as $line) {
    echo htmlspecialchars($line) . "\n";
}
echo "</pre>";

if ($return_var === 0) {
    echo "<p style='color: green;'>✅ No syntax errors found!</p>";
} else {
    echo "<p style='color: red;'>❌ Syntax errors found!</p>";
}

echo "<hr>";

// Check if the file is readable
echo "<h3>File Access Check...</h3>";
$file = 'api/tasks.php';
if (file_exists($file)) {
    echo "<p style='color: green;'>✅ File exists: $file</p>";
    if (is_readable($file)) {
        echo "<p style='color: green;'>✅ File is readable</p>";
        echo "<p>File size: " . filesize($file) . " bytes</p>";
        echo "<p>Last modified: " . date('Y-m-d H:i:s', filemtime($file)) . "</p>";
    } else {
        echo "<p style='color: red;'>❌ File is not readable</p>";
    }
} else {
    echo "<p style='color: red;'>❌ File does not exist: $file</p>";
}

echo "<hr>";

// Check basic includes
echo "<h3>Include File Check...</h3>";
$includes = [
    'includes/db.php',
    'includes/auth.php',
    'includes/functions.php'
];

foreach ($includes as $include) {
    if (file_exists($include)) {
        echo "<p style='color: green;'>✅ $include exists</p>";
    } else {
        echo "<p style='color: red;'>❌ $include missing</p>";
    }
}

echo "<hr>";

// Test basic API endpoint
echo "<h3>Basic API Test...</h3>";
echo "<p>Testing if we can even reach the API endpoint...</p>";

// Try to include and run a basic test
try {
    // Start output buffering to catch any output
    ob_start();
    
    // Set up fake request data
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [];
    
    // Mock minimal session
    session_start();
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
    
    // Try to include the API file
    include_once 'api/tasks.php';
    
    $output = ob_get_clean();
    echo "<p style='color: green;'>✅ API file included successfully</p>";
    if (!empty($output)) {
        echo "<p>API Output:</p>";
        echo "<pre>" . htmlspecialchars($output) . "</pre>";
    }
    
} catch (Exception $e) {
    ob_end_clean();
    echo "<p style='color: red;'>❌ Error including API: " . htmlspecialchars($e->getMessage()) . "</p>";
} catch (Error $e) {
    ob_end_clean();
    echo "<p style='color: red;'>❌ Fatal error in API: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>