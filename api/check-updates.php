<?php
// API endpoint to check for task updates
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Include database
    require_once '../includes/db.php';
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    $lastCheck = $input['last_check'] ?? (time() - 300) * 1000; // Default 5 minutes ago
    $lastCheckDate = date('Y-m-d H:i:s', $lastCheck / 1000);
    
    $userId = $_SESSION['user_id'] ?? null;
    
    $hasUpdates = false;
    $updates = [];
    
    // Check for task updates
    $sql = "
        SELECT t.id, t.title, t.status, t.updated_at, u.name as updated_by_name
        FROM tasks t
        LEFT JOIN users u ON t.updated_by = u.id
        WHERE t.updated_at > ?
    ";
    $params = [$lastCheckDate];
    
    // If user is not admin, only show their tasks
    if ($userId && $_SESSION['role'] !== 'admin') {
        $sql .= " AND t.assigned_to = ?";
        $params[] = $userId;
    }
    
    $sql .= " ORDER BY t.updated_at DESC LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $taskUpdates = $stmt->fetchAll();
    
    if (!empty($taskUpdates)) {
        $hasUpdates = true;
        $updates['tasks'] = $taskUpdates;
    }
    
    // Check for new notifications
    $sql = "
        SELECT id, title, body, sent_at
        FROM push_notifications_log
        WHERE sent_at > ? AND success = 1
    ";
    $params = [$lastCheckDate];
    
    if ($userId) {
        $sql .= " AND user_id = ?";
        $params[] = $userId;
    }
    
    $sql .= " ORDER BY sent_at DESC LIMIT 5";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll();
    
    if (!empty($notifications)) {
        $hasUpdates = true;
        $updates['notifications'] = $notifications;
    }
    
    // Check for status changes
    $sql = "
        SELECT sl.*, t.title as task_title, u.name as updated_by_name
        FROM status_logs sl
        LEFT JOIN tasks t ON sl.task_id = t.id
        LEFT JOIN users u ON sl.updated_by = u.id
        WHERE sl.timestamp > ?
    ";
    $params = [$lastCheckDate];
    
    if ($userId && $_SESSION['role'] !== 'admin') {
        $sql .= " AND t.assigned_to = ?";
        $params[] = $userId;
    }
    
    $sql .= " ORDER BY sl.timestamp DESC LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $statusChanges = $stmt->fetchAll();
    
    if (!empty($statusChanges)) {
        $hasUpdates = true;
        $updates['status_changes'] = $statusChanges;
    }
    
    echo json_encode([
        'success' => true,
        'hasUpdates' => $hasUpdates,
        'updates' => $updates,
        'timestamp' => time() * 1000,
        'lastCheck' => $lastCheck
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>