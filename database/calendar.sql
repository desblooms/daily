 
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tasks table
CREATE TABLE tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    date DATE NOT NULL,
    title VARCHAR(200) NOT NULL,
    details TEXT,
    assigned_to INT NOT NULL,
    status ENUM('Pending','On Progress','Done','Approved','On Hold') DEFAULT 'Pending',
    created_by INT NOT NULL,
    approved_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- Status logs table
CREATE TABLE status_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    status ENUM('Pending','On Progress','Done','Approved','On Hold') NOT NULL,
    updated_by INT NOT NULL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- Insert sample admin user (password: password)
INSERT INTO users (name, email, password, role) VALUES 
('Admin User', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert sample regular user (password: password)
INSERT INTO users (name, email, password, role) VALUES 
('John Doe', 'user@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user');

-- Insert sample tasks
INSERT INTO tasks (date, title, details, assigned_to, created_by, status) VALUES
(CURDATE(), 'Create Product Images', 'Design and create product images for the new collection', 2, 1, 'Pending'),
(CURDATE(), 'Update Website Content', 'Review and update homepage content', 2, 1, 'On Progress'),
(CURDATE(), 'Client Meeting Preparation', 'Prepare presentation slides for client meeting', 2, 1, 'Done'),
(DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'Code Review', 'Review pull requests and provide feedback', 2, 1, 'Pending');

-- Insert sample status logs
INSERT INTO status_logs (task_id, status, updated_by) VALUES
(1, 'Pending', 1),
(2, 'Pending', 1),
(2, 'On Progress', 2),
(3, 'Pending', 1),
(3, 'On Progress', 2),
(3, 'Done', 2),
(4, 'Pending', 1);