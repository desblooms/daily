<?php
// Step-by-step debug API to isolate 500 error
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$step = 0;
$results = [];

try {
    $step = 1;
    $results[] = "Step {$step}: API endpoint accessible";
    
    $step = 2;
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $results[] = "Step {$step}: Session started - ID: " . session_id();
    
    $step = 3;
    require_once '../includes/db.php';
    $results[] = "Step {$step}: Database file included";
    
    $step = 4;
    if (!isset($pdo) || !$pdo) {
        throw new Exception('PDO connection not available');
    }
    $results[] = "Step {$step}: PDO connection verified";
    
    $step = 5;
    $stmt = $pdo->query("SELECT 1 as test, NOW() as current_time");
    $testResult = $stmt->fetch();
    $results[] = "Step {$step}: Database query test passed - Time: " . $testResult['current_time'];
    
    $step = 6;
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() == 0) {
        throw new Exception('Users table does not exist');
    }
    $results[] = "Step {$step}: Users table exists";
    
    $step = 7;
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $requiredCols = ['id', 'name', 'email', 'password', 'role'];
    foreach ($requiredCols as $col) {
        if (!in_array($col, $columns)) {
            throw new Exception("Missing required column: {$col}");
        }
    }
    $results[] = "Step {$step}: All required columns exist: " . implode(', ', $requiredCols);
    
    $step = 8;
    // Test transaction
    $pdo->beginTransaction();
    $results[] = "Step {$step}: Transaction started";
    
    $step = 9;
    // Test INSERT without actually committing
    $testEmail = 'debug_test_' . time() . '@example.com';
    $hashedPassword = password_hash('debugtest123', PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, is_active) VALUES (?, ?, ?, ?, ?)");
    $insertResult = $stmt->execute([
        'Debug Test User',
        $testEmail, 
        $hashedPassword,
        'user',
        1
    ]);
    
    if ($insertResult) {
        $newUserId = $pdo->lastInsertId();
        $results[] = "Step {$step}: INSERT test successful - New ID: {$newUserId}";
    } else {
        throw new Exception('INSERT test failed');
    }
    
    $step = 10;
    $pdo->rollBack();
    $results[] = "Step {$step}: Transaction rolled back (test insert removed)";
    
    $step = 11;
    // Test session authentication simulation
    $_SESSION['user_id'] = 1; // Simulate admin login
    $_SESSION['role'] = 'admin';
    $results[] = "Step {$step}: Session variables set for testing";
    
    echo json_encode([
        'success' => true,
        'message' => 'All debug steps completed successfully',
        'steps_completed' => $step,
        'results' => $results,
        'session_info' => [
            'session_id' => session_id(),
            'user_id' => $_SESSION['user_id'],
            'role' => $_SESSION['role']
        ]
    ]);
    
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'failed_at_step' => $step,
        'error_message' => $e->getMessage(),
        'error_file' => basename($e->getFile()),
        'error_line' => $e->getLine(),
        'steps_completed' => $results,
        'full_trace' => $e->getTraceAsString()
    ]);
}
?>