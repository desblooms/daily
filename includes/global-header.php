<?php
// Global header file for task manager initialization
// Include this in all pages that need the Add Task functionality
?>
<script>
// Set global variables for the task manager
window.userRole = '<?= $_SESSION['role'] ?? 'user' ?>';
window.userId = <?= $_SESSION['user_id'] ?? 'null' ?>;
window.userName = '<?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>';

// Initialize global task manager
let globalTaskManager;
document.addEventListener('DOMContentLoaded', function() {
    // Check if the GlobalTaskManager class exists
    if (typeof GlobalTaskManager !== 'undefined') {
        globalTaskManager = new GlobalTaskManager();
        
        // Add floating action button on non-admin pages
        if (window.userRole === 'admin' && !window.location.pathname.includes('admin-dashboard')) {
            globalTaskManager.addFloatingActionButton();
        }
    } else {
        console.warn('GlobalTaskManager class not found. Make sure global-task-manager.js is loaded.');
    }
});

// Fallback function if globalTaskManager is not initialized
function safeCallTaskManager(method, ...args) {
    if (globalTaskManager && typeof globalTaskManager[method] === 'function') {
        return globalTaskManager[method](...args);
    } else {
        console.error(`GlobalTaskManager not initialized or method '${method}' not found`);
        alert('Task manager is not ready. Please refresh the page and try again.');
    }
}
</script>