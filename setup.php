<?php
// Database setup script - setup_database.php
// Run this once to create all required tables and sample data

require_once 'includes/db.php';

echo "<h2>Setting up Daily Calendar Database</h2>";

try {
    // Create users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
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
        )
    ");
    echo "✓ Users table created<br>";
    
    // Create tasks table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tasks (
            id INT PRIMARY KEY AUTO_INCREMENT,
            title VARCHAR(200) NOT NULL,
            details TEXT,
            date DATE NOT NULL,
            assigned_to INT NOT NULL,
            created_by INT NOT NULL,
            approved_by INT NULL,
            status ENUM('Pending','On Progress','Done','Approved','On Hold') DEFAULT 'Pending',
            priority ENUM('low','medium','high') DEFAULT 'medium',
            estimated_hours DECIMAL(4,2) NULL,
            actual_hours DECIMAL(4,2) NULL,
            due_time TIME NULL,
            tags JSON NULL,
            attachments JSON NULL,
            completion_notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE RESTRICT,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
            FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
            
            INDEX idx_assigned_to (assigned_to),
            INDEX idx_date (date),
            INDEX idx_status (status),
            INDEX idx_priority (priority),
            INDEX idx_created_by (created_by)
        )
    ");
    echo "✓ Tasks table created<br>";
    
    // Create status_logs table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS status_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            task_id INT NOT NULL,
            status ENUM('Pending','On Progress','Done','Approved','On Hold') NOT NULL,
            previous_status ENUM('Pending','On Progress','Done','Approved','On Hold') NULL,
            updated_by INT NOT NULL,
            comments TEXT NULL,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
            FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE RESTRICT,
            
            INDEX idx_task_id (task_id),
            INDEX idx_timestamp (timestamp)
        )
    ");
    echo "✓ Status logs table created<br>";
    
    // Create activity_logs table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS activity_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            action VARCHAR(100) NOT NULL,
            resource_type VARCHAR(50) NULL,
            resource_id INT NULL,
            details JSON NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
            
            INDEX idx_user_id (user_id),
            INDEX idx_action (action),
            INDEX idx_timestamp (timestamp)
        )
    ");
    echo "✓ Activity logs table created<br>";
    
    // Create password_logs table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS password_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            changed_by INT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            change_type ENUM('self','admin_reset','forced') DEFAULT 'self',
            
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
            
            INDEX idx_user_id (user_id),
            INDEX idx_changed_at (changed_at)
        )
    ");
    echo "✓ Password logs table created<br>";
    
    // Check if admin user already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute(['admin@example.com']);
    
    if ($stmt->fetchColumn() == 0) {
        // Insert default users with proper password hashing
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $userPassword = password_hash('user123', PASSWORD_DEFAULT);
        
        $users = [
            ['System Administrator', 'admin@example.com', $adminPassword, 'admin', 'IT'],
            ['John Doe', 'user@example.com', $userPassword, 'user', 'Development'],
            ['Jane Smith', 'jane@example.com', $userPassword, 'user', 'Design'],
            ['Mike Johnson', 'mike@example.com', $userPassword, 'user', 'Marketing']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, department) VALUES (?, ?, ?, ?, ?)");
        
        foreach ($users as $user) {
            $stmt->execute($user);
        }
        echo "✓ Default users created<br>";
        echo "&nbsp;&nbsp;- Admin: admin@example.com / admin123<br>";
        echo "&nbsp;&nbsp;- User: user@example.com / user123<br>";
    } else {
        echo "✓ Users already exist<br>";
    }
    
    // Insert sample tasks if none exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks");
    if ($stmt->fetchColumn() == 0) {
        $sampleTasks = [
            [
                'title' => 'Design Landing Page Wireframes',
                'details' => 'Create wireframes for the new product landing page',
                'date' => date('Y-m-d'),
                'assigned_to' => 3, // Jane Smith
                'created_by' => 1,
                'status' => 'Pending',
                'priority' => 'high'
            ],
            [
                'title' => 'Implement User Authentication',
                'details' => 'Set up secure user authentication system',
                'date' => date('Y-m-d'),
                'assigned_to' => 2, // John Doe
                'created_by' => 1,
                'status' => 'On Progress',
                'priority' => 'high'
            ],
            [
                'title' => 'Content Strategy Meeting',
                'details' => 'Quarterly content strategy review',
                'date' => date('Y-m-d'),
                'assigned_to' => 4, // Mike Johnson
                'created_by' => 1,
                'status' => 'Pending',
                'priority' => 'medium'
            ]
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO tasks (title, details, date, assigned_to, created_by, status, priority) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($sampleTasks as $task) {
            $stmt->execute([
                $task['title'], $task['details'], $task['date'], 
                $task['assigned_to'], $task['created_by'], $task['status'], $task['priority']
            ]);
        }
        echo "✓ Sample tasks created<br>";
    } else {
        echo "✓ Tasks already exist<br>";
    }
    
    echo "<br><strong style='color: green;'>✅ Database setup completed successfully!</strong><br>";
    echo "<br>You can now login with:<br>";
    echo "Admin: admin@example.com / admin123<br>";
    echo "User: user@example.com / user123<br>";
    echo "<br><a href='login.php'>Go to Login Page</a>";
    
} catch (Exception $e) {
    echo "<br><strong style='color: red;'>❌ Error setting up database:</strong><br>";
    echo $e->getMessage();
}
?>

<!-- Delete this file after running it -->