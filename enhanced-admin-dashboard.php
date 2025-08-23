<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$currentView = $_GET['view'] ?? 'dashboard';
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$filterUserId = isset($_GET['user_id']) && is_numeric($_GET['user_id']) ? (int)$_GET['user_id'] : null;

// Get enhanced analytics data
$analytics = getEnhancedAnalytics($selectedDate, $filterUserId);
$todayTasks = getTasks($filterUserId, $selectedDate);
$users = getAllUsers();
$recentActivities = getRecentActivities(20);

// Enhanced functions for better data
function getEnhancedAnalytics($date = null, $userId = null) {
    global $pdo;
    
    $dateCondition = $date ? "AND t.date = ?" : "";
    $userCondition = $userId ? "AND t.assigned_to = ?" : "";
    $params = array_filter([$date, $userId]);
    
    // Task statistics
    $taskStats = $pdo->prepare("
        SELECT 
            COUNT(*) as total_tasks,
            COUNT(CASE WHEN t.status = 'Pending' THEN 1 END) as pending_tasks,
            COUNT(CASE WHEN t.status = 'On Progress' THEN 1 END) as active_tasks,
            COUNT(CASE WHEN t.status = 'Done' THEN 1 END) as completed_tasks,
            COUNT(CASE WHEN t.status = 'Approved' THEN 1 END) as approved_tasks,
            COUNT(CASE WHEN t.status = 'On Hold' THEN 1 END) as on_hold_tasks,
            COUNT(CASE WHEN t.date < CURDATE() AND t.status NOT IN ('Done', 'Approved') THEN 1 END) as overdue_tasks,
            COUNT(CASE WHEN t.priority = 'high' THEN 1 END) as high_priority,
            AVG(t.estimated_hours) as avg_estimated_hours,
            COUNT(CASE WHEN t.task_category IS NOT NULL THEN 1 END) as categorized_tasks
        FROM tasks t 
        WHERE 1=1 $dateCondition $userCondition
    ");
    $taskStats->execute($params);
    $stats = $taskStats->fetch(PDO::FETCH_ASSOC);
    
    // File attachment statistics
    $fileStats = $pdo->prepare("
        SELECT 
            COUNT(ta.id) as total_attachments,
            COUNT(CASE WHEN ta.attachment_type = 'input' THEN 1 END) as input_files,
            COUNT(CASE WHEN ta.attachment_type = 'output' THEN 1 END) as output_files,
            COALESCE(SUM(ta.file_size), 0) as total_storage_used
        FROM task_attachments ta
        JOIN tasks t ON ta.task_id = t.id
        WHERE 1=1 $dateCondition $userCondition
    ");
    $fileStats->execute($params);
    $fileData = $fileStats->fetch(PDO::FETCH_ASSOC);
    
    // Work output statistics
    $outputStats = $pdo->prepare("
        SELECT 
            COUNT(two.id) as total_work_outputs,
            COUNT(CASE WHEN two.visibility = 'public' THEN 1 END) as public_outputs,
            COUNT(CASE WHEN two.is_featured = 1 THEN 1 END) as featured_outputs,
            SUM(two.view_count) as total_views
        FROM task_work_outputs two
        JOIN tasks t ON two.task_id = t.id
        WHERE 1=1 $dateCondition $userCondition
    ");
    $outputStats->execute($params);
    $outputData = $outputStats->fetch(PDO::FETCH_ASSOC);
    
    return array_merge($stats, $fileData, $outputData);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Admin Dashboard - Daily Calendar</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

    <style>
        .sidebar-hidden { transform: translateX(-100%); }
        .clickup-gradient { background: linear-gradient(135deg, #7B68EE 0%, #9F7AEA 100%); }
        .glass-morphism {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
        .hover-lift:hover { transform: translateY(-2px); }
        .chart-container {
            position: relative;
            height: 300px !important;
            width: 100%;
        }
        .chart-container canvas {
            max-height: 300px !important;
        }
        
        /* Enhanced styling for task management */
        .task-priority-high { border-left: 4px solid #ef4444; }
        .task-priority-medium { border-left: 4px solid #f59e0b; }
        .task-priority-low { border-left: 4px solid #10b981; }
        
        .status-badge-pending { @apply bg-gray-100 text-gray-800; }
        .status-badge-progress { @apply bg-blue-100 text-blue-800; }
        .status-badge-done { @apply bg-green-100 text-green-800; }
        .status-badge-approved { @apply bg-purple-100 text-purple-800; }
        .status-badge-hold { @apply bg-orange-100 text-orange-800; }
        
        .file-type-icon-image { @apply text-green-500; }
        .file-type-icon-document { @apply text-blue-500; }
        .file-type-icon-video { @apply text-red-500; }
        .file-type-icon-archive { @apply text-purple-500; }
        
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
        // Enhanced user context
        window.userRole = '<?= $_SESSION['role'] ?? 'user' ?>';
        window.userId = <?= $_SESSION['user_id'] ?? 'null' ?>;
        window.userName = '<?= htmlspecialchars($_SESSION['user_name'] ?? 'Unknown') ?>';
        window.selectedDate = '<?= $selectedDate ?>';
        window.isAdmin = true;
        
        console.log('Enhanced Admin Dashboard initialized:', {
            userRole: window.userRole,
            userId: window.userId,
            userName: window.userName,
            selectedDate: window.selectedDate
        });
    </script>
</head>

<body class="bg-gray-50">
    <!-- Navigation Header -->
    <nav class="bg-white shadow-lg fixed top-0 left-0 right-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <button id="mobile-menu-btn" class="md:hidden p-2 rounded-md text-gray-600 hover:bg-gray-100">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="flex items-center space-x-3">
                        <div class="clickup-gradient p-2 rounded-lg">
                            <i class="fas fa-calendar-alt text-white text-lg"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-gray-800">Enhanced Task Manager</h1>
                            <p class="text-sm text-gray-600">Advanced Admin Dashboard</p>
                        </div>
                    </div>
                </div>
                
                <!-- Header Controls -->
                <div class="flex items-center space-x-4">
                    <!-- Date Picker -->
                    <div class="flex items-center space-x-2">
                        <label class="text-sm text-gray-600">Date:</label>
                        <input type="date" id="task-date-picker" value="<?= $selectedDate ?>" 
                            class="px-3 py-1 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <!-- User Filter -->
                    <div class="flex items-center space-x-2">
                        <label class="text-sm text-gray-600">User:</label>
                        <select id="user-filter" class="px-3 py-1 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Users</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>" <?= $filterUserId == $user['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['department'] ?? 'No Dept') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Actions -->
                    <div class="flex items-center space-x-2">
                        <button id="refresh-tasks" class="p-2 text-gray-600 hover:bg-gray-100 rounded-md" title="Refresh">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <button onclick="taskManager.showCreateTaskModal()" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                            <i class="fas fa-plus mr-1"></i> New Task
                        </button>
                    </div>

                    <!-- User Menu -->
                    <div class="relative">
                        <button class="flex items-center space-x-2 text-gray-700 hover:bg-gray-100 p-2 rounded-md">
                            <img src="assets/img/default-avatar.png" alt="Avatar" class="w-8 h-8 rounded-full">
                            <span class="text-sm font-medium"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="pt-16">
        <!-- Enhanced Statistics Dashboard -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Tasks -->
                <div class="bg-white rounded-lg shadow p-6 hover-lift transition-transform">
                    <div class="flex items-center">
                        <div class="p-3 rounded-lg bg-blue-100">
                            <i class="fas fa-tasks text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Tasks</p>
                            <p id="total-tasks" class="text-2xl font-bold text-gray-900"><?= $analytics['total_tasks'] ?? 0 ?></p>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center text-sm text-gray-600">
                        <span class="text-green-600 font-medium"><?= $analytics['categorized_tasks'] ?? 0 ?></span>
                        <span class="ml-1">categorized</span>
                    </div>
                </div>

                <!-- Active Tasks -->
                <div class="bg-white rounded-lg shadow p-6 hover-lift transition-transform">
                    <div class="flex items-center">
                        <div class="p-3 rounded-lg bg-orange-100">
                            <i class="fas fa-clock text-orange-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">In Progress</p>
                            <p id="progress-tasks" class="text-2xl font-bold text-gray-900"><?= $analytics['active_tasks'] ?? 0 ?></p>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center text-sm text-gray-600">
                        <span class="text-red-600 font-medium"><?= $analytics['overdue_tasks'] ?? 0 ?></span>
                        <span class="ml-1">overdue</span>
                    </div>
                </div>

                <!-- Completed Tasks -->
                <div class="bg-white rounded-lg shadow p-6 hover-lift transition-transform">
                    <div class="flex items-center">
                        <div class="p-3 rounded-lg bg-green-100">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Completed</p>
                            <p id="completed-tasks" class="text-2xl font-bold text-gray-900">
                                <?= ($analytics['completed_tasks'] ?? 0) + ($analytics['approved_tasks'] ?? 0) ?>
                            </p>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center text-sm text-gray-600">
                        <span class="text-purple-600 font-medium"><?= $analytics['approved_tasks'] ?? 0 ?></span>
                        <span class="ml-1">approved</span>
                    </div>
                </div>

                <!-- File Attachments -->
                <div class="bg-white rounded-lg shadow p-6 hover-lift transition-transform">
                    <div class="flex items-center">
                        <div class="p-3 rounded-lg bg-purple-100">
                            <i class="fas fa-paperclip text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Attachments</p>
                            <p class="text-2xl font-bold text-gray-900"><?= $analytics['total_attachments'] ?? 0 ?></p>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center text-sm text-gray-600">
                        <span class="text-blue-600 font-medium">
                            <?= number_format(($analytics['total_storage_used'] ?? 0) / (1024*1024), 1) ?>MB
                        </span>
                        <span class="ml-1">storage used</span>
                    </div>
                </div>
            </div>

            <!-- Enhanced Content Tabs -->
            <div class="bg-white rounded-lg shadow">
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8 px-6">
                        <button onclick="switchMainTab('tasks')" 
                            class="main-tab-button py-4 px-1 border-b-2 font-medium text-sm active" data-tab="tasks">
                            <i class="fas fa-list-ul mr-2"></i>
                            Task Management
                        </button>
                        <button onclick="switchMainTab('analytics')" 
                            class="main-tab-button py-4 px-1 border-b-2 font-medium text-sm" data-tab="analytics">
                            <i class="fas fa-chart-bar mr-2"></i>
                            Analytics
                        </button>
                        <button onclick="switchMainTab('outputs')" 
                            class="main-tab-button py-4 px-1 border-b-2 font-medium text-sm" data-tab="outputs">
                            <i class="fas fa-share-alt mr-2"></i>
                            Work Outputs
                        </button>
                        <button onclick="switchMainTab('files')" 
                            class="main-tab-button py-4 px-1 border-b-2 font-medium text-sm" data-tab="files">
                            <i class="fas fa-folder mr-2"></i>
                            File Manager
                        </button>
                        <button onclick="switchMainTab('activity')" 
                            class="main-tab-button py-4 px-1 border-b-2 font-medium text-sm" data-tab="activity">
                            <i class="fas fa-history mr-2"></i>
                            Activity Log
                        </button>
                    </nav>
                </div>

                <div class="p-6">
                    <!-- Tasks Tab -->
                    <div id="tasks-tab" class="main-tab-pane active">
                        <div class="flex justify-between items-center mb-6">
                            <div>
                                <h2 class="text-xl font-semibold text-gray-800">Task Management</h2>
                                <p class="text-gray-600">Enhanced task management with file attachments and collaboration</p>
                            </div>
                            <div class="flex items-center space-x-3">
                                <div class="flex rounded-md shadow-sm">
                                    <button onclick="switchTaskView('list')" 
                                        class="task-view-btn px-3 py-2 text-sm font-medium bg-blue-600 text-white rounded-l-md active">
                                        <i class="fas fa-list mr-1"></i> List
                                    </button>
                                    <button onclick="switchTaskView('kanban')" 
                                        class="task-view-btn px-3 py-2 text-sm font-medium bg-gray-200 text-gray-700 rounded-r-md">
                                        <i class="fas fa-columns mr-1"></i> Kanban
                                    </button>
                                </div>
                                <button onclick="taskManager.showCreateTaskModal()" 
                                    class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                                    <i class="fas fa-plus mr-1"></i> Create Task
                                </button>
                            </div>
                        </div>

                        <!-- Task Filters -->
                        <div class="bg-gray-50 p-4 rounded-lg mb-6">
                            <div class="flex flex-wrap items-center space-x-4">
                                <div class="flex items-center space-x-2">
                                    <label class="text-sm text-gray-600">Status:</label>
                                    <select id="status-filter" class="px-3 py-1 border border-gray-300 rounded-md text-sm">
                                        <option value="">All Status</option>
                                        <option value="Pending">Pending</option>
                                        <option value="On Progress">On Progress</option>
                                        <option value="Done">Done</option>
                                        <option value="Approved">Approved</option>
                                        <option value="On Hold">On Hold</option>
                                    </select>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <label class="text-sm text-gray-600">Priority:</label>
                                    <select id="priority-filter" class="px-3 py-1 border border-gray-300 rounded-md text-sm">
                                        <option value="">All Priorities</option>
                                        <option value="high">High</option>
                                        <option value="medium">Medium</option>
                                        <option value="low">Low</option>
                                    </select>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <label class="text-sm text-gray-600">Category:</label>
                                    <select id="category-filter" class="px-3 py-1 border border-gray-300 rounded-md text-sm">
                                        <option value="">All Categories</option>
                                        <option value="Development">Development</option>
                                        <option value="Design">Design</option>
                                        <option value="Marketing">Marketing</option>
                                        <option value="Research">Research</option>
                                        <option value="Testing">Testing</option>
                                        <option value="Documentation">Documentation</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <button onclick="applyTaskFilters()" 
                                    class="px-4 py-1 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm">
                                    Apply Filters
                                </button>
                                <button onclick="clearTaskFilters()" 
                                    class="px-4 py-1 bg-gray-600 text-white rounded-md hover:bg-gray-700 text-sm">
                                    Clear
                                </button>
                            </div>
                        </div>

                        <!-- Task Container -->
                        <div id="tasks-container" class="min-h-96">
                            <!-- Tasks will be loaded here by JavaScript -->
                            <div class="flex items-center justify-center py-8">
                                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                                <span class="ml-2 text-gray-600">Loading tasks...</span>
                            </div>
                        </div>
                    </div>

                    <!-- Analytics Tab -->
                    <div id="analytics-tab" class="main-tab-pane hidden">
                        <h2 class="text-xl font-semibold text-gray-800 mb-6">Enhanced Analytics & Reports</h2>
                        
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Task Status Distribution -->
                            <div class="bg-white border rounded-lg p-6">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4">Task Status Distribution</h3>
                                <div class="chart-container">
                                    <canvas id="statusChart"></canvas>
                                </div>
                            </div>

                            <!-- Priority Analysis -->
                            <div class="bg-white border rounded-lg p-6">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4">Priority Analysis</h3>
                                <div class="chart-container">
                                    <canvas id="priorityChart"></canvas>
                                </div>
                            </div>

                            <!-- Category Breakdown -->
                            <div class="bg-white border rounded-lg p-6">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4">Task Categories</h3>
                                <div class="chart-container">
                                    <canvas id="categoryChart"></canvas>
                                </div>
                            </div>

                            <!-- File Storage Analysis -->
                            <div class="bg-white border rounded-lg p-6">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4">File Storage Usage</h3>
                                <div class="chart-container">
                                    <canvas id="storageChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Work Outputs Tab -->
                    <div id="outputs-tab" class="main-tab-pane hidden">
                        <h2 class="text-xl font-semibold text-gray-800 mb-6">Work Output Gallery</h2>
                        <div id="work-outputs-container">
                            <!-- Work outputs will be loaded here -->
                        </div>
                    </div>

                    <!-- File Manager Tab -->
                    <div id="files-tab" class="main-tab-pane hidden">
                        <h2 class="text-xl font-semibold text-gray-800 mb-6">File Management</h2>
                        <div id="file-manager-container">
                            <!-- File manager will be loaded here -->
                        </div>
                    </div>

                    <!-- Activity Log Tab -->
                    <div id="activity-tab" class="main-tab-pane hidden">
                        <h2 class="text-xl font-semibold text-gray-800 mb-6">Activity Log</h2>
                        <div id="activity-log-container">
                            <!-- Activity log will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/enhanced-task-manager.js"></script>
    <script>
        // Enhanced dashboard functionality
        function switchMainTab(tabName) {
            // Update tab buttons
            document.querySelectorAll('.main-tab-button').forEach(btn => {
                btn.classList.remove('active', 'border-blue-500', 'text-blue-600');
                btn.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
            });
            
            const activeButton = document.querySelector(`[data-tab="${tabName}"]`);
            activeButton.classList.add('active', 'border-blue-500', 'text-blue-600');
            activeButton.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
            
            // Update tab content
            document.querySelectorAll('.main-tab-pane').forEach(pane => {
                pane.classList.add('hidden');
            });
            
            const activePane = document.getElementById(`${tabName}-tab`);
            activePane.classList.remove('hidden');
            
            // Load content based on tab
            switch(tabName) {
                case 'analytics':
                    loadAnalyticsCharts();
                    break;
                case 'outputs':
                    loadWorkOutputs();
                    break;
                case 'files':
                    loadFileManager();
                    break;
                case 'activity':
                    loadActivityLog();
                    break;
            }
        }

        function switchTaskView(view) {
            document.querySelectorAll('.task-view-btn').forEach(btn => {
                btn.classList.remove('bg-blue-600', 'text-white', 'active');
                btn.classList.add('bg-gray-200', 'text-gray-700');
            });
            
            const activeBtn = document.querySelector(`button[onclick="switchTaskView('${view}')"]`);
            activeBtn.classList.add('bg-blue-600', 'text-white', 'active');
            activeBtn.classList.remove('bg-gray-200', 'text-gray-700');
            
            if (taskManager) {
                taskManager.currentView = view;
                taskManager.renderTaskList();
            }
        }

        function applyTaskFilters() {
            const status = document.getElementById('status-filter').value;
            const priority = document.getElementById('priority-filter').value;
            const category = document.getElementById('category-filter').value;
            
            if (taskManager) {
                taskManager.applyFilters({ status, priority, category });
            }
        }

        function clearTaskFilters() {
            document.getElementById('status-filter').value = '';
            document.getElementById('priority-filter').value = '';
            document.getElementById('category-filter').value = '';
            
            if (taskManager) {
                taskManager.clearFilters();
            }
        }

        function loadAnalyticsCharts() {
            // Status Distribution Chart
            const statusCtx = document.getElementById('statusChart');
            if (statusCtx && !statusCtx.chart) {
                statusCtx.chart = new Chart(statusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Pending', 'In Progress', 'Done', 'Approved', 'On Hold'],
                        datasets: [{
                            data: [
                                <?= $analytics['pending_tasks'] ?? 0 ?>,
                                <?= $analytics['active_tasks'] ?? 0 ?>,
                                <?= $analytics['completed_tasks'] ?? 0 ?>,
                                <?= $analytics['approved_tasks'] ?? 0 ?>,
                                <?= $analytics['on_hold_tasks'] ?? 0 ?>
                            ],
                            backgroundColor: [
                                '#6b7280',
                                '#3b82f6',
                                '#10b981',
                                '#8b5cf6',
                                '#f59e0b'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }

            // Priority Chart
            const priorityCtx = document.getElementById('priorityChart');
            if (priorityCtx && !priorityCtx.chart) {
                priorityCtx.chart = new Chart(priorityCtx, {
                    type: 'bar',
                    data: {
                        labels: ['High Priority', 'Medium Priority', 'Low Priority'],
                        datasets: [{
                            label: 'Tasks',
                            data: [
                                <?= $analytics['high_priority'] ?? 0 ?>,
                                <?= ($analytics['total_tasks'] ?? 0) - ($analytics['high_priority'] ?? 0) ?>,
                                0 // This would need to be calculated from the database
                            ],
                            backgroundColor: ['#ef4444', '#f59e0b', '#10b981']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }
        }

        function loadWorkOutputs() {
            const container = document.getElementById('work-outputs-container');
            container.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-share-alt text-4xl text-gray-300 mb-4"></i>
                    <p class="text-gray-600">Work outputs gallery will be loaded here</p>
                </div>
            `;
        }

        function loadFileManager() {
            const container = document.getElementById('file-manager-container');
            container.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-folder text-4xl text-gray-300 mb-4"></i>
                    <p class="text-gray-600">File manager will be loaded here</p>
                </div>
            `;
        }

        function loadActivityLog() {
            const container = document.getElementById('activity-log-container');
            container.innerHTML = `
                <div class="space-y-4">
                    <?php foreach ($recentActivities as $activity): ?>
                    <div class="bg-gray-50 p-4 rounded-lg border-l-4 border-blue-500">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="font-medium text-gray-800"><?= htmlspecialchars($activity['action'] ?? '') ?></p>
                                <p class="text-gray-600 text-sm"><?= htmlspecialchars($activity['details'] ?? '') ?></p>
                            </div>
                            <span class="text-xs text-gray-500"><?= htmlspecialchars($activity['timestamp'] ?? '') ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            `;
        }

        // Initialize enhanced dashboard
        document.addEventListener('DOMContentLoaded', function() {
            // Load initial analytics
            loadAnalyticsCharts();
            
            // Setup mobile menu
            const mobileMenuBtn = document.getElementById('mobile-menu-btn');
            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', function() {
                    // Mobile menu functionality
                });
            }
        });
    </script>
</body>
</html>