-- Enhanced Daily Calendar Database Schema - FIXED VERSION
-- Create database
CREATE DATABASE IF NOT EXISTS u345095192_dailycalendar;
USE u345095192_dailycalendar;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','user') DEFAULT 'user',
    avatar VARCHAR(255) NULL,
    phone VARCHAR(20) NULL,
    department VARCHAR(100) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    failed_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL,
    force_password_change BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_active (is_active)
);

-- Tasks table with fixed structure
CREATE TABLE tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    details TEXT,
    date DATE NOT NULL,
    assigned_to INT NOT NULL,
    created_by INT NOT NULL,
    approved_by INT NULL,
    updated_by INT NULL,  -- ADDED: This was missing and causing trigger errors
    status ENUM('Pending','On Progress','Done','Approved','On Hold') DEFAULT 'Pending',
    priority ENUM('low','medium','high') DEFAULT 'medium',
    estimated_hours DECIMAL(4,2) NULL,
    actual_hours DECIMAL(4,2) NULL,
    due_time TIME NULL,
    tags JSON NULL,
    attachments JSON NULL,
    completion_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_assigned_to (assigned_to),
    INDEX idx_date (date),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_created_by (created_by),
    INDEX idx_date_status (date, status),
    INDEX idx_assigned_date (assigned_to, date)
);

-- Status logs table for tracking task status changes
CREATE TABLE status_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    status ENUM('Pending','On Progress','Done','Approved','On Hold') NOT NULL,
    previous_status ENUM('Pending','On Progress','Done','Approved','On Hold') NULL,
    updated_by INT NOT NULL,
    comments TEXT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE RESTRICT,
    
    INDEX idx_task_id (task_id),
    INDEX idx_timestamp (timestamp),
    INDEX idx_updated_by (updated_by)
);

-- Activity logs for general system activities
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    resource_type VARCHAR(50) NULL,
    resource_id INT NULL,
    details JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_timestamp (timestamp),
    INDEX idx_resource (resource_type, resource_id)
);

-- Password history for security
CREATE TABLE password_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
);

-- Login logs for security tracking
CREATE TABLE login_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    login_status ENUM('success','failed','blocked') DEFAULT 'success',
    failure_reason VARCHAR(255) NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_user_id (user_id),
    INDEX idx_login_time (login_time),
    INDEX idx_login_status (login_status),
    INDEX idx_ip_address (ip_address)
);

-- Password change logs
CREATE TABLE password_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    changed_by INT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    change_type ENUM('self','admin_reset','forced') DEFAULT 'self',
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_user_id (user_id),
    INDEX idx_changed_at (changed_at),
    INDEX idx_changed_by (changed_by)
);

-- Task comments for collaboration
CREATE TABLE task_comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    is_internal BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    
    INDEX idx_task_id (task_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
);

-- File attachments
CREATE TABLE task_attachments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    uploaded_by INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT,
    
    INDEX idx_task_id (task_id),
    INDEX idx_uploaded_by (uploaded_by)
);

-- Notifications system
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info','success','warning','error') DEFAULT 'info',
    related_type VARCHAR(50) NULL,
    related_id INT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at),
    INDEX idx_type (type)
);

-- System settings
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NULL,
    setting_type ENUM('string','integer','boolean','json') DEFAULT 'string',
    description TEXT NULL,
    is_public BOOLEAN DEFAULT FALSE,
    updated_by INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_setting_key (setting_key),
    INDEX idx_is_public (is_public)
);

-- Task templates for recurring tasks
CREATE TABLE task_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    title_template VARCHAR(200) NOT NULL,
    details_template TEXT NULL,
    default_priority ENUM('low','medium','high') DEFAULT 'medium',
    estimated_hours DECIMAL(4,2) NULL,
    tags JSON NULL,
    created_by INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    
    INDEX idx_created_by (created_by),
    INDEX idx_is_active (is_active)
);

-- Insert default admin user (password: admin123)
INSERT INTO users (name, email, password, role, department) VALUES 
('System Administrator', 'desblooms@gmail.com', '$2y$10$VLXVfbJz/z.RR4L6dWNy5.YJY.1qI2Qp8Zq7Vr4V/DhA8b3FrKHjG', 'admin', 'IT'),
('John Doe', 'user@example.com', '$2y$10$VLXVfbJz/z.RR4L6dWNy5.YJY.1qI2Qp8Zq7Vr4V/DhA8b3FrKHjG', 'user', 'Development'),
('Jane Smith', 'jane@example.com', '$2y$10$VLXVfbJz/z.RR4L6dWNy5.YJY.1qI2Qp8Zq7Vr4V/DhA8b3FrKHjG', 'user', 'Design'),
('Mike Johnson', 'mike@example.com', '$2y$10$VLXVfbJz/z.RR4L6dWNy5.YJY.1qI2Qp8Zq7Vr4V/DhA8b3FrKHjG', 'user', 'Marketing');

