<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

// Get user information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get user statistics
$userStats = getUserStats($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Daily Calendar</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen pb-20">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b">
        <div class="px-4 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <a href="index.php" class="text-blue-500 text-sm">← Back</a>
                    <h1 class="text-lg font-bold text-gray-900">Profile</h1>
                </div>
                <a href="?logout=1" class="text-red-500 text-sm">Logout</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="px-4 py-6 space-y-6">
        <!-- User Info Card -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border">
            <div class="text-center">
                <div class="w-20 h-20 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <span class="text-white font-bold text-2xl"><?= strtoupper(substr($user['name'], 0, 2)) ?></span>
                </div>
                <h2 class="text-xl font-bold text-gray-900 mb-1"><?= htmlspecialchars($user['name']) ?></h2>
                <p class="text-gray-500 mb-2"><?= htmlspecialchars($user['email']) ?></p>
                <span class="px-3 py-1 text-sm rounded-full font-medium <?= $user['role'] === 'admin' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' ?>">
                    <?= ucfirst($user['role']) ?>
                </span>
            </div>
        </div>

        <!-- Statistics -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Your Statistics</h3>
            <div class="grid grid-cols-2 gap-4">
                <div class="text-center p-4 bg-green-50 rounded-xl">
                    <p class="text-2xl font-bold text-green-600"><?= $userStats['completed'] ?? 0 ?></p>
                    <p class="text-sm text-gray-600">Completed Tasks</p>
                </div>
                <div class="text-center p-4 bg-blue-50 rounded-xl">
                    <p class="text-2xl font-bold text-blue-600"><?= $userStats['in_progress'] ?? 0 ?></p>
                    <p class="text-sm text-gray-600">In Progress</p>
                </div>
                <div class="text-center p-4 bg-yellow-50 rounded-xl">
                    <p class="text-2xl font-bold text-yellow-600"><?= $userStats['pending'] ?? 0 ?></p>
                    <p class="text-sm text-gray-600">Pending</p>
                </div>
                <div class="text-center p-4 bg-gray-50 rounded-xl">
                    <p class="text-2xl font-bold text-gray-600"><?= $userStats['total'] ?? 0 ?></p>
                    <p class="text-sm text-gray-600">Total Tasks</p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
            <div class="space-y-3">
                <a href="change-password.php" class="flex items-center justify-between p-3 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">Change Password</p>
                            <p class="text-sm text-gray-500">Update your password</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>

                <?php if ($user['role'] === 'admin'): ?>
                <a href="admin-dashboard.php" class="flex items-center justify-between p-3 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">Admin Dashboard</p>
                            <p class="text-sm text-gray-500">Manage tasks and users</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Account Information -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Account Information</h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600">Member since:</span>
                    <span class="font-medium"><?= date('M j, Y', strtotime($user['created_at'])) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Last login:</span>
                    <span class="font-medium"><?= $user['last_login'] ? timeAgo($user['last_login']) : 'Never' ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Role:</span>
                    <span class="font-medium"><?= ucfirst($user['role']) ?></span>
                </div>
            </div>
        </div>
    </main>

    <!-- Bottom Navigation -->
    <nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 px-4 py-2">
        <div class="flex justify-around">
            <a href="index.php" class="flex flex-col items-center space-y-1 p-2 text-gray-400 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a2 2 0 012-2h4a2 2 0 012 2v4m-6 4l2 2 4-4"></path>
                </svg>
                <span class="text-xs font-medium">Tasks</span>
            </a>
            
            <a href="profile.php" class="flex flex-col items-center space-y-1 p-2 text-blue-600 transition-colors">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                </svg>
                <span class="text-xs font-medium">Profile</span>
            </a>
        </div>
    </nav>
</body>
</html>

<?php
if (isset($_GET['logout'])) {
    logout();
}
?>