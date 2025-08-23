<?php
// Debug API to isolate the 500 error
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    echo "Step 1: Basic PHP working\n";

    // Test 1: Session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    echo "Step 2: Session started\n";

    // Test 2: Database include
    require_once 'includes/db.php';
    echo "Step 3: Database included\n";

    // Test 3: Auth include  
    require_once 'includes/auth.php';
    echo "Step 4: Auth included\n";

    // Test 4: Database connection test
    if (isset($pdo) && $pdo) {
        $stmt = $pdo->query("SELECT 1");
        echo "Step 5: Database connection working\n";
    } else {
        echo "Step 5: Database connection FAILED\n";
    }

    // Test 5: Simulate create user function
    function testCreateUser($pdo) {
        // Check if users table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        if ($stmt->rowCount() == 0) {
            throw new Exception("Users table does not exist");
        }
        
        // Check table structure
        $stmt = $pdo->query("DESCRIBE users");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $requiredColumns = ['id', 'name', 'email', 'password', 'role'];
        foreach ($requiredColumns as $col) {
            if (!in_array($col, $columns)) {
                throw new Exception("Missing required column: {$col}");
            }
        }
        
        return true;
    }

    if (testCreateUser($pdo)) {
        echo "Step 6: User creation test passed\n";
    }

    echo json_encode([
        'success' => true,
        'message' => 'All tests passed',
        'session_id' => session_id(),
        'user_id' => $_SESSION['user_id'] ?? 'not set',
        'role' => $_SESSION['role'] ?? 'not set'
    ]);

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'step_reached' => 'Error occurred during execution'
    ]);
}
?>