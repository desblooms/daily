<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireLogin();

$success = '';
$error = '';

// Handle password change submission
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = "All fields are required";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "New passwords do not match";
    } elseif (strlen($newPassword) < 6) {
        $error = "New password must be at least 6 characters";
    } else {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($currentPassword, $user['password'])) {
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            
            if ($stmt->execute([$hashedPassword, $_SESSION['user_id']])) {
                $success = "Password changed successfully";
                
                // Log password change
                $stmt = $pdo->prepare("INSERT INTO password_logs (user_id, changed_at, ip_address) VALUES (?, NOW(), ?)");
                $stmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
            } else {
                $error = "Failed to update password";
            }
        } else {
            $error = "Current password is incorrect";
        }
    }
}

// Get user info
$stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userInfo = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Daily Calendar</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen pb-16">
    <div class="p-2">
        <header class="flex justify-between items-center py-2 px-2 bg-white rounded-lg shadow-sm mb-2">
            <div class="flex items-center gap-2">
                <a href="<?= $_SESSION['role'] === 'admin' ? 'admin-dashboard.php' : 'index.php' ?>" 
                   class="text-blue-500 text-sm">← Back</a>
                <h1 class="text-lg font-bold">Change Password</h1>
            </div>
            <a href="?logout=1" class="bg-red-500 text-white px-3 py-1 rounded-lg text-xs">Logout</a>
        </header>

        <!-- User Info -->
        <div class="bg-white p-3 rounded-lg shadow-sm mb-2">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center">
                    <span class="text-white font-semibold text-sm"><?= strtoupper(substr($userInfo['name'], 0, 2)) ?></span>
                </div>
                <div>
                    <p class="text-sm font-semibold"><?= htmlspecialchars($userInfo['name']) ?></p>
                    <p class="text-xs text-gray-500"><?= htmlspecialchars($userInfo['email']) ?></p>
                    <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-700"><?= ucfirst($_SESSION['role']) ?></span>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success): ?>
            <div class="bg-green-100 text-green-700 p-2 rounded-lg text-xs mb-2 flex items-center gap-2">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                <?= $success ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 p-2 rounded-lg text-xs mb-2 flex items-center gap-2">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                </svg>
                <?= $error ?>
            </div>
        <?php endif; ?>

        <!-- Change Password Form -->
        <div class="bg-white p-3 rounded-lg shadow-sm mb-2">
            <h3 class="text-sm font-semibold mb-3">Security Settings</h3>
            
            <form method="POST" class="space-y-3" id="passwordForm">
                <input type="hidden" name="action" value="change_password">
                
                <div>
                    <label class="text-xs text-gray-600 block mb-1">Current Password</label>
                    <div class="relative">
                        <input type="password" name="current_password" id="currentPassword" required 
                               class="w-full p-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 pr-10"
                               placeholder="Enter current password">
                        <button type="button" onclick="togglePassword('currentPassword')" 
                                class="absolute right-2 top-2 text-gray-400 hover:text-gray-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div>
                    <label class="text-xs text-gray-600 block mb-1">New Password</label>
                    <div class="relative">
                        <input type="password" name="new_password" id="newPassword" required 
                               class="w-full p-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 pr-10"
                               placeholder="Enter new password" minlength="6">
                        <button type="button" onclick="togglePassword('newPassword')" 
                                class="absolute right-2 top-2 text-gray-400 hover:text-gray-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
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
                        <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
                    </div>
                </div>
                
                <div>
                    <label class="text-xs text-gray-600 block mb-1">Confirm New Password</label>
                    <div class="relative">
                        <input type="password" name="confirm_password" id="confirmPassword" required 
                               class="w-full p-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 pr-10"
                               placeholder="Confirm new password">
                        <button type="button" onclick="togglePassword('confirmPassword')" 
                                class="absolute right-2 top-2 text-gray-400 hover:text-gray-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                        </button>
                    </div>
                    <div id="passwordMatch" class="text-xs mt-1 hidden"></div>
                </div>
                
                <div class="flex gap-2 pt-2">
                    <button type="submit" 
                            class="flex-1 bg-blue-500 text-white p-2 rounded-lg text-sm font-semibold hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed"
                            id="submitBtn">
                        Change Password
                    </button>
                    <button type="button" onclick="clearForm()" 
                            class="px-4 bg-gray-500 text-white p-2 rounded-lg text-sm hover:bg-gray-600">
                        Clear
                    </button>
                </div>
            </form>
        </div>

        <!-- Security Tips -->
        <div class="bg-white p-3 rounded-lg shadow-sm">
            <h3 class="text-sm font-semibold mb-2 flex items-center gap-2">
                <svg class="w-4 h-4 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                </svg>
                Security Tips
            </h3>
            <ul class="text-xs text-gray-600 space-y-1">
                <li>• Use a strong password with mix of letters, numbers, and symbols</li>
                <li>• Don't use personal information in your password</li>
                <li>• Don't reuse passwords from other accounts</li>
                <li>• Change your password regularly</li>
                <li>• Never share your password with anyone</li>
            </ul>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <footer class="fixed bottom-0 left-0 right-0 bg-white shadow-inner flex justify-around py-2">
        <a href="<?= $_SESSION['role'] === 'admin' ? 'admin-dashboard.php' : 'index.php' ?>" 
           class="text-xs text-gray-500 flex flex-col items-center">
            <svg class="w-4 h-4 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a2 2 0 012-2h4a2 2 0 012 2v4m-6 4l2 2 4-4"></path>
            </svg>
            Tasks
        </a>
        <a href="profile.php" class="text-xs text-blue-600 font-semibold flex flex-col items-center">
            <svg class="w-4 h-4 mb-1" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
            </svg>
            Profile
        </a>
        <a href="settings.php" class="text-xs text-gray-500 flex flex-col items-center">
            <svg class="w-4 h-4 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
            Settings
        </a>
    </footer>

    <script>
        // Toggle password visibility
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
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
        document.getElementById('newPassword').addEventListener('input', function(e) {
            const result = checkPasswordStrength(e.target.value);
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');

            strengthFill.style.width = result.strength + '%';
            strengthFill.className = `h-full transition-all duration-300 ${result.color}`;
            strengthText.textContent = result.label;
        });

        // Password match validation
        document.getElementById('confirmPassword').addEventListener('input', function(e) {
            const newPassword = document.getElementById('newPassword').value;
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
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match');
                return false;
            }

            if (newPassword.length < 6) {
                e.preventDefault();
                alert('New password must be at least 6 characters long');
                return false;
            }

            // Disable submit button to prevent double submission
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('submitBtn').textContent = 'Changing...';
        });

        // Clear form
        function clearForm() {
            document.getElementById('passwordForm').reset();
            document.getElementById('strengthFill').style.width = '0%';
            document.getElementById('strengthText').textContent = 'Weak';
            document.getElementById('passwordMatch').classList.add('hidden');
        }

        // Auto-hide success message after 5 seconds
        <?php if ($success): ?>
        setTimeout(function() {
            const successDiv = document.querySelector('.bg-green-100');
            if (successDiv) {
                successDiv.style.transition = 'opacity 0.5s';
                successDiv.style.opacity = '0';
                setTimeout(() => successDiv.remove(), 500);
            }
        }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>

<?php
if (isset($_GET['logout'])) {
    logout();
}
?>