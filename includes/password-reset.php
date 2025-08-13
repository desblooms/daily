<?php
// includes/password-reset.php
require_once 'db.php';
require_once 'functions.php';

/**
 * Initiate forgot password process
 */
function initiateForgotPassword($email) {
    global $pdo;
    
    try {
        // Rate limiting check
        if (isRateLimited($email)) {
            return [
                'success' => false,
                'message' => 'Too many password reset requests. Please wait before trying again.'
            ];
        }
        
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE email = ? AND is_active = TRUE");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        // Always return success message for security (don't reveal if email exists)
        if (!$user) {
            // Still log the attempt for security monitoring
            logPasswordResetAttempt($email, 'user_not_found');
            return [
                'success' => true,
                'message' => 'If an account with that email exists, you will receive reset instructions.'
            ];
        }
        
        // Generate secure token using function from functions.php
        $token = generateSecureToken(32);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $pdo->beginTransaction();
        
        // Invalidate any existing tokens for this email
        $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used = TRUE WHERE email = ? AND used = FALSE");
        $stmt->execute([$email]);
        
        // Create new reset token
        $stmt = $pdo->prepare("
            INSERT INTO password_reset_tokens (email, token, expires_at, ip_address) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$email, $token, $expiresAt, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        
        // Log the attempt
        logPasswordResetAttempt($email, 'token_generated');
        
        // Send email (implement your email sending logic here)
        $emailSent = sendPasswordResetEmail($email, $user['name'], $token);
        
        if ($emailSent) {
            $pdo->commit();
            return [
                'success' => true,
                'message' => 'Password reset instructions have been sent to your email.'
            ];
        } else {
            $pdo->rollback();
            return [
                'success' => false,
                'message' => 'Failed to send reset email. Please try again later.'
            ];
        }
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        
        error_log("Password reset error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'An error occurred. Please try again later.'
        ];
    }
}

/**
 * Validate reset token
 */
function validateResetToken($token) {
    global $pdo;
    
    if (empty($token)) {
        return [
            'valid' => false,
            'message' => 'Invalid reset token.'
        ];
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT email, expires_at, used 
            FROM password_reset_tokens 
            WHERE token = ?
        ");
        $stmt->execute([$token]);
        $tokenData = $stmt->fetch();
        
        if (!$tokenData) {
            return [
                'valid' => false,
                'message' => 'Invalid or expired reset token.'
            ];
        }
        
        if ($tokenData['used']) {
            return [
                'valid' => false,
                'message' => 'This reset link has already been used.'
            ];
        }
        
        if (strtotime($tokenData['expires_at']) < time()) {
            return [
                'valid' => false,
                'message' => 'This reset link has expired. Please request a new one.'
            ];
        }
        
        return [
            'valid' => true,
            'email' => $tokenData['email']
        ];
        
    } catch (Exception $e) {
        error_log("Token validation error: " . $e->getMessage());
        return [
            'valid' => false,
            'message' => 'An error occurred validating the reset token.'
        ];
    }
}

/**
 * Reset password using token
 */
function resetPassword($token, $newPassword) {
    global $pdo;
    
    try {
        // Validate token first
        $tokenValidation = validateResetToken($token);
        if (!$tokenValidation['valid']) {
            return $tokenValidation;
        }
        
        $email = $tokenValidation['email'];
        
        // Get user information
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? AND is_active = TRUE");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return [
                'success' => false,
                'message' => 'User account not found.'
            ];
        }
        
        $pdo->beginTransaction();
        
        // Hash the new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update user password
        $stmt = $pdo->prepare("
            UPDATE users 
            SET password = ?, 
                updated_at = NOW(),
                failed_attempts = 0,
                locked_until = NULL,
                force_password_change = FALSE
            WHERE id = ?
        ");
        $stmt->execute([$hashedPassword, $user['id']]);
        
        // Mark token as used
        $stmt = $pdo->prepare("
            UPDATE password_reset_tokens 
            SET used = TRUE, used_at = NOW() 
            WHERE token = ?
        ");
        $stmt->execute([$token]);
        
        // Log password change using function from functions.php
        logPasswordChange($user['id'], 'password_reset');
        
        // Log activity using function from functions.php
        logActivity($user['id'], 'password_reset_completed');
        
        // Send security alert
        sendSecurityAlert($email, $user['name'], 'password_reset');
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Your password has been reset successfully.'
        ];
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        
        error_log("Password reset error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'An error occurred resetting your password. Please try again.'
        ];
    }
}

/**
 * Check rate limiting for password reset requests
 */
