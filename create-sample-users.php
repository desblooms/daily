<?php
// Script to create sample users if they don't exist
require_once 'includes/db.php';

try {
    echo "<h2>Creating Sample Users</h2>";
    
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }
    
    // Check if users already exist
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE is_active = TRUE");
    $stmt->execute();
    $userCount = $stmt->fetch()['count'];
    
    echo "<p>Current active users in database: {$userCount}</p>";
    
    if ($userCount > 0) {
        echo "<p>✅ Users already exist in database. Let's check them:</p>";
        
        $stmt = $pdo->prepare("SELECT id, name, email, role, department FROM users WHERE is_active = TRUE ORDER BY id");
        $stmt->execute();
        $users = $stmt->fetchAll();
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Department</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['name']}</td>";
            echo "<td>{$user['email']}</td>";
            echo "<td>{$user['role']}</td>";
            echo "<td>{$user['department']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } else {
        echo "<p>⚠️ No users found. Creating sample users...</p>";
        
        // Password hash for 'admin123'
        $passwordHash = '$2y$10$VLXVfbJz/z.RR4L6dWNy5.YJY.1qI2Qp8Zq7Vr4V/DhA8b3FrKHjG';
        
        $users = [
            ['System Administrator', 'admin@example.com', $passwordHash, 'admin', 'IT'],
            ['John Doe', 'user@example.com', $passwordHash, 'user', 'Development'],
            ['Jane Smith', 'jane@example.com', $passwordHash, 'user', 'Design'],
            ['Mike Johnson', 'mike@example.com', $passwordHash, 'user', 'Marketing']
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password, role, department) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($users as $user) {
            try {
                $stmt->execute($user);
                echo "✅ Created user: {$user[0]} ({$user[1]})<br>";
            } catch (Exception $e) {
                echo "❌ Failed to create user {$user[0]}: " . $e->getMessage() . "<br>";
            }
        }
        
        echo "<p>✅ Sample users created successfully!</p>";
        echo "<p><strong>Login credentials for all users: Password = admin123</strong></p>";
    }
    
    echo "<h3>Testing Users API</h3>";
    
    // Test the users API directly
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
    $_SESSION['user_name'] = 'System Administrator';
    
    echo "<p>Session set up for testing...</p>";
    
    // Simulate API call
    $_GET['action'] = 'get_active_users';
    
    ob_start();
    include 'api/users.php';
    $apiResponse = ob_get_clean();
    
    echo "<strong>API Response:</strong><br>";
    echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>";
    echo htmlspecialchars($apiResponse);
    echo "</pre>";
    
    $decoded = json_decode($apiResponse, true);
    if ($decoded && $decoded['success']) {
        echo "<p>✅ API is working! Found {$decoded['count']} users.</p>";
    } else {
        echo "<p>❌ API test failed.</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>