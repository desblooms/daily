<?php
// Users API - Clean version with enhanced error handling
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in production, but log them

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
            'message' => 'Authentication required',
            'debug' => 'Session check failed - user_id: ' . (isset($_SESSION['user_id']) ? 'set' : 'not set') . ', role: ' . (isset($_SESSION['role']) ? 'set' : 'not set')
        ]);
        exit;
    }
    
    // Verify database connection
    if (!isset($pdo) || !$pdo) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    // Get input data first
    $input = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    } else {
        $input = $_GET;
    }
    
    // Get action from input data or fallback to GET/POST
    $action = $input['action'] ?? $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_active_users':
            getActiveUsers($pdo);
            break;
            
        case 'get_user_profile':
            getUserProfile($pdo);
            break;
            
        case 'update_profile':
            updateUserProfile($pdo);
            break;
            
        case 'get_user_tasks':
            getUserTasks($pdo);
            break;
            
        case 'get_user_stats':
            getUserStats($pdo);
            break;
            
        case 'create_user':
            createUser($pdo, $input);
            break;
            
        case 'delete_user':
            deleteUser($pdo, $input);
            break;
            
        case 'toggle_user_status':
            toggleUserStatus($pdo, $input);
            break;
            
        case 'reset_password':
            resetUserPassword($pdo, $input);
            break;
            
        case 'get_all_users':
            getAllUsers($pdo, $input);
            break;
            
        case 'test_connection':
            testConnection($pdo);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action', 'available_actions' => [
                'get_active_users', 'get_user_profile', 'update_profile', 'get_user_tasks', 
                'get_user_stats', 'create_user', 'delete_user', 'toggle_user_status', 
                'reset_password', 'get_all_users', 'test_connection'
            ]]);
    }
    
} catch (Throwable $e) {
    http_response_code(500);
    
    // Log detailed error information
    $errorDetails = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'post_data' => $_POST,
        'get_data' => $_GET,
        'session_data' => [
            'user_id' => $_SESSION['user_id'] ?? 'not set',
            'role' => $_SESSION['role'] ?? 'not set'
        ]
    ];
    
    error_log("Users API Error: " . json_encode($errorDetails));
    
    echo json_encode([
        'success' => false, 
        'message' => 'Internal server error',
        'error_details' => [
            'message' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
}

function getActiveUsers($pdo) {
    try {
        // Only admin can get all users, regular users can only see themselves
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
            return;
        }
        
        // Admin gets all users
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
        
    } catch (Exception $e) {
        error_log("getActiveUsers Error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to fetch users',
            'debug' => $e->getMessage()
        ]);
    }
}

function getUserProfile($pdo) {
    try {
        $userId = $_GET['user_id'] ?? $_SESSION['user_id'];
        
        // Non-admin users can only view their own profile
        if ($_SESSION['role'] !== 'admin' && $userId != $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            return;
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                u.id, 
                u.name, 
                u.email, 
                u.role, 
                u.department, 
                u.avatar, 
                u.phone,
                u.last_login,
                u.created_at
            FROM users u
            WHERE u.id = ? AND u.is_active = TRUE
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
        error_log("getUserProfile Error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to fetch user profile',
            'debug' => $e->getMessage()
        ]);
    }
}

function updateUserProfile($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
            return;
        }
        
        $userId = $input['user_id'] ?? $_SESSION['user_id'];
        
        // Non-admin users can only update their own profile
        if ($_SESSION['role'] !== 'admin' && $userId != $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            return;
        }
        
        $allowedFields = ['name', 'phone', 'department'];
        
        // Admin can update additional fields
        if ($_SESSION['role'] === 'admin') {
            $allowedFields = array_merge($allowedFields, ['email', 'role']);
        }
        
        $updates = [];
        $params = [];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field]) && $input[$field] !== null) {
                $updates[] = "{$field} = ?";
                $params[] = trim($input[$field]);
            }
        }
        
        if (empty($updates)) {
            echo json_encode(['success' => false, 'message' => 'No valid fields to update']);
            return;
        }
        
        $params[] = $userId;
        
        $stmt = $pdo->prepare("
            UPDATE users 
            SET " . implode(', ', $updates) . "
            WHERE id = ? AND is_active = TRUE
        ");
        $stmt->execute($params);
        
        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'No changes made or user not found']);
            return;
        }
        
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        
    } catch (Exception $e) {
        error_log("updateUserProfile Error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to update profile',
            'debug' => $e->getMessage()
        ]);
    }
}

