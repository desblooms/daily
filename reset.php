<?php
// Password Reset Script - reset_passwords.php
// Run this to fix password issues

require_once 'includes/db.php';

echo "<h2>Resetting User Passwords</h2>";

try {
    // Check current users in database
    $stmt = $pdo->query("SELECT id, name, email, role FROM users ORDER BY id");
    $users = $stmt->fetchAll();
    
    echo "<h3>Current Users:</h3>";
    foreach ($users as $user) {
        echo "ID: {$user['id']} - {$user['name']} ({$user['email']}) - Role: {$user['role']}<br>";
    }
    
    echo "<br><h3>Updating Passwords:</h3>";
    
    // Reset admin password
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = 'admin@example.com'");
    if ($stmt->execute([$adminPassword])) {
        echo "✓ Admin password updated (admin@example.com / admin123)<br>";
    }
    
    // Reset user password
    $userPassword = password_hash('user123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = 'user@example.com'");
    if ($stmt->execute([$userPassword])) {
        echo "✓ User password updated (user@example.com / user123)<br>";
    }
    
    // Reset Jane's password
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = 'jane@example.com'");
    if ($stmt->execute([$userPassword])) {
        echo "✓ Jane's password updated (jane@example.com / user123)<br>";
    }
    
    // Reset Mike's password
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = 'mike@example.com'");
    if ($stmt->execute([$userPassword])) {
        echo "✓ Mike's password updated (mike@example.com / user123)<br>";
    }
    
    // Verify the hashes
    echo "<br><h3>Verification:</h3>";
    $stmt = $pdo->prepare("SELECT email, password FROM users WHERE email IN ('admin@example.com', 'user@example.com')");
    $stmt->execute();
    $verifyUsers = $stmt->fetchAll();
    
    foreach ($verifyUsers as $user) {
        $testPassword = ($user['email'] === 'admin@example.com') ? 'admin123' : 'user123';
        if (password_verify($testPassword, $user['password'])) {
            echo "✓ {$user['email']} password verification PASSED<br>";
        } else {
            echo "❌ {$user['email']} password verification FAILED<br>";
        }
    }
    
    echo "<br><strong style='color: green;'>✅ Password reset completed!</strong><br>";
    echo "<br>Try logging in now with:<br>";
    echo "<strong>Admin:</strong> admin@example.com / admin123<br>";
    echo "<strong>User:</strong> user@example.com / user123<br>";
    echo "<br><a href='login.php'>Go to Login Page</a>";
    
} catch (Exception $e) {
    echo "<br><strong style='color: red;'>❌ Error resetting passwords:</strong><br>";
    echo $e->getMessage();
}
?>

<!-- Delete this file after running it -->