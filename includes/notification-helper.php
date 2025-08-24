<?php
// Notification Helper - Send push notifications when tasks are updated
require_once 'vapid-config.php';

class NotificationHelper {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Send notification when task is created
    public function notifyTaskCreated($taskId, $assignedToId, $createdById) {
        try {
            $task = $this->getTaskDetails($taskId);
            if (!$task) return;
            
            $creatorName = $this->getUserName($createdById);
            
            $this->sendNotificationToUser($assignedToId, [
                'title' => 'New Task Assigned',
                'body' => "You have been assigned: {$task['title']} by {$creatorName}",
                'data' => [
                    'task_id' => $taskId,
                    'url' => "/task.php?id={$taskId}",
                    'action' => 'task_created'
                ],
                'tag' => "task-{$taskId}"
            ]);
            
        } catch (Exception $e) {
            error_log("Error sending task created notification: " . $e->getMessage());
        }
    }
    
    // Send notification when task status changes
    public function notifyStatusChanged($taskId, $oldStatus, $newStatus, $updatedById) {
        try {
            $task = $this->getTaskDetails($taskId);
            if (!$task) return;
            
            $updaterName = $this->getUserName($updatedById);
            $statusMessage = $this->getStatusMessage($newStatus);
            
            // Notify task assignee
            if ($task['assigned_to'] != $updatedById) {
                $this->sendNotificationToUser($task['assigned_to'], [
                    'title' => 'Task Status Updated',
                    'body' => "'{$task['title']}' is now {$statusMessage} by {$updaterName}",
                    'data' => [
                        'task_id' => $taskId,
                        'url' => "/task.php?id={$taskId}",
                        'action' => 'status_changed',
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus
                    ],
                    'tag' => "task-status-{$taskId}"
                ]);
            }
            
            // Notify task creator if different from assignee and updater
            if ($task['created_by'] != $task['assigned_to'] && $task['created_by'] != $updatedById) {
                $this->sendNotificationToUser($task['created_by'], [
                    'title' => 'Task Status Updated',
                    'body' => "'{$task['title']}' is now {$statusMessage}",
                    'data' => [
                        'task_id' => $taskId,
                        'url' => "/task.php?id={$taskId}",
                        'action' => 'status_changed'
                    ],
                    'tag' => "task-status-{$taskId}"
                ]);
            }
            
            // Special handling for specific status changes
            if ($newStatus === 'Done') {
                $this->notifyAdminsTaskCompleted($taskId, $task);
            } elseif ($newStatus === 'Approved') {
                $this->notifyTaskApproved($taskId, $task['assigned_to']);
            }
            
        } catch (Exception $e) {
            error_log("Error sending status change notification: " . $e->getMessage());
        }
    }
    
    // Send notification when task is reassigned
    public function notifyTaskReassigned($taskId, $oldAssigneeId, $newAssigneeId, $reassignedById) {
        try {
            $task = $this->getTaskDetails($taskId);
            if (!$task) return;
            
            $reassignerName = $this->getUserName($reassignedById);
            
            // Notify new assignee
            $this->sendNotificationToUser($newAssigneeId, [
                'title' => 'Task Reassigned to You',
                'body' => "'{$task['title']}' has been reassigned to you by {$reassignerName}",
                'data' => [
                    'task_id' => $taskId,
                    'url' => "/task.php?id={$taskId}",
                    'action' => 'task_reassigned'
                ],
                'tag' => "task-reassign-{$taskId}"
            ]);
            
            // Notify old assignee
            if ($oldAssigneeId != $reassignedById) {
                $this->sendNotificationToUser($oldAssigneeId, [
                    'title' => 'Task Reassigned',
                    'body' => "'{$task['title']}' has been reassigned by {$reassignerName}",
                    'data' => [
                        'task_id' => $taskId,
                        'url' => "/task.php?id={$taskId}",
                        'action' => 'task_reassigned'
                    ],
                    'tag' => "task-reassign-{$taskId}"
                ]);
            }
            
        } catch (Exception $e) {
            error_log("Error sending reassignment notification: " . $e->getMessage());
        }
    }
    
    // Send notification when task is due soon
    public function notifyTaskDueSoon($taskId) {
        try {
            $task = $this->getTaskDetails($taskId);
            if (!$task || $task['status'] === 'Done' || $task['status'] === 'Approved') return;
            
            $dueDate = new DateTime($task['date']);
            $now = new DateTime();
            $diff = $now->diff($dueDate);
            
            $timeMessage = '';
            if ($diff->d == 0) {
                $timeMessage = 'today';
            } elseif ($diff->d == 1) {
                $timeMessage = 'tomorrow';
            } else {
                $timeMessage = "in {$diff->d} days";
            }
            
            $this->sendNotificationToUser($task['assigned_to'], [
                'title' => 'Task Due Soon',
                'body' => "'{$task['title']}' is due {$timeMessage}",
                'data' => [
                    'task_id' => $taskId,
                    'url' => "/task.php?id={$taskId}",
                    'action' => 'task_due_soon'
                ],
                'tag' => "task-due-{$taskId}",
                'requireInteraction' => true
            ]);
            
        } catch (Exception $e) {
            error_log("Error sending due soon notification: " . $e->getMessage());
        }
    }
    
