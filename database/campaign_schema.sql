-- Lead Assignment & Campaign Module Database Schema

-- 1. Campaigns Table
CREATE TABLE campaigns (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    daily_lead_quota INT NOT NULL DEFAULT 10,
    status ENUM('active', 'paused', 'completed', 'cancelled') DEFAULT 'active',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_campaign_dates (start_date, end_date),
    INDEX idx_campaign_status (status)
);

-- 2. Campaign Assignments Table (Sales team members assigned to campaigns)
CREATE TABLE campaign_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    user_id INT NOT NULL,
    daily_quota INT NOT NULL,
    assigned_date DATE NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_campaign_user_date (campaign_id, user_id, assigned_date),
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_assignment_campaign (campaign_id),
    INDEX idx_assignment_user (user_id),
    INDEX idx_assignment_date (assigned_date)
);

-- 3. Leads Table
CREATE TABLE leads (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lead_number VARCHAR(50) UNIQUE NOT NULL,
    campaign_id INT NOT NULL,
    assigned_to INT NOT NULL,
    assigned_date DATE NOT NULL,
    
    -- Lead Details
    customer_name VARCHAR(255),
    contact_number VARCHAR(20) NOT NULL,
    whatsapp_number VARCHAR(20),
    lead_source ENUM('whatsapp', 'instagram', 'call', 'tiktok', 'other_social', 'other') NOT NULL,
    
    -- Status Information
    sale_status ENUM('pending', 'closed', 'not_interested', 'confirmed', 'no_response') DEFAULT 'pending',
    follow_up_status ENUM('done', 'call_done', 'scheduled', 'not_required') DEFAULT 'not_required',
    
    -- Additional Information
    reason_not_closed TEXT,
    notes TEXT,
    
    -- Admin Controls
    admin_approved BOOLEAN DEFAULT FALSE,
    admin_notes TEXT,
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    
    -- Tracking
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_lead_campaign (campaign_id),
    INDEX idx_lead_assigned (assigned_to),
    INDEX idx_lead_date (assigned_date),
    INDEX idx_lead_status (sale_status),
    INDEX idx_lead_source (lead_source),
    INDEX idx_lead_number (lead_number)
);

-- 4. Lead Activities Table (Track all changes and activities)
CREATE TABLE lead_activities (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lead_id INT NOT NULL,
    activity_type ENUM('created', 'updated', 'status_changed', 'approved', 'rejected', 'note_added') NOT NULL,
    description TEXT,
    old_value TEXT,
    new_value TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_activity_lead (lead_id),
    INDEX idx_activity_type (activity_type),
    INDEX idx_activity_date (created_at)
);

-- 5. Daily Lead Quotas Table (Track daily assignments)
CREATE TABLE daily_lead_quotas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    user_id INT NOT NULL,
    date DATE NOT NULL,
    quota_assigned INT NOT NULL,
    leads_filled INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_campaign_user_date (campaign_id, user_id, date),
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_quota_campaign (campaign_id),
    INDEX idx_quota_user (user_id),
    INDEX idx_quota_date (date)
);

-- 6. Campaign KPIs View (For reporting)
CREATE VIEW campaign_kpis AS
SELECT 
    c.id as campaign_id,
    c.name as campaign_name,
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
    ROUND((SUM(CASE WHEN l.sale_status = 'closed' THEN 1 ELSE 0 END) / COUNT(l.id)) * 100, 2) as conversion_rate
FROM campaigns c
LEFT JOIN leads l ON c.id = l.campaign_id
LEFT JOIN users u ON l.assigned_to = u.id
GROUP BY c.id, u.id, DATE(l.assigned_date);

-- 7. Lead Sources Reference Table
CREATE TABLE lead_sources (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    requires_whatsapp BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default lead sources
INSERT INTO lead_sources (name, display_name, requires_whatsapp) VALUES
('whatsapp', 'WhatsApp', TRUE),
('instagram', 'Instagram', FALSE),
('call', 'Direct Call', FALSE),
('tiktok', 'TikTok', FALSE),
('other_social', 'Other Social Media', FALSE),
('other', 'Other', FALSE);

-- 8. Campaign Settings Table (For flexible configuration)
CREATE TABLE campaign_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_campaign_setting (campaign_id, setting_key),
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
);

-- 9. Lead Number Generation Function
DELIMITER //
CREATE FUNCTION generate_lead_number(campaign_id INT, assigned_date DATE)
RETURNS VARCHAR(50)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE lead_count INT;
    DECLARE lead_number VARCHAR(50);
    
    -- Get count of leads for this campaign and date
    SELECT COUNT(*) + 1 INTO lead_count
    FROM leads 
    WHERE campaign_id = campaign_id AND assigned_date = assigned_date;
    
    -- Generate lead number: CMP001-20240116-001
    SET lead_number = CONCAT(
        'CMP', LPAD(campaign_id, 3, '0'), '-',
        DATE_FORMAT(assigned_date, '%Y%m%d'), '-',
        LPAD(lead_count, 3, '0')
    );
    
    RETURN lead_number;
END//
DELIMITER ;

-- 10. Triggers for automatic lead number generation and activity logging
DELIMITER //
CREATE TRIGGER before_lead_insert
BEFORE INSERT ON leads
FOR EACH ROW
BEGIN
    IF NEW.lead_number IS NULL OR NEW.lead_number = '' THEN
        SET NEW.lead_number = generate_lead_number(NEW.campaign_id, NEW.assigned_date);
    END IF;
END//

CREATE TRIGGER after_lead_insert
AFTER INSERT ON leads
FOR EACH ROW
BEGIN
    INSERT INTO lead_activities (lead_id, activity_type, description, created_by)
    VALUES (NEW.id, 'created', CONCAT('Lead created and assigned to ', NEW.assigned_to), NEW.assigned_to);
    
    -- Update daily quota filled count
    UPDATE daily_lead_quotas 
    SET leads_filled = leads_filled + 1 
    WHERE campaign_id = NEW.campaign_id 
    AND user_id = NEW.assigned_to 
    AND date = NEW.assigned_date;
END//

CREATE TRIGGER after_lead_update
AFTER UPDATE ON leads
FOR EACH ROW
BEGIN
    IF OLD.sale_status != NEW.sale_status THEN
        INSERT INTO lead_activities (lead_id, activity_type, description, old_value, new_value, created_by)
        VALUES (NEW.id, 'status_changed', 'Sale status updated', OLD.sale_status, NEW.sale_status, NEW.updated_by);
    END IF;
    
    IF OLD.admin_approved != NEW.admin_approved AND NEW.admin_approved = TRUE THEN
        INSERT INTO lead_activities (lead_id, activity_type, description, created_by)
        VALUES (NEW.id, 'approved', 'Lead approved by admin', NEW.approved_by);
    END IF;
END//
DELIMITER ;

-- Create indexes for better performance
CREATE INDEX idx_leads_composite ON leads(campaign_id, assigned_to, assigned_date, sale_status);
CREATE INDEX idx_activities_composite ON lead_activities(lead_id, activity_type, created_at);
CREATE INDEX idx_quotas_composite ON daily_lead_quotas(campaign_id, user_id, date);