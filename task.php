<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$taskId = $_GET['id'] ?? null;
if (!$taskId) {
    header('Location: index.php');
    exit;
}

// Get task details
$stmt = $pdo->prepare("
    SELECT t.*, u.name as assigned_name, c.name as created_name 
    FROM tasks t 
    LEFT JOIN users u ON t.assigned_to = u.id 
    LEFT JOIN users c ON t.created_by = c.id 
    WHERE t.id = ?
");
$stmt->execute([$taskId]);
$task = $stmt->fetch();

if (!$task) {
    header('Location: index.php');
    exit;
}

// Check permissions
if ($_SESSION['role'] !== 'admin' && $task['assigned_to'] != $_SESSION['user_id']) {
    header('Location: index.php');
    exit;
}

// Get status logs
$stmt = $pdo->prepare("
    SELECT sl.*, u.name as updated_by_name 
    FROM status_logs sl 
    LEFT JOIN users u ON sl.updated_by = u.id 
    WHERE sl.task_id = ? 
    ORDER BY sl.timestamp DESC
");
$stmt->execute([$taskId]);
$statusLogs = $stmt->fetchAll();

// Handle form submission
if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_details' && (isAdmin() || $task['assigned_to'] == $_SESSION['user_id'])) {
        $details = $_POST['details'];
        $stmt = $pdo->prepare("UPDATE tasks SET details = ? WHERE id = ?");
        if ($stmt->execute([$details, $taskId])) {
            $success = "Task details updated successfully";
            // Refresh task data
            $stmt = $pdo->prepare("SELECT t.*, u.name as assigned_name, c.name as created_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id LEFT JOIN users c ON t.created_by = c.id WHERE t.id = ?");
            $stmt->execute([$taskId]);
            $task = $stmt->fetch();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Details</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen pb-16">
    <div class="p-2">
        <header class="flex justify-between items-center py-2 px-2 bg-white rounded-lg shadow-sm mb-2">
            <div class="flex items-center gap-2">
                <a href="<?= $_SESSION['role'] === 'admin' ? 'admin-dashboard.php' : 'index.php' ?>" 
                   class="text-blue-500 text-sm">← Back</a>
                <h1 class="text-lg font-bold">Task Details</h1>
            </div>
            <span class="px-2 py-1 text-xs rounded-full <?= getStatusColor($task['status']) ?>">
                <?= $task['status'] ?>
            </span>
        </header>

        <?php if (isset($success)): ?>
            <div class="bg-green-100 text-green-700 p-2 rounded-lg text-xs mb-2"><?= $success ?></div>
        <?php endif; ?>

        <!-- Task Information -->
        <div class="bg-white p-3 rounded-lg shadow-sm mb-2">
            <h2 class="text-lg font-semibold mb-2"><?= htmlspecialchars($task['title']) ?></h2>
            
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-600">Assigned to:</span>
                    <span><?= htmlspecialchars($task['assigned_name']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Created by:</span>
                    <span><?= htmlspecialchars($task['created_name']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Date:</span>
                    <span><?= date('d M Y', strtotime($task['date'])) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Created:</span>
                    <span><?= date('d M Y H:i', strtotime($task['created_at'])) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Last Updated:</span>
                    <span><?= date('d M Y H:i', strtotime($task['updated_at'])) ?></span>
                </div>
            </div>
        </div>

        <!-- Task Details -->
        <div class="bg-white p-3 rounded-lg shadow-sm mb-2">
            <h3 class="text-sm font-semibold mb-2">Details</h3>
            
            <?php if (isAdmin() || $task['assigned_to'] == $_SESSION['user_id']): ?>
                <form method="POST" class="space-y-2">
                    <textarea name="details" rows="4" 
                              class="w-full p-2 text-sm border rounded-lg resize-none focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Add task details..."><?= htmlspecialchars($task['details']) ?></textarea>
                    <div class="flex justify-end">
                        <button type="submit" name="action" value="update_details" 
                                class="bg-blue-500 text-white px-3 py-1 rounded-lg text-xs">Update Details</button>
                    </div>
                </form>
            <?php else: ?>
                <p class="text-sm text-gray-700"><?= nl2br(htmlspecialchars($task['details'])) ?></p>
            <?php endif; ?>
        </div>

        <!-- Status Actions -->
        <?php if ($task['assigned_to'] == $_SESSION['user_id']): ?>
            <div class="bg-white p-3 rounded-lg shadow-sm mb-2">
                <h3 class="text-sm font-semibold mb-2">Actions</h3>
                <div class="flex gap-2 flex-wrap">
                    <?php if ($task['status'] === 'Pending'): ?>
                        <button onclick="updateStatus(<?= $task['id'] ?>, 'On Progress')" 
                                class="bg-blue-500 text-white px-3 py-1 rounded-lg text-xs">Start Task</button>
                    <?php elseif ($task['status'] === 'On Progress'): ?>
                        <button onclick="updateStatus(<?= $task['id'] ?>, 'Done')" 
                                class="bg-green-500 text-white px-3 py-1 rounded-lg text-xs">Mark Complete</button>
                        <button onclick="updateStatus(<?= $task['id'] ?>, 'On Hold')" 
                                class="bg-yellow-500 text-white px-3 py-1 rounded-lg text-xs">Put On Hold</button>
                    <?php elseif ($task['status'] === 'On Hold'): ?>
                        <button onclick="openResumeModal(<?= $task['id'] ?>)" 
                                class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded-lg text-xs transition-colors">Resume Task</button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Admin Actions -->
        <?php if (isAdmin()): ?>
            <div class="bg-white p-3 rounded-lg shadow-sm mb-2">
                <h3 class="text-sm font-semibold mb-2">Admin Actions</h3>
                <div class="flex gap-2 flex-wrap">
                    <?php if ($task['status'] === 'Done'): ?>
                        <button onclick="approveTask(<?= $task['id'] ?>)" 
                                class="bg-purple-500 text-white px-3 py-1 rounded-lg text-xs">Approve Task</button>
                    <?php endif; ?>
                    
                    <?php if ($task['status'] === 'On Hold'): ?>
                        <button onclick="openAdminResumeModal(<?= $task['id'] ?>)" 
                                class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded-lg text-xs transition-colors">Resume Task</button>
                    <?php else: ?>
                        <button onclick="updateStatus(<?= $task['id'] ?>, 'On Hold')" 
                                class="bg-yellow-500 text-white px-3 py-1 rounded-lg text-xs">Put On Hold</button>
                    <?php endif; ?>
                    
                    <button onclick="openAdminStatusModal(<?= $task['id'] ?>)" 
                            class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded-lg text-xs transition-colors">Change Status</button>
                </div>
            </div>
        <?php endif; ?>

        <!-- Status History -->
        <div class="bg-white p-3 rounded-lg shadow-sm">
            <h3 class="text-sm font-semibold mb-2">Status History</h3>
            
            <?php if (empty($statusLogs)): ?>
                <p class="text-xs text-gray-500">No status changes recorded</p>
            <?php else: ?>
                <div class="space-y-2">
                    <?php foreach ($statusLogs as $log): ?>
                        <div class="flex justify-between items-center py-1 border-b border-gray-100 last:border-b-0">
                            <div>
                                <span class="px-2 py-1 text-xs rounded-full <?= getStatusColor($log['status']) ?>">
                                    <?= $log['status'] ?>
                                </span>
                                <span class="text-xs text-gray-600 ml-2">by <?= htmlspecialchars($log['updated_by_name']) ?></span>
                            </div>
                            <span class="text-xs text-gray-500"><?= date('d M Y H:i', strtotime($log['timestamp'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
    <script>
        function approveTask(taskId) {
            if (!confirm('Approve this task?')) return;
            
            fetch('./api/tasks.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'approve', task_id: taskId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Failed to approve task');
                }
            });
        }
    </script>

    <!-- Resume Task Modal -->
    <div id="resumeModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4 transform transition-all duration-300 scale-95" id="resumeModalContent">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h8m-2-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-gray-900 mb-2">Resume Task</h2>
                <p class="text-gray-600">Choose the status you want to change this task to:</p>
            </div>

            <div class="space-y-3 mb-6">
                <button onclick="resumeTask('Pending')" class="w-full flex items-center justify-between p-4 bg-yellow-50 hover:bg-yellow-100 border-2 border-yellow-200 rounded-xl transition-colors group">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-yellow-500 rounded-full mr-3"></div>
                        <div class="text-left">
                            <div class="font-semibold text-gray-900">Pending</div>
                            <div class="text-sm text-gray-600">Task is ready to be started</div>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-400 group-hover:text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>

                <button onclick="resumeTask('On Progress')" class="w-full flex items-center justify-between p-4 bg-blue-50 hover:bg-blue-100 border-2 border-blue-200 rounded-xl transition-colors group">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-blue-500 rounded-full mr-3"></div>
                        <div class="text-left">
                            <div class="font-semibold text-gray-900">On Progress</div>
                            <div class="text-sm text-gray-600">Task is actively being worked on</div>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
            </div>

            <div class="flex gap-3">
                <button onclick="closeResumeModal()" class="flex-1 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-colors">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Admin Resume Task Modal -->
    <div id="adminResumeModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4 transform transition-all duration-300 scale-95" id="adminResumeModalContent">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-gray-900 mb-2">Resume Task (Admin)</h2>
                <p class="text-gray-600">Choose the status you want to change this task to:</p>
            </div>

            <div class="space-y-3 mb-6">
                <button onclick="resumeTaskAdmin('Pending')" class="w-full flex items-center justify-between p-4 bg-yellow-50 hover:bg-yellow-100 border-2 border-yellow-200 rounded-xl transition-colors group">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-yellow-500 rounded-full mr-3"></div>
                        <div class="text-left">
                            <div class="font-semibold text-gray-900">Pending</div>
                            <div class="text-sm text-gray-600">Task is ready to be started</div>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-400 group-hover:text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>

                <button onclick="resumeTaskAdmin('On Progress')" class="w-full flex items-center justify-between p-4 bg-blue-50 hover:bg-blue-100 border-2 border-blue-200 rounded-xl transition-colors group">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-blue-500 rounded-full mr-3"></div>
                        <div class="text-left">
                            <div class="font-semibold text-gray-900">On Progress</div>
                            <div class="text-sm text-gray-600">Task is actively being worked on</div>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>

                <button onclick="resumeTaskAdmin('Done')" class="w-full flex items-center justify-between p-4 bg-green-50 hover:bg-green-100 border-2 border-green-200 rounded-xl transition-colors group">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                        <div class="text-left">
                            <div class="font-semibold text-gray-900">Done</div>
                            <div class="text-sm text-gray-600">Task is completed</div>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-400 group-hover:text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
            </div>

            <div class="flex gap-3">
                <button onclick="closeAdminResumeModal()" class="flex-1 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-colors">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Admin Status Change Modal -->
    <div id="adminStatusModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4 transform transition-all duration-300 scale-95" id="adminStatusModalContent">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path>
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-gray-900 mb-2">Change Task Status</h2>
                <p class="text-gray-600">Select the new status for this task:</p>
            </div>

            <div class="space-y-3 mb-6">
                <button onclick="changeTaskStatus('Pending')" class="w-full flex items-center justify-between p-4 bg-yellow-50 hover:bg-yellow-100 border-2 border-yellow-200 rounded-xl transition-colors group">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-yellow-500 rounded-full mr-3"></div>
                        <div class="text-left">
                            <div class="font-semibold text-gray-900">Pending</div>
                            <div class="text-sm text-gray-600">Task is ready to be started</div>
                        </div>
                    </div>
                </button>

                <button onclick="changeTaskStatus('On Progress')" class="w-full flex items-center justify-between p-4 bg-blue-50 hover:bg-blue-100 border-2 border-blue-200 rounded-xl transition-colors group">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-blue-500 rounded-full mr-3"></div>
                        <div class="text-left">
                            <div class="font-semibold text-gray-900">On Progress</div>
                            <div class="text-sm text-gray-600">Task is actively being worked on</div>
                        </div>
                    </div>
                </button>

                <button onclick="changeTaskStatus('Done')" class="w-full flex items-center justify-between p-4 bg-green-50 hover:bg-green-100 border-2 border-green-200 rounded-xl transition-colors group">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                        <div class="text-left">
                            <div class="font-semibold text-gray-900">Done</div>
                            <div class="text-sm text-gray-600">Task is completed</div>
                        </div>
                    </div>
                </button>

                <button onclick="changeTaskStatus('On Hold')" class="w-full flex items-center justify-between p-4 bg-red-50 hover:bg-red-100 border-2 border-red-200 rounded-xl transition-colors group">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-red-500 rounded-full mr-3"></div>
                        <div class="text-left">
                            <div class="font-semibold text-gray-900">On Hold</div>
                            <div class="text-sm text-gray-600">Task is temporarily paused</div>
                        </div>
                    </div>
                </button>

                <button onclick="changeTaskStatus('Approved')" class="w-full flex items-center justify-between p-4 bg-purple-50 hover:bg-purple-100 border-2 border-purple-200 rounded-xl transition-colors group">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-purple-500 rounded-full mr-3"></div>
                        <div class="text-left">
                            <div class="font-semibold text-gray-900">Approved</div>
                            <div class="text-sm text-gray-600">Task is completed and approved</div>
                        </div>
                    </div>
                </button>
            </div>

            <div class="flex gap-3">
                <button onclick="closeAdminStatusModal()" class="flex-1 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-colors">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentTaskId = null;

        function openResumeModal(taskId) {
            currentTaskId = taskId;
            const modal = document.getElementById('resumeModal');
            const content = document.getElementById('resumeModalContent');
            
            modal.classList.remove('hidden');
            setTimeout(() => {
                content.classList.remove('scale-95');
                content.classList.add('scale-100');
            }, 10);
        }

        function closeResumeModal() {
            const modal = document.getElementById('resumeModal');
            const content = document.getElementById('resumeModalContent');
            
            content.classList.remove('scale-100');
            content.classList.add('scale-95');
            
            setTimeout(() => {
                modal.classList.add('hidden');
                currentTaskId = null;
            }, 300);
        }

        function resumeTask(newStatus) {
            if (!currentTaskId) return;
            
            fetch('./api/tasks.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: 'update_status',
                    task_id: currentTaskId,
                    status: newStatus,
                    comments: `Task resumed from hold status to ${newStatus}`
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeResumeModal();
                    // Show success message
                    showSuccessMessage(`Task status updated to ${newStatus}!`);
                    // Reload page after a short delay
                    setTimeout(() => location.reload(), 1500);
                } else {
                    alert('Error: ' + (data.message || 'Failed to update task status'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error. Please try again.');
            });
        }

        function showSuccessMessage(message) {
            const successDiv = document.createElement('div');
            successDiv.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 transform translate-x-full transition-transform duration-300';
            successDiv.innerHTML = `
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    ${message}
                </div>
            `;
            
            document.body.appendChild(successDiv);
            
            setTimeout(() => {
                successDiv.classList.remove('translate-x-full');
            }, 100);
            
            setTimeout(() => {
                successDiv.classList.add('translate-x-full');
                setTimeout(() => {
                    document.body.removeChild(successDiv);
                }, 300);
            }, 3000);
        }

        // Close modal when clicking outside
        document.getElementById('resumeModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeResumeModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (!document.getElementById('resumeModal').classList.contains('hidden')) {
                    closeResumeModal();
                }
                if (!document.getElementById('adminResumeModal').classList.contains('hidden')) {
                    closeAdminResumeModal();
                }
                if (!document.getElementById('adminStatusModal').classList.contains('hidden')) {
                    closeAdminStatusModal();
                }
            }
        });

        // Admin Modal Functions
        function openAdminResumeModal(taskId) {
            currentTaskId = taskId;
            const modal = document.getElementById('adminResumeModal');
            const content = document.getElementById('adminResumeModalContent');
            
            modal.classList.remove('hidden');
            setTimeout(() => {
                content.classList.remove('scale-95');
                content.classList.add('scale-100');
            }, 10);
        }

        function closeAdminResumeModal() {
            const modal = document.getElementById('adminResumeModal');
            const content = document.getElementById('adminResumeModalContent');
            
            content.classList.remove('scale-100');
            content.classList.add('scale-95');
            
            setTimeout(() => {
                modal.classList.add('hidden');
                currentTaskId = null;
            }, 300);
        }

        function resumeTaskAdmin(newStatus) {
            if (!currentTaskId) return;
            
            fetch('./api/tasks.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: 'update_status',
                    task_id: currentTaskId,
                    status: newStatus,
                    comments: `Task resumed from hold status to ${newStatus} by admin`
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeAdminResumeModal();
                    showSuccessMessage(`Task status updated to ${newStatus}!`);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    alert('Error: ' + (data.message || 'Failed to update task status'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error. Please try again.');
            });
        }

        function openAdminStatusModal(taskId) {
            currentTaskId = taskId;
            const modal = document.getElementById('adminStatusModal');
            const content = document.getElementById('adminStatusModalContent');
            
            modal.classList.remove('hidden');
            setTimeout(() => {
                content.classList.remove('scale-95');
                content.classList.add('scale-100');
            }, 10);
        }

        function closeAdminStatusModal() {
            const modal = document.getElementById('adminStatusModal');
            const content = document.getElementById('adminStatusModalContent');
            
            content.classList.remove('scale-100');
            content.classList.add('scale-95');
            
            setTimeout(() => {
                modal.classList.add('hidden');
                currentTaskId = null;
            }, 300);
        }

        function changeTaskStatus(newStatus) {
            if (!currentTaskId) return;
            
            fetch('./api/tasks.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: 'update_status',
                    task_id: currentTaskId,
                    status: newStatus,
                    comments: `Task status changed to ${newStatus} by admin`
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeAdminStatusModal();
                    showSuccessMessage(`Task status updated to ${newStatus}!`);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    alert('Error: ' + (data.message || 'Failed to update task status'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error. Please try again.');
            });
        }

        // Close admin modals when clicking outside
        document.getElementById('adminResumeModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAdminResumeModal();
            }
        });

        document.getElementById('adminStatusModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAdminStatusModal();
            }
        });
    </script>
</body>
</html>

<?php
function getStatusColor($status) {
    switch ($status) {
        case 'Pending': return 'bg-yellow-100 text-yellow-700';
        case 'On Progress': return 'bg-blue-100 text-blue-700';
        case 'Done': return 'bg-green-100 text-green-700';
        case 'Approved': return 'bg-purple-100 text-purple-700';
        case 'On Hold': return 'bg-red-100 text-red-700';
        default: return 'bg-gray-100 text-gray-700';
    }
}
?>