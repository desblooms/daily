/**
 * Enhanced Task Management System
 * Comprehensive task management with file attachments, work outputs, and collaboration
 */

class EnhancedTaskManager {
    constructor(options = {}) {
        this.apiBase = options.apiBase || 'api/';
        this.userRole = options.userRole || 'user';
        this.userId = options.userId || null;
        this.currentView = 'list';
        this.selectedDate = options.selectedDate || new Date().toISOString().split('T')[0];
        this.tasks = [];
        this.users = [];
        this.uploadedFiles = new Map();
        
        // Initialize
        this.init();
    }

    async init() {
        await this.loadUsers();
        await this.loadTasks();
        this.setupEventListeners();
        this.initializeTooltips();
    }

    // ===========================================
    // DATA LOADING METHODS
    // ===========================================

    async loadUsers() {
        try {
            const response = await fetch(`${this.apiBase}users.php?action=get_active_users`);
            const data = await response.json();
            if (data.success) {
                this.users = data.users || [];
            }
        } catch (error) {
            console.error('Error loading users:', error);
            this.showNotification('Error loading users', 'error');
        }
    }

    async loadTasks(date = null, userId = null) {
        try {
            const params = new URLSearchParams({
                action: 'get_tasks',
                date: date || this.selectedDate
            });
            
            if (userId && this.userRole === 'admin') {
                params.append('user_id', userId);
            }

            const response = await fetch(`${this.apiBase}tasks.php?${params}`);
            const data = await response.json();
            
            if (data.success) {
                this.tasks = data.tasks || [];
                this.renderTaskList();
                this.updateTaskStats();
            } else {
                this.showNotification(data.message || 'Error loading tasks', 'error');
            }
        } catch (error) {
            console.error('Error loading tasks:', error);
            this.showNotification('Error loading tasks', 'error');
        }
    }

    // ===========================================
    // TASK CREATION & MANAGEMENT
    // ===========================================

    showCreateTaskModal() {
        const modal = this.createModal('create-task-modal', 'Create Enhanced Task', this.getCreateTaskModalContent());
        this.setupCreateTaskForm(modal);
    }

    getCreateTaskModalContent() {
        const userOptions = this.users.map(user => 
            `<option value="${user.id}">${user.name} (${user.department || 'No Dept'})</option>`
        ).join('');

        return `
            <form id="enhanced-task-form" class="space-y-6">
                <!-- Basic Information -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                        <i class="fas fa-info-circle mr-2 text-blue-500"></i>
                        Basic Information
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Task Title <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="title" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Enter task title">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                            <select name="task_category" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Category</option>
                                <option value="Development">Development</option>
                                <option value="Design">Design</option>
                                <option value="Marketing">Marketing</option>
                                <option value="Research">Research</option>
                                <option value="Testing">Testing</option>
                                <option value="Documentation">Documentation</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Assign To <span class="text-red-500">*</span>
                            </label>
                            <select name="assigned_to" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select User</option>
                                ${userOptions}
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                            <select name="priority" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Due Date <span class="text-red-500">*</span>
                            </label>
                            <input type="date" name="date" required value="${this.selectedDate}"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Due Time</label>
                            <input type="time" name="due_time"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estimated Hours</label>
                        <input type="number" name="estimated_hours" min="0.5" step="0.5" placeholder="e.g., 4.5"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="details" rows="3" placeholder="Detailed task description..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 resize-vertical"></textarea>
                    </div>
                </div>

                <!-- Requirements & Deliverables -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                        <i class="fas fa-tasks mr-2 text-green-500"></i>
                        Requirements & Deliverables
                    </h4>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Requirements</label>
                            <textarea name="requirements" rows="2" placeholder="List task requirements..."
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 resize-vertical"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Expected Deliverables</label>
                            <textarea name="deliverables" rows="2" placeholder="What should be delivered..."
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 resize-vertical"></textarea>
                        </div>
                    </div>
                    
                    <!-- Task Specifications -->
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Detailed Specifications</label>
                        <div id="specifications-container" class="space-y-2 mb-3">
                            <!-- Specifications will be added here -->
                        </div>
                        <button type="button" onclick="this.closest('.enhanced-task-manager').taskManager.addSpecification()" 
                            class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition-colors">
                            <i class="fas fa-plus mr-1"></i> Add Specification
                        </button>
                    </div>
                </div>

                <!-- External Resources -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                        <i class="fas fa-link mr-2 text-purple-500"></i>
                        External Resources
                    </h4>
                    <div id="external-links-container" class="space-y-2 mb-3">
                        <!-- External links will be added here -->
                    </div>
                    <button type="button" onclick="this.closest('.enhanced-task-manager').taskManager.addExternalLink()" 
                        class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors">
                        <i class="fas fa-link mr-1"></i> Add Reference Link
                    </button>
                </div>

                <!-- File Attachments -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                        <i class="fas fa-paperclip mr-2 text-orange-500"></i>
                        File Attachments
                    </h4>
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center cursor-pointer hover:border-blue-400 transition-colors" 
                        id="file-drop-zone">
                        <input type="file" id="file-input" multiple class="hidden" 
                            accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.mp4,.webm">
                        <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                        <p class="text-gray-600">Click to upload files or drag and drop</p>
                        <p class="text-sm text-gray-500">Supports images, documents, videos, and archives (Max 50MB each)</p>
                    </div>
                    <div id="uploaded-files-preview" class="mt-4">
                        <!-- File previews will be shown here -->
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex justify-end space-x-3 pt-4 border-t">
                    <button type="button" onclick="this.closest('.enhanced-task-manager').taskManager.closeModal()" 
                        class="px-6 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                        class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                        <i class="fas fa-save mr-1"></i> Create Task
                    </button>
                </div>
            </form>
        `;
    }

