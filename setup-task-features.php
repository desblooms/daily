<?php
// Setup script for comprehensive task features
require_once 'includes/db.php';

$results = [];
$errors = [];

try {
    // 1. Add reference_link column to tasks table if not exists
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
        $stmt = $pdo->prepare("ALTER TABLE tasks ADD COLUMN reference_link VARCHAR(500) NULL AFTER tags");
        $stmt->execute();
        $results[] = "✓ Added reference_link column to tasks table";
    } else {
        $results[] = "✓ reference_link column already exists";
    }
    
    // 2. Create task_attachments table (handle foreign key constraints)
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'task_attachments'");
    $stmt->execute();
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        // Check if table has correct columns
        $stmt = $pdo->prepare("SHOW COLUMNS FROM task_attachments LIKE 'original_name'");
        $stmt->execute();
        $hasCorrectStructure = $stmt->fetch();
        
        if (!$hasCorrectStructure) {
            // Disable foreign key checks temporarily
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            // Drop foreign key constraints if they exist
            try {
                $stmt = $pdo->prepare("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.TABLE_CONSTRAINTS 
                    WHERE TABLE_NAME = 'task_attachments' 
                    AND TABLE_SCHEMA = DATABASE()
                    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                ");
                $stmt->execute();
                $foreignKeys = $stmt->fetchAll();
                
                foreach ($foreignKeys as $fk) {
                    try {
                        $pdo->exec("ALTER TABLE task_attachments DROP FOREIGN KEY " . $fk['CONSTRAINT_NAME']);
                    } catch (Exception $e) {
                        // Ignore FK drop errors
                    }
                }
            } catch (Exception $e) {
                // Ignore FK query errors
            }
            
            $pdo->exec("DROP TABLE task_attachments");
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            $results[] = "✓ Dropped old task_attachments table with incorrect structure";
        }
    }
    
    $stmt = $pdo->prepare("
        CREATE TABLE IF NOT EXISTS task_attachments (
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
    ");
    $stmt->execute();
    $results[] = "✓ Created task_attachments table with correct structure (no foreign key constraints)";
    
    // 3. Create task_reassign_requests table
    $stmt = $pdo->prepare("
        CREATE TABLE IF NOT EXISTS task_reassign_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            task_id INT NOT NULL,
            requested_by INT NOT NULL,
            requested_to INT NOT NULL,
            reason TEXT,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            handled_by INT NULL,
            handled_at TIMESTAMP NULL,
            admin_comment TEXT NULL,
            INDEX idx_task_id (task_id),
            INDEX idx_status (status),
            INDEX idx_requested_at (requested_at),
            INDEX idx_requested_by (requested_by),
            INDEX idx_requested_to (requested_to)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $stmt->execute();
    $results[] = "✓ Created task_reassign_requests table (no foreign key constraints)";
    
    // 4. Create work outputs table (for future use)
    $stmt = $pdo->prepare("
        CREATE TABLE IF NOT EXISTS task_work_outputs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            task_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            output_type ENUM('file', 'link', 'text') DEFAULT 'text',
            content TEXT,
            file_path VARCHAR(500),
            external_link VARCHAR(500),
            visibility ENUM('private', 'public') DEFAULT 'private',
            is_featured BOOLEAN DEFAULT FALSE,
            view_count INT DEFAULT 0,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_task_id (task_id),
            INDEX idx_visibility (visibility),
            INDEX idx_created_at (created_at),
            INDEX idx_created_by (created_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $stmt->execute();
    $results[] = "✓ Created task_work_outputs table (no foreign key constraints)";
    
    // 5. Create task progress updates table
    $stmt = $pdo->prepare("
        CREATE TABLE IF NOT EXISTS task_progress_updates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            task_id INT NOT NULL,
            update_text TEXT NOT NULL,
            progress_percentage INT DEFAULT 0,
            hours_worked DECIMAL(5,2) DEFAULT 0,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_task_id (task_id),
            INDEX idx_created_at (created_at),
            INDEX idx_created_by (created_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $stmt->execute();
    $results[] = "✓ Created task_progress_updates table (no foreign key constraints)";
    
    // 6. Create uploads directory
    $uploadDir = 'uploads/tasks/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
        $results[] = "✓ Created uploads directory: $uploadDir";
    } else {
        $results[] = "✓ Uploads directory already exists";
    }
    
    // 7. Test directory permissions
    if (is_writable($uploadDir)) {
        $results[] = "✓ Uploads directory is writable";
    } else {
        $errors[] = "❌ Uploads directory is not writable - please check permissions";
    }
    
} catch (Exception $e) {
    $errors[] = "❌ Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Task Features Setup</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <div class="text-center mb-8">
                <i class="fas fa-cogs text-4xl text-blue-600 mb-4"></i>
                <h1 class="text-3xl font-bold text-gray-900">Task Features Setup</h1>
                <p class="text-gray-600 mt-2">Comprehensive task management features initialization</p>
            </div>

            <!-- Results -->
            <?php if (!empty($results)): ?>
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-green-800 mb-4">✅ Setup Results</h2>
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded">
                        <?php foreach ($results as $result): ?>
                            <div class="text-green-700 mb-1"><?= $result ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Errors -->
            <?php if (!empty($errors)): ?>
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-red-800 mb-4">⚠️ Issues Found</h2>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded">
                        <?php foreach ($errors as $error): ?>
                            <div class="text-red-700 mb-1"><?= $error ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Features Overview -->
            <div class="mb-8">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">🚀 Enhanced Task Features</h2>
                <div class="grid md:grid-cols-2 gap-6">
                    <div class="bg-blue-50 p-6 rounded-lg">
                        <div class="flex items-center mb-3">
                            <i class="fas fa-info-circle text-blue-600 text-xl mr-3"></i>
                            <h3 class="font-semibold text-blue-900">Comprehensive Task Details</h3>
                        </div>
                        <ul class="text-blue-800 text-sm space-y-1">
                            <li>• Enhanced task information display</li>
                            <li>• Priority levels with color coding</li>
                            <li>• Task categories and tags</li>
                            <li>• Estimated hours and due times</li>
                            <li>• Complete user information</li>
                        </ul>
                    </div>
                    
                    <div class="bg-green-50 p-6 rounded-lg">
                        <div class="flex items-center mb-3">
                            <i class="fas fa-paperclip text-green-600 text-xl mr-3"></i>
                            <h3 class="font-semibold text-green-900">File Attachments</h3>
                        </div>
                        <ul class="text-green-800 text-sm space-y-1">
                            <li>• Upload files to tasks</li>
                            <li>• File type validation</li>
                            <li>• File size management</li>
                            <li>• Download functionality</li>
                            <li>• Multiple file format support</li>
                        </ul>
                    </div>
                    
                    <div class="bg-purple-50 p-6 rounded-lg">
                        <div class="flex items-center mb-3">
                            <i class="fas fa-external-link-alt text-purple-600 text-xl mr-3"></i>
                            <h3 class="font-semibold text-purple-900">Reference Links</h3>
                        </div>
                        <ul class="text-purple-800 text-sm space-y-1">
                            <li>• Add reference URLs to tasks</li>
                            <li>• External resource linking</li>
                            <li>• Documentation references</li>
                            <li>• Easy access to related content</li>
                        </ul>
                    </div>
                    
                    <div class="bg-orange-50 p-6 rounded-lg">
                        <div class="flex items-center mb-3">
                            <i class="fas fa-exchange-alt text-orange-600 text-xl mr-3"></i>
                            <h3 class="font-semibold text-orange-900">Reassignment Requests</h3>
                        </div>
                        <ul class="text-orange-800 text-sm space-y-1">
                            <li>• Request task reassignment</li>
                            <li>• Admin approval workflow</li>
                            <li>• Reason tracking</li>
                            <li>• Status monitoring</li>
                            <li>• Complete audit trail</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Next Steps -->
            <div class="bg-gray-50 p-6 rounded-lg mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">📋 Next Steps</h2>
                <ol class="text-gray-700 space-y-2">
                    <li class="flex items-start">
                        <span class="bg-blue-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-sm mr-3 mt-0.5">1</span>
                        <div>
                            <strong>Test the enhanced task page:</strong>
                            <a href="task.php?id=7" class="text-blue-600 hover:underline ml-2">View Task #7</a>
                        </div>
                    </li>
                    <li class="flex items-start">
                        <span class="bg-blue-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-sm mr-3 mt-0.5">2</span>
                        <div>
                            <strong>Create tasks with attachments:</strong> Use the task creation modal with file upload
                        </div>
                    </li>
                    <li class="flex items-start">
                        <span class="bg-blue-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-sm mr-3 mt-0.5">3</span>
                        <div>
                            <strong>Add reference links:</strong> Include URLs when creating or editing tasks
                        </div>
                    </li>
                    <li class="flex items-start">
                        <span class="bg-blue-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-sm mr-3 mt-0.5">4</span>
                        <div>
                            <strong>Monitor reassignment requests:</strong> View and manage task reassignment requests
                        </div>
                    </li>
                </ol>
            </div>

            <!-- Action Buttons -->
            <div class="text-center space-x-4">
                <a href="admin-dashboard.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium inline-flex items-center">
                    <i class="fas fa-tachometer-alt mr-2"></i>
                    Go to Dashboard
                </a>
                <a href="task.php?id=7" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium inline-flex items-center">
                    <i class="fas fa-eye mr-2"></i>
                    View Sample Task
                </a>
            </div>
        </div>
    </div>
</body>
</html>