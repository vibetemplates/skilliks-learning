<?php
/**
 * Forgot Password Page
 * 
 * Allows users to request a password reset link
 */

require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/User.php';
require_once 'classes/EmailService.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: /dashboard');
    exit;
}

$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    
    if (empty($email)) {
        setFlashMessage('error', 'Please enter your email address.');
    } else {
        $db = getDB();
        $user = new User();
        
        // Check if user exists
        $stmt = $db->prepare("SELECT id, email, first_name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $userData = $stmt->fetch();
        
        if ($userData) {
            // Generate reset token using the User class method
            $token = $user->generateResetToken($email);
            
            if ($token) {
                // Send email
                try {
                    $emailService = new EmailService();
                    $result = $emailService->sendPasswordResetEmail($userData['email'], $userData['first_name'], $token);
                    
                    if ($result['success']) {
                        $success = true;
                        setFlashMessage('success', 'If an account exists with that email, you will receive password reset instructions.');
                    } else {
                        error_log('Failed to send password reset email: ' . ($result['error'] ?? 'Unknown error'));
                        // Still show success message for security
                        $success = true;
                        setFlashMessage('success', 'If an account exists with that email, you will receive password reset instructions.');
                    }
                } catch (Exception $e) {
                    error_log('Email service error: ' . $e->getMessage());
                    // Still show success message for security
                    $success = true;
                    setFlashMessage('success', 'If an account exists with that email, you will receive password reset instructions.');
                }
            } else {
                setFlashMessage('error', 'An error occurred. Please try again.');
            }
        } else {
            // Don't reveal whether email exists or not for security
            $success = true;
            setFlashMessage('success', 'If an account exists with that email, you will receive password reset instructions.');
        }
    }
}

$page_title = 'Forgot Password';
require_once 'includes/header.php';
?>

<main class="form-signin text-center">
    <form method="POST" action="/forgot-password.php" id="forgot-password-form">
        <img src="/assets/logo.png" alt="SkillikS Logo" class="mb-3" style="max-width: 150px; height: auto;">
        <h1 class="h3 mb-3 fw-normal">Reset your password</h1>
        
        <?php if (!$success): ?>
        <p class="text-muted mb-3">Enter your email address and we'll send you a link to reset your password.</p>

        <div class="form-floating mb-3">
            <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required autofocus>
            <label for="email">Email address</label>
        </div>
        
        <button class="w-100 btn btn-lg btn-primary" type="submit">Send Reset Link</button>
        <?php endif; ?>
        
        <p class="mt-3 mb-3">
            Remember your password? <a href="/login">Sign in</a>
        </p>
    </form>
</main>

<?php require_once 'includes/footer.php'; ?>