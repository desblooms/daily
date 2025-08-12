<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$tasks = getTasks($_SESSION['user_id'], date('Y-m-d'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Tasks</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen pb-16">
    <div class="p-2">
        <header class="flex justify-between items-center py-2 px-2 bg-white rounded-lg shadow-sm mb-2">
            <div>
                <h1 class="text-lg font-bold">Daily Tasks</h1>
                <p class="text-xs text-gray-500"><?= date('d M Y') ?></p>
            </div>
            <div class="flex gap-2">
                <button onclick="location.reload()" class="bg-gray-500 text-white px-3 py-1 rounded-lg text-xs">Refresh</button>
                <a href="?logout=1" class="bg-red-500 text-white px-3 py-1 rounded-lg text-xs">Logout</a>
            </div>
        </header>

        <div class="space-y-2" id="tasks-container">
            <?php if (empty($tasks)): ?>
                <div class="p-4 bg-white rounded-lg shadow-sm text-center">
                    <p class="text-sm text-gray-500">No tasks for today</p>
                </div>
            <?php else: ?>
                <?php foreach ($tasks as $task): ?>
                    <div class="p-2 bg-white rounded-lg shadow-sm">
                        <div class="flex justify-between items-start mb-1">
                            <div class="flex-1">
                                <span class="text-sm font-semibold"><?= htmlspecialchars($task['title']) ?></span>
                                <p class="text-xs text-gray-600 mt-1"><?= htmlspecialchars($task['details']) ?></p>
                            </div>
                            <span class="px-2 py-1 text-xs rounded-full <?= getStatusColor($task['status']) ?>">
                                <?= $task['status'] ?>
                            </span>
                        </div>
                        
                        <div class="flex justify-between items-center mt-2">
                            <span class="text-xs text-gray-500"><?= date('d M Y', strtotime($task['date'])) ?></span>
                            <div class="flex gap-1">
                                <?php if ($task['status'] === 'Pending'): ?>
                                    <button onclick="updateStatus(<?= $task['id'] ?>, 'On Progress')" 
                                            class="bg-blue-500 text-white px-2 py-1 rounded text-xs">Start</button>
                                <?php elseif ($task['status'] === 'On Progress'): ?>
                                    <button onclick="updateStatus(<?= $task['id'] ?>, 'Done')" 
                                            class="bg-green-500 text-white px-2 py-1 rounded text-xs">Complete</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <footer class="fixed bottom-0 left-0 right-0 bg-white shadow-inner flex justify-around py-2">
        <button class="text-xs text-blue-600 font-semibold">Tasks</button>
        <button class="text-xs text-gray-500">Analytics</button>
        <button class="text-xs text-gray-500">Profile</button>
    </footer>

    <script src="../assets/js/app.js"></script>
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

if (isset($_GET['logout'])) {
    logout();
}
?>