// ==================================================
// 1. CREATE: assets/js/global-task-manager.js
// ==================================================

class GlobalTaskManager {
    constructor() {
        this.users = [];
        this.currentUser = null;
        this.init();
    }

    async init() {
        // Load users data if admin
        await this.loadUsers();
        this.setupGlobalEventListeners();
    }

    async loadUsers() {
        try {
            // You can create an API endpoint to get users, or pass them from PHP
            const response = await fetch('api/users.php?action=get_active_users');
            const data = await response.json();
            if (data.success) {
                this.users = data.users;
            }
        } catch (error) {
            console.warn('Could not load users:', error);
        }
    }

    // Global method to open Add Task modal from anywhere
    openAddTaskModal(options = {}) {
        // Check if user has permission
        if (!this.canCreateTasks()) {
            this.showNotification('Only administrators can create tasks', 'error');
            return;
        }

        const modal = document.createElement('div');
        modal.id = 'globalAddTaskModal';
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-[9999] flex items-center justify-center p-4';
        
        const defaultDate = options.defaultDate || new Date().toISOString().split('T')[0];
        const defaultAssignee = options.defaultAssignee || '';
        
        modal.innerHTML = `
            <div class="bg-white rounded-xl p-6 max-w-md w-full max-h-[90vh] overflow-y-auto shadow-2xl">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Add New Task</h3>
                    <button onclick="globalTaskManager.closeAddTaskModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <form id="globalAddTaskForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Task Title *</label>
                        <input type="text" id="globalTaskTitle" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                               placeholder="Enter task title...">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea id="globalTaskDetails" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                                  placeholder="Task description..."></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Assign To *</label>
                        <select id="globalAssignedTo" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                            <option value="">Select user...</option>
                            ${this.renderUserOptions(defaultAssignee)}
                        </select>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Due Date *</label>
                            <input type="date" id="globalTaskDate" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                                   value="${defaultDate}">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                            <select id="globalTaskPriority"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Estimated Hours</label>
                            <input type="number" id="globalEstimatedHours" step="0.5" min="0"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                                   placeholder="0.0">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Due Time</label>
                            <input type="time" id="globalDueTime"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        </div>
                    </div>
                    
                    <div class="flex gap-3 justify-end pt-4 border-t">
                        <button type="button" onclick="globalTaskManager.closeAddTaskModal()" 
                                class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" id="globalSubmitBtn"
                                class="px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition-colors">
                            Create Task
                        </button>
                    </div>
                </form>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Focus on first input
        document.getElementById('globalTaskTitle').focus();
        
        // Handle form submission
        document.getElementById('globalAddTaskForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.createNewTask();
        });
        
        // Close modal when clicking outside
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                this.closeAddTaskModal();
            }
        });

        // Escape key to close
        document.addEventListener('keydown', this.handleEscapeKey.bind(this));
    }

    renderUserOptions(defaultAssignee = '') {
        return this.users.map(user => 
            `<option value="${user.id}" ${user.id == defaultAssignee ? 'selected' : ''}>
                ${user.name} (${user.department || 'No Department'})
            </option>`
        ).join('');
    }

    closeAddTaskModal() {
        const modal = document.getElementById('globalAddTaskModal');
        if (modal) {
            modal.remove();
        }
        document.removeEventListener('keydown', this.handleEscapeKey.bind(this));
    }

    handleEscapeKey(e) {
        if (e.key === 'Escape') {
            this.closeAddTaskModal();
        }
    }

    async createNewTask() {
        const submitButton = document.getElementById('globalSubmitBtn');
        const originalText = submitButton.textContent;
        
        // Show loading state
        submitButton.textContent = 'Creating...';
        submitButton.disabled = true;
        
        const taskData = {
            action: 'create',
            title: document.getElementById('globalTaskTitle').value.trim(),
            details: document.getElementById('globalTaskDetails').value.trim(),
            assigned_to: document.getElementById('globalAssignedTo').value,
            date: document.getElementById('globalTaskDate').value,
            priority: document.getElementById('globalTaskPriority').value,
            estimated_hours: document.getElementById('globalEstimatedHours').value || null,
            due_time: document.getElementById('globalDueTime').value || null
        };
        
        // Validation
        if (!taskData.title) {
            this.showNotification('Please enter a task title', 'error');
            this.resetSubmitButton(submitButton, originalText);
            return;
        }
        
        if (!taskData.assigned_to) {
            this.showNotification('Please select a user to assign the task to', 'error');
            this.resetSubmitButton(submitButton, originalText);
            return;
        }
        
        if (!taskData.date) {
            this.showNotification('Please select a due date', 'error');
            this.resetSubmitButton(submitButton, originalText);
            return;
        }
        
        try {
            const response = await fetch('api/tasks.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(taskData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification('Task created successfully!', 'success');
                this.closeAddTaskModal();
                
                // Trigger custom event for other components to listen
                document.dispatchEvent(new CustomEvent('taskCreated', { 
                    detail: { taskId: data.task_id, taskData } 
                }));
                
                // Refresh page after short delay
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                this.showNotification('Error: ' + (data.message || 'Unknown error'), 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showNotification('Error creating task. Please try again.', 'error');
        } finally {
            this.resetSubmitButton(submitButton, originalText);
        }
    }

    resetSubmitButton(button, originalText) {
        button.textContent = originalText;
        button.disabled = false;
    }

    canCreateTasks() {
        // Add your permission logic here
        return window.userRole === 'admin'; // You'll need to set this globally
    }

    showNotification(message, type = 'info') {
        // Remove existing notifications
        const existing = document.querySelectorAll('.global-notification');
        existing.forEach(n => n.remove());

        const notification = document.createElement('div');
        notification.className = `global-notification fixed top-4 right-4 z-[9998] p-4 rounded-xl shadow-lg max-w-sm transition-all transform ${
            type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' :
            type === 'error' ? 'bg-red-50 border border-red-200 text-red-700' :
            'bg-blue-50 border border-blue-200 text-blue-700'
        }`;
        
        notification.innerHTML = `
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0">
                    ${type === 'success' ? 
                        '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>' :
                        type === 'error' ?
                        '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>' :
                        '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>'
                    }
                </div>
                <span class="text-sm font-medium">${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-auto text-gray-400 hover:text-gray-600 flex-shrink-0">
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
                notification.style.transform = 'translateX(100%)';
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 300);
            }
        }, 5000);
    }

    setupGlobalEventListeners() {
        // Listen for keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + Shift + T to open Add Task
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'T') {
                e.preventDefault();
                this.openAddTaskModal();
            }
        });
    }

    // Utility method to add floating action button
    addFloatingActionButton() {
        if (!this.canCreateTasks()) return;

        const fab = document.createElement('button');
        fab.id = 'globalTaskFAB';
        fab.className = 'fixed bottom-6 right-6 w-14 h-14 bg-purple-500 hover:bg-purple-600 text-white rounded-full shadow-lg hover:shadow-xl transition-all z-[9997] flex items-center justify-center';
        fab.innerHTML = `
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
        `;
        fab.title = 'Add New Task (Ctrl+Shift+T)';
        fab.onclick = () => this.openAddTaskModal();

        document.body.appendChild(fab);
    }
}
