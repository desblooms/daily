<?php
// Simplified create user API - minimal version to test
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    // Step 1: Session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Step 2: Database
    require_once '../includes/db.php';
    
    if (!isset($pdo) || !$pdo) {
        throw new Exception('Database connection not available');
    }
    
    // Step 3: Get input
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    // Step 4: Simple validation
    if (empty($input['name']) || empty($input['email']) || empty($input['password'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    // Step 5: Check email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$input['email']]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        exit;
    }
    
    // Step 6: Create user
    $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, is_active) VALUES (?, ?, ?, ?, 1)");
    $result = $stmt->execute([
        $input['name'],
        $input['email'],
        $hashedPassword,
        $input['role'] ?? 'user'
    ]);
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'User created successfully',
            'user_id' => $pdo->lastInsertId()
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create user']);
    }
    
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
?>