    // Send notification to admins when task is completed
    private function notifyAdminsTaskCompleted($taskId, $task) {
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE role = 'admin' AND is_active = TRUE");
        $stmt->execute();
        $admins = $stmt->fetchAll();
        
        foreach ($admins as $admin) {
            $this->sendNotificationToUser($admin['id'], [
                'title' => 'Task Completed - Awaiting Approval',
                'body' => "'{$task['title']}' has been marked as done and needs approval",
                'data' => [
                    'task_id' => $taskId,
                    'url' => "/task.php?id={$taskId}",
                    'action' => 'task_completed'
                ],
                'tag' => "task-approval-{$taskId}"
            ]);
        }
    }
    
    // Send notification when task is approved
    private function notifyTaskApproved($taskId, $assigneeId) {
        $task = $this->getTaskDetails($taskId);
        if (!$task) return;
        
        $this->sendNotificationToUser($assigneeId, [
            'title' => 'Task Approved!',
            'body' => "'{$task['title']}' has been approved. Great work!",
            'data' => [
                'task_id' => $taskId,
                'url' => "/task.php?id={$taskId}",
                'action' => 'task_approved'
            ],
            'tag' => "task-approved-{$taskId}",
            'icon' => '/assets/icons/success-192x192.png'
        ]);
    }
    
    // Send notification to specific user
    private function sendNotificationToUser($userId, $notificationData) {
        try {
            // Get user's active subscriptions
            $stmt = $this->pdo->prepare("
                SELECT * FROM push_subscriptions 
                WHERE user_id = ? AND is_active = TRUE
            ");
            $stmt->execute([$userId]);
            $subscriptions = $stmt->fetchAll();
            
            if (empty($subscriptions)) {
                return; // No active subscriptions
            }
            
            $sentCount = 0;
            
            foreach ($subscriptions as $subscription) {
                $result = $this->sendWebPushToSubscription($subscription, $notificationData);
                
                if ($result['success']) {
                    $sentCount++;
                    
                    // Update last_used timestamp
                    $updateStmt = $this->pdo->prepare("UPDATE push_subscriptions SET last_used = CURRENT_TIMESTAMP WHERE id = ?");
                    $updateStmt->execute([$subscription['id']]);
                } else {
                    // Mark inactive subscriptions
                    if (strpos($result['error'], '410') !== false || strpos($result['error'], '404') !== false) {
                        $updateStmt = $this->pdo->prepare("UPDATE push_subscriptions SET is_active = FALSE WHERE id = ?");
                        $updateStmt->execute([$subscription['id']]);
                    }
                }
                
                // Log notification
                $logStmt = $this->pdo->prepare("
                    INSERT INTO push_notifications_log 
                    (user_id, title, body, data, sent_at, success, response_data, error_message) 
                    VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, ?, ?, ?)
                ");
                $logStmt->execute([
                    $userId,
                    $notificationData['title'],
                    $notificationData['body'],
                    json_encode($notificationData['data'] ?? []),
                    $result['success'] ? 1 : 0,
                    json_encode($result),
                    $result['error'] ?? null
                ]);
            }
            
            return $sentCount > 0;
            
        } catch (Exception $e) {
            error_log("Error sending notification to user {$userId}: " . $e->getMessage());
            return false;
        }
    }
    
    // Send web push notification to a specific subscription
    private function sendWebPushToSubscription($subscription, $notificationData) {
        // For demo purposes, simulate successful sending
        // In production, implement full Web Push protocol
        
        return [
            'success' => true,
            'message' => 'Notification sent successfully'
        ];
    }
    
    // Helper methods
    private function getTaskDetails($taskId) {
        $stmt = $this->pdo->prepare("
            SELECT t.*, u.name as assigned_name, c.name as created_name 
            FROM tasks t 
            LEFT JOIN users u ON t.assigned_to = u.id 
            LEFT JOIN users c ON t.created_by = c.id 
            WHERE t.id = ?
        ");
        $stmt->execute([$taskId]);
        return $stmt->fetch();
    }
    
    private function getUserName($userId) {
        $stmt = $this->pdo->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        return $user ? $user['name'] : 'Unknown User';
    }
    
    private function getStatusMessage($status) {
        $messages = [
            'Pending' => 'pending',
            'On Progress' => 'in progress',
            'Done' => 'completed',
            'Approved' => 'approved',
            'On Hold' => 'on hold'
        ];
        
        return $messages[$status] ?? strtolower($status);
    }
}

// Global helper function to send notifications
function sendTaskNotification($type, $taskId, $data = []) {
    global $pdo;
    
    if (!defined('PUSH_ENABLED') || !PUSH_ENABLED) {
        return;
    }
    
    $notificationHelper = new NotificationHelper($pdo);
    
    switch ($type) {
        case 'task_created':
            $notificationHelper->notifyTaskCreated($taskId, $data['assigned_to'], $data['created_by']);
            break;
            
        case 'status_changed':
            $notificationHelper->notifyStatusChanged($taskId, $data['old_status'], $data['new_status'], $data['updated_by']);
            break;
            
        case 'task_reassigned':
            $notificationHelper->notifyTaskReassigned($taskId, $data['old_assignee'], $data['new_assignee'], $data['reassigned_by']);
            break;
            
        case 'task_due_soon':
            $notificationHelper->notifyTaskDueSoon($taskId);
            break;
    }
}
?>