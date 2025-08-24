<?php
// Simple task creation API for testing
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Set test session if not set
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'admin';
        $_SESSION['user_name'] = 'Test Admin';
    }
    
    // Include database
    require_once '../includes/db.php';
    
    if (!isset($pdo) || !$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Get input data first - handle both JSON and form data
    $input = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Check if it's multipart/form-data (file upload) or JSON
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'multipart/form-data') !== false) {
            $input = $_POST; // Form data with files
        } else {
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        }
    } else {
        $input = $_GET;
    }
    
    // Get action from input data or fallback to GET/POST
    $action = $input['action'] ?? $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
        case 'create_task':
            createTask($pdo, $input);
            break;
            
        case 'get_tasks':
            getTasks($pdo, $input);
            break;
            
        default:
            echo json_encode([
                'success' => false, 
                'message' => 'Invalid action: ' . $action,
                'debug' => [
                    'received_action' => $action,
                    'input_data' => $input,
                    'method' => $_SERVER['REQUEST_METHOD'],
                    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set'
                ]
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Tasks Simple API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Internal server error',
        'debug' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

function createTask($pdo, $input) {
    try {
        // Check if user is admin
        if ($_SESSION['role'] !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Only administrators can create tasks']);
            return;
        }
        
        // Validate required fields
        $required = ['title', 'assigned_to', 'date'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                echo json_encode(['success' => false, 'message' => "Missing required field: {$field}"]);
                return;
            }
        }
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['date'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD']);
            return;
        }
        
        // Validate assigned user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND is_active = TRUE");
        $stmt->execute([$input['assigned_to']]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Invalid user assignment']);
            return;
        }
        
        // Insert task with reference link
        $sql = "
            INSERT INTO tasks (title, details, assigned_to, date, created_by, updated_by, priority, estimated_hours, due_time, status, reference_link)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?)
        ";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            trim($input['title']),
            trim($input['details'] ?? ''),
            (int)$input['assigned_to'],
            $input['date'],
            $_SESSION['user_id'],
            $_SESSION['user_id'],
            $input['priority'] ?? 'medium',
            !empty($input['estimated_hours']) ? (float)$input['estimated_hours'] : null,
            $input['due_time'] ?? null,
            !empty($input['reference_link']) ? trim($input['reference_link']) : null
        ]);
        
        if (!$result) {
            throw new Exception('Failed to insert task');
        }
        
        $taskId = $pdo->lastInsertId();
        
        // Handle file attachment if provided
        $attachmentInfo = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $attachmentInfo = handleFileUpload($_FILES['attachment'], $taskId);
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Task created successfully',
            'task_id' => $taskId,
            'attachment' => $attachmentInfo,
            'debug' => 'Simple API worked with file upload support'
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to create task: ' . $e->getMessage(),
            'debug' => [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'input' => $input
            ]
        ]);
    }
}

function getTasks($pdo, $input) {
    try {
        $date = $input['date'] ?? date('Y-m-d');
        
        $sql = "
            SELECT 
                t.*,
                u.name as assigned_name
            FROM tasks t 
            LEFT JOIN users u ON t.assigned_to = u.id 
            WHERE t.date = ?
            ORDER BY t.created_at DESC
            LIMIT 50
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$date]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'tasks' => $tasks,
            'count' => count($tasks),
            'date' => $date
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to get tasks: ' . $e->getMessage()
        ]);
    }
}

function handleFileUpload($file, $taskId) {
    global $pdo;
    
    try {
        // Create uploads directory if it doesn't exist
        $uploadDir = '../uploads/tasks/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Validate file type
        $allowedTypes = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'xlsx', 'xls', 'csv'];
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($fileExt, $allowedTypes)) {
            throw new Exception('File type not allowed. Allowed: ' . implode(', ', $allowedTypes));
        }
        
        // Validate file size (max 10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            throw new Exception('File too large. Maximum size is 10MB.');
        }
        
        // Generate unique filename
        $fileName = 'task_' . $taskId . '_' . time() . '_' . uniqid() . '.' . $fileExt;
        $filePath = $uploadDir . $fileName;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('Failed to upload file');
        }
        
        // Store in database if table exists and has correct structure
        $dbStored = false;
        try {
            $stmt = $pdo->prepare("SHOW TABLES LIKE 'task_attachments'");
            $stmt->execute();
            if ($stmt->fetch()) {
                // Check if table has required columns
                $stmt = $pdo->prepare("SHOW COLUMNS FROM task_attachments LIKE 'original_name'");
                $stmt->execute();
                $hasColumns = $stmt->fetch();
                
                if ($hasColumns) {
                    $stmt = $pdo->prepare("
                        INSERT INTO task_attachments 
                        (task_id, filename, original_name, file_path, file_size, file_type, attachment_type, uploaded_by, uploaded_at) 
                        VALUES (?, ?, ?, ?, ?, ?, 'input', ?, NOW())
                    ");
                    
                    $stmt->execute([
                        $taskId,
                        $fileName,
                        $file['name'],
                        'uploads/tasks/' . $fileName,
                        $file['size'],
                        $fileExt,
                        $_SESSION['user_id'] ?? 1
                    ]);
                    $dbStored = true;
                } else {
                    error_log("task_attachments table exists but missing required columns");
                }
            }
        } catch (Exception $dbError) {
            error_log("Database insert error: " . $dbError->getMessage());
            // Don't fail the upload if DB insert fails
        }
        
        return [
            'filename' => $fileName,
            'original_name' => $file['name'],
            'size' => $file['size'],
            'type' => $fileExt,
            'path' => 'uploads/tasks/' . $fileName,
            'stored_in_db' => $dbStored
        ];
        
    } catch (Exception $e) {
        error_log("File upload error: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}
?>