<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Details - <?= htmlspecialchars($task['title']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3B82F6',
                        secondary: '#6366F1',
                        accent: '#7B68EE',
                    }
                }
            }
        }
    </script>
    <style>
        .glass-effect {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        .hover-lift:hover {
            transform: translateY(-2px);
            transition: all 0.2s ease;
        }
        .slide-up {
            animation: slideUp 0.3s ease-out;
        }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <header class="sticky top-0 z-40 glass-effect border-b border-gray-200">
        <div class="px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <a href="<?= $_SESSION['role'] === 'admin' ? 'admin-dashboard.php' : 'index.php' ?>" 
                       class="p-2 rounded-lg hover:bg-gray-100 transition-colors">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                    </a>
                    <div>
                        <h1 class="text-lg font-bold text-gray-900">Task Details</h1>
                        <p class="text-xs text-gray-500">ID: #<?= $taskId ?></p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <span class="px-3 py-1 text-xs rounded-full font-medium border <?= getStatusColor($task['status']) ?>">
                        <?= $task['status'] ?>
                    </span>
                    <span class="px-3 py-1 text-xs rounded-full font-medium border <?= getPriorityColor($task['priority']) ?>">
                        <?= ucfirst($task['priority']) ?> Priority
                    </span>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-6 space-y-6 pb-20">
        <!-- Success/Error Messages -->
        <?php if ($success): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm flex items-center gap-3 slide-up">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                <?= $success ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm flex items-center gap-3 slide-up">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                </svg>
                <?= $error ?>
            </div>
        <?php endif; ?>

        <!-- Task Header -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border hover-lift">
            <div class="flex items-start justify-between mb-4">
                <div class="flex-1">
                    <h2 class="text-xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($task['title']) ?></h2>
                    <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a2 2 0 012-2h4a2 2 0 012 2v4m-6 4l2 2 4-4"></path>
                            </svg>
                            <span>Due: <?= date('M j, Y', strtotime($task['date'])) ?></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            <span>Assigned to: <?= htmlspecialchars($task['assigned_name']) ?></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>Created: <?= date('M j, Y g:i A', strtotime($task['created_at'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Time Tracking -->
            <?php if ($task['estimated_hours'] || $task['actual_hours']): ?>
                <div class="grid grid-cols-2 gap-4 p-4 bg-gray-50 rounded-xl">
                    <div class="text-center">
                        <p class="text-2xl font-bold text-gray-900"><?= $task['estimated_hours'] ? formatDuration($task['estimated_hours']) : 'N/A' ?></p>
                        <p class="text-xs text-gray-500">Estimated</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold text-gray-900"><?= $task['actual_hours'] ? formatDuration($task['actual_hours']) : 'N/A' ?></p>
                        <p class="text-xs text-gray-500">Actual</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Task Details -->
        <div class="bg-white rounded-2xl shadow-sm border">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Task Details</h3>
            </div>
            
            <?php if (isAdmin() || $task['assigned_to'] == $_SESSION['user_id']): ?>
                <form method="POST" class="p-6">
                    <textarea name="details" rows="6" 
                              class="w-full p-4 text-sm border border-gray-300 rounded-xl resize-none focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                              placeholder="Add task details..."><?= htmlspecialchars($task['details']) ?></textarea>
                    <div class="flex justify-end mt-4">
                        <button type="submit" name="action" value="update_details" 
                                class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors text-sm font-medium">
                            Update Details
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="p-6">
                    <p class="text-gray-700 leading-relaxed"><?= nl2br(htmlspecialchars($task['details'])) ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Time Tracking Form -->
        <?php if ($task['assigned_to'] == $_SESSION['user_id'] || isAdmin()): ?>
            <div class="bg-white rounded-2xl p-6 shadow-sm border">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Time Tracking</h3>
                <form method="POST" class="flex items-center gap-4">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Actual Hours Spent</label>
                        <input type="number" 
                               name="actual_hours" 
                               step="0.25" 
                               min="0" 
                               max="999.99"
                               value="<?= $task['actual_hours'] ?>"
                               class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="0.00">
                    </div>
                    <button type="submit" name="action" value="update_hours" 
                            class="px-4 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors text-sm font-medium">
                        Update Time
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Status Actions -->
        <?php if ($task['assigned_to'] == $_SESSION['user_id']): ?>
            <div class="bg-white rounded-2xl p-6 shadow-sm border">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
                <div class="flex flex-wrap gap-3">
                    <?php if ($task['status'] === 'Pending'): ?>
                        <button onclick="updateTaskStatus(<?= $task['id'] ?>, 'On Progress')" 
                                class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors text-sm font-medium">
                            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h12v2a2 2 0 01-2 2H7a2 2 0 01-2-2v-2z"></path>
                            </svg>
                            Start Task
                        </button>
                    <?php elseif ($task['status'] === 'On Progress'): ?>
                        <button onclick="updateTaskStatus(<?= $task['id'] ?>, 'Done')" 
                                class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors text-sm font-medium">
                            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Mark Complete
                        </button>
                        <button onclick="updateTaskStatus(<?= $task['id'] ?>, 'On Hold')" 
                                class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition-colors text-sm font-medium">
                            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Put On Hold
                        </button>
                    <?php elseif ($task['status'] === 'On Hold'): ?>
                        <button onclick="updateTaskStatus(<?= $task['id'] ?>, 'On Progress')" 
                                class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors text-sm font-medium">
                            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h12v2a2 2 0 01-2 2H7a2 2 0 01-2-2v-2z"></path>
                            </svg>
                            Resume Task
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Admin Actions -->
        <?php if (isAdmin()): ?>
            <div class="bg-white rounded-2xl p-6 shadow-sm border">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Admin Actions</h3>
                <div class="flex flex-wrap gap-3">
                    <?php if ($task['status'] === 'Done'): ?>
                        <button onclick="approveTask(<?= $task['id'] ?>)" 
                                class="px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition-colors text-sm font-medium">
                            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Approve Task
                        </button>
                    <?php endif; ?>
                    <button onclick="updateTaskStatus(<?= $task['id'] ?>, 'On Hold')" 
                            class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition-colors text-sm font-medium">
                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Put On Hold
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <!-- Comments Section -->
        <div class="bg-white rounded-2xl shadow-sm border">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Comments & Discussion</h3>
            </div>
            
            <!-- Add Comment Form -->
            <form method="POST" class="p-6 border-b border-gray-200">
                <div class="flex gap-4">
                    <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center flex-shrink-0">
                        <span class="text-white font-semibold text-sm"><?= strtoupper(substr($_SESSION['user_name'], 0, 2)) ?></span>
                    </div>
                    <div class="flex-1">
                        <textarea name="comment" rows="3" 
                                  class="w-full p-3 text-sm border border-gray-300 rounded-lg resize-none focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                                  placeholder="Add a comment..." required></textarea>
                        <div class="flex justify-end mt-3">
                            <button type="submit" name="action" value="add_comment" 
                                    class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors text-sm font-medium">
                                Post Comment
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Comments List -->
            <div class="max-h-96 overflow-y-auto">
                <?php if (empty($comments)): ?>
                    <div class="p-8 text-center">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                        </div>
                        <p class="text-gray-500">No comments yet</p>
                        <p class="text-xs text-gray-400 mt-1">Be the first to add a comment</p>
                    </div>
                <?php else: ?>
                    <div class="divide-y divide-gray-100">
                        <?php foreach ($comments as $comment): ?>
                            <div class="p-6">
                                <div class="flex gap-4">
                                    <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center flex-shrink-0">
                                        <span class="text-gray-600 font-semibold text-xs"><?= strtoupper(substr($comment['user_name'], 0, 2)) ?></span>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="font-medium text-gray-900"><?= htmlspecialchars($comment['user_name']) ?></span>
                                            <span class="text-xs text-gray-500"><?= timeAgo($comment['created_at']) ?></span>
                                        </div>
                                        <p class="text-gray-700 leading-relaxed"><?= nl2br(htmlspecialchars($comment['comment'])) ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Status History -->
        <div class="bg-white rounded-2xl shadow-sm border">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Status History</h3>
            </div>
            
            <div class="max-h-64 overflow-y-auto">
                <?php if (empty($statusHistory)): ?>
                    <div class="p-8 text-center">
                        <p class="text-gray-500 text-sm">No status changes recorded</p>
                    </div>
                <?php else: ?>
                    <div class="divide-y divide-gray-100">
                        <?php foreach ($statusHistory as $log): ?>
                            <div class="p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <span class="px-2 py-1 text-xs rounded-full font-medium border <?= getStatusColor($log['status']) ?>">
                                            <?= $log['status'] ?>
                                        </span>
                                        <span class="text-sm text-gray-600">by <?= htmlspecialchars($log['updated_by_name']) ?></span>
                                        <?php if ($log['comments']): ?>
                                            <span class="text-xs text-gray-500">- <?= htmlspecialchars($log['comments']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-xs text-gray-500"><?= timeAgo($log['timestamp']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Mobile Navigation -->
    <nav class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 px-4 py-2">
        <div class="flex justify-around">
            <a href="<?= $_SESSION['role'] === 'admin' ? 'admin-dashboard.php' : 'index.php' ?>" 
               class="flex flex-col items-center space-y-1 p-2 text-gray-400">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                </svg>
                <span class="text-xs font-medium">Dashboard</span>
            </a>
            
            <button onclick="shareTask()" class="flex flex-col items-center space-y-1 p-2 text-gray-400">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z"></path>
                </svg>
                <span class="text-xs font-medium">Share</span>
            </button>
            
            <button onclick="printTask()" class="flex flex-col items-center space-y-1 p-2 text-gray-400">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                </svg>
                <span class="text-xs font-medium">Print</span>
            </button>
        </div>
    </nav>

    <script>
        function updateTaskStatus(taskId, status) {
            if (!confirm(`Change status to "${status}"?`)) return;
            
            fetch('api/tasks.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
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
                    alert('Failed to update status: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating status');
            });
        }

        function approveTask(taskId) {
            if (!confirm('Approve this task?')) return;
            
            fetch('api/tasks.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
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
                    alert('Failed to approve task: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error approving task');
            });
        }

        function shareTask() {
            if (navigator.share) {
                navigator.share({
                    title: 'Task: <?= addslashes($task['title']) ?>',
                    text: 'Check out this task details',
                    url: window.location.href
                });
            } else {
                // Fallback: copy URL to clipboard
                navigator.clipboard.writeText(window.location.href).then(() => {
                    alert('Task URL copied to clipboard!');
                });
            }
        }

        function printTask() {
            window.print();
        }

        // Auto-hide success/error messages
        setTimeout(function() {
            const messages = document.querySelectorAll('.bg-green-50, .bg-red-50');
            messages.forEach(function(message) {
                message.style.transition = 'opacity 0.5s ease-out';
                message.style.opacity = '0';
                setTimeout(() => {
                    if (message.parentNode) {
                        message.remove();
                    }
                }, 500);
            });
        }, 5000);

        // Enhanced form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Processing...';
                    
                    setTimeout(() => {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = submitBtn.dataset.originalText || 'Submit';
                        }
                    }, 10000);
                }
            });
        });

        // Store original button text
        document.querySelectorAll('button[type="submit"]').forEach(btn => {
            btn.dataset.originalText = btn.innerHTML;
        });

        // Auto-expand textareas
        document.querySelectorAll('textarea').forEach(textarea => {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
        });

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + S to save (prevent default and trigger form submission)
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                const form = document.querySelector('form');
                if (form) form.submit();
            }
            
            // Escape key to go back
            if (e.key === 'Escape') {
                window.history.back();
            }
        });

        // Real-time character count for textareas
        document.querySelectorAll('textarea').forEach(textarea => {
            if (textarea.name === 'comment' || textarea.name === 'details') {
                const maxLength = textarea.name === 'comment' ? 1000 : 5000;
                const counter = document.createElement('div');
                counter.className = 'text-xs text-gray-400 mt-1 text-right';
                counter.textContent = `0 / ${maxLength}`;
                textarea.parentNode.appendChild(counter);
                
                textarea.addEventListener('input', function() {
                    const length = this.value.length;
                    counter.textContent = `${length} / ${maxLength}`;
                    counter.className = length > maxLength * 0.9 
                        ? 'text-xs text-red-500 mt-1 text-right' 
                        : 'text-xs text-gray-400 mt-1 text-right';
                });
            }
        });

        // Smooth scroll for anchors
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });

        // Add loading states to action buttons
        function addLoadingState(button) {
            const originalContent = button.innerHTML;
            button.disabled = true;
            button.innerHTML = `
                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Processing...
            `;
            
            setTimeout(() => {
                button.disabled = false;
                button.innerHTML = originalContent;
            }, 5000);
        }

        // Enhanced status update with loading state
        const originalUpdateStatus = updateTaskStatus;
        updateTaskStatus = function(taskId, status) {
            const button = event.target;
            addLoadingState(button);
            originalUpdateStatus(taskId, status);
        };

        // Enhanced approve task with loading state
        const originalApproveTask = approveTask;
        approveTask = function(taskId) {
            const button = event.target;
            addLoadingState(button);
            originalApproveTask(taskId);
        };

        // Add confirmation dialogs with better UX
        function confirmAction(message, callback) {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';
            modal.innerHTML = `
                <div class="bg-white rounded-xl p-6 max-w-sm w-full">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Confirm Action</h3>
                    <p class="text-gray-600 mb-6">${message}</p>
                    <div class="flex gap-3 justify-end">
                        <button class="cancel-btn px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">Cancel</button>
                        <button class="confirm-btn px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">Confirm</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            modal.querySelector('.cancel-btn').onclick = () => {
                document.body.removeChild(modal);
            };
            
            modal.querySelector('.confirm-btn').onclick = () => {
                document.body.removeChild(modal);
                callback();
            };
            
            modal.onclick = (e) => {
                if (e.target === modal) {
                    document.body.removeChild(modal);
                }
            };
        }

        // Add notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-xl shadow-lg max-w-sm ${
                type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' :
                type === 'error' ? 'bg-red-50 border border-red-200 text-red-700' :
                'bg-blue-50 border border-blue-200 text-blue-700'
            }`;
            
            notification.innerHTML = `
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        ${type === 'success' ? 
                            '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>' :
                            '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>'
                        }
                    </svg>
                    <span class="text-sm">${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-auto text-gray-400 hover:text-gray-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }

        // Add drag and drop for file uploads (future enhancement)
        function initializeFileUpload() {
            const dropZone = document.createElement('div');
            dropZone.className = 'border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hidden';
            dropZone.innerHTML = `
                <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                </svg>
                <p class="text-gray-500">Drop files here to attach to this task</p>
                <p class="text-xs text-gray-400 mt-2">Supports: PDF, DOC, DOCX, XLS, XLSX, images</p>
            `;
            
            // Add to task details section if user has permission
            const taskDetails = document.querySelector('.bg-white.rounded-2xl');
            if (taskDetails && (<?= json_encode($task['assigned_to'] == $_SESSION['user_id'] || isAdmin()) ?>)) {
                taskDetails.appendChild(dropZone);
            }
        }

        // Initialize enhancements
        document.addEventListener('DOMContentLoaded', function() {
            initializeFileUpload();
            
            // Add tooltips to buttons
            document.querySelectorAll('button[title]').forEach(button => {
                button.addEventListener('mouseenter', function() {
                    // Tooltip implementation would go here
                });
            });
        });

        // Add service worker for offline functionality (progressive enhancement)
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js').catch(console.error);
        }

        // Performance monitoring
        window.addEventListener('load', function() {
            const loadTime = performance.now();
            console.log(`Task detail page loaded in ${loadTime.toFixed(2)}ms`);
        });
    </script>

    <style media="print">
        /* Print-specific styles */
        .sticky, .fixed, nav, .bg-gray-50 {
            display: none !important;
        }
        
        body {
            background: white !important;
        }
        
        .bg-white {
            box-shadow: none !important;
            border: 1px solid #e5e7eb !important;
        }
        
        .rounded-2xl {
            border-radius: 0 !important;
        }
        
        .text-blue-500, .text-purple-500, .text-green-500 {
            color: #000 !important;
        }
        
        @page {
            margin: 1in;
        }
    </style>
</body>
</html>

<?php
if (isset($_GET['logout'])) {
    logout();
}
?><?php
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
$task = getTaskById($taskId);

if (!$task) {
    header('Location: index.php');
    exit;
}

// Check permissions
if (!isAdmin() && $task['assigned_to'] != $_SESSION['user_id']) {
    header('Location: index.php');
    exit;
}

// Get status history
$stmt = $pdo->prepare("
    SELECT 
        sl.*,
        u.name as updated_by_name
    FROM status_logs sl
    LEFT JOIN users u ON sl.updated_by = u.id
    WHERE sl.task_id = ?
    ORDER BY sl.timestamp DESC
");
$stmt->execute([$taskId]);
$statusHistory = $stmt->fetchAll();

// Get task comments
$stmt = $pdo->prepare("
    SELECT 
        tc.*,
        u.name as user_name
    FROM task_comments tc
    LEFT JOIN users u ON tc.user_id = u.id
    WHERE tc.task_id = ?
    ORDER BY tc.created_at ASC
");
$stmt->execute([$taskId]);
$comments = $stmt->fetchAll();

// Handle form submissions
$success = '';
$error = '';

if ($_POST && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'update_details':
                if (isAdmin() || $task['assigned_to'] == $_SESSION['user_id']) {
                    $details = trim($_POST['details'] ?? '');
                    $stmt = $pdo->prepare("UPDATE tasks SET details = ?, updated_at = NOW() WHERE id = ?");
                    if ($stmt->execute([$details, $taskId])) {
                        $success = "Task details updated successfully";
                        $task['details'] = $details;
                        logActivity($_SESSION['user_id'], 'task_updated', 'task', $taskId);
                    }
                }
                break;
                
            case 'add_comment':
                $comment = trim($_POST['comment'] ?? '');
                if (!empty($comment)) {
                    $stmt = $pdo->prepare("
                        INSERT INTO task_comments (task_id, user_id, comment)
                        VALUES (?, ?, ?)
                    ");
                    if ($stmt->execute([$taskId, $_SESSION['user_id'], $comment])) {
                        $success = "Comment added successfully";
                        // Refresh comments
                        $stmt = $pdo->prepare("
                            SELECT tc.*, u.name as user_name
                            FROM task_comments tc
                            LEFT JOIN users u ON tc.user_id = u.id
                            WHERE tc.task_id = ?
                            ORDER BY tc.created_at ASC
                        ");
                        $stmt->execute([$taskId]);
                        $comments = $stmt->fetchAll();
                        
                        logActivity($_SESSION['user_id'], 'comment_added', 'task', $taskId);
                    }
                }
                break;
                
            case 'update_hours':
                if ($task['assigned_to'] == $_SESSION['user_id'] || isAdmin()) {
                    $actualHours = floatval($_POST['actual_hours'] ?? 0);
                    $stmt = $pdo->prepare("UPDATE tasks SET actual_hours = ?, updated_at = NOW() WHERE id = ?");
                    if ($stmt->execute([$actualHours, $taskId])) {
                        $success = "Time tracking updated successfully";
                        $task['actual_hours'] = $actualHours;
                        logActivity($_SESSION['user_id'], 'hours_updated', 'task', $taskId);
                    }
                }
                break;
        }
    } catch (Exception $e) {
        $error = "An error occurred: " . $e->getMessage();
    }
}

function getStatusColor($status) {
    switch ($status) {
        case 'Pending': return 'bg-yellow-100 text-yellow-700 border-yellow-200';
        case 'On Progress': return 'bg-blue-100 text-blue-700 border-blue-200';
        case 'Done': return 'bg-green-100 text-green-700 border-green-200';
        case 'Approved': return 'bg-purple-100 text-purple-700 border-purple-200';
        case 'On Hold': return 'bg-red-100 text-red-700 border-red-200';
        default: return 'bg-gray-100 text-gray-700 border-gray-200';
    }
}

function getPriorityColor($priority) {
    switch ($priority) {
        case 'high': return 'bg-red-100 text-red-700 border-red-200';
        case 'medium': return 'bg-yellow-100 text-yellow-700 border-yellow-200';
        case 'low': return 'bg-green-100 text-green-700 border-green-200';
        default: return 'bg-gray-100 text-gray-700 border-gray-200';
    }
}
?>