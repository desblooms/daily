<?php
require_once 'db.php';

function getTasks($userId = null, $date = null) {
    global $pdo;
    
    $sql = "SELECT t.*, u.name as assigned_name FROM tasks t 
            LEFT JOIN users u ON t.assigned_to = u.id WHERE 1=1";
    $params = [];
    
    if ($userId) {
        $sql .= " AND t.assigned_to = ?";
        $params[] = $userId;
    }
    
    if ($date) {
        $sql .= " AND t.date = ?";
        $params[] = $date;
    }
    
    $sql .= " ORDER BY t.date DESC, t.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function updateTaskStatus($taskId, $status, $userId) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Update task status
        $stmt = $pdo->prepare("UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $taskId]);
        
        // Log status change
        $stmt = $pdo->prepare("INSERT INTO status_logs (task_id, status, updated_by) VALUES (?, ?, ?)");
        $stmt->execute([$taskId, $status, $userId]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollback();
        return false;
    }
}

function getAnalytics($date = null) {
    global $pdo;
    
    $dateFilter = $date ? "AND date = '$date'" : "AND date = CURDATE()";
    
    $stmt = $pdo->prepare("
        SELECT 
            status,
            COUNT(*) as count
        FROM tasks 
        WHERE 1=1 $dateFilter
        GROUP BY status
    ");
    $stmt->execute();
    
    $results = $stmt->fetchAll();
    $analytics = [
        'Pending' => 0,
        'On Progress' => 0,
        'Done' => 0,
        'Approved' => 0,
        'On Hold' => 0
    ];
    
    foreach ($results as $row) {
        $analytics[$row['status']] = $row['count'];
    }
    
    return $analytics;
}

function createTask($title, $details, $assignedTo, $date, $createdBy) {
    global $pdo;
    
    $stmt = $pdo->prepare("INSERT INTO tasks (title, details, assigned_to, date, created_by) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$title, $details, $assignedTo, $date, $createdBy]);
}
?>