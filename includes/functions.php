<?php
require_once 'db.php';

/**
 * Core Task Functions
 */
function getTasks($userId = null, $date = null, $status = null, $limit = 50) {
    global $pdo;
    
    $sql = "
        SELECT 
            t.*,
            u.name as assigned_name,
            u.email as assigned_email,
            u.department as assigned_department,
            c.name as created_name,
            a.name as approved_name,
            DATEDIFF(CURDATE(), t.date) as days_since_due,
            CASE 
                WHEN t.status = 'Approved' THEN 'Completed'
                WHEN t.status = 'Done' THEN 'Awaiting Approval'
                WHEN t.date < CURDATE() AND t.status IN ('Pending', 'On Progress') THEN 'Overdue'
                WHEN t.date = CURDATE() AND t.status IN ('Pending', 'On Progress') THEN 'Due Today'
                ELSE 'Active'
            END as task_urgency
        FROM tasks t 
        LEFT JOIN users u ON t.assigned_to = u.id 
        LEFT JOIN users c ON t.created_by = c.id
        LEFT JOIN users a ON t.approved_by = a.id
        WHERE u.is_active = TRUE
    ";
    
    $params = [];
    
    if ($userId) {
        $sql .= " AND t.assigned_to = ?";
        $params[] = $userId;
    }
    
    if ($date) {
        $sql .= " AND t.date = ?";
        $params[] = $date;
    }
    
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
        t.priority = 'medium' DESC,
        t.date ASC,
        t.created_at DESC
        LIMIT ?";
    
    $params[] = $limit;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getTaskById($taskId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            u.name as assigned_name,
            u.email as assigned_email,
            c.name as created_name,
            a.name as approved_name
        FROM tasks t 
        LEFT JOIN users u ON t.assigned_to = u.id 
        LEFT JOIN users c ON t.created_by = c.id
        LEFT JOIN users a ON t.approved_by = a.id
        WHERE t.id = ?
    ");
    $stmt->execute([$taskId]);
    return $stmt->fetch();
}

function createTask($data) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO tasks (title, details, assigned_to, date, created_by, updated_by, priority, estimated_hours, due_time, tags)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['title'],
            $data['details'] ?? null,
            $data['assigned_to'],
            $data['date'],
            $data['created_by'],
            $data['created_by'], // Set updated_by to created_by initially
            $data['priority'] ?? 'medium',
            $data['estimated_hours'] ?? null,
            $data['due_time'] ?? null,
            isset($data['tags']) ? json_encode($data['tags']) : null
        ]);
        
        $taskId = $pdo->lastInsertId();
        
        // Log initial status
        logTaskStatusChange($taskId, 'Pending', null, $data['created_by']);
        
        // Create notification for assigned user
        if ($data['assigned_to'] != $data['created_by']) {
            createNotification($data['assigned_to'], 'New Task Assigned', 
                'You have been assigned a new task: ' . $data['title'], 'info', 'task', $taskId);
        }
        
        logActivity($data['created_by'], 'task_created', 'task', $taskId);
        
        $pdo->commit();
        return $taskId;
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
}

function updateTaskStatus($taskId, $status, $userId, $comments = null) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Get current task
        $task = getTaskById($taskId);
        if (!$task) {
            throw new Exception('Task not found');
        }
        
        $previousStatus = $task['status'];
        
        // Update task status
        $updateData = ['status' => $status, 'updated_by' => $userId, 'updated_at' => date('Y-m-d H:i:s')];
        
        if ($status === 'Approved' && isAdmin()) {
            $updateData['approved_by'] = $userId;
        }
        
        $setClause = implode(' = ?, ', array_keys($updateData)) . ' = ?';
        $stmt = $pdo->prepare("UPDATE tasks SET {$setClause} WHERE id = ?");
        $stmt->execute(array_merge(array_values($updateData), [$taskId]));
        
        // Log status change
        logTaskStatusChange($taskId, $status, $previousStatus, $userId, $comments);
        
        // Create notifications
        if ($userId != $task['assigned_to']) {
            createNotification($task['assigned_to'], 'Task Status Updated', 
                "Your task '{$task['title']}' status changed to {$status}", 'info', 'task', $taskId);
        }
        
        if ($status === 'Done' && $task['created_by'] != $userId) {
            createNotification($task['created_by'], 'Task Completed', 
                "Task '{$task['title']}' has been completed", 'success', 'task', $taskId);
        }
        
        logActivity($userId, 'task_status_updated', 'task', $taskId, ['from' => $previousStatus, 'to' => $status]);
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
}

