<?php
/**
 * Send test reset email
 */

require_once 'config/database.php';
require_once 'classes/EmailService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /check-reset-process.php');
    exit;
}

$email = $_POST['email'] ?? '';
$token = $_POST['token'] ?? '';
$firstName = $_POST['first_name'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Test Reset Email</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Sending Test Reset Email</h1>
        
        <div class="card mt-4">
            <div class="card-header">
                <h3>Email Send Results</h3>
            </div>
            <div class="card-body">
                <?php
                if (empty($email) || empty($token)) {
                    echo "<div class='alert alert-danger'>Missing required data</div>";
                } else {
                    try {
                        $emailService = new EmailService();
                        $result = $emailService->sendPasswordResetEmail($email, $firstName, $token);
                        
                        if ($result['success']) {
                            echo "<div class='alert alert-success'>";
                            echo "<h5>✓ Email sent successfully!</h5>";
                            echo "<p>Check your email at: " . htmlspecialchars($email) . "</p>";
                            echo "</div>";
                            
                            echo "<div class='alert alert-info'>";
                            echo "<h5>Email Details:</h5>";
                            echo "<p><strong>From:</strong> " . EMAIL_FROM_NAME . " &lt;" . EMAIL_FROM . "&gt;</p>";
                            echo "<p><strong>To:</strong> " . htmlspecialchars($firstName) . " &lt;" . htmlspecialchars($email) . "&gt;</p>";
                            echo "<p><strong>Subject:</strong> Reset Your Password - SkillikS</p>";
                            echo "<p><strong>Template ID:</strong> z86org8yw5klew13</p>";
                            echo "</div>";
                            
                            echo "<div class='alert alert-warning'>";
                            echo "<h5>Important Notes:</h5>";
                            echo "<ul>";
                            echo "<li>Check your spam/junk folder if you don't see the email</li>";
                            echo "<li>The email will come from: " . EMAIL_FROM . "</li>";
                            echo "<li>The reset link is valid for 1 day</li>";
                            echo "<li>Make sure your MailerSend template uses {{customer.reset_url}} for the link</li>";
                            echo "</ul>";
                            echo "</div>";
                            
                            $resetUrl = 'https://stage.skilliks.ai/reset-password.php?token=' . $token;
                            echo "<div class='card'>";
                            echo "<div class='card-header'>Direct Reset Link</div>";
                            echo "<div class='card-body'>";
                            echo "<p>If the email doesn't arrive, you can use this direct link:</p>";
                            echo "<p><a href='" . htmlspecialchars($resetUrl) . "' class='btn btn-primary' target='_blank'>Reset Password</a></p>";
                            echo "<p class='text-muted small'>" . htmlspecialchars($resetUrl) . "</p>";
                            echo "</div>";
                            echo "</div>";
                            
                        } else {
                            echo "<div class='alert alert-danger'>";
                            echo "<h5>✗ Failed to send email</h5>";
                            echo "<p>Error: " . htmlspecialchars($result['error'] ?? 'Unknown error') . "</p>";
                            echo "</div>";
                        }
                        
                    } catch (Exception $e) {
                        echo "<div class='alert alert-danger'>";
                        echo "<h5>Exception occurred:</h5>";
                        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
                        echo "</div>";
                    }
                }
                ?>
                
                <div class="mt-3">
                    <a href="/check-reset-process.php?email=<?php echo urlencode($email); ?>" class="btn btn-secondary">Back to Check Process</a>
                    <a href="/debug-reset-token.php?token=<?php echo urlencode($token); ?>" class="btn btn-info">Debug Token</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>