<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');
requireLogin();

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

switch ($action) {
    case 'change_password':
        changePassword($input);
        break;
        
    case 'check_password_strength':
        checkPasswordStrength($input);
        break;
        
    case 'get_password_history':
        if (isAdmin()) {
            getPasswordHistory($input);
        } else {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function changePassword($input) {
    global $pdo;
    
    $currentPassword = $input['current_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';
    
    // Validation
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }
    
    if ($newPassword !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
        return;
    }
    
    if (strlen($newPassword) < 6) {
        echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters']);
        return;
    }
    
    // Check password strength
    $strength = calculatePasswordStrength($newPassword);
    if ($strength < 2) {
        echo json_encode(['success' => false, 'message' => 'Password is too weak. Please use a stronger password.']);
        return;
    }
    
    try {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($currentPassword, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
            return;
        }
        
        // Check if new password is same as current
        if (password_verify($newPassword, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'New password must be different from current password']);
            return;
        }
        
        $pdo->beginTransaction();
        
        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$hashedPassword, $_SESSION['user_id']]);
        
        // Log password change
        $stmt = $pdo->prepare("INSERT INTO password_logs (user_id, changed_at, ip_address, user_agent) VALUES (?, NOW(), ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'], 
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Password changed successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to update password']);
    }
}

function checkPasswordStrength($input) {
    $password = $input['password'] ?? '';
    $strength = calculatePasswordStrength($password);
    
    $labels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
    $colors = ['red', 'orange', 'yellow', 'blue', 'green', 'green'];
    
    echo json_encode([
        'success' => true,
        'strength' => $strength,
        'label' => $labels[$strength] ?? 'Very Weak',
        'color' => $colors[$strength] ?? 'red',
        'percentage' => ($strength / 5) * 100
    ]);
}

function calculatePasswordStrength($password) {
    $strength = 0;
    
    if (strlen($password) >= 6) $strength++;
    if (strlen($password) >= 8) $strength++;
    if (preg_match('/[a-z]/', $password)) $strength++;
    if (preg_match('/[A-Z]/', $password)) $strength++;
    if (preg_match('/[0-9]/', $password)) $strength++;
    if (preg_match('/[^A-Za-z0-9]/', $password)) $strength++;
    
    return min($strength, 5);
}

function getPasswordHistory($input) {
    global $pdo;
    
    $userId = $input['user_id'] ?? null;
    $limit = min($input['limit'] ?? 10, 50); // Max 50 records
    
    $sql = "SELECT pl.*, u.name, u.email 
            FROM password_logs pl 
            JOIN users u ON pl.user_id = u.id";
    $params = [];
    
    if ($userId) {
        $sql .= " WHERE pl.user_id = ?";
        $params[] = $userId;
    }
    
    $sql .= " ORDER BY pl.changed_at DESC LIMIT ?";
    $params[] = $limit;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'logs' => $logs
    ]);
}
?>