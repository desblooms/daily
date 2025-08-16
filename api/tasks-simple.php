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
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    // Get input data
    $input = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    } else {
        $input = $_GET;
    }
    
    switch ($action) {
        case 'create':
        case 'create_task':
            createTask($pdo, $input);
            break;
            
        case 'get_tasks':
            getTasks($pdo, $input);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
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
        
        // Insert task
        $sql = "
            INSERT INTO tasks (title, details, assigned_to, date, created_by, updated_by, priority, estimated_hours, due_time, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
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
            $input['due_time'] ?? null
        ]);
        
        if (!$result) {
            throw new Exception('Failed to insert task');
        }
        
        $taskId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Task created successfully',
            'task_id' => $taskId,
            'debug' => 'Simple API worked'
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
?>