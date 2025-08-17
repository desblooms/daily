<?php
// Campaign Media Module Setup Script
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || !isAdmin()) {
    die('Admin access required');
}

echo "<h1>Campaign Media Module Setup</h1>";

try {
    // Read and execute the media schema
    $schemaPath = __DIR__ . '/database/campaign_media_schema.sql';
    if (!file_exists($schemaPath)) {
        throw new Exception('Media schema file not found');
    }

    $schema = file_get_contents($schemaPath);
    
    // Split into individual statements and execute
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    
    echo "<h2>Creating Media Tables...</h2>";
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            echo "<p style='color: green;'>✓ Executed: " . substr($statement, 0, 50) . "...</p>";
        } catch (Exception $e) {
            // Skip if table already exists
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "<p style='color: orange;'>⚠ Already exists: " . substr($statement, 0, 50) . "...</p>";
            } else {
                echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
            }
        }
    }

    // Create upload directories
    echo "<h2>Creating Upload Directories...</h2>";
    
    $directories = [
        'uploads',
        'uploads/campaigns',
        'uploads/campaigns/1',
        'uploads/campaigns/1/images',
        'uploads/campaigns/1/videos'
    ];
    
    foreach ($directories as $dir) {
        $fullPath = __DIR__ . '/' . $dir;
        if (!is_dir($fullPath)) {
            if (mkdir($fullPath, 0755, true)) {
                echo "<p style='color: green;'>✓ Created directory: $dir</p>";
            } else {
                echo "<p style='color: red;'>✗ Failed to create directory: $dir</p>";
            }
        } else {
            echo "<p style='color: orange;'>⚠ Directory already exists: $dir</p>";
        }
    }

    // Add sample campaign media if campaign exists
    $stmt = $pdo->prepare("SELECT id FROM campaigns LIMIT 1");
    $stmt->execute();
    $sampleCampaign = $stmt->fetch();

    if ($sampleCampaign) {
        echo "<h2>Adding Sample Media Data...</h2>";
        
        // Create sample media entries (you would normally upload actual files)
        $sampleMedia = [
            [
                'title' => 'Facebook Ad Creative #1',
                'description' => 'Primary campaign creative for Facebook advertising',
                'media_type' => 'image',
                'file_name' => 'facebook_ad_1.jpg',
                'file_path' => 'uploads/campaigns/' . $sampleCampaign['id'] . '/images/facebook_ad_1.jpg',
                'mime_type' => 'image/jpeg'
            ],
            [
                'title' => 'Instagram Story Video',
                'description' => 'Vertical video for Instagram Stories',
                'media_type' => 'video',
                'file_name' => 'instagram_story.mp4',
                'file_path' => 'uploads/campaigns/' . $sampleCampaign['id'] . '/videos/instagram_story.mp4',
                'mime_type' => 'video/mp4'
            ],
            [
                'title' => 'Google Ads Banner',
                'description' => 'Display banner for Google Ads network',
                'media_type' => 'image',
                'file_name' => 'google_banner.png',
                'file_path' => 'uploads/campaigns/' . $sampleCampaign['id'] . '/images/google_banner.png',
                'mime_type' => 'image/png'
            ]
        ];

        foreach ($sampleMedia as $index => $media) {
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO campaign_media (
                    campaign_id, media_type, file_name, file_path, file_size,
                    mime_type, title, description, display_order, uploaded_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $sampleCampaign['id'],
                $media['media_type'],
                $media['file_name'],
                $media['file_path'],
                rand(100000, 5000000), // Random file size for demo
                $media['mime_type'],
                $media['title'],
                $media['description'],
                $index + 1,
                $_SESSION['user_id']
            ]);

            if ($result) {
                echo "<p style='color: green;'>✓ Added sample media: {$media['title']}</p>";
            }
        }

        // Add sample performance data
        echo "<h2>Adding Sample Performance Data...</h2>";
        
        for ($i = 7; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $platforms = ['facebook', 'instagram', 'google'];
            
            foreach ($platforms as $platform) {
                $impressions = rand(1000, 10000);
                $clicks = rand(50, 500);
                $conversions = rand(5, 50);
                $spend = rand(100, 1000);
                
                $stmt = $pdo->prepare("
                    INSERT IGNORE INTO campaign_performance (
                        campaign_id, date, platform, impressions, clicks, conversions,
                        spend, reach, engagement, ctr, cpc, cpm
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $result = $stmt->execute([
                    $sampleCampaign['id'],
                    $date,
                    $platform,
                    $impressions,
                    $clicks,
                    $conversions,
                    $spend,
                    rand(800, 8000), // reach
                    rand(100, 1000), // engagement
                    round(($clicks / $impressions) * 100, 4), // CTR
                    round($spend / $clicks, 4), // CPC
                    round(($spend / $impressions) * 1000, 4) // CPM
                ]);
            }
        }
        
        echo "<p style='color: green;'>✓ Added sample performance data for last 7 days</p>";

        // Add sample notes
        $sampleNotes = [
            [
                'title' => 'Campaign Launch',
                'content' => 'Campaign successfully launched across all platforms. Initial performance looks promising.',
                'note_type' => 'milestone'
            ],
            [
                'title' => 'Creative Update',
                'content' => 'Updated Facebook ad creative based on performance data. New version has better CTR.',
                'note_type' => 'optimization'
            ],
            [
                'title' => 'Budget Adjustment',
                'content' => 'Increased daily budget by 20% due to strong performance and low CPA.',
                'note_type' => 'update'
            ]
        ];

        foreach ($sampleNotes as $note) {
            $stmt = $pdo->prepare("
                INSERT INTO campaign_notes (campaign_id, note_type, title, content, created_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $sampleCampaign['id'],
                $note['note_type'],
                $note['title'],
                $note['content'],
                $_SESSION['user_id']
            ]);
        }
        
        echo "<p style='color: green;'>✓ Added sample campaign notes</p>";
    }

    echo "<h2>Setup Complete!</h2>";
    echo "<p>The Campaign Media Module has been successfully set up. Features include:</p>";
    echo "<ul>";
    echo "<li><strong>Media Gallery:</strong> Upload and display campaign ads (photos/videos)</li>";
    echo "<li><strong>Performance Tracking:</strong> Track impressions, clicks, conversions across platforms</li>";
    echo "<li><strong>Campaign Notes:</strong> Add updates, milestones, and optimization notes</li>";
    echo "<li><strong>Visual Analytics:</strong> Charts and graphs for performance visualization</li>";
    echo "<li><strong>Lightbox Gallery:</strong> Click to view full-size media with descriptions</li>";
    echo "</ul>";

    echo "<h3>Quick Access:</h3>";
    echo "<div style='display: flex; gap: 10px; margin: 20px 0;'>";
    echo "<a href='campaigns.php' style='background: #3B82F6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Campaigns</a>";
    if ($sampleCampaign) {
        echo "<a href='campaign-details.php?id={$sampleCampaign['id']}' style='background: #8B5CF6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Sample Campaign</a>";
    }
    echo "</div>";

    echo "<h3>Next Steps:</h3>";
    echo "<ol>";
    echo "<li>Visit the campaign details page</li>";
    echo "<li>Upload actual ad creatives (photos/videos)</li>";
    echo "<li>Add performance data from your ad platforms</li>";
    echo "<li>Use campaign notes to track progress and optimizations</li>";
    echo "</ol>";

} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    line-height: 1.6;
}
h1, h2, h3 {
    color: #333;
}
ul, ol {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
}
</style>