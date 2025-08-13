<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$currentPage = $_GET['page'] ?? 'tasks';
$selectedDate = $_GET['date'] ?? date('Y-m-d');

// Get user's tasks for selected date
$tasks = getTasks($_SESSION['user_id'], $selectedDate);

// Get user stats
$userStats = getUserStats($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Tasks - Calendar App</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3B82F6',
                        secondary: '#6366F1',
                        success: '#10B981',
                        warning: '#F59E0B',
                        danger: '#EF4444',
                    }
                }
            }
        }
    </script>
    <style>
        .glass-effect {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.9);
        }
        .floating-action {
            box-shadow: 0 4px 20px rgba(59, 130, 246, 0.3);
        }
        .task-card {
            transition: all 0.2s ease;
        }
        .task-card:active {
            transform: scale(0.98);
        }
        .bottom-nav-item {
            transition: all 0.2s ease;
        }
        .bottom-nav-item.active {
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen pb-20">
    <!-- Header -->
    <header class="sticky top-0 z-40 glass-effect border-b border-gray-200">
        <div class="px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a2 2 0 012-2h4a2 2 0 012 2v4m-6 4l2 2 4-4"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-gray-900"><?= getPageTitle($currentPage) ?></h1>
                        <p class="text-xs text-gray-500"><?= date('F j, Y', strtotime($selectedDate)) ?></p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <button onclick="refreshTasks()" class="p-2 rounded-lg bg-gray-100 hover:bg-gray-200 transition-colors">
                        <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </button>
                    <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                        <span class="text-white font-semibold text-xs"><?= strtoupper(substr($_SESSION['user_name'], 0, 2)) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Content Area -->
    <main class="px-4 py-4 space-y-4">
        <?php if ($currentPage === 'tasks'): ?>
            <!-- Date Selector -->
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-gray-900">Select Date</h3>
                    <button onclick="selectToday()" class="text-xs text-blue-600 font-medium">Today</button>
                </div>
                <input type="date" 
                       value="<?= $selectedDate ?>" 
                       onchange="changeDate(this.value)"
                       class="w-full p-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <!-- Tasks List -->
            <div class="space-y-3">
                <?php if (empty($tasks)): ?>
                    <div class="bg-white rounded-xl p-8 text-center shadow-sm">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <p class="text-gray-500 mb-2">No tasks for this date</p>
                        <p class="text-xs text-gray-400">Check back later for new assignments</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($tasks as $task): ?>
                        <div class="task-card bg-white rounded-xl p-4 shadow-sm border border-gray-100" 
                             onclick="openTaskDetail(<?= $task['id'] ?>)">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-2 mb-2">
                                        <h4 class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($task['title']) ?></h4>
                                        <span class="px-2 py-1 text-xs rounded-full font-medium <?= getStatusStyle($task['status']) ?>">
                                            <?= $task['status'] ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($task['details'])): ?>
                                        <p class="text-xs text-gray-600 mb-3 line-clamp-2"><?= htmlspecialchars($task['details']) ?></p>
                                    <?php endif; ?>
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs text-gray-400">
                                            <?= date('g:i A', strtotime($task['created_at'])) ?>
                                        </span>
                                        <div class="flex space-x-1">
                                            <?php if ($task['status'] === 'Pending'): ?>
                                                <button onclick="event.stopPropagation(); updateTaskStatus(<?= $task['id'] ?>, 'On Progress')" 
                                                        class="px-3 py-1 bg-blue-500 text-white text-xs rounded-lg hover:bg-blue-600 transition-colors">
                                                    Start
                                                </button>
                                            <?php elseif ($task['status'] === 'On Progress'): ?>
                                                <button onclick="event.stopPropagation(); updateTaskStatus(<?= $task['id'] ?>, 'Done')" 
                                                        class="px-3 py-1 bg-green-500 text-white text-xs rounded-lg hover:bg-green-600 transition-colors">
                                                    Complete
                                                </button>
                                                <button onclick="event.stopPropagation(); updateTaskStatus(<?= $task['id'] ?>, 'On Hold')" 
                                                        class="px-3 py-1 bg-yellow-500 text-white text-xs rounded-lg hover:bg-yellow-600 transition-colors">
                                                    Hold
                                                </button>
                                            <?php elseif ($task['status'] === 'On Hold'): ?>
                                                <button onclick="event.stopPropagation(); updateTaskStatus(<?= $task['id'] ?>, 'On Progress')" 
                                                        class="px-3 py-1 bg-blue-500 text-white text-xs rounded-lg hover:bg-blue-600 transition-colors">
                                                    Resume
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <?php elseif ($currentPage === 'analytics'): ?>
            <!-- User Analytics -->
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="bg-white rounded-xl p-4 shadow-sm">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900"><?= $userStats['completed'] ?></p>
                            <p class="text-xs text-gray-500">Completed</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl p-4 shadow-sm">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900"><?= $userStats['pending'] ?></p>
                            <p class="text-xs text-gray-500">Pending</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Progress Chart -->
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <h3 class="font-semibold text-gray-900 mb-4">Weekly Progress</h3>
                <canvas id="progressChart" height="200"></canvas>
            </div>

        <?php elseif ($currentPage === 'profile'): ?>
            <!-- Profile Page -->
            <div class="bg-white rounded-xl p-6 shadow-sm text-center">
                <div class="w-20 h-20 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <span class="text-white font-bold text-xl"><?= strtoupper(substr($_SESSION['user_name'], 0, 2)) ?></span>
                </div>
                <h2 class="text-lg font-bold text-gray-900 mb-1"><?= htmlspecialchars($_SESSION['user_name']) ?></h2>
                <p class="text-sm text-gray-500 mb-4">Team Member</p>
                
                <div class="space-y-3">
                    <a href="change-password.php" class="block w-full p-3 bg-gray-50 rounded-lg text-left">
                        <div class="flex items-center space-x-3">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                            <span class="font-medium text-gray-900">Change Password</span>
                        </div>
                    </a>
                    
                    <button onclick="logout()" class="block w-full p-3 bg-red-50 rounded-lg text-left">
                        <div class="flex items-center space-x-3">
                            <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                            </svg>
                            <span class="font-medium text-red-900">Logout</span>
                        </div>
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Bottom Navigation -->
    <nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 px-4 py-2 glass-effect">
        <div class="flex justify-around">
            <a href="?page=tasks" class="bottom-nav-item flex flex-col items-center space-y-1 p-2 <?= $currentPage === 'tasks' ? 'active' : '' ?>">
                <div class="w-6 h-6 <?= $currentPage === 'tasks' ? 'text-blue-600' : 'text-gray-400' ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                </div>
                <span class="text-xs font-medium <?= $currentPage === 'tasks' ? 'text-blue-600' : 'text-gray-400' ?>">Tasks</span>
            </a>
            
            <a href="?page=analytics" class="bottom-nav-item flex flex-col items-center space-y-1 p-2 <?= $currentPage === 'analytics' ? 'active' : '' ?>">
                <div class="w-6 h-6 <?= $currentPage === 'analytics' ? 'text-blue-600' : 'text-gray-400' ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
                <span class="text-xs font-medium <?= $currentPage === 'analytics' ? 'text-blue-600' : 'text-gray-400' ?>">Analytics</span>
            </a>
            
            <a href="?page=profile" class="bottom-nav-item flex flex-col items-center space-y-1 p-2 <?= $currentPage === 'profile' ? 'active' : '' ?>">
                <div class="w-6 h-6 <?= $currentPage === 'profile' ? 'text-blue-600' : 'text-gray-400' ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                </div>
                <span class="text-xs font-medium <?= $currentPage === 'profile' ? 'text-blue-600' : 'text-gray-400' ?>">Profile</span>
            </a>
        </div>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        function refreshTasks() {
            location.reload();
        }

        function selectToday() {
            const today = new Date().toISOString().split('T')[0];
            changeDate(today);
        }

        function changeDate(date) {
            window.location.href = `?page=tasks&date=${date}`;
        }

        function openTaskDetail(taskId) {
            window.location.href = `task.php?id=${taskId}`;
        }

        function updateTaskStatus(taskId, status) {
            if (!confirm(`Change status to "${status}"?`)) return;
            
            fetch('api/tasks.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_status',
                    task_id: taskId,
                    status: status
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Failed to update status');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating status');
            });
        }

        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'login.php?logout=1';
            }
        }

        // Initialize progress chart if on analytics page
        <?php if ($currentPage === 'analytics'): ?>
        const ctx = document.getElementById('progressChart').getContext('2d');
        const progressChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Completed Tasks',
                    data: [3, 2, 5, 4, 6, 3, 2],
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { display: false } },
                    x: { grid: { display: false } }
                }
            }
        });
        <?php endif; ?>

        // Auto-refresh every 30 seconds
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>

<?php
function getPageTitle($page) {
    switch ($page) {
        case 'tasks': return "Today's Tasks";
        case 'analytics': return 'Analytics';
        case 'profile': return 'Profile';
        default: return 'Daily Calendar';
    }
}

function getStatusStyle($status) {
    switch ($status) {
        case 'Pending': return 'bg-yellow-100 text-yellow-700';
        case 'On Progress': return 'bg-blue-100 text-blue-700';
        case 'Done': return 'bg-green-100 text-green-700';
        case 'Approved': return 'bg-purple-100 text-purple-700';
        case 'On Hold': return 'bg-red-100 text-red-700';
        default: return 'bg-gray-100 text-gray-700';
    }
}

function getUserStats($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN status IN ('Done', 'Approved') THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'On Progress' THEN 1 ELSE 0 END) as in_progress
        FROM tasks 
        WHERE assigned_to = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$userId]);
    
    return $stmt->fetch() ?: ['completed' => 0, 'pending' => 0, 'in_progress' => 0];
}

if (isset($_GET['logout'])) {
    logout();
}
?>