<?php
// Script to fix user passwords if they have invalid hashes
require_once 'includes/db.php';

try {
    echo "<h2>Fixing User Passwords</h2>";
    
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }
    
    // Generate a new password hash for 'admin123'
    $newPassword = 'admin123';
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    echo "<p>Updating all user passwords to: <strong>{$newPassword}</strong></p>";
    echo "<p>New hash: {$newHash}</p>";
    
    // Update all users with the new password hash
    $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE is_active = TRUE");
    $result = $stmt->execute([$newHash]);
    
    if ($result) {
        $rowsAffected = $stmt->rowCount();
        echo "<p>✅ Updated passwords for {$rowsAffected} users</p>";
        
        // Show all users
        $stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE is_active = TRUE ORDER BY id");
        $stmt->execute();
        $users = $stmt->fetchAll();
        
        echo "<h3>Active Users:</h3>";
        echo "<ul>";
        foreach ($users as $user) {
            echo "<li>ID: {$user['id']} - {$user['name']} ({$user['email']}) - Role: {$user['role']}</li>";
        }
        echo "</ul>";
        
        echo "<p><strong>All users can now login with password: {$newPassword}</strong></p>";
        
    } else {
        echo "<p>❌ Failed to update passwords</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>