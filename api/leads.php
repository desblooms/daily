<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/campaign_functions.php';

// Start session and check authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    switch ($action) {
        case 'create_lead':
            $required = ['campaign_id', 'assigned_to', 'contact_number', 'lead_source'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    throw new Exception("Field '$field' is required");
                }
            }

            // Validate lead data
            $errors = validateLeadData($input);
            if (!empty($errors)) {
                throw new Exception(implode(', ', $errors));
            }

            // Check if user can create leads for this campaign/user
            if (!isAdmin() && $input['assigned_to'] != $_SESSION['user_id']) {
                throw new Exception('You can only create leads for yourself');
            }

            $leadId = createLead($input);
            
            echo json_encode([
                'success' => true,
                'message' => 'Lead created successfully',
                'lead_id' => $leadId
            ]);
            break;

        case 'update_lead':
            $leadId = $input['lead_id'] ?? 0;
            if (!$leadId) {
                throw new Exception('Lead ID is required');
            }

            // Validate lead data
            $errors = validateLeadData($input);
            if (!empty($errors)) {
                throw new Exception(implode(', ', $errors));
            }

            $result = updateLead($leadId, $input);
            
            echo json_encode([
                'success' => true,
                'message' => 'Lead updated successfully'
            ]);
            break;

        case 'get_lead':
            $leadId = $input['lead_id'] ?? 0;
            if (!$leadId) {
                throw new Exception('Lead ID is required');
            }

            $lead = getLeadById($leadId);
            if (!$lead) {
                throw new Exception('Lead not found');
            }

            // Check if user has access to this lead
            if (!isAdmin() && $lead['assigned_to'] != $_SESSION['user_id']) {
                throw new Exception('Access denied to this lead');
            }

            echo json_encode([
                'success' => true,
                'lead' => $lead
            ]);
            break;

        case 'get_user_leads':
            $userId = $input['user_id'] ?? $_SESSION['user_id'];
            $date = $input['date'] ?? date('Y-m-d');
            $campaignId = $input['campaign_id'] ?? null;

            // Check if user can access these leads
            if (!isAdmin() && $userId != $_SESSION['user_id']) {
                throw new Exception('Access denied');
            }

            $leads = getLeads($campaignId, $userId, $date);
            
            echo json_encode([
                'success' => true,
                'leads' => $leads,
                'date' => $date
            ]);
            break;

        case 'admin_approve_lead':
            if (!isAdmin()) {
                throw new Exception('Admin access required');
            }

            $leadId = $input['lead_id'] ?? 0;
            $approved = $input['approved'] ?? false;
            $notes = $input['notes'] ?? null;

            if (!$leadId) {
                throw new Exception('Lead ID is required');
            }

            $result = adminApproveLead($leadId, $approved, $notes);
            
            if ($result) {
                $status = $approved ? 'approved' : 'rejected';
                echo json_encode([
                    'success' => true,
                    'message' => "Lead $status successfully"
                ]);
            } else {
                throw new Exception("Failed to $status lead");
            }
            break;

        case 'bulk_approve_leads':
            if (!isAdmin()) {
                throw new Exception('Admin access required');
            }

            $leadIds = $input['lead_ids'] ?? [];
            $approved = $input['approved'] ?? false;
            $notes = $input['notes'] ?? null;

            if (empty($leadIds)) {
                throw new Exception('Lead IDs are required');
            }

            $successCount = 0;
            $errors = [];

            foreach ($leadIds as $leadId) {
                try {
                    $result = adminApproveLead($leadId, $approved, $notes);
                    if ($result) {
                        $successCount++;
                    }
                } catch (Exception $e) {
                    $errors[] = "Lead $leadId: " . $e->getMessage();
                }
            }

            $status = $approved ? 'approved' : 'rejected';
            $message = "$successCount leads $status successfully";
            if (!empty($errors)) {
                $message .= ". Errors: " . implode(', ', $errors);
            }

            echo json_encode([
                'success' => true,
                'message' => $message,
                'processed' => $successCount,
                'errors' => $errors
            ]);
            break;

        case 'get_lead_activities':
            $leadId = $input['lead_id'] ?? 0;
            if (!$leadId) {
                throw new Exception('Lead ID is required');
            }

            $lead = getLeadById($leadId);
            if (!$lead) {
                throw new Exception('Lead not found');
            }

            // Check if user has access to this lead
            if (!isAdmin() && $lead['assigned_to'] != $_SESSION['user_id']) {
                throw new Exception('Access denied to this lead');
            }

            $stmt = $pdo->prepare("
                SELECT 
                    la.*,
                    u.name as created_by_name
                FROM lead_activities la
                LEFT JOIN users u ON la.created_by = u.id
                WHERE la.lead_id = ?
                ORDER BY la.created_at DESC
            ");
            $stmt->execute([$leadId]);
            $activities = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'activities' => $activities
            ]);
            break;

        case 'add_lead_note':
            $leadId = $input['lead_id'] ?? 0;
            $note = $input['note'] ?? '';

            if (!$leadId) {
                throw new Exception('Lead ID is required');
            }

            if (empty($note)) {
                throw new Exception('Note is required');
            }

            $lead = getLeadById($leadId);
            if (!$lead) {
                throw new Exception('Lead not found');
            }

            // Check if user has access to this lead
            if (!isAdmin() && $lead['assigned_to'] != $_SESSION['user_id']) {
                throw new Exception('Access denied to this lead');
            }

            // Add activity record
            $stmt = $pdo->prepare("
                INSERT INTO lead_activities (lead_id, activity_type, description, created_by)
                VALUES (?, 'note_added', ?, ?)
            ");
            $result = $stmt->execute([$leadId, $note, $_SESSION['user_id']]);

            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Note added successfully'
                ]);
            } else {
                throw new Exception('Failed to add note');
            }
            break;

        case 'get_campaign_leads':
            $campaignId = $input['campaign_id'] ?? 0;
            $date = $input['date'] ?? null;
            $status = $input['status'] ?? null;

            if (!$campaignId) {
                throw new Exception('Campaign ID is required');
            }

            // Check if user has access to this campaign
            if (!isAdmin()) {
                $userAssignments = getUserCampaignAssignments($_SESSION['user_id']);
                $hasAccess = false;
                foreach ($userAssignments as $assignment) {
                    if ($assignment['id'] == $campaignId) {
                        $hasAccess = true;
                        break;
                    }
                }
                if (!$hasAccess) {
                    throw new Exception('Access denied to this campaign');
                }
            }

            $leads = getLeads($campaignId, null, $date, $status);
            
            echo json_encode([
                'success' => true,
                'leads' => $leads
            ]);
            break;

        case 'export_leads':
            $campaignId = $input['campaign_id'] ?? 0;
            $startDate = $input['start_date'] ?? null;
            $endDate = $input['end_date'] ?? null;

            if (!$campaignId) {
                throw new Exception('Campaign ID is required');
            }

            // Check if user has access to this campaign
            if (!isAdmin()) {
                throw new Exception('Admin access required for export');
            }

            $sql = "
                SELECT 
                    l.lead_number,
                    l.customer_name,
                    l.contact_number,
                    l.whatsapp_number,
                    l.lead_source,
                    l.sale_status,
                    l.follow_up_status,
                    l.reason_not_closed,
                    l.admin_approved,
                    l.assigned_date,
                    l.created_at,
                    l.updated_at,
                    c.name as campaign_name,
                    u.name as assigned_to_name
                FROM leads l
                LEFT JOIN campaigns c ON l.campaign_id = c.id
                LEFT JOIN users u ON l.assigned_to = u.id
                WHERE l.campaign_id = ?
            ";
            
            $params = [$campaignId];

            if ($startDate) {
                $sql .= " AND l.assigned_date >= ?";
                $params[] = $startDate;
            }

            if ($endDate) {
                $sql .= " AND l.assigned_date <= ?";
                $params[] = $endDate;
            }

            $sql .= " ORDER BY l.assigned_date DESC, l.created_at DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $leads = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'leads' => $leads,
                'campaign_id' => $campaignId
            ]);
            break;

        case 'update_lead_status':
            $leadId = $input['lead_id'] ?? 0;
            $status = $input['status'] ?? '';

            if (!$leadId) {
                throw new Exception('Lead ID is required');
            }

            if (empty($status)) {
                throw new Exception('Status is required');
            }

            $validStatuses = ['pending', 'closed', 'not_interested', 'confirmed', 'no_response'];
            if (!in_array($status, $validStatuses)) {
                throw new Exception('Invalid status');
            }

            $lead = getLeadById($leadId);
            if (!$lead) {
                throw new Exception('Lead not found');
            }

            // Check if user has access to this lead
            if (!isAdmin() && $lead['assigned_to'] != $_SESSION['user_id']) {
                throw new Exception('Access denied to this lead');
            }

            $result = updateLead($leadId, ['sale_status' => $status]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Lead status updated successfully'
            ]);
            break;

        default:
            throw new Exception('Invalid action: ' . $action);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>