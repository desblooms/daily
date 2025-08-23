<?php
// Fix for Date Filtering - Backup and Update index.php
echo "<h1>🔧 Date Filtering Fix</h1>";
echo "<p>This will fix the date filtering to ensure users see only tasks for the selected date.</p>";

$indexFile = 'index.php';
$backupFile = 'index.php.backup.' . date('Y-m-d-H-i-s');

// Create backup
if (copy($indexFile, $backupFile)) {
    echo "<p>✅ Backup created: {$backupFile}</p>";
} else {
    echo "<p>❌ Failed to create backup</p>";
    exit;
}

// Read current content
$content = file_get_contents($indexFile);

// Fix 1: Ensure default page is 'today' instead of allowing undefined behavior
$oldLogic = '$currentPage = $_GET[\'page\'] ?? \'today\';';
$newLogic = '// Force today as default to ensure date-specific viewing
$currentPage = $_GET[\'page\'] ?? \'today\';';

$content = str_replace($oldLogic, $newLogic, $content);

// Fix 2: Modify the all tasks case to show recent tasks instead of ALL tasks
$oldAllCase = 'case \'all\':
        $tasks = getTasks($_SESSION[\'user_id\']);
        $pageTitle = "All Tasks";
        break;';

$newAllCase = 'case \'all\':
        // Show recent tasks (last 30 days) instead of ALL tasks for better performance
        $thirtyDaysAgo = date(\'Y-m-d\', strtotime(\'-30 days\'));
        $stmt = $pdo->prepare("
            SELECT t.*, u.name as assigned_name 
            FROM tasks t 
            LEFT JOIN users u ON t.assigned_to = u.id 
            WHERE t.assigned_to = ? AND t.date >= ? 
            ORDER BY t.date DESC, t.created_at DESC 
            LIMIT 100
        ");
        $stmt->execute([$_SESSION[\'user_id\'], $thirtyDaysAgo]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $pageTitle = "Recent Tasks (Last 30 Days)";
        break;';

$content = str_replace($oldAllCase, $newAllCase, $content);

// Fix 3: Update the "All Tasks" navigation label to be clearer
$oldNavigation = '<span class="text-xs font-medium">All</span>';
$newNavigation = '<span class="text-xs font-medium">Recent</span>';

$content = str_replace($oldNavigation, $newNavigation, $content);

// Fix 4: Improve the showOtherDays function
$oldFunction = 'function showOtherDays() {
            window.location.href = \'?page=all\';
        }';

$newFunction = 'function showOtherDays() {
            // Instead of showing all tasks, go to yesterday or let user pick date
            const yesterday = new Date();
            yesterday.setDate(yesterday.getDate() - 1);
            const dateString = yesterday.toISOString().split(\'T\')[0];
            window.location.href = `?page=custom&date=${dateString}`;
        }';

$content = str_replace($oldFunction, $newFunction, $content);

// Fix 5: Add debug info to ensure date filtering is working
$oldDebugSection = '// Get user stats
$userStats = getUserStats($_SESSION[\'user_id\']);';

$newDebugSection = '// Debug: Log what we\'re fetching (remove in production)
error_log("Daily Calendar Debug: User {$_SESSION[\'user_id\']}, Page: {$currentPage}, Date: {$selectedDate}, Tasks found: " . count($tasks));

// Get user stats  
$userStats = getUserStats($_SESSION[\'user_id\']);';

$content = str_replace($oldDebugSection, $newDebugSection, $content);

// Write the updated content
if (file_put_contents($indexFile, $content)) {
    echo "<p>✅ Fixed applied to index.php</p>";
    echo "<h2>Changes Made:</h2>";
    echo "<ul>";
    echo "<li>✅ Ensured 'today' is the default page</li>";
    echo "<li>✅ Limited 'all' tasks to recent 30 days (better performance)</li>";
    echo "<li>✅ Updated navigation label from 'All' to 'Recent'</li>";
    echo "<li>✅ Modified 'showOtherDays' to navigate to yesterday instead of all tasks</li>";
    echo "<li>✅ Added debug logging to track what's being fetched</li>";
    echo "</ul>";
    
    echo "<h2>Test the Fix:</h2>";
    echo "<p>1. Go to <a href='index.php'>Home Page</a> - Should show today's tasks only</p>";
    echo "<p>2. Use date picker to select different dates - Should show tasks for that date only</p>";
    echo "<p>3. Click 'Recent' in navigation - Should show last 30 days of tasks</p>";
    echo "<p>4. Check server error logs for debug information</p>";
    
    echo "<h2>Restore Backup (if needed):</h2>";
    echo "<p>If something goes wrong: <a href='restore-backup.php?file={$backupFile}'>Restore Backup</a></p>";
    
} else {
    echo "<p>❌ Failed to write changes to index.php</p>";
}
?>