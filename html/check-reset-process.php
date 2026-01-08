<?php
/**
 * Check the complete password reset process
 */

require_once 'config/database.php';
require_once 'classes/User.php';
require_once 'classes/EmailService.php';

$email = $_GET['email'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Reset Process</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Password Reset Process Check</h1>
        
        <div class="card mt-4">
            <div class="card-header">
                <h3>Test Password Reset</h3>
            </div>
            <div class="card-body">
                <form method="get">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Test Reset Process</button>
                </form>
            </div>
        </div>

        <?php if (!empty($email)): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h3>Process Results</h3>
            </div>
            <div class="card-body">
                <?php
                try {
                    $db = getDB();
                    $user = new User($db);
                    
                    // Step 1: Check if user exists
                    echo "<h5>Step 1: Check User Exists</h5>";
                    $stmt = $db->prepare("SELECT id, email, first_name FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    $userData = $stmt->fetch();
                    
                    if ($userData) {
                        echo "<div class='alert alert-success'>✓ User found: " . htmlspecialchars($userData['email']) . "</div>";
                        
                        // Step 2: Generate token
                        echo "<h5>Step 2: Generate Reset Token</h5>";
                        $token = $user->generateResetToken($email);
                        
                        if ($token) {
                            echo "<div class='alert alert-success'>✓ Token generated: <code>" . htmlspecialchars($token) . "</code></div>";
                            
                            // Step 3: Verify token in database
                            echo "<h5>Step 3: Verify Token in Database</h5>";
                            $stmt2 = $db->prepare("SELECT reset_token, reset_token_expires FROM users WHERE id = ?");
                            $stmt2->execute([$userData['id']]);
                            $tokenData = $stmt2->fetch();
                            
                            echo "<p>Token in DB: <code>" . htmlspecialchars($tokenData['reset_token']) . "</code></p>";
                            echo "<p>Expires at: " . htmlspecialchars($tokenData['reset_token_expires']) . "</p>";
                            echo "<p>Tokens match: " . ($token === $tokenData['reset_token'] ? '✓ Yes' : '✗ No') . "</p>";
                            
                            // Step 4: Generate URLs
                            echo "<h5>Step 4: Reset URLs</h5>";
                            $resetUrl = 'https://stage.skilliks.ai/reset-password.php?token=' . $token;
                            $debugUrl = 'https://stage.skilliks.ai/debug-reset-token.php?token=' . $token;
                            
                            echo "<p>Reset URL: <a href='" . htmlspecialchars($resetUrl) . "' target='_blank'>" . htmlspecialchars($resetUrl) . "</a></p>";
                            echo "<p>Debug URL: <a href='" . htmlspecialchars($debugUrl) . "' target='_blank'>" . htmlspecialchars($debugUrl) . "</a></p>";
                            
                            // Step 5: Test email sending
                            echo "<h5>Step 5: Send Test Email</h5>";
                            echo "<form method='post' action='send-test-reset.php'>";
                            echo "<input type='hidden' name='email' value='" . htmlspecialchars($email) . "'>";
                            echo "<input type='hidden' name='token' value='" . htmlspecialchars($token) . "'>";
                            echo "<input type='hidden' name='first_name' value='" . htmlspecialchars($userData['first_name']) . "'>";
                            echo "<button type='submit' class='btn btn-success'>Send Test Reset Email</button>";
                            echo "</form>";
                            
                        } else {
                            echo "<div class='alert alert-danger'>✗ Failed to generate token</div>";
                        }
                        
                    } else {
                        echo "<div class='alert alert-warning'>No user found with email: " . htmlspecialchars($email) . "</div>";
                    }
                    
                } catch (Exception $e) {
                    echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
                ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>