<?php
// Users API - Fixed version without problematic logActivity calls
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle OPTIONS request for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Include required files
    require_once '../includes/db.php';
    require_once '../includes/auth.php';
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'message' => 'Authentication required'
        ]);
        exit;
    }
    
    // Verify database connection
    if (!isset($pdo) || !$pdo) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    // Get input data
    $input = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    } else {
        $input = $_GET;
    }
    
    // Get action
    $action = $input['action'] ?? $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_user':
            createUserFixed($pdo, $input);
            break;
            
        case 'delete_user':
            deleteUserFixed($pdo, $input);
            break;
            
        case 'get_user_profile':
            getUserProfileFixed($pdo, $input);
            break;
            
        case 'test_connection':
            testConnectionFixed($pdo);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
    }
    
} catch (Throwable $e) {
    http_response_code(500);
    error_log("Users API Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    
    echo json_encode([
        'success' => false, 
        'message' => 'Internal server error',
        'error' => [
            'message' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
}

function createUserFixed($pdo, $input) {
    // Check admin access
    if ($_SESSION['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }
    
    $required = ['name', 'email', 'password'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            echo json_encode(['success' => false, 'message' => "Missing required field: {$field}"]);
            return;
        }
    }
    
    // Validate email
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        return;
    }
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$input['email']]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email address already exists']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password, role, department, phone, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");
        
        $result = $stmt->execute([
            trim($input['name']),
            trim($input['email']),
            $hashedPassword,
            $input['role'] ?? 'user',
            $input['department'] ?? null,
            $input['phone'] ?? null
        ]);
        
        if (!$result) {
            throw new Exception('Failed to create user');
        }
        
        $userId = $pdo->lastInsertId();
        
        // Skip logActivity to avoid issues
        // logActivity($_SESSION['user_id'], 'user_created', 'user', $userId, [...]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'User created successfully',
            'user_id' => $userId
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to create user: ' . $e->getMessage()]);
    }
}

function deleteUserFixed($pdo, $input) {
    // Check admin access
    if ($_SESSION['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }
    
    $userId = $input['user_id'] ?? null;
    $deleteType = $input['delete_type'] ?? 'soft'; // 'soft' or 'hard'
    
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'Missing user ID']);
        return;
    }
    
    // Prevent admin from deleting themselves
    if ($userId == $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
        return;
    }
    
    // Get user info before deletion
    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        if ($deleteType === 'hard') {
            // Hard delete - permanently remove from database
            
            // First check if user has any active tasks
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status NOT IN ('Done', 'Approved')");
            $stmt->execute([$userId]);
            $activeTasks = $stmt->fetchColumn();
            
            if ($activeTasks > 0) {
                echo json_encode(['success' => false, 'message' => "Cannot permanently delete user with {$activeTasks} active tasks. Complete or reassign tasks first."]);
                return;
            }
            
            // Delete user permanently
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            
            $message = "User '{$user['name']}' permanently deleted from database";
            
        } else {
            // Soft delete - mark as inactive (default)
            $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
            $stmt->execute([$userId]);
            
            $message = "User '{$user['name']}' deactivated (soft deleted)";
        }
        
        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'No changes made - user may already be deleted']);
            return;
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'delete_type' => $deleteType,
            'user_info' => [
                'name' => $user['name'],
                'email' => $user['email']
            ]
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to delete user: ' . $e->getMessage()]);
    }
}

function getUserProfileFixed($pdo, $input) {
    try {
        $userId = $_GET['user_id'] ?? $_SESSION['user_id'];
        
        // Non-admin users can only view their own profile
        if ($_SESSION['role'] !== 'admin' && $userId != $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            return;
        }
        
        $stmt = $pdo->prepare("
            SELECT id, name, email, role, department, phone, last_login, created_at
            FROM users 
            WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'user' => $user
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to fetch user profile: ' . $e->getMessage()
        ]);
    }
}

function testConnectionFixed($pdo) {
    try {
        $stmt = $pdo->query("SELECT 1 as test, NOW() as current_time");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Database connection working',
            'test_result' => $result,
            'session_info' => [
                'user_id' => $_SESSION['user_id'] ?? 'not set',
                'role' => $_SESSION['role'] ?? 'not set',
                'session_id' => session_id()
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'message' => 'Database test failed: ' . $e->getMessage()
        ]);
    }
}
?>