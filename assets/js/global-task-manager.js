// ==================================================
// Global Task Manager - Improved Error Handling Version
// ==================================================

class GlobalTaskManager {
    constructor() {
        this.users = [];
        this.currentUser = null;
        this.initialized = false;
        this.debug = true; // Set to false in production
        this.init();
    }

    async init() {
        try {
            console.log('Initializing GlobalTaskManager...');
            
            // Check if user is authenticated
            if (!this.checkAuthentication()) {
                console.warn('User not authenticated, redirecting to login');
                return;
            }
            
            // Load users data
            await this.loadUsers();
            this.setupGlobalEventListeners();
            this.initialized = true;
            console.log('GlobalTaskManager initialized successfully');
        } catch (error) {
            console.error('Failed to initialize GlobalTaskManager:', error);
            this.showNotification('Failed to initialize task manager', 'error');
        }
    }

    checkAuthentication() {
        // Check if user session variables are available
        if (typeof window.userId === 'undefined' || typeof window.userRole === 'undefined') {
            console.warn('User session not found');
            // Optionally redirect to login
            // window.location.href = 'login.php';
            return false;
        }
        return true;
    }

    async loadUsers() {
        try {
            console.log('Loading users...');
            
            const response = await fetch('api/users.php?action=get_active_users', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Cache-Control': 'no-cache'
                },
                credentials: 'same-origin' // Include session cookies
            });
            
            // Check if response is ok
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            // Get response text first to check if it's JSON
            const responseText = await response.text();
            
            if (this.debug) {
                console.log('Raw response:', responseText);
            }
            
            // Check if response is HTML (error page)
            if (responseText.trim().startsWith('<')) {
                console.error('Received HTML instead of JSON:', responseText.substring(0, 200));
                throw new Error('Server returned HTML error page instead of JSON. Check server logs.');
            }
            
