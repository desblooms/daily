<?php
// api/tasks.php - Clean version
ini_set('display_errors', 0);
error_reporting(0);

try {
    // Set JSON header first
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Include required files
    require_once '../includes/db.php';
    require_once '../includes/auth.php';
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    // Verify database connection
    if (!isset($pdo) || !$pdo) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
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
        case 'get_tasks':
            getTasks($pdo, $input);
            break;
            
        case 'create_task':
            createNewTask($pdo, $input);
            break;
            
        case 'update_task':
            updateTask($pdo, $input);
            break;
            
        case 'delete_task':
            deleteTask($pdo, $input);
            break;
            
        case 'update_status':
            updateTaskStatus($pdo, $input);
            break;
            
        case 'approve':
            approveTask($pdo, $input);
            break;
            
        case 'get_task_details':
            getTaskDetails($pdo, $input);
            break;
            
        case 'export':
            exportTasks($pdo, $input);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Tasks API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Internal server error',
        'debug' => $e->getMessage()
    ]);
} catch (Error $e) {
    http_response_code(500);
    error_log("Tasks API Fatal Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'System error',
        'debug' => $e->getMessage()
    ]);
}

function getTasks($pdo, $input) {
    try {
        $date = $input['date'] ?? date('Y-m-d');
        $userId = $input['user_id'] ?? null;
        $status = $input['status'] ?? null;
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            echo json_encode(['success' => false, 'message' => 'Invalid date format']);
            return;
        }
        
        // Build query based on user role and filters
        $sql = "
            SELECT t.*, 
                   u.name as assigned_to_name,
                   u.email as assigned_to_email,
                   c.name as created_by_name
            FROM tasks t
            LEFT JOIN users u ON t.assigned_to = u.id
            LEFT JOIN users c ON t.created_by = c.id
            WHERE t.date = ?
        ";
        
        $params = [$date];
        
        // Add filters based on user role
        if ($_SESSION['role'] !== 'admin') {
            // Regular users can only see their own tasks
            $sql .= " AND t.assigned_to = ?";
            $params[] = $_SESSION['user_id'];
        } else if ($userId) {
            // Admin can filter by specific user
            $sql .= " AND t.assigned_to = ?";
            $params[] = $userId;
        }
        
        // Add status filter if provided
        if ($status) {
            $sql .= " AND t.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY t.priority = 'high' DESC, t.priority = 'medium' DESC, t.created_at ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'tasks' => $tasks,
            'count' => count($tasks),
            'date' => $date
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch tasks: ' . $e->getMessage()]);
    }
}

function createNewTask($pdo, $input) {
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
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['date'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid date format']);
        return;
    }
    
    // Validate assigned user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND is_active = TRUE");
    $stmt->execute([$input['assigned_to']]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Invalid user assignment']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
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
        
        // Log activity
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, target_type, target_id, details)
            VALUES (?, 'task_created', 'task', ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $taskId,
            json_encode(['title' => $input['title']])
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Task created successfully',
            'task_id' => $taskId
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to create task: ' . $e->getMessage()]);
    }
}

function updateTask($pdo, $input) {
    $taskId = $input['task_id'] ?? null;
    
    if (!$taskId) {
        echo json_encode(['success' => false, 'message' => 'Missing task ID']);
        return;
    }
    
    // Get current task
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$task) {
        echo json_encode(['success' => false, 'message' => 'Task not found']);
        return;
    }
    
    // Check permissions
    if ($_SESSION['role'] !== 'admin' && 
        $task['assigned_to'] != $_SESSION['user_id'] && 
        $task['created_by'] != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Build update query dynamically
        $updateFields = [];
        $params = [];
        
        $allowedFields = ['title', 'details', 'assigned_to', 'date', 'priority', 'estimated_hours', 'due_time'];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updateFields[] = "$field = ?";
                $params[] = $input[$field];
            }
        }
        
        if (!empty($updateFields)) {
            $updateFields[] = "updated_by = ?";
            $updateFields[] = "updated_at = NOW()";
            $params[] = $_SESSION['user_id'];
            $params[] = $taskId;
            
            $sql = "UPDATE tasks SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        
        // Log activity
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, target_type, target_id, details)
            VALUES (?, 'task_updated', 'task', ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $taskId,
            json_encode(['changes' => array_keys($input)])
        ]);
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Task updated successfully']);
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to update task: ' . $e->getMessage()]);
    }
}

function updateTaskStatus($pdo, $input) {
    $taskId = $input['task_id'] ?? null;
    $newStatus = $input['status'] ?? null;
    $comments = $input['comments'] ?? '';
    
    if (!$taskId || !$newStatus) {
        echo json_encode(['success' => false, 'message' => 'Missing task ID or status']);
        return;
    }
    
    $validStatuses = ['Pending', 'On Progress', 'Done', 'Approved', 'On Hold'];
    if (!in_array($newStatus, $validStatuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        return;
    }
    
    // Get current task
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$task) {
        echo json_encode(['success' => false, 'message' => 'Task not found']);
        return;
    }
    
    // Check permissions
    if ($_SESSION['role'] !== 'admin' && $task['assigned_to'] != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Update task status
        $stmt = $pdo->prepare("
            UPDATE tasks 
            SET status = ?, updated_by = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$newStatus, $_SESSION['user_id'], $taskId]);
        
        // Log status change
        $stmt = $pdo->prepare("
            INSERT INTO status_logs (task_id, status, updated_by, comments)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$taskId, $newStatus, $_SESSION['user_id'], $comments]);
        
        // Log activity
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, target_type, target_id, details)
            VALUES (?, 'status_changed', 'task', ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $taskId,
            json_encode(['from' => $task['status'], 'to' => $newStatus])
        ]);
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to update status: ' . $e->getMessage()]);
    }
}

