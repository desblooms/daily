<?php
require_once 'db.php';

function getTasks($userId = null, $date = null) {
    global $pdo;
    
    $sql = "SELECT t.*, u.name as assigned_name FROM tasks t 
            LEFT JOIN users u ON t.assigned_to = u.id WHERE 1=1";
    $params = [];
    
    if ($userId) {
        $sql .= " AND t.assigned_to = ?";
        $params[] = $userId;
    }
    
    if ($date) {
        $sql .= " AND t.date = ?";
        $params[] = $date;
    }
    
    $sql .= " ORDER BY t.date DESC, t.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function updateTaskStatus($taskId, $status, $userId) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Update task status
        $stmt = $pdo->prepare("UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $taskId]);
        
        // Log status change
        $stmt = $pdo->prepare("INSERT INTO status_logs (task_id, status, updated_by) VALUES (?, ?, ?)");
        $stmt->execute([$taskId, $status, $userId]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollback();
        return false;
    }
}

function getAnalytics($date = null) {
    global $pdo;
    
    $dateFilter = $date ? "AND date = '$date'" : "AND date = CURDATE()";
    
    $stmt = $pdo->prepare("
        SELECT 
            status,
            COUNT(*) as count
        FROM tasks 
        WHERE 1=1 $dateFilter
        GROUP BY status
    ");
    $stmt->execute();
    
    $results = $stmt->fetchAll();
    $analytics = [
        'Pending' => 0,
        'On Progress' => 0,
        'Done' => 0,
        'Approved' => 0,
        'On Hold' => 0
    ];
    
    foreach ($results as $row) {
        $analytics[$row['status']] = $row['count'];
    }
    
    return $analytics;
}

function createTask($title, $details, $assignedTo, $date, $createdBy) {
    global $pdo;
    
    $stmt = $pdo->prepare("INSERT INTO tasks (title, details, assigned_to, date, created_by) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$title, $details, $assignedTo, $date, $createdBy]);
}
?>


<?php
// Add these functions to your existing includes/functions.php file

/**
 * Generate a secure random password
 */
function generateRandomPassword($length = 12) {
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $numbers = '0123456789';
    $symbols = '!@#$%^&*';
    
    // Ensure at least one character from each set
    $password = '';
    $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
    $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
    $password .= $numbers[random_int(0, strlen($numbers) - 1)];
    $password .= $symbols[random_int(0, strlen($symbols) - 1)];
    
    // Fill the rest randomly
    $allChars = $uppercase . $lowercase . $numbers . $symbols;
    for ($i = 4; $i < $length; $i++) {
        $password .= $allChars[random_int(0, strlen($allChars) - 1)];
    }
    
    // Shuffle the password
    return str_shuffle($password);
}

/**
 * Calculate password strength (0-5)
 */
function calculatePasswordStrength($password) {
    $strength = 0;
    
    // Length criteria
    if (strlen($password) >= 6) $strength++;
    if (strlen($password) >= 8) $strength++;
    if (strlen($password) >= 12) $strength++;
    
    // Character variety
    if (preg_match('/[a-z]/', $password)) $strength++;
    if (preg_match('/[A-Z]/', $password)) $strength++;
    if (preg_match('/[0-9]/', $password)) $strength++;
    if (preg_match('/[^A-Za-z0-9]/', $password)) $strength++;
    
    return min($strength, 5);
}

/**
 * Check if password meets minimum requirements
 */
function validatePassword($password) {
    $errors = [];
    
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    
    return $errors;
}

/**
 * Check if password was used recently
 */
function isPasswordRecentlyUsed($userId, $newPassword, $historyLimit = 5) {
    global $pdo;
    
    // Get recent password hashes (you'd need to store these)
    $stmt = $pdo->prepare("
        SELECT password_hash 
        FROM password_history 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$userId, $historyLimit]);
    $recentPasswords = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($recentPasswords as $hashedPassword) {
        if (password_verify($newPassword, $hashedPassword)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Log password change activity
 */
function logPasswordChange($userId, $ipAddress = null, $userAgent = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO password_logs (user_id, changed_at, ip_address, user_agent) 
        VALUES (?, NOW(), ?, ?)
    ");
    
    return $stmt->execute([
        $userId,
        $ipAddress ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
}

/**
 * Get password statistics for admin dashboard
 */
function getPasswordStatistics() {
    global $pdo;
    
    // Users with weak passwords (last changed > 90 days ago or never)
    $stmt = $pdo->query("
        SELECT COUNT(*) as weak_passwords
        FROM users u
        LEFT JOIN password_logs pl ON u.id = pl.user_id
        WHERE pl.changed_at IS NULL 
        OR pl.changed_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
    ");
    $weakPasswords = $stmt->fetch()['weak_passwords'];
    
    // Recent password changes (last 30 days)
    $stmt = $pdo->query("
        SELECT COUNT(*) as recent_changes
        FROM password_logs
        WHERE changed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $recentChanges = $stmt->fetch()['recent_changes'];
    
    // Users who need to change passwords
    $stmt = $pdo->query("
        SELECT COUNT(*) as forced_changes
        FROM users
        WHERE force_password_change = 1
    ");
    $forcedChanges = $stmt->fetch()['forced_changes'];
    
    return [
        'weak_passwords' => $weakPasswords,
        'recent_changes' => $recentChanges,
        'forced_changes' => $forcedChanges,
        'total_users' => getUserCount()
    ];
}

/**
 * Get total user count
 */
function getUserCount() {
    global $pdo;
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'deleted'");
    return $stmt->fetchColumn();
}

/**
 * Send password reset notification (placeholder for email integration)
 */
function sendPasswordResetNotification($userEmail, $userName, $newPassword) {
    // This is a placeholder - in production, you'd integrate with your email service
    $subject = "Password Reset - Daily Calendar";
    $message = "
        Hello {$userName},
        
        Your password has been reset by an administrator.
        Your new temporary password is: {$newPassword}
        
        Please log in and change your password immediately.
        
        Best regards,
        Daily Calendar Team
    ";
    
    // In production, use mail() or a proper email service
    // return mail($userEmail, $subject, $message);
    
    // For now, just log it
    error_log("Password reset notification for {$userEmail}: {$newPassword}");
    return true;
}

/**
 * Check if user needs to change password
 */
function userNeedsPasswordChange($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT force_password_change FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    return $user && $user['force_password_change'] == 1;
}

/**
 * Clear password change requirement
 */
function clearPasswordChangeRequirement($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("UPDATE users SET force_password_change = 0 WHERE id = ?");
    return $stmt->execute([$userId]);
}

/**
 * Get user's password change history
 */
function getUserPasswordHistory($userId, $limit = 10) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM password_logs 
        WHERE user_id = ? 
        ORDER BY changed_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$userId, $limit]);
    
    return $stmt->fetchAll();
}

/**
 * Format time ago helper
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

/**
 * Check if account is locked due to failed attempts
 */
function isAccountLocked($email) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT failed_attempts, last_failed_attempt, locked_until
        FROM users 
        WHERE email = ?
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) return false;
    
    // Check if account is currently locked
    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
        return true;
    }
    
    // Check if too many failed attempts in last 15 minutes
    if ($user['failed_attempts'] >= 5 && 
        $user['last_failed_attempt'] && 
        strtotime($user['last_failed_attempt']) > (time() - 900)) {
        return true;
    }
    
    return false;
}

/**
 * Record failed login attempt
 */
function recordFailedLogin($email) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE users 
        SET failed_attempts = failed_attempts + 1,
            last_failed_attempt = NOW(),
            locked_until = CASE 
                WHEN failed_attempts >= 4 THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                ELSE locked_until
            END
        WHERE email = ?
    ");
    
    return $stmt->execute([$email]);
}

/**
 * Clear failed login attempts on successful login
 */
function clearFailedLogins($email) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE users 
        SET failed_attempts = 0,
            last_failed_attempt = NULL,
            locked_until = NULL
        WHERE email = ?
    ");
    
    return $stmt->execute([$email]);
}

/**
 * Enhanced login function with security features
 */
function secureLogin($email, $password) {
    global $pdo;
    
    // Check if account is locked
    if (isAccountLocked($email)) {
        return ['success' => false, 'message' => 'Account is temporarily locked due to too many failed attempts'];
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        // Clear any failed login attempts
        clearFailedLogins($email);
        
        // Check if user needs to change password
        if ($user['force_password_change'] == 1) {
            $_SESSION['temp_user_id'] = $user['id'];
            $_SESSION['must_change_password'] = true;
            return ['success' => false, 'message' => 'You must change your password', 'redirect' => 'change-password.php'];
        }
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        
        // Log successful login
        $stmt = $pdo->prepare("
            INSERT INTO login_logs (user_id, login_time, ip_address, user_agent) 
            VALUES (?, NOW(), ?, ?)
        ");
        $stmt->execute([
            $user['id'],
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        return ['success' => true, 'redirect' => $user['role'] === 'admin' ? 'admin-dashboard.php' : 'index.php'];
    } else {
        // Record failed attempt if user exists
        if ($user) {
            recordFailedLogin($email);
        }
        
        return ['success' => false, 'message' => 'Invalid email or password'];
    }
}

?>