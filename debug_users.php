<?php
// Debug script to check users in database
require_once 'includes/db.php';

try {
    echo "<h2>Database Connection Test</h2>";
    if ($pdo) {
        echo "✅ Database connected successfully<br><br>";
        
        echo "<h3>All Users in Database:</h3>";
        $stmt = $pdo->prepare("SELECT id, name, email, role, is_active, created_at FROM users ORDER BY id");
        $stmt->execute();
        $users = $stmt->fetchAll();
        
        if (empty($users)) {
            echo "❌ No users found in database<br>";
        } else {
            echo "✅ Found " . count($users) . " users:<br>";
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
            echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Active</th><th>Created</th></tr>";
            foreach ($users as $user) {
                $activeStatus = $user['is_active'] ? '✅ Yes' : '❌ No';
                echo "<tr>";
                echo "<td>{$user['id']}</td>";
                echo "<td>{$user['name']}</td>";
                echo "<td>{$user['email']}</td>";
                echo "<td>{$user['role']}</td>";
                echo "<td>{$activeStatus}</td>";
                echo "<td>{$user['created_at']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        echo "<h3>Active Users Only:</h3>";
        $stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE is_active = TRUE ORDER BY name");
        $stmt->execute();
        $activeUsers = $stmt->fetchAll();
        
        if (empty($activeUsers)) {
            echo "❌ No active users found<br>";
        } else {
            echo "✅ Found " . count($activeUsers) . " active users:<br>";
            echo "<ul>";
            foreach ($activeUsers as $user) {
                echo "<li>ID: {$user['id']} - {$user['name']} ({$user['email']}) - Role: {$user['role']}</li>";
            }
            echo "</ul>";
        }
        
        echo "<h3>Table Structure Check:</h3>";
        $stmt = $pdo->prepare("DESCRIBE users");
        $stmt->execute();
        $structure = $stmt->fetchAll();
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($structure as $column) {
            echo "<tr>";
            echo "<td>{$column['Field']}</td>";
            echo "<td>{$column['Type']}</td>";
            echo "<td>{$column['Null']}</td>";
            echo "<td>{$column['Key']}</td>";
            echo "<td>{$column['Default']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } else {
        echo "❌ Database connection failed<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>