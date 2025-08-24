<?php
// Test script to add sample attachment data and verify the attachments display
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireLogin();

$taskId = $_GET['id'] ?? 7; // Default to task ID 7

$results = [];
$errors = [];

try {
    // 1. First, run the setup to ensure tables exist
    include_once 'setup-task-features.php';
    
    // 2. Check if task exists
    $stmt = $pdo->prepare("SELECT id, title FROM tasks WHERE id = ?");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();
    
    if (!$task) {
        $errors[] = "Task with ID $taskId not found";
    } else {
        $results[] = "✓ Task found: " . $task['title'];
        
        // 3. Check if attachments table exists
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'task_attachments'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $errors[] = "task_attachments table does not exist - run setup-task-features.php first";
        } else {
            $results[] = "✓ task_attachments table exists";
            
            // 4. Check existing attachments
            $stmt = $pdo->prepare("SELECT * FROM task_attachments WHERE task_id = ?");
            $stmt->execute([$taskId]);
            $existingAttachments = $stmt->fetchAll();
            $results[] = "✓ Found " . count($existingAttachments) . " existing attachments";
            
            // 5. Add sample attachments if none exist
            if (empty($existingAttachments)) {
                // Create uploads directory if it doesn't exist
                $uploadDir = 'uploads/tasks/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Sample attachment data
                $sampleAttachments = [
                    [
                        'filename' => "task_{$taskId}_sample_document.pdf",
                        'original_name' => 'Project Requirements.pdf',
                        'file_type' => 'pdf',
                        'file_size' => 245760, // 240KB
                        'attachment_type' => 'input'
                    ],
                    [
                        'filename' => "task_{$taskId}_sample_image.jpg",
                        'original_name' => 'Design Mockup.jpg',
                        'file_type' => 'jpg',
                        'file_size' => 512000, // 500KB
                        'attachment_type' => 'input'
                    ],
                    [
                        'filename' => "task_{$taskId}_sample_spreadsheet.xlsx",
                        'original_name' => 'Task Data.xlsx',
                        'file_type' => 'xlsx',
                        'file_size' => 32768, // 32KB
                        'attachment_type' => 'output'
                    ]
                ];
                
                foreach ($sampleAttachments as $attachment) {
                    // Create sample file
                    $filePath = $uploadDir . $attachment['filename'];
                    file_put_contents($filePath, "Sample file content for " . $attachment['original_name']);
                    
                    // Insert into database
                    $stmt = $pdo->prepare("
                        INSERT INTO task_attachments 
                        (task_id, filename, original_name, file_path, file_size, file_type, attachment_type, uploaded_by, uploaded_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    
                    $stmt->execute([
                        $taskId,
                        $attachment['filename'],
                        $attachment['original_name'],
                        'uploads/tasks/' . $attachment['filename'],
                        $attachment['file_size'],
                        $attachment['file_type'],
                        $attachment['attachment_type'],
                        $_SESSION['user_id'] ?? 1
                    ]);
                    
                    $results[] = "✓ Added sample attachment: " . $attachment['original_name'];
                }
            }
            
            // 6. Verify attachments after insert
            $stmt = $pdo->prepare("SELECT * FROM task_attachments WHERE task_id = ?");
            $stmt->execute([$taskId]);
            $finalAttachments = $stmt->fetchAll();
            $results[] = "✓ Total attachments now: " . count($finalAttachments);
        }
    }
    
} catch (Exception $e) {
    $errors[] = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Test Attachments</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <div class="text-center mb-6">
                <i class="fas fa-paperclip text-4xl text-blue-600 mb-4"></i>
                <h1 class="text-2xl font-bold text-gray-900">Attachment Testing</h1>
                <p class="text-gray-600">Testing attachment display for Task #<?= $taskId ?></p>
            </div>

            <?php if (!empty($results)): ?>
                <div class="mb-6">
                    <h2 class="text-lg font-semibold text-green-800 mb-3">✅ Results</h2>
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

            <div class="text-center space-x-4 mt-8">
                <a href="task.php?id=<?= $taskId ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium inline-flex items-center">
                    <i class="fas fa-eye mr-2"></i>
                    View Task Details
                </a>
                
                <a href="setup-task-features.php" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium inline-flex items-center">
                    <i class="fas fa-cogs mr-2"></i>
                    Run Full Setup
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