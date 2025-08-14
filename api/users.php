<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
requireLogin();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_active_users':
            getActiveUsers();
            break;
            
        case 'get_user_profile':
            getUserProfile();
            break;
            
        case 'update_profile':
            updateUserProfile();
            break;
            
        case 'get_user_tasks':
            getUserTasks();
            break;
            
        case 'get_user_stats':
            getUserStats();
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function getActiveUsers() {
    global $pdo;
    
    // Only admin can get all users, regular users can only see themselves
    if ($_SESSION['role'] !== 'admin') {
        // Return only current user for non-admin
        $stmt = $pdo->prepare("
            SELECT id, name, email, role, department, avatar, last_login 
            FROM users 
            WHERE id = ? AND is_active = TRUE
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'users' => $user ? [$user] : [],
            'count' => $user ? 1 : 0
        ]);
        return;
    }
    
    try {
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
        $users = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'users' => $users,
            'count' => count($users)
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch users: ' . $e->getMessage()]);
    }
}

function getUserProfile() {
    global $pdo;
    
    $userId = $_GET['user_id'] ?? $_SESSION['user_id'];
    
    // Non-admin users can only view their own profile
    if ($_SESSION['role'] !== 'admin' && $userId != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        return;
    }
    
    try {
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
        $user = $stmt->fetch();
        
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
        $activities = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'user' => $user,
            'recent_activities' => $activities
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch user profile: ' . $e->getMessage()]);
    }
}

function updateUserProfile() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
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
            $params[] = sanitizeInput($input[$field]);
        }
    }
    
    if (empty($updates)) {
        echo json_encode(['success' => false, 'message' => 'No valid fields to update']);
        return;
    }
    
    $params[] = $userId;
    
    try {
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
        
        // Log activity
        logActivity($_SESSION['user_id'], 'profile_updated', 'user', $userId);
        
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to update profile: ' . $e->getMessage()]);
    }
}

function getUserTasks() {
    global $pdo;
    
    $userId = $_GET['user_id'] ?? $_SESSION['user_id'];
    $status = $_GET['status'] ?? null;
    $limit = min($_GET['limit'] ?? 20, 100);
    
    // Non-admin users can only view their own tasks
    if ($_SESSION['role'] !== 'admin' && $userId != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        return;
    }
    
    try {
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
        
        $sql .= " ORDER BY 
            CASE WHEN t.status = 'On Progress' THEN 1
                 WHEN t.status = 'Pending' THEN 2
                 WHEN t.status = 'Done' THEN 3
                 WHEN t.status = 'On Hold' THEN 4
                 ELSE 5 END,
            t.priority = 'high' DESC,
            t.date ASC
            LIMIT ?
        ";
        
        $params[] = $limit;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'tasks' => $tasks,
            'count' => count($tasks)
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch user tasks: ' . $e->getMessage()]);
    }
}

function getUserStats() {
    global $pdo;
    
    $userId = $_GET['user_id'] ?? $_SESSION['user_id'];
    $period = $_GET['period'] ?? 'week'; // week, month, year
    
    // Non-admin users can only view their own stats
    if ($_SESSION['role'] !== 'admin' && $userId != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        return;
    }
    
    try {
        // Determine date range
        switch ($period) {
            case 'month':
                $dateFilter = "t.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
            case 'year':
                $dateFilter = "t.date >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)";
                break;
            default: // week
                $dateFilter = "t.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        }
        
        // Overall stats
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_tasks,
                COUNT(CASE WHEN status = 'Done' THEN 1 END) as completed_tasks,
                COUNT(CASE WHEN status = 'On Progress' THEN 1 END) as active_tasks,
                COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending_tasks,
                COUNT(CASE WHEN status = 'Approved' THEN 1 END) as approved_tasks,
                COUNT(CASE WHEN status = 'On Hold' THEN 1 END) as on_hold_tasks,
                AVG(CASE WHEN status = 'Done' AND actual_hours IS NOT NULL THEN actual_hours END) as avg_completion_time,
                COUNT(CASE WHEN priority = 'high' THEN 1 END) as high_priority_tasks,
                COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as tasks_created_today
            FROM tasks t
            WHERE t.assigned_to = ? AND {$dateFilter}
        ");
        $stmt->execute([$userId]);
        $stats = $stmt->fetch();
        
        // Daily completion trend
        $stmt = $pdo->prepare("
            SELECT 
                DATE(updated_at) as date,
                COUNT(*) as completed_count
            FROM tasks t
            JOIN status_logs sl ON t.id = sl.task_id
            WHERE t.assigned_to = ? 
                AND sl.status = 'Done'
                AND {$dateFilter}
            GROUP BY DATE(updated_at)
            ORDER BY date DESC
            LIMIT 30
        ");
        $stmt->execute([$userId]);
        $completionTrend = $stmt->fetchAll();
        
        // Priority distribution
        $stmt = $pdo->prepare("
            SELECT 
                priority,
                COUNT(*) as count
            FROM tasks t
            WHERE t.assigned_to = ? AND {$dateFilter}
            GROUP BY priority
        ");
        $stmt->execute([$userId]);
        $priorityDistribution = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'stats' => $stats,
            'completion_trend' => $completionTrend,
            'priority_distribution' => $priorityDistribution,
            'period' => $period
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch user stats: ' . $e->getMessage()]);
    }
}

// Helper function - should be moved to functions.php
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Helper function - should be moved to functions.php  
function logActivity($userId, $action, $resourceType, $resourceId, $details = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, resource_type, resource_id, details, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $userId,
            $action,
            $resourceType,
            $resourceId,
            $details ? json_encode($details) : null,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
        return false;
    }
}
?>