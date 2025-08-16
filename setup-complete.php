<?php
/**
 * Complete Database Setup & Fix Script
 * Run this to fix all database issues and set up the Daily Calendar app
 */

require_once 'includes/db.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Daily Calendar - Database Setup</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .success { color: #10B981; font-weight: bold; }
        .error { color: #EF4444; font-weight: bold; }
        .info { color: #3B82F6; font-weight: bold; }
        .step { margin: 10px 0; padding: 10px; border-left: 4px solid #ddd; }
        .credentials { background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0; }
    </style>
</head>
<body>";

echo "<h1>Daily Calendar - Database Setup & Fix</h1>";

try {
    // Step 1: Add missing column to tasks table
    echo "<div class='step'>";
    echo "<h3>Step 1: Fixing Tasks Table Structure</h3>";
    
    try {
        // Check if updated_by column exists
        $result = $pdo->query("SHOW COLUMNS FROM tasks LIKE 'updated_by'");
        if ($result->rowCount() == 0) {
            $pdo->exec("ALTER TABLE tasks ADD COLUMN updated_by INT NULL AFTER approved_by");
            $pdo->exec("ALTER TABLE tasks ADD FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL");
            echo "<span class='success'>✓ Added missing 'updated_by' column to tasks table</span><br>";
        } else {
            echo "<span class='info'>✓ Column 'updated_by' already exists</span><br>";
        }
        
        // Update existing tasks to have updated_by set
        $pdo->exec("UPDATE tasks SET updated_by = created_by WHERE updated_by IS NULL");
        echo "<span class='success'>✓ Updated existing tasks with updated_by values</span><br>";
        
    } catch (Exception $e) {
        echo "<span class='error'>❌ Error fixing tasks table: " . $e->getMessage() . "</span><br>";
    }
    echo "</div>";

    // Step 2: Drop and recreate triggers
    echo "<div class='step'>";
    echo "<h3>Step 2: Fixing Database Triggers</h3>";
    
    try {
        // Drop existing triggers if they exist
        $pdo->exec("DROP TRIGGER IF EXISTS task_status_change_trigger");
        $pdo->exec("DROP TRIGGER IF EXISTS task_assignment_trigger");
        $pdo->exec("DROP TRIGGER IF EXISTS new_task_trigger");
        echo "<span class='info'>✓ Dropped existing triggers</span><br>";
        
        // Create new triggers with correct syntax
        $pdo->exec("
            CREATE TRIGGER task_status_change_trigger
            AFTER UPDATE ON tasks
            FOR EACH ROW
            BEGIN
                IF OLD.status != NEW.status THEN
                    INSERT INTO status_logs (task_id, status, previous_status, updated_by, timestamp)
                    VALUES (NEW.id, NEW.status, OLD.status, NEW.updated_by, NOW());
                    
                    IF NEW.assigned_to != COALESCE(NEW.updated_by, NEW.assigned_to) THEN
                        INSERT INTO notifications (user_id, title, message, type, related_type, related_id)
                        VALUES (
                            NEW.assigned_to,
                            CONCAT('Task Status Updated: ', NEW.title),
                            CONCAT('Your task status has been changed to ', NEW.status),
                            'info',
                            'task',
                            NEW.id
                        );
                    END IF;
                END IF;
            END
        ");
        echo "<span class='success'>✓ Created task_status_change_trigger</span><br>";
        
        $pdo->exec("
            CREATE TRIGGER task_assignment_trigger
            AFTER UPDATE ON tasks
            FOR EACH ROW
            BEGIN
                IF OLD.assigned_to != NEW.assigned_to THEN
                    INSERT INTO notifications (user_id, title, message, type, related_type, related_id)
                    VALUES (
                        NEW.assigned_to,
                        CONCAT('New Task Assigned: ', NEW.title),
                        CONCAT('You have been assigned a new task for ', NEW.date),
                        'info',
                        'task',
                        NEW.id
                    );
                END IF;
            END
        ");
        echo "<span class='success'>✓ Created task_assignment_trigger</span><br>";
        
        $pdo->exec("
            CREATE TRIGGER new_task_trigger
            AFTER INSERT ON tasks
            FOR EACH ROW
            BEGIN
                INSERT INTO status_logs (task_id, status, updated_by, timestamp)
                VALUES (NEW.id, NEW.status, NEW.created_by, NOW());
                
                IF NEW.assigned_to != NEW.created_by THEN
                    INSERT INTO notifications (user_id, title, message, type, related_type, related_id)
                    VALUES (
                        NEW.assigned_to,
                        CONCAT('New Task Assigned: ', NEW.title),
                        CONCAT('You have been assigned a new task for ', NEW.date),
                        'info',
                        'task',
                        NEW.id
                    );
                END IF;
            END
        ");
        echo "<span class='success'>✓ Created new_task_trigger</span><br>";
        
    } catch (Exception $e) {
        echo "<span class='error'>❌ Error creating triggers: " . $e->getMessage() . "</span><br>";
    }
    echo "</div>";

    // Step 3: Verify and fix user passwords
    echo "<div class='step'>";
    echo "<h3>Step 3: Fixing User Passwords</h3>";
    
    try {
        // Check if users exist
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $userCount = $stmt->fetchColumn();
        
        if ($userCount == 0) {
            // Create default users
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
            echo "<span class='success'>✓ Created default users</span><br>";
        } else {
            // Reset existing passwords
            $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $userPassword = password_hash('user123', PASSWORD_DEFAULT);
            
            $pdo->prepare("UPDATE users SET password = ? WHERE email = 'admin@example.com'")->execute([$adminPassword]);
            $pdo->prepare("UPDATE users SET password = ? WHERE email = 'user@example.com'")->execute([$userPassword]);
            $pdo->prepare("UPDATE users SET password = ? WHERE email = 'jane@example.com'")->execute([$userPassword]);
            $pdo->prepare("UPDATE users SET password = ? WHERE email = 'mike@example.com'")->execute([$userPassword]);
            
            echo "<span class='success'>✓ Reset all user passwords</span><br>";
        }
        
        // Verify passwords work
        $stmt = $pdo->prepare("SELECT email, password FROM users WHERE email IN ('admin@example.com', 'user@example.com')");
        $stmt->execute();
        $verifyUsers = $stmt->fetchAll();
        
        foreach ($verifyUsers as $user) {
            $testPassword = ($user['email'] === 'admin@example.com') ? 'admin123' : 'user123';
            if (password_verify($testPassword, $user['password'])) {
                echo "<span class='success'>✓ {$user['email']} password verification PASSED</span><br>";
            } else {
                echo "<span class='error'>❌ {$user['email']} password verification FAILED</span><br>";
            }
        }
        
    } catch (Exception $e) {
        echo "<span class='error'>❌ Error fixing passwords: " . $e->getMessage() . "</span><br>";
    }
    echo "</div>";

    // Step 4: Create sample tasks with proper updated_by
    echo "<div class='step'>";
    echo "<h3>Step 4: Creating Sample Tasks</h3>";
    
    try {
        // Check if tasks exist
        $stmt = $pdo->query("SELECT COUNT(*) FROM tasks");
        $taskCount = $stmt->fetchColumn();
        
        if ($taskCount == 0) {
            $sampleTasks = [
                [
                    'title' => 'Design Landing Page Wireframes',
                    'details' => 'Create wireframes for the new product landing page including mobile and desktop versions',
                    'date' => date('Y-m-d'),
                    'assigned_to' => 3, // Jane Smith
                    'created_by' => 1,
                    'updated_by' => 1,
                    'status' => 'Pending',
                    'priority' => 'high'
                ],
                [
                    'title' => 'Implement User Authentication',
                    'details' => 'Set up secure user authentication system with JWT tokens and password encryption',
                    'date' => date('Y-m-d'),
                    'assigned_to' => 2, // John Doe
                    'created_by' => 1,
                    'updated_by' => 1,
                    'status' => 'On Progress',
                    'priority' => 'high'
                ],
                [
                    'title' => 'Content Strategy Meeting',
                    'details' => 'Quarterly content strategy review and planning session with marketing team',
                    'date' => date('Y-m-d'),
                    'assigned_to' => 4, // Mike Johnson
                    'created_by' => 1,
                    'updated_by' => 1,
                    'status' => 'Pending',
                    'priority' => 'medium'
                ],
                [
                    'title' => 'Database Optimization',
                    'details' => 'Optimize database queries and add proper indexing for better performance',
                    'date' => date('Y-m-d'),
                    'assigned_to' => 2, // John Doe
                    'created_by' => 1,
                    'updated_by' => 1,
                    'status' => 'Done',
                    'priority' => 'medium'
                ]
            ];
            
            $stmt = $pdo->prepare("
                INSERT INTO tasks (title, details, date, assigned_to, created_by, updated_by, status, priority) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($sampleTasks as $task) {
                $stmt->execute([
                    $task['title'], $task['details'], $task['date'], 
                    $task['assigned_to'], $task['created_by'], $task['updated_by'],
                    $task['status'], $task['priority']
                ]);
            }
            echo "<span class='success'>✓ Created sample tasks</span><br>";
        } else {
            echo "<span class='info'>✓ Tasks already exist</span><br>";
        }
        
    } catch (Exception $e) {
        echo "<span class='error'>❌ Error creating tasks: " . $e->getMessage() . "</span><br>";
    }
    echo "</div>";

    // Step 5: Test database functionality
    echo "<div class='step'>";
    echo "<h3>Step 5: Testing Database Functionality</h3>";
    
    try {
        // Test task query
        $stmt = $pdo->query("
            SELECT t.*, u.name as assigned_name 
            FROM tasks t 
            LEFT JOIN users u ON t.assigned_to = u.id 
            LIMIT 1
        ");
        $testTask = $stmt->fetch();
        
        if ($testTask) {
            echo "<span class='success'>✓ Task queries working correctly</span><br>";
        }
        
        // Test user authentication query
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute(['admin@example.com']);
        $testUser = $stmt->fetch();
        
        if ($testUser && password_verify('admin123', $testUser['password'])) {
            echo "<span class='success'>✓ User authentication working correctly</span><br>";
        }
        
        // Test trigger by updating a task
        if ($testTask) {
            $stmt = $pdo->prepare("UPDATE tasks SET updated_by = ? WHERE id = ?");
            $stmt->execute([1, $testTask['id']]);
            echo "<span class='success'>✓ Database triggers working correctly</span><br>";
        }
        
    } catch (Exception $e) {
        echo "<span class='error'>❌ Error testing functionality: " . $e->getMessage() . "</span><br>";
    }
    echo "</div>";

    // Success message
    echo "<div class='step' style='border-left-color: #10B981; background: #f0fdf4;'>";
    echo "<h3 class='success'>🎉 Database Setup Completed Successfully!</h3>";
    echo "<p>Your Daily Calendar application is now ready to use.</p>";
    echo "</div>";

    // Login credentials
    echo "<div class='credentials'>";
    echo "<h3>🔑 Login Credentials</h3>";
    echo "<strong>Administrator Account:</strong><br>";
    echo "Email: admin@example.com<br>";
    echo "Password: admin123<br><br>";
    
    echo "<strong>User Accounts:</strong><br>";
    echo "Email: user@example.com | Password: user123<br>";
    echo "Email: jane@example.com | Password: user123<br>";
    echo "Email: mike@example.com | Password: user123<br>";
    echo "</div>";

    echo "<div style='text-align: center; margin: 30px 0;'>";
    echo "<a href='login.php' style='background: #3B82F6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold;'>Go to Login Page</a>";
    echo "</div>";

    echo "<div style='background: #fef3c7; padding: 15px; border-radius: 8px; margin: 20px 0;'>";
    echo "<strong>⚠️ Important:</strong> Delete this setup file (setup-complete.php) after running it for security reasons.";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='step' style='border-left-color: #EF4444; background: #fef2f2;'>";
    echo "<h3 class='error'>❌ Database Setup Failed</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database connection settings in includes/db.php</p>";
    echo "</div>";
}

echo "</body></html>";
?>

<!-- 
INSTRUCTIONS:
1. Save this file as 'setup-complete.php' in your project root
2. Run it by visiting: http://yoursite.com/setup-complete.php
3. Delete this file after successful setup
4. Use the login credentials provided to access the application
-->