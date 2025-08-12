 
<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

if ($_POST) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (login($email, $password)) {
        $redirect = $_SESSION['role'] === 'admin' ? 'admin-dashboard.php' : 'index.php';
        header("Location: $redirect");
        exit;
    } else {
        $error = "Invalid credentials";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Daily Calendar</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white p-6 rounded-lg shadow-sm w-full max-w-sm">
        <h1 class="text-lg font-bold text-center mb-4">Daily Calendar</h1>
        
        <?php if (isset($error)): ?>
            <div class="bg-red-100 text-red-700 p-2 rounded-lg text-xs mb-4"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST" class="space-y-3">
            <div>
                <label class="text-xs text-gray-600">Email</label>
                <input type="email" name="email" required 
                       class="w-full p-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div>
                <label class="text-xs text-gray-600">Password</label>
                <input type="password" name="password" required 
                       class="w-full p-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <button type="submit" 
                    class="w-full bg-blue-500 text-white p-2 rounded-lg text-sm font-semibold hover:bg-blue-600">
                Login
            </button>
        </form>
        
        <div class="mt-4 text-xs text-gray-500 text-center">
            <p>Demo accounts:</p>
            <p>Admin: admin@example.com / admin123</p>
            <p>User: user@example.com / user123</p>
        </div>
    </div>
</body>
</html>