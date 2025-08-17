<?php
echo "<h1>Cleanup Test Files</h1>";

$testFiles = [
    'check-syntax.php',
    'test-api-direct.php', 
    'test-components.php',
    'test-minimal-api.php',
    'test-reassign-api.html',
    'test-simple-reassign.php',
    'api/tasks-minimal.php'
];

echo "<p>The following test files were created for debugging:</p>";
echo "<ul>";

foreach ($testFiles as $file) {
    echo "<li>";
    if (file_exists($file)) {
        echo "<span style='color: green;'>✅ {$file}</span> - ";
        echo "<a href='?delete=" . urlencode($file) . "' onclick='return confirm(\"Delete {$file}?\")'>Delete</a>";
    } else {
        echo "<span style='color: gray;'>❌ {$file}</span> - Not found";
    }
    echo "</li>";
}

echo "</ul>";

// Handle deletion
if (isset($_GET['delete'])) {
    $fileToDelete = $_GET['delete'];
    if (in_array($fileToDelete, $testFiles) && file_exists($fileToDelete)) {
        if (unlink($fileToDelete)) {
            echo "<p style='color: green;'>✅ Deleted: {$fileToDelete}</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to delete: {$fileToDelete}</p>";
        }
        echo "<script>setTimeout(() => window.location.href = 'cleanup-test-files.php', 1000);</script>";
    }
}

echo "<hr>";
echo "<h3>Cleanup Options:</h3>";
echo "<p><a href='?deleteall=1' onclick='return confirm(\"Delete all test files?\")'>Delete All Test Files</a></p>";

if (isset($_GET['deleteall'])) {
    $deleted = 0;
    $failed = 0;
    
    foreach ($testFiles as $file) {
        if (file_exists($file)) {
            if (unlink($file)) {
                $deleted++;
                echo "<p style='color: green;'>✅ Deleted: {$file}</p>";
            } else {
                $failed++;
                echo "<p style='color: red;'>❌ Failed to delete: {$file}</p>";
            }
        }
    }
    
    echo "<p><strong>Summary: {$deleted} deleted, {$failed} failed</strong></p>";
    echo "<script>setTimeout(() => window.location.href = 'cleanup-test-files.php', 2000);</script>";
}

echo "<hr>";
echo "<p><a href='admin-dashboard.php'>Back to Admin Dashboard</a></p>";
echo "<p><em>Note: You can safely delete this cleanup file after use.</em></p>";
?>