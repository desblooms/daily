<?php
// Simplified users API for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set JSON header
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Start session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Simple session setup for testing
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'admin';
        $_SESSION['user_name'] = 'Test Admin';
    }
    
    // Include database
    require_once '../includes/db.php';
    
    if (!isset($pdo) || !$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Simple query to get users
    $stmt = $pdo->prepare("
        SELECT 
            id, 
            name, 
            email, 
            role, 
            department
        FROM users 
        WHERE is_active = TRUE 
        ORDER BY name ASC
        LIMIT 20
    ");
    
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return JSON response
    echo json_encode([
        'success' => true,
        'users' => $users,
        'count' => count($users),
        'debug' => 'Simple API working'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => 'Error in simple API',
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>