function logTaskStatusChange($taskId, $status, $previousStatus = null, $userId = null, $comments = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO status_logs (task_id, status, previous_status, updated_by, comments)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    return $stmt->execute([$taskId, $status, $previousStatus, $userId, $comments]);
}

/**
 * Analytics Functions - SINGLE IMPLEMENTATION
 */
function getAnalytics($date = null, $userId = null) {
    global $pdo;
    
    $dateFilter = $date ? "AND t.date = ?" : "AND t.date = CURDATE()";
    $userFilter = $userId ? "AND t.assigned_to = ?" : "";
    
    $params = [];
    if ($date) $params[] = $date;
    if ($userId) $params[] = $userId;
    
    $stmt = $pdo->prepare("
        SELECT 
            t.status,
            COUNT(*) as count,
            AVG(t.actual_hours) as avg_hours,
            SUM(CASE WHEN t.priority = 'high' THEN 1 ELSE 0 END) as high_priority_count,
            SUM(CASE WHEN t.date < CURDATE() AND t.status NOT IN ('Done', 'Approved') THEN 1 ELSE 0 END) as overdue_count
        FROM tasks t 
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE u.is_active = TRUE {$dateFilter} {$userFilter}
        GROUP BY t.status
    ");
    $stmt->execute($params);
    
    $results = $stmt->fetchAll();
    $analytics = [
        'Pending' => 0,
        'On Progress' => 0,
        'Done' => 0,
        'Approved' => 0,
        'On Hold' => 0,
        'total' => 0,
        'overdue' => 0,
        'high_priority' => 0,
        'avg_completion_time' => 0
    ];
    
    foreach ($results as $row) {
        $analytics[$row['status']] = (int)$row['count'];
        $analytics['total'] += (int)$row['count'];
        $analytics['overdue'] += (int)$row['overdue_count'];
        $analytics['high_priority'] += (int)$row['high_priority_count'];
        if ($row['avg_hours']) {
            $analytics['avg_completion_time'] = round($row['avg_hours'], 2);
        }
    }
    
    return $analytics;
}

function getWeeklyAnalytics($userId = null) {
    global $pdo;
    
    $userFilter = $userId ? "AND t.assigned_to = ?" : "";
    $params = $userId ? [$userId] : [];
    
    $stmt = $pdo->prepare("
        SELECT 
            DATE(t.date) as task_date,
            t.status,
            COUNT(*) as count
        FROM tasks t
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE u.is_active = TRUE 
        AND t.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        {$userFilter}
        GROUP BY DATE(t.date), t.status
        ORDER BY task_date DESC
    ");
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

function getProductivityMetrics($userId = null, $days = 30) {
    global $pdo;
    
    $userFilter = $userId ? "AND t.assigned_to = ?" : "";
    $params = [$days];
    if ($userId) $params[] = $userId;
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_tasks,
            SUM(CASE WHEN t.status IN ('Done', 'Approved') THEN 1 ELSE 0 END) as completed_tasks,
            AVG(CASE WHEN t.status IN ('Done', 'Approved') THEN t.actual_hours END) as avg_completion_time,
            SUM(CASE WHEN t.date < CURDATE() AND t.status NOT IN ('Done', 'Approved') THEN 1 ELSE 0 END) as overdue_tasks,
            COUNT(DISTINCT t.assigned_to) as active_users
        FROM tasks t
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE u.is_active = TRUE 
        AND t.date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        {$userFilter}
    ");
    $stmt->execute($params);
    
    $metrics = $stmt->fetch();
    
    if ($metrics && $metrics['total_tasks'] > 0) {
        $metrics['completion_rate'] = round(($metrics['completed_tasks'] / $metrics['total_tasks']) * 100, 2);
    } else {
        $metrics['completion_rate'] = 0;
    }
    
    return $metrics;
}

/**
 * User Management Functions
 */
function getAllUsers($includeInactive = false) {
    global $pdo;
    
    $activeFilter = $includeInactive ? "" : "WHERE is_active = TRUE";
    
    $stmt = $pdo->prepare("
        SELECT 
            u.*,
            COUNT(t.id) as total_tasks,
            SUM(CASE WHEN t.status IN ('Done', 'Approved') THEN 1 ELSE 0 END) as completed_tasks,
            MAX(u.last_login) as last_login
        FROM users u
        LEFT JOIN tasks t ON u.id = t.assigned_to
        {$activeFilter}
        GROUP BY u.id
        ORDER BY u.name
    ");
    $stmt->execute();
    
    return $stmt->fetchAll();
}

function getUserStats($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'On Progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status = 'Done' THEN 1 ELSE 0 END) as done,
            SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'On Hold' THEN 1 ELSE 0 END) as on_hold,
            SUM(CASE WHEN status IN ('Done', 'Approved') THEN 1 ELSE 0 END) as completed,
            AVG(CASE WHEN status IN ('Done', 'Approved') THEN actual_hours END) as avg_completion_time,
            COUNT(CASE WHEN date < CURDATE() AND status NOT IN ('Done', 'Approved') THEN 1 END) as overdue
        FROM tasks 
        WHERE assigned_to = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$userId]);
    
    $stats = $stmt->fetch();
    
    if (!$stats) {
        return [
            'total' => 0, 'pending' => 0, 'in_progress' => 0, 'done' => 0,
            'approved' => 0, 'on_hold' => 0, 'completed' => 0,
            'avg_completion_time' => 0, 'overdue' => 0
        ];
    }
    
    return $stats;
}

function updateUserLastLogin($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    return $stmt->execute([$userId]);
}

/**
 * Notification Functions
 */
function createNotification($userId, $title, $message, $type = 'info', $relatedType = null, $relatedId = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, type, related_type, related_id)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    return $stmt->execute([$userId, $title, $message, $type, $relatedType, $relatedId]);
}

