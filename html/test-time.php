<?php
date_default_timezone_set('UTC');

echo "Current time tests:\n";
echo "1. PHP time(): " . date('Y-m-d H:i:s', time()) . "\n";
echo "2. PHP date(): " . date('Y-m-d H:i:s') . "\n";
echo "3. PHP strtotime('+1 hour'): " . date('Y-m-d H:i:s', strtotime('+1 hour')) . "\n";

require_once 'config/database.php';
$db = getDB();

$stmt = $db->query("SELECT NOW() as now_time, DATE_ADD(NOW(), INTERVAL 1 HOUR) as future_time");
$result = $stmt->fetch();

echo "\n4. MySQL NOW(): " . $result['now_time'] . "\n";
echo "5. MySQL +1 hour: " . $result['future_time'] . "\n";

// Test what the User class would generate
echo "\n6. What User class generates:\n";
$expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
echo "   Expires: $expires\n";

// Test storing and retrieving
$testToken = bin2hex(random_bytes(16));
$stmt = $db->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = 1");
$stmt->execute([$testToken, $expires]);

$stmt = $db->prepare("SELECT reset_token_expires FROM users WHERE id = 1");
$stmt->execute();
$stored = $stmt->fetch();

echo "7. Stored in DB: " . $stored['reset_token_expires'] . "\n";

// Test validation query
$stmt = $db->prepare("SELECT reset_token_expires > NOW() as is_valid FROM users WHERE id = 1");
$stmt->execute();
$valid = $stmt->fetch();

echo "8. Is valid (expires > NOW()): " . ($valid['is_valid'] ? 'YES' : 'NO') . "\n";