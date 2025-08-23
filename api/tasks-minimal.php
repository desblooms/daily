<?php
// Minimal tasks API for testing
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Start session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Include database
    require_once '../includes/db.php';
    
    // Check authentication
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    // Check database
    if (!isset($pdo) || !$pdo) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    // Get input
    $input = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    } else {
        $input = $_GET;
    }
    
    $action = $input['action'] ?? '';
    
    if ($action === 'update_task') {
        echo json_encode(updateTaskMinimal($pdo, $input));
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Server error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

function updateTaskMinimal($pdo, $input) {
    try {
        $taskId = (int)($input['task_id'] ?? 0);
        $newAssignedTo = (int)($input['assigned_to'] ?? 0);
        
        if ($taskId <= 0) {
            return ['success' => false, 'message' => 'Invalid task ID'];
        }
        
        if ($newAssignedTo <= 0) {
            return ['success' => false, 'message' => 'Invalid user ID'];
        }
        
        // Check if user is admin
        if ($_SESSION['role'] !== 'admin') {
            return ['success' => false, 'message' => 'Admin access required'];
        }
        
        // Get current task
        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$task) {
            return ['success' => false, 'message' => 'Task not found'];
        }
        
        // Check if new user exists
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE id = ? AND is_active = TRUE");
        $stmt->execute([$newAssignedTo]);
        $newUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$newUser) {
            return ['success' => false, 'message' => 'Target user not found or inactive'];
        }
        
        // Update the task
        $stmt = $pdo->prepare("UPDATE tasks SET assigned_to = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$newAssignedTo, $_SESSION['user_id'], $taskId]);
        
        if ($result) {
            return [
                'success' => true, 
                'message' => 'Task reassigned successfully to ' . $newUser['name'],
                'from_user' => $task['assigned_to'],
                'to_user' => $newAssignedTo,
                'task_id' => $taskId
            ];
        } else {
            return ['success' => false, 'message' => 'Failed to update task'];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false, 
            'message' => 'Update error: ' . $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ];
    }
}
?>