<?php
// Users API - Clean version
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
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
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
            SET " . implode(', ', $updates) . ", updated_at = NOW()
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
?>