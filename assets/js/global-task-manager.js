// ==================================================
// Global Task Manager - Updated Version
// ==================================================

class GlobalTaskManager {
    constructor() {
        this.users = [];
        this.currentUser = null;
        this.initialized = false;
        this.init();
    }

    async init() {
        try {
            // Load users data if admin
            await this.loadUsers();
            this.setupGlobalEventListeners();
            this.initialized = true;
            console.log('GlobalTaskManager initialized successfully');
        } catch (error) {
            console.error('Failed to initialize GlobalTaskManager:', error);
        }
    }

    async loadUsers() {
        try {
            const response = await fetch('api/users.php?action=get_active_users');
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                this.users = data.users || [];
                console.log(`Loaded ${this.users.length} users`);
            } else {
                console.warn('Failed to load users:', data.message);
                this.users = [];
            }
        } catch (error) {
            console.warn('Could not load users:', error.message);
            this.users = [];
            
            // Show user-friendly message only if it's a critical error
            if (error.message.includes('404')) {
                console.error('API endpoint missing: api/users.php');
            }
        }
    }

    // Check if current user can create tasks
    canCreateTasks() {
        // This should be set from PHP session data
        return window.userRole === 'admin' || false;
    }

    // Check if current user can edit specific task
    canEditTask(task) {
        if (window.userRole === 'admin') return true;
        if (window.userId && task.assigned_to == window.userId) return true;
        if (window.userId && task.created_by == window.userId) return true;
        return false;
    }

    // Global method to open Add Task modal from anywhere
    openAddTaskModal(options = {}) {
        if (!this.canCreateTasks()) {
            this.showNotification('Only administrators can create tasks', 'error');
            return;
        }

        // Remove existing modal if present
        this.closeAddTaskModal();

        const modal = document.createElement('div');
        modal.id = 'globalAddTaskModal';
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-[9999] flex items-center justify-center p-4';
        
        const defaultDate = options.defaultDate || new Date().toISOString().split('T')[0];
        const defaultAssignee = options.defaultAssignee || '';
        
        modal.innerHTML = `
            <div class="bg-white rounded-xl p-6 max-w-md w-full max-h-[90vh] overflow-y-auto shadow-2xl">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Add New Task</h3>
                    <button onclick="globalTaskManager.closeAddTaskModal()" class="text-gray-400 hover:text-gray-600 text-2xl">
                        ×
                    </button>
                </div>
                
                <form id="globalAddTaskForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                        <input type="text" name="title" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Enter task title">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="details" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                  placeholder="Enter task description"></textarea>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date *</label>
                            <input type="date" name="date" required value="${defaultDate}"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                            <select name="priority" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Assign to *</label>
                        <select name="assigned_to" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Select user...</option>
                            ${this.users.map(user => `
                                <option value="${user.id}" ${user.id == defaultAssignee ? 'selected' : ''}>
                                    ${user.name} (${user.department || 'No Dept'})
                                </option>
                            `).join('')}
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estimated Hours</label>
                        <input type="number" name="estimated_hours" min="0" step="0.5"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Optional">
                    </div>
                    
                    <div class="flex gap-3 pt-4">
                        <button type="submit" 
                                class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                            Create Task
                        </button>
                        <button type="button" onclick="globalTaskManager.closeAddTaskModal()"
                                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Setup form submission
        document.getElementById('globalAddTaskForm').addEventListener('submit', (e) => {
            this.handleAddTaskSubmit(e);
        });
        
        // Focus first input
        modal.querySelector('input[name="title"]').focus();
        
        // Close on backdrop click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                this.closeAddTaskModal();
            }
        });
    }

    closeAddTaskModal() {
        const modal = document.getElementById('globalAddTaskModal');
        if (modal) {
            modal.remove();
        }
    }

    async handleAddTaskSubmit(e) {
        e.preventDefault();
        
        const form = e.target;
        const formData = new FormData(form);
        const taskData = {
            action: 'create',
            title: formData.get('title'),
            details: formData.get('details'),
            date: formData.get('date'),
            assigned_to: formData.get('assigned_to'),
            priority: formData.get('priority'),
            estimated_hours: formData.get('estimated_hours') || null
        };
        
        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Creating...';
        submitBtn.disabled = true;
        
        try {
            const response = await fetch('api/tasks.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(taskData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showNotification('Task created successfully!', 'success');
                this.closeAddTaskModal();
                
                // Refresh page or update UI
                if (typeof refreshTasks === 'function') {
                    refreshTasks();
                } else {
                    // Fallback: reload page
                    setTimeout(() => window.location.reload(), 1000);
                }
            } else {
                this.showNotification(result.message || 'Failed to create task', 'error');
            }
        } catch (error) {
            console.error('Error creating task:', error);
            this.showNotification('Network error. Please try again.', 'error');
        } finally {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
    }

    // Quick task status update
    async updateTaskStatus(taskId, newStatus, comments = '') {
        try {
            const response = await fetch('api/tasks.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'update_status',
                    task_id: taskId,
                    status: newStatus,
                    comments: comments
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showNotification(`Task status updated to ${newStatus}`, 'success');
                return true;
            } else {
                this.showNotification(result.message || 'Failed to update task', 'error');
                return false;
            }
        } catch (error) {
            console.error('Error updating task status:', error);
            this.showNotification('Network error. Please try again.', 'error');
            return false;
        }
    }

    // Bulk operations
    async bulkUpdateTasks(taskIds, updates) {
        if (!this.canCreateTasks()) {
            this.showNotification('Permission denied', 'error');
            return false;
        }
        
        try {
            const response = await fetch('api/tasks.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'bulk_update',
                    task_ids: taskIds,
                    updates: updates
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showNotification(`Updated ${taskIds.length} tasks successfully`, 'success');
                return true;
            } else {
                this.showNotification(result.message || 'Failed to update tasks', 'error');
                return false;
            }
        } catch (error) {
            console.error('Error bulk updating tasks:', error);
            this.showNotification('Network error. Please try again.', 'error');
            return false;
        }
    }

    // Auto-assignment feature
    async autoAssignTask(taskId, criteria = {}) {
        if (!this.canCreateTasks()) {
            this.showNotification('Permission denied', 'error');
            return false;
        }
        
        try {
            const response = await fetch('api/tasks.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'auto_assign',
                    task_id: taskId,
                    criteria: criteria
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showNotification(`Task auto-assigned to ${result.assigned_to_name}`, 'success');
                return result;
            } else {
                this.showNotification(result.message || 'Failed to auto-assign task', 'error');
                return false;
            }
        } catch (error) {
            console.error('Error auto-assigning task:', error);
            this.showNotification('Network error. Please try again.', 'error');
            return false;
        }
    }

    setupGlobalEventListeners() {
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + Shift + T = Add Task
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'T') {
                e.preventDefault();
                this.openAddTaskModal();
            }
            
            // Escape = Close modal
            if (e.key === 'Escape') {
                this.closeAddTaskModal();
            }
        });

        // Add global task button click handlers
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-add-task]')) {
                e.preventDefault();
                const options = {
                    defaultDate: e.target.dataset.date,
                    defaultAssignee: e.target.dataset.assignee
                };
                this.openAddTaskModal(options);
            }
        });
    }

    // Notification system
    showNotification(message, type = 'info', duration = 5000) {
        // Remove existing notifications
        const existing = document.querySelectorAll('.global-notification');
        existing.forEach(n => n.remove());
        
        const notification = document.createElement('div');
        notification.className = `global-notification fixed top-4 right-4 z-[10000] px-6 py-3 rounded-lg shadow-lg max-w-sm transition-all duration-300 transform translate-x-full`;
        
        const colors = {
            success: 'bg-green-500 text-white',
            error: 'bg-red-500 text-white',
            warning: 'bg-yellow-500 text-white',
            info: 'bg-blue-500 text-white'
        };
        
        notification.className += ` ${colors[type] || colors.info}`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => {
            notification.classList.remove('translate-x-full');
        }, 100);
        
        // Auto remove
        setTimeout(() => {
            notification.classList.add('translate-x-full');
            setTimeout(() => notification.remove(), 300);
        }, duration);
        
        // Click to dismiss
        notification.addEventListener('click', () => {
            notification.classList.add('translate-x-full');
            setTimeout(() => notification.remove(), 300);
        });
    }

    // Utility methods
    formatDate(date) {
        return new Date(date).toLocaleDateString();
    }

    formatTime(time) {
        if (!time) return '';
        return new Date(`2000-01-01T${time}`).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    }

    getStatusColor(status) {
        const colors = {
            'Pending': 'text-yellow-600 bg-yellow-50 border-yellow-200',
            'On Progress': 'text-blue-600 bg-blue-50 border-blue-200',
            'Done': 'text-green-600 bg-green-50 border-green-200',
            'Approved': 'text-purple-600 bg-purple-50 border-purple-200',
            'On Hold': 'text-gray-600 bg-gray-50 border-gray-200'
        };
        return colors[status] || colors['Pending'];
    }

    getPriorityColor(priority) {
        const colors = {
            'low': 'text-green-600 bg-green-50 border-green-200',
            'medium': 'text-yellow-600 bg-yellow-50 border-yellow-200',
            'high': 'text-red-600 bg-red-50 border-red-200'
        };
        return colors[priority] || colors['medium'];
    }

    // Task filtering and search
    filterTasks(tasks, filters) {
        return tasks.filter(task => {
            if (filters.status && task.status !== filters.status) return false;
            if (filters.priority && task.priority !== filters.priority) return false;
            if (filters.assignee && task.assigned_to != filters.assignee) return false;
            if (filters.search) {
                const searchTerm = filters.search.toLowerCase();
                return task.title.toLowerCase().includes(searchTerm) || 
                       (task.details && task.details.toLowerCase().includes(searchTerm));
            }
            return true;
        });
    }

    // Export functionality
    async exportTasks(filters = {}, format = 'csv') {
        if (!this.canCreateTasks()) {
            this.showNotification('Permission denied', 'error');
            return;
        }

        try {
            const params = new URLSearchParams({
                action: 'export',
                format: format,
                ...filters
            });

            const response = await fetch(`api/tasks.php?${params}`);
            
            if (!response.ok) {
                throw new Error('Export failed');
            }

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `tasks_${new Date().toISOString().split('T')[0]}.${format}`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);

            this.showNotification('Tasks exported successfully', 'success');
        } catch (error) {
            console.error('Error exporting tasks:', error);
            this.showNotification('Failed to export tasks', 'error');
        }
    }

    // Template management
    async loadTaskTemplates() {
        try {
            const response = await fetch('api/templates.php?action=get_templates');
            const result = await response.json();
            
            if (result.success) {
                return result.templates;
            } else {
                console.warn('Failed to load templates:', result.message);
                return [];
            }
        } catch (error) {
            console.error('Error loading templates:', error);
            return [];
        }
    }

    async createTaskFromTemplate(templateId, customData = {}) {
        try {
            const response = await fetch('api/templates.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'create_from_template',
                    template_id: templateId,
                    custom_data: customData
                })
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('Task created from template successfully', 'success');
                return result.task_id;
            } else {
                this.showNotification(result.message || 'Failed to create task from template', 'error');
                return false;
            }
        } catch (error) {
            console.error('Error creating task from template:', error);
            this.showNotification('Network error. Please try again.', 'error');
            return false;
        }
    }

    // Real-time features (WebSocket support)
    initWebSocket() {
        if (!window.WebSocket) {
            console.warn('WebSocket not supported');
            return;
        }

        try {
            const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
            const wsUrl = `${protocol}//${window.location.host}/ws`;
            
            this.ws = new WebSocket(wsUrl);
            
            this.ws.onopen = () => {
                console.log('WebSocket connected');
                this.ws.send(JSON.stringify({
                    type: 'auth',
                    token: window.userToken || null
                }));
            };
            
            this.ws.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    this.handleWebSocketMessage(data);
                } catch (error) {
                    console.error('Error parsing WebSocket message:', error);
                }
            };
            
            this.ws.onclose = () => {
                console.log('WebSocket disconnected');
                // Attempt to reconnect after 5 seconds
                setTimeout(() => this.initWebSocket(), 5000);
            };
            
            this.ws.onerror = (error) => {
                console.error('WebSocket error:', error);
            };
        } catch (error) {
            console.error('Failed to initialize WebSocket:', error);
        }
    }

    handleWebSocketMessage(data) {
        switch (data.type) {
            case 'task_updated':
                this.onTaskUpdated(data.task);
                break;
            case 'task_created':
                this.onTaskCreated(data.task);
                break;
            case 'task_deleted':
                this.onTaskDeleted(data.task_id);
                break;
            case 'notification':
                this.showNotification(data.message, data.level || 'info');
                break;
            default:
                console.log('Unknown WebSocket message type:', data.type);
        }
    }

    onTaskUpdated(task) {
        // Update task in current view if present
        const taskElement = document.querySelector(`[data-task-id="${task.id}"]`);
        if (taskElement && typeof updateTaskElement === 'function') {
            updateTaskElement(taskElement, task);
        }
        
        // Show notification if not current user's update
        if (task.updated_by != window.userId) {
            this.showNotification(
                `Task "${task.title}" was updated by ${task.updated_by_name}`, 
                'info', 
                3000
            );
        }
    }

    onTaskCreated(task) {
        // Add task to current view if applicable
        if (typeof addTaskToView === 'function') {
            addTaskToView(task);
        }
        
        // Show notification if assigned to current user
        if (task.assigned_to == window.userId && task.created_by != window.userId) {
            this.showNotification(
                `New task assigned: "${task.title}"`, 
                'info', 
                5000
            );
        }
    }

    onTaskDeleted(taskId) {
        // Remove task from current view
        const taskElement = document.querySelector(`[data-task-id="${taskId}"]`);
        if (taskElement) {
            taskElement.remove();
        }
    }

    // Analytics and reporting
    async getTaskAnalytics(period = 'week', userId = null) {
        try {
            const params = new URLSearchParams({
                action: 'analytics',
                period: period
            });
            
            if (userId) {
                params.append('user_id', userId);
            }

            const response = await fetch(`api/analytics.php?${params}`);
            const result = await response.json();

            if (result.success) {
                return result.analytics;
            } else {
                console.warn('Failed to get analytics:', result.message);
                return null;
            }
        } catch (error) {
            console.error('Error getting analytics:', error);
            return null;
        }
    }

    // File attachment handling
    async uploadTaskAttachment(taskId, file) {
        const formData = new FormData();
        formData.append('task_id', taskId);
        formData.append('file', file);

        try {
            const response = await fetch('api/attachments.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('File uploaded successfully', 'success');
                return result.attachment;
            } else {
                this.showNotification(result.message || 'Failed to upload file', 'error');
                return false;
            }
        } catch (error) {
            console.error('Error uploading file:', error);
            this.showNotification('Network error. Please try again.', 'error');
            return false;
        }
    }

    // Destroy method for cleanup
    destroy() {
        if (this.ws) {
            this.ws.close();
        }
        
        // Remove event listeners
        document.removeEventListener('keydown', this.handleKeydown);
        document.removeEventListener('click', this.handleClick);
        
        // Remove any open modals
        this.closeAddTaskModal();
        
        console.log('GlobalTaskManager destroyed');
    }
}

// Initialize global instance
let globalTaskManager;

// Wait for DOM to be ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        globalTaskManager = new GlobalTaskManager();
    });
} else {
    globalTaskManager = new GlobalTaskManager();
}

// Make it available globally
window.globalTaskManager = globalTaskManager;

// Helper functions for backward compatibility
window.openAddTaskModal = (options) => {
    if (globalTaskManager) {
        globalTaskManager.openAddTaskModal(options);
    }
};

window.updateTaskStatus = (taskId, status, comments) => {
    if (globalTaskManager) {
        return globalTaskManager.updateTaskStatus(taskId, status, comments);
    }
};

window.showNotification = (message, type, duration) => {
    if (globalTaskManager) {
        globalTaskManager.showNotification(message, type, duration);
    }
};