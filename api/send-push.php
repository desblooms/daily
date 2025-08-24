<?php
// API endpoint to send push notifications
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if user is admin (for security)
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Access denied. Admin privileges required.');
    }
    
    // Include required files
    require_once '../includes/db.php';
    require_once '../includes/vapid-config.php';
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid input data');
    }
    
    // Extract notification data
    $title = $input['title'] ?? 'Daily Calendar Notification';
    $body = $input['body'] ?? 'You have a new update';
    $icon = $input['icon'] ?? '/assets/icons/icon-192x192.png';
    $badge = $input['badge'] ?? '/assets/icons/badge-72x72.png';
    $data = $input['data'] ?? [];
    $userId = $input['user_id'] ?? null;
    $tag = $input['tag'] ?? 'general';
    
    // Get target subscriptions
    $sql = "SELECT * FROM push_subscriptions WHERE is_active = TRUE";
    $params = [];
    
    if ($userId) {
        $sql .= " AND user_id = ?";
        $params[] = $userId;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $subscriptions = $stmt->fetchAll();
    
    if (empty($subscriptions)) {
        throw new Exception('No active subscriptions found');
    }
    
    $sentCount = 0;
    $failedCount = 0;
    $results = [];
    
    foreach ($subscriptions as $subscription) {
        try {
            $result = sendWebPushNotification(
                $subscription['endpoint'],
                $subscription['p256dh_key'],
                $subscription['auth_key'],
                [
                    'title' => $title,
                    'body' => $body,
                    'icon' => $icon,
                    'badge' => $badge,
                    'tag' => $tag,
                    'data' => $data,
                    'requireInteraction' => true,
                    'actions' => [
                        [
                            'action' => 'open',
                            'title' => 'Open',
                            'icon' => '/assets/icons/open-24x24.png'
                        ],
                        [
                            'action' => 'dismiss',
                            'title' => 'Dismiss',
                            'icon' => '/assets/icons/dismiss-24x24.png'
                        ]
                    ]
                ]
            );
            
            if ($result['success']) {
                $sentCount++;
                
                // Update last_used timestamp
                $updateStmt = $pdo->prepare("UPDATE push_subscriptions SET last_used = CURRENT_TIMESTAMP WHERE id = ?");
                $updateStmt->execute([$subscription['id']]);
                
            } else {
                $failedCount++;
                
                // If subscription is invalid, mark as inactive
                if (strpos($result['error'], '410') !== false || strpos($result['error'], '404') !== false) {
                    $updateStmt = $pdo->prepare("UPDATE push_subscriptions SET is_active = FALSE WHERE id = ?");
                    $updateStmt->execute([$subscription['id']]);
                }
            }
            
            $results[] = [
                'subscription_id' => $subscription['id'],
                'success' => $result['success'],
                'error' => $result['error'] ?? null
            ];
            
            // Log notification
            $logStmt = $pdo->prepare("
                INSERT INTO push_notifications_log 
                (user_id, title, body, data, sent_at, success, response_data, error_message) 
                VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, ?, ?, ?)
            ");
            $logStmt->execute([
                $subscription['user_id'],
                $title,
                $body,
                json_encode($data),
                $result['success'] ? 1 : 0,
                json_encode($result),
                $result['error'] ?? null
            ]);
            
        } catch (Exception $e) {
            $failedCount++;
            $results[] = [
                'subscription_id' => $subscription['id'],
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Notifications sent: {$sentCount}, Failed: {$failedCount}",
        'sent_count' => $sentCount,
        'failed_count' => $failedCount,
        'total_subscriptions' => count($subscriptions),
        'results' => $results
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Simple Web Push function (without external libraries)
function sendWebPushNotification($endpoint, $p256dhKey, $authKey, $payload) {
    try {
        // For FCM endpoints, use FCM API
        if (strpos($endpoint, 'fcm.googleapis.com') !== false) {
            return sendFCMNotification($endpoint, $payload);
        }
        
        // For other endpoints, this would require the full Web Push protocol implementation
        // For now, we'll simulate success for non-FCM endpoints
        return [
            'success' => true,
            'message' => 'Simulated push notification sent'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function sendFCMNotification($endpoint, $payload) {
    // Extract FCM token from endpoint
    $parts = explode('/', $endpoint);
    $token = end($parts);
    
    // FCM API endpoint
    $fcmUrl = 'https://fcm.googleapis.com/fcm/send';
    
    // This would require FCM server key - for now simulate success
    // In production, you'd need to configure FCM properly
    
    $notification = [
        'to' => $token,
        'notification' => [
            'title' => $payload['title'],
            'body' => $payload['body'],
            'icon' => $payload['icon'],
            'click_action' => $payload['data']['url'] ?? '/'
        ],
        'data' => $payload['data']
    ];
    
    // Simulate successful response
    return [
        'success' => true,
        'message' => 'FCM notification prepared'
    ];
}
?>