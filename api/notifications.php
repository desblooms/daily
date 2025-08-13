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
        case 'get_notifications':
            getNotifications($input);
            break;
            
        case 'mark_as_read':
            markAsRead($input);
            break;
            
        case 'mark_all_read':
            markAllAsRead();
            break;
            
        case 'create_notification':
            if ($_SESSION['role'] === 'admin') {
                createNotification($input);
            } else {
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function getNotifications($input) {
    $userId = $input['user_id'] ?? $_SESSION['user_id'];
    $unreadOnly = $input['unread_only'] ?? false;
    $limit = min($input['limit'] ?? 20, 50);
    
    $notifications = getUserNotifications($userId, $unreadOnly, $limit);
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'count' => count($notifications)
    ]);
}

function markAsRead($input) {
    $notificationId = $input['notification_id'] ?? null;
    
    if (!$notificationId) {
        echo json_encode(['success' => false, 'message' => 'Missing notification ID']);
        return;
    }
    
    $result = markNotificationAsRead($notificationId, $_SESSION['user_id']);
    
    echo json_encode([
        'success' => $result,
        'message' => $result ? 'Notification marked as read' : 'Failed to mark as read'
    ]);
}

function markAllAsRead() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = TRUE, read_at = NOW() 
            WHERE user_id = ? AND is_read = FALSE
        ");
        $result = $stmt->execute([$_SESSION['user_id']]);
        
        echo json_encode([
            'success' => $result,
            'message' => $result ? 'All notifications marked as read' : 'Failed to mark notifications as read'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function createNotification($input) {
    $userId = $input['user_id'] ?? null;
    $title = $input['title'] ?? null;
    $message = $input['message'] ?? null;
    $type = $input['type'] ?? 'info';
    
    if (!$userId || !$title || !$message) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    $result = createNotification($userId, $title, $message, $type);
    
    echo json_encode([
        'success' => $result,
        'message' => $result ? 'Notification created successfully' : 'Failed to create notification'
    ]);
}
?>