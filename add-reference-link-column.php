<?php
// Add reference_link column to tasks table if it doesn't exist
require_once 'includes/db.php';

try {
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
        echo "Adding reference_link column to tasks table...\n";
        $stmt = $pdo->prepare("ALTER TABLE tasks ADD COLUMN reference_link VARCHAR(500) NULL AFTER tags");
        $stmt->execute();
        echo "✅ Successfully added reference_link column\n";
    } else {
        echo "✅ reference_link column already exists\n";
    }
    
    // Create uploads directory
    $uploadDir = 'uploads/tasks/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
        echo "✅ Created uploads directory: $uploadDir\n";
    } else {
        echo "✅ Uploads directory already exists\n";
    }
    
    echo "\nTask modal is now ready with:\n";
    echo "- Single file attachment support\n";
    echo "- Reference link field\n";
    echo "- Proper file upload handling\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>