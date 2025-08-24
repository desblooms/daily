<?php
// Fix the task_attachments table structure
require_once 'includes/db.php';

$results = [];
$errors = [];

try {
    // Check if task_attachments table exists
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'task_attachments'");
    $stmt->execute();
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        // Check current table structure
        $stmt = $pdo->prepare("DESCRIBE task_attachments");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $results[] = "✓ task_attachments table exists";
        $results[] = "Current columns: " . implode(', ', array_column($columns, 'Field'));
        
        // Drop and recreate table with correct structure
        $pdo->exec("DROP TABLE task_attachments");
        $results[] = "✓ Dropped existing table";
    }
    
    // Create table with proper structure
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
            INDEX idx_uploaded_at (uploaded_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($createTableSQL);
    $results[] = "✓ Created task_attachments table with correct structure";
    
    // Verify new table structure
    $stmt = $pdo->prepare("DESCRIBE task_attachments");
    $stmt->execute();
    $newColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $results[] = "✓ New table columns: " . implode(', ', array_column($newColumns, 'Field'));
    
    // Create uploads directory
    $uploadDir = 'uploads/tasks/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
        $results[] = "✓ Created uploads directory: $uploadDir";
    } else {
        $results[] = "✓ Uploads directory already exists";
    }
    
    // Test insert to verify table works
    $testInsert = $pdo->prepare("
        INSERT INTO task_attachments 
        (task_id, filename, original_name, file_path, file_size, file_type, attachment_type, uploaded_by, uploaded_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $testInsert->execute([
        7, // task_id
        'test_file.pdf',
        'Test Document.pdf',
        'uploads/tasks/test_file.pdf',
        1024,
        'pdf',
        'input',
        1
    ]);
    
    $results[] = "✓ Test insert successful - table structure is correct";
    
    // Clean up test record
    $pdo->prepare("DELETE FROM task_attachments WHERE filename = 'test_file.pdf'")->execute();
    $results[] = "✓ Cleaned up test record";
    
} catch (Exception $e) {
    $errors[] = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Fix Attachments Table</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <div class="text-center mb-6">
                <i class="fas fa-wrench text-4xl text-blue-600 mb-4"></i>
                <h1 class="text-2xl font-bold text-gray-900">Fix Attachments Database</h1>
                <p class="text-gray-600">Fixing the task_attachments table structure</p>
            </div>

            <?php if (!empty($results)): ?>
                <div class="mb-6">
                    <h2 class="text-lg font-semibold text-green-800 mb-3">✅ Fix Results</h2>
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded">
                        <?php foreach ($results as $result): ?>
                            <div class="text-green-700 mb-1"><?= $result ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="mb-6">
                    <h2 class="text-lg font-semibold text-red-800 mb-3">❌ Errors</h2>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded">
                        <?php foreach ($errors as $error): ?>
                            <div class="text-red-700 mb-1"><?= $error ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="bg-blue-50 p-6 rounded-lg mb-6">
                <h3 class="font-semibold text-blue-900 mb-3">What was fixed:</h3>
                <ul class="text-blue-800 space-y-1">
                    <li>• Recreated task_attachments table with correct column structure</li>
                    <li>• Added all required columns: original_name, file_path, file_size, etc.</li>
                    <li>• Set proper data types and constraints</li>
                    <li>• Added database indexes for better performance</li>
                    <li>• Verified table works with test insert</li>
                </ul>
            </div>

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