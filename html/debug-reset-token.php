<?php
/**
 * Debug reset token issues
 */

require_once 'config/database.php';

$token = $_GET['token'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Reset Token</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Reset Token Debug</h1>
        
        <div class="card mt-4">
            <div class="card-header">
                <h3>Token Information</h3>
            </div>
            <div class="card-body">
                <p><strong>Token from URL:</strong> <code><?php echo htmlspecialchars($token); ?></code></p>
                <p><strong>Token length:</strong> <?php echo strlen($token); ?> characters</p>
            </div>
        </div>

        <?php if (!empty($token)): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h3>Database Check</h3>
            </div>
            <div class="card-body">
                <?php
                try {
                    $db = getDB();
                    
                    // Check if token exists
                    $stmt = $db->prepare("SELECT id, email, first_name, reset_token, reset_token_expires, 
                                         CASE 
                                             WHEN reset_token_expires > NOW() THEN 'Valid'
                                             ELSE 'Expired'
                                         END as status
                                         FROM users 
                                         WHERE reset_token = ?");
                    $stmt->execute([$token]);
                    $user = $stmt->fetch();
                    
                    if ($user) {
                        echo "<div class='alert alert-info'>";
                        echo "<h5>Token found in database!</h5>";
                        echo "<p>User: " . htmlspecialchars($user['email']) . "</p>";
                        echo "<p>Name: " . htmlspecialchars($user['first_name']) . "</p>";
                        echo "<p>Token expires: " . htmlspecialchars($user['reset_token_expires']) . "</p>";
                        echo "<p>Status: <strong>" . $user['status'] . "</strong></p>";
                        echo "</div>";
                        
                        if ($user['status'] === 'Expired') {
                            echo "<div class='alert alert-warning'>The token has expired. Request a new password reset.</div>";
                        }
                    } else {
                        echo "<div class='alert alert-danger'>";
                        echo "<h5>Token not found in database!</h5>";
                        echo "</div>";
                        
                        // Let's check if there are any tokens at all
                        $stmt2 = $db->prepare("SELECT COUNT(*) as count FROM users WHERE reset_token IS NOT NULL");
                        $stmt2->execute();
                        $count = $stmt2->fetch();
                        
                        echo "<p>Total users with reset tokens: " . $count['count'] . "</p>";
                        
                        // Show recent reset tokens (first 10 chars only for security)
                        $stmt3 = $db->prepare("SELECT email, 
                                              SUBSTRING(reset_token, 1, 10) as token_start,
                                              reset_token_expires,
                                              CASE 
                                                  WHEN reset_token_expires > NOW() THEN 'Valid'
                                                  ELSE 'Expired'
                                              END as status
                                              FROM users 
                                              WHERE reset_token IS NOT NULL 
                                              ORDER BY reset_token_expires DESC 
                                              LIMIT 5");
                        $stmt3->execute();
                        $recentTokens = $stmt3->fetchAll();
                        
                        if ($recentTokens) {
                            echo "<h5 class='mt-3'>Recent reset tokens (first 10 chars):</h5>";
                            echo "<table class='table table-sm'>";
                            echo "<thead><tr><th>Email</th><th>Token Start</th><th>Expires</th><th>Status</th></tr></thead>";
                            echo "<tbody>";
                            foreach ($recentTokens as $rt) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($rt['email']) . "</td>";
                                echo "<td><code>" . htmlspecialchars($rt['token_start']) . "...</code></td>";
                                echo "<td>" . htmlspecialchars($rt['reset_token_expires']) . "</td>";
                                echo "<td>" . htmlspecialchars($rt['status']) . "</td>";
                                echo "</tr>";
                            }
                            echo "</tbody></table>";
                        }
                    }
                    
                } catch (Exception $e) {
                    echo "<div class='alert alert-danger'>Database error: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
                ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card mt-4">
            <div class="card-header">
                <h3>Troubleshooting Steps</h3>
            </div>
            <div class="card-body">
                <ol>
                    <li>Make sure you clicked the link from the most recent password reset email</li>
                    <li>Check that the entire URL was copied (sometimes email clients truncate long URLs)</li>
                    <li>Verify the token hasn't expired (1 day limit)</li>
                    <li>Try requesting a new password reset</li>
                </ol>
                
                <div class="mt-3">
                    <a href="/forgot-password.php" class="btn btn-primary">Request New Password Reset</a>
                    <a href="/login" class="btn btn-secondary">Back to Login</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>