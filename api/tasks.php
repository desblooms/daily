<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
requireLogin();

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'update_status':
            updateTaskStatus($input);
            break;
            
        case 'approve':
            approveTask($input);
            break;
            
        case 'create':
            createNewTask($input);
            break;
            
        case 'update':
            updateTask($input);
            break;
            
        case 'delete':
            deleteTask($input);
            break;
            
        case 'get_tasks':
            getTasks($input);
            break;
            
        case 'assign_task':
            assignTask($input);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function updateTaskStatus($input) {
    global $pdo;
    
    $taskId = $input['task_id'] ?? null;
    $status = $input['status'] ?? null;
    $comments = $input['comments'] ?? null;
    
    if (!$taskId || !$status) {
        echo json_encode(['success' => false, 'message' => 'Missing task ID or status']);
        return;
    }
    
    // Get current task
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();
    
    if (!$task) {
        echo json_encode(['success' => false, 'message' => 'Task not found']);
        return;
    }
    
    // Check permissions
    if ($_SESSION['role'] !== 'admin' && $task['assigned_to'] != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        return;
    }
    
    // Validate status transition
    $validTransitions = [
        'Pending' => ['On Progress', 'On Hold'],
        'On Progress' => ['Done', 'On Hold', 'Pending'],
        'Done' => ['Approved', 'On Progress'], // Only admin can approve
        'Approved' => [], // Cannot change approved tasks
        'On Hold' => ['Pending', 'On Progress']
    ];
    
    if (!in_array($status, $validTransitions[$task['status']] ?? [])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status transition']);
        return;
    }
    
    // Special check for approval - only admin can approve
    if ($status === 'Approved' && $_SESSION['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Only administrators can approve tasks']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Update task status and set updated_by
        $updateData = [
            'status' => $status,
            'updated_by' => $_SESSION['user_id'],
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($status === 'Approved') {
            $updateData['approved_by'] = $_SESSION['user_id'];
        }
        
        $setClause = implode(' = ?, ', array_keys($updateData)) . ' = ?';
        $stmt = $pdo->prepare("UPDATE tasks SET {$setClause} WHERE id = ?");
        $stmt->execute(array_merge(array_values($updateData), [$taskId]));
        
        // Log the status change (handled by trigger, but we can also do it manually)
        $stmt = $pdo->prepare("
            INSERT INTO status_logs (task_id, status, previous_status, updated_by, comments) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$taskId, $status, $task['status'], $_SESSION['user_id'], $comments]);
        
        // Log activity
        logActivity($_SESSION['user_id'], 'task_status_updated', 'task', $taskId, [
            'from' => $task['status'],
            'to' => $status
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Task status updated successfully',
            'new_status' => $status
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to update task status: ' . $e->getMessage()]);
    }
}

function approveTask($input) {
    global $pdo;
    
    if ($_SESSION['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Only administrators can approve tasks']);
        return;
    }
    
    $taskId = $input['task_id'] ?? null;
    
    if (!$taskId) {
        echo json_encode(['success' => false, 'message' => 'Missing task ID']);
        return;
    }
    
    // Get current task
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();
    
    if (!$task) {
        echo json_encode(['success' => false, 'message' => 'Task not found']);
        return;
    }
    
    if ($task['status'] !== 'Done') {
        echo json_encode(['success' => false, 'message' => 'Only completed tasks can be approved']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Update task to approved
        $stmt = $pdo->prepare("
            UPDATE tasks 
            SET status = 'Approved', approved_by = ?, updated_by = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $taskId]);
        
        // Log the approval
        $stmt = $pdo->prepare("
            INSERT INTO status_logs (task_id, status, previous_status, updated_by, comments) 
            VALUES (?, 'Approved', 'Done', ?, 'Task approved by administrator')
        ");
        $stmt->execute([$taskId, $_SESSION['user_id']]);
        
        // Create notification for task assignee
        if ($task['assigned_to'] != $_SESSION['user_id']) {
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, related_type, related_id)
                VALUES (?, ?, ?, 'success', 'task', ?)
            ");
            $stmt->execute([
                $task['assigned_to'],
                'Task Approved',
                "Your task '{$task['title']}' has been approved!",
                $taskId
            ]);
        }
        
        // Log activity
        logActivity($_SESSION['user_id'], 'task_approved', 'task', $taskId);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Task approved successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to approve task: ' . $e->getMessage()]);
    }
}

function createNewTask($input) {
    global $pdo;
    
    if ($_SESSION['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Only administrators can create tasks']);
        return;
    }
    
    $required = ['title', 'assigned_to', 'date'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            echo json_encode(['success' => false, 'message' => "Missing required field: {$field}"]);
            return;
        }
    }
    
    // Validate assigned user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND is_active = TRUE");
    $stmt->execute([$input['assigned_to']]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Invalid user assignment']);
        return;
    }
    
    // Validate date format
    if (!validateDate($input['date'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid date format']);
        return;
    }
    
    try {
        $taskData = [
            'title' => sanitizeInput($input['title']),
            'details' => sanitizeInput($input['details'] ?? ''),
            'assigned_to' => (int)$input['assigned_to'],
            'date' => $input['date'],
            'created_by' => $_SESSION['user_id'],
            'updated_by' => $_SESSION['user_id'],
            'priority' => $input['priority'] ?? 'medium',
            'estimated_hours' => !empty($input['estimated_hours']) ? (float)$input['estimated_hours'] : null,
            'due_time' => $input['due_time'] ?? null
        ];
        
        $taskId = createTask($taskData);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Task created successfully',
            'task_id' => $taskId
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to create task: ' . $e->getMessage()]);
    }
}

function updateTask($input) {
    global $pdo;
    
    $taskId = $input['task_id'] ?? null;
    
    if (!$taskId) {
        echo json_encode(['success' => false, 'message' => 'Missing task ID']);
        return;
    }
    
    // Get current task
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();
    
    if (!$task) {
        echo json_encode(['success' => false, 'message' => 'Task not found']);
        return;
    }
    
    // Check permissions
    $canEdit = ($_SESSION['role'] === 'admin') || 
               ($task['assigned_to'] == $_SESSION['user_id']) || 
               ($task['created_by'] == $_SESSION['user_id']);
    
    if (!$canEdit) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        return;
    }
    
    try {
        $updateFields = [];
        $params = [];
        
        // Fields that can be updated
        $allowedFields = ['title', 'details', 'date', 'priority', 'estimated_hours', 'due_time'];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                if ($field === 'date' && !validateDate($input[$field])) {
                    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
                    return;
                }
                
                $updateFields[] = "{$field} = ?";
                $params[] = ($field === 'estimated_hours') ? (float)$input[$field] : sanitizeInput($input[$field]);
            }
        }
        
        if (empty($updateFields)) {
            echo json_encode(['success' => false, 'message' => 'No valid fields to update']);
            return;
        }
        
        // Add updated_by and updated_at
        $updateFields[] = "updated_by = ?";
        $updateFields[] = "updated_at = NOW()";
        $params[] = $_SESSION['user_id'];
        $params[] = $taskId;
        
        $sql = "UPDATE tasks SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // Log activity
        logActivity($_SESSION['user_id'], 'task_updated', 'task', $taskId, $input);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Task updated successfully'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to update task: ' . $e->getMessage()]);
    }
}

