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

// Get user's enhanced task data
function getUserEnhancedData($userId, $date = null) {
    global $pdo;
    
    $dateCondition = $date ? "AND t.date = ?" : "";
    $params = $date ? [$userId, $date] : [$userId];
    
    // Get tasks with enhanced information
    $stmt = $pdo->prepare("
        SELECT t.*, 
               u.name as assigned_name, 
               u.email as assigned_email,
               u.avatar as assigned_avatar,
               c.name as created_by_name,
               COUNT(DISTINCT ta.id) as attachment_count,
               COUNT(DISTINCT two.id) as output_count,
               COUNT(DISTINCT tpu.id) as progress_count
        FROM tasks t 
        LEFT JOIN users u ON t.assigned_to = u.id 
        LEFT JOIN users c ON t.created_by = c.id
        LEFT JOIN task_attachments ta ON t.id = ta.task_id
        LEFT JOIN task_work_outputs two ON t.id = two.task_id
        LEFT JOIN task_progress_updates tpu ON t.id = tpu.task_id
        WHERE t.assigned_to = ? $dateCondition
        GROUP BY t.id
        ORDER BY t.priority = 'high' DESC, t.priority = 'medium' DESC, t.created_at DESC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get user statistics
function getUserStatistics($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_tasks,
            COUNT(CASE WHEN t.status = 'Pending' THEN 1 END) as pending_tasks,
            COUNT(CASE WHEN t.status = 'On Progress' THEN 1 END) as active_tasks,
            COUNT(CASE WHEN t.status = 'Done' THEN 1 END) as completed_tasks,
            COUNT(CASE WHEN t.status = 'Approved' THEN 1 END) as approved_tasks,
            COUNT(CASE WHEN t.date < CURDATE() AND t.status NOT IN ('Done', 'Approved') THEN 1 END) as overdue_tasks,
            COUNT(CASE WHEN t.date = CURDATE() THEN 1 END) as today_tasks,
            COUNT(CASE WHEN DATE(t.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as week_tasks,
            AVG(CASE WHEN t.status IN ('Done', 'Approved') THEN t.estimated_hours END) as avg_completion_time,
            COUNT(DISTINCT ta.id) as total_attachments,
            COUNT(DISTINCT two.id) as total_outputs
        FROM tasks t
        LEFT JOIN task_attachments ta ON t.id = ta.task_id
        LEFT JOIN task_work_outputs two ON t.id = two.task_id
        WHERE t.assigned_to = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get tasks based on current page
switch ($currentPage) {
    case 'today':
        $todayDate = date('Y-m-d');
        $tasks = getUserEnhancedData($_SESSION['user_id'], $todayDate);
        $pageTitle = "Today's Tasks";
        $selectedDate = $todayDate;
        break;
        
    case 'day':
        $tasks = getUserEnhancedData($_SESSION['user_id'], $selectedDate);
        $dateObj = DateTime::createFromFormat('Y-m-d', $selectedDate);
        $today = new DateTime();
        
        if ($selectedDate === $today->format('Y-m-d')) {
            $pageTitle = "Today's Tasks";
        } else {
            $pageTitle = "Tasks for " . $dateObj->format('M j, Y');
        }
        break;
        
    case 'week':
        $tasks = getUserEnhancedData($_SESSION['user_id']);
        $pageTitle = "All My Tasks";
        break;
        
    default:
        $tasks = getUserEnhancedData($_SESSION['user_id'], $selectedDate);
        $pageTitle = "My Tasks";
}

$userStats = getUserStatistics($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tasks - Enhanced Daily Calendar</title>
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
                        clickup: '#7B68EE',
                    }
                }
            }
        }
    </script>

    <style>
        .clickup-gradient { background: linear-gradient(135deg, #7B68EE 0%, #9F7AEA 100%); }
        .glass-morphism {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
        .hover-lift:hover { transform: translateY(-2px); }
        
        .task-priority-high { border-left: 4px solid #ef4444; }
        .task-priority-medium { border-left: 4px solid #f59e0b; }
        .task-priority-low { border-left: 4px solid #10b981; }
        
        .progress-ring-bg { stroke: #e5e7eb; }
        .progress-ring { stroke: #3b82f6; stroke-linecap: round; }
        
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .slide-in {
            animation: slideIn 0.3s ease-out;
        }
        
        @media (max-width: 768px) {
            .mobile-card-stack .task-card:not(:first-child) {
                margin-top: -10px;
            }
        }
    </style>

    <script>
        window.userRole = '<?= $_SESSION['role'] ?? 'user' ?>';
        window.userId = <?= $_SESSION['user_id'] ?? 'null' ?>;
        window.userName = '<?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?>';
        window.selectedDate = '<?= $selectedDate ?>';
        window.isAdmin = false;
        
        console.log('Enhanced User Dashboard initialized:', {
            userRole: window.userRole,
            userId: window.userId,
            userName: window.userName,
            selectedDate: window.selectedDate
        });
    </script>
</head>

<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">
    <!-- Header -->
    <nav class="bg-white shadow-lg sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <!-- Logo & Title -->
                <div class="flex items-center space-x-3">
                    <div class="clickup-gradient p-2 rounded-lg">
                        <i class="fas fa-calendar-check text-white text-lg"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-800">My Tasks</h1>
                        <p class="text-sm text-gray-600"><?= $pageTitle ?></p>
                    </div>
                </div>

                <!-- Navigation & Controls -->
                <div class="flex items-center space-x-4">
                    <!-- Date Navigation -->
                    <div class="hidden md:flex items-center space-x-2">
                        <button onclick="changeDate(-1)" class="p-2 text-gray-600 hover:bg-gray-100 rounded-md">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <input type="date" id="task-date-picker" value="<?= $selectedDate ?>" 
                            class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button onclick="changeDate(1)" class="p-2 text-gray-600 hover:bg-gray-100 rounded-md">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>

                    <!-- Quick Actions -->
                    <div class="flex items-center space-x-2">
                        <div class="relative">
                            <button onclick="showNotificationPanel()" class="p-2 text-gray-600 hover:bg-gray-100 rounded-md relative">
                                <i class="fas fa-bell"></i>
                                <span class="notification-badge">3</span>
                            </button>
                        </div>
                        
                        <button onclick="refreshTasks()" class="p-2 text-gray-600 hover:bg-gray-100 rounded-md" title="Refresh">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        
                        <!-- User Avatar Menu -->
                        <div class="relative">
                            <button onclick="toggleUserMenu()" class="flex items-center space-x-2 text-gray-700 hover:bg-gray-100 p-2 rounded-md">
                                <img src="assets/img/default-avatar.png" alt="Avatar" class="w-8 h-8 rounded-full">
                                <span class="hidden md:block text-sm font-medium"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></span>
                                <i class="fas fa-chevron-down text-xs"></i>
                            </button>
                            <!-- Dropdown menu will be added by JS -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- User Statistics Dashboard -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Today's Tasks -->
            <div class="bg-white rounded-xl shadow-md p-6 hover-lift transition-all">
                <div class="flex items-center justify-between mb-4">
                    <div class="p-3 rounded-lg bg-blue-100">
                        <i class="fas fa-calendar-day text-blue-600 text-xl"></i>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-600">Today's Tasks</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $userStats['today_tasks'] ?? 0 ?></p>
                    </div>
                </div>
                <div class="flex items-center text-sm text-gray-600">
                    <div class="flex items-center space-x-4">
                        <span class="text-orange-600">📋 Active</span>
                        <span class="text-green-600">✅ Done</span>
                    </div>
                </div>
            </div>

            <!-- In Progress -->
            <div class="bg-white rounded-xl shadow-md p-6 hover-lift transition-all">
                <div class="flex items-center justify-between mb-4">
                    <div class="p-3 rounded-lg bg-orange-100">
                        <i class="fas fa-spinner text-orange-600 text-xl"></i>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-600">In Progress</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $userStats['active_tasks'] ?? 0 ?></p>
                    </div>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <?php 
                    $progress = $userStats['total_tasks'] > 0 ? 
                        (($userStats['completed_tasks'] + $userStats['approved_tasks']) / $userStats['total_tasks']) * 100 : 0;
                    ?>
                    <div class="bg-orange-600 h-2 rounded-full" style="width: <?= min($progress, 100) ?>%"></div>
                </div>
            </div>

            <!-- Completed Tasks -->
            <div class="bg-white rounded-xl shadow-md p-6 hover-lift transition-all">
                <div class="flex items-center justify-between mb-4">
                    <div class="p-3 rounded-lg bg-green-100">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-600">Completed</p>
                        <p class="text-2xl font-bold text-gray-900">
                            <?= ($userStats['completed_tasks'] ?? 0) + ($userStats['approved_tasks'] ?? 0) ?>
                        </p>
                    </div>
                </div>
                <div class="flex items-center text-sm text-gray-600">
                    <span class="text-purple-600 font-medium"><?= $userStats['approved_tasks'] ?? 0 ?></span>
                    <span class="ml-1">approved by admin</span>
                </div>
            </div>

            <!-- Overdue Alert -->
            <div class="bg-white rounded-xl shadow-md p-6 hover-lift transition-all <?= ($userStats['overdue_tasks'] ?? 0) > 0 ? 'ring-2 ring-red-200' : '' ?>">
                <div class="flex items-center justify-between mb-4">
                    <div class="p-3 rounded-lg <?= ($userStats['overdue_tasks'] ?? 0) > 0 ? 'bg-red-100' : 'bg-gray-100' ?>">
                        <i class="fas fa-exclamation-triangle <?= ($userStats['overdue_tasks'] ?? 0) > 0 ? 'text-red-600' : 'text-gray-600' ?> text-xl"></i>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-600">Overdue</p>
                        <p class="text-2xl font-bold <?= ($userStats['overdue_tasks'] ?? 0) > 0 ? 'text-red-600' : 'text-gray-900' ?>">
                            <?= $userStats['overdue_tasks'] ?? 0 ?>
                        </p>
                    </div>
                </div>
                <?php if (($userStats['overdue_tasks'] ?? 0) > 0): ?>
                <div class="flex items-center text-sm text-red-600 font-medium">
                    <i class="fas fa-clock mr-1"></i>
                    <span>Needs immediate attention</span>
                </div>
                <?php else: ?>
                <div class="flex items-center text-sm text-green-600">
                    <i class="fas fa-check mr-1"></i>
                    <span>All tasks on track!</span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Task Interface -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <!-- Main Task List -->
            <div class="lg:col-span-3">
                <div class="bg-white rounded-xl shadow-md">
                    <!-- Task Header -->
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex justify-between items-center">
                            <div>
                                <h2 class="text-xl font-semibold text-gray-800"><?= $pageTitle ?></h2>
                                <p class="text-gray-600 text-sm"><?= count($tasks) ?> tasks • <?= date('l, F j, Y', strtotime($selectedDate)) ?></p>
                            </div>
                            
                            <!-- View Options -->
                            <div class="flex items-center space-x-3">
                                <div class="flex rounded-lg bg-gray-100 p-1">
                                    <button onclick="switchView('list')" 
                                        class="view-btn px-3 py-1 text-sm font-medium rounded-md bg-white text-blue-600 shadow-sm active">
                                        <i class="fas fa-list mr-1"></i> List
                                    </button>
                                    <button onclick="switchView('card')" 
                                        class="view-btn px-3 py-1 text-sm font-medium rounded-md text-gray-600 hover:text-gray-800">
                                        <i class="fas fa-th-large mr-1"></i> Cards
                                    </button>
                                </div>
                                
                                <!-- Sort & Filter -->
                                <div class="relative">
                                    <button onclick="toggleFilterMenu()" class="p-2 text-gray-600 hover:bg-gray-100 rounded-md">
                                        <i class="fas fa-filter"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Filters -->
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button onclick="filterTasks('all')" class="filter-chip active">
                                All Tasks
                            </button>
                            <button onclick="filterTasks('pending')" class="filter-chip">
                                Pending
                            </button>
                            <button onclick="filterTasks('progress')" class="filter-chip">
                                In Progress
                            </button>
                            <button onclick="filterTasks('overdue')" class="filter-chip">
                                Overdue
                                <?php if (($userStats['overdue_tasks'] ?? 0) > 0): ?>
                                <span class="ml-1 px-1.5 py-0.5 text-xs bg-red-500 text-white rounded-full">
                                    <?= $userStats['overdue_tasks'] ?>
                                </span>
                                <?php endif; ?>
                            </button>
                        </div>
                    </div>

                    <!-- Task List Container -->
                    <div id="tasks-container" class="p-6">
                        <?php if (empty($tasks)): ?>
                        <!-- Empty State -->
                        <div class="text-center py-12">
                            <div class="w-24 h-24 mx-auto mb-6 bg-gray-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-calendar-check text-3xl text-gray-400"></i>
                            </div>
                            <h3 class="text-xl font-medium text-gray-800 mb-2">No tasks for this date</h3>
                            <p class="text-gray-600 mb-6">Enjoy your free day or select a different date to view tasks.</p>
                            <div class="flex justify-center space-x-3">
                                <button onclick="changeDate(-1)" class="px-4 py-2 text-blue-600 border border-blue-600 rounded-md hover:bg-blue-50">
                                    <i class="fas fa-chevron-left mr-1"></i> Previous Day
                                </button>
                                <button onclick="goToToday()" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    Today's Tasks
                                </button>
                                <button onclick="changeDate(1)" class="px-4 py-2 text-blue-600 border border-blue-600 rounded-md hover:bg-blue-50">
                                    Next Day <i class="fas fa-chevron-right ml-1"></i>
                                </button>
                            </div>
                        </div>
                        <?php else: ?>
                        <!-- Task Items -->
                        <div id="task-list" class="space-y-4">
                            <?php foreach ($tasks as $task): ?>
                            <?php
                            $priorityClass = 'task-priority-' . $task['priority'];
                            $isOverdue = (new DateTime($task['date']) < new DateTime()) && 
                                         !in_array($task['status'], ['Done', 'Approved']);
                            ?>
                            <div class="task-card bg-gray-50 rounded-lg p-5 hover:bg-white hover:shadow-md transition-all <?= $priorityClass ?> slide-in">
                                <div class="flex justify-between items-start mb-3">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-3 mb-2">
                                            <h3 class="text-lg font-semibold text-gray-800">
                                                <?= htmlspecialchars($task['title']) ?>
                                            </h3>
                                            <?php if ($isOverdue): ?>
                                            <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">
                                                Overdue
                                            </span>
                                            <?php endif; ?>
                                            <?php if ($task['task_category']): ?>
                                            <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                                                <?= htmlspecialchars($task['task_category']) ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($task['details']): ?>
                                        <p class="text-gray-600 text-sm mb-3 line-clamp-2">
                                            <?= htmlspecialchars($task['details']) ?>
                                        </p>
                                        <?php endif; ?>

                                        <div class="flex items-center space-x-4 text-sm text-gray-500">
                                            <span class="flex items-center">
                                                <i class="fas fa-calendar mr-1"></i>
                                                <?= date('M j, Y', strtotime($task['date'])) ?>
                                            </span>
                                            <?php if ($task['due_time']): ?>
                                            <span class="flex items-center">
                                                <i class="fas fa-clock mr-1"></i>
                                                <?= date('g:i A', strtotime($task['due_time'])) ?>
                                            </span>
                                            <?php endif; ?>
                                            <?php if ($task['estimated_hours']): ?>
                                            <span class="flex items-center">
                                                <i class="fas fa-hourglass-half mr-1"></i>
                                                <?= $task['estimated_hours'] ?>h estimated
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Task Status & Actions -->
                                    <div class="flex items-center space-x-3">
                                        <!-- Status Badge -->
                                        <div class="text-right">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= getStatusClasses($task['status']) ?>">
                                                <?= $task['status'] ?>
                                            </span>
                                            <div class="text-xs text-gray-500 mt-1 capitalize">
                                                <?= $task['priority'] ?> priority
                                            </div>
                                        </div>

                                        <!-- Quick Actions -->
                                        <div class="flex flex-col space-y-1">
                                            <button onclick="taskManager.viewTaskDetails(<?= $task['id'] ?>)" 
                                                class="p-2 text-blue-600 hover:bg-blue-50 rounded-md transition-colors" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button onclick="showStatusUpdateModal(<?= $task['id'] ?>)" 
                                                class="p-2 text-green-600 hover:bg-green-50 rounded-md transition-colors" title="Update Status">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Task Metrics -->
                                <div class="flex justify-between items-center pt-3 border-t border-gray-200">
                                    <div class="flex items-center space-x-4 text-xs text-gray-500">
                                        <?php if ($task['attachment_count'] > 0): ?>
                                        <span class="flex items-center">
                                            <i class="fas fa-paperclip mr-1"></i>
                                            <?= $task['attachment_count'] ?> files
                                        </span>
                                        <?php endif; ?>
                                        
                                        <?php if ($task['output_count'] > 0): ?>
                                        <span class="flex items-center">
                                            <i class="fas fa-share mr-1"></i>
                                            <?= $task['output_count'] ?> outputs
                                        </span>
                                        <?php endif; ?>
                                        
                                        <?php if ($task['progress_count'] > 0): ?>
                                        <span class="flex items-center">
                                            <i class="fas fa-chart-line mr-1"></i>
                                            <?= $task['progress_count'] ?> updates
                                        </span>
                                        <?php endif; ?>
                                        
                                        <span class="flex items-center">
                                            <i class="fas fa-user mr-1"></i>
                                            Created by <?= htmlspecialchars($task['created_by_name']) ?>
                                        </span>
                                    </div>

                                    <!-- Progress Indicator -->
                                    <?php if ($task['status'] === 'On Progress'): ?>
                                    <div class="flex items-center space-x-2">
                                        <div class="w-16 h-1 bg-gray-200 rounded-full overflow-hidden">
                                            <div class="h-full bg-blue-500 animate-pulse" style="width: 60%"></div>
                                        </div>
                                        <span class="text-xs text-blue-600 font-medium">Active</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Quick Actions Panel -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <button onclick="showAddProgressModal()" 
                            class="w-full px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center">
                            <i class="fas fa-plus mr-2"></i>
                            Add Progress Update
                        </button>
                        
                        <button onclick="showWorkOutputModal()" 
                            class="w-full px-4 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex items-center">
                            <i class="fas fa-share mr-2"></i>
                            Share Work Output
                        </button>
                        
                        <button onclick="showUploadModal()" 
                            class="w-full px-4 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors flex items-center">
                            <i class="fas fa-upload mr-2"></i>
                            Upload Files
                        </button>
                    </div>
                </div>

                <!-- Personal Stats -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">My Performance</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Completion Rate</span>
                            <span class="text-sm font-semibold text-green-600">
                                <?= $userStats['total_tasks'] > 0 ? 
                                    round((($userStats['completed_tasks'] + $userStats['approved_tasks']) / $userStats['total_tasks']) * 100) : 0 ?>%
                            </span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">This Week</span>
                            <span class="text-sm font-semibold text-blue-600"><?= $userStats['week_tasks'] ?> tasks</span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Avg. Completion</span>
                            <span class="text-sm font-semibold text-purple-600">
                                <?= $userStats['avg_completion_time'] ? round($userStats['avg_completion_time'], 1) . 'h' : 'N/A' ?>
                            </span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Files Uploaded</span>
                            <span class="text-sm font-semibold text-orange-600"><?= $userStats['total_attachments'] ?></span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Outputs Shared</span>
                            <span class="text-sm font-semibold text-indigo-600"><?= $userStats['total_outputs'] ?></span>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Activity</h3>
                    <div class="space-y-3" id="recent-activity">
                        <!-- Activity items will be loaded by JavaScript -->
                        <div class="text-center text-gray-500 text-sm py-4">
                            <i class="fas fa-spinner fa-spin mb-2"></i>
                            <p>Loading activity...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/enhanced-task-manager.js"></script>
    <script>
        // Enhanced user interface functionality
        function switchView(view) {
            document.querySelectorAll('.view-btn').forEach(btn => {
                btn.classList.remove('bg-white', 'text-blue-600', 'shadow-sm', 'active');
                btn.classList.add('text-gray-600', 'hover:text-gray-800');
            });
            
            const activeBtn = document.querySelector(`button[onclick="switchView('${view}')"]`);
            activeBtn.classList.add('bg-white', 'text-blue-600', 'shadow-sm', 'active');
            activeBtn.classList.remove('text-gray-600', 'hover:text-gray-800');
            
            // Update view (implementation would depend on the view type)
            const taskList = document.getElementById('task-list');
            if (view === 'card') {
                taskList.classList.remove('space-y-4');
                taskList.classList.add('grid', 'grid-cols-1', 'md:grid-cols-2', 'gap-4');
            } else {
                taskList.classList.add('space-y-4');
                taskList.classList.remove('grid', 'grid-cols-1', 'md:grid-cols-2', 'gap-4');
            }
        }

        function filterTasks(filter) {
            document.querySelectorAll('.filter-chip').forEach(chip => {
                chip.classList.remove('active');
            });
            
            event.target.classList.add('active');
            
            const taskCards = document.querySelectorAll('.task-card');
            taskCards.forEach(card => {
                const shouldShow = filterTask(card, filter);
                card.style.display = shouldShow ? 'block' : 'none';
            });
        }

        function filterTask(taskCard, filter) {
            // Implementation would check task properties and return true/false
            // For now, just show all
            return true;
        }

        function changeDate(direction) {
            const currentDate = new Date(window.selectedDate);
            currentDate.setDate(currentDate.getDate() + direction);
            const newDate = currentDate.toISOString().split('T')[0];
            
            window.location.href = `enhanced-user-dashboard.php?page=day&date=${newDate}`;
        }

        function goToToday() {
            window.location.href = 'enhanced-user-dashboard.php?page=today';
        }

        function refreshTasks() {
            window.location.reload();
        }

        function showNotificationPanel() {
            // Implementation for notification panel
            console.log('Show notification panel');
        }

        function toggleUserMenu() {
            // Implementation for user menu
            console.log('Toggle user menu');
        }

        function toggleFilterMenu() {
            // Implementation for filter menu
            console.log('Toggle filter menu');
        }

        function showStatusUpdateModal(taskId) {
            if (taskManager) {
                taskManager.showStatusUpdateModal(taskId);
            }
        }

        function showAddProgressModal() {
            console.log('Show add progress modal');
        }

        function showWorkOutputModal() {
            console.log('Show work output modal');
        }

        function showUploadModal() {
            console.log('Show upload modal');
        }

        // Initialize date picker
        document.addEventListener('DOMContentLoaded', function() {
            const datePicker = document.getElementById('task-date-picker');
            if (datePicker) {
                datePicker.addEventListener('change', function() {
                    window.location.href = `enhanced-user-dashboard.php?page=day&date=${this.value}`;
                });
            }
            
            // Load recent activity
            loadRecentActivity();
        });

        function loadRecentActivity() {
            // Simulate loading recent activity
            setTimeout(() => {
                const activityContainer = document.getElementById('recent-activity');
                activityContainer.innerHTML = `
                    <div class="flex items-start space-x-3">
                        <div class="w-2 h-2 bg-green-500 rounded-full mt-2"></div>
                        <div class="text-xs text-gray-600">
                            <p class="font-medium">Task completed</p>
                            <p class="text-gray-500">2 hours ago</p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="w-2 h-2 bg-blue-500 rounded-full mt-2"></div>
                        <div class="text-xs text-gray-600">
                            <p class="font-medium">File uploaded</p>
                            <p class="text-gray-500">4 hours ago</p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="w-2 h-2 bg-purple-500 rounded-full mt-2"></div>
                        <div class="text-xs text-gray-600">
                            <p class="font-medium">Work output shared</p>
                            <p class="text-gray-500">1 day ago</p>
                        </div>
                    </div>
                `;
            }, 1000);
        }

        // CSS classes for status badges
        const statusClasses = {
            'Pending': 'bg-gray-100 text-gray-800',
            'On Progress': 'bg-blue-100 text-blue-800',
            'Done': 'bg-green-100 text-green-800',
            'Approved': 'bg-purple-100 text-purple-800',
            'On Hold': 'bg-orange-100 text-orange-800'
        };
    </script>

    <style>
        .filter-chip {
            @apply px-3 py-1 text-sm font-medium bg-gray-100 text-gray-700 rounded-full hover:bg-gray-200 transition-colors;
        }
        
        .filter-chip.active {
            @apply bg-blue-600 text-white hover:bg-blue-700;
        }
        
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
</body>
</html>

<?php
function getStatusClasses($status) {
    $classes = [
        'Pending' => 'bg-gray-100 text-gray-800',
        'On Progress' => 'bg-blue-100 text-blue-800',
        'Done' => 'bg-green-100 text-green-800',
        'Approved' => 'bg-purple-100 text-purple-800',
        'On Hold' => 'bg-orange-100 text-orange-800'
    ];
    return $classes[$status] ?? 'bg-gray-100 text-gray-800';
}
?>