function approveTask($pdo, $input) {
    if ($_SESSION['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Only administrators can approve tasks']);
        return;
    }
    
    $taskId = $input['task_id'] ?? null;
    
    if (!$taskId) {
        echo json_encode(['success' => false, 'message' => 'Missing task ID']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Check if task exists and is in 'Done' status
        $stmt = $pdo->prepare("SELECT status FROM tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$task) {
            echo json_encode(['success' => false, 'message' => 'Task not found']);
            return;
        }
        
        if ($task['status'] !== 'Done') {
            echo json_encode(['success' => false, 'message' => 'Only completed tasks can be approved']);
            return;
        }
        
        // Update to approved status
        $stmt = $pdo->prepare("
            UPDATE tasks 
            SET status = 'Approved', updated_by = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $taskId]);
        
        // Log status change
        $stmt = $pdo->prepare("
            INSERT INTO status_logs (task_id, status, updated_by, comments)
            VALUES (?, 'Approved', ?, 'Task approved by administrator')
        ");
        $stmt->execute([$taskId, $_SESSION['user_id']]);
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Task approved successfully']);
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to approve task: ' . $e->getMessage()]);
    }
}

function deleteTask($pdo, $input) {
    if ($_SESSION['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Only administrators can delete tasks']);
        return;
    }
    
    $taskId = $input['task_id'] ?? null;
    
    if (!$taskId) {
        echo json_encode(['success' => false, 'message' => 'Missing task ID']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Check if task exists
        $stmt = $pdo->prepare("SELECT title FROM tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$task) {
            echo json_encode(['success' => false, 'message' => 'Task not found']);
            return;
        }
        
        // Delete related records first (foreign key constraints)
        $stmt = $pdo->prepare("DELETE FROM status_logs WHERE task_id = ?");
        $stmt->execute([$taskId]);
        
        $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE target_type = 'task' AND target_id = ?");
        $stmt->execute([$taskId]);
        
        // Delete the task
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Task deleted successfully']);
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to delete task: ' . $e->getMessage()]);
    }
}

function getTaskDetails($pdo, $input) {
    $taskId = $input['task_id'] ?? null;
    
    if (!$taskId) {
        echo json_encode(['success' => false, 'message' => 'Missing task ID']);
        return;
    }
    
    try {
        // Get task with user details
        $stmt = $pdo->prepare("
            SELECT t.*, 
                   u.name as assigned_to_name,
                   u.email as assigned_to_email,
                   c.name as created_by_name
            FROM tasks t
            LEFT JOIN users u ON t.assigned_to = u.id
            LEFT JOIN users c ON t.created_by = c.id
            WHERE t.id = ?
        ");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$task) {
            echo json_encode(['success' => false, 'message' => 'Task not found']);
            return;
        }
        
        // Check permissions
        if ($_SESSION['role'] !== 'admin' && 
            $task['assigned_to'] != $_SESSION['user_id'] && 
            $task['created_by'] != $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            return;
        }
        
        // Get status history
        $stmt = $pdo->prepare("
            SELECT sl.*, u.name as updated_by_name
            FROM status_logs sl
            LEFT JOIN users u ON sl.updated_by = u.id
            WHERE sl.task_id = ?
            ORDER BY sl.updated_at DESC
        ");
        $stmt->execute([$taskId]);
        $statusHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'task' => $task,
            'status_history' => $statusHistory
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch task details: ' . $e->getMessage()]);
    }
}

function exportTasks($pdo, $input) {
    try {
        $format = $input['format'] ?? 'csv';
        $date = $input['date'] ?? date('Y-m-d');
        
        // Get tasks data
        $sql = "
            SELECT t.*, 
                   u.name as assigned_to_name,
                   c.name as created_by_name
            FROM tasks t
            LEFT JOIN users u ON t.assigned_to = u.id
            LEFT JOIN users c ON t.created_by = c.id
            WHERE t.date = ?
        ";
        
        $params = [$date];
        
        if ($_SESSION['role'] !== 'admin') {
            $sql .= " AND t.assigned_to = ?";
            $params[] = $_SESSION['user_id'];
        }
        
        $sql .= " ORDER BY t.priority = 'high' DESC, t.created_at ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="tasks_' . $date . '.csv"');
            
            $output = fopen('php://output', 'w');
            
            // CSV Headers
            fputcsv($output, [
                'ID', 'Title', 'Details', 'Assigned To', 'Status', 'Priority', 
                'Date', 'Created By', 'Estimated Hours', 'Due Time', 'Created At'
            ]);
            
            // CSV Data
            foreach ($tasks as $task) {
                fputcsv($output, [
                    $task['id'],
                    $task['title'],
                    $task['details'],
                    $task['assigned_to_name'],
                    $task['status'],
                    $task['priority'],
                    $task['date'],
                    $task['created_by_name'],
                    $task['estimated_hours'],
                    $task['due_time'],
                    $task['created_at']
                ]);
            }
            
            fclose($output);
        } else {
            echo json_encode(['success' => false, 'message' => 'Unsupported export format']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to export tasks: ' . $e->getMessage()]);
    }
}
?>