-- Insert sample tasks
INSERT INTO tasks (date, title, details, assigned_to, created_by, updated_by, status, priority, estimated_hours) VALUES
(CURDATE(), 'Design Landing Page Wireframes', 'Create wireframes for the new product landing page including mobile and desktop versions', 3, 1, 1, 'Pending', 'high', 4.0),
(CURDATE(), 'Implement User Authentication', 'Set up secure user authentication system with JWT tokens and password encryption', 2, 1, 1, 'On Progress', 'high', 8.0),
(CURDATE(), 'Content Strategy Meeting', 'Quarterly content strategy review and planning session with marketing team', 4, 1, 1, 'Pending', 'medium', 2.0),
(CURDATE(), 'Database Optimization', 'Optimize database queries and add proper indexing for better performance', 2, 1, 1, 'Done', 'medium', 6.0),
(DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'User Testing Session', 'Conduct user testing for the new dashboard interface', 3, 1, 1, 'Pending', 'medium', 3.0),
(DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'API Documentation', 'Update API documentation with new endpoints and examples', 2, 1, 1, 'Pending', 'low', 4.0),
(DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'Social Media Campaign', 'Launch social media campaign for product announcement', 4, 1, 1, 'Pending', 'high', 5.0);

-- Insert sample status logs
INSERT INTO status_logs (task_id, status, updated_by, comments) VALUES
(1, 'Pending', 1, 'Initial task creation'),
(2, 'Pending', 1, 'Initial task creation'),
(2, 'On Progress', 2, 'Started working on authentication system'),
(3, 'Pending', 1, 'Initial task creation'),
(4, 'Pending', 1, 'Initial task creation'),
(4, 'On Progress', 2, 'Started database analysis'),
(4, 'Done', 2, 'Completed optimization and testing'),
(5, 'Pending', 1, 'Initial task creation'),
(6, 'Pending', 1, 'Initial task creation'),
(7, 'Pending', 1, 'Initial task creation');

-- Insert sample task templates
INSERT INTO task_templates (name, title_template, details_template, default_priority, estimated_hours, created_by) VALUES
('Daily Standup', 'Daily Standup - {date}', 'Daily team standup meeting to discuss progress and blockers', 'medium', 0.5, 1),
('Code Review', 'Code Review - {feature}', 'Review code changes for {feature} implementation', 'high', 2.0, 1),
('Weekly Report', 'Weekly Report - Week {week}', 'Prepare and submit weekly progress report', 'medium', 1.0, 1),
('Bug Fix', 'Bug Fix - {issue}', 'Investigate and fix reported bug: {issue}', 'high', 3.0, 1);

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description, is_public) VALUES
('site_name', 'Daily Calendar', 'string', 'Application name', TRUE),
('timezone', 'UTC', 'string', 'Default timezone', TRUE),
('date_format', 'Y-m-d', 'string', 'Default date format', TRUE),
('time_format', 'H:i', 'string', 'Default time format', TRUE),
('max_file_size', '10485760', 'integer', 'Maximum file upload size in bytes (10MB)', FALSE),
('session_timeout', '3600', 'integer', 'Session timeout in seconds', FALSE),
('password_min_length', '6', 'integer', 'Minimum password length', FALSE),
('max_login_attempts', '5', 'integer', 'Maximum failed login attempts before lockout', FALSE),
('lockout_duration', '900', 'integer', 'Account lockout duration in seconds (15 minutes)', FALSE),
('enable_notifications', 'true', 'boolean', 'Enable system notifications', TRUE);

-- Create views for common queries
CREATE VIEW task_overview AS
SELECT 
    t.*,
    u.name as assigned_name,
    u.email as assigned_email,
    u.department as assigned_department,
    c.name as created_name,
    a.name as approved_name,
    DATEDIFF(CURDATE(), t.date) as days_since_due,
    CASE 
        WHEN t.status = 'Approved' THEN 'Completed'
        WHEN t.status = 'Done' THEN 'Awaiting Approval'
        WHEN t.date < CURDATE() AND t.status IN ('Pending', 'On Progress') THEN 'Overdue'
        WHEN t.date = CURDATE() AND t.status IN ('Pending', 'On Progress') THEN 'Due Today'
        ELSE 'Active'
    END as task_urgency
FROM tasks t
LEFT JOIN users u ON t.assigned_to = u.id
LEFT JOIN users c ON t.created_by = c.id
LEFT JOIN users a ON t.approved_by = a.id
WHERE u.is_active = TRUE;

