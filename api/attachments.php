<?php
// Enhanced File Attachments API with rich media support
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

try {
    // Set headers
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    // Handle OPTIONS request for CORS
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }

    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Include required files
    require_once '../includes/db.php';
    require_once '../includes/auth.php';

    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }

    // Verify database connection
    if (!isset($pdo) || !$pdo) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }

    // Get action
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    switch ($action) {
        case 'upload':
            uploadAttachment($pdo);
            break;
            
        case 'get_attachments':
            getAttachments($pdo, $_GET);
            break;
            
        case 'delete_attachment':
            deleteAttachment($pdo, $_POST);
            break;
            
        case 'update_attachment':
            updateAttachment($pdo, $_POST);
            break;
            
        case 'create_share_link':
            createShareLink($pdo, $_POST);
            break;
            
        case 'get_shared_file':
            getSharedFile($pdo, $_GET);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Attachments API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error: ' . $e->getMessage()]);
}

function uploadAttachment($pdo) {
    
    $taskId = $_POST['task_id'] ?? null;
    
    if (!$taskId || !isset($_FILES['file'])) {
        echo json_encode(['success' => false, 'message' => 'Task ID and file required']);
        return;
    }
    
    // Verify task exists and user has permission
    $stmt = $pdo->prepare("
        SELECT * FROM tasks 
        WHERE id = ? AND (assigned_to = ? OR created_by = ? OR ? = 'admin')
    ");
    $stmt->execute([$taskId, $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['role']]);
    $task = $stmt->fetch();
    
    if (!$task) {
        echo json_encode(['success' => false, 'message' => 'Task not found or permission denied']);
        return;
    }
    
    $file = $_FILES['file'];
    
    // Validate file
    $maxSize = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'File too large (max 10MB)']);
        return;
    }
    
    $allowedTypes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime',
        'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain', 'text/csv', 'application/json', 'application/xml',
        'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed'
    ];
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'File type not allowed']);
        return;
    }
    
    try {
        // Create upload directory if it doesn't exist
        $uploadDir = '../uploads/tasks/' . $taskId . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Generate thumbnail for images
            $thumbnailPath = null;
            if (strpos($file['type'], 'image/') === 0) {
                $thumbnailPath = generateThumbnail($filepath, $uploadDir, $filename);
            }

            // Prepare metadata
            $metadata = json_encode([
                'original_name' => $file['name'],
                'size' => $file['size'],
                'type' => $file['type'],
                'uploaded_at' => date('Y-m-d H:i:s'),
                'uploaded_by' => $_SESSION['user_id']
            ]);

            // Save to database with enhanced fields
            $stmt = $pdo->prepare("
                INSERT INTO task_attachments (task_id, uploaded_by, filename, original_filename, file_path, file_size, mime_type, attachment_type, description, is_public, thumbnail_path, metadata)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $taskId,
                $_SESSION['user_id'],
                $filename,
                $file['name'],
                $filepath,
                $file['size'],
                $file['type'],
                $_POST['attachment_type'] ?? 'input',
                $_POST['description'] ?? '',
                !empty($_POST['is_public']) ? 1 : 0,
                $thumbnailPath,
                $metadata
            ]);
            
            $attachmentId = $pdo->lastInsertId();
            
            // Log activity
            $stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, resource_type, resource_id, details)
                VALUES (?, 'attachment_uploaded', 'task', ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $taskId,
                json_encode([
                    'attachment_id' => $attachmentId,
                    'filename' => $file['name'],
                    'type' => $_POST['attachment_type'] ?? 'input'
                ])
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'File uploaded successfully',
                'attachment_id' => $attachmentId,
                'filename' => $filename,
                'original_filename' => $file['name'],
                'thumbnail_path' => $thumbnailPath
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
        }
        
    } catch (Exception $e) {
        // Clean up uploaded file if database insert failed
        if (isset($filepath) && file_exists($filepath)) {
            unlink($filepath);
        }
        if (isset($thumbnailPath) && file_exists($thumbnailPath)) {
            unlink($thumbnailPath);
        }
        echo json_encode(['success' => false, 'message' => 'Upload failed: ' . $e->getMessage()]);
    }
}