function getUserTasks($pdo) {
    try {
        $userId = $_GET['user_id'] ?? $_SESSION['user_id'];
        $status = $_GET['status'] ?? null;
        $limit = min($_GET['limit'] ?? 20, 100);
        
        // Non-admin users can only view their own tasks
        if ($_SESSION['role'] !== 'admin' && $userId != $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            return;
        }
        
        $sql = "
            SELECT t.*
            FROM tasks t
            WHERE t.assigned_to = ?
        ";
        
        $params = [$userId];
        
        if ($status) {
            $sql .= " AND t.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY t.date DESC, t.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'tasks' => $tasks,
            'count' => count($tasks)
        ]);
        
    } catch (Exception $e) {
        error_log("getUserTasks Error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to fetch user tasks',
            'debug' => $e->getMessage()
        ]);
    }
}

function getUserStats($pdo) {
    try {
        $userId = $_GET['user_id'] ?? $_SESSION['user_id'];
        
        // Non-admin users can only view their own stats
        if ($_SESSION['role'] !== 'admin' && $userId != $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            return;
        }
        
        // Get user task statistics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_tasks,
                COUNT(CASE WHEN status = 'Done' THEN 1 END) as completed_tasks,
                COUNT(CASE WHEN status = 'On Progress' THEN 1 END) as active_tasks,
                COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending_tasks,
                COUNT(CASE WHEN status = 'On Hold' THEN 1 END) as on_hold_tasks
            FROM tasks 
            WHERE assigned_to = ?
        ");
        $stmt->execute([$userId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'stats' => $stats
        ]);
        
    } catch (Exception $e) {
        error_log("getUserStats Error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to fetch user statistics',
            'debug' => $e->getMessage()
        ]);
    }
}

function createUser($pdo, $input) {
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
            INSERT INTO users (name, email, password, role, department, phone, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, ?, TRUE, NOW())
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
        
        // Log activity using existing function (with error handling)
        try {
            if (file_exists('../includes/functions.php')) {
                require_once '../includes/functions.php';
                if (function_exists('logActivity')) {
                    logActivity($_SESSION['user_id'], 'user_created', 'user', $userId, [
                        'name' => $input['name'],
                        'email' => $input['email'],
                        'role' => $input['role'] ?? 'user'
                    ]);
                }
            }
        } catch (Exception $logError) {
            // Don't fail the user creation if logging fails
            error_log("Failed to log activity: " . $logError->getMessage());
        }
        
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

function deleteUser($pdo, $input) {
    // Check admin access
    if ($_SESSION['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }
    $userId = $input['user_id'] ?? null;
    
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'Missing user ID']);
        return;
    }
    
    // Prevent admin from deleting themselves
    if ($userId == $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
        return;
    }
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        return;
    }
    
    // Check if user has active tasks
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status NOT IN ('Done', 'Approved')");
    $stmt->execute([$userId]);
    $activeTasks = $stmt->fetchColumn();
    
    if ($activeTasks > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete user with active tasks. Please reassign or complete their tasks first.']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Soft delete - mark as inactive instead of actual deletion
        $stmt = $pdo->prepare("UPDATE users SET is_active = FALSE WHERE id = ?");
        $stmt->execute([$userId]);
        
        // Log activity (with error handling)
        try {
            if (file_exists('../includes/functions.php')) {
                require_once '../includes/functions.php';
                if (function_exists('logActivity')) {
                    logActivity($_SESSION['user_id'], 'user_deleted', 'user', $userId, [
                        'name' => $user['name']
                    ]);
                }
            }
        } catch (Exception $logError) {
            error_log("Failed to log activity: " . $logError->getMessage());
        }
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'User deactivated successfully']);
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to delete user: ' . $e->getMessage()]);
    }
}

