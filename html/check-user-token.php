<?php
require_once 'config/database.php';

$email = $_GET['email'] ?? 'edward.honour@kineticseas.com';

$db = getDB();

echo "Checking token for email: $email\n\n";

$stmt = $db->prepare("SELECT id, email, reset_token, reset_token_expires, 
                     NOW() as now_time,
                     (reset_token_expires > NOW()) as is_valid,
                     TIMESTAMPDIFF(MINUTE, NOW(), reset_token_expires) as minutes_remaining
                     FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    echo "User found:\n";
    echo "- ID: " . $user['id'] . "\n";
    echo "- Email: " . $user['email'] . "\n";
    echo "- Token: " . ($user['reset_token'] ?: 'NULL') . "\n";
    echo "- Expires: " . ($user['reset_token_expires'] ?: 'NULL') . "\n";
    echo "- Current time: " . $user['now_time'] . "\n";
    echo "- Is valid: " . ($user['is_valid'] ? 'YES' : 'NO') . "\n";
    echo "- Minutes remaining: " . ($user['minutes_remaining'] ?: '0') . "\n";
    
    if ($user['reset_token']) {
        echo "\nDirect validation test:\n";
        $token = $user['reset_token'];
        
        // Test the exact query used in reset-password.php
        $stmt2 = $db->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
        $stmt2->execute([$token]);
        $result = $stmt2->fetch();
        
        echo "Query: SELECT id FROM users WHERE reset_token = ? AND reset_token_expires > NOW()\n";
        echo "Token: $token\n";
        echo "Result: " . ($result ? "FOUND (id=" . $result['id'] . ")" : "NOT FOUND") . "\n";
        
        // Generate fresh token
        echo "\nGenerating fresh token...\n";
        $newToken = bin2hex(random_bytes(32));
        $newExpires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $stmt3 = $db->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
        $stmt3->execute([$newToken, $newExpires, $user['id']]);
        
        echo "New token: $newToken\n";
        echo "New expires: $newExpires\n";
        
        // Test new token
        $stmt4 = $db->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
        $stmt4->execute([$newToken]);
        $newResult = $stmt4->fetch();
        
        echo "New token validation: " . ($newResult ? "SUCCESS" : "FAILED") . "\n";
        
        if ($newResult) {
            $resetUrl = 'https://stage.skilliks.ai/reset-password.php?token=' . $newToken;
            echo "\nUse this URL to reset password:\n$resetUrl\n";
        }
    }
} else {
    echo "User not found!\n";
}