<?php
/**
 * Check timezone configuration
 */

require_once 'config/database.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Timezone Check</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Timezone Configuration Check</h1>
        
        <div class="card mt-3">
            <div class="card-body">
                <h5>PHP Configuration</h5>
                <p>PHP Timezone: <code><?php echo date_default_timezone_get(); ?></code></p>
                <p>Current PHP Time: <code><?php echo date('Y-m-d H:i:s'); ?></code></p>
                
                <h5 class="mt-3">MySQL Configuration</h5>
                <?php
                try {
                    $db = getDB();
                    
                    // Get MySQL timezone
                    $stmt = $db->query("SELECT @@global.time_zone, @@session.time_zone");
                    $tz = $stmt->fetch();
                    echo "<p>MySQL Global Timezone: <code>" . $tz['@@global.time_zone'] . "</code></p>";
                    echo "<p>MySQL Session Timezone: <code>" . $tz['@@session.time_zone'] . "</code></p>";
                    
                    // Get MySQL current time
                    $stmt = $db->query("SELECT NOW() as mysql_time");
                    $time = $stmt->fetch();
                    echo "<p>Current MySQL Time: <code>" . $time['mysql_time'] . "</code></p>";
                    
                    // Compare times
                    $phpTime = new DateTime();
                    $mysqlTime = new DateTime($time['mysql_time']);
                    $diff = $phpTime->diff($mysqlTime);
                    
                    if ($diff->h > 0 || $diff->i > 1) {
                        echo "<div class='alert alert-warning'>⚠️ Time difference detected: " . $diff->format('%h hours %i minutes') . "</div>";
                    } else {
                        echo "<div class='alert alert-success'>✓ PHP and MySQL times are in sync</div>";
                    }
                    
                } catch (Exception $e) {
                    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
                }
                ?>
                
                <h5 class="mt-3">Token Expiry Test</h5>
                <?php
                try {
                    // Create a test token that expires in 1 hour
                    $futureTime = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    echo "<p>Token would expire at: <code>$futureTime</code></p>";
                    
                    // Test the query
                    $stmt = $db->prepare("SELECT ? > NOW() as is_future");
                    $stmt->execute([$futureTime]);
                    $result = $stmt->fetch();
                    
                    if ($result['is_future']) {
                        echo "<div class='alert alert-success'>✓ Future time comparison works correctly</div>";
                    } else {
                        echo "<div class='alert alert-danger'>✗ Time comparison issue detected</div>";
                    }
                    
                } catch (Exception $e) {
                    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
                }
                ?>
            </div>
        </div>
    </div>
</body>
</html>