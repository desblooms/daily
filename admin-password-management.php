<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireAdmin();

$success = '';
$error = '';
$resetPassword = '';

// Handle admin actions
if ($_POST && isset($_POST['action'])) {
    $userId = $_POST['user_id'] ?? null;
    
    switch ($_POST['action']) {
        case 'reset_password':
            if ($userId) {
                $newPassword = generateRandomPassword();
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                try {
                    $pdo->beginTransaction();
                    
                    $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$hashedPassword, $userId]);
                    
                    // Log password reset
                    $stmt = $pdo->prepare("INSERT INTO password_logs (user_id, changed_at, ip_address, user_agent) VALUES (?, NOW(), ?, ?)");
                    $stmt->execute([
                        $userId, 
                        $_SERVER['REMOTE_ADDR'] ?? 'admin-reset',
                        'Admin Reset - ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown')
                    ]);
                    
                    $pdo->commit();
                    
                    $resetPassword = $newPassword;
                    $success = "Password reset successfully. New password: " . $newPassword;
                } catch (Exception $e) {
                    $pdo->rollback();
                    $error = "Failed to reset password";
                }
            }
            break;
            
        case 'force_password_change':
            if ($userId) {
                $stmt = $pdo->prepare("UPDATE users SET force_password_change = 1 WHERE id = ?");
                if ($stmt->execute([$userId])) {
                    $success = "User will be forced to change password on next login";
                } else {
                    $error = "Failed to set password change requirement";
                }
            }
            break;
    }
}

