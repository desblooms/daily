<?php
// Absolute minimal API test
header('Content-Type: application/json');

try {
    echo json_encode([
        'success' => true,
        'message' => 'Minimal API is working',
        'timestamp' => date('Y-m-d H:i:s'),
        'php_version' => phpversion()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>