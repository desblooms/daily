<?php
// Prevent any HTML output and ensure JSON responses
ini_set('display_errors', 0);
error_reporting(0);

// Start output buffering to catch any unexpected output
ob_start();

try {
    // Set JSON header first
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Include required files
    require_once '../includes/db.php';
    require_once '../includes/functions.php';
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        // Clean any output buffer
        ob_clean();
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    // Verify database connection
    if (!isset($pdo) || !$pdo) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    // Clean output buffer before processing
    ob_clean();
    
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
    // Clean any output buffer
    ob_clean();
    error_log("Users API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Internal server error',
        'debug' => $e->getMessage() // Remove in production
    ]);
} catch (Error $e) {
    // Clean any output buffer
    ob_clean();
    error_log("Users API Fatal Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'System error',
        'debug' => $e->getMessage() // Remove in production
    ]);
} finally {
    // End output buffering
    ob_end_flush();
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
                u.created_at,
                COUNT(t.id) as total_tasks,
                COUNT(CASE WHEN t.status = 'Done' THEN 1 END) as completed_tasks,
                COUNT(CASE WHEN t.status = 'On Progress' THEN 1 END) as active_tasks,
                COUNT(CASE WHEN t.status = 'Pending' THEN 1 END) as pending_tasks
            FROM users u
            LEFT JOIN tasks t ON u.id = t.assigned_to
            WHERE u.is_active = TRUE
            GROUP BY u.id
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
                u.created_at,
                COUNT(t.id) as total_tasks,
                COUNT(CASE WHEN t.status = 'Done' THEN 1 END) as completed_tasks,
                COUNT(CASE WHEN t.status = 'On Progress' THEN 1 END) as active_tasks,
                AVG(CASE WHEN t.status = 'Done' AND t.actual_hours IS NOT NULL THEN t.actual_hours END) as avg_completion_time
            FROM users u
            LEFT JOIN tasks t ON u.id = t.assigned_to
            WHERE u.id = ? AND u.is_active = TRUE
            GROUP BY u.id
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            return;
        }
        
        // Get recent activities
        $stmt = $pdo->prepare("
            SELECT 
                al.action,
                al.resource_type,
                al.resource_id,
                al.details,
                al.created_at,
                t.title as task_title
            FROM activity_logs al
            LEFT JOIN tasks t ON al.resource_id = t.id AND al.resource_type = 'task'
            WHERE al.user_id = ?
            ORDER BY al.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$userId]);
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'user' => $user,
            'recent_activities' => $activities
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
                $params[] = trim($input[$field]); // Simple sanitization
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
        
        // Log activity if function exists
        if (function_exists('logActivity')) {
            logActivity($_SESSION['user_id'], 'profile_updated', 'user', $userId);
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
            SELECT 
                t.*,
                c.name as created_by_name,
                a.name as approved_by_name
            FROM tasks t
            LEFT JOIN users c ON t.created_by = c.id
            LEFT JOIN users a ON t.approved_by = a.id
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
                COUNT(CASE WHEN status = 'On Hold' THEN 1 END) as on_hold_tasks,
                AVG(CASE WHEN status = 'Done' AND actual_hours IS NOT NULL THEN actual_hours END) as avg_completion_time,
                AVG(CASE WHEN status = 'Done' AND estimated_hours IS NOT NULL AND actual_hours IS NOT NULL 
                    THEN (actual_hours / estimated_hours) * 100 END) as avg_accuracy_percentage
            FROM tasks 
            WHERE assigned_to = ?
        ");
        $stmt->execute([$userId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get this week's tasks
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as this_week_tasks
            FROM tasks 
            WHERE assigned_to = ? 
            AND date >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
            AND date < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)
        ");
        $stmt->execute([$userId]);
        $weekStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'stats' => array_merge($stats, $weekStats)
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