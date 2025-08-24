<?php
// API endpoint for task synchronization
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Include required files
    require_once '../includes/db.php';
    require_once '../includes/functions.php';
    
    $userId = $_SESSION['user_id'] ?? null;
    
    if (!$userId) {
        throw new Exception('User not authenticated');
    }
    
    // Get user's tasks with latest updates
    $sql = "
        SELECT t.*, u.name as assigned_name, c.name as created_name,
               CASE 
                   WHEN t.status = 'Approved' THEN 'Completed'
                   WHEN t.status = 'Done' THEN 'Awaiting Approval'
                   WHEN t.date < CURDATE() AND t.status IN ('Pending', 'On Progress') THEN 'Overdue'
                   WHEN t.date = CURDATE() AND t.status IN ('Pending', 'On Progress') THEN 'Due Today'
                   ELSE 'Active'
               END as task_urgency
        FROM tasks t 
        LEFT JOIN users u ON t.assigned_to = u.id 
        LEFT JOIN users c ON t.created_by = c.id
        WHERE 1=1
    ";
    $params = [];
    
    // Filter based on user role
    if ($_SESSION['role'] !== 'admin') {
        $sql .= " AND t.assigned_to = ?";
        $params[] = $userId;
    }
    
    $sql .= " ORDER BY t.updated_at DESC, t.date ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();
    
    // Get task counts for summary
    $summary = [
        'total' => 0,
        'pending' => 0,
        'in_progress' => 0,
        'completed' => 0,
        'overdue' => 0
    ];
    
    foreach ($tasks as $task) {
        $summary['total']++;
        
        switch ($task['status']) {
            case 'Pending':
                $summary['pending']++;
                break;
            case 'On Progress':
                $summary['in_progress']++;
                break;
            case 'Done':
            case 'Approved':
                $summary['completed']++;
                break;
        }
        
        if ($task['task_urgency'] === 'Overdue') {
            $summary['overdue']++;
        }
    }
    
    // Get recent notifications for this user
    $stmt = $pdo->prepare("
        SELECT title, body, sent_at, success 
        FROM push_notifications_log 
        WHERE user_id = ? AND sent_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY sent_at DESC LIMIT 5
    ");
    $stmt->execute([$userId]);
    $recentNotifications = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'tasks' => $tasks,
        'summary' => $summary,
        'recent_notifications' => $recentNotifications,
        'sync_time' => date('Y-m-d H:i:s'),
        'user_id' => $userId,
        'user_role' => $_SESSION['role'] ?? 'user'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>