    setupCreateTaskForm(modal) {
        const form = modal.querySelector('#enhanced-task-form');
        const fileInput = modal.querySelector('#file-input');
        const dropZone = modal.querySelector('#file-drop-zone');
        const filesPreview = modal.querySelector('#uploaded-files-preview');

        // File upload handling
        this.setupFileUpload(fileInput, dropZone, filesPreview);

        // Add initial specification and link
        setTimeout(() => {
            this.addSpecification();
            this.addExternalLink();
        }, 100);

        // Form submission
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.submitEnhancedTask(form);
        });
    }

    setupFileUpload(fileInput, dropZone, preview) {
        // Click to upload
        dropZone.addEventListener('click', () => fileInput.click());

        // File selection
        fileInput.addEventListener('change', (e) => {
            this.handleFileSelection(Array.from(e.target.files), preview);
        });

        // Drag and drop
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('border-blue-400', 'bg-blue-50');
        });

        dropZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            dropZone.classList.remove('border-blue-400', 'bg-blue-50');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('border-blue-400', 'bg-blue-50');
            this.handleFileSelection(Array.from(e.dataTransfer.files), preview);
        });
    }

    handleFileSelection(files, preview) {
        files.forEach(file => {
            if (file.size > 50 * 1024 * 1024) {
                this.showNotification(`File "${file.name}" is too large (max 50MB)`, 'error');
                return;
            }

            const fileId = this.generateId();
            this.uploadedFiles.set(fileId, file);
            this.addFilePreview(fileId, file, preview);
        });
    }

    addFilePreview(fileId, file, preview) {
        const fileDiv = document.createElement('div');
        fileDiv.className = 'flex items-center justify-between bg-white p-3 rounded-md border';
        fileDiv.innerHTML = `
            <div class="flex items-center">
                <i class="${this.getFileIcon(file.type)} text-lg mr-3 text-blue-500"></i>
                <div>
                    <div class="font-medium text-sm">${file.name}</div>
                    <div class="text-xs text-gray-500">${this.formatFileSize(file.size)}</div>
                </div>
            </div>
            <button type="button" onclick="this.closest('.enhanced-task-manager').taskManager.removeFile('${fileId}', this.parentElement)" 
                class="text-red-500 hover:text-red-700">
                <i class="fas fa-trash"></i>
            </button>
        `;
        preview.appendChild(fileDiv);
    }

    removeFile(fileId, element) {
        this.uploadedFiles.delete(fileId);
        element.remove();
    }

    addSpecification() {
        const container = document.getElementById('specifications-container');
        const specDiv = document.createElement('div');
        specDiv.className = 'flex space-x-2 items-start bg-white p-3 rounded border';
        specDiv.innerHTML = `
            <select class="px-2 py-1 border border-gray-300 rounded text-sm">
                <option value="requirement">Requirement</option>
                <option value="deliverable">Deliverable</option>
                <option value="acceptance_criteria">Acceptance Criteria</option>
                <option value="resource">Resource</option>
            </select>
            <div class="flex-1">
                <input type="text" placeholder="Specification title" 
                    class="w-full px-2 py-1 border border-gray-300 rounded text-sm mb-1">
                <textarea placeholder="Description" rows="2"
                    class="w-full px-2 py-1 border border-gray-300 rounded text-sm resize-none"></textarea>
            </div>
            <button type="button" onclick="this.parentElement.remove()" 
                class="text-red-500 hover:text-red-700 p-1">
                <i class="fas fa-times"></i>
            </button>
        `;
        container.appendChild(specDiv);
    }

    addExternalLink() {
        const container = document.getElementById('external-links-container');
        const linkDiv = document.createElement('div');
        linkDiv.className = 'flex space-x-2 items-center bg-white p-3 rounded border';
        linkDiv.innerHTML = `
            <input type="text" placeholder="Link title" 
                class="flex-1 px-2 py-1 border border-gray-300 rounded text-sm">
            <input type="url" placeholder="https://example.com" 
                class="flex-1 px-2 py-1 border border-gray-300 rounded text-sm">
            <button type="button" onclick="this.parentElement.remove()" 
                class="text-red-500 hover:text-red-700 p-1">
                <i class="fas fa-times"></i>
            </button>
        `;
        container.appendChild(linkDiv);
    }

    async submitEnhancedTask(form) {
        try {
            const formData = new FormData();
            formData.append('action', 'create_task');

            // Basic form data
            const formElements = form.elements;
            for (let element of formElements) {
                if (element.name && element.value && element.type !== 'file') {
                    formData.append(element.name, element.value);
                }
            }

            // Process specifications
            const specifications = [];
            const specElements = document.querySelectorAll('#specifications-container > div');
            specElements.forEach(specDiv => {
                const select = specDiv.querySelector('select');
                const titleInput = specDiv.querySelector('input[type="text"]');
                const descTextarea = specDiv.querySelector('textarea');
                
                if (titleInput.value.trim()) {
                    specifications.push({
                        type: select.value,
                        title: titleInput.value.trim(),
                        description: descTextarea.value.trim(),
                        priority: 'medium'
                    });
                }
            });

            if (specifications.length > 0) {
                formData.append('specifications', JSON.stringify(specifications));
            }

            // Process external links
            const externalLinks = [];
            const linkElements = document.querySelectorAll('#external-links-container > div');
            linkElements.forEach(linkDiv => {
                const titleInput = linkDiv.querySelector('input[type="text"]');
                const urlInput = linkDiv.querySelector('input[type="url"]');
                
                if (titleInput.value.trim() && urlInput.value.trim()) {
                    externalLinks.push({
                        title: titleInput.value.trim(),
                        url: urlInput.value.trim()
                    });
                }
            });

            if (externalLinks.length > 0) {
                formData.append('external_links', JSON.stringify(externalLinks));
            }

            // Submit task
            const response = await fetch(`${this.apiBase}tasks.php`, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            
            if (result.success) {
                const taskId = result.task_id;
                
                // Upload files if any
                if (this.uploadedFiles.size > 0) {
                    await this.uploadTaskFiles(taskId);
                }
                
                this.showNotification('Task created successfully!', 'success');
                this.closeModal();
                await this.loadTasks();
                
                // Clear uploaded files
                this.uploadedFiles.clear();
            } else {
                this.showNotification(result.message || 'Error creating task', 'error');
            }
        } catch (error) {
            console.error('Error submitting task:', error);
            this.showNotification('Error creating task', 'error');
        }
    }

    async uploadTaskFiles(taskId) {
        const uploadPromises = Array.from(this.uploadedFiles.values()).map(async (file) => {
            const fileFormData = new FormData();
            fileFormData.append('action', 'upload');
            fileFormData.append('task_id', taskId);
            fileFormData.append('file', file);
            fileFormData.append('attachment_type', 'input');
            fileFormData.append('description', `Uploaded: ${file.name}`);

            try {
                const response = await fetch(`${this.apiBase}attachments.php`, {
                    method: 'POST',
                    body: fileFormData
                });
                
                const result = await response.json();
                if (!result.success) {
                    console.error(`Failed to upload ${file.name}:`, result.message);
                }
            } catch (error) {
                console.error(`Error uploading ${file.name}:`, error);
            }
        });

        await Promise.all(uploadPromises);
    }

    // ===========================================
    // TASK DISPLAY & INTERACTION
    // ===========================================

    renderTaskList() {
        const container = document.getElementById('tasks-container');
        if (!container) return;

        if (this.tasks.length === 0) {
            container.innerHTML = this.getEmptyStateHTML();
            return;
        }

        const tasksHTML = this.tasks.map(task => this.renderTaskCard(task)).join('');
        container.innerHTML = `<div class="space-y-4">${tasksHTML}</div>`;
    }

    renderTaskCard(task) {
        const priorityColors = {
            high: 'border-red-500 bg-red-50',
            medium: 'border-yellow-500 bg-yellow-50',
            low: 'border-green-500 bg-green-50'
        };

        const statusColors = {
            'Pending': 'bg-gray-100 text-gray-800',
            'On Progress': 'bg-blue-100 text-blue-800',
            'Done': 'bg-green-100 text-green-800',
            'Approved': 'bg-purple-100 text-purple-800',
            'On Hold': 'bg-orange-100 text-orange-800'
        };

        const dueDate = new Date(task.date);
        const today = new Date();
        const isOverdue = dueDate < today && !['Done', 'Approved'].includes(task.status);
        
        return `
            <div class="task-card bg-white rounded-lg shadow-md border-l-4 ${priorityColors[task.priority] || 'border-gray-300'} p-4 hover:shadow-lg transition-shadow">
                <div class="flex justify-between items-start mb-3">
                    <div class="flex-1">
                        <h3 class="font-semibold text-lg text-gray-800 mb-1">${this.escapeHtml(task.title)}</h3>
                        <div class="flex items-center space-x-4 text-sm text-gray-600">
                            <span class="flex items-center">
                                <i class="fas fa-user mr-1"></i>
                                ${this.escapeHtml(task.assigned_to_name || 'Unassigned')}
                            </span>
                            <span class="flex items-center">
                                <i class="fas fa-calendar mr-1"></i>
                                ${this.formatDate(task.date)}
                                ${isOverdue ? '<i class="fas fa-exclamation-triangle text-red-500 ml-1" title="Overdue"></i>' : ''}
                            </span>
                            ${task.task_category ? `
                                <span class="flex items-center">
                                    <i class="fas fa-tag mr-1"></i>
                                    ${this.escapeHtml(task.task_category)}
                                </span>
                            ` : ''}
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="px-2 py-1 rounded-full text-xs font-medium ${statusColors[task.status] || 'bg-gray-100 text-gray-800'}">
                            ${task.status}
                        </span>
                        <span class="px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 capitalize">
                            ${task.priority}
                        </span>
                    </div>
                </div>

                ${task.details ? `
                    <p class="text-gray-600 text-sm mb-3 line-clamp-2">${this.escapeHtml(task.details)}</p>
                ` : ''}

                <div class="flex justify-between items-center">
                    <div class="flex items-center space-x-4 text-sm text-gray-500">
                        ${task.estimated_hours ? `
                            <span class="flex items-center">
                                <i class="fas fa-clock mr-1"></i>
                                ${task.estimated_hours}h est.
                            </span>
                        ` : ''}
                        ${task.due_time ? `
                            <span class="flex items-center">
                                <i class="fas fa-hourglass-half mr-1"></i>
                                ${task.due_time}
                            </span>
                        ` : ''}
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        <button onclick="taskManager.viewTaskDetails(${task.id})" 
                            class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            <i class="fas fa-eye mr-1"></i> View Details
                        </button>
                        ${this.userRole === 'admin' || task.assigned_to == this.userId ? `
                            <button onclick="taskManager.editTask(${task.id})" 
                                class="text-green-600 hover:text-green-800 text-sm font-medium">
                                <i class="fas fa-edit mr-1"></i> Edit
                            </button>
                        ` : ''}
                        ${this.userRole === 'admin' ? `
                            <button onclick="taskManager.deleteTask(${task.id})" 
                                class="text-red-600 hover:text-red-800 text-sm font-medium">
                                <i class="fas fa-trash mr-1"></i> Delete
                            </button>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    }

    getEmptyStateHTML() {
        return `
            <div class="text-center py-12">
                <i class="fas fa-tasks text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-medium text-gray-600 mb-2">No tasks found</h3>
                <p class="text-gray-500 mb-6">There are no tasks for the selected date.</p>
                ${this.userRole === 'admin' ? `
                    <button onclick="taskManager.showCreateTaskModal()" 
                        class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-plus mr-2"></i> Create First Task
                    </button>
                ` : ''}
            </div>
        `;
    }

    // ===========================================
    // TASK DETAILS & OPERATIONS
    // ===========================================

    async viewTaskDetails(taskId) {
        try {
            const response = await fetch(`${this.apiBase}tasks.php?action=get_enhanced_details&task_id=${taskId}`);
            const data = await response.json();
            
            if (data.success) {
                this.showTaskDetailsModal(data);
            } else {
                this.showNotification(data.message || 'Error loading task details', 'error');
            }
        } catch (error) {
            console.error('Error loading task details:', error);
            this.showNotification('Error loading task details', 'error');
        }
    }

    showTaskDetailsModal(data) {
        const task = data.task;
        const specifications = data.specifications || [];
        const workOutputs = data.work_outputs || [];
        const progressUpdates = data.progress_updates || [];
        const attachments = data.attachments || [];

        const content = this.getTaskDetailsContent(task, specifications, workOutputs, progressUpdates, attachments);
        const modal = this.createModal('task-details-modal', `Task: ${task.title}`, content, 'max-w-6xl');
        
        this.setupTaskDetailsModal(modal, task);
    }

    getTaskDetailsContent(task, specifications, workOutputs, progressUpdates, attachments) {
        return `
            <div class="space-y-6">
                <!-- Task Overview -->
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-6 rounded-lg border">
                    <div class="flex justify-between items-start mb-4">
                        <div class="flex-1">
                            <h2 class="text-2xl font-bold text-gray-800 mb-2">${this.escapeHtml(task.title)}</h2>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-600">Status:</span>
                                    <span class="ml-1 px-2 py-1 rounded-full text-xs ${this.getStatusClasses(task.status)}">
                                        ${task.status}
                                    </span>
                                </div>
                                <div>
                                    <span class="text-gray-600">Priority:</span>
                                    <span class="ml-1 px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800 capitalize">
                                        ${task.priority}
                                    </span>
                                </div>
                                <div>
                                    <span class="text-gray-600">Due Date:</span>
                                    <span class="ml-1 font-medium">${this.formatDate(task.date)}</span>
                                </div>
                                <div>
                                    <span class="text-gray-600">Assigned:</span>
                                    <span class="ml-1 font-medium">${this.escapeHtml(task.assigned_to_name || 'Unassigned')}</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            ${this.getUserActionButtons(task)}
                        </div>
                    </div>
                    
                    ${task.details ? `
                        <div class="mt-4">
                            <h4 class="font-semibold text-gray-700 mb-2">Description</h4>
                            <p class="text-gray-600 leading-relaxed">${this.escapeHtml(task.details)}</p>
                        </div>
                    ` : ''}
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        ${task.requirements ? `
                            <div>
                                <h4 class="font-semibold text-gray-700 mb-2 flex items-center">
                                    <i class="fas fa-list-check mr-2 text-green-500"></i>
                                    Requirements
                                </h4>
                                <p class="text-gray-600 text-sm">${this.escapeHtml(task.requirements)}</p>
                            </div>
                        ` : ''}
                        
                        ${task.deliverables ? `
                            <div>
                                <h4 class="font-semibold text-gray-700 mb-2 flex items-center">
                                    <i class="fas fa-box mr-2 text-purple-500"></i>
                                    Deliverables
                                </h4>
                                <p class="text-gray-600 text-sm">${this.escapeHtml(task.deliverables)}</p>
                            </div>
                        ` : ''}
                    </div>
                    
                    ${task.external_links ? this.renderExternalLinks(JSON.parse(task.external_links)) : ''}
                </div>

                <!-- Tabbed Content -->
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8">
                        <button onclick="taskManager.switchTab('specifications')" 
                            class="tab-button py-2 px-1 border-b-2 font-medium text-sm active" data-tab="specifications">
                            Specifications (${specifications.length})
                        </button>
                        <button onclick="taskManager.switchTab('attachments')" 
                            class="tab-button py-2 px-1 border-b-2 font-medium text-sm" data-tab="attachments">
                            Attachments (${attachments.length})
                        </button>
                        <button onclick="taskManager.switchTab('outputs')" 
                            class="tab-button py-2 px-1 border-b-2 font-medium text-sm" data-tab="outputs">
                            Work Outputs (${workOutputs.length})
                        </button>
                        <button onclick="taskManager.switchTab('progress')" 
                            class="tab-button py-2 px-1 border-b-2 font-medium text-sm" data-tab="progress">
                            Progress (${progressUpdates.length})
                        </button>
                    </nav>
                </div>

                <!-- Tab Content -->
                <div class="tab-content">
                    <!-- Specifications Tab -->
                    <div id="specifications-tab" class="tab-pane active">
                        ${this.renderSpecifications(specifications, task)}
                    </div>
                    
                    <!-- Attachments Tab -->
                    <div id="attachments-tab" class="tab-pane hidden">
                        ${this.renderAttachments(attachments, task)}
                    </div>
                    
                    <!-- Work Outputs Tab -->
                    <div id="outputs-tab" class="tab-pane hidden">
                        ${this.renderWorkOutputs(workOutputs, task)}
                    </div>
                    
                    <!-- Progress Tab -->
                    <div id="progress-tab" class="tab-pane hidden">
                        ${this.renderProgressUpdates(progressUpdates, task)}
                    </div>
                </div>
            </div>
        `;
    }

    getUserActionButtons(task) {
        const buttons = [];
        
        if (this.userRole === 'admin' || task.assigned_to == this.userId) {
            buttons.push(`
                <button onclick="taskManager.showStatusUpdateModal(${task.id})" 
                    class="px-3 py-1 text-sm bg-blue-600 text-white rounded hover:bg-blue-700">
                    Update Status
                </button>
            `);
            
            buttons.push(`
                <button onclick="taskManager.showProgressUpdateModal(${task.id})" 
                    class="px-3 py-1 text-sm bg-green-600 text-white rounded hover:bg-green-700">
                    Add Progress
                </button>
            `);
            
            buttons.push(`
                <button onclick="taskManager.showWorkOutputModal(${task.id})" 
                    class="px-3 py-1 text-sm bg-purple-600 text-white rounded hover:bg-purple-700">
                    Share Output
                </button>
            `);
        }
        
        return buttons.join('');
    }

    // ===========================================
    // HELPER METHODS
    // ===========================================

    switchTab(tabName) {
        // Update tab buttons
        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.classList.remove('active', 'border-blue-500', 'text-blue-600');
            btn.classList.add('border-transparent', 'text-gray-500');
        });
        
        const activeButton = document.querySelector(`[data-tab="${tabName}"]`);
        activeButton.classList.add('active', 'border-blue-500', 'text-blue-600');
        activeButton.classList.remove('border-transparent', 'text-gray-500');
        
        // Update tab content
        document.querySelectorAll('.tab-pane').forEach(pane => {
            pane.classList.add('hidden');
        });
        
        document.getElementById(`${tabName}-tab`).classList.remove('hidden');
    }

    createModal(id, title, content, size = 'max-w-4xl') {
        // Remove existing modal
        const existing = document.getElementById(id);
        if (existing) existing.remove();

        const modal = document.createElement('div');
        modal.id = id;
        modal.className = 'fixed inset-0 z-50 overflow-y-auto enhanced-task-manager';
        modal.innerHTML = `
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" onclick="this.closest('.enhanced-task-manager').taskManager.closeModal()">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:${size} sm:w-full">
                    <div class="bg-white px-6 pt-6 pb-4">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">${title}</h3>
                            <button onclick="this.closest('.enhanced-task-manager').taskManager.closeModal()" 
                                class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>
                        <div class="modal-content">
                            ${content}
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Store reference to task manager
        modal.taskManager = this;
        
        document.body.appendChild(modal);
        return modal;
    }

    closeModal() {
        document.querySelectorAll('[id$="-modal"]').forEach(modal => modal.remove());
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg transition-opacity ${this.getNotificationClasses(type)}`;
        notification.innerHTML = `
            <div class="flex items-center">
                <i class="${this.getNotificationIcon(type)} mr-2"></i>
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-lg">&times;</button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }

    getNotificationClasses(type) {
        const classes = {
            success: 'bg-green-500 text-white',
            error: 'bg-red-500 text-white',
            warning: 'bg-yellow-500 text-white',
            info: 'bg-blue-500 text-white'
        };
        return classes[type] || classes.info;
    }

    getNotificationIcon(type) {
        const icons = {
            success: 'fas fa-check-circle',
            error: 'fas fa-exclamation-circle',
            warning: 'fas fa-exclamation-triangle',
            info: 'fas fa-info-circle'
        };
        return icons[type] || icons.info;
    }

    getFileIcon(mimeType) {
        if (mimeType.startsWith('image/')) return 'fas fa-image';
        if (mimeType.startsWith('video/')) return 'fas fa-video';
        if (mimeType.includes('pdf')) return 'fas fa-file-pdf';
        if (mimeType.includes('word') || mimeType.includes('document')) return 'fas fa-file-word';
        if (mimeType.includes('excel') || mimeType.includes('sheet')) return 'fas fa-file-excel';
        if (mimeType.includes('powerpoint') || mimeType.includes('presentation')) return 'fas fa-file-powerpoint';
        if (mimeType.includes('zip') || mimeType.includes('rar')) return 'fas fa-file-archive';
        return 'fas fa-file';
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric' 
        });
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    generateId() {
        return Date.now().toString(36) + Math.random().toString(36).substr(2);
    }

    getStatusClasses(status) {
        const classes = {
            'Pending': 'bg-gray-100 text-gray-800',
            'On Progress': 'bg-blue-100 text-blue-800',
            'Done': 'bg-green-100 text-green-800',
            'Approved': 'bg-purple-100 text-purple-800',
            'On Hold': 'bg-orange-100 text-orange-800'
        };
        return classes[status] || 'bg-gray-100 text-gray-800';
    }

    updateTaskStats() {
        const stats = {
            total: this.tasks.length,
            pending: this.tasks.filter(t => t.status === 'Pending').length,
            inProgress: this.tasks.filter(t => t.status === 'On Progress').length,
            completed: this.tasks.filter(t => ['Done', 'Approved'].includes(t.status)).length,
            overdue: this.tasks.filter(t => {
                const dueDate = new Date(t.date);
                const today = new Date();
                return dueDate < today && !['Done', 'Approved'].includes(t.status);
            }).length
        };

        // Update stats display if elements exist
        this.updateStatElement('total-tasks', stats.total);
        this.updateStatElement('pending-tasks', stats.pending);
        this.updateStatElement('progress-tasks', stats.inProgress);
        this.updateStatElement('completed-tasks', stats.completed);
        this.updateStatElement('overdue-tasks', stats.overdue);
    }

    updateStatElement(id, value) {
        const element = document.getElementById(id);
        if (element) element.textContent = value;
    }

    setupEventListeners() {
        // Date navigation
        const dateInput = document.getElementById('task-date-picker');
        if (dateInput) {
            dateInput.addEventListener('change', (e) => {
                this.selectedDate = e.target.value;
                this.loadTasks();
            });
        }

        // User filter (admin only)
        const userFilter = document.getElementById('user-filter');
        if (userFilter) {
            userFilter.addEventListener('change', (e) => {
                const userId = e.target.value || null;
                this.loadTasks(null, userId);
            });
        }

        // Refresh button
        const refreshBtn = document.getElementById('refresh-tasks');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => this.loadTasks());
        }
    }

    initializeTooltips() {
        // Add tooltip functionality if needed
        document.querySelectorAll('[title]').forEach(element => {
            // Simple tooltip implementation
            element.addEventListener('mouseenter', function() {
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.textContent = this.title;
                // Position and show tooltip
            });
        });
    }

    // Additional placeholder methods for features mentioned in renderSpecifications, etc.
    renderSpecifications(specifications, task) {
        if (specifications.length === 0) {
            return `
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-list-ul text-3xl mb-3"></i>
                    <p>No specifications defined for this task.</p>
                </div>
            `;
        }

        const grouped = specifications.reduce((acc, spec) => {
            if (!acc[spec.spec_type]) acc[spec.spec_type] = [];
            acc[spec.spec_type].push(spec);
            return acc;
        }, {});

        return Object.entries(grouped).map(([type, specs]) => `
            <div class="mb-6">
                <h4 class="font-semibold text-gray-700 mb-3 capitalize">${type.replace('_', ' ')}</h4>
                <div class="space-y-2">
                    ${specs.map(spec => `
                        <div class="bg-gray-50 p-3 rounded border-l-4 ${this.getSpecPriorityColor(spec.priority)}">
                            <h5 class="font-medium text-gray-800">${this.escapeHtml(spec.title)}</h5>
                            <p class="text-gray-600 text-sm mt-1">${this.escapeHtml(spec.description)}</p>
                            <div class="flex items-center justify-between mt-2">
                                <span class="text-xs text-gray-500 capitalize">${spec.priority} priority</span>
                                ${spec.is_completed ? '<i class="fas fa-check-circle text-green-500"></i>' : ''}
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `).join('');
    }

    renderAttachments(attachments, task) {
        if (attachments.length === 0) {
            return `
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-paperclip text-3xl mb-3"></i>
                    <p>No attachments uploaded for this task.</p>
                </div>
            `;
        }

        return `
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                ${attachments.map(attachment => `
                    <div class="bg-gray-50 p-4 rounded-lg border">
                        <div class="flex items-start justify-between mb-3">
                            <i class="${this.getFileIcon(attachment.mime_type)} text-2xl text-blue-500"></i>
                            <span class="text-xs px-2 py-1 rounded bg-blue-100 text-blue-800 capitalize">
                                ${attachment.attachment_type}
                            </span>
                        </div>
                        <h5 class="font-medium text-gray-800 mb-1 truncate" title="${attachment.original_filename}">
                            ${attachment.original_filename}
                        </h5>
                        <p class="text-xs text-gray-500 mb-3">${this.formatFileSize(attachment.file_size)}</p>
                        ${attachment.description ? `
                            <p class="text-sm text-gray-600 mb-3">${this.escapeHtml(attachment.description)}</p>
                        ` : ''}
                        <div class="flex space-x-2">
                            <button onclick="taskManager.downloadAttachment(${attachment.id})" 
                                class="text-xs px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700">
                                Download
                            </button>
                            ${this.userRole === 'admin' || task.assigned_to == this.userId ? `
                                <button onclick="taskManager.deleteAttachment(${attachment.id})" 
                                    class="text-xs px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700">
                                    Delete
                                </button>
                            ` : ''}
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    renderWorkOutputs(outputs, task) {
        if (outputs.length === 0) {
            return `
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-share text-3xl mb-3"></i>
                    <p>No work outputs shared yet.</p>
                    ${(this.userRole === 'admin' || task.assigned_to == this.userId) ? `
                        <button onclick="taskManager.showWorkOutputModal(${task.id})" 
                            class="mt-4 px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700">
                            Share Work Output
                        </button>
                    ` : ''}
                </div>
            `;
        }

        return `
            <div class="space-y-4">
                ${outputs.map(output => `
                    <div class="bg-white border rounded-lg p-4 shadow-sm">
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex-1">
                                <h5 class="font-semibold text-gray-800">${this.escapeHtml(output.title)}</h5>
                                <p class="text-sm text-gray-600 mt-1">${this.escapeHtml(output.description || '')}</p>
                            </div>
                            <span class="text-xs px-2 py-1 rounded bg-purple-100 text-purple-800 capitalize">
                                ${output.output_type}
                            </span>
                        </div>
                        
                        ${output.external_url ? `
                            <a href="${output.external_url}" target="_blank" 
                                class="inline-flex items-center text-blue-600 hover:text-blue-800 text-sm">
                                <i class="fas fa-external-link-alt mr-1"></i>
                                View Output
                            </a>
                        ` : ''}
                        
                        <div class="flex justify-between items-center mt-3 text-xs text-gray-500">
                            <span>By ${this.escapeHtml(output.created_by_name)} • ${this.formatDate(output.created_at)}</span>
                            <span>${output.view_count} views</span>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    renderProgressUpdates(updates, task) {
        if (updates.length === 0) {
            return `
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-chart-line text-3xl mb-3"></i>
                    <p>No progress updates yet.</p>
                    ${(this.userRole === 'admin' || task.assigned_to == this.userId) ? `
                        <button onclick="taskManager.showProgressUpdateModal(${task.id})" 
                            class="mt-4 px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                            Add Progress Update
                        </button>
                    ` : ''}
                </div>
            `;
        }

        return `
            <div class="space-y-4">
                ${updates.map(update => `
                    <div class="bg-white border rounded-lg p-4 shadow-sm">
                        <div class="flex justify-between items-start mb-2">
                            <div class="flex items-center space-x-2">
                                <img src="${update.user_avatar || '/assets/img/default-avatar.png'}" 
                                    alt="${update.user_name}" class="w-8 h-8 rounded-full">
                                <div>
                                    <span class="font-medium text-gray-800">${this.escapeHtml(update.user_name)}</span>
                                    <span class="text-sm text-gray-500 ml-2">${this.formatDate(update.created_at)}</span>
                                </div>
                            </div>
                            ${update.progress_percentage ? `
                                <span class="text-sm font-medium text-blue-600">${update.progress_percentage}%</span>
                            ` : ''}
                        </div>
                        
                        ${update.title ? `
                            <h6 class="font-medium text-gray-800 mb-2">${this.escapeHtml(update.title)}</h6>
                        ` : ''}
                        
                        <p class="text-gray-600 text-sm mb-3">${this.escapeHtml(update.description)}</p>
                        
                        <div class="flex justify-between items-center text-xs text-gray-500">
                            <div class="flex space-x-4">
                                <span class="capitalize">${update.update_type.replace('_', ' ')}</span>
                                ${update.hours_logged ? `<span>${update.hours_logged}h logged</span>` : ''}
                                ${update.is_milestone ? '<span class="text-yellow-600">🏆 Milestone</span>' : ''}
                            </div>
                            <span class="capitalize">${update.visibility} visibility</span>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    renderExternalLinks(links) {
        if (!links || links.length === 0) return '';
        
        return `
            <div class="mt-4">
                <h4 class="font-semibold text-gray-700 mb-2 flex items-center">
                    <i class="fas fa-link mr-2 text-blue-500"></i>
                    Reference Links
                </h4>
                <div class="flex flex-wrap gap-2">
                    ${links.map(link => `
                        <a href="${link.url}" target="_blank" 
                            class="inline-flex items-center px-3 py-1 text-sm bg-blue-100 text-blue-800 rounded-full hover:bg-blue-200">
                            <i class="fas fa-external-link-alt mr-1 text-xs"></i>
                            ${this.escapeHtml(link.title)}
                        </a>
                    `).join('')}
                </div>
            </div>
        `;
    }

    getSpecPriorityColor(priority) {
        const colors = {
            'critical': 'border-red-500',
            'high': 'border-orange-500',
            'medium': 'border-yellow-500',
            'low': 'border-green-500'
        };
        return colors[priority] || 'border-gray-300';
    }

    // Placeholder methods that would be implemented for full functionality
    downloadAttachment(attachmentId) {
        window.open(`${this.apiBase}attachments.php?action=download&id=${attachmentId}`, '_blank');
    }

    async deleteAttachment(attachmentId) {
        if (!confirm('Are you sure you want to delete this attachment?')) return;
        
        try {
            const formData = new FormData();
            formData.append('action', 'delete_attachment');
            formData.append('attachment_id', attachmentId);
            
            const response = await fetch(`${this.apiBase}attachments.php`, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            if (result.success) {
                this.showNotification('Attachment deleted successfully', 'success');
                // Refresh the current modal/view
            } else {
                this.showNotification(result.message || 'Error deleting attachment', 'error');
            }
        } catch (error) {
            console.error('Error deleting attachment:', error);
            this.showNotification('Error deleting attachment', 'error');
        }
    }

    showStatusUpdateModal(taskId) {
        // Implementation for status update modal
        console.log('Show status update modal for task:', taskId);
    }

    showProgressUpdateModal(taskId) {
        // Implementation for progress update modal
        console.log('Show progress update modal for task:', taskId);
    }

    showWorkOutputModal(taskId) {
        // Implementation for work output modal
        console.log('Show work output modal for task:', taskId);
    }

    editTask(taskId) {
        // Implementation for task editing
        console.log('Edit task:', taskId);
    }

    async deleteTask(taskId) {
        if (!confirm('Are you sure you want to delete this task?')) return;
        
        try {
            const formData = new FormData();
            formData.append('action', 'delete_task');
            formData.append('task_id', taskId);
            
            const response = await fetch(`${this.apiBase}tasks.php`, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            if (result.success) {
                this.showNotification('Task deleted successfully', 'success');
                this.loadTasks();
            } else {
                this.showNotification(result.message || 'Error deleting task', 'error');
            }
        } catch (error) {
            console.error('Error deleting task:', error);
            this.showNotification('Error deleting task', 'error');
        }
    }
}

// Initialize global task manager
let taskManager;
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.userRole !== 'undefined' && typeof window.userId !== 'undefined') {
        taskManager = new EnhancedTaskManager({
            userRole: window.userRole,
            userId: window.userId
        });
    }
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = EnhancedTaskManager;
}