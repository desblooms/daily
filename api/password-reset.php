<?php
// api/password-reset.php
require_once '../includes/db.php';
require_once '../includes/password-reset.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'forgot_password':
            handleForgotPassword($input);
            break;
            
        case 'reset_password':
            handleResetPassword($input);
            break;
            
        case 'validate_token':
            handleValidateToken($input);
            break;
            
        case 'check_password_strength':
            handleCheckPasswordStrength($input);
            break;
            
        case 'cleanup_tokens':
            // Admin only
            if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                handleCleanupTokens();
            } else {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Password reset API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

function handleForgotPassword($input) {
    $email = trim($input['email'] ?? '');
    
    // Validation
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Email is required']);
        return;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        return;
    }
    
    // Initiate password reset
    $result = initiateForgotPassword($email);
    echo json_encode($result);
}

function handleResetPassword($input) {
    $token = trim($input['token'] ?? '');
    $newPassword = $input['new_password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';
    
    // Validation
    if (empty($token)) {
        echo json_encode(['success' => false, 'message' => 'Reset token is required']);
        return;
    }
    
    if (empty($newPassword) || empty($confirmPassword)) {
        echo json_encode(['success' => false, 'message' => 'Both password fields are required']);
        return;
    }
    
    if ($newPassword !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        return;
    }
    
    // Check password strength
    $passwordValidation = validatePasswordStrengthForReset($newPassword);
    if (!$passwordValidation['valid']) {
        echo json_encode([
            'success' => false, 
            'message' => 'Password does not meet requirements',
            'errors' => $passwordValidation['errors']
        ]);
        return;
    }
    
    // Reset password
    $result = resetPassword($token, $newPassword);
    echo json_encode($result);
}

function handleValidateToken($input) {
    $token = trim($input['token'] ?? '');
    
    if (empty($token)) {
        echo json_encode(['valid' => false, 'message' => 'Token is required']);
        return;
    }
    
    $result = validateResetToken($token);
    echo json_encode($result);
}

function handleCheckPasswordStrength($input) {
    $password = $input['password'] ?? '';
    
    if (empty($password)) {
        echo json_encode([
            'success' => false,
            'message' => 'Password is required'
        ]);
        return;
    }
    
    $validation = validatePasswordStrengthForReset($password);
    $strength = calculatePasswordStrength($password);
    
    $strengthLabels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
    $strengthColors = ['red', 'orange', 'yellow', 'blue', 'green', 'darkgreen'];
    
    echo json_encode([
        'success' => true,
        'valid' => $validation['valid'],
        'errors' => $validation['errors'],
        'strength' => [
            'score' => $strength,
            'label' => $strengthLabels[$strength] ?? 'Very Weak',
            'color' => $strengthColors[$strength] ?? 'red',
            'percentage' => ($strength / 5) * 100
        ]
    ]);
}

function handleCleanupTokens() {
    $deleted = cleanupExpiredTokens();
    
    if ($deleted !== false) {
        echo json_encode([
            'success' => true,
            'message' => "Cleaned up expired tokens",
            'deleted_count' => $deleted
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to cleanup tokens'
        ]);
    }
}

// calculatePasswordStrength function is in includes/functions.php
?>