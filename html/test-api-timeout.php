<?php
/**
 * Simple test to check API timeout behavior
 */

// Set execution time limit
set_time_limit(60);

// Get test parameters
$apiUrl = $_GET['url'] ?? 'http://localhost/api/run-coder';
$apiKey = $_GET['key'] ?? 'test-key';
$timeout = $_GET['timeout'] ?? 10;

?>
<!DOCTYPE html>
<html>
<head>
    <title>API Timeout Test</title>
    <style>
        body { font-family: monospace; padding: 20px; }
        .test { margin: 20px 0; padding: 10px; border: 1px solid #ccc; }
        .success { background: #e8f5e9; }
        .error { background: #ffebee; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>API Timeout Test</h1>
    
    <form method="get">
        <label>API URL: <input type="text" name="url" value="<?= htmlspecialchars($apiUrl) ?>" size="50"></label><br>
        <label>API Key: <input type="text" name="key" value="<?= htmlspecialchars($apiKey) ?>" size="30"></label><br>
        <label>Timeout (seconds): <input type="number" name="timeout" value="<?= $timeout ?>" min="1" max="300"></label><br>
        <button type="submit">Run Test</button>
    </form>
    
    <?php if ($_GET): ?>
    
    <div class="test">
        <h2>Test 1: Basic Connectivity (<?= $timeout ?>s timeout)</h2>
        <?php
        $startTime = microtime(true);
        
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['prompt' => 'test connectivity']));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-API-Key: ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        
        $duration = round(microtime(true) - $startTime, 2);
        
        $class = $error ? 'error' : 'success';
        ?>
        
        <div class="<?= $class ?>">
            <p>Duration: <?= $duration ?>s</p>
            <p>HTTP Code: <?= $httpCode ?></p>
            <?php if ($error): ?>
                <p>Error: <?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            
            <details>
                <summary>Response</summary>
                <pre><?= htmlspecialchars($response ?: '(empty)') ?></pre>
            </details>
            
            <details>
                <summary>Connection Info</summary>
                <pre><?= htmlspecialchars(print_r($info, true)) ?></pre>
            </details>
        </div>
    </div>
    
    <div class="test">
        <h2>Test 2: Async Request Simulation</h2>
        <?php
        // Test if the API supports async/background processing
        $testPrompt = "On templates.php please change the word Marketplace to Market in the hero section";
        
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['prompt' => $testPrompt]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-API-Key: ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Short timeout to test async
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $startTime = microtime(true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $duration = round(microtime(true) - $startTime, 2);
        ?>
        
        <div class="<?= ($httpCode >= 200 && $httpCode < 300) ? 'success' : 'error' ?>">
            <p>Duration: <?= $duration ?>s</p>
            <p>HTTP Code: <?= $httpCode ?></p>
            <?php if ($error): ?>
                <p>Error: <?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            
            <p>Expected behavior: API should return quickly with process info (pid, tempFile)</p>
            
            <details>
                <summary>Response</summary>
                <pre><?= htmlspecialchars($response ?: '(empty)') ?></pre>
            </details>
            
            <?php
            if ($response) {
                $data = json_decode($response, true);
                if ($data && isset($data['pid'])) {
                    echo '<p style="color: green;">✓ Async processing detected (PID: ' . $data['pid'] . ')</p>';
                } else {
                    echo '<p style="color: orange;">⚠ No async process info in response</p>';
                }
            }
            ?>
        </div>
    </div>
    
    <div class="test">
        <h2>Test 3: Check Apache/PHP Configuration</h2>
        <pre>
Max Execution Time: <?= ini_get('max_execution_time') ?>s
Memory Limit: <?= ini_get('memory_limit') ?>
Post Max Size: <?= ini_get('post_max_size') ?>
Default Socket Timeout: <?= ini_get('default_socket_timeout') ?>s
        </pre>
    </div>
    
    <?php endif; ?>
</body>
</html>