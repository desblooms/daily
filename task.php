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
                        <button onclick="updateStatus(<?= $task['id'] ?>, 'On Progress')" 
                                class="bg-blue-500 text-white px-3 py-1 rounded-lg text-xs">Resume</button>
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
                    <button onclick="updateStatus(<?= $task['id'] ?>, 'On Hold')" 
                            class="bg-yellow-500 text-white px-3 py-1 rounded-lg text-xs">Put On Hold</button>
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