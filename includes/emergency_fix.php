<?php
// SUPER SIMPLE PATCH - Save as admin_patch.php
// Just get basic task counts to make dashboard work

function getSimpleAnalytics($date = null) {
    global $pdo;
    
    $targetDate = $date ? $date : date('Y-m-d');
    
    // Get task counts for today
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM tasks WHERE date = ? GROUP BY status");
    $stmt->execute([$targetDate]);
    $results = $stmt->fetchAll();
    
    $analytics = [
        'Pending' => 0,
        'On Progress' => 0, 
        'Done' => 0,
        'Approved' => 0,
        'On Hold' => 0,
        'total' => 0,
        'overdue' => 0,
        'high_priority' => 0,
        'avg_completion_time' => 0
    ];
    
    foreach ($results as $row) {
        if (isset($analytics[$row['status']])) {
            $analytics[$row['status']] = (int)$row['count'];
            $analytics['total'] += (int)$row['count'];
        }
    }
    
    return $analytics;
}
?>