// Get users list
$stmt = $pdo->prepare("
    SELECT u.*, 
           COUNT(pl.id) as password_changes,
           MAX(pl.changed_at) as last_password_change
    FROM users u 
    LEFT JOIN password_logs pl ON u.id = pl.user_id 
    GROUP BY u.id 
    ORDER BY u.name
");
$stmt->execute();
$users = $stmt->fetchAll();

// Get recent password changes
$stmt = $pdo->prepare("
    SELECT pl.*, u.name, u.email 
    FROM password_logs pl 
    JOIN users u ON pl.user_id = u.id 
    ORDER BY pl.changed_at DESC 
    LIMIT 20
");
$stmt->execute();
$recentChanges = $stmt->fetchAll();

function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    return substr(str_shuffle($chars), 0, $length);
}

function timeAgo($datetime) {
    if (!$datetime) return 'Never';
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    return floor($time/86400) . 'd ago';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Management - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen pb-16">
    <div class="p-2">
        <header class="flex justify-between items-center py-2 px-2 bg-white rounded-lg shadow-sm mb-2">
            <div class="flex items-center gap-2">
                <a href="admin-dashboard.php" class="text-blue-500 text-sm">← Back</a>
                <h1 class="text-lg font-bold">Password Management</h1>
            </div>
            <a href="?logout=1" class="bg-red-500 text-white px-3 py-1 rounded-lg text-xs">Logout</a>
        </header>

        <!-- Success/Error Messages -->
        <?php if ($success): ?>
            <div class="bg-green-100 text-green-700 p-3 rounded-lg text-xs mb-2 flex items-center gap-2">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                <div>
                    <?= $success ?>
                    <?php if ($resetPassword): ?>
                        <div class="mt-2 p-2 bg-white rounded border">
                            <p class="font-semibold text-gray-800">Temporary Password:</p>
                            <code class="bg-gray-100 px-2 py-1 rounded text-sm"><?= $resetPassword ?></code>
                            <button onclick="copyToClipboard('<?= $resetPassword ?>')" class="ml-2 text-blue-600 text-xs">Copy</button>
                        </div>
                    <?php endif; ?>
                </div>
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

        <!-- Users Password Management -->
        <div class="bg-white p-3 rounded-lg shadow-sm mb-2">
            <h3 class="text-sm font-semibold mb-3 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                </svg>
                User Password Management
            </h3>
            
            <div class="space-y-2">
                <?php foreach ($users as $user): ?>
                    <div class="border border-gray-200 rounded-lg p-2">
                        <div class="flex justify-between items-start mb-2">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                                        <span class="text-white font-semibold text-xs"><?= strtoupper(substr($user['name'], 0, 2)) ?></span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold"><?= htmlspecialchars($user['name']) ?></p>
                                        <p class="text-xs text-gray-500"><?= htmlspecialchars($user['email']) ?></p>
                                    </div>
                                </div>
                            </div>
                            <span class="px-2 py-1 text-xs rounded-full <?= $user['role'] === 'admin' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' ?>">
                                <?= ucfirst($user['role']) ?>
                            </span>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-2 text-xs text-gray-600 mb-2">
                            <div>
                                <span class="text-gray-500">Password Changes:</span>
                                <span class="font-semibold"><?= $user['password_changes'] ?></span>
                            </div>
                            <div>
                                <span class="text-gray-500">Last Change:</span>
                                <span class="font-semibold"><?= timeAgo($user['last_password_change']) ?></span>
                            </div>
                        </div>
                        
                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <div class="flex gap-2">
                                <form method="POST" class="inline" onsubmit="return confirm('Reset password for <?= htmlspecialchars($user['name']) ?>?')">
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" class="bg-orange-500 text-white px-2 py-1 rounded text-xs hover:bg-orange-600">
                                        Reset Password
                                    </button>
                                </form>
                                
                                <form method="POST" class="inline" onsubmit="return confirm('Force password change for <?= htmlspecialchars($user['name']) ?>?')">
                                    <input type="hidden" name="action" value="force_password_change">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" class="bg-yellow-500 text-white px-2 py-1 rounded text-xs hover:bg-yellow-600">
                                        Force Change
                                    </button>
                                </form>
                                
                                <button onclick="viewPasswordHistory(<?= $user['id'] ?>)" 
                                        class="bg-gray-500 text-white px-2 py-1 rounded text-xs hover:bg-gray-600">
                                    View History
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="text-xs text-gray-500 italic">Current admin user</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Recent Password Changes -->
        <div class="bg-white p-3 rounded-lg shadow-sm mb-2">
            <h3 class="text-sm font-semibold mb-3 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Recent Password Changes
            </h3>
            
            <?php if (empty($recentChanges)): ?>
                <p class="text-xs text-gray-500 text-center py-4">No password changes recorded</p>
            <?php else: ?>
                <div class="space-y-2 max-h-64 overflow-y-auto">
                    <?php foreach ($recentChanges as $change): ?>
                        <div class="flex justify-between items-center py-2 border-b border-gray-100 last:border-b-0">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 bg-green-500 rounded-full flex items-center justify-center">
                                    <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold"><?= htmlspecialchars($change['name']) ?></p>
                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($change['email']) ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-xs"><?= date('d M Y', strtotime($change['changed_at'])) ?></p>
                                <p class="text-xs text-gray-500"><?= date('H:i', strtotime($change['changed_at'])) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Password Policy Settings -->
        <div class="bg-white p-3 rounded-lg shadow-sm">
            <h3 class="text-sm font-semibold mb-3 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                </svg>
                Password Policy
            </h3>
            
            <div class="space-y-2 text-xs">
                <div class="flex justify-between">
                    <span class="text-gray-600">Minimum Length:</span>
                    <span class="font-semibold">6 characters</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Password Expiry:</span>
                    <span class="font-semibold">90 days (recommended)</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Failed Login Attempts:</span>
                    <span class="font-semibold">5 attempts</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Password History:</span>
                    <span class="font-semibold">Last 5 passwords</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Password History Modal -->
    <div id="historyModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-md w-full max-h-96 overflow-hidden">
                <div class="flex justify-between items-center p-3 border-b">
                    <h3 class="text-sm font-semibold">Password History</h3>
                    <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div id="historyContent" class="p-3 overflow-y-auto max-h-80">
                    Loading...
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <footer class="fixed bottom-0 left-0 right-0 bg-white shadow-inner flex justify-around py-2">
        <a href="admin-dashboard.php" class="text-xs text-gray-500 flex flex-col items-center">
            <svg class="w-4 h-4 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
            </svg>
            Dashboard
        </a>
        <a href="admin-password-management.php" class="text-xs text-blue-600 font-semibold flex flex-col items-center">
            <svg class="w-4 h-4 mb-1" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2z" clip-rule="evenodd"></path>
            </svg>
            Security
        </a>
        <a href="admin-users.php" class="text-xs text-gray-500 flex flex-col items-center">
            <svg class="w-4 h-4 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1z"></path>
            </svg>
            Users
        </a>
    </footer>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Password copied to clipboard!');
            });
        }

        function viewPasswordHistory(userId) {
            document.getElementById('historyModal').classList.remove('hidden');
            document.getElementById('historyContent').innerHTML = 'Loading...';
            
            fetch('./api/password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'get_password_history', 
                    user_id: userId,
                    limit: 20
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let html = '';
                    if (data.logs.length === 0) {
                        html = '<p class="text-xs text-gray-500 text-center py-4">No password changes found</p>';
                    } else {
                        html = '<div class="space-y-2">';
                        data.logs.forEach(log => {
                            const date = new Date(log.changed_at);
                            html += `
                                <div class="flex justify-between items-center py-2 border-b border-gray-100 last:border-b-0">
                                    <div>
                                        <p class="text-xs font-semibold">${date.toLocaleDateString()}</p>
                                        <p class="text-xs text-gray-500">${date.toLocaleTimeString()}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs text-gray-600">IP: ${log.ip_address}</p>
                                    </div>
                                </div>
                            `;
                        });
                        html += '</div>';
                    }
                    document.getElementById('historyContent').innerHTML = html;
                } else {
                    document.getElementById('historyContent').innerHTML = '<p class="text-xs text-red-500">Failed to load history</p>';
                }
            })
            .catch(error => {
                document.getElementById('historyContent').innerHTML = '<p class="text-xs text-red-500">Error loading history</p>';
            });
        }

        function closeModal() {
            document.getElementById('historyModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('historyModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Auto-hide success message after 10 seconds
        <?php if ($success): ?>
        setTimeout(function() {
            const successDiv = document.querySelector('.bg-green-100');
            if (successDiv) {
                successDiv.style.transition = 'opacity 0.5s';
                successDiv.style.opacity = '0';
                setTimeout(() => successDiv.remove(), 500);
            }
        }, 10000);
        <?php endif; ?>
    </script>
</body>
</html>

<?php
if (isset($_GET['logout'])) {
    logout();
}
?>