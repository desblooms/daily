<?php
// Test task creation functionality
require_once 'includes/db.php';

// Set up test session
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['user_name'] = 'Test Admin';

echo "<h1>Task Creation Test</h1>";

try {
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }
    
    echo "✅ Database connected<br><br>";
    
    // Check if tasks table exists and its structure
    echo "<h2>Tasks Table Structure:</h2>";
    $stmt = $pdo->query("DESCRIBE tasks");
    $columns = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "</tr>";
    }
    echo "</table><br>";
    
    // Check if activity_logs table exists
    echo "<h2>Activity Logs Table Check:</h2>";
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'activity_logs'");
        if ($stmt->rowCount() > 0) {
            echo "✅ activity_logs table exists<br>";
            
            $stmt = $pdo->query("DESCRIBE activity_logs");
            $columns = $stmt->fetchAll();
            
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>Field</th><th>Type</th></tr>";
            foreach ($columns as $column) {
                echo "<tr><td>{$column['Field']}</td><td>{$column['Type']}</td></tr>";
            }
            echo "</table><br>";
            
        } else {
            echo "❌ activity_logs table missing<br>";
            
            // Create activity_logs table
            echo "Creating activity_logs table...<br>";
            $pdo->exec("
                CREATE TABLE activity_logs (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    action VARCHAR(100) NOT NULL,
                    target_type VARCHAR(50) NULL,
                    target_id INT NULL,
                    details JSON NULL,
                    ip_address VARCHAR(45) NULL,
                    user_agent TEXT NULL,
                    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
                    
                    INDEX idx_user_id (user_id),
                    INDEX idx_action (action),
                    INDEX idx_timestamp (timestamp),
                    INDEX idx_target (target_type, target_id)
                )
            ");
            echo "✅ activity_logs table created<br>";
        }
    } catch (Exception $e) {
        echo "❌ Error with activity_logs: " . $e->getMessage() . "<br>";
    }
    
    // Test manual task creation
    echo "<h2>Manual Task Creation Test:</h2>";
    
    $testTask = [
        'title' => 'Test Task - ' . date('Y-m-d H:i:s'),
        'details' => 'This is a test task created for debugging',
        'assigned_to' => 1,
        'date' => date('Y-m-d'),
        'priority' => 'medium',
        'estimated_hours' => 2.5
    ];
    
    try {
        $pdo->beginTransaction();
        
        $sql = "
            INSERT INTO tasks (title, details, assigned_to, date, created_by, updated_by, priority, estimated_hours, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
        ";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $testTask['title'],
            $testTask['details'],
            $testTask['assigned_to'],
            $testTask['date'],
            $_SESSION['user_id'],
            $_SESSION['user_id'],
            $testTask['priority'],
            $testTask['estimated_hours']
        ]);
        
        if ($result) {
            $taskId = $pdo->lastInsertId();
            echo "✅ Task created successfully with ID: {$taskId}<br>";
            
            // Try to log activity
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO activity_logs (user_id, action, target_type, target_id, details)
                    VALUES (?, 'task_created', 'task', ?, ?)
                ");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $taskId,
                    json_encode(['title' => $testTask['title']])
                ]);
                echo "✅ Activity logged successfully<br>";
            } catch (Exception $e) {
                echo "⚠️ Activity logging failed: " . $e->getMessage() . "<br>";
            }
            
            $pdo->commit();
            echo "✅ Transaction committed successfully<br>";
            
        } else {
            throw new Exception("Failed to insert task");
        }
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo "❌ Task creation failed: " . $e->getMessage() . "<br>";
    }
    
    // Test API call simulation
    echo "<h2>API Call Simulation:</h2>";
    
    $_POST['action'] = 'create_task';
    $testInput = [
        'action' => 'create_task',
        'title' => 'API Test Task - ' . date('Y-m-d H:i:s'),
        'details' => 'This is a test task via API',
        'assigned_to' => '1',
        'date' => date('Y-m-d'),
        'priority' => 'high'
    ];
    
    // Simulate the API function call
    ob_start();
    
    // Inline the createNewTask logic for testing
    if ($_SESSION['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Only administrators can create tasks']);
    } else {
        $required = ['title', 'assigned_to', 'date'];
        $missingFields = [];
        
        foreach ($required as $field) {
            if (empty($testInput[$field])) {
                $missingFields[] = $field;
            }
        }
        
        if (!empty($missingFields)) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields: ' . implode(', ', $missingFields)]);
        } else {
            echo json_encode(['success' => true, 'message' => 'API simulation successful', 'data' => $testInput]);
        }
    }
    
    $apiOutput = ob_get_clean();
    echo "API Response: <pre>" . htmlspecialchars($apiOutput) . "</pre>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>