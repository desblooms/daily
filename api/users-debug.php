<?php
// Debug version to identify the exact error
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Log all errors to a specific file
ini_set('error_log', '../debug_errors.log');

echo "Starting debug API...\n";

try {
    // Set JSON header first
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
    echo "Headers set...\n";
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
        echo "Session started...\n";
    }
    
    echo "Checking includes...\n";
    
    // Include required files one by one
    if (file_exists('../includes/db.php')) {
        echo "db.php exists, including...\n";
        require_once '../includes/db.php';
        echo "db.php included...\n";
    } else {
        throw new Exception('db.php not found');
    }
    
    if (file_exists('../includes/auth.php')) {
        echo "auth.php exists, including...\n";
        require_once '../includes/auth.php';
        echo "auth.php included...\n";
    } else {
        throw new Exception('auth.php not found');
    }
    
    if (file_exists('../includes/functions.php')) {
        echo "functions.php exists, including...\n";
        require_once '../includes/functions.php';
        echo "functions.php included...\n";
    } else {
        throw new Exception('functions.php not found');
    }
    
    echo "All includes loaded...\n";
    
    // Set test session
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
    $_SESSION['user_name'] = 'Test Admin';
    
    echo "Session variables set...\n";
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        throw new Exception('Session not set properly');
    }
    
    echo "Session check passed...\n";
    
    // Verify database connection
    if (!isset($pdo) || !$pdo) {
        throw new Exception('PDO not available');
    }
    
    echo "PDO check passed...\n";
    
    $action = $_GET['action'] ?? $_POST['action'] ?? 'get_active_users';
    
    echo "Action: $action\n";
    
    switch ($action) {
        case 'get_active_users':
            echo "Calling getActiveUsers...\n";
            
            if (function_exists('getActiveUsers')) {
                echo "getActiveUsers function exists...\n";
                getActiveUsers($pdo);
            } else {
                // Manual implementation
                echo "Manual user query...\n";
                
                $stmt = $pdo->prepare("
                    SELECT 
                        u.id, 
                        u.name, 
                        u.email, 
                        u.role, 
                        u.department, 
                        u.avatar, 
                        u.last_login,
                        u.created_at
                    FROM users u
                    WHERE u.is_active = TRUE
                    ORDER BY u.role DESC, u.name ASC
                ");
                $stmt->execute();
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'users' => $users,
                    'count' => count($users),
                    'debug' => 'Manual query executed'
                ]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("API Debug Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'debug' => 'Exception caught',
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
} catch (Error $e) {
    error_log("API Debug Fatal Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'debug' => 'Fatal error caught',
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>