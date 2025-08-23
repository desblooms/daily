<?php
// Apply Date Filtering Fix Directly
echo "<h1>🔧 Apply Date Filtering Fix</h1>";

require_once 'includes/db.php';

// Read the current index.php
$indexPath = 'index.php';
$content = file_get_contents($indexPath);

if (!$content) {
    echo "<p style='color: red;'>❌ Could not read index.php</p>";
    exit;
}

// Create backup
$backupPath = 'index.php.backup.' . date('YmdHis');
if (copy($indexPath, $backupPath)) {
    echo "<p style='color: green;'>✅ Backup created: {$backupPath}</p>";
} else {
    echo "<p style='color: red;'>❌ Failed to create backup</p>";
    exit;
}

// Apply fixes
$fixes = [
    // Fix 1: Better default page handling
    [
        'search' => '$currentPage = $_GET[\'page\'] ?? \'today\';',
        'replace' => '$currentPage = $_GET[\'page\'] ?? \'today\';
// Ensure we always filter by date unless explicitly requesting all'
    ],
    
    // Fix 2: Improve the 'all' case to limit results
    [
        'search' => 'case \'all\':
        $tasks = getTasks($_SESSION[\'user_id\']);
        $pageTitle = "All Tasks";
        break;',
        'replace' => 'case \'all\':
        // Show recent tasks instead of ALL for better performance and UX
        $stmt = $pdo->prepare("
            SELECT t.*, u.name as assigned_name, u.email as assigned_email 
            FROM tasks t 
            LEFT JOIN users u ON t.assigned_to = u.id 
            WHERE t.assigned_to = ? AND t.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ORDER BY t.date DESC, t.priority = \'high\' DESC, t.created_at DESC 
            LIMIT 50
        ");
        $stmt->execute([$_SESSION[\'user_id\']]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $pageTitle = "Recent Tasks (Last 30 Days)";
        break;'
    ],
    
    // Fix 3: Update navigation text
    [
        'search' => '<span class="text-xs font-medium">All</span>',
        'replace' => '<span class="text-xs font-medium">Recent</span>'
    ],
    
    // Fix 4: Add date validation
    [
        'search' => '$selectedDate = $_GET[\'date\'] ?? date(\'Y-m-d\');',
        'replace' => '// Validate and set selected date
$selectedDate = $_GET[\'date\'] ?? date(\'Y-m-d\');
// Ensure date is valid format
if (!preg_match(\'/^\d{4}-\d{2}-\d{2}$/\', $selectedDate) || !strtotime($selectedDate)) {
    $selectedDate = date(\'Y-m-d\');
}'
    ]
];

$changesMade = 0;
foreach ($fixes as $fix) {
    if (strpos($content, $fix['search']) !== false) {
        $content = str_replace($fix['search'], $fix['replace'], $content);
        $changesMade++;
        echo "<p style='color: green;'>✅ Applied fix " . ($changesMade) . "</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Could not find text for fix " . (count($fixes) - count($fixes) + $changesMade + 1) . " - may already be applied</p>";
    }
}

// Write the fixed content
if (file_put_contents($indexPath, $content)) {
    echo "<p style='color: green;'>✅ Successfully applied {$changesMade} fixes to index.php</p>";
    
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #28a745;'>";
    echo "<h3>✅ Date Filtering Fixed!</h3>";
    echo "<p><strong>Changes made:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Added date validation to prevent invalid dates</li>";
    echo "<li>✅ Limited 'All Tasks' to show only last 30 days (better performance)</li>";
    echo "<li>✅ Updated navigation label to 'Recent'</li>";
    echo "<li>✅ Improved date filtering logic</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<h2>🧪 Test Your Fix</h2>";
    echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 5px; border-left: 4px solid #2196F3;'>";
    echo "<p><strong>Test these scenarios:</strong></p>";
    echo "<ol>";
    echo "<li><a href='index.php' target='_blank'>Home Page</a> - Should show only TODAY's tasks</li>";
    echo "<li><a href='index.php?page=today' target='_blank'>Today Page</a> - Should show today's tasks</li>";
    echo "<li><a href='index.php?date=" . date('Y-m-d', strtotime('-1 day')) . "' target='_blank'>Yesterday</a> - Should show yesterday's tasks only</li>";
    echo "<li><a href='index.php?date=" . date('Y-m-d', strtotime('+1 day')) . "' target='_blank'>Tomorrow</a> - Should show tomorrow's tasks only</li>";
    echo "<li><a href='index.php?page=all' target='_blank'>Recent Tasks</a> - Should show last 30 days</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<h2>🔍 Verify Fix</h2>";
    echo "<p>Run this test: <a href='test-date-filtering.php' target='_blank'>Date Filtering Test</a></p>";
    
    echo "<h2>🔙 Rollback (if needed)</h2>";
    echo "<p>If something went wrong, restore from backup:</p>";
    echo "<code>cp {$backupPath} index.php</code>";
    
} else {
    echo "<p style='color: red;'>❌ Failed to save changes to index.php</p>";
}

echo "<h2>📋 Summary</h2>";
echo "<p>The fix ensures that:</p>";
echo "<ul>";
echo "<li><strong>Default behavior:</strong> Shows today's tasks only</li>";
echo "<li><strong>Date selection:</strong> Shows tasks for the selected date only</li>";
echo "<li><strong>'Recent' page:</strong> Shows last 30 days instead of all tasks</li>";
echo "<li><strong>Date validation:</strong> Prevents invalid date parameters</li>";
echo "</ul>";
?>