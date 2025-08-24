<!DOCTYPE html>
<html>
<head>
    <title>Debug Modal</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="p-8">
    <h1 class="text-2xl mb-4">Modal Debug Test</h1>
    
    <button onclick="testModal()" class="bg-blue-600 text-white px-4 py-2 rounded mb-4">
        Test Updated Modal
    </button>
    
    <div class="bg-gray-100 p-4 rounded">
        <h3 class="font-bold">Expected Fields:</h3>
        <ul class="list-disc ml-6">
            <li>Task Title (required)</li>
            <li>Description</li>
            <li>Assign To (required)</li>
            <li>Due Date & Priority</li>
            <li>Estimated Hours & Due Time</li>
            <li><strong>File Attachment (NEW)</strong></li>
            <li><strong>Reference Link (NEW)</strong></li>
        </ul>
    </div>

    <script src="assets/js/global-task-manager.js?v=<?= time() ?>"></script>
    <script>
    // Mock session data for testing
    window.userRole = 'admin';
    window.userId = 1;
    window.userName = 'Test Admin';

    function testModal() {
        // Create a minimal test to verify the fields
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';
        
        modal.innerHTML = `
            <div class="bg-white rounded-xl p-6 max-w-md w-full shadow-2xl">
                <h3 class="text-lg font-semibold mb-4">Test Modal - Verify Fields</h3>
                
                <div class="space-y-3 text-sm">
                    <div class="border p-3 rounded">
                        <label class="block font-medium text-gray-700 mb-1">File Attachment</label>
                        <input type="file" name="attachment" class="w-full border rounded p-2">
                    </div>
                    
                    <div class="border p-3 rounded">
                        <label class="block font-medium text-gray-700 mb-1">Reference Link</label>
                        <input type="url" name="reference_link" placeholder="https://example.com" class="w-full border rounded p-2">
                    </div>
                </div>
                
                <div class="mt-4 text-center">
                    <button onclick="this.parentElement.parentElement.parentElement.remove()" 
                            class="bg-red-600 text-white px-4 py-2 rounded">
                        Close
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
    }
    
    // Test if GlobalTaskManager is available
    document.addEventListener('DOMContentLoaded', function() {
        console.log('GlobalTaskManager available:', typeof window.globalTaskManager);
        
        if (window.globalTaskManager) {
            // Override users for testing
            window.globalTaskManager.users = [
                {id: 1, name: 'Test User', department: 'Testing'}
            ];
        }
    });
    </script>
</body>
</html>