function getAttachments($pdo, $input) {
    $taskId = $input['task_id'] ?? null;

    if (!$taskId) {
        echo json_encode(['success' => false, 'message' => 'Missing task ID']);
        return;
    }

    // Verify task exists and user has permission
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        echo json_encode(['success' => false, 'message' => 'Task not found']);
        return;
    }

    // Check permissions
    if ($_SESSION['role'] !== 'admin' && 
        $task['assigned_to'] != $_SESSION['user_id'] && 
        $task['created_by'] != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        return;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT ta.*, u.name as uploaded_by_name, u.avatar as uploaded_by_avatar
            FROM task_attachments ta
            LEFT JOIN users u ON ta.uploaded_by = u.id
            WHERE ta.task_id = ?
            ORDER BY ta.attachment_type, ta.created_at DESC
        ");
        $stmt->execute([$taskId]);
        $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group by attachment type for better organization
        $groupedAttachments = [
            'input' => [],
            'output' => [],
            'reference' => [],
            'work_sample' => []
        ];

        foreach ($attachments as $attachment) {
            $type = $attachment['attachment_type'];
            if (!isset($groupedAttachments[$type])) {
                $groupedAttachments[$type] = [];
            }
            $groupedAttachments[$type][] = $attachment;
        }

        echo json_encode([
            'success' => true,
            'attachments' => $attachments,
            'grouped_attachments' => $groupedAttachments,
            'count' => count($attachments)
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch attachments: ' . $e->getMessage()]);
    }
}

function deleteAttachment($pdo, $input) {
    $attachmentId = $input['attachment_id'] ?? null;

    if (!$attachmentId) {
        echo json_encode(['success' => false, 'message' => 'Missing attachment ID']);
        return;
    }

    try {
        // Get attachment details
        $stmt = $pdo->prepare("
            SELECT ta.*, t.assigned_to, t.created_by
            FROM task_attachments ta
            LEFT JOIN tasks t ON ta.task_id = t.id
            WHERE ta.id = ?
        ");
        $stmt->execute([$attachmentId]);
        $attachment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$attachment) {
            echo json_encode(['success' => false, 'message' => 'Attachment not found']);
            return;
        }

        // Check permissions (admin, task creator, task assignee, or file uploader)
        if ($_SESSION['role'] !== 'admin' && 
            $attachment['assigned_to'] != $_SESSION['user_id'] && 
            $attachment['created_by'] != $_SESSION['user_id'] &&
            $attachment['uploaded_by'] != $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            return;
        }

        $pdo->beginTransaction();

        // Delete file shares first
        $stmt = $pdo->prepare("DELETE FROM task_file_shares WHERE attachment_id = ?");
        $stmt->execute([$attachmentId]);

        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM task_attachments WHERE id = ?");
        $stmt->execute([$attachmentId]);

        // Delete physical files
        if (file_exists($attachment['file_path'])) {
            unlink($attachment['file_path']);
        }
        if ($attachment['thumbnail_path'] && file_exists($attachment['thumbnail_path'])) {
            unlink($attachment['thumbnail_path']);
        }

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Attachment deleted successfully']);

    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to delete attachment: ' . $e->getMessage()]);
    }
}

function updateAttachment($pdo, $input) {
    $attachmentId = $input['attachment_id'] ?? null;

    if (!$attachmentId) {
        echo json_encode(['success' => false, 'message' => 'Missing attachment ID']);
        return;
    }

    try {
        // Get attachment details
        $stmt = $pdo->prepare("
            SELECT ta.*, t.assigned_to, t.created_by
            FROM task_attachments ta
            LEFT JOIN tasks t ON ta.task_id = t.id
            WHERE ta.id = ?
        ");
        $stmt->execute([$attachmentId]);
        $attachment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$attachment) {
            echo json_encode(['success' => false, 'message' => 'Attachment not found']);
            return;
        }

        // Check permissions
        if ($_SESSION['role'] !== 'admin' && 
            $attachment['assigned_to'] != $_SESSION['user_id'] && 
            $attachment['created_by'] != $_SESSION['user_id'] &&
            $attachment['uploaded_by'] != $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            return;
        }

        // Update attachment
        $stmt = $pdo->prepare("
            UPDATE task_attachments 
            SET description = ?, attachment_type = ?, is_public = ?
            WHERE id = ?
        ");

        $result = $stmt->execute([
            $input['description'] ?? $attachment['description'],
            $input['attachment_type'] ?? $attachment['attachment_type'],
            !empty($input['is_public']) ? 1 : 0,
            $attachmentId
        ]);

        if (!$result) {
            throw new Exception('Failed to update attachment');
        }

        echo json_encode(['success' => true, 'message' => 'Attachment updated successfully']);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to update attachment: ' . $e->getMessage()]);
    }
}

function createShareLink($pdo, $input) {
    $attachmentId = $input['attachment_id'] ?? null;
    $expiresIn = $input['expires_in'] ?? 7; // Days

    if (!$attachmentId) {
        echo json_encode(['success' => false, 'message' => 'Missing attachment ID']);
        return;
    }

    try {
        // Get attachment details
        $stmt = $pdo->prepare("
            SELECT ta.*, t.assigned_to, t.created_by
            FROM task_attachments ta
            LEFT JOIN tasks t ON ta.task_id = t.id
            WHERE ta.id = ?
        ");
        $stmt->execute([$attachmentId]);
        $attachment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$attachment) {
            echo json_encode(['success' => false, 'message' => 'Attachment not found']);
            return;
        }

        // Check permissions
        if ($_SESSION['role'] !== 'admin' && 
            $attachment['assigned_to'] != $_SESSION['user_id'] && 
            $attachment['created_by'] != $_SESSION['user_id'] &&
            $attachment['uploaded_by'] != $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            return;
        }

        // Generate share token
        $shareToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiresIn} days"));

        $stmt = $pdo->prepare("
            INSERT INTO task_file_shares (attachment_id, permissions, expires_at, share_token, shared_by)
            VALUES (?, ?, ?, ?, ?)
        ");

        $permissions = json_encode([
            'view' => true,
            'download' => !empty($input['allow_download']),
            'edit' => false,
            'delete' => false
        ]);

        $result = $stmt->execute([
            $attachmentId,
            $permissions,
            $expiresAt,
            $shareToken,
            $_SESSION['user_id']
        ]);

        if (!$result) {
            throw new Exception('Failed to create share link');
        }

        $shareUrl = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/attachments.php?action=get_shared_file&token=" . $shareToken;

        echo json_encode([
            'success' => true,
            'message' => 'Share link created successfully',
            'share_token' => $shareToken,
            'share_url' => $shareUrl,
            'expires_at' => $expiresAt
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to create share link: ' . $e->getMessage()]);
    }
}

function getSharedFile($pdo, $input) {
    $token = $input['token'] ?? null;

    if (!$token) {
        http_response_code(404);
        echo "Invalid or missing share token";
        return;
    }

    try {
        // Get share details
        $stmt = $pdo->prepare("
            SELECT tfs.*, ta.file_path, ta.original_filename, ta.mime_type, ta.file_size
            FROM task_file_shares tfs
            LEFT JOIN task_attachments ta ON tfs.attachment_id = ta.id
            WHERE tfs.share_token = ? AND (tfs.expires_at IS NULL OR tfs.expires_at > NOW())
        ");
        $stmt->execute([$token]);
        $share = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$share) {
            http_response_code(404);
            echo "Share link expired or not found";
            return;
        }

        // Update access count
        $stmt = $pdo->prepare("
            UPDATE task_file_shares 
            SET access_count = access_count + 1, last_accessed = NOW()
            WHERE share_token = ?
        ");
        $stmt->execute([$token]);

        // Serve the file
        if (file_exists($share['file_path'])) {
            header('Content-Type: ' . $share['mime_type']);
            header('Content-Length: ' . $share['file_size']);
            header('Content-Disposition: inline; filename="' . $share['original_filename'] . '"');
            
            readfile($share['file_path']);
        } else {
            http_response_code(404);
            echo "File not found";
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo "Error accessing shared file";
    }
}

function generateThumbnail($filePath, $uploadDir, $filename) {
    try {
        $imageInfo = getimagesize($filePath);
        if (!$imageInfo) {
            return null;
        }

        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $type = $imageInfo[2];

        // Only generate thumbnail if image is large enough
        if ($width <= 300 && $height <= 300) {
            return null;
        }

        // Create image resource
        switch ($type) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($filePath);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($filePath);
                break;
            case IMAGETYPE_GIF:
                $image = imagecreatefromgif($filePath);
                break;
            default:
                return null;
        }

        if (!$image) {
            return null;
        }

        // Calculate thumbnail dimensions
        $thumbWidth = 300;
        $thumbHeight = 300;
        
        if ($width > $height) {
            $thumbHeight = ($height / $width) * $thumbWidth;
        } else {
            $thumbWidth = ($width / $height) * $thumbHeight;
        }

        // Create thumbnail
        $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);
        
        // Preserve transparency for PNG and GIF
        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
            imagefilledrectangle($thumbnail, 0, 0, $thumbWidth, $thumbHeight, $transparent);
        }

        imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);

        // Save thumbnail
        $thumbnailFilename = 'thumb_' . $filename;
        $thumbnailPath = $uploadDir . $thumbnailFilename;

        switch ($type) {
            case IMAGETYPE_JPEG:
                imagejpeg($thumbnail, $thumbnailPath, 85);
                break;
            case IMAGETYPE_PNG:
                imagepng($thumbnail, $thumbnailPath);
                break;
            case IMAGETYPE_GIF:
                imagegif($thumbnail, $thumbnailPath);
                break;
        }

        imagedestroy($image);
        imagedestroy($thumbnail);

        return $thumbnailPath;

    } catch (Exception $e) {
        error_log("Thumbnail generation failed: " . $e->getMessage());
        return null;
    }
}