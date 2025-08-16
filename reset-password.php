<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/password-reset.php';

$token = $_GET['token'] ?? '';
$message = '';
$messageType = '';
$showForm = false;
$tokenValid = false;

// Validate token on page load
if ($token) {
    $tokenInfo = validateResetToken($token);
    if ($tokenInfo['valid']) {
        $showForm = true;
        $tokenValid = true;
    } else {
        $message = $tokenInfo['message'];
        $messageType = 'error';
    }
} else {
    $message = "Invalid or missing reset token. Please request a new password reset.";
    $messageType = 'error';
}

// Handle password reset form submission
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'reset_password' && $tokenValid) {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($newPassword) || empty($confirmPassword)) {
        $message = "All fields are required";
        $messageType = 'error';
    } elseif ($newPassword !== $confirmPassword) {
        $message = "Passwords do not match";
        $messageType = 'error';
    } elseif (strlen($newPassword) < 6) {
        $message = "Password must be at least 6 characters long";
        $messageType = 'error';
    } else {
        // Check password strength
        $strength = calculatePasswordStrength($newPassword);
        if ($strength < 2) {
            $message = "Password is too weak. Please use a stronger password with a mix of letters, numbers, and symbols.";
            $messageType = 'error';
        } else {
            $result = resetPassword($token, $newPassword);
            
            if ($result['success']) {
                $message = "Your password has been reset successfully! You can now log in with your new password.";
                $messageType = 'success';
                $showForm = false;
            } else {
                $message = $result['message'];
                $messageType = 'error';
            }
        }
    }
}

