<?php
/**
 * Daily Calendar Enhanced Task Management System
 * Auto-Installation & Deployment Setup Script
 * 
 * This script will automatically:
 * 1. Check system requirements
 * 2. Set up database schema
 * 3. Create required directories
 * 4. Configure permissions
 * 5. Create admin account
 * 6. Initialize the system
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Security: Only allow installation once
if (file_exists('installation_complete.lock')) {
    die('<h1>Installation Already Complete</h1><p>The system has already been installed. Delete "installation_complete.lock" file to reinstall.</p>');
}

// Installation configuration
$config = [
    'db_host' => 'localhost',
    'db_name' => 'u345095192_tasks',
    'db_user' => 'u345095192_tasks',
    'db_pass' => 'Tasks@788',
    'admin_email' => 'resgrowqatar@gmail.com',
    'admin_name' => 'System Administrator',
    'admin_password' => 'Admin123!@#', // Change this!
    'site_url' => 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI'])
];

$step = $_GET['step'] ?? 1;
$errors = [];
$success = [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Calendar - Installation Wizard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .step-active { background: #4F46E5; color: white; }
        .step-completed { background: #10B981; color: white; }
        .step-pending { background: #E5E7EB; color: #6B7280; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="gradient-bg py-12">
        <div class="max-w-4xl mx-auto px-4">
            <div class="text-center text-white">
                <h1 class="text-4xl font-bold mb-4">Daily Calendar Installation</h1>
                <p class="text-xl opacity-90">Enhanced Task Management System Setup</p>
            </div>
            
            <!-- Progress Steps -->
            <div class="flex justify-center mt-8 space-x-4">
                <?php 
                $steps = [
                    1 => 'System Check',
                    2 => 'Database Setup', 
                    3 => 'File System',
                    4 => 'Admin Account',
                    5 => 'Complete'
                ];
                
                foreach ($steps as $num => $name): 
                    $class = 'step-pending';
                    if ($num < $step) $class = 'step-completed';
                    if ($num == $step) $class = 'step-active';
                ?>
                <div class="flex flex-col items-center">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center text-sm font-bold <?= $class ?>">
                        <?= $num < $step ? '<i class="fas fa-check"></i>' : $num ?>
                    </div>
                    <div class="text-white text-sm mt-2"><?= $name ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="max-w-4xl mx-auto px-4 -mt-8">
        <div class="bg-white rounded-lg shadow-xl p-8">
            <?php
            
            switch ($step) {
                case 1:
                    performSystemCheck();
                    break;
                case 2:
                    setupDatabase();
                    break;
                case 3:
                    setupFileSystem();
                    break;
                case 4:
                    createAdminAccount();
                    break;
                case 5:
                    completeInstallation();
                    break;
                default:
                    performSystemCheck();
            }
            
            function performSystemCheck() {
                global $errors, $success, $step;
                
                echo '<h2 class="text-3xl font-bold mb-6 text-gray-800">Step 1: System Requirements Check</h2>';
                
                // PHP Version Check
                $phpVersion = phpversion();
                if (version_compare($phpVersion, '7.4', '>=')) {
                    $success[] = "PHP Version: $phpVersion ✓";
                } else {
                    $errors[] = "PHP 7.4+ required. Current: $phpVersion";
                }
                
                // Extensions Check
                $requiredExtensions = ['pdo', 'pdo_mysql', 'gd', 'json', 'mbstring', 'session'];
                foreach ($requiredExtensions as $ext) {
                    if (extension_loaded($ext)) {
                        $success[] = "PHP Extension '$ext' ✓";
                    } else {
                        $errors[] = "Required PHP extension '$ext' not found";
                    }
                }
                
                // File Permissions
                $directories = ['.', 'includes', 'api', 'assets'];
                foreach ($directories as $dir) {
                    if (is_readable($dir) && is_writable($dir)) {
                        $success[] = "Directory '$dir' permissions ✓";
                    } else {
                        $errors[] = "Directory '$dir' needs read/write permissions";
                    }
                }
                
                // Display Results
                if (!empty($success)) {
                    echo '<div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">';
                    echo '<h3 class="text-green-800 font-semibold mb-2">System Requirements Met:</h3>';
                    echo '<ul class="text-green-700 space-y-1">';
                    foreach ($success as $msg) {
                        echo "<li><i class='fas fa-check text-green-500 mr-2'></i>$msg</li>";
                    }
                    echo '</ul></div>';
                }
                
                if (!empty($errors)) {
                    echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">';
                    echo '<h3 class="text-red-800 font-semibold mb-2">Issues Found:</h3>';
                    echo '<ul class="text-red-700 space-y-1">';
                    foreach ($errors as $msg) {
                        echo "<li><i class='fas fa-times text-red-500 mr-2'></i>$msg</li>";
                    }
                    echo '</ul></div>';
                    
                    echo '<p class="text-red-600 mb-4">Please fix the issues above before continuing.</p>';
                    echo '<button onclick="location.reload()" class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700">Recheck</button>';
                } else {
                    echo '<div class="text-center">';
                    echo '<p class="text-green-600 text-lg mb-4">All system requirements met! Ready to proceed.</p>';
                    echo '<a href="?step=2" class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 text-lg">Continue to Database Setup</a>';
                    echo '</div>';
                }
            }
            
            function setupDatabase() {
                global $config, $errors, $success;
                
                echo '<h2 class="text-3xl font-bold mb-6 text-gray-800">Step 2: Database Setup</h2>';
                
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    // Update config with form data
                    $config['db_host'] = $_POST['db_host'] ?? $config['db_host'];
                    $config['db_name'] = $_POST['db_name'] ?? $config['db_name'];
                    $config['db_user'] = $_POST['db_user'] ?? $config['db_user'];
                    $config['db_pass'] = $_POST['db_pass'] ?? $config['db_pass'];
                    
                    try {
                        // Test database connection
                        $pdo = new PDO(
                            "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
                            $config['db_user'],
                            $config['db_pass'],
                            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                        );
                        
                        $success[] = "Database connection successful";
                        
                        // Create database schema directly
                        $success[] = "Connected to database: {$config['db_name']}";
                        
                        // Create core tables
                        createCoreSchema($pdo);
                        $success[] = "Created core database tables";
                        
                        // Run additional SQL files if they exist
                        $sqlFiles = [
                            'database/task_enhancements.sql',
                            'database/campaign_schema.sql',
                            'database/campaign_media_schema.sql'
                        ];
                        
                        foreach ($sqlFiles as $sqlFile) {
                            if (file_exists($sqlFile)) {
                                try {
                                    $sql = file_get_contents($sqlFile);
                                    // Replace database name in SQL
                                    $sql = str_replace('u345095192_tasks', $config['db_name'], $sql);
                                    $sql = str_replace('CREATE DATABASE', '-- CREATE DATABASE', $sql);
                                    $sql = str_replace('USE u345095192_tasks', '-- USE database', $sql);
                                    $sql = str_replace('USE ' . $config['db_name'], '-- USE database', $sql);
                                    
                                    // Split by semicolon and execute each statement
                                    $statements = array_filter(array_map('trim', explode(';', $sql)));
                                    
                                    foreach ($statements as $statement) {
                                        if (!empty($statement) && !preg_match('/^(--|\/\*)/', $statement)) {
                                            try {
                                                $pdo->exec($statement);
                                            } catch (PDOException $e) {
                                                // Some statements might fail if tables already exist - that's OK
                                                if (!strpos($e->getMessage(), 'already exists') && !strpos($e->getMessage(), 'Duplicate')) {
                                                    error_log("SQL Error in $sqlFile: " . $e->getMessage());
                                                }
                                            }
                                        }
                                    }
                                    
                                    $success[] = "Executed $sqlFile";
                                } catch (Exception $e) {
                                    error_log("Error processing $sqlFile: " . $e->getMessage());
                                }
                            }
                        }
                        
                        // Create production optimizations
                        createProductionIndexes($pdo);
                        $success[] = "Applied database optimizations";
                        
                        // Create database config file
                        $dbConfigContent = '<?php
// Database Configuration - Auto-generated by installer
class Database {
    private $host = "' . $config['db_host'] . '";
    private $dbname = "' . $config['db_name'] . '";
    private $username = "' . $config['db_user'] . '";
    private $password = "' . $config['db_pass'] . '";
    private $conn;

    public function connect() {
        if ($this->conn === null) {
            try {
                ini_set("display_errors", 0);
                error_reporting(0);
                
                $this->conn = new PDO(
                    "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4",
                    $this->username,
                    $this->password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
                
                $this->conn->query("SELECT 1");
                
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                throw new Exception("Database connection failed. Please check your database configuration.");
            }
        }
        return $this->conn;
    }
}

$database = new Database();
$pdo = $database->connect();
?>';
                        
                        file_put_contents('includes/db.php', $dbConfigContent);
                        $success[] = "Database configuration file created";
                        
                        if (empty($errors)) {
                            echo '<div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">';
                            echo '<h3 class="text-green-800 font-semibold mb-2">Database Setup Complete:</h3>';
                            echo '<ul class="text-green-700 space-y-1">';
                            foreach ($success as $msg) {
                                echo "<li><i class='fas fa-check text-green-500 mr-2'></i>$msg</li>";
                            }
                            echo '</ul></div>';
                            
                            echo '<div class="text-center">';
                            echo '<a href="?step=3" class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 text-lg">Continue to File System Setup</a>';
                            echo '</div>';
                        }
                        
                    } catch (Exception $e) {
                        $errors[] = "Database connection failed: " . $e->getMessage();
                    }
                }
                
                if (!empty($errors)) {
                    echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">';
                    echo '<ul class="text-red-700 space-y-1">';
                    foreach ($errors as $msg) {
                        echo "<li><i class='fas fa-times text-red-500 mr-2'></i>$msg</li>";
                    }
                    echo '</ul></div>';
                }
                
                if (empty($success) || !empty($errors)) {
                    echo '<form method="POST" class="space-y-4">';
                    echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';
                    
                    echo '<div>';
                    echo '<label class="block text-sm font-medium text-gray-700 mb-2">Database Host</label>';
                    echo '<input type="text" name="db_host" value="' . htmlspecialchars($config['db_host']) . '" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">';
                    echo '</div>';
                    
                    echo '<div>';
                    echo '<label class="block text-sm font-medium text-gray-700 mb-2">Database Name</label>';
                    echo '<input type="text" name="db_name" value="' . htmlspecialchars($config['db_name']) . '" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">';
                    echo '</div>';
                    
                    echo '<div>';
                    echo '<label class="block text-sm font-medium text-gray-700 mb-2">Database Username</label>';
                    echo '<input type="text" name="db_user" value="' . htmlspecialchars($config['db_user']) . '" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">';
                    echo '</div>';
                    
                    echo '<div>';
                    echo '<label class="block text-sm font-medium text-gray-700 mb-2">Database Password</label>';
                    echo '<input type="password" name="db_pass" value="' . htmlspecialchars($config['db_pass']) . '" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">';
                    echo '</div>';
                    
                    echo '</div>';
                    echo '<div class="text-center mt-6">';
                    echo '<button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 text-lg">Setup Database</button>';
                    echo '</div>';
                    echo '</form>';
                }
            }
            
            function setupFileSystem() {
                global $errors, $success;
                
                echo '<h2 class="text-3xl font-bold mb-6 text-gray-800">Step 3: File System Setup</h2>';
                
                // Create required directories
                $directories = [
                    'uploads' => 0755,
                    'uploads/tasks' => 0755,
                    'uploads/attachments' => 0755,
                    'uploads/campaigns' => 0755,
                    'uploads/campaigns/images' => 0755,
                    'uploads/campaigns/videos' => 0755,
                    'logs' => 0755
                ];
                
                foreach ($directories as $dir => $permission) {
                    if (!is_dir($dir)) {
                        if (mkdir($dir, $permission, true)) {
                            $success[] = "Created directory: $dir";
                        } else {
                            $errors[] = "Failed to create directory: $dir";
                        }
                    } else {
                        $success[] = "Directory exists: $dir";
                    }
                    
                    // Check if writable
                    if (is_writable($dir)) {
                        $success[] = "Directory writable: $dir";
                    } else {
                        $errors[] = "Directory not writable: $dir";
                    }
                }
                
                // Create index.php files for security
                $protectedDirs = ['uploads', 'uploads/tasks', 'uploads/attachments', 'uploads/campaigns', 'logs'];
                foreach ($protectedDirs as $dir) {
                    $indexFile = "$dir/index.php";
                    if (!file_exists($indexFile)) {
                        file_put_contents($indexFile, '<?php header("HTTP/1.0 403 Forbidden"); exit; ?>');
                        $success[] = "Created security file: $indexFile";
                    }
                }
                
                // Create .htaccess for additional security
                $htaccessContent = 'Options -Indexes
<Files "*.php">
    Order Allow,Deny
    Deny from all
</Files>
<Files "index.php">
    Order Allow,Deny
    Allow from all
</Files>';
                
                foreach ($protectedDirs as $dir) {
                    $htaccessFile = "$dir/.htaccess";
                    if (!file_exists($htaccessFile)) {
                        file_put_contents($htaccessFile, $htaccessContent);
                        $success[] = "Created .htaccess: $htaccessFile";
                    }
                }
                
                // Display results
                if (!empty($success)) {
                    echo '<div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">';
                    echo '<h3 class="text-green-800 font-semibold mb-2">File System Setup:</h3>';
                    echo '<ul class="text-green-700 space-y-1">';
                    foreach ($success as $msg) {
                        echo "<li><i class='fas fa-check text-green-500 mr-2'></i>$msg</li>";
                    }
                    echo '</ul></div>';
                }
                
                if (!empty($errors)) {
                    echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">';
                    echo '<ul class="text-red-700 space-y-1">';
                    foreach ($errors as $msg) {
                        echo "<li><i class='fas fa-times text-red-500 mr-2'></i>$msg</li>";
                    }
                    echo '</ul></div>';
                    
                    echo '<p class="text-red-600 mb-4">Please fix directory permissions manually.</p>';
                }
                
                echo '<div class="text-center">';
                echo '<a href="?step=4" class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 text-lg">Continue to Admin Account</a>';
                echo '</div>';
            }
            
            function createAdminAccount() {
                global $config, $errors, $success;
                
                echo '<h2 class="text-3xl font-bold mb-6 text-gray-800">Step 4: Create Admin Account</h2>';
                
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $config['admin_name'] = $_POST['admin_name'] ?? $config['admin_name'];
                    $config['admin_email'] = $_POST['admin_email'] ?? $config['admin_email'];
                    $config['admin_password'] = $_POST['admin_password'] ?? $config['admin_password'];
                    
                    try {
                        require_once 'includes/db.php';
                        
                        // Check if admin already exists
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR role = 'admin' LIMIT 1");
                        $stmt->execute([$config['admin_email']]);
                        
                        if ($stmt->fetch()) {
                            $errors[] = "Admin user already exists";
                        } else {
                            // Create admin user
                            $hashedPassword = password_hash($config['admin_password'], PASSWORD_DEFAULT);
                            
                            $stmt = $pdo->prepare("
                                INSERT INTO users (name, email, password, role, department, is_active, created_at) 
                                VALUES (?, ?, ?, 'admin', 'Administration', TRUE, NOW())
                            ");
                            
                            if ($stmt->execute([$config['admin_name'], $config['admin_email'], $hashedPassword])) {
                                $success[] = "Admin account created successfully";
                                $success[] = "Email: " . $config['admin_email'];
                                $success[] = "Password: [Hidden for security]";
                                
                                // Create sample task
                                $adminId = $pdo->lastInsertId();
                                $stmt = $pdo->prepare("
                                    INSERT INTO tasks (title, details, assigned_to, date, created_by, updated_by, status, priority) 
                                    VALUES (?, ?, ?, CURDATE(), ?, ?, 'Pending', 'medium')
                                ");
                                $stmt->execute([
                                    'Welcome to Daily Calendar',
                                    'This is a sample task to get you started with the enhanced task management system.',
                                    $adminId,
                                    $adminId,
                                    $adminId
                                ]);
                                
                                $success[] = "Sample task created";
                            } else {
                                $errors[] = "Failed to create admin account";
                            }
                        }
                        
                    } catch (Exception $e) {
                        $errors[] = "Database error: " . $e->getMessage();
                    }
                }
                
                if (!empty($success)) {
                    echo '<div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">';
                    echo '<h3 class="text-green-800 font-semibold mb-2">Admin Account Created:</h3>';
                    echo '<ul class="text-green-700 space-y-1">';
                    foreach ($success as $msg) {
                        echo "<li><i class='fas fa-check text-green-500 mr-2'></i>$msg</li>";
                    }
                    echo '</ul></div>';
                    
                    echo '<div class="text-center">';
                    echo '<a href="?step=5" class="bg-green-600 text-white px-8 py-3 rounded-lg hover:bg-green-700 text-lg">Complete Installation</a>';
                    echo '</div>';
                } else {
                    if (!empty($errors)) {
                        echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">';
                        echo '<ul class="text-red-700 space-y-1">';
                        foreach ($errors as $msg) {
                            echo "<li><i class='fas fa-times text-red-500 mr-2'></i>$msg</li>";
                        }
                        echo '</ul></div>';
                    }
                    
                    echo '<form method="POST" class="space-y-4">';
                    
                    echo '<div>';
                    echo '<label class="block text-sm font-medium text-gray-700 mb-2">Admin Name</label>';
                    echo '<input type="text" name="admin_name" value="' . htmlspecialchars($config['admin_name']) . '" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">';
                    echo '</div>';
                    
                    echo '<div>';
                    echo '<label class="block text-sm font-medium text-gray-700 mb-2">Admin Email</label>';
                    echo '<input type="email" name="admin_email" value="' . htmlspecialchars($config['admin_email']) . '" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">';
                    echo '</div>';
                    
                    echo '<div>';
                    echo '<label class="block text-sm font-medium text-gray-700 mb-2">Admin Password</label>';
                    echo '<input type="password" name="admin_password" value="' . htmlspecialchars($config['admin_password']) . '" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">';
                    echo '<p class="text-sm text-gray-600 mt-1">Minimum 8 characters, include numbers and special characters</p>';
                    echo '</div>';
                    
                    echo '<div class="text-center mt-6">';
                    echo '<button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 text-lg">Create Admin Account</button>';
                    echo '</div>';
                    echo '</form>';
                }
            }
            
            function completeInstallation() {
                echo '<h2 class="text-3xl font-bold mb-6 text-gray-800">Step 5: Installation Complete!</h2>';
                
                // Create installation lock file
                file_put_contents('installation_complete.lock', date('Y-m-d H:i:s') . " - Installation completed\n");
                
                // Remove production validation file for security
                if (file_exists('production_validation.php')) {
                    unlink('production_validation.php');
                }
                
                echo '<div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-6">';
                echo '<div class="text-center">';
                echo '<i class="fas fa-check-circle text-green-500 text-6xl mb-4"></i>';
                echo '<h3 class="text-2xl font-bold text-green-800 mb-4">Installation Successful!</h3>';
                echo '<p class="text-green-700 text-lg">Your Daily Calendar Enhanced Task Management System is ready to use.</p>';
                echo '</div>';
                echo '</div>';
                
                echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">';
                
                // Admin Access
                echo '<div class="bg-blue-50 border border-blue-200 rounded-lg p-6">';
                echo '<h4 class="text-xl font-semibold text-blue-800 mb-4">Admin Access</h4>';
                echo '<ul class="text-blue-700 space-y-2">';
                echo '<li><strong>Admin Dashboard:</strong> <a href="enhanced-admin-dashboard.php" class="text-blue-600 underline">enhanced-admin-dashboard.php</a></li>';
                echo '<li><strong>User Management:</strong> Create and manage users</li>';
                echo '<li><strong>Analytics:</strong> View system analytics and reports</li>';
                echo '<li><strong>Task Management:</strong> Oversee all tasks and projects</li>';
                echo '</ul>';
                echo '</div>';
                
                // User Access
                echo '<div class="bg-purple-50 border border-purple-200 rounded-lg p-6">';
                echo '<h4 class="text-xl font-semibold text-purple-800 mb-4">User Access</h4>';
                echo '<ul class="text-purple-700 space-y-2">';
                echo '<li><strong>User Dashboard:</strong> <a href="enhanced-user-dashboard.php" class="text-purple-600 underline">enhanced-user-dashboard.php</a></li>';
                echo '<li><strong>Task Management:</strong> Create and manage personal tasks</li>';
                echo '<li><strong>File Sharing:</strong> Upload and share work outputs</li>';
                echo '<li><strong>Collaboration:</strong> Work with team members</li>';
                echo '</ul>';
                echo '</div>';
                
                echo '</div>';
                
                // Features Overview
                echo '<div class="bg-gray-50 border border-gray-200 rounded-lg p-6 mb-6">';
                echo '<h4 class="text-xl font-semibold text-gray-800 mb-4">Enhanced Features Installed</h4>';
                echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm text-gray-700">';
                echo '<div><i class="fas fa-tasks mr-2"></i>Enhanced Task Management</div>';
                echo '<div><i class="fas fa-file-upload mr-2"></i>File Attachments</div>';
                echo '<div><i class="fas fa-share-alt mr-2"></i>Work Output Sharing</div>';
                echo '<div><i class="fas fa-chart-line mr-2"></i>Advanced Analytics</div>';
                echo '<div><i class="fas fa-users mr-2"></i>Team Collaboration</div>';
                echo '<div><i class="fas fa-bell mr-2"></i>Notifications</div>';
                echo '<div><i class="fas fa-mobile-alt mr-2"></i>Responsive Design</div>';
                echo '<div><i class="fas fa-shield-alt mr-2"></i>Security Features</div>';
                echo '</div>';
                echo '</div>';
                
                // Important Notes
                echo '<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">';
                echo '<h4 class="text-xl font-semibold text-yellow-800 mb-4">Important Security Notes</h4>';
                echo '<ul class="text-yellow-700 space-y-2">';
                echo '<li><i class="fas fa-exclamation-triangle mr-2"></i><strong>Delete install.php:</strong> Remove this installation file for security</li>';
                echo '<li><i class="fas fa-key mr-2"></i><strong>Change passwords:</strong> Update default admin password</li>';
                echo '<li><i class="fas fa-server mr-2"></i><strong>Set file permissions:</strong> Ensure proper server permissions</li>';
                echo '<li><i class="fas fa-database mr-2"></i><strong>Backup database:</strong> Create regular automated backups</li>';
                echo '</ul>';
                echo '</div>';
                
                // Quick Start Links
                echo '<div class="text-center space-x-4">';
                echo '<a href="login.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 text-lg">Login to System</a>';
                echo '<a href="enhanced-admin-dashboard.php" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 text-lg">Admin Dashboard</a>';
                echo '<a href="enhanced-user-dashboard.php" class="bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 text-lg">User Dashboard</a>';
                echo '</div>';
                
                echo '<div class="text-center mt-8 text-gray-600">';
                echo '<p>Thank you for choosing Daily Calendar Enhanced Task Management System!</p>';
                echo '<p class="text-sm mt-2">Version 2.0.0 - ' . date('Y') . '</p>';
                echo '</div>';
            }
            
            ?>
        </div>
    </div>
</body>
</html>

<?php
// Helper functions for database setup
function createCoreSchema($pdo) {
    // Users table
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

    // Tasks table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tasks (
            id INT PRIMARY KEY AUTO_INCREMENT,
            title VARCHAR(200) NOT NULL,
            details TEXT,
            date DATE NOT NULL,
            assigned_to INT NOT NULL,
            created_by INT NOT NULL,
            approved_by INT NULL,
            updated_by INT NULL,
            status ENUM('Pending','On Progress','Done','Approved','On Hold') DEFAULT 'Pending',
            priority ENUM('low','medium','high') DEFAULT 'medium',
            estimated_hours DECIMAL(4,2) NULL,
            actual_hours DECIMAL(4,2) NULL,
            due_time TIME NULL,
            tags JSON NULL,
            attachments JSON NULL,
            completion_notes TEXT NULL,
            task_category VARCHAR(50) NULL,
            requirements TEXT NULL,
            deliverables TEXT NULL,
            external_links JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE RESTRICT,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
            FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
            
            INDEX idx_assigned_to (assigned_to),
            INDEX idx_date (date),
            INDEX idx_status (status),
            INDEX idx_priority (priority),
            INDEX idx_created_by (created_by)
        )
    ");

    // Notifications table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            message TEXT NOT NULL,
            type ENUM('info','success','warning','error') DEFAULT 'info',
            is_read BOOLEAN DEFAULT FALSE,
            related_type VARCHAR(50) NULL,
            related_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            read_at TIMESTAMP NULL,
            
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_is_read (is_read),
            INDEX idx_created_at (created_at)
        )
    ");

    // Activity logs table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS activity_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NULL,
            action VARCHAR(100) NOT NULL,
            resource_type VARCHAR(50) NULL,
            resource_id INT NULL,
            details TEXT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_action (action),
            INDEX idx_created_at (created_at)
        )
    ");

    // Password reset tokens table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            token VARCHAR(64) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            used BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_token (token),
            INDEX idx_user_id (user_id),
            INDEX idx_expires_at (expires_at)
        )
    ");

    // Login attempts table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS login_attempts (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NULL,
            email VARCHAR(150) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT NULL,
            status ENUM('success','failed','blocked') NOT NULL,
            failure_reason VARCHAR(100) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_email (email),
            INDEX idx_ip_address (ip_address),
            INDEX idx_created_at (created_at)
        )
    ");

    // Password logs table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS password_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            change_type ENUM('self','admin','forced') NOT NULL,
            changed_by INT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_created_at (created_at)
        )
    ");
}

function createProductionIndexes($pdo) {
    try {
        // Additional performance indexes
        $indexes = [
            'CREATE INDEX IF NOT EXISTS idx_tasks_date_status ON tasks(date, status)',
            'CREATE INDEX IF NOT EXISTS idx_tasks_assigned_status ON tasks(assigned_to, status)',
            'CREATE INDEX IF NOT EXISTS idx_notifications_user_unread ON notifications(user_id, is_read)',
            'CREATE INDEX IF NOT EXISTS idx_activity_logs_resource ON activity_logs(resource_type, resource_id)'
        ];
        
        foreach ($indexes as $index) {
            try {
                $pdo->exec($index);
            } catch (PDOException $e) {
                // Index might already exist
                if (!strpos($e->getMessage(), 'Duplicate key name')) {
                    error_log("Index creation error: " . $e->getMessage());
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error creating indexes: " . $e->getMessage());
    }
}
?>