<?php
// debug-api.php - Place this in your root directory to debug API issues
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Check if this is an AJAX request for testing
if (isset($_GET['test_api'])) {
    header('Content-Type: application/json');
    
    try {
        // Test database connection
        require_once 'includes/db.php';
        
        // Test session
        if (!isset($_SESSION['user_id'])) {
            // Create a test session for debugging
            $_SESSION['user_id'] = 1;
            $_SESSION['role'] = 'admin';
            $_SESSION['name'] = 'Test Admin';
        }
        
        // Test a simple query
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tasks");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'API test successful',
            'task_count' => $result['count'],
            'session_user_id' => $_SESSION['user_id'],
            'session_role' => $_SESSION['role']
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'API test failed',
            'error' => $e->getMessage()
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Debug Tool</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">🔧 API Debug Tool</h1>
        
        <!-- Session Status -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">👤 Session Status</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <strong>User ID:</strong> 
                    <span class="<?php echo isset($_SESSION['user_id']) ? 'text-green-600' : 'text-red-600'; ?>">
                        <?php echo $_SESSION['user_id'] ?? 'Not Set'; ?>
                    </span>
                </div>
                <div>
                    <strong>Role:</strong> 
                    <span class="<?php echo isset($_SESSION['role']) ? 'text-green-600' : 'text-red-600'; ?>">
                        <?php echo $_SESSION['role'] ?? 'Not Set'; ?>
                    </span>
                </div>
            </div>
            
            <?php if (!isset($_SESSION['user_id'])): ?>
            <div class="mt-4 p-3 bg-yellow-100 border border-yellow-400 rounded">
                <strong>⚠️ No Session Found</strong><br>
                <button onclick="createTestSession()" class="mt-2 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    Create Test Session
                </button>
            </div>
            <?php endif; ?>
        </div>

        <!-- Database Test -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">🗄️ Database Connection</h2>
            <?php
            try {
                require_once 'includes/db.php';
                echo '<div class="text-green-600">✅ Database connection successful</div>';
                
                // Test query
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users");
                $stmt->execute();
                $userCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tasks");
                $stmt->execute();
                $taskCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                echo "<div class=\"mt-2\">Users in database: <strong>$userCount</strong></div>";
                echo "<div>Tasks in database: <strong>$taskCount</strong></div>";
                
            } catch (Exception $e) {
                echo '<div class="text-red-600">❌ Database connection failed: ' . $e->getMessage() . '</div>';
            }
            ?>
        </div>

        <!-- API Tests -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">🔌 API Tests</h2>
            <div class="space-y-4">
                <button onclick="testUsersAPI()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 mr-2">
                    Test Users API
                </button>
                <button onclick="testTasksAPI()" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 mr-2">
                    Test Tasks API
                </button>
                <button onclick="testNewTasksAPI()" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600 mr-2">
                    Test New Tasks API
                </button>
                <button onclick="testIntegratedAPI()" class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600">
                    Test Integrated API
                </button>
                
                <div id="apiResults" class="mt-6 p-4 bg-gray-50 rounded"></div>
            </div>
        </div>

        <!-- File Check -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">📁 File System Check</h2>
            <div class="grid grid-cols-2 gap-4">
                <?php
                $requiredFiles = [
                    'includes/db.php',
                    'includes/functions.php',
                    'includes/auth.php',
                    'api/users.php',
                    'api/tasks.php'
                ];
                
                foreach ($requiredFiles as $file) {
                    $exists = file_exists($file);
                    $readable = $exists && is_readable($file);
                    
                    echo '<div class="flex items-center space-x-2">';
                    echo '<span class="' . ($readable ? 'text-green-600' : 'text-red-600') . '">';
                    echo ($readable ? '✅' : '❌');
                    echo '</span>';
                    echo '<span>' . $file . '</span>';
                    if ($exists && !$readable) {
                        echo '<span class="text-yellow-600">(not readable)</span>';
                    } elseif (!$exists) {
                        echo '<span class="text-red-600">(missing)</span>';
                    }
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>

    <script>
        async function testUsersAPI() {
            const resultsDiv = document.getElementById('apiResults');
            resultsDiv.innerHTML = '<div class="text-blue-600">Testing Users API...</div>';
            
            try {
                const response = await fetch('api/users.php?action=get_active_users', {
                    credentials: 'same-origin'
                });
                
                const responseText = await response.text();
                displayAPIResult('Users API', response, responseText);
                
            } catch (error) {
                resultsDiv.innerHTML = `<div class="text-red-600">❌ Users API Error: ${error.message}</div>`;
            }
        }
        
        async function testTasksAPI() {
            const resultsDiv = document.getElementById('apiResults');
            resultsDiv.innerHTML = '<div class="text-blue-600">Testing Tasks API...</div>';
            
            try {
                const response = await fetch('api/tasks.php?action=get_tasks', {
                    credentials: 'same-origin'
                });
                
                const responseText = await response.text();
                displayAPIResult('Tasks API', response, responseText);
                
            } catch (error) {
                resultsDiv.innerHTML = `<div class="text-red-600">❌ Tasks API Error: ${error.message}</div>`;
            }
        }
        
        async function testNewTasksAPI() {
            const resultsDiv = document.getElementById('apiResults');
            resultsDiv.innerHTML = '<div class="text-blue-600">Testing New Tasks API (Fixed Version)...</div>';
            
            try {
                // First check if new API file exists
                const response = await fetch('api/tasks-new.php?action=get_tasks', {
                    credentials: 'same-origin'
                });
                
                const responseText = await response.text();
                displayAPIResult('New Tasks API', response, responseText);
                
            } catch (error) {
                resultsDiv.innerHTML = `<div class="text-red-600">❌ New Tasks API Error: ${error.message}</div>`;
            }
        }
        
        async function testIntegratedAPI() {
            const resultsDiv = document.getElementById('apiResults');
            resultsDiv.innerHTML = '<div class="text-blue-600">Testing Integrated API...</div>';
            
            try {
                const response = await fetch('?test_api=1', {
                    credentials: 'same-origin'
                });
                
                const responseText = await response.text();
                displayAPIResult('Integrated API', response, responseText);
                
            } catch (error) {
                resultsDiv.innerHTML = `<div class="text-red-600">❌ Integrated API Error: ${error.message}</div>`;
            }
        }
        
        function displayAPIResult(apiName, response, responseText) {
            const resultsDiv = document.getElementById('apiResults');
            const isJSON = !responseText.trim().startsWith('<');
            
            let parsedData = null;
            if (isJSON) {
                try {
                    parsedData = JSON.parse(responseText);
                } catch (e) {
                    // Ignore parse errors for display
                }
            }
            
            resultsDiv.innerHTML = `
                <div class="border rounded p-4">
                    <div class="font-semibold mb-2">${apiName} Response:</div>
                    <div class="grid grid-cols-2 gap-4 mb-3">
                        <div><strong>Status:</strong> ${response.status} ${response.statusText}</div>
                        <div><strong>Content-Type:</strong> ${response.headers.get('content-type') || 'unknown'}</div>
                    </div>
                    
                    <div class="mb-3">
                        ${isJSON 
                            ? '<div class="text-green-600">✅ Valid JSON Response</div>' 
                            : '<div class="text-red-600">❌ HTML Response (Error)</div>'
                        }
                    </div>
                    
                    ${parsedData ? `
                        <div class="mb-3">
                            <div class="font-semibold">Parsed Response:</div>
                            <div class="mt-1">
                                <strong>Success:</strong> ${parsedData.success ? '✅' : '❌'}<br>
                                <strong>Message:</strong> ${parsedData.message || 'None'}<br>
                                ${parsedData.users ? `<strong>Users Count:</strong> ${parsedData.users.length}<br>` : ''}
                                ${parsedData.tasks ? `<strong>Tasks Count:</strong> ${parsedData.tasks.length}<br>` : ''}
                                ${parsedData.count !== undefined ? `<strong>Count:</strong> ${parsedData.count}<br>` : ''}
                            </div>
                        </div>
                    ` : ''}
                    
                    <details class="mt-3">
                        <summary class="cursor-pointer text-blue-600 font-semibold">View Raw Response</summary>
                        <pre class="mt-2 p-3 bg-gray-100 rounded text-xs overflow-auto max-h-60">${responseText}</pre>
                    </details>
                </div>
            `;
        }
        
        async function createTestSession() {
            try {
                const response = await fetch('login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'email=admin@example.com&password=admin123'
                });
                
                if (response.ok) {
                    alert('Test session created! Reloading page...');
                    location.reload();
                } else {
                    alert('Failed to create test session. Try logging in manually.');
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
        
        // Auto-test APIs on page load
        document.addEventListener('DOMContentLoaded', function() {
            const resultsDiv = document.getElementById('apiResults');
            resultsDiv.innerHTML = '<div class="text-gray-600">Click a button above to test APIs</div>';
        });
    </script>
</body>
</html>