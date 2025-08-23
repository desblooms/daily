-- Enhanced Task Management Schema Updates
-- Adds support for rich file attachments, work outputs, and detailed task information

-- First, let's enhance the existing tasks table with additional fields
ALTER TABLE tasks 
ADD COLUMN task_category VARCHAR(100) NULL AFTER priority,
ADD COLUMN external_links JSON NULL AFTER attachments,
ADD COLUMN work_outputs JSON NULL AFTER external_links,
ADD COLUMN requirements TEXT NULL AFTER details,
ADD COLUMN deliverables TEXT NULL AFTER requirements,
ADD COLUMN collaboration_notes TEXT NULL AFTER completion_notes;

-- Enhance the task_attachments table for better file management
ALTER TABLE task_attachments 
ADD COLUMN attachment_type ENUM('input', 'output', 'reference', 'work_sample') DEFAULT 'input' AFTER mime_type,
ADD COLUMN description TEXT NULL AFTER attachment_type,
ADD COLUMN is_public BOOLEAN DEFAULT FALSE AFTER description,
ADD COLUMN thumbnail_path VARCHAR(500) NULL AFTER file_path,
ADD COLUMN metadata JSON NULL AFTER thumbnail_path;

-- Create a new table for work output sharing
CREATE TABLE task_work_outputs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    output_type ENUM('image', 'video', 'document', 'link', 'code', 'presentation', 'other') NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    file_path VARCHAR(500) NULL,
    external_url VARCHAR(1000) NULL,
    thumbnail_url VARCHAR(500) NULL,
    preview_data JSON NULL,
    metadata JSON NULL,
    view_count INT DEFAULT 0,
    is_featured BOOLEAN DEFAULT FALSE,
    visibility ENUM('private', 'team', 'public') DEFAULT 'team',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    
    INDEX idx_task_id (task_id),
    INDEX idx_output_type (output_type),
    INDEX idx_visibility (visibility),
    INDEX idx_created_by (created_by),
    INDEX idx_is_featured (is_featured)
);

-- Create table for task requirements and specifications
CREATE TABLE task_specifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    spec_type ENUM('requirement', 'deliverable', 'acceptance_criteria', 'resource') NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    is_completed BOOLEAN DEFAULT FALSE,
    completed_at TIMESTAMP NULL,
    completed_by INT NULL,
    order_index INT DEFAULT 0,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_task_id (task_id),
    INDEX idx_spec_type (spec_type),
    INDEX idx_priority (priority),
    INDEX idx_is_completed (is_completed),
    INDEX idx_order_index (order_index)
);

-- Create table for task collaboration and team communication
CREATE TABLE task_collaboration (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('assignee', 'reviewer', 'approver', 'collaborator', 'observer') DEFAULT 'collaborator',
    permissions JSON NULL,
    invited_by INT NOT NULL,
    invited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    accepted_at TIMESTAMP NULL,
    status ENUM('pending', 'accepted', 'declined', 'removed') DEFAULT 'pending',
    
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE RESTRICT,
    
    UNIQUE KEY unique_task_user (task_id, user_id),
    INDEX idx_task_id (task_id),
    INDEX idx_user_id (user_id),
    INDEX idx_role (role),
    INDEX idx_status (status)
);

-- Create table for task file sharing and permissions
CREATE TABLE task_file_shares (
    id INT PRIMARY KEY AUTO_INCREMENT,
    attachment_id INT NOT NULL,
    shared_with_user INT NULL,
    shared_with_role ENUM('admin', 'user', 'team', 'public') NULL,
    permissions JSON NULL, -- {view: true, download: true, edit: false, delete: false}
    expires_at TIMESTAMP NULL,
    share_token VARCHAR(255) NULL UNIQUE,
    shared_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_accessed TIMESTAMP NULL,
    access_count INT DEFAULT 0,
    
    FOREIGN KEY (attachment_id) REFERENCES task_attachments(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_with_user) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_by) REFERENCES users(id) ON DELETE RESTRICT,
    
    INDEX idx_attachment_id (attachment_id),
    INDEX idx_shared_with_user (shared_with_user),
    INDEX idx_shared_with_role (shared_with_role),
    INDEX idx_expires_at (expires_at),
    INDEX idx_share_token (share_token)
);

-- Create table for task progress tracking with rich media
CREATE TABLE task_progress_updates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    progress_percentage DECIMAL(5,2) NULL,
    update_type ENUM('status', 'progress', 'milestone', 'issue', 'note', 'media_share') DEFAULT 'progress',
    title VARCHAR(255) NULL,
    description TEXT NOT NULL,
    media_attachments JSON NULL, -- Array of file references
    external_links JSON NULL, -- Array of external URLs
    hours_logged DECIMAL(4,2) NULL,
    is_milestone BOOLEAN DEFAULT FALSE,
    visibility ENUM('private', 'team', 'public') DEFAULT 'team',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    
    INDEX idx_task_id (task_id),
    INDEX idx_user_id (user_id),
    INDEX idx_update_type (update_type),
    INDEX idx_created_at (created_at),
    INDEX idx_is_milestone (is_milestone)
);

-- Create table for task resources and references
CREATE TABLE task_resources (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    resource_type ENUM('document', 'link', 'image', 'video', 'tool', 'reference', 'template') NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    url VARCHAR(1000) NULL,
    file_path VARCHAR(500) NULL,
    metadata JSON NULL,
    is_required BOOLEAN DEFAULT FALSE,
    added_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE RESTRICT,
    
    INDEX idx_task_id (task_id),
    INDEX idx_resource_type (resource_type),
    INDEX idx_is_required (is_required)
);