-- Create view for user statistics
CREATE VIEW user_stats AS
SELECT 
    u.id,
    u.name,
    u.email,
    u.department,
    COUNT(t.id) as total_tasks,
    SUM(CASE WHEN t.status = 'Pending' THEN 1 ELSE 0 END) as pending_tasks,
    SUM(CASE WHEN t.status = 'On Progress' THEN 1 ELSE 0 END) as active_tasks,
    SUM(CASE WHEN t.status = 'Done' THEN 1 ELSE 0 END) as completed_tasks,
    SUM(CASE WHEN t.status = 'Approved' THEN 1 ELSE 0 END) as approved_tasks,
    SUM(CASE WHEN t.status = 'On Hold' THEN 1 ELSE 0 END) as on_hold_tasks,
    ROUND(AVG(CASE WHEN t.status IN ('Done', 'Approved') THEN t.actual_hours END), 2) as avg_completion_time,
    COUNT(CASE WHEN t.date < CURDATE() AND t.status IN ('Pending', 'On Progress') THEN 1 END) as overdue_tasks
FROM users u
LEFT JOIN tasks t ON u.id = t.assigned_to
WHERE u.is_active = TRUE
GROUP BY u.id, u.name, u.email, u.department;

-- Create indexes for better performance
CREATE INDEX idx_tasks_date_range ON tasks(date, status, assigned_to);
CREATE INDEX idx_status_logs_recent ON status_logs(timestamp DESC, task_id);
CREATE INDEX idx_activity_logs_recent ON activity_logs(timestamp DESC, user_id);
CREATE INDEX idx_notifications_unread ON notifications(user_id, is_read, created_at DESC);

-- FIXED TRIGGERS (No syntax errors)
DELIMITER //

CREATE TRIGGER task_status_change_trigger
AFTER UPDATE ON tasks
FOR EACH ROW
BEGIN
    -- Only log if status actually changed
    IF OLD.status != NEW.status THEN
        INSERT INTO status_logs (task_id, status, previous_status, updated_by, timestamp)
        VALUES (NEW.id, NEW.status, OLD.status, NEW.updated_by, NOW());
        
        -- Create notification for assigned user if status changed by someone else
        IF NEW.assigned_to != COALESCE(NEW.updated_by, NEW.assigned_to) THEN
            INSERT INTO notifications (user_id, title, message, type, related_type, related_id)
            VALUES (
                NEW.assigned_to,
                CONCAT('Task Status Updated: ', NEW.title),
                CONCAT('Your task status has been changed to ', NEW.status),
                'info',
                'task',
                NEW.id
            );
        END IF;
    END IF;
END//

CREATE TRIGGER task_assignment_trigger
AFTER UPDATE ON tasks
FOR EACH ROW
BEGIN
    -- Only trigger if assignment changed
    IF OLD.assigned_to != NEW.assigned_to THEN
        -- Notify new assignee
        INSERT INTO notifications (user_id, title, message, type, related_type, related_id)
        VALUES (
            NEW.assigned_to,
            CONCAT('New Task Assigned: ', NEW.title),
            CONCAT('You have been assigned a new task for ', NEW.date),
            'info',
            'task',
            NEW.id
        );
    END IF;
END//

CREATE TRIGGER new_task_trigger
AFTER INSERT ON tasks
FOR EACH ROW
BEGIN
    -- Log initial status
    INSERT INTO status_logs (task_id, status, updated_by, timestamp)
    VALUES (NEW.id, NEW.status, NEW.created_by, NOW());
    
    -- Notify assigned user if different from creator
    IF NEW.assigned_to != NEW.created_by THEN
        INSERT INTO notifications (user_id, title, message, type, related_type, related_id)
        VALUES (
            NEW.assigned_to,
            CONCAT('New Task Assigned: ', NEW.title),
            CONCAT('You have been assigned a new task for ', NEW.date),
            'info',
            'task',
            NEW.id
        );
    END IF;
END//

DELIMITER ;

-- Final optimization
ANALYZE TABLE users, tasks, status_logs, activity_logs, notifications;




-- Add password reset table to existing schema
CREATE TABLE password_reset_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(150) NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used_at TIMESTAMP NULL,
    ip_address VARCHAR(45) NULL,
    
    INDEX idx_email (email),
    INDEX idx_token (token),
    INDEX idx_expires (expires_at),
    INDEX idx_used (used)
);

-- Add rate limiting table for password reset requests
CREATE TABLE password_reset_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(150) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_email_time (email, attempt_time),
    INDEX idx_ip_time (ip_address, attempt_time)
);

-- Clean up expired tokens (run as scheduled job)
CREATE EVENT IF NOT EXISTS cleanup_expired_reset_tokens
ON SCHEDULE EVERY 1 HOUR
DO
  DELETE FROM password_reset_tokens 
  WHERE expires_at < NOW() - INTERVAL 24 HOUR;