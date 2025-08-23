-- Production Database Optimizations
-- Run these queries to optimize database performance for production

-- Indexes for tasks table
CREATE INDEX IF NOT EXISTS idx_tasks_assigned_to ON tasks(assigned_to);
CREATE INDEX IF NOT EXISTS idx_tasks_date ON tasks(date);
CREATE INDEX IF NOT EXISTS idx_tasks_status ON tasks(status);
CREATE INDEX IF NOT EXISTS idx_tasks_created_by ON tasks(created_by);
CREATE INDEX IF NOT EXISTS idx_tasks_priority ON tasks(priority);
CREATE INDEX IF NOT EXISTS idx_tasks_created_at ON tasks(created_at);
CREATE INDEX IF NOT EXISTS idx_tasks_date_status ON tasks(date, status);
CREATE INDEX IF NOT EXISTS idx_tasks_assigned_status ON tasks(assigned_to, status);

-- Indexes for users table
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_users_is_active ON users(is_active);
CREATE INDEX IF NOT EXISTS idx_users_department ON users(department);
CREATE INDEX IF NOT EXISTS idx_users_last_login ON users(last_login);

-- Indexes for task_attachments table (if exists)
CREATE INDEX IF NOT EXISTS idx_task_attachments_task_id ON task_attachments(task_id);
CREATE INDEX IF NOT EXISTS idx_task_attachments_uploaded_by ON task_attachments(uploaded_by);
CREATE INDEX IF NOT EXISTS idx_task_attachments_type ON task_attachments(attachment_type);
CREATE INDEX IF NOT EXISTS idx_task_attachments_created ON task_attachments(created_at);


-- Indexes for task_work_outputs table (if exists)
CREATE INDEX IF NOT EXISTS idx_task_work_outputs_task_id ON task_work_outputs(task_id);
CREATE INDEX IF NOT EXISTS idx_task_work_outputs_created_by ON task_work_outputs(created_by);
CREATE INDEX IF NOT EXISTS idx_task_work_outputs_visibility ON task_work_outputs(visibility);
CREATE INDEX IF NOT EXISTS idx_task_work_outputs_featured ON task_work_outputs(is_featured);
CREATE INDEX IF NOT EXISTS idx_task_work_outputs_created ON task_work_outputs(created_at);

-- Indexes for notifications table (if exists)
CREATE INDEX IF NOT EXISTS idx_notifications_user_id ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_notifications_read_status ON notifications(is_read);
CREATE INDEX IF NOT EXISTS idx_notifications_created_at ON notifications(created_at);
CREATE INDEX IF NOT EXISTS idx_notifications_user_unread ON notifications(user_id, is_read);

-- Indexes for activity_logs table (if exists)
CREATE INDEX IF NOT EXISTS idx_activity_logs_user_id ON activity_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_activity_logs_created_at ON activity_logs(created_at);
CREATE INDEX IF NOT EXISTS idx_activity_logs_action ON activity_logs(action);

-- Indexes for task_progress_updates table (if exists)
CREATE INDEX IF NOT EXISTS idx_task_progress_task_id ON task_progress_updates(task_id);
CREATE INDEX IF NOT EXISTS idx_task_progress_updated_by ON task_progress_updates(updated_by);
CREATE INDEX IF NOT EXISTS idx_task_progress_created ON task_progress_updates(created_at);

-- Indexes for campaigns table (if exists)
CREATE INDEX IF NOT EXISTS idx_campaigns_created_by ON campaigns(created_by);
CREATE INDEX IF NOT EXISTS idx_campaigns_status ON campaigns(status);
CREATE INDEX IF NOT EXISTS idx_campaigns_start_date ON campaigns(start_date);
CREATE INDEX IF NOT EXISTS idx_campaigns_end_date ON campaigns(end_date);

-- Indexes for leads table (if exists)  
CREATE INDEX IF NOT EXISTS idx_leads_assigned_to ON leads(assigned_to);
CREATE INDEX IF NOT EXISTS idx_leads_status ON leads(status);
CREATE INDEX IF NOT EXISTS idx_leads_created_at ON leads(created_at);
CREATE INDEX IF NOT EXISTS idx_leads_campaign_id ON leads(campaign_id);

-- Performance optimization settings
-- Set appropriate InnoDB buffer pool size (adjust based on available RAM)
-- SET GLOBAL innodb_buffer_pool_size = 1073741824; -- 1GB, adjust as needed

-- Enable query cache if not already enabled (MySQL < 8.0)
-- SET GLOBAL query_cache_type = ON;
-- SET GLOBAL query_cache_size = 268435456; -- 256MB

-- Optimize table maintenance
-- OPTIMIZE TABLE tasks;
-- OPTIMIZE TABLE users;  
-- OPTIMIZE TABLE task_attachments;
-- OPTIMIZE TABLE notifications;
-- OPTIMIZE TABLE activity_logs;

-- Add table comments for documentation
ALTER TABLE tasks COMMENT = 'Core task management table with enhanced features';
ALTER TABLE users COMMENT = 'User accounts with role-based access control';

-- Additional performance recommendations (run manually as needed):
/*
-- Analyze tables to update statistics
ANALYZE TABLE tasks;
ANALYZE TABLE users;
ANALYZE TABLE task_attachments;
ANALYZE TABLE notifications;

-- Check for unused indexes periodically
SELECT * FROM sys.schema_unused_indexes WHERE object_schema = 'u345095192_dailycalendar';

-- Monitor slow queries
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2; -- Log queries taking more than 2 seconds
*/