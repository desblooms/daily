<?php
// Quick database check script
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Check</h1>";

try {
    require_once 'includes/db.php';
    
    if (!$pdo) {
        die("❌ Database connection failed");
    }
    
    echo "✅ Database connected<br><br>";
    
    // Check if users table exists
    echo "<h2>Checking Tables:</h2>";
    
    $tables = ['users', 'tasks', 'notifications'];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
            if ($stmt->rowCount() > 0) {
                echo "✅ Table '{$table}' exists<br>";
                
                // Check record count
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM {$table}");
                $count = $stmt->fetch()['count'];
                echo "&nbsp;&nbsp;Records: {$count}<br>";
                
                if ($table === 'users' && $count === 0) {
                    echo "&nbsp;&nbsp;⚠️ No users found - need to create users<br>";
                }
                
            } else {
                echo "❌ Table '{$table}' missing<br>";
            }
        } catch (Exception $e) {
            echo "❌ Error checking table '{$table}': " . $e->getMessage() . "<br>";
        }
    }
    
    // If users table is missing, create it
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() === 0) {
        echo "<br><h2>Creating Users Table:</h2>";
        
        $createUsersSQL = "
        CREATE TABLE users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(150) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin','user') DEFAULT 'user',
            avatar VARCHAR(255) NULL,
            phone VARCHAR(20) NULL,
            department VARCHAR(100) NULL,
            is_active BOOLEAN DEFAULT TRUE,
            last_login TIMESTAMP NULL,
            failed_attempts INT DEFAULT 0,
            locked_until TIMESTAMP NULL,
            force_password_change BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_email (email),
            INDEX idx_role (role),
            INDEX idx_active (is_active)
        )";
        
        try {
            $pdo->exec($createUsersSQL);
            echo "✅ Users table created successfully<br>";
            
            // Insert sample users
            $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
            
            $insertSQL = "
            INSERT INTO users (name, email, password, role, department) VALUES 
            ('System Administrator', 'admin@example.com', ?, 'admin', 'IT'),
            ('John Doe', 'user@example.com', ?, 'user', 'Development'),
            ('Jane Smith', 'jane@example.com', ?, 'user', 'Design'),
            ('Mike Johnson', 'mike@example.com', ?, 'user', 'Marketing')
            ";
            
            $stmt = $pdo->prepare($insertSQL);
            $stmt->execute([$passwordHash, $passwordHash, $passwordHash, $passwordHash]);
            
            echo "✅ Sample users inserted successfully<br>";
            echo "Password for all users: admin123<br>";
            
        } catch (Exception $e) {
            echo "❌ Error creating users table: " . $e->getMessage() . "<br>";
        }
    }
    
    // Show current users
    echo "<br><h2>Current Users:</h2>";
    try {
        $stmt = $pdo->query("SELECT id, name, email, role, department, is_active FROM users ORDER BY id");
        $users = $stmt->fetchAll();
        
        if (empty($users)) {
            echo "❌ No users found<br>";
        } else {
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th>Active</th></tr>";
            foreach ($users as $user) {
                $active = $user['is_active'] ? '✅' : '❌';
                echo "<tr>";
                echo "<td>{$user['id']}</td>";
                echo "<td>{$user['name']}</td>";
                echo "<td>{$user['email']}</td>";
                echo "<td>{$user['role']}</td>";
                echo "<td>{$user['department']}</td>";
                echo "<td>{$active}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } catch (Exception $e) {
        echo "❌ Error fetching users: " . $e->getMessage() . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>