// If already logged in, redirect
if (isLoggedIn()) {
    $redirect = $_SESSION['role'] === 'admin' ? 'admin-dashboard.php' : 'index.php';
    header("Location: $redirect");
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Daily Calendar</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3B82F6',
                        secondary: '#6366F1',
                        accent: '#7B68EE',
                    }
                }
            }
        }
    </script>
    <style>
        .reset-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .glass-effect {
            backdrop-filter: blur(16px);
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        .floating-animation {
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        .slide-in {
            animation: slideIn 0.5s ease-out;
        }
        @keyframes slideIn {
            from { transform: translateY(100px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .input-focus:focus {
            transform: translateY(-2px);
            transition: all 0.2s ease;
        }
    </style>
</head>
<body class="reset-bg flex items-center justify-center p-4">
    <!-- Background decorative elements -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-1/4 left-1/4 w-64 h-64 bg-white opacity-5 rounded-full floating-animation"></div>
        <div class="absolute bottom-1/4 right-1/4 w-48 h-48 bg-white opacity-5 rounded-full floating-animation" style="animation-delay: -3s;"></div>
        <div class="absolute top-3/4 left-1/2 w-32 h-32 bg-white opacity-5 rounded-full floating-animation" style="animation-delay: -1.5s;"></div>
    </div>

    <div class="relative z-10 w-full max-w-md">
        <!-- Reset Password Card -->
        <div class="glass-effect rounded-3xl p-8 slide-in">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="w-20 h-20 bg-gradient-to-r from-green-500 to-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-800 mb-2">Reset Your Password</h1>
                <p class="text-gray-600">Enter your new password below to regain access to your account.</p>
            </div>

            <!-- Back to Login Link -->
            <div class="mb-6">
                <a href="login.php" class="inline-flex items-center text-sm text-blue-600 hover:text-blue-800 transition-colors">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Back to Login
                </a>
            </div>
            
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="<?= $messageType === 'error' ? 'bg-red-50 border-l-4 border-red-400 text-red-700' : 'bg-green-50 border-l-4 border-green-400 text-green-700' ?> px-4 py-3 rounded-lg text-sm mb-6 flex items-center gap-3">
                    <?php if ($messageType === 'error'): ?>
                        <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                    <?php else: ?>
                        <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                    <?php endif; ?>
                    <span><?= htmlspecialchars($message) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($showForm): ?>
                <!-- Reset Password Form -->
                <form method="POST" class="space-y-6" id="resetPasswordForm">
                    <input type="hidden" name="action" value="reset_password">
                    
                    <div class="space-y-2">
                        <label for="new_password" class="block text-sm font-semibold text-gray-700">New Password</label>
                        <div class="relative">
                            <input type="password" 
                                   name="new_password" 
                                   id="new_password"
                                   required 
                                   minlength="6"
                                   class="input-focus w-full px-4 py-3 pl-12 pr-12 text-sm border-2 border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200"
                                   placeholder="Enter new password">
                            <svg class="w-5 h-5 text-gray-400 absolute left-4 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                            <button type="button" 
                                    onclick="togglePassword('new_password')" 
                                    class="absolute right-4 top-3.5 text-gray-400 hover:text-gray-600 transition-colors">
                                <svg id="eye1Open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                                <svg id="eye1Closed" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L12 12m-3.122-3.122L12 12m0 0l3.878 3.878M12 12l3.878-3.878m-7.756-.001l3.878 3.879"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="mt-1">
                            <div class="flex items-center gap-1">
                                <div id="strengthBar" class="flex-1 h-1 bg-gray-200 rounded-full overflow-hidden">
                                    <div id="strengthFill" class="h-full transition-all duration-300"></div>
                                </div>
                                <span id="strengthText" class="text-xs text-gray-500">Weak</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Minimum 6 characters, include letters, numbers, and symbols</p>
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="confirm_password" class="block text-sm font-semibold text-gray-700">Confirm New Password</label>
                        <div class="relative">
                            <input type="password" 
                                   name="confirm_password" 
                                   id="confirm_password"
                                   required 
                                   class="input-focus w-full px-4 py-3 pl-12 pr-12 text-sm border-2 border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200"
                                   placeholder="Confirm new password">
                            <svg class="w-5 h-5 text-gray-400 absolute left-4 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                            <button type="button" 
                                    onclick="togglePassword('confirm_password')" 
                                    class="absolute right-4 top-3.5 text-gray-400 hover:text-gray-600 transition-colors">
                                <svg id="eye2Open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                                <svg id="eye2Closed" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L12 12m-3.122-3.122L12 12m0 0l3.878 3.878M12 12l3.878-3.878m-7.756-.001l3.878 3.879"></path>
                                </svg>
                            </button>
                        </div>
                        <div id="passwordMatch" class="text-xs mt-1 hidden"></div>
                    </div>
                    
                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-green-600 to-blue-600 hover:from-green-700 hover:to-blue-700 text-white font-semibold py-3 px-4 rounded-xl transition-all duration-200 focus:outline-none focus:ring-4 focus:ring-green-300 disabled:opacity-50 disabled:cursor-not-allowed shadow-lg"
                            id="submitButton">
                        <span id="submitText" class="flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                            Reset Password
                        </span>
                        <span id="submitSpinner" class="hidden">
                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Resetting...
                        </span>
                    </button>
                </form>
            <?php else: ?>
                <!-- Success or Error state -->
                <div class="text-center">
                    <?php if ($messageType === 'success'): ?>
                        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Password Reset Successful!</h3>
                        <p class="text-sm text-gray-600 mb-6">Your password has been updated. You can now log in with your new password.</p>
                        <a href="login.php" class="inline-flex items-center px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors text-sm font-medium">
                            Go to Login
                        </a>
                    <?php else: ?>
                        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Reset Link Invalid</h3>
                        <p class="text-sm text-gray-600 mb-6">This password reset link has expired or is invalid. Please request a new one.</p>
                        <a href="forgot-password.php" class="inline-flex items-center px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors text-sm font-medium">
                            Request New Reset
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Security Notice -->
            <div class="mt-8 pt-6 border-t border-gray-200">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                    </svg>
                    <div>
                        <p class="text-sm font-semibold text-blue-800">Security Tip</p>
                        <p class="text-xs text-blue-700 mt-1">Choose a strong password with a mix of uppercase, lowercase, numbers, and symbols. Don't reuse passwords from other accounts.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-6">
            <p class="text-white text-sm opacity-80">
                © <?= date('Y') ?> Daily Calendar. All rights reserved.
            </p>
        </div>
    </div>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const eyeOpen = document.getElementById(inputId === 'new_password' ? 'eye1Open' : 'eye2Open');
            const eyeClosed = document.getElementById(inputId === 'new_password' ? 'eye1Closed' : 'eye2Closed');
            
            if (input.type === 'password') {
                input.type = 'text';
                eyeOpen.classList.add('hidden');
                eyeClosed.classList.remove('hidden');
            } else {
                input.type = 'password';
                eyeOpen.classList.remove('hidden');
                eyeClosed.classList.add('hidden');
            }
        }

        // Password strength checker
        function checkPasswordStrength(password) {
            let strength = 0;
            let label = '';
            let color = '';

            if (password.length >= 6) strength += 1;
            if (password.length >= 8) strength += 1;
            if (/[a-z]/.test(password)) strength += 1;
            if (/[A-Z]/.test(password)) strength += 1;
            if (/[0-9]/.test(password)) strength += 1;
            if (/[^A-Za-z0-9]/.test(password)) strength += 1;

            switch (strength) {
                case 0:
                case 1:
                    label = 'Very Weak';
                    color = 'bg-red-500';
                    break;
                case 2:
                    label = 'Weak';
                    color = 'bg-orange-500';
                    break;
                case 3:
                case 4:
                    label = 'Medium';
                    color = 'bg-yellow-500';
                    break;
                case 5:
                case 6:
                    label = 'Strong';
                    color = 'bg-green-500';
                    break;
            }

            return { strength: (strength / 6) * 100, label, color };
        }

        // Real-time password strength
        document.getElementById('new_password')?.addEventListener('input', function(e) {
            const result = checkPasswordStrength(e.target.value);
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');

            strengthFill.style.width = result.strength + '%';
            strengthFill.className = `h-full transition-all duration-300 ${result.color}`;
            strengthText.textContent = result.label;
        });

        // Password match validation
        document.getElementById('confirm_password')?.addEventListener('input', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = e.target.value;
            const matchDiv = document.getElementById('passwordMatch');

            if (confirmPassword.length > 0) {
                matchDiv.classList.remove('hidden');
                if (newPassword === confirmPassword) {
                    matchDiv.textContent = '✓ Passwords match';
                    matchDiv.className = 'text-xs mt-1 text-green-600';
                } else {
                    matchDiv.textContent = '✗ Passwords do not match';
                    matchDiv.className = 'text-xs mt-1 text-red-600';
                }
            } else {
                matchDiv.classList.add('hidden');
            }
        });

        // Form validation
        document.getElementById('resetPasswordForm')?.addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match');
                return false;
            }

            if (newPassword.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long');
                return false;
            }

            // Add loading state
            const button = document.getElementById('submitButton');
            const text = document.getElementById('submitText');
            const spinner = document.getElementById('submitSpinner');
            
            button.disabled = true;
            text.classList.add('hidden');
            spinner.classList.remove('hidden');
        });

        // Focus management
        document.addEventListener('DOMContentLoaded', function() {
            const newPasswordField = document.getElementById('new_password');
            if (newPasswordField) {
                newPasswordField.focus();
            }
        });

        // Auto-hide messages after 10 seconds
        setTimeout(function() {
            const messages = document.querySelectorAll('.bg-red-50, .bg-green-50');
            messages.forEach(function(message) {
                message.style.transition = 'opacity 0.5s ease-out';
                message.style.opacity = '0';
                setTimeout(() => {
                    if (message.parentNode) {
                        message.remove();
                    }
                }, 500);
            });
        }, 10000);

        // Prevent double submission
        let formSubmitted = false;
        document.getElementById('resetPasswordForm')?.addEventListener('submit', function(e) {
            if (formSubmitted) {
                e.preventDefault();
                return false;
            }
            formSubmitted = true;
        });
    </script>
</body>
</html>