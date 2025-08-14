<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
requireAdmin();

$date = $_GET['date'] ?? null;
$period = $_GET['period'] ?? 'today'; // today, week, month

switch ($period) {
    case 'today':
        $analytics = getAnalytics(date('Y-m-d'));
        break;
        
    case 'week':
        // Get week analytics
        $stmt = $pdo->prepare("
            SELECT 
                DATE(date) as day,
                status,
                COUNT(*) as count
            FROM tasks 
            WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(date), status
            ORDER BY day DESC
        ");
        $stmt->execute();
        $weekData = $stmt->fetchAll();
        
        $analytics = ['week_data' => $weekData];
        break;
        
    case 'month':
        // Get month analytics
        $stmt = $pdo->prepare("
            SELECT 
                WEEK(date) as week,
                status,
                COUNT(*) as count
            FROM tasks 
            WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY WEEK(date), status
            ORDER BY week DESC
        ");
        $stmt->execute();
        $monthData = $stmt->fetchAll();
        
        $analytics = ['month_data' => $monthData];
        break;
        
    default:
        $analytics = getAnalytics();
}

echo json_encode(['success' => true, 'data' => $analytics]);
?>