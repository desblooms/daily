<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$currentView = $_GET['view'] ?? 'dashboard';
$selectedDate = $_GET['date'] ?? date('Y-m-d');

// Get analytics data
$analytics = getAnalytics($selectedDate);
$todayTasks = getTasks(null, $selectedDate);
$users = getAllUsers();

// Get recent activities
$recentActivities = getRecentActivities(20);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Daily Calendar</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        clickup: '#7B68EE',
                    }
                }
            }
        }
    </script>
<script src="assets/js/global-task-manager.js"></script>


    <style>
        .sidebar-hidden { transform: translateX(-100%); }
        .clickup-gradient { background: linear-gradient(135deg, #7B68EE 0%, #9F7AEA 100%); }
        .glass-morphism {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
        .hover-lift:hover { transform: translateY(-2px); }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
        }

/* Fixed chart container heights */
        .chart-container {
            position: relative;
            height: 300px !important;
            width: 100%;
        }
        
        .chart-container canvas {
            max-height: 300px !important;
        }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .chart-container {
                height: 250px !important;
            }
            .chart-container canvas {
                max-height: 250px !important;
            }
        }
    </style>

    <script>
    // Provide user context to JavaScript
    window.userRole = '<?= $_SESSION['role'] ?? 'user' ?>';
    window.userId = <?= $_SESSION['user_id'] ?? 'null' ?>;
    window.userName = '<?= htmlspecialchars($_SESSION['user_name'] ?? 'Unknown') ?>';
</script>
</head>
<body class="bg-gray-50">
    <?php 
    // Include the global header AFTER the scripts are loaded
    include 'includes/global-header.php'; 
    ?>
    <!-- Mobile Header -->
    <div class="md:hidden bg-white shadow-sm border-b">
        <div class="flex items-center justify-between p-4">
            <button onclick="toggleSidebar()" class="p-2 rounded-lg hover:bg-gray-100">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            <h1 class="text-lg font-bold">Admin Dashboard</h1>
            <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center">
                <span class="text-white font-semibold text-xs"><?= strtoupper(substr($_SESSION['user_name'], 0, 2)) ?></span>
            </div>
        </div>
    </div>

    <div class="flex h-screen">
        <!-- Sidebar -->
        <aside id="sidebar" class="sidebar fixed md:relative z-50 w-64 bg-white h-full shadow-lg border-r transition-transform duration-300">
            <!-- Sidebar Header -->
            <div class="clickup-gradient p-6 text-white">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-white bg-opacity-20 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold">Daily Calendar</h2>
                        <p class="text-sm opacity-80">Admin Panel</p>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="p-4 space-y-2">
                <a href="?view=dashboard" class="nav-item flex items-center space-x-3 p-3 rounded-xl transition-all <?= $currentView === 'dashboard' ? 'bg-purple-50 text-purple-700 border-l-4 border-purple-500' : 'text-gray-600 hover:bg-gray-50' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a2 2 0 012-2h4a2 2 0 012 2v4m-6 4l2 2 4-4"></path>
                    </svg>
                    <span class="font-medium">Dashboard</span>
                </a>

                <a href="?view=tasks" class="nav-item flex items-center space-x-3 p-3 rounded-xl transition-all <?= $currentView === 'tasks' ? 'bg-purple-50 text-purple-700 border-l-4 border-purple-500' : 'text-gray-600 hover:bg-gray-50' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                    <span class="font-medium">Task Management</span>
                </a>

                <a href="?view=analytics" class="nav-item flex items-center space-x-3 p-3 rounded-xl transition-all <?= $currentView === 'analytics' ? 'bg-purple-50 text-purple-700 border-l-4 border-purple-500' : 'text-gray-600 hover:bg-gray-50' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    <span class="font-medium">Analytics</span>
                </a>

                <a href="?view=users" class="nav-item flex items-center space-x-3 p-3 rounded-xl transition-all <?= $currentView === 'users' ? 'bg-purple-50 text-purple-700 border-l-4 border-purple-500' : 'text-gray-600 hover:bg-gray-50' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                    </svg>
                    <span class="font-medium">Team Members</span>
                </a>

                <a href="admin-password-management.php" class="nav-item flex items-center space-x-3 p-3 rounded-xl transition-all text-gray-600 hover:bg-gray-50">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                    <span class="font-medium">Security</span>
                </a>

                <div class="border-t border-gray-200 pt-4 mt-4">
                    <a href="change-password.php" class="nav-item flex items-center space-x-3 p-3 rounded-xl transition-all text-gray-600 hover:bg-gray-50">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <span class="font-medium">Settings</span>
                    </a>

                    <button onclick="logout()" class="nav-item w-full flex items-center space-x-3 p-3 rounded-xl transition-all text-red-600 hover:bg-red-50">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        <span class="font-medium">Logout</span>
                    </button>
                </div>
            </nav>
        </aside>

        <!-- Overlay for mobile -->
        <div id="overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 md:hidden hidden" onclick="toggleSidebar()"></div>

        <!-- Main Content -->
        <main class="flex-1 overflow-hidden">
            <div class="h-full overflow-y-auto">
                <!-- Desktop Header -->
                <header class="hidden md:block bg-white shadow-sm border-b p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900"><?= getViewTitle($currentView) ?></h1>
                            <p class="text-sm text-gray-500"><?= date('l, F j, Y', strtotime($selectedDate)) ?></p>
                        </div>
                        <div class="flex items-center space-x-4">
                            <input type="date" value="<?= $selectedDate ?>" onchange="changeDate(this.value)" 
                                   class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                            <button onclick="refreshData()" class="p-2 bg-purple-100 text-purple-600 rounded-lg hover:bg-purple-200 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                            </button>
                            <div class="w-10 h-10 bg-purple-500 rounded-full flex items-center justify-center">
                                <span class="text-white font-bold"><?= strtoupper(substr($_SESSION['user_name'], 0, 2)) ?></span>
                            </div>
                        </div>
                    </div>
                </header>

                <!-- Content Area -->
                <div class="p-4 md:p-6 space-y-6">
                    <?php if ($currentView === 'dashboard'): ?>
                        <!-- Stats Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
                            <div class="bg-white rounded-2xl p-6 shadow-sm border hover-lift transition-all">
                                <div class="flex items-center space-x-4">
                                    <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-2xl font-bold text-gray-900"><?= $analytics['Done'] + $analytics['Approved'] ?></p>
                                        <p class="text-sm text-gray-500">Completed Today</p>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white rounded-2xl p-6 shadow-sm border hover-lift transition-all">
                                <div class="flex items-center space-x-4">
                                    <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center">
                                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-2xl font-bold text-gray-900"><?= $analytics['Pending'] ?></p>
                                        <p class="text-sm text-gray-500">Pending Tasks</p>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white rounded-2xl p-6 shadow-sm border hover-lift transition-all">
                                <div class="flex items-center space-x-4">
                                    <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-2xl font-bold text-gray-900"><?= $analytics['On Progress'] ?></p>
                                        <p class="text-sm text-gray-500">In Progress</p>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white rounded-2xl p-6 shadow-sm border hover-lift transition-all">
                                <div class="flex items-center space-x-4">
                                    <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center">
                                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-2xl font-bold text-gray-900"><?= $analytics['On Hold'] ?></p>
                                        <p class="text-sm text-gray-500">On Hold</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Charts Row -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Task Distribution Chart -->
                            <div class="bg-white rounded-2xl p-6 shadow-sm border">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">Task Distribution</h3>
                                <div class="h-64">
                                    <canvas id="distributionChart"></canvas>
                                </div>
                            </div>

                            <!-- Weekly Progress Chart -->
                            <div class="bg-white rounded-2xl p-6 shadow-sm border">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">Weekly Progress</h3>
                                <div class="h-64">
                                    <canvas id="weeklyChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Today's Tasks and Recent Activity -->
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <!-- Today's Tasks -->
                            <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border">
                                <div class="p-6 border-b border-gray-200">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-lg font-semibold text-gray-900">Today's Tasks</h3>
                                        <button onclick="globalTaskManager.openAddTaskModal()" class="px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition-colors text-sm">
                                            + Add Task
                                        </button>

                                 
                                    </div>
                                </div>
                                <div class="max-h-96 overflow-y-auto">
                                    <?php if (empty($todayTasks)): ?>
                                        <div class="p-8 text-center">
                                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                </svg>
                                            </div>
                                            <p class="text-gray-500">No tasks for today</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="divide-y divide-gray-100">
                                            <?php foreach ($todayTasks as $task): ?>
                                                <div class="p-4 hover:bg-gray-50 transition-colors">
                                                    <div class="flex items-center justify-between">
                                                        <div class="flex-1">
                                                            <div class="flex items-center space-x-3 mb-2">
                                                                <h4 class="font-medium text-gray-900"><?= htmlspecialchars($task['title']) ?></h4>
                                                                <span class="px-2 py-1 text-xs rounded-full font-medium <?= getStatusStyle($task['status']) ?>">
                                                                    <?= $task['status'] ?>
                                                                </span>
                                                            </div>
                                                            <p class="text-sm text-gray-600 mb-2">Assigned to: <?= htmlspecialchars($task['assigned_name']) ?></p>
                                                            <?php if (!empty($task['details'])): ?>
                                                                <p class="text-xs text-gray-500"><?= htmlspecialchars(substr($task['details'], 0, 100)) ?>...</p>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="flex items-center space-x-2">
                                                            <?php if ($task['status'] === 'Done'): ?>
                                                                <button onclick="approveTask(<?= $task['id'] ?>)" 
                                                                        class="px-3 py-1 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition-colors text-sm">
                                                                    Approve
                                                                </button>
                                                            <?php endif; ?>
                                                            <button onclick="viewTask(<?= $task['id'] ?>)" 
                                                                    class="p-2 text-gray-400 hover:text-gray-600 transition-colors">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Recent Activity -->
                            <div class="bg-white rounded-2xl shadow-sm border">
                                <div class="p-6 border-b border-gray-200">
                                    <h3 class="text-lg font-semibold text-gray-900">Recent Activity</h3>
                                </div>
                                <div class="max-h-96 overflow-y-auto p-4">
                                    <div class="space-y-4">
                                        <?php foreach ($recentActivities as $activity): ?>
                                            <div class="flex items-start space-x-3">
                                                <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center flex-shrink-0">
                                                    <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm text-gray-900"><?= htmlspecialchars($activity['description']) ?></p>
                                                    <p class="text-xs text-gray-500"><?= timeAgo($activity['timestamp']) ?></p>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php elseif ($currentView === 'tasks'): ?>
                        <!-- Task Management View -->
                        <div class="bg-white rounded-2xl shadow-sm border">
                            <div class="p-6 border-b border-gray-200">
                                <div class="flex flex-col md:flex-row md:items-center justify-between space-y-4 md:space-y-0">
                                    <h3 class="text-lg font-semibold text-gray-900">Task Management</h3>
                                    <div class="flex items-center space-x-4">
                                        <select class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                                            <option>All Status</option>
                                            <option>Pending</option>
                                            <option>On Progress</option>
                                            <option>Done</option>
                                            <option>Approved</option>
                                            <option>On Hold</option>
                                        </select>
                                        <button onclick="globalTaskManager.openAddTaskModal()" class="px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition-colors">
                                            + Add Task
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead class="bg-gray-50 border-b border-gray-200">
                                        <tr>
                                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Task</th>
                                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned To</th>
                                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($todayTasks as $task): ?>
                                            <tr class="hover:bg-gray-50 transition-colors">
                                                <td class="px-6 py-4">
                                                    <div>
                                                        <p class="font-medium text-gray-900"><?= htmlspecialchars($task['title']) ?></p>
                                                        <?php if (!empty($task['details'])): ?>
                                                            <p class="text-sm text-gray-500"><?= htmlspecialchars(substr($task['details'], 0, 50)) ?>...</p>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center">
                                                        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center mr-3">
                                                            <span class="text-white font-semibold text-xs"><?= strtoupper(substr($task['assigned_name'], 0, 2)) ?></span>
                                                        </div>
                                                        <span class="text-sm text-gray-900"><?= htmlspecialchars($task['assigned_name']) ?></span>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span class="px-3 py-1 text-xs rounded-full font-medium <?= getStatusStyle($task['status']) ?>">
                                                        <?= $task['status'] ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-500">
                                                    <?= date('M j, Y', strtotime($task['date'])) ?>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center space-x-2">
                                                        <button onclick="viewTask(<?= $task['id'] ?>)" 
                                                                class="p-1 text-gray-400 hover:text-gray-600 transition-colors"
                                                                title="View Task">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                            </svg>
                                                        </button>
                                                        <button onclick="globalTaskManager.deleteTask(<?= $task['id'] ?>, '<?= htmlspecialchars($task['title'], ENT_QUOTES) ?>')" 
                                                                class="p-1 text-red-400 hover:text-red-600 transition-colors"
                                                                title="Delete Task">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                            </svg>
                                                        </button>
                                                        <?php if ($task['status'] === 'Done'): ?>
                                                            <button onclick="approveTask(<?= $task['id'] ?>)" 
                                                                    class="px-2 py-1 bg-purple-500 text-white rounded text-xs hover:bg-purple-600 transition-colors">
                                                                Approve
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    <?php elseif ($currentView === 'analytics'): ?>
                        <!-- Analytics View -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div class="bg-white rounded-2xl p-6 shadow-sm border">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">Performance Metrics</h3>
                                  <div class="chart-container">
                                <canvas id="performanceChart" height="200"></canvas>
                  
                            </div>
                            </div>
                            <div class="bg-white rounded-2xl p-6 shadow-sm border">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">Team Productivity</h3>
                                <div class="chart-container">
                                <canvas id="productivityChart" height="200"></canvas>
                                  </div>
                            </div>
                        

                    <?php elseif ($currentView === 'users'): ?>
                        <!-- Users Management View -->
                        <div class="bg-white rounded-2xl shadow-sm border">
                            <div class="p-6 border-b border-gray-200">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-semibold text-gray-900">Team Members</h3>
                                    <button class="px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition-colors">
                                        + Add Member
                                    </button>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 p-6">
                                <?php foreach ($users as $user): ?>
                                    <div class="bg-gray-50 rounded-xl p-6 hover:shadow-md transition-all">
                                        <div class="text-center">
                                            <div class="w-16 h-16 bg-gradient-to-r from-purple-400 to-blue-500 rounded-full flex items-center justify-center mx-auto mb-4">
                                                <span class="text-white font-bold text-lg"><?= strtoupper(substr($user['name'], 0, 2)) ?></span>
                                            </div>
                                            <h4 class="font-semibold text-gray-900 mb-1"><?= htmlspecialchars($user['name']) ?></h4>
                                            <p class="text-sm text-gray-500 mb-2"><?= htmlspecialchars($user['email']) ?></p>
                                            <span class="px-3 py-1 text-xs rounded-full font-medium <?= $user['role'] === 'admin' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' ?>">
                                                <?= ucfirst($user['role']) ?>
                                            </span>
                                            
                                            <div class="mt-4 pt-4 border-t border-gray-200">
                                                <div class="grid grid-cols-2 gap-4 text-center">
                                                    <div>
                                                        <p class="text-lg font-bold text-gray-900"><?= getUserTaskCount($user['id'], 'completed') ?></p>
                                                        <p class="text-xs text-gray-500">Completed</p>
                                                    </div>
                                                    <div>
                                                        <p class="text-lg font-bold text-gray-900"><?= getUserTaskCount($user['id'], 'pending') ?></p>
                                                        <p class="text-xs text-gray-500">Pending</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Mobile Bottom Navigation (only show on mobile) -->
    <nav class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 px-4 py-2">
        <div class="flex justify-around">
            <a href="?view=dashboard" class="flex flex-col items-center space-y-1 p-2 <?= $currentView === 'dashboard' ? 'text-purple-600' : 'text-gray-400' ?>">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                </svg>
                <span class="text-xs font-medium">Dashboard</span>
            </a>
            
            <a href="?view=tasks" class="flex flex-col items-center space-y-1 p-2 <?= $currentView === 'tasks' ? 'text-purple-600' : 'text-gray-400' ?>">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                <span class="text-xs font-medium">Tasks</span>
            </a>
            
            <a href="?view=analytics" class="flex flex-col items-center space-y-1 p-2 <?= $currentView === 'analytics' ? 'text-purple-600' : 'text-gray-400' ?>">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                <span class="text-xs font-medium">Analytics</span>
            </a>
            
            <a href="?view=users" class="flex flex-col items-center space-y-1 p-2 <?= $currentView === 'users' ? 'text-purple-600' : 'text-gray-400' ?>">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                </svg>
                <span class="text-xs font-medium">Team</span>
            </a>
        </div>
    </nav>

    <script>
        // Sidebar toggle for mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            
            sidebar.classList.toggle('open');
            overlay.classList.toggle('hidden');
        }

        // Functions
        function refreshData() {
            location.reload();
        }

        function changeDate(date) {
            window.location.href = `?view=<?= $currentView ?>&date=${date}`;
        }

        function viewTask(taskId) {
            window.location.href = `task.php?id=${taskId}`;
        }

        function approveTask(taskId) {
            if (!confirm('Approve this task?')) return;
            
            fetch('api/tasks.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'approve',
                    task_id: taskId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Failed to approve task');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error approving task');
            });
        }

        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'login.php?logout=1';
            }
        }

        // Initialize Charts
        <?php if ($currentView === 'dashboard'): ?>
        // Distribution Chart
        const distributionCtx = document.getElementById('distributionChart').getContext('2d');
        const distributionChart = new Chart(distributionCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'In Progress', 'Done', 'Approved', 'On Hold'],
                datasets: [{
                    data: [<?= $analytics['Pending'] ?>, <?= $analytics['On Progress'] ?>, 
                           <?= $analytics['Done'] ?>, <?= $analytics['Approved'] ?>, <?= $analytics['On Hold'] ?>],
                    backgroundColor: ['#FEF3C7', '#DBEAFE', '#D1FAE5', '#E9D5FF', '#FEE2E2'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { 
                            padding: 20,
                            font: { size: 12 }
                        }
                    }
                }
            }
        });

        // Weekly Chart
        const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
        const weeklyChart = new Chart(weeklyCtx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Completed Tasks',
                    data: [5, 8, 6, 9, 7, 4, 3],
                    borderColor: '#7B68EE',
                    backgroundColor: 'rgba(123, 104, 238, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#7B68EE',
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
                        ticks: { font: { size: 12 } }
                    },
                    x: { 
                        grid: { display: false },
                        ticks: { font: { size: 12 } }
                    }
                }
            }
        });
        <?php endif; ?>

        <?php if ($currentView === 'analytics'): ?>
        // Performance Chart
        const performanceCtx = document.getElementById('performanceChart').getContext('2d');
        const performanceChart = new Chart(performanceCtx, {
            type: 'bar',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                datasets: [{
                    label: 'Tasks Completed',
                    data: [25, 32, 28, 35],
                    backgroundColor: '#7B68EE',
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true },
                    x: { grid: { display: false } }
                }
            }
        });

        // Productivity Chart
        const productivityCtx = document.getElementById('productivityChart').getContext('2d');
        const productivityChart = new Chart(productivityCtx, {
            type: 'radar',
            data: {
                labels: ['Quality', 'Speed', 'Accuracy', 'Teamwork', 'Innovation'],
                datasets: [{
                    label: 'Team Performance',
                    data: [85, 75, 90, 80, 70],
                    backgroundColor: 'rgba(123, 104, 238, 0.2)',
                    borderColor: '#7B68EE',
                    pointBackgroundColor: '#7B68EE'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    r: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
        <?php endif; ?>

        // Auto-refresh every 60 seconds
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                location.reload();
            }
        }, 60000);

        // Fallback function for removeFromHold if global-task-manager.js doesn't load
        if (typeof removeFromHold === 'undefined') {
            function removeFromHold(taskId) {
                // Simple fallback - directly resume to "On Progress"
                if (confirm('Resume this task to "On Progress" status?')) {
                    fetch('api/tasks.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            action: 'update_status',
                            task_id: taskId,
                            status: 'On Progress',
                            comments: 'Task resumed from hold status'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Task status updated successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + (data.message || 'Failed to update task status'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Network error. Please try again.');
                    });
                }
            }
        }
    </script>
</body>
</html>

<?php
function getViewTitle($view) {
    switch ($view) {
        case 'dashboard': return 'Dashboard Overview';
        case 'tasks': return 'Task Management';
        case 'analytics': return 'Analytics & Reports';
        case 'users': return 'Team Management';
        default: return 'Admin Dashboard';
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

function getUserTaskCount($userId, $type) {
    global $pdo;
    
    if ($type === 'completed') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status IN ('Done', 'Approved')");
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = 'Pending'");
    }
    
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}

if (isset($_GET['logout'])) {
    logout();
}
?>