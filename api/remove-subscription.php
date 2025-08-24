<?php
// API endpoint to remove push subscription
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Include database
    require_once '../includes/db.php';
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['endpoint'])) {
        throw new Exception('Invalid input data');
    }
    
    $endpoint = $input['endpoint'];
    
    // Mark subscription as inactive instead of deleting
    $stmt = $pdo->prepare("
        UPDATE push_subscriptions 
        SET is_active = FALSE, last_used = CURRENT_TIMESTAMP 
        WHERE endpoint = ?
    ");
    $result = $stmt->execute([$endpoint]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Subscription removed successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Subscription not found'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>