<?php
/**
 * Comprehensive Password Reset Debugging
 */

require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/User.php';
require_once 'classes/EmailService.php';

$action = $_GET['action'] ?? 'start';
$email = $_POST['email'] ?? $_GET['email'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Password Reset Process Debugger</h1>
        
        <?php if ($action === 'start'): ?>
        <div class="card">
            <div class="card-header">
                <h3>Step 1: Enter Email</h3>
            </div>
            <div class="card-body">
                <form method="post" action="?action=generate">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Start Debug Process</button>
                </form>
            </div>
        </div>
        
        <?php elseif ($action === 'generate'): ?>
        <div class="card">
            <div class="card-header">
                <h3>Step 2: Generate Token</h3>
            </div>
            <div class="card-body">
                <?php
                echo "<h5>Processing email: " . htmlspecialchars($email) . "</h5>";
                
                try {
                    $db = getDB();
                    
                    // Check if user exists
                    echo "<p>1. Checking if user exists...</p>";
                    $stmt = $db->prepare("SELECT id, email, first_name FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    $userData = $stmt->fetch();
                    
                    if (!$userData) {
                        echo "<div class='alert alert-danger'>User not found!</div>";
                        exit;
                    }
                    
                    echo "<div class='alert alert-success'>✓ User found: ID=" . $userData['id'] . "</div>";
                    
                    // Generate token
                    echo "<p>2. Generating reset token...</p>";
                    $user = new User();
                    $token = $user->generateResetToken($email);
                    
                    if (!$token) {
                        echo "<div class='alert alert-danger'>Failed to generate token!</div>";
                        exit;
                    }
                    
                    echo "<div class='alert alert-success'>✓ Token generated: <code>" . htmlspecialchars($token) . "</code></div>";
                    
                    // Verify token in database
                    echo "<p>3. Verifying token in database...</p>";
                    $stmt = $db->prepare("SELECT reset_token, reset_token_expires FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    $tokenData = $stmt->fetch();
                    
                    echo "<div class='alert alert-info'>";
                    echo "Token in DB: <code>" . htmlspecialchars($tokenData['reset_token']) . "</code><br>";
                    echo "Expires: " . htmlspecialchars($tokenData['reset_token_expires']) . "<br>";
                    echo "Match: " . ($token === $tokenData['reset_token'] ? '✓ Yes' : '✗ No');
                    echo "</div>";
                    
                    // Test reset URL
                    echo "<p>4. Testing reset URL...</p>";
                    $resetUrl = 'https://stage.skilliks.ai/reset-password.php?token=' . urlencode($token);
                    echo "<div class='alert alert-info'>";
                    echo "Reset URL: <a href='" . htmlspecialchars($resetUrl) . "' target='_blank'>" . htmlspecialchars($resetUrl) . "</a><br>";
                    echo "URL Length: " . strlen($resetUrl) . " characters";
                    echo "</div>";
                    
                    // Test direct database query with token
                    echo "<p>5. Testing token validation query...</p>";
                    $stmt = $db->prepare("SELECT id, email FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
                    $stmt->execute([$token]);
                    $validationResult = $stmt->fetch();
                    
                    if ($validationResult) {
                        echo "<div class='alert alert-success'>✓ Token validation query successful!</div>";
                    } else {
                        echo "<div class='alert alert-danger'>✗ Token validation query failed!</div>";
                        
                        // Check without time constraint
                        $stmt = $db->prepare("SELECT id, email, reset_token_expires FROM users WHERE reset_token = ?");
                        $stmt->execute([$token]);
                        $expiredCheck = $stmt->fetch();
                        
                        if ($expiredCheck) {
                            echo "<div class='alert alert-warning'>Token exists but may be expired. Expires: " . htmlspecialchars($expiredCheck['reset_token_expires']) . "</div>";
                        }
                    }
                    
                    ?>
                    <form method="post" action="?action=send">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                        <input type="hidden" name="first_name" value="<?php echo htmlspecialchars($userData['first_name']); ?>">
                        <button type="submit" class="btn btn-success">Continue to Send Email</button>
                    </form>
                    <?php
                    
                } catch (Exception $e) {
                    echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
                    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
                }
                ?>
            </div>
        </div>
        
        <?php elseif ($action === 'send'): ?>
        <div class="card">
            <div class="card-header">
                <h3>Step 3: Send Email</h3>
            </div>
            <div class="card-body">
                <?php
                $token = $_POST['token'] ?? '';
                $firstName = $_POST['first_name'] ?? '';
                
                echo "<p>Sending email to: " . htmlspecialchars($email) . "</p>";
                
                try {
                    $emailService = new EmailService();
                    $result = $emailService->sendPasswordResetEmail($email, $firstName, $token);
                    
                    if ($result['success']) {
                        echo "<div class='alert alert-success'>✓ Email sent successfully!</div>";
                        
                        $resetUrl = 'https://stage.skilliks.ai/reset-password.php?token=' . urlencode($token);
                        echo "<div class='alert alert-info'>";
                        echo "<h5>Next Steps:</h5>";
                        echo "<ol>";
                        echo "<li>Check your email at " . htmlspecialchars($email) . "</li>";
                        echo "<li>Click the reset link in the email</li>";
                        echo "<li>Or use this direct link: <a href='" . htmlspecialchars($resetUrl) . "' target='_blank' class='btn btn-sm btn-primary'>Test Reset Link</a></li>";
                        echo "</ol>";
                        echo "</div>";
                        
                        echo "<h5>Test Token Validation:</h5>";
                        echo "<form method='get' action='?action=validate'>";
                        echo "<input type='hidden' name='action' value='validate'>";
                        echo "<div class='mb-3'>";
                        echo "<label class='form-label'>Token to validate:</label>";
                        echo "<input type='text' name='token' class='form-control' value='" . htmlspecialchars($token) . "'>";
                        echo "</div>";
                        echo "<button type='submit' class='btn btn-warning'>Validate Token</button>";
                        echo "</form>";
                        
                    } else {
                        echo "<div class='alert alert-danger'>✗ Failed to send email: " . htmlspecialchars($result['error'] ?? 'Unknown error') . "</div>";
                    }
                    
                } catch (Exception $e) {
                    echo "<div class='alert alert-danger'>Exception: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
                ?>
            </div>
        </div>
        
        <?php elseif ($action === 'validate'): ?>
        <div class="card">
            <div class="card-header">
                <h3>Token Validation Test</h3>
            </div>
            <div class="card-body">
                <?php
                $token = $_GET['token'] ?? '';
                
                if (empty($token)) {
                    echo "<div class='alert alert-danger'>No token provided</div>";
                } else {
                    echo "<p>Testing token: <code>" . htmlspecialchars($token) . "</code></p>";
                    echo "<p>Token length: " . strlen($token) . " characters</p>";
                    
                    try {
                        $db = getDB();
                        
                        // Test 1: Basic token lookup
                        echo "<h5>Test 1: Basic Token Lookup</h5>";
                        $stmt = $db->prepare("SELECT id, email, reset_token, reset_token_expires FROM users WHERE reset_token = ?");
                        $stmt->execute([$token]);
                        $result = $stmt->fetch();
                        
                        if ($result) {
                            echo "<div class='alert alert-success'>✓ Token found in database</div>";
                            echo "<ul>";
                            echo "<li>User ID: " . $result['id'] . "</li>";
                            echo "<li>Email: " . htmlspecialchars($result['email']) . "</li>";
                            echo "<li>Expires: " . htmlspecialchars($result['reset_token_expires']) . "</li>";
                            echo "</ul>";
                        } else {
                            echo "<div class='alert alert-danger'>✗ Token not found in database</div>";
                        }
                        
                        // Test 2: Time-constrained lookup (same as reset-password.php)
                        echo "<h5>Test 2: Time-Constrained Lookup</h5>";
                        $stmt = $db->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
                        $stmt->execute([$token]);
                        $result = $stmt->fetch();
                        
                        if ($result) {
                            echo "<div class='alert alert-success'>✓ Token is valid and not expired</div>";
                        } else {
                            echo "<div class='alert alert-danger'>✗ Token is either invalid or expired</div>";
                            
                            // Check current time vs expiry
                            $stmt = $db->prepare("SELECT reset_token_expires FROM users WHERE reset_token = ?");
                            $stmt->execute([$token]);
                            $expiry = $stmt->fetch();
                            
                            if ($expiry) {
                                echo "<p>Token expires at: " . htmlspecialchars($expiry['reset_token_expires']) . "</p>";
                                echo "<p>Current server time: " . date('Y-m-d H:i:s') . "</p>";
                                
                                $expiryTime = strtotime($expiry['reset_token_expires']);
                                $currentTime = time();
                                
                                if ($expiryTime < $currentTime) {
                                    echo "<div class='alert alert-warning'>Token has expired " . round(($currentTime - $expiryTime) / 60) . " minutes ago</div>";
                                } else {
                                    echo "<div class='alert alert-info'>Token should be valid for " . round(($expiryTime - $currentTime) / 60) . " more minutes</div>";
                                }
                            }
                        }
                        
                        // Test 3: What reset-password.php would see
                        echo "<h5>Test 3: Reset Page Validation</h5>";
                        $resetUrl = 'https://stage.skilliks.ai/reset-password.php?token=' . urlencode($token);
                        echo "<p>URL that would be used: <code>" . htmlspecialchars($resetUrl) . "</code></p>";
                        echo "<a href='" . htmlspecialchars($resetUrl) . "' target='_blank' class='btn btn-primary'>Test Reset Page</a>";
                        
                    } catch (Exception $e) {
                        echo "<div class='alert alert-danger'>Database error: " . htmlspecialchars($e->getMessage()) . "</div>";
                    }
                }
                ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="mt-3">
            <a href="?action=start" class="btn btn-secondary">Start Over</a>
            <a href="/forgot-password.php" class="btn btn-info">Normal Password Reset</a>
        </div>
    </div>
</body>
</html>