-- Enhanced views for better data access
CREATE OR REPLACE VIEW enhanced_task_overview AS
SELECT 
    t.*,
    u.name as assigned_name,
    u.email as assigned_email,
    u.department as assigned_department,
    c.name as created_name,
    a.name as approved_name,
    COUNT(DISTINCT ta.id) as attachment_count,
    COUNT(DISTINCT twa.id) as work_output_count,
    COUNT(DISTINCT tpu.id) as progress_update_count,
    COUNT(DISTINCT tc.id) as collaborator_count,
    GROUP_CONCAT(DISTINCT ts.title ORDER BY ts.order_index SEPARATOR '|') as requirements_list,
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
LEFT JOIN task_attachments ta ON t.id = ta.task_id
LEFT JOIN task_work_outputs twa ON t.id = twa.task_id
LEFT JOIN task_progress_updates tpu ON t.id = tpu.task_id
LEFT JOIN task_collaboration tc ON t.id = tc.task_id AND tc.status = 'accepted'
LEFT JOIN task_specifications ts ON t.id = ts.task_id AND ts.spec_type = 'requirement'
WHERE u.is_active = TRUE
GROUP BY t.id;

-- View for task work outputs gallery
CREATE VIEW task_output_gallery AS
SELECT 
    two.id,
    two.task_id,
    two.output_type,
    two.title,
    two.description,
    two.file_path,
    two.external_url,
    two.thumbnail_url,
    two.preview_data,
    two.view_count,
    two.is_featured,
    two.visibility,
    two.created_at,
    u.name as created_by_name,
    u.avatar as created_by_avatar,
    t.title as task_title,
    t.date as task_date,
    assigned_user.name as task_assigned_to
FROM task_work_outputs two
JOIN users u ON two.created_by = u.id
JOIN tasks t ON two.task_id = t.id
JOIN users assigned_user ON t.assigned_to = assigned_user.id
WHERE two.visibility IN ('team', 'public')
ORDER BY two.created_at DESC;

-- Insert some sample enhanced data
INSERT INTO task_specifications (task_id, spec_type, title, description, priority, created_by) VALUES
(1, 'requirement', 'Mobile Responsive Design', 'Wireframes must be responsive and work on mobile devices (320px and up)', 'high', 1),
(1, 'requirement', 'Accessibility Compliance', 'Design must meet WCAG 2.1 AA standards', 'medium', 1),
(1, 'deliverable', 'High-Fidelity Wireframes', 'Detailed wireframes showing all page elements and interactions', 'high', 1),
(1, 'deliverable', 'Style Guide', 'Color palette, typography, and component specifications', 'medium', 1),
(2, 'requirement', 'JWT Token Implementation', 'Use JWT tokens for secure authentication', 'high', 1),
(2, 'requirement', 'Password Encryption', 'Implement bcrypt for password hashing', 'high', 1),
(2, 'deliverable', 'Authentication API', 'REST API endpoints for login, logout, and token refresh', 'high', 1);

-- Insert sample work outputs
INSERT INTO task_work_outputs (task_id, output_type, title, description, created_by, visibility) VALUES
(1, 'image', 'Landing Page Desktop Wireframe', 'High-fidelity wireframe showing the desktop version of the landing page', 3, 'team'),
(1, 'image', 'Landing Page Mobile Wireframe', 'Mobile-responsive wireframe for the landing page', 3, 'team'),
(2, 'document', 'Authentication API Documentation', 'Complete API documentation with examples and response formats', 2, 'team'),
(2, 'code', 'JWT Authentication Module', 'Node.js module for handling JWT token authentication', 2, 'team');

-- Insert sample progress updates
INSERT INTO task_progress_updates (task_id, user_id, progress_percentage, update_type, description, hours_logged) VALUES
(1, 3, 25.0, 'progress', 'Started working on desktop wireframes. Completed header and hero section design.', 2.0),
(1, 3, 50.0, 'progress', 'Desktop wireframes 80% complete. Started mobile version wireframes.', 2.5),
(2, 2, 30.0, 'progress', 'Set up basic JWT authentication structure. Token generation working.', 3.0),
(2, 2, 60.0, 'milestone', 'Login and logout functionality completed. Working on token refresh.', 2.5);

-- Add indexes for better performance
CREATE INDEX idx_task_outputs_featured ON task_work_outputs(is_featured, visibility, created_at DESC);
CREATE INDEX idx_progress_updates_recent ON task_progress_updates(task_id, created_at DESC);
CREATE INDEX idx_specifications_priority ON task_specifications(task_id, priority, order_index);

-- Update existing tasks with sample enhanced data
UPDATE tasks SET 
    task_category = 'Design', 
    requirements = 'Create responsive wireframes that work across all device sizes and meet accessibility standards',
    deliverables = 'High-fidelity wireframes for desktop and mobile, style guide with color palette and typography',
    external_links = JSON_ARRAY(
        JSON_OBJECT('title', 'Design System Reference', 'url', 'https://example.com/design-system'),
        JSON_OBJECT('title', 'Brand Guidelines', 'url', 'https://example.com/brand-guidelines')
    )
WHERE id = 1;

UPDATE tasks SET 
    task_category = 'Development', 
    requirements = 'Implement secure authentication system using JWT tokens with proper password encryption',
    deliverables = 'Working authentication API with login, logout, token refresh endpoints and comprehensive documentation',
    external_links = JSON_ARRAY(
        JSON_OBJECT('title', 'JWT Best Practices', 'url', 'https://example.com/jwt-practices'),
        JSON_OBJECT('title', 'Security Guidelines', 'url', 'https://example.com/security-guide')
    )
WHERE id = 2;

UPDATE tasks SET 
    task_category = 'Marketing', 
    requirements = 'Plan quarterly content strategy covering social media, blog posts, and email campaigns',
    deliverables = 'Content calendar, social media templates, and performance metrics dashboard'
WHERE id = 3;