function deleteTask($input) {
    global $pdo;
    
    if ($_SESSION['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Only administrators can delete tasks']);
        return;
    }
    
    $taskId = $input['task_id'] ?? null;
    
    if (!$taskId) {
        echo json_encode(['success' => false, 'message' => 'Missing task ID']);
        return;
    }
    
    // Get current task
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();
    
    if (!$task) {
        echo json_encode(['success' => false, 'message' => 'Task not found']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Delete task (cascade will handle related records)
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        
        // Log activity
        logActivity($_SESSION['user_id'], 'task_deleted', 'task', $taskId, ['title' => $task['title']]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Task deleted successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to delete task: ' . $e->getMessage()]);
    }
}

function getTasks($input) {
    global $pdo;
    
    $userId = $input['user_id'] ?? null;
    $date = $input['date'] ?? null;
    $status = $input['status'] ?? null;
    $limit = min($input['limit'] ?? 50, 100); // Max 100 tasks
    
    // Build query
    $sql = "
        SELECT 
            t.*,
            u.name as assigned_name,
            u.email as assigned_email,
            c.name as created_name
        FROM tasks t 
        LEFT JOIN users u ON t.assigned_to = u.id 
        LEFT JOIN users c ON t.created_by = c.id
        WHERE u.is_active = TRUE
    ";
    
    $params = [];
    
    // Apply filters
    if ($userId) {
        $sql .= " AND t.assigned_to = ?";
        $params[] = $userId;
    }
    
    if ($date) {
        $sql .= " AND t.date = ?";
        $params[] = $date;
    }
    
    if ($status) {
        $sql .= " AND t.status = ?";
        $params[] = $status;
    }
    
    // Check permissions for non-admin users
    if ($_SESSION['role'] !== 'admin') {
        $sql .= " AND (t.assigned_to = ? OR t.created_by = ?)";
        $params[] = $_SESSION['user_id'];
        $params[] = $_SESSION['user_id'];
    }
    
    $sql .= " ORDER BY 
        CASE WHEN t.status = 'On Progress' THEN 1
             WHEN t.status = 'Pending' THEN 2
             WHEN t.status = 'Done' THEN 3
             WHEN t.status = 'On Hold' THEN 4
             ELSE 5 END,
        t.priority = 'high' DESC,
        t.date ASC,
        t.created_at DESC
        LIMIT ?";
    
    $params[] = $limit;
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'tasks' => $tasks,
            'count' => count($tasks)
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch tasks: ' . $e->getMessage()]);
    }
}

