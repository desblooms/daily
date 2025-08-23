<?php
/**
 * Production Validation Script
 * Run this script to validate that the system is ready for production deployment
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Daily Calendar - Production Validation</h1>\n";
echo "<pre>\n";

$validationResults = [];
$overallStatus = true;

// Test 1: Database Connection
echo "1. Testing Database Connection...\n";
try {
    require_once 'includes/db.php';
    if ($pdo && $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS)) {
        echo "   ✅ Database connection successful\n";
        $validationResults['database'] = true;
    } else {
        echo "   ❌ Database connection failed\n";
        $validationResults['database'] = false;
        $overallStatus = false;
    }
} catch (Exception $e) {
    echo "   ❌ Database connection error: " . $e->getMessage() . "\n";
    $validationResults['database'] = false;
    $overallStatus = false;
}

// Test 2: Core Functions
echo "\n2. Testing Core Functions...\n";
try {
    require_once 'includes/functions.php';
    require_once 'includes/auth.php';
    
    if (function_exists('getTasks')) {
        echo "   ✅ getTasks function available\n";
    } else {
        echo "   ❌ getTasks function missing\n";
        $overallStatus = false;
    }
    
    if (function_exists('getEnhancedAnalytics')) {
        echo "   ✅ getEnhancedAnalytics function available\n";
    } else {
        echo "   ❌ getEnhancedAnalytics function missing\n";
        $overallStatus = false;
    }
    
    if (function_exists('getAllUsers')) {
        echo "   ✅ getAllUsers function available\n";
    } else {
        echo "   ❌ getAllUsers function missing\n";
        $overallStatus = false;
    }
    
    $validationResults['functions'] = true;
} catch (Exception $e) {
    echo "   ❌ Error loading functions: " . $e->getMessage() . "\n";
    $validationResults['functions'] = false;
    $overallStatus = false;
}

// Test 3: Database Tables
echo "\n3. Testing Database Tables...\n";
try {
    // Check core tables
    $requiredTables = ['users', 'tasks', 'notifications', 'activity_logs'];
    $existingTables = [];
    
    $result = $pdo->query("SHOW TABLES");
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $existingTables[] = $row[0];
    }
    
    $missingTables = array_diff($requiredTables, $existingTables);
    
    if (empty($missingTables)) {
        echo "   ✅ All core tables present\n";
        $validationResults['tables'] = true;
    } else {
        echo "   ❌ Missing tables: " . implode(', ', $missingTables) . "\n";
        $validationResults['tables'] = false;
        $overallStatus = false;
    }
    
    // Check enhanced tables
    $enhancedTables = ['task_attachments', 'task_work_outputs', 'task_progress_updates'];
    $existingEnhanced = array_intersect($enhancedTables, $existingTables);
    
    if (count($existingEnhanced) > 0) {
        echo "   ✅ Enhanced tables available: " . implode(', ', $existingEnhanced) . "\n";
    } else {
        echo "   ⚠️  No enhanced tables found (run task_enhancements.sql)\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Error checking tables: " . $e->getMessage() . "\n";
    $validationResults['tables'] = false;
    $overallStatus = false;
}

// Test 4: File Permissions
echo "\n4. Testing File Permissions...\n";
$directories = ['uploads', 'uploads/campaigns', 'uploads/tasks', 'uploads/attachments'];
$directoriesOk = true;

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "   ✅ Created directory: $dir\n";
        } else {
            echo "   ❌ Failed to create directory: $dir\n";
            $directoriesOk = false;
            $overallStatus = false;
        }
    } else {
        if (is_writable($dir)) {
            echo "   ✅ Directory writable: $dir\n";
        } else {
            echo "   ❌ Directory not writable: $dir\n";
            $directoriesOk = false;
            $overallStatus = false;
        }
    }
}

$validationResults['directories'] = $directoriesOk;

// Test 5: Core Files
echo "\n5. Testing Core Files...\n";
$coreFiles = [
    'index.php',
    'login.php', 
    'enhanced-admin-dashboard.php',
    'enhanced-user-dashboard.php',
    'api/tasks.php',
    'api/users.php',
    'api/attachments.php',
    'assets/js/enhanced-task-manager.js'
];

$filesOk = true;
foreach ($coreFiles as $file) {
    if (file_exists($file)) {
        echo "   ✅ Core file exists: $file\n";
    } else {
        echo "   ❌ Missing core file: $file\n";
        $filesOk = false;
        $overallStatus = false;
    }
}

$validationResults['files'] = $filesOk;

// Test 6: API Endpoints (basic syntax check)
echo "\n6. Testing API Syntax...\n";
$apiFiles = ['api/tasks.php', 'api/users.php', 'api/attachments.php'];
$apiOk = true;

foreach ($apiFiles as $apiFile) {
    if (file_exists($apiFile)) {
        $content = file_get_contents($apiFile);
        if (strpos($content, '<?php') === 0) {
            echo "   ✅ API file syntax OK: $apiFile\n";
        } else {
            echo "   ❌ API file syntax issue: $apiFile\n";
            $apiOk = false;
            $overallStatus = false;
        }
    }
}

$validationResults['api'] = $apiOk;

// Test 7: Configuration
echo "\n7. Testing Configuration...\n";
if (file_exists('config.php')) {
    echo "   ✅ Production configuration file exists\n";
    $validationResults['config'] = true;
} else {
    echo "   ❌ Production configuration file missing\n";
    $validationResults['config'] = false;
    $overallStatus = false;
}

// Test 8: Security Check
echo "\n8. Security Validation...\n";
$securityOk = true;

// Check for debug files
$debugFiles = glob('debug*.php');
$testFiles = glob('test*.php');
$testHtml = glob('test*.html');

if (empty($debugFiles) && empty($testFiles) && empty($testHtml)) {
    echo "   ✅ No debug/test files found\n";
} else {
    echo "   ❌ Debug/test files still present:\n";
    foreach (array_merge($debugFiles, $testFiles, $testHtml) as $file) {
        echo "       - $file\n";
    }
    $securityOk = false;
    $overallStatus = false;
}

$validationResults['security'] = $securityOk;

// Summary
echo "\n" . str_repeat("=", 50) . "\n";
echo "VALIDATION SUMMARY\n";
echo str_repeat("=", 50) . "\n";

foreach ($validationResults as $test => $result) {
    $status = $result ? "✅ PASS" : "❌ FAIL";
    echo sprintf("%-20s: %s\n", ucfirst($test), $status);
}

echo "\nOVERALL STATUS: " . ($overallStatus ? "✅ READY FOR PRODUCTION" : "❌ NEEDS ATTENTION") . "\n";

if (!$overallStatus) {
    echo "\nRECOMMENDATIONS:\n";
    echo "1. Fix all failing tests above\n";
    echo "2. Run database migrations if tables are missing\n";
    echo "3. Set proper file permissions on directories\n";  
    echo "4. Remove any remaining debug/test files\n";
    echo "5. Re-run this validation script\n";
} else {
    echo "\nSYSTEM READY! You can proceed with production deployment.\n";
    echo "Remember to:\n";
    echo "1. Run database optimizations (production_optimizations.sql)\n";
    echo "2. Set up regular backups\n";
    echo "3. Configure monitoring and alerts\n";
    echo "4. Test all functionality on production server\n";
}

echo "\n</pre>\n";
?>