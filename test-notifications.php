<?php
// Test page for PWA notifications and functionality
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireLogin();

$results = [];
$tests = [];

// Run basic tests
try {
    // Test 1: Check if VAPID keys exist
    if (file_exists('includes/vapid-config.php')) {
        $results[] = "✅ VAPID configuration file exists";
        require_once 'includes/vapid-config.php';
        
        if (defined('VAPID_PUBLIC_KEY') && defined('VAPID_PRIVATE_KEY')) {
            $results[] = "✅ VAPID keys are configured";
        } else {
            $results[] = "❌ VAPID keys not properly configured";
        }
    } else {
        $results[] = "❌ VAPID configuration file missing - run generate-vapid-keys.php";
    }
    
    // Test 2: Check database tables
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'push_subscriptions'");
    $stmt->execute();
    if ($stmt->fetch()) {
        $results[] = "✅ Push subscriptions table exists";
        
        // Count active subscriptions
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM push_subscriptions WHERE is_active = TRUE");
        $stmt->execute();
        $count = $stmt->fetch()['count'];
        $results[] = "📊 Active subscriptions: {$count}";
    } else {
        $results[] = "❌ Push subscriptions table missing";
    }
    
    // Test 3: Check service worker file
    if (file_exists('sw.js')) {
        $results[] = "✅ Service worker file exists";
    } else {
        $results[] = "❌ Service worker file missing";
    }
    
    // Test 4: Check manifest file
    if (file_exists('manifest.json')) {
        $results[] = "✅ PWA manifest file exists";
    } else {
        $results[] = "❌ PWA manifest file missing";
    }
    
    // Test 5: Check notification manager
    if (file_exists('assets/js/notification-manager.js')) {
        $results[] = "✅ Notification manager JavaScript exists";
    } else {
        $results[] = "❌ Notification manager JavaScript missing";
    }
    
} catch (Exception $e) {
    $results[] = "❌ Error running tests: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PWA & Notifications Test - Daily Calendar</title>
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#3B82F6">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Daily Calendar">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="Daily Calendar">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="/manifest.json">
    
    <!-- PWA Icons -->
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/icons/icon-192x192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="/assets/icons/icon-512x512.png">
    <link rel="apple-touch-icon" href="/assets/icons/icon-192x192.png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="assets/js/notification-manager.js?v=<?= time() ?>"></script>
    
    <script>
        window.userRole = '<?= $_SESSION['role'] ?? 'user' ?>';
        window.userId = <?= $_SESSION['user_id'] ?? 'null' ?>;
        window.userName = '<?= htmlspecialchars($_SESSION['user_name'] ?? 'Unknown') ?>';
    </script>
</head>
<body class="bg-gray-100 p-4">
    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <div class="text-center">
                <i class="fas fa-mobile-alt text-4xl text-blue-600 mb-4"></i>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">PWA & Push Notifications Test</h1>
                <p class="text-gray-600">Test your Progressive Web App installation and push notification functionality</p>
            </div>
        </div>

        <!-- System Tests -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">
                <i class="fas fa-clipboard-check text-blue-600 mr-2"></i>
                System Status
            </h2>
            <div class="grid gap-2">
                <?php foreach ($results as $result): ?>
                    <div class="text-sm <?= strpos($result, '✅') !== false ? 'text-green-700' : (strpos($result, '❌') !== false ? 'text-red-700' : 'text-blue-700') ?>">
                        <?= $result ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- PWA Installation Test -->
        <div class="grid md:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">
                    <i class="fas fa-download text-green-600 mr-2"></i>
                    PWA Installation
                </h2>
                <div class="space-y-4">
                    <div id="pwa-status" class="p-4 rounded-lg bg-gray-50">
                        <p class="text-sm text-gray-600">Checking PWA installation status...</p>
                    </div>
                    <button id="install-pwa" class="w-full bg-green-600 hover:bg-green-700 text-white py-3 px-4 rounded-lg font-medium transition-colors" style="display: none;">
                        <i class="fas fa-download mr-2"></i>
                        Install as App
                    </button>
                    <div class="text-xs text-gray-500">
                        <strong>How to test:</strong> This app can be installed on your device for a native-like experience.
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">
                    <i class="fas fa-bell text-yellow-600 mr-2"></i>
                    Push Notifications
                </h2>
                <div class="space-y-4">
                    <div id="notification-status" class="p-4 rounded-lg bg-gray-50">
                        <p class="text-sm text-gray-600">Checking notification permissions...</p>
                    </div>
                    <button id="enable-notifications" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 px-4 rounded-lg font-medium transition-colors">
                        <i class="fas fa-bell mr-2"></i>
                        Enable Notifications
                    </button>
                    <div class="text-xs text-gray-500">
                        <strong>How to test:</strong> Enable notifications to receive real-time updates about your tasks.
                    </div>
                </div>
            </div>
        </div>

        <!-- Test Controls -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">
                <i class="fas fa-vial text-purple-600 mr-2"></i>
                Test Functions
            </h2>
            <div class="grid md:grid-cols-3 gap-4">
                <button onclick="testBasicNotification()" class="bg-purple-600 hover:bg-purple-700 text-white py-3 px-4 rounded-lg font-medium transition-colors">
                    <i class="fas fa-bell mr-2"></i>
                    Test Basic Notification
                </button>
                <button onclick="testTaskNotification()" class="bg-indigo-600 hover:bg-indigo-700 text-white py-3 px-4 rounded-lg font-medium transition-colors">
                    <i class="fas fa-tasks mr-2"></i>
                    Test Task Notification
                </button>
                <button onclick="testOfflineMode()" class="bg-gray-600 hover:bg-gray-700 text-white py-3 px-4 rounded-lg font-medium transition-colors">
                    <i class="fas fa-wifi mr-2"></i>
                    Test Offline Mode
                </button>
            </div>
        </div>

        <!-- Device Information -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">
                <i class="fas fa-info-circle text-teal-600 mr-2"></i>
                Device & Browser Information
            </h2>
            <div id="device-info" class="grid md:grid-cols-2 gap-4 text-sm">
                <div class="space-y-2">
                    <div><strong>User Agent:</strong> <span id="user-agent"></span></div>
                    <div><strong>Platform:</strong> <span id="platform"></span></div>
                    <div><strong>Screen Size:</strong> <span id="screen-size"></span></div>
                </div>
                <div class="space-y-2">
                    <div><strong>Service Worker Support:</strong> <span id="sw-support"></span></div>
                    <div><strong>Push Manager Support:</strong> <span id="push-support"></span></div>
                    <div><strong>Notification Permission:</strong> <span id="notification-permission"></span></div>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <div class="text-center mt-8 space-x-4">
            <a href="admin-dashboard.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium inline-flex items-center">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Dashboard
            </a>
            <a href="generate-vapid-keys.php" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium inline-flex items-center">
                <i class="fas fa-key mr-2"></i>
                Generate VAPID Keys
            </a>
        </div>
    </div>

    <script>
        // Test functions
        let deferredPrompt;

        document.addEventListener('DOMContentLoaded', function() {
            updateDeviceInfo();
            checkPWAStatus();
            checkNotificationStatus();
        });

        function updateDeviceInfo() {
            document.getElementById('user-agent').textContent = navigator.userAgent.substring(0, 100) + '...';
            document.getElementById('platform').textContent = navigator.platform;
            document.getElementById('screen-size').textContent = screen.width + 'x' + screen.height;
            document.getElementById('sw-support').textContent = 'serviceWorker' in navigator ? '✅ Supported' : '❌ Not Supported';
            document.getElementById('push-support').textContent = 'PushManager' in window ? '✅ Supported' : '❌ Not Supported';
            document.getElementById('notification-permission').textContent = Notification.permission;
        }

        function checkPWAStatus() {
            const status = document.getElementById('pwa-status');
            const installBtn = document.getElementById('install-pwa');

            if (window.matchMedia('(display-mode: standalone)').matches) {
                status.innerHTML = '<p class="text-sm text-green-600">✅ App is installed and running in standalone mode</p>';
                installBtn.style.display = 'none';
            } else {
                status.innerHTML = '<p class="text-sm text-blue-600">📱 App can be installed for a better experience</p>';
            }
        }

        function checkNotificationStatus() {
            const status = document.getElementById('notification-status');
            const permission = Notification.permission;

            if (permission === 'granted') {
                status.innerHTML = '<p class="text-sm text-green-600">✅ Notifications are enabled</p>';
            } else if (permission === 'denied') {
                status.innerHTML = '<p class="text-sm text-red-600">❌ Notifications are blocked</p>';
            } else {
                status.innerHTML = '<p class="text-sm text-yellow-600">⚠️ Notification permission not requested</p>';
            }
        }

        // Test Functions
        async function testBasicNotification() {
            if (window.notificationManager) {
                window.notificationManager.showNotification('Test notification sent successfully!', 'success');
                
                // Also try to send push notification if subscribed
                if (window.notificationManager.isSubscribed) {
                    await window.notificationManager.sendTestNotification();
                }
            } else {
                alert('Notification manager not loaded');
            }
        }

        async function testTaskNotification() {
            try {
                const response = await fetch('/api/send-push.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        title: 'Task Update Test',
                        body: 'Your task "Sample Task" status has been updated to "In Progress"',
                        data: {
                            task_id: 1,
                            url: '/task.php?id=1',
                            action: 'status_changed'
                        },
                        tag: 'test-notification'
                    }),
                    credentials: 'same-origin'
                });

                const result = await response.json();
                
                if (result.success) {
                    if (window.notificationManager) {
                        window.notificationManager.showNotification(`Test notification sent to ${result.sent_count} subscriptions`, 'success');
                    }
                } else {
                    if (window.notificationManager) {
                        window.notificationManager.showNotification('Test notification failed: ' + result.message, 'error');
                    }
                }
            } catch (error) {
                console.error('Error sending test notification:', error);
                if (window.notificationManager) {
                    window.notificationManager.showNotification('Error sending test notification', 'error');
                }
            }
        }

        function testOfflineMode() {
            if (window.notificationManager) {
                window.notificationManager.showNotification('Try disconnecting your internet to test offline functionality', 'info');
            }
            
            // Test offline capabilities
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.ready.then(registration => {
                    registration.sync.register('test-sync').then(() => {
                        console.log('Background sync registered for testing');
                    });
                });
            }
        }

        // PWA Install handling
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            document.getElementById('install-pwa').style.display = 'block';
        });

        document.getElementById('install-pwa').addEventListener('click', async () => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                console.log('User choice:', outcome);
                deferredPrompt = null;
                document.getElementById('install-pwa').style.display = 'none';
            }
        });

        // Enable notifications
        document.getElementById('enable-notifications').addEventListener('click', () => {
            if (window.notificationManager) {
                window.notificationManager.togglePushSubscription();
            }
        });
    </script>
</body>
</html>