function getUserNotifications($userId, $unreadOnly = false, $limit = 20) {
    global $pdo;
    
    $readFilter = $unreadOnly ? "AND is_read = FALSE" : "";
    
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? {$readFilter}
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$userId, $limit]);
    
    return $stmt->fetchAll();
}

function markNotificationAsRead($notificationId, $userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = TRUE, read_at = NOW() 
        WHERE id = ? AND user_id = ?
    ");
    
    return $stmt->execute([$notificationId, $userId]);
}

function getUnreadNotificationCount($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$userId]);
    
    return (int)$stmt->fetchColumn();
}

/**
 * Activity Logging Functions
 */
function logActivity($userId, $action, $resourceType = null, $resourceId = null, $details = null) {
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
        // Log activity failure silently
        error_log("Failed to log activity: " . $e->getMessage());
        return false;
    }
}

function getRecentActivities($limit = 50, $userId = null) {
    global $pdo;
    
    $userFilter = $userId ? "WHERE al.user_id = ?" : "";
    $params = $userId ? [$userId, $limit] : [$limit];
    
    $stmt = $pdo->prepare("
        SELECT 
            al.*,
            u.name as user_name,
            CASE 
                WHEN al.action = 'task_created' THEN CONCAT(u.name, ' created a new task')
                WHEN al.action = 'task_status_updated' THEN CONCAT(u.name, ' updated task status')
                WHEN al.action = 'task_completed' THEN CONCAT(u.name, ' completed a task')
                WHEN al.action = 'task_approved' THEN CONCAT(u.name, ' approved a task')
                WHEN al.action = 'user_login' THEN CONCAT(u.name, ' logged in')
                WHEN al.action = 'password_changed' THEN CONCAT(u.name, ' changed password')
                ELSE CONCAT(u.name, ' performed ', al.action)
            END as description
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        {$userFilter}
        ORDER BY al.timestamp DESC
        LIMIT ?
    ");
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * Security Functions
 */
function isAccountLocked($email) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT failed_attempts, locked_until
        FROM users 
        WHERE email = ? AND is_active = TRUE
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) return false;
    
    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
        return true;
    }
    
    return false;
}

