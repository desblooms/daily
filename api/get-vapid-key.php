<?php
// API endpoint to get VAPID public key
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Include VAPID configuration
    $vapidConfigPath = '../includes/vapid-config.php';
    
    if (!file_exists($vapidConfigPath)) {
        throw new Exception('VAPID keys not generated. Run generate-vapid-keys.php first.');
    }
    
    require_once $vapidConfigPath;
    
    if (!defined('VAPID_PUBLIC_KEY')) {
        throw new Exception('VAPID public key not found in configuration.');
    }
    
    echo json_encode([
        'success' => true,
        'publicKey' => VAPID_PUBLIC_KEY
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>