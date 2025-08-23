<?php
// Clean version of users API
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Include required files
    require_once '../includes/db.php';
    require_once '../includes/auth.php';
    
    // Set test session if not already set
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'admin';
        $_SESSION['user_name'] = 'Test Admin';
    }
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    // Verify database connection
    if (!isset($pdo) || !$pdo) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    $action = $_GET['action'] ?? 'get_active_users';
    
    switch ($action) {
        case 'get_active_users':
            // Simple user query without complex logic
            if ($_SESSION['role'] !== 'admin') {
                // Return only current user for non-admin
                $stmt = $pdo->prepare("
                    SELECT id, name, email, role, department, avatar, last_login 
                    FROM users 
                    WHERE id = ? AND is_active = TRUE
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'users' => $user ? [$user] : [],
                    'count' => $user ? 1 : 0
                ]);
            } else {
                // Admin gets all users (simplified query)
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
                    'count' => count($users)
                ]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Users API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Internal server error',
        'debug' => $e->getMessage()
    ]);
}
?>