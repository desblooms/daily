<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/password-reset.php';
require_once 'includes/functions.php';

requireAdmin();

// Get security statistics
$resetStats = getPasswordResetStats(30);
$securitySettings = getSecuritySettings();

// Handle settings update
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_security') {
    $newSettings = [
        'password_min_length' => (int)($_POST['password_min_length'] ?? 6),
        'password_require_uppercase' => isset($_POST['password_require_uppercase']),
        'password_require_lowercase' => isset($_POST['password_require_lowercase']),
        'password_require_numbers' => isset($_POST['password_require_numbers']),
        'password_require_symbols' => isset($_POST['password_require_symbols']),
        'max_login_attempts' => (int)($_POST['max_login_attempts'] ?? 5),
        'lockout_duration' => (int)($_POST['lockout_duration'] ?? 900),
        'password_reset_rate_limit' => (int)($_POST['password_reset_rate_limit'] ?? 3)
    ];
    
    $updated = updateSecuritySettings($newSettings, $_SESSION['user_id']);
    if ($updated > 0) {
        $success = "Security settings updated successfully";
        $securitySettings = getSecuritySettings(); // Refresh settings
    } else {
        $error = "Failed to update security settings";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Dashboard - Daily Calendar</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50 min-h-screen pb-20">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b">
        <div class="px-4 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <a href="admin-dashboard.php" class="text-blue-500 text-sm">← Back</a>
                    <h1 class="text-lg font-bold text-gray-900">Security Dashboard</h1>
                </div>
                <div class="flex items-center space-x-2">
                    <button onclick="cleanupTokens()" class="bg-red-500 text-white px-3 py-1 rounded-lg text-xs">
                        Cleanup Tokens
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Success/Error Messages -->
    <?php if (isset($success)): ?>
        <div class="mx-4 mt-4 bg-green-100 text-green-700 p-3 rounded-lg text-sm">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="mx-4 mt-4 bg-red-100 text-red-700 p-3 rounded-lg text-sm">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Main Content -->
    <main class="px-4 py-4 space-y-4">
        <!-- Security Overview -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Security Overview</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-blue-50 p-4 rounded-xl">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-blue-600"><?= $resetStats['token_stats']['total_tokens'] ?? 0 ?></p>
                            <p class="text-sm text-gray-600">Reset Requests (30d)</p>
                        </div>
                    </div>
                </div>

                <div class="bg-green-50 p-4 rounded-xl">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-green-500 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-green-600"><?= $resetStats['token_stats']['used_tokens'] ?? 0 ?></p>
                            <p class="text-sm text-gray-600">Successful Resets</p>
                        </div>
                    </div>
                </div>

                <div class="bg-red-50 p-4 rounded-xl">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-red-500 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-red-600"><?= $resetStats['token_stats']['expired_tokens'] ?? 0 ?></p>
                            <p class="text-sm text-gray-600">Expired Tokens</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reset Activity Chart -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Password Reset Activity (Last 30 Days)</h3>
            <div class="h-64">
                <canvas id="resetActivityChart"></canvas>
            </div>
        </div>

        <!-- Security Settings -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Security Settings</h3>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_security">
                
                <!-- Password Requirements -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <h4 class="text-md font-semibold text-gray-800 mb-3">Password Requirements</h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Minimum Length</label>
                            <input type="number" name="password_min_length" min="4" max="50" 
                                   value="<?= $securitySettings['password_min_length'] ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="checkbox" name="password_require_uppercase" 
                                       <?= $securitySettings['password_require_uppercase'] ? 'checked' : '' ?>
                                       class="mr-2 rounded">
                                <span class="text-sm text-gray-700">Require uppercase letters</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="password_require_lowercase"
                                       <?= $securitySettings['password_require_lowercase'] ? 'checked' : '' ?>
                                       class="mr-2 rounded">
                                <span class="text-sm text-gray-700">Require lowercase letters</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="password_require_numbers"
                                       <?= $securitySettings['password_require_numbers'] ? 'checked' : '' ?>
                                       class="mr-2 rounded">
                                <span class="text-sm text-gray-700">Require numbers</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="password_require_symbols"
                                       <?= $securitySettings['password_require_symbols'] ? 'checked' : '' ?>
                                       class="mr-2 rounded">
                                <span class="text-sm text-gray-700">Require special characters</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Login Security -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <h4 class="text-md font-semibold text-gray-800 mb-3">Login Security</h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Max Login Attempts</label>
                            <input type="number" name="max_login_attempts" min="3" max="10" 
                                   value="<?= $securitySettings['max_login_attempts'] ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                            <p class="text-xs text-gray-500 mt-1">Failed attempts before account lockout</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Lockout Duration (seconds)</label>
                            <input type="number" name="lockout_duration" min="300" max="3600" step="300"
                                   value="<?= $securitySettings['lockout_duration'] ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                            <p class="text-xs text-gray-500 mt-1">How long to lock account (default: 900 = 15 minutes)</p>
                        </div>
                    </div>
                </div>

                <!-- Password Reset Settings -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <h4 class="text-md font-semibold text-gray-800 mb-3">Password Reset Settings</h4>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reset Rate Limit</label>
                        <input type="number" name="password_reset_rate_limit" min="1" max="10" 
                               value="<?= $securitySettings['password_reset_rate_limit'] ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-gray-500 mt-1">Max reset requests per email in 15 minutes</p>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors">
                        Update Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- Recent Security Events -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Security Events</h3>
            
            <div class="space-y-3 max-h-80 overflow-y-auto">
                <?php
                $securityEvents = getRecentSecurityEvents(20);
                if (empty($securityEvents)):
                ?>
                    <p class="text-sm text-gray-500 text-center py-4">No recent security events</p>
                <?php else: ?>
                    <?php foreach ($securityEvents as $event): ?>
                        <div class="flex items-center justify-between py-2 px-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 <?= getEventTypeColor($event['event_type']) ?> rounded-full flex items-center justify-center">
                                    <?= getEventTypeIcon($event['event_type']) ?>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($event['description']) ?></p>
                                    <p class="text-xs text-gray-500">
                                        <?= htmlspecialchars($event['user_name']) ?> • 
                                        IP: <?= htmlspecialchars($event['ip_address']) ?>
                                    </p>
                                </div>
                            </div>
                            <span class="text-xs text-gray-500"><?= timeAgo($event['timestamp']) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Security Recommendations -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Security Recommendations</h3>
            
            <div class="space-y-3">
                <div class="flex items-start space-x-3 p-3 bg-yellow-50 rounded-lg">
                    <svg class="w-5 h-5 text-yellow-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-yellow-800">Enable Two-Factor Authentication</p>
                        <p class="text-xs text-yellow-700">Consider implementing 2FA for enhanced account security</p>
                    </div>
                </div>

                <div class="flex items-start space-x-3 p-3 bg-blue-50 rounded-lg">
                    <svg class="w-5 h-5 text-blue-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-blue-800">Regular Security Audits</p>
                        <p class="text-xs text-blue-700">Review user access and permissions monthly</p>
                    </div>
                </div>

                <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                    <svg class="w-5 h-5 text-green-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-green-800">Password Policy Compliance</p>
                        <p class="text-xs text-green-700">Current password policy settings are secure</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bottom Navigation -->
    <nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 px-4 py-2">
        <div class="flex justify-around">
            <a href="admin-dashboard.php" class="flex flex-col items-center space-y-1 p-2 text-gray-400">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                </svg>
                <span class="text-xs font-medium">Dashboard</span>
            </a>
            
            <a href="admin-security.php" class="flex flex-col items-center space-y-1 p-2 text-blue-600">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                <span class="text-xs font-medium">Security</span>
            </a>
            
            <a href="admin-users.php" class="flex flex-col items-center space-y-1 p-2 text-gray-400">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                </svg>
                <span class="text-xs font-medium">Users</span>
            </a>
        </div>
    </nav>

    <script>
        // Initialize reset activity chart
        const ctx = document.getElementById('resetActivityChart').getContext('2d');
        const chartData = <?= json_encode($resetStats['attempts_by_date'] ?? []) ?>;
        
        // Prepare chart data
        const labels = chartData.map(item => {
            const date = new Date(item.date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }).reverse();
        
        const data = chartData.map(item => item.attempts).reverse();
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Reset Requests',
                    data: data,
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#3B82F6',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f3f4f6' },
                        ticks: { 
                            font: { size: 12 },
                            stepSize: 1
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 12 } }
                    }
                }
            }
        });

        // Cleanup tokens function
        function cleanupTokens() {
            if (!confirm('This will permanently delete all expired password reset tokens. Continue?')) {
                return;
            }

            fetch('api/password-reset.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'cleanup_tokens' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`Successfully cleaned up ${data.deleted_count} expired tokens`);
                    location.reload();
                } else {
                    alert('Failed to cleanup tokens: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error cleaning up tokens');
            });
        }

        // Auto-hide success/error messages
        setTimeout(function() {
            const messages = document.querySelectorAll('.bg-green-100, .bg-red-100');
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
    </script>
</body>
</html>

<?php
// Helper functions for this page
function getRecentSecurityEvents($limit = 20) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            al.*,
            u.name as user_name,
            CASE 
                WHEN al.action = 'password_reset_requested' THEN 'Password reset requested'
                WHEN al.action = 'password_reset_completed' THEN 'Password reset completed'
                WHEN al.action = 'user_login' THEN 'User logged in'
                WHEN al.action = 'login_failed' THEN 'Failed login attempt'
                WHEN al.action = 'account_locked' THEN 'Account locked'
                WHEN al.action = 'force_password_change' THEN 'Forced password change'
                ELSE al.action
            END as description,
            CASE 
                WHEN al.action LIKE '%login%' OR al.action LIKE '%password%' THEN al.action
                ELSE 'other'
            END as event_type
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE al.action IN ('password_reset_requested', 'password_reset_completed', 'user_login', 'login_failed', 'account_locked', 'force_password_change')
        ORDER BY al.timestamp DESC
        LIMIT ?
    ");
    
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function getEventTypeColor($eventType) {
    switch (true) {
        case strpos($eventType, 'failed') !== false:
        case strpos($eventType, 'locked') !== false:
            return 'bg-red-100 text-red-600';
        case strpos($eventType, 'password') !== false:
            return 'bg-yellow-100 text-yellow-600';
        case strpos($eventType, 'login') !== false:
            return 'bg-green-100 text-green-600';
        default:
            return 'bg-blue-100 text-blue-600';
    }
}

function getEventTypeIcon($eventType) {
    switch (true) {
        case strpos($eventType, 'failed') !== false:
        case strpos($eventType, 'locked') !== false:
            return '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>';
        case strpos($eventType, 'password') !== false:
            return '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2z" clip-rule="evenodd"></path></svg>';
        case strpos($eventType, 'login') !== false:
            return '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>';
        default:
            return '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>';
    }
}

if (isset($_GET['logout'])) {
    logout();
}
?>