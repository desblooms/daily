<?php
// Setup database for file attachments and reference links
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Setup</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
    </style>
</head>
<body>
    <h1>Database Setup for Task Attachments & Links</h1>
    
    <?php
    try {
        require_once 'includes/db.php';
        
        echo "<div class='info'>✓ Database connection successful</div>\n";
        
        // Check if reference_link column exists
        $stmt = $pdo->prepare("DESCRIBE tasks");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $hasReferenceLink = false;
        foreach ($columns as $column) {
            if ($column['Field'] === 'reference_link') {
                $hasReferenceLink = true;
                break;
            }
        }
        
        if (!$hasReferenceLink) {
            echo "<div class='info'>Adding reference_link column to tasks table...</div>\n";
            $stmt = $pdo->prepare("ALTER TABLE tasks ADD COLUMN reference_link VARCHAR(500) NULL");
            $stmt->execute();
            echo "<div class='success'>✓ Successfully added reference_link column</div>\n";
        } else {
            echo "<div class='success'>✓ reference_link column already exists</div>\n";
        }
        
        // Create uploads directory
        $uploadDir = 'uploads/tasks/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
            echo "<div class='success'>✓ Created uploads directory: $uploadDir</div>\n";
        } else {
            echo "<div class='success'>✓ Uploads directory already exists</div>\n";
        }
        
        // Test the uploads directory is writable
        if (is_writable($uploadDir)) {
            echo "<div class='success'>✓ Uploads directory is writable</div>\n";
        } else {
            echo "<div class='error'>❌ Uploads directory is not writable - please check permissions</div>\n";
        }
        
        echo "<h2>Setup Complete!</h2>\n";
        echo "<div class='success'>Your task modal now supports:</div>\n";
        echo "<ul>\n";
        echo "<li>✓ Single file attachment upload</li>\n";
        echo "<li>✓ Reference link field</li>\n";
        echo "<li>✓ Proper database storage</li>\n";
        echo "<li>✓ File validation and security</li>\n";
        echo "</ul>\n";
        
        echo "<p><strong>Next steps:</strong></p>\n";
        echo "<ol>\n";
        echo "<li>Clear your browser cache (Ctrl+F5)</li>\n";
        echo "<li>Go to your admin dashboard</li>\n";
        echo "<li>Click 'Add Task' to see the new fields</li>\n";
        echo "</ol>\n";
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>\n";
        echo "<p>Please check your database configuration in includes/db.php</p>\n";
    }
    ?>
    
    <hr>
    <p><a href="admin-dashboard.php">← Back to Admin Dashboard</a></p>
    <p><a href="debug-modal.php">→ Test Modal Fields</a></p>
</body>
</html>