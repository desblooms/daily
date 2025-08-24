<?php
// API endpoint to save push subscription
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
    
    if (!$input || !isset($input['subscription'])) {
        throw new Exception('Invalid input data');
    }
    
    $subscription = $input['subscription'];
    $userId = $input['user_id'] ?? $_SESSION['user_id'] ?? null;
    
    // Validate subscription data
    if (!isset($subscription['endpoint']) || !isset($subscription['keys']['p256dh']) || !isset($subscription['keys']['auth'])) {
        throw new Exception('Invalid subscription data');
    }
    
    $endpoint = $subscription['endpoint'];
    $p256dhKey = $subscription['keys']['p256dh'];
    $authKey = $subscription['keys']['auth'];
    
    // Get user agent and IP
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // Check if subscription already exists
    $stmt = $pdo->prepare("
        SELECT id FROM push_subscriptions 
        WHERE endpoint = ? OR (user_id = ? AND user_id IS NOT NULL)
    ");
    $stmt->execute([$endpoint, $userId]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Update existing subscription
        $stmt = $pdo->prepare("
            UPDATE push_subscriptions 
            SET p256dh_key = ?, auth_key = ?, user_agent = ?, ip_address = ?, 
                last_used = CURRENT_TIMESTAMP, is_active = TRUE
            WHERE id = ?
        ");
        $stmt->execute([$p256dhKey, $authKey, $userAgent, $ipAddress, $existing['id']]);
        $subscriptionId = $existing['id'];
        $action = 'updated';
    } else {
        // Insert new subscription
        $stmt = $pdo->prepare("
            INSERT INTO push_subscriptions 
            (user_id, endpoint, p256dh_key, auth_key, user_agent, ip_address, created_at, last_used, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, TRUE)
        ");
        $stmt->execute([$userId, $endpoint, $p256dhKey, $authKey, $userAgent, $ipAddress]);
        $subscriptionId = $pdo->lastInsertId();
        $action = 'created';
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Subscription {$action} successfully",
        'subscription_id' => $subscriptionId,
        'action' => $action
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>