<?php
/**
 * Debug email sending issues
 * This page shows what happens when email sending is attempted
 */

require_once 'config/constants.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Debug Information</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Email Configuration Debug</h1>
        
        <div class="card mt-4">
            <div class="card-header">
                <h3>Current Configuration</h3>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-3">From Email:</dt>
                    <dd class="col-sm-9"><code><?php echo EMAIL_FROM; ?></code></dd>
                    
                    <dt class="col-sm-3">From Name:</dt>
                    <dd class="col-sm-9"><?php echo EMAIL_FROM_NAME; ?></dd>
                    
                    <dt class="col-sm-3">API Token:</dt>
                    <dd class="col-sm-9"><code><?php echo substr(MAILERSEND_API_TOKEN, 0, 20); ?>...</code></dd>
                    
                    <dt class="col-sm-3">Template ID:</dt>
                    <dd class="col-sm-9"><code>z86org8yw5klew13</code></dd>
                </dl>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h3>Common Issues and Solutions</h3>
            </div>
            <div class="card-body">
                <div class="alert alert-danger">
                    <h5>Error: "The from.email domain must be verified"</h5>
                    <p>This means the email address <strong><?php echo EMAIL_FROM; ?></strong> is not verified in your MailerSend account.</p>
                </div>
                
                <h5>Solutions:</h5>
                <ol>
                    <li>
                        <strong>Verify your domain in MailerSend:</strong>
                        <ul>
                            <li>Log in to your MailerSend dashboard</li>
                            <li>Go to Domains section</li>
                            <li>Add and verify the domain "projecttracker.edu"</li>
                            <li>Follow the DNS verification steps</li>
                        </ul>
                    </li>
                    <li>
                        <strong>OR use a pre-verified email:</strong>
                        <ul>
                            <li>Check your MailerSend account for already verified domains</li>
                            <li>Update EMAIL_FROM in constants.php to use a verified email</li>
                            <li>For example: <code>define('EMAIL_FROM', 'info@yourdomain.com');</code></li>
                        </ul>
                    </li>
                </ol>
                
                <div class="alert alert-info mt-3">
                    <strong>Note:</strong> MailerSend requires domain verification to prevent spam. You cannot send emails from domains you don't own or haven't verified.
                </div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h3>Template Variables</h3>
            </div>
            <div class="card-body">
                <p>Your MailerSend template should include these variables:</p>
                <ul>
                    <li><code>{{customer.first_name}}</code> - User's first name</li>
                    <li><code>{{customer.token}}</code> - Reset token</li>
                    <li><code>{{customer.reset_url}}</code> - Full reset URL</li>
                </ul>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h3>Quick Actions</h3>
            </div>
            <div class="card-body">
                <a href="https://app.mailersend.com/domains" target="_blank" class="btn btn-primary">Go to MailerSend Domains</a>
                <a href="/forgot-password.php" class="btn btn-secondary">Test Password Reset</a>
            </div>
        </div>
    </div>
</body>
</html>