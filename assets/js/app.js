 
// Task management functions for users
function updateStatus(taskId, status) {
    if (!confirm(`Change status to "${status}"?`)) {
        return;
    }
    
    fetch('./api/tasks.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'update_status',
            task_id: taskId,
            status: status
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Failed to update status');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating status');
    });
}

// Refresh tasks
function refreshTasks() {
    location.reload();
}

// Auto-refresh every 30 seconds
setInterval(refreshTasks, 30000);