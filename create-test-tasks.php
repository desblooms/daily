<?php
// Simple Task Creation Tool for Testing
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set test admin session if not logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
}

require_once 'includes/db.php';

echo "<h1>🔧 Create Test Tasks</h1>";
echo "<p>Current User: ID = {$_SESSION['user_id']}, Role = {$_SESSION['role']}</p>";

// Handle form submission
if ($_POST) {
    try {
        $title = $_POST['title'] ?? '';
        $details = $_POST['details'] ?? '';
        $date = $_POST['date'] ?? date('Y-m-d');
        $assigned_to = $_POST['assigned_to'] ?? $_SESSION['user_id'];
        $status = $_POST['status'] ?? 'Pending';
        $priority = $_POST['priority'] ?? 'medium';
        
        if (empty($title)) {
            throw new Exception('Title is required');
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO tasks (title, details, date, assigned_to, created_by, status, priority, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $title,
            $details,
            $date,
            $assigned_to,
            $_SESSION['user_id'],
            $status,
            $priority
        ]);
        
        if ($result) {
            $newTaskId = $pdo->lastInsertId();
            echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; color: #155724; margin: 15px 0;'>";
            echo "✅ <strong>Task created successfully!</strong><br>";
            echo "Task ID: {$newTaskId}<br>";
            echo "Title: {$title}<br>";
            echo "Date: {$date}<br>";
            echo "Status: {$status}";
            echo "</div>";
        }
    } catch (Exception $e) {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24; margin: 15px 0;'>";
        echo "❌ <strong>Error:</strong> " . $e->getMessage();
        echo "</div>";
    }
}

// Get list of users for assignment
try {
    $stmt = $pdo->query("SELECT id, name FROM users WHERE is_active = 1 ORDER BY name");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $users = [];
}

// Get current task count
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks");
    $taskCount = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userTaskCount = $stmt->fetchColumn();
    
    echo "<p><strong>Current Tasks:</strong> {$taskCount} total, {$userTaskCount} assigned to you</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Error counting tasks: {$e->getMessage()}</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.form-group { margin: 15px 0; }
label { display: block; font-weight: bold; margin-bottom: 5px; }
input, select, textarea { width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 10px 5px; }
button:hover { background: #0056b3; }
.btn-secondary { background: #6c757d; }
.btn-secondary:hover { background: #5a6268; }
.preset-buttons { display: flex; gap: 10px; flex-wrap: wrap; margin: 15px 0; }
</style>

<form method="POST">
    <div class="form-group">
        <label for="title">Task Title *</label>
        <input type="text" id="title" name="title" required placeholder="Enter task title">
    </div>
    
    <div class="form-group">
        <label for="details">Task Details</label>
        <textarea id="details" name="details" rows="3" placeholder="Task description (optional)"></textarea>
    </div>
    
    <div class="form-group">
        <label for="date">Task Date</label>
        <input type="date" id="date" name="date" value="<?= date('Y-m-d') ?>">
    </div>
    
    <div class="form-group">
        <label for="assigned_to">Assign To</label>
        <select id="assigned_to" name="assigned_to">
            <?php foreach ($users as $user): ?>
                <option value="<?= $user['id'] ?>" <?= $user['id'] == $_SESSION['user_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($user['name']) ?> (ID: <?= $user['id'] ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="form-group">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="Pending">Pending</option>
            <option value="On Progress">On Progress</option>
            <option value="Done">Done</option>
            <option value="Approved">Approved</option>
            <option value="On Hold">On Hold</option>
        </select>
    </div>
    
    <div class="form-group">
        <label for="priority">Priority</label>
        <select id="priority" name="priority">
            <option value="low">Low</option>
            <option value="medium" selected>Medium</option>
            <option value="high">High</option>
        </select>
    </div>
    
    <button type="submit">Create Task</button>
    <button type="button" onclick="clearForm()">Clear Form</button>
</form>

<h2>Quick Presets</h2>
<div class="preset-buttons">
    <button type="button" onclick="createPresetTask('today')">Today's Task</button>
    <button type="button" onclick="createPresetTask('tomorrow')">Tomorrow's Task</button>
    <button type="button" onclick="createPresetTask('urgent')">Urgent Task</button>
    <button type="button" onclick="createPresetTask('multiple')">Create Multiple Tasks</button>
</div>

<h2>Links</h2>
<p>
    <a href="diagnose-task-issues.php">🔍 Diagnose Issues</a> | 
    <a href="test-frontend-tasks.html">🧪 Test Frontend</a> | 
    <a href="index.php">🏠 Home Page</a> |
    <a href="admin-dashboard.php">👨‍💼 Admin Dashboard</a>
</p>

<script>
function clearForm() {
    document.getElementById('title').value = '';
    document.getElementById('details').value = '';
    document.getElementById('date').value = '<?= date('Y-m-d') ?>';
    document.getElementById('status').value = 'Pending';
    document.getElementById('priority').value = 'medium';
}

function createPresetTask(type) {
    const today = new Date().toISOString().split('T')[0];
    const tomorrow = new Date(Date.now() + 24*60*60*1000).toISOString().split('T')[0];
    
    switch(type) {
        case 'today':
            document.getElementById('title').value = 'Daily Review Task';
            document.getElementById('details').value = 'Review and complete daily activities';
            document.getElementById('date').value = today;
            document.getElementById('status').value = 'Pending';
            document.getElementById('priority').value = 'medium';
            break;
            
        case 'tomorrow':
            document.getElementById('title').value = 'Tomorrow Planning';
            document.getElementById('details').value = 'Plan tasks for tomorrow';
            document.getElementById('date').value = tomorrow;
            document.getElementById('status').value = 'Pending';
            document.getElementById('priority').value = 'low';
            break;
            
        case 'urgent':
            document.getElementById('title').value = 'URGENT: System Check';
            document.getElementById('details').value = 'Critical system maintenance required';
            document.getElementById('date').value = today;
            document.getElementById('status').value = 'Pending';
            document.getElementById('priority').value = 'high';
            break;
            
        case 'multiple':
            createMultipleTasks();
            return;
    }
}

function createMultipleTasks() {
    const tasks = [
        {title: 'Morning Standup', details: 'Daily team standup meeting', date: '<?= date('Y-m-d') ?>', priority: 'medium'},
        {title: 'Code Review', details: 'Review pending pull requests', date: '<?= date('Y-m-d') ?>', priority: 'high'},
        {title: 'Testing', details: 'Test new features', date: '<?= date('Y-m-d', strtotime('+1 day')) ?>', priority: 'medium'},
        {title: 'Documentation', details: 'Update project documentation', date: '<?= date('Y-m-d', strtotime('+2 days')) ?>', priority: 'low'}
    ];
    
    if (confirm('This will create ' + tasks.length + ' sample tasks. Continue?')) {
        tasks.forEach((task, index) => {
            setTimeout(() => {
                document.getElementById('title').value = task.title;
                document.getElementById('details').value = task.details;
                document.getElementById('date').value = task.date;
                document.getElementById('priority').value = task.priority;
                document.querySelector('form').submit();
            }, index * 100);
        });
    }
}
</script>