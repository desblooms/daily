// Admin dashboard functions
function approveTask(taskId) {
    if (!confirm('Approve this task?')) {
        return;
    }
    
    fetch('./api/tasks.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'approve',
            task_id: taskId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Failed to approve task');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error approving task');
    });
}

// Load analytics data
function loadAnalytics(period = 'today') {
    fetch(`./api/analytics.php?period=${period}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateCharts(data.data);
        }
    })
    .catch(error => {
        console.error('Error loading analytics:', error);
    });
}

function updateCharts(data) {
    // Update charts with new data
    console.log('Analytics data:', data);
}

// Auto-refresh dashboard every 60 seconds
setInterval(() => {
    location.reload();
}, 60000);