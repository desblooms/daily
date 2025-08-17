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
        case 'create_campaign':
            if (!isAdmin()) {
                throw new Exception('Admin access required');
            }

            // Validate required fields
            $required = ['name', 'start_date', 'end_date', 'daily_lead_quota'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    throw new Exception("Field '$field' is required");
                }
            }

            // Validate dates
            $startDate = new DateTime($input['start_date']);
            $endDate = new DateTime($input['end_date']);
            $today = new DateTime();

            if ($startDate < $today) {
                throw new Exception('Start date cannot be in the past');
            }

            if ($endDate <= $startDate) {
                throw new Exception('End date must be after start date');
            }

            // Validate quota
            if ($input['daily_lead_quota'] < 1 || $input['daily_lead_quota'] > 1000) {
                throw new Exception('Daily lead quota must be between 1 and 1000');
            }

            $campaignId = createCampaign($input);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Campaign created successfully',
                'campaign_id' => $campaignId
            ]);
            break;

        case 'generate_daily_leads':
            if (!isAdmin()) {
                throw new Exception('Admin access required');
            }

            $campaignId = $input['campaign_id'] ?? 0;
            if (!$campaignId) {
                throw new Exception('Campaign ID is required');
            }

            $date = $input['date'] ?? date('Y-m-d');
            $count = generateDailyLeads($campaignId, $date);
            
            echo json_encode([
                'success' => true,
                'message' => "Generated $count leads for $date",
                'count' => $count
            ]);
            break;

        case 'update_campaign':
            if (!isAdmin()) {
                throw new Exception('Admin access required');
            }

            $campaignId = $input['campaign_id'] ?? 0;
            if (!$campaignId) {
                throw new Exception('Campaign ID is required');
            }

            // TODO: Implement campaign update functionality
            echo json_encode([
                'success' => false,
                'message' => 'Update functionality not yet implemented'
            ]);
            break;

        case 'get_campaign_details':
            $campaignId = $input['campaign_id'] ?? 0;
            if (!$campaignId) {
                throw new Exception('Campaign ID is required');
            }

            $campaign = getCampaignById($campaignId);
            if (!$campaign) {
                throw new Exception('Campaign not found');
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

            $analytics = getCampaignAnalytics($campaignId);
            $kpis = getCampaignKPIs($campaignId);
            
            echo json_encode([
                'success' => true,
                'campaign' => $campaign,
                'analytics' => $analytics,
                'kpis' => $kpis
            ]);
            break;

        case 'assign_team_members':
            if (!isAdmin()) {
                throw new Exception('Admin access required');
            }

            $campaignId = $input['campaign_id'] ?? 0;
            $teamMembers = $input['team_members'] ?? [];

            if (!$campaignId) {
                throw new Exception('Campaign ID is required');
            }

            if (empty($teamMembers)) {
                throw new Exception('At least one team member is required');
            }

            $campaign = getCampaignById($campaignId);
            if (!$campaign) {
                throw new Exception('Campaign not found');
            }

            assignTeamToCampaign($campaignId, $teamMembers, $campaign['start_date'], $campaign['end_date']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Team members assigned successfully'
            ]);
            break;

        case 'get_daily_quotas':
            $campaignId = $input['campaign_id'] ?? 0;
            $date = $input['date'] ?? date('Y-m-d');

            if (!$campaignId) {
                throw new Exception('Campaign ID is required');
            }

            $quotas = getDailyQuotaStatus($campaignId, $date);
            
            echo json_encode([
                'success' => true,
                'quotas' => $quotas,
                'date' => $date
            ]);
            break;

        case 'update_daily_quota':
            if (!isAdmin()) {
                throw new Exception('Admin access required');
            }

            $campaignId = $input['campaign_id'] ?? 0;
            $userId = $input['user_id'] ?? 0;
            $date = $input['date'] ?? date('Y-m-d');
            $newQuota = $input['quota'] ?? 0;

            if (!$campaignId || !$userId || !$newQuota) {
                throw new Exception('Campaign ID, User ID, and quota are required');
            }

            if ($newQuota < 0 || $newQuota > 1000) {
                throw new Exception('Quota must be between 0 and 1000');
            }

            $stmt = $pdo->prepare("
                UPDATE daily_lead_quotas 
                SET quota_assigned = ? 
                WHERE campaign_id = ? AND user_id = ? AND date = ?
            ");
            $result = $stmt->execute([$newQuota, $campaignId, $userId, $date]);

            if ($result) {
                logActivity($_SESSION['user_id'], 'quota_updated', 'campaign', $campaignId, [
                    'user_id' => $userId,
                    'date' => $date,
                    'new_quota' => $newQuota
                ]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Daily quota updated successfully'
                ]);
            } else {
                throw new Exception('Failed to update quota');
            }
            break;

        case 'get_user_campaigns':
            $userId = $_SESSION['user_id'];
            $date = $input['date'] ?? date('Y-m-d');

            $campaigns = getUserCampaignAssignments($userId, $date);
            
            echo json_encode([
                'success' => true,
                'campaigns' => $campaigns,
                'date' => $date
            ]);
            break;

        case 'pause_campaign':
            if (!isAdmin()) {
                throw new Exception('Admin access required');
            }

            $campaignId = $input['campaign_id'] ?? 0;
            if (!$campaignId) {
                throw new Exception('Campaign ID is required');
            }

            $stmt = $pdo->prepare("UPDATE campaigns SET status = 'paused' WHERE id = ?");
            $result = $stmt->execute([$campaignId]);

            if ($result) {
                logActivity($_SESSION['user_id'], 'campaign_paused', 'campaign', $campaignId);
                echo json_encode([
                    'success' => true,
                    'message' => 'Campaign paused successfully'
                ]);
            } else {
                throw new Exception('Failed to pause campaign');
            }
            break;

        case 'resume_campaign':
            if (!isAdmin()) {
                throw new Exception('Admin access required');
            }

            $campaignId = $input['campaign_id'] ?? 0;
            if (!$campaignId) {
                throw new Exception('Campaign ID is required');
            }

            $stmt = $pdo->prepare("UPDATE campaigns SET status = 'active' WHERE id = ?");
            $result = $stmt->execute([$campaignId]);

            if ($result) {
                logActivity($_SESSION['user_id'], 'campaign_resumed', 'campaign', $campaignId);
                echo json_encode([
                    'success' => true,
                    'message' => 'Campaign resumed successfully'
                ]);
            } else {
                throw new Exception('Failed to resume campaign');
            }
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