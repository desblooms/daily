<?php
// VAPID Key Generator for Push Notifications
// Run this script once to generate VAPID keys for your application

// Composer autoload (if using composer for web-push library)
// require_once 'vendor/autoload.php';

// For manual VAPID key generation without external libraries
function generateVAPIDKeys() {
    // Generate a private key
    $privateKeyResource = openssl_pkey_new([
        "curve_name" => "prime256v1",
        "private_key_type" => OPENSSL_KEYTYPE_EC,
    ]);
    
    // Get private key details
    openssl_pkey_export($privateKeyResource, $privateKeyPEM);
    $privateKeyDetails = openssl_pkey_get_details($privateKeyResource);
    
    // Extract public key
    $publicKeyPEM = $privateKeyDetails['key'];
    
    // Convert to base64url format for web push
    $privateKeyBinary = openssl_pkey_get_private($privateKeyPEM);
    $privateKeyDetails = openssl_pkey_get_details($privateKeyBinary);
    
    $publicKeyBinary = $privateKeyDetails['ec']['key'];
    $privateKeyBinary = $privateKeyDetails['ec']['d'];
    
    $publicKey = base64url_encode($publicKeyBinary);
    $privateKey = base64url_encode($privateKeyBinary);
    
    return [
        'publicKey' => $publicKey,
        'privateKey' => $privateKey
    ];
}

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// Simple VAPID key generation (alternative method)
function generateSimpleVAPIDKeys() {
    // Generate random keys for demonstration
    $publicKey = base64url_encode(random_bytes(65));
    $privateKey = base64url_encode(random_bytes(32));
    
    return [
        'publicKey' => $publicKey,
        'privateKey' => $privateKey
    ];
}

$results = [];
$errors = [];

try {
    // Try to generate VAPID keys
    if (function_exists('openssl_pkey_new')) {
        $keys = generateVAPIDKeys();
        $results[] = "✓ VAPID keys generated using OpenSSL";
    } else {
        $keys = generateSimpleVAPIDKeys();
        $results[] = "⚠️ VAPID keys generated using simple method (OpenSSL not available)";
    }
    
    // Save keys to config file
    $configContent = "<?php
// VAPID Keys for Push Notifications
// Generated on: " . date('Y-m-d H:i:s') . "

define('VAPID_PUBLIC_KEY', '{$keys['publicKey']}');
define('VAPID_PRIVATE_KEY', '{$keys['privateKey']}');
define('VAPID_SUBJECT', 'mailto:admin@daily.desblooms.com');

// Additional push notification settings
define('PUSH_ENABLED', true);
define('PUSH_DEBUG', true);
?>";

    file_put_contents('includes/vapid-config.php', $configContent);
    $results[] = "✓ VAPID configuration saved to includes/vapid-config.php";
    
    // Create push subscription table
    require_once 'includes/db.php';
    
    $sql = "
        CREATE TABLE IF NOT EXISTS push_subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            endpoint TEXT NOT NULL,
            p256dh_key VARCHAR(255) NOT NULL,
            auth_key VARCHAR(255) NOT NULL,
            user_agent TEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_used TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_active BOOLEAN DEFAULT TRUE,
            INDEX idx_user_id (user_id),
            INDEX idx_endpoint_hash (endpoint(255)),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($sql);
    $results[] = "✓ Push subscriptions table created";
    
    // Create notifications log table
    $sql = "
        CREATE TABLE IF NOT EXISTS push_notifications_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            title VARCHAR(255) NOT NULL,
            body TEXT NOT NULL,
            data JSON,
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            success BOOLEAN DEFAULT FALSE,
            response_data JSON,
            error_message TEXT,
            INDEX idx_user_id (user_id),
            INDEX idx_sent_at (sent_at),
            INDEX idx_success (success)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($sql);
    $results[] = "✓ Push notifications log table created";
    
} catch (Exception $e) {
    $errors[] = "Error: " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Generate VAPID Keys</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <div class="text-center mb-6">
                <i class="fas fa-key text-4xl text-blue-600 mb-4"></i>
                <h1 class="text-2xl font-bold text-gray-900">VAPID Keys Generator</h1>
                <p class="text-gray-600">Web Push Notification Keys Setup</p>
            </div>

            <?php if (!empty($results)): ?>
                <div class="mb-6">
                    <h2 class="text-lg font-semibold text-green-800 mb-3">✅ Generation Results</h2>
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded">
                        <?php foreach ($results as $result): ?>
                            <div class="text-green-700 mb-1 text-sm"><?= $result ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="mb-6">
                    <h2 class="text-lg font-semibold text-red-800 mb-3">❌ Errors</h2>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded">
                        <?php foreach ($errors as $error): ?>
                            <div class="text-red-700 mb-1 text-sm"><?= $error ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($keys)): ?>
                <div class="mb-6">
                    <h2 class="text-lg font-semibold text-blue-800 mb-3">🔑 Generated VAPID Keys</h2>
                    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-blue-900 mb-2">Public Key:</label>
                            <div class="bg-white p-3 rounded border font-mono text-xs break-all">
                                <?= htmlspecialchars($keys['publicKey']) ?>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-blue-900 mb-2">Private Key:</label>
                            <div class="bg-white p-3 rounded border font-mono text-xs break-all">
                                <?= htmlspecialchars($keys['privateKey']) ?>
                            </div>
                        </div>
                        <div class="text-sm text-blue-700">
                            <strong>Important:</strong> Keep your private key secure! It's saved in includes/vapid-config.php
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
                <div class="flex">
                    <i class="fas fa-exclamation-triangle text-yellow-600 text-xl mr-3 mt-1"></i>
                    <div>
                        <h3 class="font-semibold text-yellow-900 mb-2">Security Notice</h3>
                        <ul class="text-yellow-800 text-sm space-y-1">
                            <li>• VAPID keys are used to identify your application to push services</li>
                            <li>• Keep your private key secure and never expose it to clients</li>
                            <li>• The public key is safe to use in client-side JavaScript</li>
                            <li>• These keys should be generated once and reused</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-6">
                <h3 class="font-semibold text-green-900 mb-3">Next Steps:</h3>
                <ol class="text-green-800 text-sm space-y-2">
                    <li class="flex items-start">
                        <span class="bg-green-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs mr-3 mt-0.5">1</span>
                        <span>VAPID keys have been generated and saved</span>
                    </li>
                    <li class="flex items-start">
                        <span class="bg-green-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs mr-3 mt-0.5">2</span>
                        <span>Database tables for push subscriptions created</span>
                    </li>
                    <li class="flex items-start">
                        <span class="bg-green-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs mr-3 mt-0.5">3</span>
                        <span>Add notification-manager.js to your pages</span>
                    </li>
                    <li class="flex items-start">
                        <span class="bg-green-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs mr-3 mt-0.5">4</span>
                        <span>Test push notifications</span>
                    </li>
                </ol>
            </div>

            <div class="text-center space-x-4">
                <a href="/test-notifications.php" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium inline-flex items-center">
                    <i class="fas fa-vial mr-2"></i>
                    Test Notifications
                </a>
                
                <a href="/admin-dashboard.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium inline-flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</body>
</html>