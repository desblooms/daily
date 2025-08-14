<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
requireLogin();

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

try {
    switch ($action) {
        case 'get_templates':
            getTaskTemplates();
            break;
            
        case 'create_from_template':
            createTaskFromTemplate($input);
            break;
            
        case 'save_template':
            saveTaskTemplate($input);
            break;
            
        case 'delete_template':
            deleteTaskTemplate($input);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function getTaskTemplates() {
    global $pdo;
    
    // Admin can see all templates, users see only their own
    $sql = "SELECT * FROM task_templates WHERE is_active = TRUE";
    $params = [];
    
    if ($_SESSION['role'] !== 'admin') {
        $sql .= " AND created_by = ?";
        $params[] = $_SESSION['user_id'];
    }
    
    $sql .= " ORDER BY name";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $templates = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'templates' => $templates
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch templates: ' . $e->getMessage()]);
    }
}

function createTaskFromTemplate($input) {
    global $pdo;
    
    $templateId = $input['template_id'] ?? null;
    $customData = $input['custom_data'] ?? [];
    
    if (!$templateId) {
        echo json_encode(['success' => false, 'message' => 'Template ID required']);
        return;
    }
    
    try {
        // Get template
        $stmt = $pdo->prepare("SELECT * FROM task_templates WHERE id = ? AND is_active = TRUE");
        $stmt->execute([$templateId]);
        $template = $stmt->fetch();
        
        if (!$template) {
            echo json_encode(['success' => false, 'message' => 'Template not found']);
            return;
        }
        
        // Create task from template
        $taskData = [
            'title' => $customData['title'] ?? $template['title_template'],
            'details' => $customData['details'] ?? $template['details_template'],
            'assigned_to' => $customData['assigned_to'] ?? null,
            'date' => $customData['date'] ?? date('Y-m-d'),
            'priority' => $customData['priority'] ?? $template['default_priority'],
            'estimated_hours' => $customData['estimated_hours'] ?? $template['estimated_hours'],
            'created_by' => $_SESSION['user_id'],
            'updated_by' => $_SESSION['user_id']
        ];
        
        if (!$taskData['assigned_to']) {
            echo json_encode(['success' => false, 'message' => 'Assigned user required']);
            return;
        }
        
        $taskId = createTask($taskData);
        
        echo json_encode([
            'success' => true,
            'message' => 'Task created from template successfully',
            'task_id' => $taskId
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to create task from template: ' . $e->getMessage()]);
    }
}
?>