<?php
// Fix foreign key constraint error when recreating task_attachments table
require_once 'includes/db.php';

$results = [];
$errors = [];

try {
    // Disable foreign key checks temporarily
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $results[] = "✓ Disabled foreign key checks temporarily";
    
    // Check if task_attachments table exists
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'task_attachments'");
    $stmt->execute();
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        // Get current table info
        $stmt = $pdo->prepare("DESCRIBE task_attachments");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results[] = "✓ Current table columns: " . implode(', ', array_column($columns, 'Field'));
        
        // Check for foreign key constraints
        $stmt = $pdo->prepare("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.TABLE_CONSTRAINTS 
            WHERE TABLE_NAME = 'task_attachments' 
            AND TABLE_SCHEMA = DATABASE()
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ");
        $stmt->execute();
        $foreignKeys = $stmt->fetchAll();
        
        if ($foreignKeys) {
            $results[] = "✓ Found " . count($foreignKeys) . " foreign key constraints";
            
            // Drop foreign key constraints first
            foreach ($foreignKeys as $fk) {
                try {
                    $pdo->exec("ALTER TABLE task_attachments DROP FOREIGN KEY " . $fk['CONSTRAINT_NAME']);
                    $results[] = "✓ Dropped foreign key: " . $fk['CONSTRAINT_NAME'];
                } catch (Exception $e) {
                    $results[] = "⚠️  Could not drop FK " . $fk['CONSTRAINT_NAME'] . ": " . $e->getMessage();
                }
            }
        }
        
        // Now drop the table
        $pdo->exec("DROP TABLE IF EXISTS task_attachments");
        $results[] = "✓ Dropped task_attachments table";
    }
    
    // Create new table without foreign key constraints (for better compatibility)
    $createTableSQL = "
        CREATE TABLE task_attachments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            task_id INT NOT NULL,
            filename VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_size INT DEFAULT 0,
            file_type VARCHAR(50),
            attachment_type ENUM('input', 'output') DEFAULT 'input',
            uploaded_by INT,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_task_id (task_id),
            INDEX idx_uploaded_at (uploaded_at),
            INDEX idx_uploaded_by (uploaded_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($createTableSQL);
    $results[] = "✓ Created new task_attachments table without foreign key constraints";
    
    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    $results[] = "✓ Re-enabled foreign key checks";
    
    // Verify new table structure
    $stmt = $pdo->prepare("DESCRIBE task_attachments");
    $stmt->execute();
    $newColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $results[] = "✓ New table columns: " . implode(', ', array_column($newColumns, 'Field'));
    
    // Test insert to verify table works
    $testInsert = $pdo->prepare("
        INSERT INTO task_attachments 
        (task_id, filename, original_name, file_path, file_size, file_type, attachment_type, uploaded_by, uploaded_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $testInsert->execute([
        7, // task_id
        'test_file_' . time() . '.pdf',
        'Test Document.pdf',
        'uploads/tasks/test_file.pdf',
        1024,
        'pdf',
        'input',
        1
    ]);
    
    $insertedId = $pdo->lastInsertId();
    $results[] = "✓ Test insert successful - ID: $insertedId";
    
    // Clean up test record
    $pdo->prepare("DELETE FROM task_attachments WHERE id = ?")->execute([$insertedId]);
    $results[] = "✓ Cleaned up test record";
    
    // Create uploads directory if needed
    $uploadDir = 'uploads/tasks/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
        $results[] = "✓ Created uploads directory: $uploadDir";
    } else {
        $results[] = "✓ Uploads directory already exists";
    }
    
} catch (Exception $e) {
    $errors[] = "Error: " . $e->getMessage();
    // Try to re-enable foreign key checks even on error
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    } catch (Exception $cleanupError) {
        $errors[] = "Cleanup error: " . $cleanupError->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Fix Foreign Key Error</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <div class="text-center mb-6">
                <i class="fas fa-key text-4xl text-red-600 mb-4"></i>
                <h1 class="text-2xl font-bold text-gray-900">Fix Foreign Key Constraint Error</h1>
                <p class="text-gray-600">Resolving foreign key constraint issues with task_attachments table</p>
            </div>

            <?php if (!empty($results)): ?>
                <div class="mb-6">
                    <h2 class="text-lg font-semibold text-green-800 mb-3">✅ Fix Results</h2>
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded max-h-64 overflow-y-auto">
                        <?php foreach ($results as $result): ?>
                            <div class="text-green-700 mb-1 text-sm"><?= $result ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="mb-6">
                    <h2 class="text-lg font-semibold text-red-800 mb-3">❌ Errors</h2>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded">
                        <?php foreach ($errors as $error): ?>
                            <div class="text-red-700 mb-1 text-sm"><?= $error ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="bg-blue-50 p-6 rounded-lg mb-6">
                <h3 class="font-semibold text-blue-900 mb-3">Foreign Key Issue Fixed:</h3>
                <ul class="text-blue-800 space-y-1 text-sm">
                    <li>• Temporarily disabled foreign key checks</li>
                    <li>• Removed existing foreign key constraints</li>
                    <li>• Dropped and recreated table with correct structure</li>
                    <li>• Created table without problematic foreign key constraints</li>
                    <li>• Added proper indexes for performance</li>
                    <li>• Verified table works with test insert</li>
                    <li>• Re-enabled foreign key checks</li>
                </ul>
            </div>

            <?php if (empty($errors)): ?>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-600 text-xl mr-3"></i>
                        <div>
                            <h3 class="font-semibold text-green-900">Success!</h3>
                            <p class="text-green-800 text-sm">The task_attachments table has been fixed and is ready to use.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="text-center space-x-4">
                <a href="test-attachments.php?id=7" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium inline-flex items-center">
                    <i class="fas fa-vial mr-2"></i>
                    Test Attachments
                </a>
                
                <a href="task.php?id=7" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium inline-flex items-center">
                    <i class="fas fa-eye mr-2"></i>
                    View Task Details
                </a>
                
                <a href="admin-dashboard.php" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-lg font-medium inline-flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</body>
</html>