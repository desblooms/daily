<?php
// Production Configuration
return [
    // Environment
    'environment' => 'production',
    
    // Database Settings
    'database' => [
        'host' => 'localhost',
        'dbname' => 'u345095192_dailycalendar',
        'username' => 'u345095192_dailycalendar',
        'password' => 'Daily@788',
        'charset' => 'utf8mb4'
    ],
    
    // Application Settings
    'app' => [
        'name' => 'Daily Calendar - Enhanced Task Management',
        'version' => '2.0.0',
        'timezone' => 'America/New_York',
        'session_name' => 'DAILY_CALENDAR_SESSION',
        'session_lifetime' => 3600 * 8 // 8 hours
    ],
    
    // File Upload Settings
    'uploads' => [
        'max_file_size' => 10 * 1024 * 1024, // 10MB
        'allowed_types' => [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'video/mp4', 'video/avi', 'video/mov', 'video/wmv',
            'application/pdf', 'application/msword', 
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain', 'text/csv'
        ],
        'upload_path' => 'uploads/',
        'create_thumbnails' => true,
        'thumbnail_size' => [200, 200]
    ],
    
    // Security Settings
    'security' => [
        'max_login_attempts' => 5,
        'lockout_duration' => 900, // 15 minutes
        'password_min_length' => 8,
        'require_strong_password' => true,
        'session_regenerate_id' => true
    ],
    
    // Email Settings (if needed later)
    'email' => [
        'enabled' => false,
        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_username' => '',
        'smtp_password' => '',
        'from_email' => '',
        'from_name' => 'Daily Calendar System'
    ],
    
    // Feature Toggles
    'features' => [
        'enhanced_analytics' => true,
        'file_attachments' => true,
        'work_output_sharing' => true,
        'task_collaboration' => true,
        'notifications' => true,
        'campaigns' => true
    ],
    
    // Logging
    'logging' => [
        'enabled' => true,
        'level' => 'error', // 'debug', 'info', 'warning', 'error'
        'file' => 'logs/app.log',
        'max_files' => 7
    ]
];
?>