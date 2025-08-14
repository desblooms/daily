<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
requireLogin();

$action = $_POST['action'] ?? 'upload';

try {
    switch ($action) {
        case 'upload':
            uploadAttachment();
            break;
            
        case 'delete':
            deleteAttachment();
            break;
            
        case 'download':
            downloadAttachment();
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function uploadAttachment() {
    global $pdo;
    
    $taskId = $_POST['task_id'] ?? null;
    
    if (!$taskId || !isset($_FILES['file'])) {
        echo json_encode(['success' => false, 'message' => 'Task ID and file required']);
        return;
    }
    
    // Verify task exists and user has permission
    $stmt = $pdo->prepare("
        SELECT * FROM tasks 
        WHERE id = ? AND (assigned_to = ? OR created_by = ? OR ? = 'admin')
    ");
    $stmt->execute([$taskId, $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['role']]);
    $task = $stmt->fetch();
    
    if (!$task) {
        echo json_encode(['success' => false, 'message' => 'Task not found or permission denied']);
        return;
    }
    
    $file = $_FILES['file'];
    
    // Validate file
    $maxSize = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'File too large (max 10MB)']);
        return;
    }
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'text/plain', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'File type not allowed']);
        return;
    }
    
    try {
        // Create uploads directory if it doesn't exist
        $uploadDir = '../uploads/tasks/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Save to database
            $stmt = $pdo->prepare("
                INSERT INTO task_attachments (task_id, uploaded_by, filename, original_filename, file_path, file_size, mime_type)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $taskId,
                $_SESSION['user_id'],
                $filename,
                $file['name'],
                $filepath,
                $file['size'],
                $file['type']
            ]);
            
            $attachmentId = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'File uploaded successfully',
                'attachment' => [
                    'id' => $attachmentId,
                    'filename' => $filename,
                    'original_filename' => $file['name'],
                    'file_size' => $file['size']
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>