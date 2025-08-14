<?php
// Detailed debug script to find the exact cause of the 500 error
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h1>Detailed Debug Analysis</h1>";
echo "<hr>";

// Step 1: Check PHP version and extensions
echo "<h2>1. PHP Environment Check</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "PDO Available: " . (extension_loaded('pdo') ? '✅ Yes' : '❌ No') . "<br>";
echo "PDO MySQL Available: " . (extension_loaded('pdo_mysql') ? '✅ Yes' : '❌ No') . "<br>";
echo "Session Support: " . (function_exists('session_start') ? '✅ Yes' : '❌ No') . "<br>";
echo "<hr>";

// Step 2: Check file existence
echo "<h2>2. File Existence Check</h2>";
$files = [
    'includes/db.php',
    'includes/auth.php', 
    'includes/functions.php',
    'api/users.php'
];

foreach ($files as $file) {
    $exists = file_exists($file);
    echo "{$file}: " . ($exists ? '✅ Exists' : '❌ Missing') . "<br>";
    if ($exists) {
        echo "&nbsp;&nbsp;Size: " . filesize($file) . " bytes<br>";
        echo "&nbsp;&nbsp;Readable: " . (is_readable($file) ? '✅ Yes' : '❌ No') . "<br>";
    }
}
echo "<hr>";

// Step 3: Test database connection step by step
echo "<h2>3. Database Connection Test</h2>";
try {
    echo "Attempting to include db.php...<br>";
    require_once 'includes/db.php';
    echo "✅ db.php included successfully<br>";
    
    if (isset($pdo)) {
        echo "✅ PDO variable exists<br>";
        
        if ($pdo instanceof PDO) {
            echo "✅ PDO is valid object<br>";
            
            // Test simple query
            $stmt = $pdo->query("SELECT 1 as test");
            $result = $stmt->fetch();
            echo "✅ Database query test: " . $result['test'] . "<br>";
            
        } else {
            echo "❌ PDO is not a valid object<br>";
        }
    } else {
        echo "❌ PDO variable not set<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
    echo "❌ Error file: " . $e->getFile() . " line " . $e->getLine() . "<br>";
}
echo "<hr>";

// Step 4: Test auth.php
echo "<h2>4. Auth Functions Test</h2>";
try {
    echo "Attempting to include auth.php...<br>";
    require_once 'includes/auth.php';
    echo "✅ auth.php included successfully<br>";
    
    if (function_exists('isAdmin')) {
        echo "✅ isAdmin function exists<br>";
    } else {
        echo "❌ isAdmin function missing<br>";
    }
    
    if (function_exists('isLoggedIn')) {
        echo "✅ isLoggedIn function exists<br>";
    } else {
        echo "❌ isLoggedIn function missing<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Auth error: " . $e->getMessage() . "<br>";
}
echo "<hr>";

// Step 5: Test functions.php
echo "<h2>5. Functions.php Test</h2>";
try {
    echo "Attempting to include functions.php...<br>";
    require_once 'includes/functions.php';
    echo "✅ functions.php included successfully<br>";
    
    if (function_exists('getAllUsers')) {
        echo "✅ getAllUsers function exists<br>";
    } else {
        echo "❌ getAllUsers function missing<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Functions error: " . $e->getMessage() . "<br>";
    echo "❌ Error file: " . $e->getFile() . " line " . $e->getLine() . "<br>";
}
echo "<hr>";

// Step 6: Check database tables
echo "<h2>6. Database Tables Check</h2>";
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        // Check if users table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        if ($stmt->rowCount() > 0) {
            echo "✅ Users table exists<br>";
            
            // Check table structure
            $stmt = $pdo->query("DESCRIBE users");
            $columns = $stmt->fetchAll();
            echo "Users table columns: ";
            foreach ($columns as $col) {
                echo $col['Field'] . " ";
            }
            echo "<br>";
            
            // Check if users exist
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
            $count = $stmt->fetch()['count'];
            echo "Users in table: {$count}<br>";
            
        } else {
            echo "❌ Users table does not exist<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Table check error: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ Cannot check tables - no database connection<br>";
}
echo "<hr>";

// Step 7: Test session setup
echo "<h2>7. Session Test</h2>";
try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
        echo "✅ Session started<br>";
    } else {
        echo "✅ Session already active<br>";
    }
    
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
    $_SESSION['user_name'] = 'Test Admin';
    
    echo "✅ Session variables set<br>";
    echo "User ID: " . $_SESSION['user_id'] . "<br>";
    echo "Role: " . $_SESSION['role'] . "<br>";
    
} catch (Exception $e) {
    echo "❌ Session error: " . $e->getMessage() . "<br>";
}
echo "<hr>";

// Step 8: Test API directly
echo "<h2>8. Direct API Test</h2>";
try {
    // Simulate the API call
    $_GET['action'] = 'get_active_users';
    
    echo "Attempting to execute users API code...<br>";
    
    // Capture any output
    ob_start();
    
    // Manually include and execute the API logic
    if (isset($pdo) && function_exists('isAdmin')) {
        
        // Simulate the getActiveUsers function call
        if ($_SESSION['role'] !== 'admin') {
            echo "User role check: Non-admin user<br>";
        } else {
            echo "User role check: Admin user<br>";
            
            $stmt = $pdo->prepare("
                SELECT 
                    u.id, 
                    u.name, 
                    u.email, 
                    u.role, 
                    u.department, 
                    u.avatar, 
                    u.last_login,
                    u.created_at
                FROM users u
                WHERE u.is_active = TRUE
                ORDER BY u.role DESC, u.name ASC
                LIMIT 10
            ");
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "Query executed successfully. Found " . count($users) . " users<br>";
            
            $result = [
                'success' => true,
                'users' => $users,
                'count' => count($users)
            ];
            
            echo "JSON Result: " . json_encode($result) . "<br>";
        }
    } else {
        echo "❌ Missing PDO or isAdmin function<br>";
    }
    
    $output = ob_get_clean();
    echo $output;
    
} catch (Exception $e) {
    ob_end_clean();
    echo "❌ API test error: " . $e->getMessage() . "<br>";
    echo "❌ Error file: " . $e->getFile() . " line " . $e->getLine() . "<br>";
}

echo "<hr>";
echo "<h2>Summary</h2>";
echo "If all checks above pass, the issue might be in the API error handling or headers.";
?>