function isRateLimited($email) {
    global $pdo;
    
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $timeLimit = date('Y-m-d H:i:s', strtotime('-15 minutes'));
    
    // Check attempts by email (max 3 per 15 minutes)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM password_reset_attempts 
        WHERE email = ? AND attempt_time > ?
    ");
    $stmt->execute([$email, $timeLimit]);
    $emailAttempts = $stmt->fetchColumn();
    
    // Check attempts by IP (max 5 per 15 minutes)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM password_reset_attempts 
        WHERE ip_address = ? AND attempt_time > ?
    ");
    $stmt->execute([$ipAddress, $timeLimit]);
    $ipAttempts = $stmt->fetchColumn();
    
    return ($emailAttempts >= 3 || $ipAttempts >= 5);
}

/**
 * Log password reset attempt
 */
function logPasswordResetAttempt($email, $result) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO password_reset_attempts (email, ip_address, attempt_time) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$email, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        
        // Also log to activity logs if user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            logActivity($user['id'], 'password_reset_requested', null, null, ['result' => $result]);
        }
        
    } catch (Exception $e) {
        error_log("Failed to log password reset attempt: " . $e->getMessage());
    }
}

/**
 * Send password reset email
 * Note: This is a simplified version. In production, use a proper email service.
 */
function sendPasswordResetEmail($email, $name, $token) {
    // Get site settings using function from functions.php
    $siteName = getSystemSetting('site_name', 'Daily Calendar');
    $siteUrl = getSystemSetting('site_url', 'https://daily.desblooms.com/');
    
    $resetUrl = $siteUrl . '/reset-password.php?token=' . $token;
    
    $subject = "Password Reset Request - $siteName";
    
    $message = "
    <html>
    <head>
        <title>Password Reset Request</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
            .button { display: inline-block; background: #3B82F6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
            .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
            .warning { background: #FEF3C7; border-left: 4px solid #F59E0B; padding: 12px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>$siteName</h1>
                <p>Password Reset Request</p>
            </div>
            <div class='content'>
                <h2>Hello " . htmlspecialchars($name) . ",</h2>
                <p>We received a request to reset the password for your account. If you made this request, click the button below to reset your password:</p>
                
                <p style='text-align: center;'>
                    <a href='$resetUrl' class='button'>Reset My Password</a>
                </p>
                
                <p>Or copy and paste this link into your browser:</p>
                <p style='word-break: break-all; background: #eee; padding: 10px; border-radius: 4px;'>$resetUrl</p>
                
                <div class='warning'>
                    <strong>Important:</strong> This link will expire in 1 hour for security reasons.
                </div>
                
                <p>If you didn't request a password reset, you can safely ignore this email. Your password will remain unchanged.</p>
                
                <p>For security reasons, this link can only be used once. If you need another reset link, please visit the forgot password page again.</p>
                
                <p>Best regards,<br>The $siteName Team</p>
            </div>
            <div class='footer'>
                <p>This is an automated message, please do not reply to this email.</p>
                <p>&copy; " . date('Y') . " $siteName. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = array(
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: noreply@desblooms.com',
        'Reply-To: noreply@desblooms.com',
        'X-Mailer: PHP/' . phpversion()
    );
    
    // In production, replace this with a proper email service like SendGrid, AWS SES, etc.
    return mail($email, $subject, $message, implode("\r\n", $headers));
}

/**
 * Clean up expired tokens (call this periodically)
 */
function cleanupExpiredTokens() {
    global $pdo;
    
    try {
        // Delete tokens older than 24 hours
        $stmt = $pdo->prepare("
            DELETE FROM password_reset_tokens 
            WHERE expires_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $deleted = $stmt->execute();
        
        // Clean up old reset attempts (older than 7 days)
        $stmt = $pdo->prepare("
            DELETE FROM password_reset_attempts 
            WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->execute();
        
        return $stmt->rowCount();
        
    } catch (Exception $e) {
        error_log("Token cleanup error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get password reset statistics (for admin)
 */
function getPasswordResetStats($days = 30) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                DATE(attempt_time) as date,
                COUNT(*) as attempts
            FROM password_reset_attempts 
            WHERE attempt_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(attempt_time)
            ORDER BY date DESC
        ");
        $stmt->execute([$days]);
        $attempts = $stmt->fetchAll();
        
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_tokens,
                SUM(CASE WHEN used = TRUE THEN 1 ELSE 0 END) as used_tokens,
                SUM(CASE WHEN expires_at < NOW() THEN 1 ELSE 0 END) as expired_tokens
            FROM password_reset_tokens 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);
        $tokens = $stmt->fetch();
        
        return [
            'attempts_by_date' => $attempts,
            'token_stats' => $tokens
        ];
        
    } catch (Exception $e) {
        error_log("Password reset stats error: " . $e->getMessage());
        return null;
    }
}

/**
 * Validate password strength - specific to reset functionality
 */
function validatePasswordStrengthForReset($password, $minLength = 6) {
    $errors = [];
    
    if (strlen($password) < $minLength) {
        $errors[] = "Password must be at least {$minLength} characters long";
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
    
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }
    
    // Check for common weak passwords
    $commonPasswords = [
        'password', '123456', 'password123', 'admin', 'qwerty',
        'letmein', 'welcome', 'monkey', '1234567890'
    ];
    
    if (in_array(strtolower($password), $commonPasswords)) {
        $errors[] = "Password is too common. Please choose a more unique password";
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'strength' => calculatePasswordStrength($password)
    ];
}

/**
 * Send security alert email
 */
function sendSecurityAlert($email, $name, $alertType, $details = []) {
    $siteName = getSystemSetting('site_name', 'Daily Calendar');
    
    $alertMessages = [
        'password_reset' => 'Your password has been reset',
        'suspicious_login' => 'Suspicious login attempt detected',
        'account_locked' => 'Your account has been temporarily locked',
        'password_changed' => 'Your password has been changed'
    ];
    
    $subject = "Security Alert - " . ($alertMessages[$alertType] ?? 'Security Event') . " - $siteName";
    
    $message = "
    <html>
    <head>
        <title>Security Alert</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #DC2626; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
            .alert { background: #FEE2E2; border-left: 4px solid #DC2626; padding: 12px; margin: 20px 0; }
            .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🔒 Security Alert</h1>
                <p>$siteName Account Security</p>
            </div>
            <div class='content'>
                <h2>Hello " . htmlspecialchars($name) . ",</h2>
                
                <div class='alert'>
                    <strong>Security Event:</strong> " . ($alertMessages[$alertType] ?? 'Security event detected') . "
                </div>
                
                <p><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p>
                <p><strong>IP Address:</strong> " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "</p>
                
                " . (!empty($details) ? "<p><strong>Details:</strong> " . htmlspecialchars(json_encode($details)) . "</p>" : "") . "
                
                <p>If this was you, no action is required. If you did not perform this action, please:</p>
                <ul>
                    <li>Change your password immediately</li>
                    <li>Review your account activity</li>
                    <li>Contact support if you notice any suspicious activity</li>
                </ul>
                
                <p>Best regards,<br>The $siteName Security Team</p>
            </div>
            <div class='footer'>
                <p>This is an automated security alert. Please do not reply to this email.</p>
                <p>&copy; " . date('Y') . " $siteName. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = array(
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: security@desblooms.com',
        'Reply-To: security@desblooms.com',
        'X-Mailer: PHP/' . phpversion(),
        'X-Priority: 1'
    );
    
    return mail($email, $subject, $message, implode("\r\n", $headers));
}

/**
 * Get system security settings
 */
function getSecuritySettings() {
    return [
        'password_min_length' => getSystemSetting('password_min_length', 6),
        'password_require_uppercase' => getSystemSetting('password_require_uppercase', true),
        'password_require_lowercase' => getSystemSetting('password_require_lowercase', true),
        'password_require_numbers' => getSystemSetting('password_require_numbers', true),
        'password_require_symbols' => getSystemSetting('password_require_symbols', false),
        'password_expiry_days' => getSystemSetting('password_expiry_days', 90),
        'max_login_attempts' => getSystemSetting('max_login_attempts', 5),
        'lockout_duration' => getSystemSetting('lockout_duration', 900), // 15 minutes
        'password_reset_rate_limit' => getSystemSetting('password_reset_rate_limit', 3),
        'session_timeout' => getSystemSetting('session_timeout', 3600) // 1 hour
    ];
}

/**
 * Update system security settings
 */
function updateSecuritySettings($settings, $adminUserId) {
    $allowedSettings = [
        'password_min_length', 'password_require_uppercase', 'password_require_lowercase',
        'password_require_numbers', 'password_require_symbols', 'password_expiry_days',
        'max_login_attempts', 'lockout_duration', 'password_reset_rate_limit', 'session_timeout'
    ];
    
    $updated = 0;
    foreach ($settings as $key => $value) {
        if (in_array($key, $allowedSettings)) {
            $type = is_bool($value) ? 'boolean' : (is_numeric($value) ? 'integer' : 'string');
            if (updateSystemSetting($key, $value, $type, $adminUserId)) {
                $updated++;
            }
        }
    }
    
    if ($updated > 0) {
        logActivity($adminUserId, 'security_settings_updated', null, null, $settings);
    }
    
    return $updated;
}
?>