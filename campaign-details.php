<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/campaign_functions.php';

requireLogin();

$campaignId = $_GET['id'] ?? 0;
if (!$campaignId) {
    header('Location: campaigns.php');
    exit;
}

$campaign = getCampaignById($campaignId);
if (!$campaign) {
    header('Location: campaigns.php');
    exit;
}

// Check access permissions
if (!isAdmin()) {
    $userAssignments = getUserCampaignAssignments($_SESSION['user_id']);
    $hasAccess = false;
    foreach ($userAssignments as $assignment) {
        if ($assignment['id'] == $campaignId) {
            $hasAccess = true;
            break;
        }
    }
    if (!$hasAccess) {
        header('Location: my-leads.php');
        exit;
    }
}

// Get campaign analytics
$analytics = getCampaignAnalytics($campaignId);
$kpis = getCampaignKPIs($campaignId);

// Get campaign media
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
$campaignMedia = $stmt->fetchAll();

// Get campaign notes
$stmt = $pdo->prepare("
    SELECT 
        cn.*,
        u.name as created_by_name
    FROM campaign_notes cn
    LEFT JOIN users u ON cn.created_by = u.id
    WHERE cn.campaign_id = ?
    ORDER BY cn.created_at DESC
    LIMIT 10
");
$stmt->execute([$campaignId]);
$campaignNotes = $stmt->fetchAll();

// Get campaign performance data
$stmt = $pdo->prepare("
    SELECT *
    FROM campaign_performance
    WHERE campaign_id = ?
    ORDER BY date DESC
    LIMIT 30
");
$stmt->execute([$campaignId]);
$performanceData = $stmt->fetchAll();

// Get team assignments
$stmt = $pdo->prepare("
    SELECT 
        ca.*,
        u.name as user_name,
        u.email as user_email,
        COUNT(l.id) as total_leads,
        SUM(CASE WHEN l.sale_status = 'closed' THEN 1 ELSE 0 END) as closed_leads
    FROM campaign_assignments ca
    LEFT JOIN users u ON ca.user_id = u.id
    LEFT JOIN leads l ON ca.campaign_id = l.campaign_id AND ca.user_id = l.assigned_to
    WHERE ca.campaign_id = ? AND ca.status = 'active'
    GROUP BY ca.id, u.id
    ORDER BY u.name
");
$stmt->execute([$campaignId]);
$teamAssignments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($campaign['name']) ?> - Campaign Details</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css">
    <script src="https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }
        .media-item {
            position: relative;
            overflow: hidden;
            border-radius: 0.75rem;
            aspect-ratio: 16/9;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        .media-item:hover {
            transform: scale(1.05);
        }
        .media-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.7));
            color: white;
            padding: 1rem;
            font-size: 0.875rem;
        }
        .performance-chart {
            height: 300px;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-weight: 500;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include 'includes/global-header.php'; ?>

    <div class="container mx-auto px-4 py-6">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
            <div>
                <div class="flex items-center space-x-3 mb-2">
                    <a href="campaigns.php" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <h1 class="text-3xl font-bold text-gray-900"><?= htmlspecialchars($campaign['name']) ?></h1>
                    <?php
                    $statusColors = [
                        'active' => 'bg-green-100 text-green-800',
                        'paused' => 'bg-yellow-100 text-yellow-800',
                        'completed' => 'bg-blue-100 text-blue-800',
                        'cancelled' => 'bg-red-100 text-red-800'
                    ];
                    $statusColor = $statusColors[$campaign['status']] ?? 'bg-gray-100 text-gray-800';
                    ?>
                    <span class="status-badge <?= $statusColor ?>"><?= ucfirst($campaign['status']) ?></span>
                </div>
                <p class="text-gray-600"><?= htmlspecialchars($campaign['description']) ?></p>
                <div class="flex items-center space-x-4 text-sm text-gray-500 mt-2">
                    <span><i class="fas fa-calendar mr-1"></i><?= date('M j, Y', strtotime($campaign['start_date'])) ?> - <?= date('M j, Y', strtotime($campaign['end_date'])) ?></span>
                    <span><i class="fas fa-user mr-1"></i>Created by <?= htmlspecialchars($campaign['created_by_name']) ?></span>
                </div>
            </div>
            <div class="flex space-x-3 mt-4 md:mt-0">
                <?php if (isAdmin()): ?>
                    <button onclick="openUploadModal()" class="bg-purple-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-purple-700 transition-colors">
                        <i class="fas fa-upload mr-2"></i>Upload Media
                    </button>
                    <button onclick="addNote()" class="bg-green-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-green-700 transition-colors">
                        <i class="fas fa-sticky-note mr-2"></i>Add Note
                    </button>
                <?php endif; ?>
                <a href="lead-dashboard.php?campaign_id=<?= $campaignId ?>" class="bg-blue-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-blue-700 transition-colors">
                    <i class="fas fa-chart-line mr-2"></i>Analytics
                </a>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl p-6 shadow-sm border">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-users text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-gray-900"><?= count($teamAssignments) ?></p>
                        <p class="text-sm text-gray-500">Team Members</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl p-6 shadow-sm border">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-bullseye text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-gray-900"><?= $analytics['total_leads'] ?? 0 ?></p>
                        <p class="text-sm text-gray-500">Total Leads</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl p-6 shadow-sm border">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-handshake text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-gray-900"><?= $analytics['closed_leads'] ?? 0 ?></p>
                        <p class="text-sm text-gray-500">Closed Leads</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl p-6 shadow-sm border">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-percentage text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-gray-900"><?= $analytics['overall_conversion_rate'] ?? 0 ?>%</p>
                        <p class="text-sm text-gray-500">Conversion Rate</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Campaign Ads Media Section -->
        <?php if (!empty($campaignMedia)): ?>
            <div class="bg-white rounded-xl p-6 shadow-sm border mb-8">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-gray-900">
                        <i class="fas fa-images text-purple-600 mr-2"></i>
                        Running Ads & Media (<?= count($campaignMedia) ?>)
                    </h2>
                    <?php if (isAdmin()): ?>
                        <button onclick="openUploadModal()" class="text-purple-600 hover:text-purple-800 font-medium">
                            <i class="fas fa-plus mr-1"></i>Add Media
                        </button>
                    <?php endif; ?>
                </div>
                
                <div class="media-grid">
                    <?php foreach ($campaignMedia as $media): ?>
                        <div class="media-item bg-gray-200" 
                             data-gallery="campaign-media"
                             data-glightbox="<?= $media['media_type'] === 'video' ? 'type: video; ' : '' ?>href: <?= htmlspecialchars($media['file_path']) ?>; title: <?= htmlspecialchars($media['title'] ?? 'Campaign Media') ?>; description: <?= htmlspecialchars($media['description'] ?? '') ?>">
                            
                            <?php if ($media['media_type'] === 'image'): ?>
                                <img src="<?= htmlspecialchars($media['file_path']) ?>" 
                                     alt="<?= htmlspecialchars($media['title'] ?? 'Campaign Image') ?>"
                                     class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full bg-gray-800 flex items-center justify-center relative">
                                    <video class="w-full h-full object-cover" muted>
                                        <source src="<?= htmlspecialchars($media['file_path']) ?>" type="<?= htmlspecialchars($media['mime_type']) ?>">
                                    </video>
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <div class="w-16 h-16 bg-black bg-opacity-50 rounded-full flex items-center justify-center">
                                            <i class="fas fa-play text-white text-xl"></i>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($media['title'] || $media['description']): ?>
                                <div class="media-overlay">
                                    <?php if ($media['title']): ?>
                                        <div class="font-medium"><?= htmlspecialchars($media['title']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($media['description']): ?>
                                        <div class="text-xs opacity-90 mt-1"><?= htmlspecialchars($media['description']) ?></div>
                                    <?php endif; ?>
                                    <div class="text-xs opacity-75 mt-1">
                                        <i class="fas fa-user mr-1"></i><?= htmlspecialchars($media['uploaded_by_name']) ?>
                                        <span class="ml-2"><i class="fas fa-calendar mr-1"></i><?= date('M j', strtotime($media['created_at'])) ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl p-8 shadow-sm border mb-8 text-center">
                <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-images text-gray-400 text-3xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">No campaign media uploaded</h3>
                <p class="text-gray-500 mb-6">Upload photos and videos of your running ads to track creative performance</p>
                <?php if (isAdmin()): ?>
                    <button onclick="openUploadModal()" class="bg-purple-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-purple-700 transition-colors">
                        <i class="fas fa-upload mr-2"></i>Upload First Media
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Performance Chart -->
        <?php if (!empty($performanceData)): ?>
            <div class="bg-white rounded-xl p-6 shadow-sm border mb-8">
                <h2 class="text-xl font-bold text-gray-900 mb-6">
                    <i class="fas fa-chart-area text-blue-600 mr-2"></i>
                    Campaign Performance
                </h2>
                <div class="performance-chart">
                    <canvas id="performanceChart"></canvas>
                </div>
            </div>
        <?php endif; ?>

        <!-- Team Performance -->
        <?php if (!empty($teamAssignments)): ?>
            <div class="bg-white rounded-xl p-6 shadow-sm border mb-8">
                <h2 class="text-xl font-bold text-gray-900 mb-6">
                    <i class="fas fa-users text-green-600 mr-2"></i>
                    Team Performance
                </h2>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Team Member</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Daily Quota</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Leads</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Closed</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Conversion</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($teamAssignments as $assignment): ?>
                                <?php 
                                $conversionRate = $assignment['total_leads'] > 0 ? 
                                    round(($assignment['closed_leads'] / $assignment['total_leads']) * 100, 2) : 0;
                                ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                                <span class="text-blue-600 text-xs font-medium"><?= strtoupper(substr($assignment['user_name'], 0, 2)) ?></span>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($assignment['user_name']) ?></p>
                                                <p class="text-xs text-gray-500"><?= htmlspecialchars($assignment['user_email']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?= $assignment['daily_quota'] ?></td>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= $assignment['total_leads'] ?></td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                                            <?= $assignment['closed_leads'] ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?= $conversionRate ?>%</td>
                                    <td class="px-6 py-4">
                                        <a href="lead-dashboard.php?campaign_id=<?= $campaignId ?>&user_id=<?= $assignment['user_id'] ?>" 
                                           class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Campaign Notes -->
        <?php if (!empty($campaignNotes)): ?>
            <div class="bg-white rounded-xl p-6 shadow-sm border">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-gray-900">
                        <i class="fas fa-sticky-note text-yellow-600 mr-2"></i>
                        Campaign Notes
                    </h2>
                    <?php if (isAdmin()): ?>
                        <button onclick="addNote()" class="text-yellow-600 hover:text-yellow-800 font-medium">
                            <i class="fas fa-plus mr-1"></i>Add Note
                        </button>
                    <?php endif; ?>
                </div>
                
                <div class="space-y-4">
                    <?php foreach ($campaignNotes as $note): ?>
                        <div class="border-l-4 border-gray-200 pl-4 py-2">
                            <div class="flex items-center justify-between mb-2">
                                <?php if ($note['title']): ?>
                                    <h4 class="font-medium text-gray-900"><?= htmlspecialchars($note['title']) ?></h4>
                                <?php endif; ?>
                                <div class="flex items-center space-x-2 text-xs text-gray-500">
                                    <span class="px-2 py-1 bg-gray-100 rounded"><?= ucfirst($note['note_type']) ?></span>
                                    <span><?= htmlspecialchars($note['created_by_name']) ?></span>
                                    <span><?= timeAgo($note['created_at']) ?></span>
                                </div>
                            </div>
                            <p class="text-sm text-gray-700"><?= nl2br(htmlspecialchars($note['content'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Upload Media Modal -->
    <?php if (isAdmin()): ?>
        <div id="uploadModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
            <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-gray-900">Upload Campaign Media</h2>
                    <button onclick="closeUploadModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <form id="uploadForm" enctype="multipart/form-data" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Media File</label>
                        <input type="file" name="media_file" accept="image/*,video/*" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <p class="text-xs text-gray-500 mt-1">Supports images (JPG, PNG, GIF) and videos (MP4, MOV, AVI)</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Title</label>
                        <input type="text" name="title" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" rows="3" 
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"></textarea>
                    </div>

                    <div class="flex space-x-4">
                        <button type="submit" class="flex-1 bg-purple-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-purple-700 transition-colors">
                            <i class="fas fa-upload mr-2"></i>Upload
                        </button>
                        <button type="button" onclick="closeUploadModal()" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <script>
        // Initialize lightbox for media gallery
        const lightbox = GLightbox({
            touchNavigation: true,
            loop: true,
            autoplayVideos: true
        });

        <?php if (!empty($performanceData)): ?>
        // Performance Chart
        const performanceCtx = document.getElementById('performanceChart').getContext('2d');
        const performanceChart = new Chart(performanceCtx, {
            type: 'line',
            data: {
                labels: [<?= implode(',', array_map(function($p) { return '"' . date('M j', strtotime($p['date'])) . '"'; }, array_reverse($performanceData))) ?>],
                datasets: [{
                    label: 'Impressions',
                    data: [<?= implode(',', array_map(function($p) { return $p['impressions']; }, array_reverse($performanceData))) ?>],
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    yAxisID: 'y'
                }, {
                    label: 'Clicks',
                    data: [<?= implode(',', array_map(function($p) { return $p['clicks']; }, array_reverse($performanceData))) ?>],
                    borderColor: '#10B981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    yAxisID: 'y'
                }, {
                    label: 'Conversions',
                    data: [<?= implode(',', array_map(function($p) { return $p['conversions']; }, array_reverse($performanceData))) ?>],
                    borderColor: '#F59E0B',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
        <?php endif; ?>

        function openUploadModal() {
            document.getElementById('uploadModal').classList.remove('hidden');
        }

        function closeUploadModal() {
            document.getElementById('uploadModal').classList.add('hidden');
            document.getElementById('uploadForm').reset();
        }

        function addNote() {
            const title = prompt('Note title (optional):');
            const content = prompt('Note content:');
            
            if (content) {
                fetch('api/campaign-media.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'add_note',
                        campaign_id: <?= $campaignId ?>,
                        title: title,
                        content: content
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Failed to add note'));
                    }
                });
            }
        }

        // Handle file upload
        document.getElementById('uploadForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'upload_media');
            formData.append('campaign_id', <?= $campaignId ?>);
            
            fetch('api/campaign-media.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Media uploaded successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Upload failed'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Upload failed. Please try again.');
            });
        });

        // Close modal when clicking outside
        document.getElementById('uploadModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeUploadModal();
            }
        });
    </script>
</body>
</html>