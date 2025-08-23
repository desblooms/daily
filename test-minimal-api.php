<?php
echo "<h1>Minimal API Test</h1>";

// Set up session
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['user_name'] = 'Test Admin';

echo "<p>Testing minimal API endpoint...</p>";

// Test data
$testData = [
    'action' => 'update_task',
    'task_id' => 1,
    'assigned_to' => 2
];

echo "<h3>Test Data:</h3>";
echo "<pre>" . json_encode($testData, JSON_PRETTY_PRINT) . "</pre>";

// Test using cURL
echo "<h3>Testing with cURL...</h3>";

$url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/api/tasks-minimal.php';
echo "<p>URL: $url</p>";

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($testData),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Cookie: ' . $_SERVER['HTTP_COOKIE'] ?? ''
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$error = curl_error($curl);
curl_close($curl);

if ($error) {
    echo "<p style='color: red;'>❌ cURL Error: " . htmlspecialchars($error) . "</p>";
} else {
    echo "<p style='color: green;'>✅ HTTP Code: $httpCode</p>";
    echo "<h3>Response:</h3>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    
    $data = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "<h3>Parsed Response:</h3>";
        echo "<pre>" . json_encode($data, JSON_PRETTY_PRINT) . "</pre>";
        
        if (isset($data['success'])) {
            if ($data['success']) {
                echo "<p style='color: green;'>✅ API call successful!</p>";
            } else {
                echo "<p style='color: red;'>❌ API error: " . htmlspecialchars($data['message']) . "</p>";
            }
        }
    } else {
        echo "<p style='color: orange;'>⚠️ Response is not valid JSON</p>";
    }
}

echo "<hr>";

// Also test using file_get_contents
echo "<h3>Testing with file_get_contents...</h3>";

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n" .
                   "Cookie: " . ($_SERVER['HTTP_COOKIE'] ?? '') . "\r\n",
        'content' => json_encode($testData)
    ]
]);

$result = @file_get_contents($url, false, $context);

if ($result === false) {
    echo "<p style='color: red;'>❌ file_get_contents failed</p>";
    $error = error_get_last();
    if ($error) {
        echo "<p>Last error: " . htmlspecialchars($error['message']) . "</p>";
    }
} else {
    echo "<p style='color: green;'>✅ file_get_contents succeeded</p>";
    echo "<pre>" . htmlspecialchars($result) . "</pre>";
}

echo "<hr>";
echo "<p><a href='test-components.php'>Test Components</a></p>";
echo "<p><a href='api/tasks-minimal.php'>Direct API Access</a></p>";
?>