            // Try to parse JSON
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('JSON Parse Error:', parseError);
                console.error('Response text:', responseText);
                throw new Error('Invalid JSON response from server');
            }
            
            if (data.success) {
                this.users = data.users || [];
                console.log(`✅ Loaded ${this.users.length} users`);
                
                if (this.debug) {
                    console.log('Users data:', this.users);
                }
            } else {
                console.warn('❌ Failed to load users:', data.message);
                
                if (data.debug && this.debug) {
                    console.error('Debug info:', data.debug);
                }
                
                this.users = [];
                
                // Show user-friendly error message
                this.showNotification(data.message || 'Failed to load users', 'warning');
            }
            
        } catch (error) {
            console.error('❌ Could not load users:', error);
            this.users = [];
            
            // Provide specific error handling
            if (error.message.includes('404')) {
                console.error('🔍 API endpoint missing: api/users.php');
                this.showNotification('API endpoint not found. Check server configuration.', 'error');
            } else if (error.message.includes('500')) {
                console.error('🔥 Server error occurred');
                this.showNotification('Server error. Please check server logs.', 'error');
            } else if (error.message.includes('403') || error.message.includes('401')) {
                console.error('🔒 Authentication/Authorization failed');
                this.showNotification('Authentication required. Please login again.', 'error');
                // Optionally redirect to login
                // setTimeout(() => window.location.href = 'login.php', 2000);
            } else if (error.message.includes('HTML error page')) {
                console.error('🔧 Server configuration issue - check PHP error logs');
                this.showNotification('Server configuration error. Contact administrator.', 'error');
            } else {
                console.error('🌐 Network or unknown error');
                this.showNotification('Network error. Please check your connection.', 'error');
            }
        }
    }

    // Enhanced notification system
    showNotification(message, type = 'info', duration = 5000) {
        // Remove existing notifications
        const existingNotifications = document.querySelectorAll('.global-notification');
        existingNotifications.forEach(notification => notification.remove());

        const notification = document.createElement('div');
        notification.className = `global-notification fixed top-4 right-4 z-[10000] px-6 py-3 rounded-lg shadow-lg text-white font-medium max-w-sm transition-all duration-300 transform translate-x-full`;
        
        // Set color based on type
        const typeClasses = {
            'success': 'bg-green-500',
            'error': 'bg-red-500',
            'warning': 'bg-yellow-500',
            'info': 'bg-blue-500'
        };
        
        notification.classList.add(typeClasses[type] || typeClasses.info);
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

    // Enhanced API call method with better error handling
    async apiCall(endpoint, options = {}) {
        try {
            const defaultOptions = {
                headers: {
                    'Content-Type': 'application/json',
                    'Cache-Control': 'no-cache'
                },
                credentials: 'same-origin'
            };
            
            const mergedOptions = { ...defaultOptions, ...options };
            
            const response = await fetch(endpoint, mergedOptions);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const responseText = await response.text();
            
            // Check if response is HTML
            if (responseText.trim().startsWith('<')) {
                throw new Error('Server returned HTML error page instead of JSON');
            }
            
            return JSON.parse(responseText);
            
        } catch (error) {
            console.error(`API call failed for ${endpoint}:`, error);
            throw error;
        }
    }

    // Check if current user can create tasks
    canCreateTasks() {
        return window.userRole === 'admin' || false;
    }

    // Check if current user can edit specific task
    canEditTask(task) {
        if (window.userRole === 'admin') return true;
        if (window.userId && task.assigned_to == window.userId) return true;
        if (window.userId && task.created_by == window.userId) return true;
        return false;
    }

    // Get user by ID
    getUserById(userId) {
        return this.users.find(user => user.id == userId) || null;
    }

    // Get users by role
    getUsersByRole(role) {
        return this.users.filter(user => user.role === role);
    }

    // Get users by department
    getUsersByDepartment(department) {
        return this.users.filter(user => user.department === department);
    }

    // Global method to open Add Task modal with better error handling
    openAddTaskModal(options = {}) {
        if (!this.canCreateTasks()) {
            this.showNotification('Only administrators can create tasks', 'warning');
            return;
        }

        if (this.users.length === 0) {
            this.showNotification('Users not loaded. Please refresh the page.', 'warning');
            return;
        }

        // Remove existing modal if present
        this.closeAddTaskModal();

        const modal = document.createElement('div');
        modal.id = 'globalAddTaskModal';
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-[9999] flex items-center justify-center p-4';
        
        const defaultDate = options.defaultDate || new Date().toISOString().split('T')[0];
        const defaultAssignee = options.defaultAssignee || '';
        
        // Generate user options
        const userOptions = this.users.map(user => 
            `<option value="${user.id}" ${user.id == defaultAssignee ? 'selected' : ''}>
                ${user.name} (${user.department || 'No Dept'})
            </option>`
        ).join('');
        
        modal.innerHTML = `
            <div class="bg-white rounded-xl p-6 max-w-md w-full max-h-[90vh] overflow-y-auto shadow-2xl">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Add New Task</h3>
                    <button onclick="window.globalTaskManager.closeAddTaskModal()" 
                            class="text-gray-400 hover:text-gray-600 text-xl font-bold">×</button>
                </div>
                
                <form id="globalAddTaskForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Task Title *</label>
                        <input type="text" name="title" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                               placeholder="Enter task title...">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="details" rows="3" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                  placeholder="Task description..."></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Assign To *</label>
                        <select name="assigned_to" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <option value="">Select user...</option>
                            ${userOptions}
                        </select>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                            <input type="date" name="date" value="${defaultDate}"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                            <select name="priority" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Estimated Hours</label>
                            <input type="number" name="estimated_hours" min="0" step="0.5"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                   placeholder="0.0">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Due Time</label>
                            <input type="time" name="due_time"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" onclick="window.globalTaskManager.closeAddTaskModal()"
                                class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                            Create Task
                        </button>
                    </div>
                </form>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Handle form submission
        document.getElementById('globalAddTaskForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.handleTaskSubmission(e.target);
        });
        
        // Focus on title input
        modal.querySelector('input[name="title"]').focus();
        
        // Close modal when clicking outside
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                this.closeAddTaskModal();
            }
        });
    }

    async handleTaskSubmission(form) {
        try {
            const formData = new FormData(form);
            const taskData = {
                action: 'create_task',
                title: formData.get('title'),
                details: formData.get('details'),
                assigned_to: formData.get('assigned_to'),
                date: formData.get('date'),
                priority: formData.get('priority'),
                estimated_hours: formData.get('estimated_hours') || null,
                due_time: formData.get('due_time') || null
            };

            // Validate required fields
            if (!taskData.title.trim()) {
                this.showNotification('Task title is required', 'error');
                return;
            }

            if (!taskData.assigned_to) {
                this.showNotification('Please assign the task to a user', 'error');
                return;
            }

            // Show loading state
            const submitButton = form.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;
            submitButton.textContent = 'Creating...';
            submitButton.disabled = true;

            // Make API call
            const response = await this.apiCall('api/tasks-simple.php', {
                method: 'POST',
                body: JSON.stringify(taskData)
            });

            if (response.success) {
                this.showNotification('Task created successfully!', 'success');
                this.closeAddTaskModal();
                
                // Refresh page or update UI
                if (typeof refreshTasks === 'function') {
                    refreshTasks();
                } else {
                    setTimeout(() => location.reload(), 1000);
                }
            } else {
                this.showNotification(response.message || 'Failed to create task', 'error');
            }

        } catch (error) {
            console.error('Error creating task:', error);
            this.showNotification('Network error. Please try again.', 'error');
        } finally {
            // Reset button state
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.textContent = 'Create Task';
                submitButton.disabled = false;
            }
        }
    }

    closeAddTaskModal() {
        const modal = document.getElementById('globalAddTaskModal');
        if (modal) {
            modal.remove();
        }
    }

    // Setup global event listeners
    setupGlobalEventListeners() {
        // Listen for keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + Shift + T = Quick Add Task
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'T') {
                e.preventDefault();
                this.openAddTaskModal();
            }
            
            // Escape = Close modal
            if (e.key === 'Escape') {
                this.closeAddTaskModal();
            }
        });

        // Listen for custom events
        document.addEventListener('refreshUsers', () => {
            this.loadUsers();
        });

        document.addEventListener('openTaskModal', (e) => {
            this.openAddTaskModal(e.detail || {});
        });
    }

    // Utility methods for task operations
    async updateTaskStatus(taskId, newStatus, comments = '') {
        try {
            const response = await this.apiCall('api/tasks.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'update_status',
                    task_id: taskId,
                    status: newStatus,
                    comments: comments
                })
            });

            if (response.success) {
                this.showNotification('Task status updated successfully', 'success');
                return true;
            } else {
                this.showNotification(response.message || 'Failed to update task status', 'error');
                return false;
            }
        } catch (error) {
            console.error('Error updating task status:', error);
            this.showNotification('Network error. Please try again.', 'error');
            return false;
        }
    }

    async deleteTask(taskId) {
        if (!confirm('Are you sure you want to delete this task? This action cannot be undone.')) {
            return false;
        }

        try {
            const response = await this.apiCall('api/tasks.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'delete',
                    task_id: taskId
                })
            });

            if (response.success) {
                this.showNotification('Task deleted successfully', 'success');
                return true;
            } else {
                this.showNotification(response.message || 'Failed to delete task', 'error');
                return false;
            }
        } catch (error) {
            console.error('Error deleting task:', error);
            this.showNotification('Network error. Please try again.', 'error');
            return false;
        }
    }

    // Export tasks functionality
    async exportTasks(format = 'csv', filters = {}) {
        try {
            const params = new URLSearchParams({
                action: 'export',
                format: format,
                ...filters
            });

            const response = await fetch(`api/tasks.php?${params}`, {
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `tasks_export_${new Date().toISOString().split('T')[0]}.${format}`;
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

    // Get analytics data
    async getAnalytics(period = 'week', userId = null) {
        try {
            const params = new URLSearchParams({
                action: 'analytics',
                period: period
            });
            
            if (userId) {
                params.append('user_id', userId);
            }

            const response = await this.apiCall(`api/analytics.php?${params}`);

            if (response.success) {
                return response.analytics;
            } else {
                console.warn('Failed to get analytics:', response.message);
                return null;
            }
        } catch (error) {
            console.error('Error getting analytics:', error);
            return null;
        }
    }

    // Debug methods
    getDebugInfo() {
        return {
            initialized: this.initialized,
            usersLoaded: this.users.length,
            currentUser: this.currentUser,
            userRole: window.userRole,
            userId: window.userId,
            users: this.users
        };
    }

    logDebugInfo() {
        console.table(this.getDebugInfo());
    }

    // Force reload users (for debugging)
    async reloadUsers() {
        console.log('🔄 Force reloading users...');
        this.users = [];
        await this.loadUsers();
        console.log('✅ Users reloaded');
    }
}

// Initialize global task manager when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Make sure we have session data
    if (typeof window.userId === 'undefined') {
        console.warn('⚠️ User session data not found. Make sure PHP session variables are properly set.');
    }
    
    window.globalTaskManager = new GlobalTaskManager();
    
    // Expose methods for debugging
    window.debugTaskManager = () => {
        window.globalTaskManager.logDebugInfo();
    };
    
    window.reloadUsers = () => {
        window.globalTaskManager.reloadUsers();
    };
});

// Utility functions for backward compatibility
function openAddTaskModal(options = {}) {
    if (window.globalTaskManager) {
        window.globalTaskManager.openAddTaskModal(options);
    } else {
        console.error('GlobalTaskManager not initialized');
    }
}

function showNotification(message, type = 'info') {
    if (window.globalTaskManager) {
        window.globalTaskManager.showNotification(message, type);
    } else {
        alert(message); // Fallback
    }
}