<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$analytics = getAnalytics();
$todayTasks = getTasks(null, date('Y-m-d'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100 min-h-screen pb-16">
    <div class="p-2">
        <header class="flex justify-between items-center py-2 px-2 bg-white rounded-lg shadow-sm mb-2">
            <div>
                <h1 class="text-lg font-bold">Admin Dashboard</h1>
                <p class="text-xs text-gray-500"><?= date('d M Y') ?></p>
            </div>
            <a href="?logout=1" class="bg-red-500 text-white px-3 py-1 rounded-lg text-xs">Logout</a>
        </header>

        <div class="grid grid-cols-2 gap-2 mb-3">
            <div class="bg-white p-2 rounded-lg shadow-sm">
                <p class="text-xs text-gray-500">Completed Today</p>
                <p class="text-lg font-bold text-green-600"><?= $analytics['Done'] + $analytics['Approved'] ?></p>
            </div>
            <div class="bg-white p-2 rounded-lg shadow-sm">
                <p class="text-xs text-gray-500">Pending</p>
                <p class="text-lg font-bold text-yellow-600"><?= $analytics['Pending'] ?></p>
            </div>
            <div class="bg-white p-2 rounded-lg shadow-sm">
                <p class="text-xs text-gray-500">In Progress</p>
                <p class="text-lg font-bold text-blue-600"><?= $analytics['On Progress'] ?></p>
            </div>
            <div class="bg-white p-2 rounded-lg shadow-sm">
                <p class="text-xs text-gray-500">On Hold</p>
                <p class="text-lg font-bold text-red-600"><?= $analytics['On Hold'] ?></p>
            </div>
        </div>

        <div class="bg-white p-3 rounded-lg shadow-sm mb-3">
            <h3 class="text-sm font-semibold mb-2">Task Status Distribution</h3>
            <canvas id="statusChart" width="400" height="200"></canvas>
        </div>

        <div>
            <div class="flex justify-between items-center mb-2">
                <p class="text-sm font-semibold">Today's Tasks</p>
                <button class="bg-blue-500 text-white px-2 py-1 rounded text-xs">+ New Task</button>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm divide-y">
                <?php if (empty($todayTasks)): ?>
                    <div class="p-3 text-center">
                        <p class="text-sm text-gray-500">No tasks for today</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($todayTasks as $task): ?>
                        <div class="p-2">
                            <div class="flex justify-between items-start mb-1">
                                <div class="flex-1">
                                    <span class="text-sm font-semibold"><?= htmlspecialchars($task['title']) ?></span>
                                    <p class="text-xs text-gray-600">Assigned to: <?= htmlspecialchars($task['assigned_name']) ?></p>
                                </div>
                                <span class="px-2 py-1 text-xs rounded-full <?= getStatusColor($task['status']) ?>">
                                    <?= $task['status'] ?>
                                </span>
                            </div>
                            
                            <?php if ($task['status'] === 'Done'): ?>
                                <div class="flex justify-end mt-2">
                                    <button onclick="approveTask(<?= $task['id'] ?>)" 
                                            class="bg-purple-500 text-white px-2 py-1 rounded text-xs">Approve</button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="fixed bottom-0 left-0 right-0 bg-white shadow-inner flex justify-around py-2">
        <button class="text-xs text-blue-600 font-semibold">Dashboard</button>
        <button class="text-xs text-gray-500">Reports</button>
        <button class="text-xs text-gray-500">Users</button>
    </footer>

    <script>
        // Chart.js configuration
        const ctx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'In Progress', 'Done', 'Approved', 'On Hold'],
                datasets: [{
                    data: [<?= $analytics['Pending'] ?>, <?= $analytics['On Progress'] ?>, 
                           <?= $analytics['Done'] ?>, <?= $analytics['Approved'] ?>, <?= $analytics['On Hold'] ?>],
                    backgroundColor: ['#FEF3C7', '#DBEAFE', '#D1FAE5', '#E9D5FF', '#FEE2E2']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { fontSize: 10 }
                    }
                }
            }
        });

        function approveTask(taskId) {
            fetch('./api/tasks.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'approve', task_id: taskId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
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

if (isset($_GET['logout'])) {
    logout();
}
?>