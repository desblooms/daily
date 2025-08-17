<?php
header('Content-Type: application/json');

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

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
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    switch ($action) {
        case 'upload_media':
            if (!isAdmin()) {
                throw new Exception('Admin access required');
            }

            $campaignId = (int)($_POST['campaign_id'] ?? 0);
            if (!$campaignId) {
                throw new Exception('Campaign ID is required');
            }

            // Verify campaign exists
            $stmt = $pdo->prepare("SELECT id FROM campaigns WHERE id = ?");
            $stmt->execute([$campaignId]);
            if (!$stmt->fetch()) {
                throw new Exception('Campaign not found');
            }

            if (!isset($_FILES['media_file']) || $_FILES['media_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('No file uploaded or upload error');
            }

            $file = $_FILES['media_file'];
            $title = $_POST['title'] ?? '';
            $description = $_POST['description'] ?? '';

            // Validate file type
            $allowedTypes = [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                'video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm'
            ];
            
            $fileType = $file['type'];
            if (!in_array($fileType, $allowedTypes)) {
                throw new Exception('Invalid file type. Please upload images (JPG, PNG, GIF, WebP) or videos (MP4, MOV, AVI, WebM)');
            }

            // Determine media type
            $mediaType = strpos($fileType, 'image/') === 0 ? 'image' : 'video';

            // File size validation (50MB max)
            $maxSize = 50 * 1024 * 1024; // 50MB
            if ($file['size'] > $maxSize) {
                throw new Exception('File too large. Maximum size is 50MB');
            }

            // Create upload directory
            $uploadDir = __DIR__ . "/../uploads/campaigns/{$campaignId}/{$mediaType}s/";
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    throw new Exception('Failed to create upload directory');
                }
            }

            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '_' . time() . '.' . $extension;
            $filePath = $uploadDir . $filename;
            $webPath = "uploads/campaigns/{$campaignId}/{$mediaType}s/{$filename}";

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new Exception('Failed to save uploaded file');
            }

            // Save to database
            $stmt = $pdo->prepare("
                INSERT INTO campaign_media (
                    campaign_id, media_type, file_name, file_path, file_size, 
                    mime_type, title, description, uploaded_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $campaignId, $mediaType, $filename, $webPath, $file['size'],
                $fileType, $title, $description, $_SESSION['user_id']
            ]);

            if ($result) {
                logActivity($_SESSION['user_id'], 'media_uploaded', 'campaign', $campaignId, [
                    'media_type' => $mediaType,
                    'filename' => $filename,
                    'title' => $title
                ]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Media uploaded successfully',
                    'media_id' => $pdo->lastInsertId(),
                    'file_path' => $webPath
                ]);
            } else {
                // Clean up file if database insert failed
                unlink($filePath);
                throw new Exception('Failed to save media information');
            }
            break;

        case 'delete_media':
            if (!isAdmin()) {
                throw new Exception('Admin access required');
            }

            $mediaId = (int)($_POST['media_id'] ?? 0);
            if (!$mediaId) {
                throw new Exception('Media ID is required');
            }

            // Get media info
            $stmt = $pdo->prepare("SELECT * FROM campaign_media WHERE id = ?");
            $stmt->execute([$mediaId]);
            $media = $stmt->fetch();

            if (!$media) {
                throw new Exception('Media not found');
            }

            // Delete file
            $filePath = __DIR__ . '/../' . $media['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // Delete from database
            $stmt = $pdo->prepare("UPDATE campaign_media SET is_active = FALSE WHERE id = ?");
            $result = $stmt->execute([$mediaId]);

            if ($result) {
                logActivity($_SESSION['user_id'], 'media_deleted', 'campaign', $media['campaign_id'], [
                    'media_id' => $mediaId,
                    'filename' => $media['file_name']
                ]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Media deleted successfully'
                ]);
            } else {
                throw new Exception('Failed to delete media');
            }
            break;

        case 'add_note':
            $input = json_decode(file_get_contents('php://input'), true);
            
            $campaignId = (int)($input['campaign_id'] ?? 0);
            $title = $input['title'] ?? '';
            $content = $input['content'] ?? '';
            $noteType = $input['note_type'] ?? 'general';

            if (!$campaignId) {
                throw new Exception('Campaign ID is required');
            }

            if (empty($content)) {
                throw new Exception('Note content is required');
            }

            // Verify campaign exists and user has access
            $stmt = $pdo->prepare("SELECT id FROM campaigns WHERE id = ?");
            $stmt->execute([$campaignId]);
            if (!$stmt->fetch()) {
                throw new Exception('Campaign not found');
            }

            // Insert note
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

                echo json_encode([
                    'success' => true,
                    'message' => 'Note added successfully',
                    'note_id' => $pdo->lastInsertId()
                ]);
            } else {
                throw new Exception('Failed to add note');
            }
            break;

        case 'update_performance':
            if (!isAdmin()) {
                throw new Exception('Admin access required');
            }

            $input = json_decode(file_get_contents('php://input'), true);
            
            $campaignId = (int)($input['campaign_id'] ?? 0);
            $date = $input['date'] ?? date('Y-m-d');
            $platform = $input['platform'] ?? 'other';
            $metrics = $input['metrics'] ?? [];

            if (!$campaignId) {
                throw new Exception('Campaign ID is required');
            }

            // Insert or update performance data
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

                echo json_encode([
                    'success' => true,
                    'message' => 'Performance data updated successfully'
                ]);
            } else {
                throw new Exception('Failed to update performance data');
            }
            break;

        case 'get_media':
            $campaignId = (int)($_GET['campaign_id'] ?? 0);
            if (!$campaignId) {
                throw new Exception('Campaign ID is required');
            }

            $stmt = $pdo->prepare("
                SELECT 
                    cm.*,
                    u.name as uploaded_by_name
                FROM campaign_media cm
                LEFT JOIN users u ON cm.uploaded_by = u.id
                WHERE cm.campaign_id = ? AND cm.is_active = TRUE
                ORDER BY cm.display_order ASC, cm.created_at DESC
            ");
            $stmt->execute([$campaignId]);
            $media = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'media' => $media
            ]);
            break;

        case 'reorder_media':
            if (!isAdmin()) {
                throw new Exception('Admin access required');
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $mediaOrder = $input['media_order'] ?? [];

            if (empty($mediaOrder)) {
                throw new Exception('Media order is required');
            }

            $pdo->beginTransaction();
            try {
                foreach ($mediaOrder as $index => $mediaId) {
                    $stmt = $pdo->prepare("UPDATE campaign_media SET display_order = ? WHERE id = ?");
                    $stmt->execute([$index, $mediaId]);
                }
                $pdo->commit();

                echo json_encode([
                    'success' => true,
                    'message' => 'Media order updated successfully'
                ]);
            } catch (Exception $e) {
                $pdo->rollback();
                throw $e;
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