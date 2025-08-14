<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$error = '';
$info = '';
$success = '';

// Check if user must change password
if (isset($_SESSION['must_change_password']) && $_SESSION['must_change_password']) {
    $info = "You must change your password before continuing.";
}

// Handle login form submission
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password";
    } else {
        $loginResult = secureLogin($email, $password);
        
        if ($loginResult['success']) {
            // Set success message in session for redirect
            $_SESSION['login_success'] = true;
            header("Location: " . $loginResult['redirect']);
            exit;
        } else {
            $error = $loginResult['message'];
            
            // If user needs to change password, redirect to change password page
            if (isset($loginResult['redirect'])) {
                header("Location: " . $loginResult['redirect']);
                exit;
            }
        }
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    if (isset($_SESSION['user_id'])) {
        logActivity($_SESSION['user_id'], 'user_logout');
    }
    session_destroy();
    $success = "You have been logged out successfully.";
}

// If already logged in, redirect to appropriate dashboard
if (isLoggedIn() && !isset($_SESSION['must_change_password'])) {
    $redirect = $_SESSION['role'] === 'admin' ? 'admin-dashboard.php' : 'index.php';
    header("Location: $redirect");
    exit;
}

// Get system settings
$siteName = getSystemSetting('site_name', 'Daily Calendar');
$maxLoginAttempts = getSystemSetting('max_login_attempts', 5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars($siteName) ?></title>
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
        .login-bg {
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
        .pulse-button:hover {
            animation: pulse 0.3s ease-in-out;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        .input-focus:focus {
            transform: translateY(-2px);
            transition: all 0.2s ease;
        }
    </style>

    <script>
    // Provide user context to JavaScript
    window.userRole = '<?= $_SESSION['role'] ?? 'user' ?>';
    window.userId = <?= $_SESSION['user_id'] ?? 'null' ?>;
    window.userName = '<?= htmlspecialchars($_SESSION['user_name'] ?? 'Unknown') ?>';
</script>
</head>
<body class="login-bg flex items-center justify-center p-4">
    <!-- Background decorative elements -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-1/4 left-1/4 w-64 h-64 bg-white opacity-5 rounded-full floating-animation"></div>
        <div class="absolute bottom-1/4 right-1/4 w-48 h-48 bg-white opacity-5 rounded-full floating-animation" style="animation-delay: -3s;"></div>
        <div class="absolute top-3/4 left-1/2 w-32 h-32 bg-white opacity-5 rounded-full floating-animation" style="animation-delay: -1.5s;"></div>
    </div>

    <div class="relative z-10 w-full max-w-md">
        <!-- Login Card -->
        <div class="glass-effect rounded-3xl p-8 slide-in">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="w-20 h-20 bg-gradient-to-r from-blue-500 to-purple-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a2 2 0 012-2h4a2 2 0 012 2v4m-6 4l2 2 4-4"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-800 mb-2"><?= htmlspecialchars($siteName) ?></h1>
                <p class="text-gray-600">Welcome back! Please sign in to continue.</p>
            </div>
            
            <!-- Messages -->
            <?php if ($error): ?>
                <div class="bg-red-50 border-l-4 border-red-400 text-red-700 px-4 py-3 rounded-lg text-sm mb-6 flex items-center gap-3">
                    <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($info): ?>
                <div class="bg-blue-50 border-l-4 border-blue-400 text-blue-700 px-4 py-3 rounded-lg text-sm mb-6 flex items-center gap-3">
                    <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                    </svg>
                    <span><?= htmlspecialchars($info) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-50 border-l-4 border-green-400 text-green-700 px-4 py-3 rounded-lg text-sm mb-6 flex items-center gap-3">
                    <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>
            
            <!-- Login Form -->
            <form method="POST" class="space-y-6" id="loginForm">
                <input type="hidden" name="action" value="login">
                
                <div class="space-y-2">
                    <label for="email" class="block text-sm font-semibold text-gray-700">Email Address</label>
                    <div class="relative">
                        <input type="email" 
                               name="email" 
                               id="email"
                               required 
                               autocomplete="email"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               class="input-focus w-full px-4 py-3 pl-12 text-sm border-2 border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                               placeholder="Enter your email address">
                        <svg class="w-5 h-5 text-gray-400 absolute left-4 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                        </svg>
                    </div>
                </div>
                
                <div class="space-y-2">
                    <label for="password" class="block text-sm font-semibold text-gray-700">Password</label>
                    <div class="relative">
                        <input type="password" 
                               name="password" 
                               id="password"
                               required 
                               autocomplete="current-password"
                               class="input-focus w-full px-4 py-3 pl-12 pr-12 text-sm border-2 border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                               placeholder="Enter your password">
                        <svg class="w-5 h-5 text-gray-400 absolute left-4 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                        <button type="button" 
                                onclick="togglePasswordVisibility()" 
                                class="absolute right-4 top-3.5 text-gray-400 hover:text-gray-600 transition-colors">
                            <svg id="eyeOpen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                            <svg id="eyeClosed" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L12 12m-3.122-3.122L12 12m0 0l3.878 3.878M12 12l3.878-3.878m-7.756-.001l3.878 3.879"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                
                

                <div class="flex items-center justify-between">
                    <label class="flex items-center">
                        <input type="checkbox" name="remember_me" class="w-4 h-4 text-blue-600 border-2 border-gray-300 rounded focus:ring-blue-500 focus:ring-2">
                        <span class="ml-2 text-sm text-gray-600">Remember me</span>
                    </label>
                    <a href="forgot-password.php" class="text-sm text-blue-600 hover:text-blue-800 font-medium transition-colors">
                        Forgot password?
                    </a>
                </div>
                
                <button type="submit" 
                        class="pulse-button w-full bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white font-semibold py-3 px-4 rounded-xl transition-all duration-200 focus:outline-none focus:ring-4 focus:ring-blue-300 disabled:opacity-50 disabled:cursor-not-allowed shadow-lg"
                        id="loginButton">
                    <span id="loginText" class="flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                        </svg>
                        Sign In
                    </span>
                    <span id="loginSpinner" class="hidden">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Signing in...
                    </span>
                </button>
            </form>
            
            <!-- Demo Accounts -->
            <div class="mt-8 pt-6 border-t border-gray-200">
                <p class="text-xs text-gray-500 text-center mb-4 font-medium">Demo Accounts for Testing</p>
                <div class="grid grid-cols-1 gap-3">
                    <div class="flex justify-between items-center p-3 bg-gradient-to-r from-purple-50 to-blue-50 rounded-xl border border-purple-100">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">Administrator</p>
                            <p class="text-xs text-gray-500">admin@example.com</p>
                        </div>
                        <button onclick="fillCredentials('admin@example.com', 'admin123')" 
                                class="px-3 py-1 bg-gradient-to-r from-purple-500 to-blue-500 text-white text-xs rounded-lg hover:from-purple-600 hover:to-blue-600 transition-all duration-200 font-medium">
                            Use Account
                        </button>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-gradient-to-r from-blue-50 to-green-50 rounded-xl border border-blue-100">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">Team Member</p>
                            <p class="text-xs text-gray-500">user@example.com</p>
                        </div>
                        <button onclick="fillCredentials('user@example.com', 'user123')" 
                                class="px-3 py-1 bg-gradient-to-r from-blue-500 to-green-500 text-white text-xs rounded-lg hover:from-blue-600 hover:to-green-600 transition-all duration-200 font-medium">
                            Use Account
                        </button>
                    </div>
                </div>
            </div>

            <!-- Security Notice -->
            <div class="mt-6 p-4 bg-amber-50 border border-amber-200 rounded-xl">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                    <div>
                        <p class="text-sm font-semibold text-amber-800">Security Notice</p>
                        <p class="text-xs text-amber-700 mt-1">Your account will be temporarily locked after <?= $maxLoginAttempts ?> failed login attempts for security protection.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-6">
            <p class="text-white text-sm opacity-80">
                © <?= date('Y') ?> <?= htmlspecialchars($siteName) ?>. All rights reserved.
            </p>
        </div>
    </div>

    <script>
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const eyeOpen = document.getElementById('eyeOpen');
            const eyeClosed = document.getElementById('eyeClosed');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeOpen.classList.add('hidden');
                eyeClosed.classList.remove('hidden');
            } else {
                passwordInput.type = 'password';
                eyeOpen.classList.remove('hidden');
                eyeClosed.classList.add('hidden');
            }
        }

        function fillCredentials(email, password) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = password;
            
            // Add a subtle animation to indicate the fields have been filled
            const emailField = document.getElementById('email');
            const passwordField = document.getElementById('password');
            
            emailField.classList.add('bg-blue-50');
            passwordField.classList.add('bg-blue-50');
            
            setTimeout(() => {
                emailField.classList.remove('bg-blue-50');
                passwordField.classList.remove('bg-blue-50');
            }, 1000);
        }

        // Enhanced form submission with loading state
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const button = document.getElementById('loginButton');
            const text = document.getElementById('loginText');
            const spinner = document.getElementById('loginSpinner');
            
            button.disabled = true;
            text.classList.add('hidden');
            spinner.classList.remove('hidden');
            
            // Add a slight delay to show the loading state
            setTimeout(() => {
                // The form will submit naturally
            }, 100);
        });

        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            const messages = document.querySelectorAll('.bg-red-50, .bg-green-50, .bg-blue-50');
            messages.forEach(function(message) {
                message.style.transition = 'opacity 0.5s ease-out';
                message.style.opacity = '0';
                setTimeout(() => {
                    if (message.parentNode) {
                        message.remove();
                    }
                }, 500);
            });
        }, 5000);

        // Focus management
        document.addEventListener('DOMContentLoaded', function() {
            const emailField = document.getElementById('email');
            if (!emailField.value) {
                emailField.focus();
            } else {
                document.getElementById('password').focus();
            }
        });

        // Enhanced keyboard navigation
        document.getElementById('password').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('loginForm').submit();
            }
        });

        // Add visual feedback for form interactions
        const inputs = document.querySelectorAll('input[type="email"], input[type="password"]');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('ring-2', 'ring-blue-200');
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('ring-2', 'ring-blue-200');
            });
        });

        // Prevent double submission
        let formSubmitted = false;
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            if (formSubmitted) {
                e.preventDefault();
                return false;
            }
            formSubmitted = true;
        });

        // Add progressive enhancement for better UX
        if ('serviceWorker' in navigator) {
            // Register service worker for offline capability (optional)
            // navigator.serviceWorker.register('/sw.js');
        }

        // Add connection status awareness
        window.addEventListener('online', function() {
            document.getElementById('loginButton').disabled = false;
        });

        window.addEventListener('offline', function() {
            document.getElementById('loginButton').disabled = true;
        });
    </script>
</body>
</html>