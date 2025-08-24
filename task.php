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

// Get comprehensive task details
$stmt = $pdo->prepare("
    SELECT t.*, 
           u.name as assigned_name, 
           u.email as assigned_email,
           u.department as assigned_department,
           c.name as created_name,
           c.email as created_email,
           a.name as approved_by_name
    FROM tasks t 
    LEFT JOIN users u ON t.assigned_to = u.id 
    LEFT JOIN users c ON t.created_by = c.id 
    LEFT JOIN users a ON t.approved_by = a.id
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

// Get attachments (check if table exists first)
$attachments = [];
try {
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'task_attachments'");
    $stmt->execute();
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("
            SELECT * FROM task_attachments 
            WHERE task_id = ? 
            ORDER BY uploaded_at DESC
        ");
        $stmt->execute([$taskId]);
        $attachments = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Error fetching attachments: " . $e->getMessage());
}

// Get reassignment requests (check if table exists first)
$reassignRequests = [];
try {
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'task_reassign_requests'");
    $stmt->execute();
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("
            SELECT trr.*, 
                   u_from.name as requested_by_name,
                   u_to.name as requested_to_name,
                   u_admin.name as handled_by_name
            FROM task_reassign_requests trr
            LEFT JOIN users u_from ON trr.requested_by = u_from.id
            LEFT JOIN users u_to ON trr.requested_to = u_to.id  
            LEFT JOIN users u_admin ON trr.handled_by = u_admin.id
            WHERE trr.task_id = ? 
            ORDER BY trr.requested_at DESC
        ");
        $stmt->execute([$taskId]);
        $reassignRequests = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Error fetching reassign requests: " . $e->getMessage());
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
    <title>Task Details - <?= htmlspecialchars($task['title']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        <div class="bg-white p-4 rounded-lg shadow-sm mb-3">
            <div class="flex justify-between items-start mb-3">
                <h2 class="text-xl font-bold text-gray-900 flex-1 mr-2"><?= htmlspecialchars($task['title']) ?></h2>
                <span class="px-3 py-1 text-sm rounded-full font-medium <?= getStatusColor($task['status']) ?>">
                    <?= $task['status'] ?>
                </span>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <!-- Left Column -->
                <div class="space-y-3">
                    <div class="flex items-center">
                        <i class="fas fa-user text-blue-500 w-5"></i>
                        <span class="text-gray-600 ml-2 mr-3">Assigned to:</span>
                        <div>
                            <div class="font-medium"><?= htmlspecialchars($task['assigned_name']) ?></div>
                            <?php if ($task['assigned_email']): ?>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($task['assigned_email']) ?></div>
                            <?php endif; ?>
                            <?php if ($task['assigned_department']): ?>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($task['assigned_department']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="flex items-center">
                        <i class="fas fa-user-plus text-green-500 w-5"></i>
                        <span class="text-gray-600 ml-2 mr-3">Created by:</span>
                        <div>
                            <div class="font-medium"><?= htmlspecialchars($task['created_name']) ?></div>
                            <?php if ($task['created_email']): ?>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($task['created_email']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($task['approved_by_name']): ?>
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-purple-500 w-5"></i>
                        <span class="text-gray-600 ml-2 mr-3">Approved by:</span>
                        <div class="font-medium text-purple-700"><?= htmlspecialchars($task['approved_by_name']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Right Column -->
                <div class="space-y-3">
                    <div class="flex items-center">
                        <i class="fas fa-calendar text-indigo-500 w-5"></i>
                        <span class="text-gray-600 ml-2 mr-3">Due Date:</span>
                        <span class="font-medium"><?= date('d M Y', strtotime($task['date'])) ?></span>
                        <?php if ($task['due_time']): ?>
                            <span class="text-gray-500 ml-2"><?= date('g:i A', strtotime($task['due_time'])) ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($task['priority']): ?>
                    <div class="flex items-center">
                        <i class="fas fa-flag text-orange-500 w-5"></i>
                        <span class="text-gray-600 ml-2 mr-3">Priority:</span>
                        <span class="px-2 py-1 text-xs rounded-full font-medium <?= getPriorityColor($task['priority']) ?>">
                            <?= ucfirst($task['priority']) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($task['estimated_hours']): ?>
                    <div class="flex items-center">
                        <i class="fas fa-clock text-yellow-500 w-5"></i>
                        <span class="text-gray-600 ml-2 mr-3">Estimated:</span>
                        <span class="font-medium"><?= $task['estimated_hours'] ?> hours</span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($task['task_category']): ?>
                    <div class="flex items-center">
                        <i class="fas fa-tag text-teal-500 w-5"></i>
                        <span class="text-gray-600 ml-2 mr-3">Category:</span>
                        <span class="px-2 py-1 text-xs rounded-full bg-teal-100 text-teal-800 font-medium">
                            <?= htmlspecialchars($task['task_category']) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Timeline Info -->
            <div class="mt-4 pt-3 border-t border-gray-200 text-xs text-gray-500 space-y-1">
                <div class="flex justify-between">
                    <span>Created: <?= date('d M Y H:i', strtotime($task['created_at'])) ?></span>
                    <span>Last Updated: <?= date('d M Y H:i', strtotime($task['updated_at'])) ?></span>
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
        
        <!-- Reference Link -->
        <?php if ($task['reference_link']): ?>
        <div class="bg-white p-3 rounded-lg shadow-sm mb-2">
            <h3 class="text-sm font-semibold mb-2 flex items-center">
                <i class="fas fa-external-link-alt text-blue-500 mr-2"></i>
                Reference Link
            </h3>
            <div class="bg-blue-50 p-3 rounded-lg">
                <a href="<?= htmlspecialchars($task['reference_link']) ?>" 
                   target="_blank" 
                   class="text-blue-600 hover:text-blue-800 break-all text-sm flex items-center">
                    <?= htmlspecialchars($task['reference_link']) ?>
                    <i class="fas fa-external-link-alt ml-2 text-xs"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Attachments -->
        <?php if (!empty($attachments)): ?>
        <div class="bg-white p-3 rounded-lg shadow-sm mb-2">
            <h3 class="text-sm font-semibold mb-2 flex items-center">
                <i class="fas fa-paperclip text-green-500 mr-2"></i>
                Attachments (<?= count($attachments) ?>)
            </h3>
            <div class="space-y-2">
                <?php foreach ($attachments as $attachment): ?>
                    <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg">
                        <div class="flex items-center flex-1">
                            <div class="flex-shrink-0">
                                <?php 
                                $ext = strtolower(pathinfo($attachment['filename'], PATHINFO_EXTENSION));
                                $iconClass = getFileIcon($ext);
                                ?>
                                <i class="<?= $iconClass ?> text-lg mr-3"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-sm text-gray-900 truncate">
                                    <?= htmlspecialchars($attachment['original_name'] ?? $attachment['filename']) ?>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?= formatFileSize($attachment['file_size'] ?? 0) ?> • 
                                    <?= date('M j, Y g:i A', strtotime($attachment['uploaded_at'])) ?>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2 ml-4">
                            <a href="<?= htmlspecialchars($attachment['file_path']) ?>" 
                               target="_blank"
                               class="text-blue-600 hover:text-blue-800 text-sm">
                                <i class="fas fa-download"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Reassignment Requests -->
        <?php if (!empty($reassignRequests)): ?>
        <div class="bg-white p-3 rounded-lg shadow-sm mb-2">
            <h3 class="text-sm font-semibold mb-2 flex items-center">
                <i class="fas fa-exchange-alt text-purple-500 mr-2"></i>
                Reassignment Requests (<?= count($reassignRequests) ?>)
            </h3>
            <div class="space-y-3">
                <?php foreach ($reassignRequests as $request): ?>
                    <div class="border border-gray-200 rounded-lg p-3">
                        <div class="flex justify-between items-start mb-2">
                            <div class="flex-1">
                                <div class="text-sm">
                                    <span class="font-medium"><?= htmlspecialchars($request['requested_by_name']) ?></span>
                                    <span class="text-gray-600">requested to reassign to</span>
                                    <span class="font-medium"><?= htmlspecialchars($request['requested_to_name']) ?></span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    <?= date('M j, Y g:i A', strtotime($request['requested_at'])) ?>
                                </div>
                            </div>
                            <div class="ml-3">
                                <?php
                                $statusColor = match($request['status']) {
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'approved' => 'bg-green-100 text-green-800',
                                    'rejected' => 'bg-red-100 text-red-800',
                                    default => 'bg-gray-100 text-gray-800'
                                };
                                ?>
                                <span class="px-2 py-1 text-xs rounded-full font-medium <?= $statusColor ?>">
                                    <?= ucfirst($request['status']) ?>
                                </span>
                            </div>
                        </div>
                        
                        <?php if ($request['reason']): ?>
                            <div class="text-sm text-gray-700 bg-gray-50 p-2 rounded mb-2">
                                <strong>Reason:</strong> <?= nl2br(htmlspecialchars($request['reason'])) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($request['status'] !== 'pending'): ?>
                            <div class="text-xs text-gray-500 border-t pt-2 mt-2">
                                <?= ucfirst($request['status']) ?> by 
                                <span class="font-medium"><?= htmlspecialchars($request['handled_by_name']) ?></span>
                                on <?= date('M j, Y g:i A', strtotime($request['handled_at'])) ?>
                                <?php if ($request['admin_comment']): ?>
                                    <div class="mt-1 text-gray-700">
                                        <strong>Comment:</strong> <?= htmlspecialchars($request['admin_comment']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

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

function getPriorityColor($priority) {
    switch (strtolower($priority)) {
        case 'high': return 'bg-red-100 text-red-800';
        case 'medium': return 'bg-yellow-100 text-yellow-800';
        case 'low': return 'bg-green-100 text-green-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getFileIcon($extension) {
    switch (strtolower($extension)) {
        case 'pdf':
            return 'fas fa-file-pdf text-red-500';
        case 'doc':
        case 'docx':
            return 'fas fa-file-word text-blue-500';
        case 'xls':
        case 'xlsx':
        case 'csv':
            return 'fas fa-file-excel text-green-500';
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
        case 'bmp':
        case 'svg':
            return 'fas fa-file-image text-purple-500';
        case 'txt':
            return 'fas fa-file-alt text-gray-500';
        case 'zip':
        case 'rar':
        case '7z':
            return 'fas fa-file-archive text-orange-500';
        default:
            return 'fas fa-file text-gray-400';
    }
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        $bytes = number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        $bytes = number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        $bytes = number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        $bytes = $bytes . ' bytes';
    } elseif ($bytes == 1) {
        $bytes = $bytes . ' byte';
    } else {
        $bytes = '0 bytes';
    }
    
    return $bytes;
}
?>