function recordFailedLogin($email) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE users 
        SET failed_attempts = failed_attempts + 1,
            locked_until = CASE 
                WHEN failed_attempts >= 4 THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                ELSE locked_until
            END
        WHERE email = ?
    ");
    
    return $stmt->execute([$email]);
}

function clearFailedLogins($email) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE users 
        SET failed_attempts = 0, locked_until = NULL
        WHERE email = ?
    ");
    
    return $stmt->execute([$email]);
}

function logLoginAttempt($userId, $status, $ipAddress = null, $userAgent = null, $failureReason = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO login_logs (user_id, login_status, ip_address, user_agent, failure_reason)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    return $stmt->execute([
        $userId,
        $status,
        $ipAddress ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        $failureReason
    ]);
}

/**
 * Utility Functions
 */
function timeAgo($datetime) {
    if (!$datetime) return 'Never';
    
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    if ($time < 2592000) return floor($time/86400) . 'd ago';
    if ($time < 31536000) return floor($time/2592000) . ' month' . (floor($time/2592000) > 1 ? 's' : '') . ' ago';
    
    return floor($time/31536000) . ' year' . (floor($time/31536000) > 1 ? 's' : '') . ' ago';
}

function formatDuration($hours) {
    if (!$hours) return 'N/A';
    
    if ($hours < 1) {
        return round($hours * 60) . 'm';
    } elseif ($hours < 24) {
        return round($hours, 1) . 'h';
    } else {
        $days = floor($hours / 24);
        $remainingHours = $hours % 24;
        return $days . 'd ' . round($remainingHours, 1) . 'h';
    }
}

function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function getSystemSetting($key, $default = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT setting_value, setting_type FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $setting = $stmt->fetch();
    
    if (!$setting) return $default;
    
    switch ($setting['setting_type']) {
        case 'boolean':
            return filter_var($setting['setting_value'], FILTER_VALIDATE_BOOLEAN);
        case 'integer':
            return (int)$setting['setting_value'];
        case 'json':
            return json_decode($setting['setting_value'], true);
        default:
            return $setting['setting_value'];
    }
}

function updateSystemSetting($key, $value, $type = 'string', $userId = null) {
    global $pdo;
    
    if ($type === 'json') {
        $value = json_encode($value);
    } elseif ($type === 'boolean') {
        $value = $value ? 'true' : 'false';
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value, setting_type, updated_by)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        setting_value = VALUES(setting_value),
        setting_type = VALUES(setting_type),
        updated_by = VALUES(updated_by),
        updated_at = NOW()
    ");
    
    return $stmt->execute([$key, $value, $type, $userId]);
}

/**
 * Export Functions
 */
