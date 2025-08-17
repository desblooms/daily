<?php
// Campaign Module Setup Script
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || !isAdmin()) {
    die('Admin access required');
}

echo "<h1>Campaign Module Setup</h1>";

try {
    // Read and execute the schema
    $schemaPath = __DIR__ . '/database/campaign_schema.sql';
    if (!file_exists($schemaPath)) {
        throw new Exception('Schema file not found');
    }

    $schema = file_get_contents($schemaPath);
    
    // Split into individual statements and execute
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    
    echo "<h2>Creating Database Tables...</h2>";
    
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

    // Insert sample data
    echo "<h2>Setting up Sample Data...</h2>";

    // Create a sample campaign
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO campaigns (id, name, description, start_date, end_date, daily_lead_quota, created_by)
        VALUES (1, 'Sample Marketing Campaign', 'Demo campaign for testing', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 5, ?)
    ");
    $stmt->execute([$_SESSION['user_id']]);
    echo "<p style='color: green;'>✓ Sample campaign created</p>";

    // Get sample users (non-admin users)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'user' AND is_active = TRUE LIMIT 3");
    $stmt->execute();
    $sampleUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($sampleUsers)) {
        // Assign users to sample campaign
        foreach ($sampleUsers as $userId) {
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO campaign_assignments (campaign_id, user_id, daily_quota, assigned_date)
                VALUES (1, ?, 5, CURDATE())
            ");
            $stmt->execute([$userId]);

            $stmt = $pdo->prepare("
                INSERT IGNORE INTO daily_lead_quotas (campaign_id, user_id, date, quota_assigned)
                VALUES (1, ?, CURDATE(), 5)
            ");
            $stmt->execute([$userId]);
        }
        echo "<p style='color: green;'>✓ Users assigned to sample campaign</p>";

        // Create sample leads
        $leadSources = ['whatsapp', 'instagram', 'call', 'tiktok'];
        $saleStatuses = ['pending', 'closed', 'not_interested', 'confirmed'];
        
        for ($i = 1; $i <= 10; $i++) {
            $userId = $sampleUsers[array_rand($sampleUsers)];
            $source = $leadSources[array_rand($leadSources)];
            $status = $saleStatuses[array_rand($saleStatuses)];
            
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO leads (
                    campaign_id, assigned_to, assigned_date, customer_name, 
                    contact_number, lead_source, sale_status, updated_by
                ) VALUES (1, ?, CURDATE(), ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId, 
                "Sample Customer $i", 
                "+1234567890$i", 
                $source, 
                $status, 
                $_SESSION['user_id']
            ]);
        }
        echo "<p style='color: green;'>✓ Sample leads created</p>";
    } else {
        echo "<p style='color: orange;'>⚠ No regular users found to assign to campaign</p>";
    }

    echo "<h2>Setup Complete!</h2>";
    echo "<p>The Campaign Module has been successfully set up. You can now:</p>";
    echo "<ul>";
    echo "<li><a href='campaigns.php'>Manage Campaigns</a></li>";
    echo "<li><a href='my-leads.php'>View Your Leads</a></li>";
    echo "<li><a href='lead-dashboard.php'>Monitor KPIs</a></li>";
    echo "<li><a href='admin-lead-approval.php'>Approve Leads (Admin)</a></li>";
    echo "</ul>";

    echo "<h3>Quick Access Links:</h3>";
    echo "<div style='display: flex; gap: 10px; margin: 20px 0;'>";
    echo "<a href='campaigns.php' style='background: #3B82F6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Campaigns</a>";
    echo "<a href='my-leads.php' style='background: #10B981; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>My Leads</a>";
    echo "<a href='lead-dashboard.php' style='background: #8B5CF6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Dashboard</a>";
    echo "<a href='admin-lead-approval.php' style='background: #F59E0B; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Approvals</a>";
    echo "</div>";

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
ul {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
}
</style>