function assignTask($input) {
    global $pdo;
    
    if ($_SESSION['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Only administrators can reassign tasks']);
        return;
    }
    
    $taskId = $input['task_id'] ?? null;
    $userId = $input['user_id'] ?? null;
    
    if (!$taskId || !$userId) {
        echo json_encode(['success' => false, 'message' => 'Missing task ID or user ID']);
        return;
    }
    
    // Validate user exists and is active
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE id = ? AND is_active = TRUE");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Invalid user assignment']);
        return;
    }
    
    // Get current task
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();
    
    if (!$task) {
        echo json_encode(['success' => false, 'message' => 'Task not found']);
        return;
    }
    
    if ($task['assigned_to'] == $userId) {
        echo json_encode(['success' => false, 'message' => 'Task is already assigned to this user']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Update task assignment
        $stmt = $pdo->prepare("
            UPDATE tasks 
            SET assigned_to = ?, updated_by = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$userId, $_SESSION['user_id'], $taskId]);
        
        // Create notification for new assignee
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, related_type, related_id)
            VALUES (?, ?, ?, 'info', 'task', ?)
        ");
        $stmt->execute([
            $userId,
            'Task Assigned',
            "You have been assigned the task: {$task['title']}",
            $taskId
        ]);
        
        // Log activity
        logActivity($_SESSION['user_id'], 'task_reassigned', 'task', $taskId, [
            'from_user' => $task['assigned_to'],
            'to_user' => $userId
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => "Task assigned to {$user['name']} successfully"
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to assign task: ' . $e->getMessage()]);
    }
}

// Helper functions
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function createTask($data) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO tasks (title, details, assigned_to, date, created_by, updated_by, priority, estimated_hours, due_time)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['title'],
            $data['details'],
            $data['assigned_to'],
            $data['date'],
            $data['created_by'],
            $data['updated_by'],
            $data['priority'],
            $data['estimated_hours'],
            $data['due_time']
        ]);
        
        $taskId = $pdo->lastInsertId();
        
        // Log activity
        logActivity($data['created_by'], 'task_created', 'task', $taskId);
        
        $pdo->commit();
        return $taskId;
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
}

function logActivity($userId, $action, $resourceType = null, $resourceId = null, $details = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, resource_type, resource_id, details, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $userId,
            $action,
            $resourceType,
            $resourceId,
            $details ? json_encode($details) : null,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        // Log activity failure silently
        error_log("Failed to log activity: " . $e->getMessage());
        return false;
    }
}
?>