function toggleUserStatus($pdo, $input) {
    // Check admin access
    if ($_SESSION['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }
    $userId = $input['user_id'] ?? null;
    
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'Missing user ID']);
        return;
    }
    
    // Prevent admin from deactivating themselves
    if ($userId == $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Cannot modify your own account status']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Get current status
        $stmt = $pdo->prepare("SELECT is_active, name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            return;
        }
        
        $newStatus = !$user['is_active'];
        
        $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        $stmt->execute([$newStatus, $userId]);
        
        // Log activity (with error handling)
        try {
            if (file_exists('../includes/functions.php')) {
                require_once '../includes/functions.php';
                if (function_exists('logActivity')) {
                    logActivity($_SESSION['user_id'], 'user_status_changed', 'user', $userId, [
                        'name' => $user['name'],
                        'from' => $user['is_active'] ? 'active' : 'inactive',
                        'to' => $newStatus ? 'active' : 'inactive'
                    ]);
                }
            }
        } catch (Exception $logError) {
            error_log("Failed to log activity: " . $logError->getMessage());
        }
        
        $pdo->commit();
        
        $statusText = $newStatus ? 'activated' : 'deactivated';
        echo json_encode(['success' => true, 'message' => "User {$statusText} successfully"]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to update user status: ' . $e->getMessage()]);
    }
}

function resetUserPassword($pdo, $input) {
    // Check admin access
    if ($_SESSION['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }
    $userId = $input['user_id'] ?? null;
    $newPassword = $input['password'] ?? null;
    
    if (!$userId || !$newPassword) {
        echo json_encode(['success' => false, 'message' => 'Missing user ID or password']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Get user info
        $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            return;
        }
        
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users SET password = ?, force_password_change = TRUE WHERE id = ?");
        $stmt->execute([$hashedPassword, $userId]);
        
        // Log activity (with error handling)
        try {
            if (file_exists('../includes/functions.php')) {
                require_once '../includes/functions.php';
                if (function_exists('logActivity')) {
                    logActivity($_SESSION['user_id'], 'password_reset', 'user', $userId, [
                        'name' => $user['name']
                    ]);
                }
            }
        } catch (Exception $logError) {
            error_log("Failed to log activity: " . $logError->getMessage());
        }
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Password reset successfully. User will be prompted to change it on next login.']);
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to reset password: ' . $e->getMessage()]);
    }
}

function getAllUsers($pdo, $input) {
    // Check admin access
    if ($_SESSION['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }
    
    try {
        $includeInactive = $input['include_inactive'] ?? false;
        $search = $input['search'] ?? '';
        
        $sql = "
            SELECT 
                u.*,
                COUNT(t.id) as total_tasks,
                SUM(CASE WHEN t.status IN ('Done', 'Approved') THEN 1 ELSE 0 END) as completed_tasks,
                SUM(CASE WHEN t.status = 'Pending' THEN 1 ELSE 0 END) as pending_tasks,
                SUM(CASE WHEN t.status = 'On Progress' THEN 1 ELSE 0 END) as active_tasks
            FROM users u
            LEFT JOIN tasks t ON u.id = t.assigned_to
            WHERE 1=1
        ";
        
        $params = [];
        
        if (!$includeInactive) {
            $sql .= " AND u.is_active = TRUE";
        }
        
        if ($search) {
            $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.department LIKE ?)";
            $searchParam = '%' . $search . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        $sql .= " GROUP BY u.id ORDER BY u.name";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'users' => $users,
            'count' => count($users)
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch users: ' . $e->getMessage()]);
    }
}

function testConnection($pdo) {
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