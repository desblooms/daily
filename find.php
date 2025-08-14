<?php
// diagnostic.php - API Health Check Script
// Place this file in the root directory of your project

// Start session and set error reporting
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClickUp Lite Clone - API Diagnostics</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">🔍 API Diagnostics</h1>
        
        <!-- Session Information -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">📊 Session Information</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <strong>Session Status:</strong> 
                    <span class="<?php echo session_status() === PHP_SESSION_ACTIVE ? 'text-green-600' : 'text-red-600'; ?>">
                        <?php 
                        switch(session_status()) {
                            case PHP_SESSION_DISABLED: echo 'DISABLED'; break;
                            case PHP_SESSION_NONE: echo 'NONE'; break;
                            case PHP_SESSION_ACTIVE: echo 'ACTIVE'; break;
                        }
                        ?>
                    </span>
                </div>
                <div>
                    <strong>Session ID:</strong> <?php echo session_id() ?: 'None'; ?>
                </div>
                <div>
                    <strong>User ID:</strong> 
                    <span class="<?php echo isset($_SESSION['user_id']) ? 'text-green-600' : 'text-red-600'; ?>">
                        <?php echo $_SESSION['user_id'] ?? 'Not Set'; ?>
                    </span>
                </div>
                <div>
                    <strong>User Role:</strong> 
                    <span class="<?php echo isset($_SESSION['role']) ? 'text-green-600' : 'text-red-600'; ?>">
                        <?php echo $_SESSION['role'] ?? 'Not Set'; ?>
                    </span>
                </div>
            </div>
            
            <?php if (!empty($_SESSION)): ?>
            <details class="mt-4">
                <summary class="cursor-pointer text-blue-600 hover:text-blue-800">View Full Session Data</summary>
                <pre class="mt-2 p-3 bg-gray-100 rounded text-sm overflow-auto"><?php print_r($_SESSION); ?></pre>
            </details>
            <?php endif; ?>
        </div>

        <!-- Database Connection Test -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">🗄️ Database Connection</h2>
            <?php
            try {
                require_once 'includes/db.php';
                if (isset($pdo) && $pdo instanceof PDO) {
                    echo '<div class="text-green-600">✅ Database connection successful</div>';
                    
                    // Test a simple query
                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE is_active = TRUE");
                    $result = $stmt->fetch();
                    echo '<div class="mt-2">👥 Active users in database: ' . $result['count'] . '</div>';
                } else {
                    echo '<div class="text-red-600">❌ Database connection failed - $pdo not available</div>';
                }
            } catch (Exception $e) {
                echo '<div class="text-red-600">❌ Database error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            ?>
        </div>

        <!-- File System Check -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">📁 File System Check</h2>
            <div class="space-y-2">
                <?php
                $requiredFiles = [
                    'includes/db.php',
                    'includes/auth.php', 
                    'includes/functions.php',
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

        <!-- API Endpoint Tests -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">🔌 API Endpoint Tests</h2>
            <div id="apiTests" class="space-y-4">
                <button onclick="testUsersAPI()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    Test Users API
                </button>
                <button onclick="testTasksAPI()" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                    Test Tasks API
                </button>
                <div id="apiResults" class="mt-4 space-y-2"></div>
            </div>
        </div>

        <!-- Quick Fixes -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">🔧 Quick Fixes</h2>
            <div class="space-y-3">
                <?php if (!isset($_SESSION['user_id'])): ?>
                <div class="p-3 bg-yellow-100 border border-yellow-400 rounded">
                    <strong>⚠️ No User Session:</strong> 
                    <a href="login.php" class="text-blue-600 hover:underline">Login here</a> 
                    or create a test session:
                    <button onclick="createTestSession()" class="ml-2 bg-yellow-500 text-white px-2 py-1 rounded text-sm">
                        Create Test Session
                    </button>
                </div>
                <?php endif; ?>
                
                <div class="p-3 bg-blue-100 border border-blue-400 rounded">
                    <strong>💡 Tips:</strong>
                    <ul class="list-disc ml-5 mt-2">
                        <li>Check PHP error logs for detailed error messages</li>
                        <li>Ensure all database tables are created properly</li>
                        <li>Verify file permissions for includes/ and api/ directories</li>
                        <li>Make sure session cookies are enabled in browser</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        async function testUsersAPI() {
            const resultsDiv = document.getElementById('apiResults');
            resultsDiv.innerHTML = '<div class="text-blue-600">Testing Users API...</div>';
            
            try {
                const response = await fetch('api/users.php?action=get_active_users', {
                    credentials: 'same-origin',
                    headers: {
                        'Cache-Control': 'no-cache'
                    }
                });
                
                const responseText = await response.text();
                
                resultsDiv.innerHTML = `
                    <div class="border rounded p-3">
                        <div class="font-semibold mb-2">Users API Response:</div>
                        <div><strong>Status:</strong> ${response.status} ${response.statusText}</div>
                        <div><strong>Content-Type:</strong> ${response.headers.get('content-type') || 'unknown'}</div>
                        <details class="mt-2">
                            <summary class="cursor-pointer text-blue-600">View Raw Response</summary>
                            <pre class="mt-2 p-2 bg-gray-100 rounded text-xs overflow-auto max-h-40">${responseText}</pre>
                        </details>
                        ${responseText.trim().startsWith('<') ? 
                            '<div class="text-red-600 mt-2">❌ API returned HTML instead of JSON</div>' :
                            '<div class="text-green-600 mt-2">✅ API returned valid response</div>'
                        }
                    </div>
                `;
                
                if (!responseText.trim().startsWith('<')) {
                    try {
                        const data = JSON.parse(responseText);
                        resultsDiv.innerHTML += `
                            <div class="border rounded p-3 mt-2">
                                <div class="font-semibold">Parsed JSON:</div>
                                <pre class="mt-2 p-2 bg-gray-100 rounded text-xs overflow-auto max-h-40">${JSON.stringify(data, null, 2)}</pre>
                            </div>
                        `;
                    } catch (e) {
                        resultsDiv.innerHTML += `<div class="text-red-600 mt-2">❌ JSON Parse Error: ${e.message}</div>`;
                    }
                }
                
            } catch (error) {
                resultsDiv.innerHTML = `<div class="text-red-600">❌ Network Error: ${error.message}</div>`;
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
                
                resultsDiv.innerHTML = `
                    <div class="border rounded p-3">
                        <div class="font-semibold mb-2">Tasks API Response:</div>
                        <div><strong>Status:</strong> ${response.status} ${response.statusText}</div>
                        <div><strong>Content-Type:</strong> ${response.headers.get('content-type') || 'unknown'}</div>
                        <details class="mt-2">
                            <summary class="cursor-pointer text-blue-600">View Raw Response</summary>
                            <pre class="mt-2 p-2 bg-gray-100 rounded text-xs overflow-auto max-h-40">${responseText}</pre>
                        </details>
                        ${responseText.trim().startsWith('<') ? 
                            '<div class="text-red-600 mt-2">❌ API returned HTML instead of JSON</div>' :
                            '<div class="text-green-600 mt-2">✅ API returned valid response</div>'
                        }
                    </div>
                `;
                
            } catch (error) {
                resultsDiv.innerHTML = `<div class="text-red-600">❌ Network Error: ${error.message}</div>`;
            }
        }
        
        async function createTestSession() {
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=create_test_session'
                });
                
                if (response.ok) {
                    location.reload();
                } else {
                    alert('Failed to create test session');
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
    </script>
</body>
</html>

<?php
// Handle AJAX requests
if ($_POST['action'] ?? '' === 'create_test_session') {
    // Create a test session for debugging
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
    $_SESSION['name'] = 'Test Admin';
    $_SESSION['email'] = 'admin@example.com';
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Test session created']);
    exit;
}
?>