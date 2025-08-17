<?php
require_once 'db.php';

/**
 * Campaign Management Functions
 */

// Create a new campaign
function createCampaign($data) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO campaigns (name, description, start_date, end_date, daily_lead_quota, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['start_date'],
            $data['end_date'],
            $data['daily_lead_quota'],
            $_SESSION['user_id']
        ]);
        
        $campaignId = $pdo->lastInsertId();
        
        // Assign team members to campaign
        if (!empty($data['team_members'])) {
            assignTeamToCampaign($campaignId, $data['team_members'], $data['start_date'], $data['end_date']);
        }
        
        logActivity($_SESSION['user_id'], 'campaign_created', 'campaign', $campaignId);
        
        $pdo->commit();
        return $campaignId;
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
}

// Get all campaigns
function getCampaigns($status = null, $userId = null) {
    global $pdo;
    
    $sql = "
        SELECT 
            c.*,
            u.name as created_by_name,
            COUNT(DISTINCT ca.user_id) as team_count,
            COUNT(l.id) as total_leads,
            SUM(CASE WHEN l.sale_status = 'closed' THEN 1 ELSE 0 END) as closed_leads
        FROM campaigns c
        LEFT JOIN users u ON c.created_by = u.id
        LEFT JOIN campaign_assignments ca ON c.id = ca.campaign_id AND ca.status = 'active'
        LEFT JOIN leads l ON c.id = l.campaign_id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($status) {
        $sql .= " AND c.status = ?";
        $params[] = $status;
    }
    
    if ($userId) {
        $sql .= " AND (c.created_by = ? OR ca.user_id = ?)";
        $params[] = $userId;
        $params[] = $userId;
    }
    
    $sql .= " GROUP BY c.id ORDER BY c.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

// Get campaign by ID
function getCampaignById($campaignId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            u.name as created_by_name
        FROM campaigns c
        LEFT JOIN users u ON c.created_by = u.id
        WHERE c.id = ?
    ");
    $stmt->execute([$campaignId]);
    
    return $stmt->fetch();
}

// Assign team members to campaign
function assignTeamToCampaign($campaignId, $teamMembers, $startDate, $endDate) {
    global $pdo;
    
    $campaign = getCampaignById($campaignId);
    if (!$campaign) {
        throw new Exception('Campaign not found');
    }
    
    // Generate daily assignments for the campaign period
    $currentDate = new DateTime($startDate);
    $endDate = new DateTime($endDate);
    
    while ($currentDate <= $endDate) {
        $dateStr = $currentDate->format('Y-m-d');
        
        foreach ($teamMembers as $memberId) {
            // Insert campaign assignment
            $stmt = $pdo->prepare("
                INSERT INTO campaign_assignments (campaign_id, user_id, daily_quota, assigned_date)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE daily_quota = VALUES(daily_quota)
            ");
            $stmt->execute([$campaignId, $memberId, $campaign['daily_lead_quota'], $dateStr]);
            
            // Insert daily quota record
            $stmt = $pdo->prepare("
                INSERT INTO daily_lead_quotas (campaign_id, user_id, date, quota_assigned)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE quota_assigned = VALUES(quota_assigned)
            ");
            $stmt->execute([$campaignId, $memberId, $dateStr, $campaign['daily_lead_quota']]);
        }
        
        $currentDate->add(new DateInterval('P1D'));
    }
}

// Auto-generate leads for daily quotas
function generateDailyLeads($campaignId, $date = null) {
    global $pdo;
    
    if (!$date) {
        $date = date('Y-m-d');
    }
    
    // Get daily quotas for the date
    $stmt = $pdo->prepare("
        SELECT dq.*, u.name as user_name, c.name as campaign_name
        FROM daily_lead_quotas dq
        JOIN users u ON dq.user_id = u.id
        JOIN campaigns c ON dq.campaign_id = c.id
        WHERE dq.campaign_id = ? AND dq.date = ? AND dq.leads_filled < dq.quota_assigned
    ");
    $stmt->execute([$campaignId, $date]);
    $quotas = $stmt->fetchAll();
    
    $generatedCount = 0;
    
    foreach ($quotas as $quota) {
        $leadsToGenerate = $quota['quota_assigned'] - $quota['leads_filled'];
        
        for ($i = 0; $i < $leadsToGenerate; $i++) {
            $leadData = [
                'campaign_id' => $campaignId,
                'assigned_to' => $quota['user_id'],
                'assigned_date' => $date,
                'contact_number' => '', // Will be filled by sales member
                'lead_source' => 'other' // Default source
            ];
            
            createLead($leadData);
            $generatedCount++;
        }
    }
    
    return $generatedCount;
}

/**
 * Lead Management Functions
 */

// Create a new lead
function createLead($data) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO leads (
                campaign_id, assigned_to, assigned_date, customer_name, 
                contact_number, whatsapp_number, lead_source, sale_status, 
                follow_up_status, reason_not_closed, notes, updated_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['campaign_id'],
            $data['assigned_to'],
            $data['assigned_date'],
            $data['customer_name'] ?? null,
            $data['contact_number'],
            $data['whatsapp_number'] ?? null,
            $data['lead_source'],
            $data['sale_status'] ?? 'pending',
            $data['follow_up_status'] ?? 'not_required',
            $data['reason_not_closed'] ?? null,
            $data['notes'] ?? null,
            $_SESSION['user_id']
        ]);
        
        return $pdo->lastInsertId();
        
    } catch (Exception $e) {
        throw $e;
    }
}

// Update lead
function updateLead($leadId, $data) {
    global $pdo;
    
    try {
        $lead = getLeadById($leadId);
        if (!$lead) {
            throw new Exception('Lead not found');
        }
        
        // Check permissions
        if (!isAdmin() && $lead['assigned_to'] != $_SESSION['user_id']) {
            throw new Exception('Not authorized to update this lead');
        }
        
        $updateFields = [];
        $params = [];
        
        $allowedFields = [
            'customer_name', 'contact_number', 'whatsapp_number', 'lead_source',
            'sale_status', 'follow_up_status', 'reason_not_closed', 'notes'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateFields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (!empty($updateFields)) {
            $updateFields[] = "updated_by = ?";
            $updateFields[] = "updated_at = NOW()";
            $params[] = $_SESSION['user_id'];
            $params[] = $leadId;
            
            $sql = "UPDATE leads SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        
        return true;
        
    } catch (Exception $e) {
        throw $e;
    }
}

// Get leads for a user/campaign
function getLeads($campaignId = null, $userId = null, $date = null, $status = null) {
    global $pdo;
    
    $sql = "
        SELECT 
            l.*,
            c.name as campaign_name,
            u.name as assigned_name,
            ub.name as updated_by_name,
            ap.name as approved_by_name
        FROM leads l
        LEFT JOIN campaigns c ON l.campaign_id = c.id
        LEFT JOIN users u ON l.assigned_to = u.id
        LEFT JOIN users ub ON l.updated_by = ub.id
        LEFT JOIN users ap ON l.approved_by = ap.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($campaignId) {
        $sql .= " AND l.campaign_id = ?";
        $params[] = $campaignId;
    }
    
    if ($userId) {
        $sql .= " AND l.assigned_to = ?";
        $params[] = $userId;
    }
    
    if ($date) {
        $sql .= " AND l.assigned_date = ?";
        $params[] = $date;
    }
    
    if ($status) {
        $sql .= " AND l.sale_status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY l.assigned_date DESC, l.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

// Get lead by ID
function getLeadById($leadId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            l.*,
            c.name as campaign_name,
            u.name as assigned_name,
            ub.name as updated_by_name,
            ap.name as approved_by_name
        FROM leads l
        LEFT JOIN campaigns c ON l.campaign_id = c.id
        LEFT JOIN users u ON l.assigned_to = u.id
        LEFT JOIN users ub ON l.updated_by = ub.id
        LEFT JOIN users ap ON l.approved_by = ap.id
        WHERE l.id = ?
    ");
    $stmt->execute([$leadId]);
    
    return $stmt->fetch();
}

// Get user's daily leads
function getUserDailyLeads($userId, $date = null) {
    global $pdo;
    
    if (!$date) {
        $date = date('Y-m-d');
    }
    
    return getLeads(null, $userId, $date);
}

// Admin approve/reject lead
function adminApproveLead($leadId, $approved, $notes = null) {
    global $pdo;
    
    if (!isAdmin()) {
        throw new Exception('Admin access required');
    }
    
    $stmt = $pdo->prepare("
        UPDATE leads 
        SET admin_approved = ?, admin_notes = ?, approved_by = ?, approved_at = NOW()
        WHERE id = ?
    ");
    
    $result = $stmt->execute([$approved, $notes, $_SESSION['user_id'], $leadId]);
    
    if ($result) {
        $action = $approved ? 'lead_approved' : 'lead_rejected';
        logActivity($_SESSION['user_id'], $action, 'lead', $leadId);
    }
    
    return $result;
}

/**
 * KPI and Reporting Functions
 */

// Get campaign KPIs
function getCampaignKPIs($campaignId, $startDate = null, $endDate = null) {
    global $pdo;
    
    $sql = "
        SELECT 
            u.id as user_id,
            u.name as user_name,
            DATE(l.assigned_date) as date,
            COUNT(l.id) as total_leads,
            SUM(CASE WHEN l.sale_status = 'closed' THEN 1 ELSE 0 END) as closed_leads,
            SUM(CASE WHEN l.sale_status = 'pending' THEN 1 ELSE 0 END) as pending_leads,
            SUM(CASE WHEN l.sale_status = 'not_interested' THEN 1 ELSE 0 END) as not_interested_leads,
            SUM(CASE WHEN l.sale_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_leads,
            SUM(CASE WHEN l.sale_status = 'no_response' THEN 1 ELSE 0 END) as no_response_leads,
            SUM(CASE WHEN l.follow_up_status = 'done' THEN 1 ELSE 0 END) as follow_up_done,
            SUM(CASE WHEN l.admin_approved = TRUE THEN 1 ELSE 0 END) as admin_approved_leads,
            ROUND(
                (SUM(CASE WHEN l.sale_status = 'closed' THEN 1 ELSE 0 END) / 
                 NULLIF(COUNT(l.id), 0)) * 100, 2
            ) as conversion_rate
        FROM leads l
        JOIN users u ON l.assigned_to = u.id
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
    
    $sql .= " GROUP BY u.id, DATE(l.assigned_date) ORDER BY l.assigned_date DESC, u.name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

// Get daily quota status
function getDailyQuotaStatus($campaignId, $date = null) {
    global $pdo;
    
    if (!$date) {
        $date = date('Y-m-d');
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            dq.*,
            u.name as user_name,
            u.email as user_email
        FROM daily_lead_quotas dq
        JOIN users u ON dq.user_id = u.id
        WHERE dq.campaign_id = ? AND dq.date = ?
        ORDER BY u.name
    ");
    $stmt->execute([$campaignId, $date]);
    
    return $stmt->fetchAll();
}

// Get lead sources
function getLeadSources() {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM lead_sources WHERE is_active = TRUE ORDER BY display_name");
    $stmt->execute();
    
    return $stmt->fetchAll();
}

// Get campaign analytics summary
function getCampaignAnalytics($campaignId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(l.id) as total_leads,
            SUM(CASE WHEN l.sale_status = 'closed' THEN 1 ELSE 0 END) as closed_leads,
            SUM(CASE WHEN l.sale_status = 'pending' THEN 1 ELSE 0 END) as pending_leads,
            SUM(CASE WHEN l.sale_status = 'not_interested' THEN 1 ELSE 0 END) as not_interested_leads,
            SUM(CASE WHEN l.sale_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_leads,
            SUM(CASE WHEN l.sale_status = 'no_response' THEN 1 ELSE 0 END) as no_response_leads,
            SUM(CASE WHEN l.admin_approved = TRUE THEN 1 ELSE 0 END) as admin_approved_leads,
            COUNT(DISTINCT l.assigned_to) as team_members,
            ROUND(
                (SUM(CASE WHEN l.sale_status = 'closed' THEN 1 ELSE 0 END) / 
                 NULLIF(COUNT(l.id), 0)) * 100, 2
            ) as overall_conversion_rate
        FROM leads l
        WHERE l.campaign_id = ?
    ");
    $stmt->execute([$campaignId]);
    
    return $stmt->fetch();
}

// Validate lead data
function validateLeadData($data) {
    $errors = [];
    
    if (empty($data['contact_number'])) {
        $errors[] = 'Contact number is required';
    }
    
    if (empty($data['lead_source'])) {
        $errors[] = 'Lead source is required';
    }
    
    if ($data['lead_source'] === 'whatsapp' && empty($data['whatsapp_number'])) {
        $errors[] = 'WhatsApp number is required for WhatsApp leads';
    }
    
    // Validate phone numbers
    if (!empty($data['contact_number']) && !preg_match('/^[+]?[0-9\s\-\(\)]{7,20}$/', $data['contact_number'])) {
        $errors[] = 'Invalid contact number format';
    }
    
    if (!empty($data['whatsapp_number']) && !preg_match('/^[+]?[0-9\s\-\(\)]{7,20}$/', $data['whatsapp_number'])) {
        $errors[] = 'Invalid WhatsApp number format';
    }
    
    return $errors;
}

// Get user's campaign assignments
function getUserCampaignAssignments($userId, $date = null) {
    global $pdo;
    
    if (!$date) {
        $date = date('Y-m-d');
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            ca.daily_quota,
            dq.quota_assigned,
            dq.leads_filled,
            (dq.quota_assigned - dq.leads_filled) as remaining_quota
        FROM campaigns c
        JOIN campaign_assignments ca ON c.id = ca.campaign_id
        LEFT JOIN daily_lead_quotas dq ON c.id = dq.campaign_id AND dq.user_id = ca.user_id AND dq.date = ?
        WHERE ca.user_id = ? 
        AND ca.status = 'active'
        AND c.status = 'active'
        AND ? BETWEEN c.start_date AND c.end_date
        ORDER BY c.name
    ");
    $stmt->execute([$date, $userId, $date]);
    
    return $stmt->fetchAll();
}

/**
 * Campaign Media Functions
 */

// Get campaign media
function getCampaignMedia($campaignId, $mediaType = null) {
    global $pdo;
    
    $sql = "
        SELECT 
            cm.*,
            u.name as uploaded_by_name
        FROM campaign_media cm
        LEFT JOIN users u ON cm.uploaded_by = u.id
        WHERE cm.campaign_id = ? AND cm.is_active = TRUE
    ";
    
    $params = [$campaignId];
    
    if ($mediaType) {
        $sql .= " AND cm.media_type = ?";
        $params[] = $mediaType;
    }
    
    $sql .= " ORDER BY cm.display_order ASC, cm.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

// Add campaign note
function addCampaignNote($campaignId, $content, $title = null, $noteType = 'general') {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO campaign_notes (campaign_id, note_type, title, content, created_by)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $result = $stmt->execute([$campaignId, $noteType, $title, $content, $_SESSION['user_id']]);
    
    if ($result) {
        logActivity($_SESSION['user_id'], 'note_added', 'campaign', $campaignId, [
            'note_type' => $noteType,
            'title' => $title
        ]);
    }
    
    return $result;
}

// Get campaign notes
function getCampaignNotes($campaignId, $limit = 10) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            cn.*,
            u.name as created_by_name
        FROM campaign_notes cn
        LEFT JOIN users u ON cn.created_by = u.id
        WHERE cn.campaign_id = ?
        ORDER BY cn.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$campaignId, $limit]);
    
    return $stmt->fetchAll();
}

// Update campaign performance
function updateCampaignPerformance($campaignId, $date, $platform, $metrics) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO campaign_performance (
            campaign_id, date, platform, impressions, clicks, conversions, 
            spend, reach, engagement, ctr, cpc, cpm
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            impressions = VALUES(impressions),
            clicks = VALUES(clicks),
            conversions = VALUES(conversions),
            spend = VALUES(spend),
            reach = VALUES(reach),
            engagement = VALUES(engagement),
            ctr = VALUES(ctr),
            cpc = VALUES(cpc),
            cpm = VALUES(cpm),
            updated_at = NOW()
    ");
    
    $result = $stmt->execute([
        $campaignId, $date, $platform,
        $metrics['impressions'] ?? 0,
        $metrics['clicks'] ?? 0,
        $metrics['conversions'] ?? 0,
        $metrics['spend'] ?? 0.00,
        $metrics['reach'] ?? 0,
        $metrics['engagement'] ?? 0,
        $metrics['ctr'] ?? 0.0000,
        $metrics['cpc'] ?? 0.0000,
        $metrics['cpm'] ?? 0.0000
    ]);
    
    if ($result) {
        logActivity($_SESSION['user_id'], 'performance_updated', 'campaign', $campaignId, [
            'date' => $date,
            'platform' => $platform
        ]);
    }
    
    return $result;
}

// Get campaign performance data
function getCampaignPerformance($campaignId, $startDate = null, $endDate = null, $platform = null) {
    global $pdo;
    
    $sql = "SELECT * FROM campaign_performance WHERE campaign_id = ?";
    $params = [$campaignId];
    
    if ($startDate) {
        $sql .= " AND date >= ?";
        $params[] = $startDate;
    }
    
    if ($endDate) {
        $sql .= " AND date <= ?";
        $params[] = $endDate;
    }
    
    if ($platform) {
        $sql .= " AND platform = ?";
        $params[] = $platform;
    }
    
    $sql .= " ORDER BY date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

// Get campaign with enhanced details including media
function getCampaignWithDetails($campaignId) {
    global $pdo;
    
    // Get basic campaign info
    $campaign = getCampaignById($campaignId);
    if (!$campaign) {
        return null;
    }
    
    // Add media count and recent media
    $campaign['media'] = getCampaignMedia($campaignId);
    $campaign['media_count'] = count($campaign['media']);
    
    // Add recent notes
    $campaign['notes'] = getCampaignNotes($campaignId, 5);
    
    // Add performance summary
    $performanceData = getCampaignPerformance($campaignId);
    $campaign['performance_summary'] = [
        'total_impressions' => array_sum(array_column($performanceData, 'impressions')),
        'total_clicks' => array_sum(array_column($performanceData, 'clicks')),
        'total_conversions' => array_sum(array_column($performanceData, 'conversions')),
        'total_spend' => array_sum(array_column($performanceData, 'spend'))
    ];
    
    return $campaign;
}
?>