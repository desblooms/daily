<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$currentPage = $_GET['page'] ?? 'today';
$selectedDate = $_GET['date'] ?? date('Y-m-d');

// If we have a date parameter, switch to day view to show specific date
if (isset($_GET['date']) && $_GET['date'] !== date('Y-m-d')) {
    $currentPage = 'day';
}

// Get user's tasks based on current page with DIRECT SQL to ensure proper date filtering
switch ($currentPage) {
    case 'today':
        $todayDate = date('Y-m-d');
        $stmt = $pdo->prepare("
            SELECT t.*, u.name as assigned_name, u.email as assigned_email 
            FROM tasks t 
            LEFT JOIN users u ON t.assigned_to = u.id 
            WHERE t.assigned_to = ? AND t.date = ? 
            ORDER BY t.priority = 'high' DESC, t.priority = 'medium' DESC, t.created_at DESC
        ");
        $stmt->execute([$_SESSION['user_id'], $todayDate]);
        $tasks = $stmt->fetchAll();
        $pageTitle = "Today's Tasks";
        $selectedDate = $todayDate; // Ensure selectedDate is set to today
        break;
        
    case 'day':
        // Show tasks for a specific selected date
        $stmt = $pdo->prepare("
            SELECT t.*, u.name as assigned_name, u.email as assigned_email 
            FROM tasks t 
            LEFT JOIN users u ON t.assigned_to = u.id 
            WHERE t.assigned_to = ? AND t.date = ? 
            ORDER BY t.priority = 'high' DESC, t.priority = 'medium' DESC, t.created_at DESC
        ");
        $stmt->execute([$_SESSION['user_id'], $selectedDate]);
        $tasks = $stmt->fetchAll();
        
        // Create friendly page title
        $dateObj = DateTime::createFromFormat('Y-m-d', $selectedDate);
        $today = new DateTime();
        $yesterday = new DateTime('yesterday');
        $tomorrow = new DateTime('tomorrow');
        
        if ($selectedDate === $today->format('Y-m-d')) {
            $pageTitle = "Today's Tasks";
        } elseif ($selectedDate === $yesterday->format('Y-m-d')) {
            $pageTitle = "Yesterday's Tasks";
        } elseif ($selectedDate === $tomorrow->format('Y-m-d')) {
            $pageTitle = "Tomorrow's Tasks";
        } else {
            $pageTitle = "Tasks for " . $dateObj->format('M j, Y');
        }
        break;
        
    case 'week':
        $tasks = getWeekTasks($_SESSION['user_id']);
        $pageTitle = "This Week";
        break;
        
    case 'all':
        $stmt = $pdo->prepare("
            SELECT t.*, u.name as assigned_name, u.email as assigned_email 
            FROM tasks t 
            LEFT JOIN users u ON t.assigned_to = u.id 
            WHERE t.assigned_to = ? 
            ORDER BY t.date DESC, t.priority = 'high' DESC, t.created_at DESC 
            LIMIT 100
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $tasks = $stmt->fetchAll();
        $pageTitle = "All Tasks";
        break;
        
    default:
        // Ensure we have a valid date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
            $selectedDate = date('Y-m-d');
        }
        
        $stmt = $pdo->prepare("
            SELECT t.*, u.name as assigned_name, u.email as assigned_email 
            FROM tasks t 
            LEFT JOIN users u ON t.assigned_to = u.id 
            WHERE t.assigned_to = ? AND t.date = ? 
            ORDER BY t.priority = 'high' DESC, t.priority = 'medium' DESC, t.created_at DESC
        ");
        $stmt->execute([$_SESSION['user_id'], $selectedDate]);
        $tasks = $stmt->fetchAll();
        $pageTitle = "Tasks for " . date('M j', strtotime($selectedDate));
}

// Debug: Log what we're showing (remove in production)
error_log("Index.php Debug: User {$_SESSION['user_id']}, Page: {$currentPage}, Date: {$selectedDate}, Tasks found: " . count($tasks));

// Get user stats
$userStats = getUserStats($_SESSION['user_id']);
$notifications = getUserNotifications($_SESSION['user_id'], true, 5);
?>
<!DOCTYPE html>
<html lang="en">
<head>


    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Daily Calendar</title>
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#3B82F6">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Daily Calendar">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="Daily Calendar">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="/manifest.json">
    
    <!-- PWA Icons -->
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/icons/icon-192x192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="/assets/icons/icon-512x512.png">
    <link rel="apple-touch-icon" href="/assets/icons/icon-192x192.png">
    
    <!-- Preload critical resources -->
    <link rel="preload" href="/assets/js/notification-manager.js" as="script">
    <link rel="preload" href="/sw.js" as="script">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .task-card {
            transition: all 0.2s ease;
        }
        .task-card:active {
            transform: scale(0.98);
        }
        .floating-action {
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
        }
        .slide-up {
            animation: slideUp 0.3s ease-out;
        }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .notification-dot {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
    <script>
    // Provide user context to JavaScript
    window.userRole = '<?= $_SESSION['role'] ?? 'user' ?>';
    window.userId = <?= $_SESSION['user_id'] ?? 'null' ?>;
    window.userName = '<?= htmlspecialchars($_SESSION['user_name'] ?? 'Unknown') ?>';
</script>
    <script src="assets/js/notification-manager.js?v=<?= time() ?>"></script>
</head>
<body class="bg-gray-50 min-h-screen pb-20">
    <!-- Header with Glass Effect -->
    <header class="sticky top-0 z-40 glass-effect border-b border-gray-200">
        <div class="px-4 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a2 2 0 012-2h4a2 2 0 012 2v4m-6 4l2 2 4-4"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-gray-900"><?= $pageTitle ?></h1>
                        <p class="text-xs text-gray-500"><?= date('l, F j, Y') ?></p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <!-- Notifications Bell -->
                    <button onclick="toggleNotifications()" class="relative p-2 rounded-lg hover:bg-gray-100 transition-colors">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        <?php if (count($notifications) > 0): ?>
                            <span class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full notification-dot"></span>
                        <?php endif; ?>
                    </button>
                    
                    <!-- Refresh Button -->
                    <button onclick="refreshTasks()" class="p-2 rounded-lg hover:bg-gray-100 transition-colors">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </button>
                    
                    <!-- User Avatar -->
                    <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center">
                        <span class="text-white font-semibold text-xs"><?= strtoupper(substr($_SESSION['user_name'], 0, 2)) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Quick Stats Bar -->
    <div class="px-4 py-3 bg-white border-b border-gray-100">
        <div class="grid grid-cols-4 gap-2">
            <div class="text-center">
                <p class="text-lg font-bold text-green-600"><?= $userStats['completed'] ?? 0 ?></p>
                <p class="text-xs text-gray-500">Done</p>
            </div>
            <div class="text-center">
                <p class="text-lg font-bold text-blue-600"><?= $userStats['in_progress'] ?? 0 ?></p>
                <p class="text-xs text-gray-500">Active</p>
            </div>
            <div class="text-center">
                <p class="text-lg font-bold text-yellow-600"><?= $userStats['pending'] ?? 0 ?></p>
                <p class="text-xs text-gray-500">Pending</p>
            </div>
            <div class="text-center">
                <p class="text-lg font-bold text-red-600"><?= $userStats['overdue'] ?? 0 ?></p>
                <p class="text-xs text-gray-500">Overdue</p>
            </div>
        </div>
    </div>

    <!-- Date Navigation (for date-specific views) -->
    <?php if ($currentPage !== 'all' && $currentPage !== 'week'): ?>
    <div class="px-4 py-3 bg-white">
        <div class="flex items-center justify-between">
            <button onclick="changeDate(-1)" class="p-2 rounded-lg bg-gray-100 hover:bg-gray-200 transition-colors">
                <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </button>
            
            <input type="date" 
                   value="<?= $selectedDate ?>" 
                   onchange="jumpToDate(this.value)"
                   class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            
            <button onclick="changeDate(1)" class="p-2 rounded-lg bg-gray-100 hover:bg-gray-200 transition-colors">
                <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Content Area -->
    <main class="px-4 py-4 space-y-3">
        <?php if (empty($tasks)): ?>
            <!-- Empty State -->
            <div class="bg-white rounded-2xl p-8 text-center shadow-sm slide-up">
                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">No tasks found</h3>
                <p class="text-sm text-gray-500 mb-4">You're all caught up! Check back later for new assignments.</p>
                <?php if ($currentPage === 'today'): ?>
                    <button onclick="showOtherDays()" class="px-4 py-2 bg-blue-500 text-white rounded-lg text-sm font-medium hover:bg-blue-600 transition-colors">
                        View Other Days
                    </button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Tasks List -->
            <div class="space-y-3">
                <?php foreach ($tasks as $index => $task): ?>
                    <div class="task-card bg-white rounded-2xl p-4 shadow-sm border border-gray-100 slide-up" 
                         style="animation-delay: <?= $index * 0.1 ?>s"
                         onclick="openTaskDetail(<?= $task['id'] ?>)">
                        
                        <!-- Task Header -->
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex-1">
                                <div class="flex items-center space-x-2 mb-1">
                                    <h4 class="font-semibold text-gray-900 text-sm line-clamp-1"><?= htmlspecialchars($task['title']) ?></h4>
                                    <?php if ($task['priority'] === 'high'): ?>
                                        <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($task['details'])): ?>
                                    <p class="text-xs text-gray-600 line-clamp-2 mb-2"><?= htmlspecialchars(substr($task['details'], 0, 100)) ?><?= strlen($task['details']) > 100 ? '...' : '' ?></p>
                                <?php endif; ?>
                            </div>
                            <span class="px-2 py-1 text-xs rounded-full font-medium ml-2 <?= getStatusStyle($task['status']) ?>">
                                <?= $task['status'] ?>
                            </span>
                        </div>
                        
                        <!-- Task Meta Info -->
                        <div class="flex items-center justify-between text-xs text-gray-500 mb-3">
                            <div class="flex items-center space-x-3">
                                <span class="flex items-center">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a2 2 0 012-2h4a2 2 0 012 2v4m-6 4l2 2 4-4"></path>
                                    </svg>
                                    <?= date('M j', strtotime($task['date'])) ?>
                                </span>
                                <?php if ($task['estimated_hours']): ?>
                                    <span class="flex items-center">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <?= $task['estimated_hours'] ?>h
                                    </span>
                                <?php endif; ?>
                            </div>
                            <span><?= timeAgo($task['created_at']) ?></span>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="flex items-center justify-between">
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
                                            class="px-2 py-1 bg-yellow-500 text-white text-xs rounded-lg hover:bg-yellow-600 transition-colors">
                                        Hold
                                    </button>
                                <?php elseif ($task['status'] === 'On Hold'): ?>
                                    <button onclick="event.stopPropagation(); updateTaskStatus(<?= $task['id'] ?>, 'On Progress')" 
                                            class="px-3 py-1 bg-blue-500 text-white text-xs rounded-lg hover:bg-blue-600 transition-colors">
                                        Resume
                                    </button>
                                <?php endif; ?>
                                
                                <!-- Request Reassignment -->
                                <button onclick="event.stopPropagation(); requestReassignment(<?= $task['id'] ?>, '<?= htmlspecialchars($task['title'], ENT_QUOTES) ?>')" 
                                        class="px-2 py-1 bg-purple-500 text-white text-xs rounded-lg hover:bg-purple-600 transition-colors" 
                                        title="Request Reassignment">
                                    <i class="fas fa-user-edit"></i>
                                </button>
                            </div>
                            
                            <!-- View Detail Arrow -->
                            <button onclick="openTaskDetail(<?= $task['id'] ?>)" class="p-1 rounded-lg hover:bg-gray-100 transition-colors">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Notifications Dropdown -->
    <div id="notificationsDropdown" class="fixed top-16 right-4 w-80 max-w-sm bg-white rounded-2xl shadow-xl border border-gray-200 z-50 hidden">
        <div class="p-4 border-b border-gray-100">
            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-gray-900">Notifications</h3>
                <button onclick="markAllAsRead()" class="text-xs text-blue-600 hover:text-blue-800">Mark all read</button>
            </div>
        </div>
        <div class="max-h-80 overflow-y-auto">
            <?php if (empty($notifications)): ?>
                <div class="p-4 text-center">
                    <p class="text-sm text-gray-500">No new notifications</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="p-4 border-b border-gray-50 hover:bg-gray-50 transition-colors">
                        <div class="flex items-start space-x-3">
                            <div class="w-2 h-2 bg-blue-500 rounded-full mt-2 flex-shrink-0"></div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($notification['title']) ?></p>
                                <p class="text-xs text-gray-600 mt-1"><?= htmlspecialchars($notification['message']) ?></p>
                                <p class="text-xs text-gray-400 mt-1"><?= timeAgo($notification['created_at']) ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 px-4 py-2 glass-effect">
        <div class="flex justify-around">
            <a href="?page=today" class="flex flex-col items-center space-y-1 p-2 <?= ($currentPage === 'today' || $currentPage === 'day') ? 'text-blue-600' : 'text-gray-400' ?> transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a2 2 0 012-2h4a2 2 0 012 2v4m-6 4l2 2 4-4"></path>
                </svg>
                <span class="text-xs font-medium">Today</span>
            </a>
            
            <a href="?page=week" class="flex flex-col items-center space-y-1 p-2 <?= $currentPage === 'week' ? 'text-blue-600' : 'text-gray-400' ?> transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a2 2 0 012-2h4a2 2 0 012 2v4m-6 4l2 2 4-4"></path>
                </svg>
                <span class="text-xs font-medium">Week</span>
            </a>
            
            <a href="?page=all" class="flex flex-col items-center space-y-1 p-2 <?= $currentPage === 'all' ? 'text-blue-600' : 'text-gray-400' ?> transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                <span class="text-xs font-medium">All</span>
            </a>
            
            <a href="my-leads.php" class="flex flex-col items-center space-y-1 p-2 text-gray-400 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <span class="text-xs font-medium">Leads</span>
            </a>
            
            <a href="profile.php" class="flex flex-col items-center space-y-1 p-2 text-gray-400 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
                <span class="text-xs font-medium">Profile</span>
            </a>
        </div>
    </nav>

    <script>
        // Global variables
        let currentDate = new Date('<?= $selectedDate ?>');
        
        // Functions
        function refreshTasks() {
            location.reload();
        }

        function openTaskDetail(taskId) {
            window.location.href = `task.php?id=${taskId}`;
        }

        function updateTaskStatus(taskId, status) {
            if (!confirm(`Change status to "${status}"?`)) return;
            
            // Add loading state
            const button = event.target;
            const originalText = button.textContent;
            button.textContent = '...';
            button.disabled = true;
            
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
                    // Show success feedback
                    button.textContent = '✓';
                    button.className = button.className.replace(/bg-\w+-500/, 'bg-green-500');
                    
                    // Reload after short delay
                    setTimeout(() => location.reload(), 500);
                } else {
                    alert('Failed to update status: ' + (data.message || 'Unknown error'));
                    button.textContent = originalText;
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating status');
                button.textContent = originalText;
                button.disabled = false;
            });
        }

        function changeDate(days) {
            currentDate.setDate(currentDate.getDate() + days);
            const dateString = currentDate.toISOString().split('T')[0];
            const today = new Date().toISOString().split('T')[0];
            
            // If navigating to today, use 'today' page, otherwise use 'day' page
            if (dateString === today) {
                window.location.href = `?page=today`;
            } else {
                window.location.href = `?page=day&date=${dateString}`;
            }
        }

        function jumpToDate(date) {
            const today = new Date().toISOString().split('T')[0];
            
            // If jumping to today, use 'today' page, otherwise use 'day' page
            if (date === today) {
                window.location.href = `?page=today`;
            } else {
                window.location.href = `?page=day&date=${date}`;
            }
        }

        function showOtherDays() {
            window.location.href = '?page=all';
        }

        function toggleNotifications() {
            const dropdown = document.getElementById('notificationsDropdown');
            dropdown.classList.toggle('hidden');
            
            // Close when clicking outside
            if (!dropdown.classList.contains('hidden')) {
                setTimeout(() => {
                    document.addEventListener('click', function closeDropdown(e) {
                        if (!dropdown.contains(e.target) && !e.target.closest('button[onclick="toggleNotifications()"]')) {
                            dropdown.classList.add('hidden');
                            document.removeEventListener('click', closeDropdown);
                        }
                    });
                }, 100);
            }
        }

        function markAllAsRead() {
            fetch('api/notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'mark_all_read' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('notificationsDropdown').classList.add('hidden');
                    location.reload();
                }
            });
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Left/Right arrows for date navigation
            if (e.key === 'ArrowLeft' && e.ctrlKey) {
                e.preventDefault();
                changeDate(-1);
            } else if (e.key === 'ArrowRight' && e.ctrlKey) {
                e.preventDefault();
                changeDate(1);
            }
            // R for refresh
            else if (e.key === 'r' || e.key === 'R') {
                if (!e.target.matches('input, textarea')) {
                    e.preventDefault();
                    refreshTasks();
                }
            }
        });

        // Pull to refresh (touch devices)
        let startY = 0;
        let pullDistance = 0;
        
        document.addEventListener('touchstart', function(e) {
            startY = e.touches[0].clientY;
        });
        
        document.addEventListener('touchmove', function(e) {
            if (window.scrollY === 0) {
                pullDistance = e.touches[0].clientY - startY;
                if (pullDistance > 0) {
                    e.preventDefault();
                    // Visual feedback could be added here
                }
            }
        });
        
        document.addEventListener('touchend', function(e) {
            if (pullDistance > 100) {
                refreshTasks();
            }
            pullDistance = 0;
        });

        // Auto-refresh every 2 minutes
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                location.reload();
            }
        }, 120000);

        function requestReassignment(taskId, taskTitle) {
            const reason = prompt(`Request reassignment for "${taskTitle}"?\n\nPlease provide a reason (optional):`);
            
            // User cancelled
            if (reason === null) return;
            
            // Add loading state to button
            const button = event.target.closest('button');
            const originalHTML = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            button.disabled = true;
            
            // Create notification for admins
            fetch('api/notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: 'create_admin_notification',
                    title: 'Reassignment Request',
                    message: `${window.userName} requests reassignment of task: "${taskTitle}"`,
                    type: 'warning',
                    related_type: 'task',
                    related_id: taskId,
                    details: {
                        task_id: taskId,
                        task_title: taskTitle,
                        requester_id: window.userId,
                        requester_name: window.userName,
                        reason: reason || 'No reason provided',
                        request_type: 'reassignment'
                    }
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Reassignment request sent to administrators successfully!');
                } else {
                    alert('Error: ' + (data.message || 'Failed to send request'));
                }
                
                // Restore button
                button.innerHTML = originalHTML;
                button.disabled = false;
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error. Please try again.');
                
                // Restore button
                button.innerHTML = originalHTML;
                button.disabled = false;
            });
        }

        // Show loading state on navigation
        document.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function() {
                if (this.href && !this.href.includes('#')) {
                    this.style.opacity = '0.6';
                }
            });
        });
    </script>
</body>
</html>

<?php
// Helper functions
function getStatusStyle($status) {
    switch ($status) {
        case 'Pending': return 'bg-yellow-100 text-yellow-700 border-yellow-200';
        case 'On Progress': return 'bg-blue-100 text-blue-700 border-blue-200';
        case 'Done': return 'bg-green-100 text-green-700 border-green-200';
        case 'Approved': return 'bg-purple-100 text-purple-700 border-purple-200';
        case 'On Hold': return 'bg-red-100 text-red-700 border-red-200';
        default: return 'bg-gray-100 text-gray-700 border-gray-200';
    }
}

function getWeekTasks($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT t.*, u.name as assigned_name, c.name as created_name
        FROM tasks t 
        LEFT JOIN users u ON t.assigned_to = u.id 
        LEFT JOIN users c ON t.created_by = c.id
        WHERE t.assigned_to = ? 
        AND t.date >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
        AND t.date <= DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 6 DAY)
        ORDER BY t.date ASC, 
        CASE WHEN t.status = 'On Progress' THEN 1
             WHEN t.status = 'Pending' THEN 2
             WHEN t.status = 'Done' THEN 3
             ELSE 4 END
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

if (isset($_GET['logout'])) {
    logout();
}
?>