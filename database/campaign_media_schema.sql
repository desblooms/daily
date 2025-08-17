-- Campaign Media Extension - Add media support for campaign ads

-- Campaign Media Table for storing photos and videos
CREATE TABLE campaign_media (
    id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    media_type ENUM('image', 'video') NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT DEFAULT 0,
    mime_type VARCHAR(100),
    title VARCHAR(255),
    description TEXT,
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_campaign_media (campaign_id),
    INDEX idx_media_type (media_type),
    INDEX idx_display_order (display_order)
);

-- Campaign Performance Metrics Table
CREATE TABLE campaign_performance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    date DATE NOT NULL,
    impressions INT DEFAULT 0,
    clicks INT DEFAULT 0,
    conversions INT DEFAULT 0,
    spend DECIMAL(10,2) DEFAULT 0.00,
    reach INT DEFAULT 0,
    engagement INT DEFAULT 0,
    ctr DECIMAL(5,4) DEFAULT 0.0000, -- Click-through rate
    cpc DECIMAL(8,4) DEFAULT 0.0000, -- Cost per click
    cpm DECIMAL(8,4) DEFAULT 0.0000, -- Cost per mille
    platform ENUM('facebook', 'instagram', 'google', 'tiktok', 'linkedin', 'other') DEFAULT 'other',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_campaign_date_platform (campaign_id, date, platform),
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    INDEX idx_campaign_performance (campaign_id, date),
    INDEX idx_platform (platform)
);

-- Campaign Notes/Updates Table
CREATE TABLE campaign_notes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    note_type ENUM('update', 'milestone', 'issue', 'optimization', 'general') DEFAULT 'general',
    title VARCHAR(255),
    content TEXT NOT NULL,
    is_important BOOLEAN DEFAULT FALSE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_campaign_notes (campaign_id),
    INDEX idx_note_type (note_type),
    INDEX idx_created_date (created_at)
);

-- Add media count to campaigns table
ALTER TABLE campaigns 
ADD COLUMN media_count INT DEFAULT 0,
ADD COLUMN total_impressions BIGINT DEFAULT 0,
ADD COLUMN total_clicks BIGINT DEFAULT 0,
ADD COLUMN total_spend DECIMAL(12,2) DEFAULT 0.00;

-- Trigger to update media count
DELIMITER //
CREATE TRIGGER update_campaign_media_count
AFTER INSERT ON campaign_media
FOR EACH ROW
BEGIN
    UPDATE campaigns 
    SET media_count = (
        SELECT COUNT(*) 
        FROM campaign_media 
        WHERE campaign_id = NEW.campaign_id AND is_active = TRUE
    )
    WHERE id = NEW.campaign_id;
END//

CREATE TRIGGER update_campaign_media_count_delete
AFTER DELETE ON campaign_media
FOR EACH ROW
BEGIN
    UPDATE campaigns 
    SET media_count = (
        SELECT COUNT(*) 
        FROM campaign_media 
        WHERE campaign_id = OLD.campaign_id AND is_active = TRUE
    )
    WHERE id = OLD.campaign_id;
END//

CREATE TRIGGER update_campaign_media_count_update
AFTER UPDATE ON campaign_media
FOR EACH ROW
BEGIN
    UPDATE campaigns 
    SET media_count = (
        SELECT COUNT(*) 
        FROM campaign_media 
        WHERE campaign_id = NEW.campaign_id AND is_active = TRUE
    )
    WHERE id = NEW.campaign_id;
END//
DELIMITER ;

-- Create media upload directory structure
-- These would typically be created by the application
-- uploads/campaigns/{campaign_id}/images/
-- uploads/campaigns/{campaign_id}/videos/