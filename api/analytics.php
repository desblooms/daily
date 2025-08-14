<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
requireLogin();

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'analytics':
            getAnalytics();
            break;
            
        case 'user_stats':
            getUserAnalytics();
            break;
            
        case 'department_stats':
            getDepartmentAnalytics();
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function getAnalytics() {
    global $pdo;
    
    $period = $_GET['period'] ?? 'week';
    $userId = $_GET['user_id'] ?? null;
    
    // Non-admin users can only see their own analytics
    if ($_SESSION['role'] !== 'admin' && $userId && $userId != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        return;
    }
    
    try {
        $dateFilter = '';
        switch ($period) {
            case 'month':
                $dateFilter = "t.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
            case 'year':
                $dateFilter = "t.date >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)";
                break;
            default:
                $dateFilter = "t.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        }
        
        $userFilter = '';
        $params = [];
        if ($userId) {
            $userFilter = "AND t.assigned_to = ?";
            $params[] = $userId;
        } elseif ($_SESSION['role'] !== 'admin') {
            $userFilter = "AND t.assigned_to = ?";
            $params[] = $_SESSION['user_id'];
        }
        
        // Overall stats
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_tasks,
                COUNT(CASE WHEN status = 'Done' THEN 1 END) as completed_tasks,
                COUNT(CASE WHEN status = 'On Progress' THEN 1 END) as active_tasks,
                COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending_tasks,
                COUNT(CASE WHEN status = 'Approved' THEN 1 END) as approved_tasks,
                COUNT(CASE WHEN status = 'On Hold' THEN 1 END) as on_hold_tasks,
                AVG(CASE WHEN status = 'Done' AND actual_hours IS NOT NULL THEN actual_hours END) as avg_completion_time,
                COUNT(CASE WHEN priority = 'high' THEN 1 END) as high_priority_tasks
            FROM tasks t
            WHERE {$dateFilter} {$userFilter}
        ");
        $stmt->execute($params);
        $stats = $stmt->fetch();
        
        // Daily trend
        $stmt = $pdo->prepare("
            SELECT 
                DATE(t.date) as date,
                COUNT(*) as total,
                COUNT(CASE WHEN t.status = 'Done' THEN 1 END) as completed
            FROM tasks t
            WHERE {$dateFilter} {$userFilter}
            GROUP BY DATE(t.date)
            ORDER BY date DESC
            LIMIT 30
        ");
        $stmt->execute($params);
        $trend = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'analytics' => [
                'stats' => $stats,
                'trend' => $trend,
                'period' => $period
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to get analytics: ' . $e->getMessage()]);
    }
}
?>