function exportTasksToCSV($filters = []) {
    global $pdo;
    
    $sql = "
        SELECT 
            t.id,
            t.title,
            t.details,
            t.date,
            t.status,
            t.priority,
            t.estimated_hours,
            t.actual_hours,
            u.name as assigned_to,
            c.name as created_by,
            t.created_at,
            t.updated_at
        FROM tasks t
        LEFT JOIN users u ON t.assigned_to = u.id
        LEFT JOIN users c ON t.created_by = c.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if (!empty($filters['date_from'])) {
        $sql .= " AND t.date >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $sql .= " AND t.date <= ?";
        $params[] = $filters['date_to'];
    }
    
    if (!empty($filters['status'])) {
        $sql .= " AND t.status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['assigned_to'])) {
        $sql .= " AND t.assigned_to = ?";
        $params[] = $filters['assigned_to'];
    }
    
    $sql .= " ORDER BY t.date DESC, t.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * Template Functions
 */
function getTaskTemplates($userId = null) {
    global $pdo;
    
    $userFilter = $userId ? "WHERE created_by = ? AND is_active = TRUE" : "WHERE is_active = TRUE";
    $params = $userId ? [$userId] : [];
    
    $stmt = $pdo->prepare("
        SELECT * FROM task_templates 
        {$userFilter}
        ORDER BY name
    ");
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

function createTaskFromTemplate($templateId, $data) {
    global $pdo;
    
    // Get template
    $stmt = $pdo->prepare("SELECT * FROM task_templates WHERE id = ? AND is_active = TRUE");
    $stmt->execute([$templateId]);
    $template = $stmt->fetch();
    
    if (!$template) {
        throw new Exception('Template not found');
    }
    
    // Replace placeholders in template
    $title = str_replace(['{date}', '{week}'], [date('Y-m-d'), date('W')], $template['title_template']);
    $details = str_replace(['{date}', '{week}'], [date('Y-m-d'), date('W')], $template['details_template']);
    
    // Merge template data with provided data
    $taskData = array_merge([
        'title' => $title,
        'details' => $details,
        'priority' => $template['default_priority'],
        'estimated_hours' => $template['estimated_hours'],
        'tags' => json_decode($template['tags'], true)
    ], $data);
    
    return createTask($taskData);
}

/**
 * Dashboard Functions for Enhanced Mobile UI
 */
function getDashboardData($userId = null) {
    $data = [
        'analytics' => getAnalytics(date('Y-m-d'), $userId),
        'recent_tasks' => getTasks($userId, null, null, 10),
        'notifications' => getUserNotifications($userId ?: $_SESSION['user_id'], true, 5),
        'productivity' => getProductivityMetrics($userId, 7)
    ];
    
    if (isAdmin() && !$userId) {
        $data['team_stats'] = getAllUsers();
        $data['recent_activities'] = getRecentActivities(10);
    }
    
    return $data;
}

/**
 * Enhanced Login Function
 */
function secureLogin($email, $password) {
    global $pdo;
    
    // Check if account is locked
    if (isAccountLocked($email)) {
        return [
            'success' => false, 
            'message' => 'Account is temporarily locked due to too many failed attempts. Please try again later.'
        ];
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = TRUE");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        // Clear failed login attempts
        clearFailedLogins($email);
        
        // Check if user needs to change password
        if ($user['force_password_change']) {
            $_SESSION['temp_user_id'] = $user['id'];
            $_SESSION['must_change_password'] = true;
            return [
                'success' => false, 
                'message' => 'You must change your password before continuing.',
                'redirect' => 'change-password.php'
            ];
        }
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['last_activity'] = time();
        
        // Update last login
        updateUserLastLogin($user['id']);
        
        // Log successful login
        logLoginAttempt($user['id'], 'success');
        logActivity($user['id'], 'user_login');
        
        return [
            'success' => true,
            'redirect' => $user['role'] === 'admin' ? 'admin-dashboard.php' : 'index.php'
        ];
    } else {
        // Record failed attempt
        if ($user) {
            recordFailedLogin($email);
            logLoginAttempt($user['id'], 'failed', null, null, 'Invalid password');
        }
        
        return [
            'success' => false,
            'message' => 'Invalid email or password'
        ];
    }
}

/**
 * Enhanced Password Functions
 */
function generateRandomPassword($length = 12) {
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $numbers = '0123456789';
    $symbols = '!@#$%^&*';
    
    $password = '';
    $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
    $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
    $password .= $numbers[random_int(0, strlen($numbers) - 1)];
    $password .= $symbols[random_int(0, strlen($symbols) - 1)];
    
    $allChars = $uppercase . $lowercase . $numbers . $symbols;
    for ($i = 4; $i < $length; $i++) {
        $password .= $allChars[random_int(0, strlen($allChars) - 1)];
    }
    
    return str_shuffle($password);
}

function calculatePasswordStrength($password) {
    $strength = 0;
    
    if (strlen($password) >= 6) $strength++;
    if (strlen($password) >= 8) $strength++;
    if (strlen($password) >= 12) $strength++;
    if (preg_match('/[a-z]/', $password)) $strength++;
    if (preg_match('/[A-Z]/', $password)) $strength++;
    if (preg_match('/[0-9]/', $password)) $strength++;
    if (preg_match('/[^A-Za-z0-9]/', $password)) $strength++;
    
    return min($strength, 5);
}

function logPasswordChange($userId, $changeType = 'self', $changedBy = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO password_logs (user_id, change_type, changed_by, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    return $stmt->execute([
        $userId,
        $changeType,
        $changedBy,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
}

/**
 * Helper function to check if current user is admin
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}
?>