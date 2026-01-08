<?php
/**
 * Reset Password Page
 * 
 * Allows users to reset their password using a valid token
 */

require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/User.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: /dashboard');
    exit;
}

$token = $_GET['token'] ?? '';
$validToken = false;
$success = false;

// Validate token
if (!empty($token)) {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if ($user) {
        $validToken = true;
    }
}

// Handle password reset submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($confirmPassword)) {
        setFlashMessage('error', 'Please fill in all fields.');
    } elseif ($password !== $confirmPassword) {
        setFlashMessage('error', 'Passwords do not match.');
    } elseif (strlen($password) < 8) {
        setFlashMessage('error', 'Password must be at least 8 characters long.');
    } else {
        $db = getDB();
        $user = new User();
        
        if ($user->resetPassword($token, $password)) {
            $success = true;
            setFlashMessage('success', 'Your password has been reset successfully. You can now log in with your new password.');
        } else {
            setFlashMessage('error', 'Invalid or expired reset token. Please request a new password reset.');
        }
    }
}

$page_title = 'Reset Password';
require_once 'includes/header.php';
?>

<main class="form-signin text-center">
    <?php if (!$validToken && !$success): ?>
        <form>
            <img src="/assets/logo.png" alt="SkillikS Logo" class="mb-3" style="max-width: 150px; height: auto;">
            <h1 class="h3 mb-3 fw-normal">Invalid Reset Link</h1>
            
            <div class="alert alert-danger" role="alert">
                <p>This password reset link is invalid or has expired.</p>
                <p>Password reset links are valid for 1 day after being requested.</p>
            </div>
            
            <p class="mt-3">
                <a href="/forgot-password.php">Request a new password reset link</a>
            </p>
            
            <p class="mt-3 mb-3">
                <a href="/login">Back to login</a>
            </p>
        </form>
    <?php elseif ($success): ?>
        <form>
            <img src="/assets/logo.png" alt="SkillikS Logo" class="mb-3" style="max-width: 150px; height: auto;">
            <h1 class="h3 mb-3 fw-normal">Password Reset Successful</h1>
            
            <div class="alert alert-success" role="alert" id="success-message">
                Your password has been reset successfully!
            </div>
            
            <a href="/login" class="w-100 btn btn-lg btn-primary">Go to Login</a>
        </form>
    <?php else: ?>
        <form method="POST" action="/reset-password.php?token=<?php echo htmlspecialchars($token); ?>" id="reset-password-form">
            <img src="/assets/logo.png" alt="SkillikS Logo" class="mb-3" style="max-width: 150px; height: auto;">
            <h1 class="h3 mb-3 fw-normal">Reset your password</h1>
            
            <p class="text-muted mb-3">Enter your new password below.</p>
            
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

            <div class="form-floating mb-3">
                <input type="password" class="form-control" id="password" name="password" placeholder="New Password" required autofocus minlength="8">
                <label for="password">New Password</label>
                <div class="form-text">Must be at least 8 characters long</div>
            </div>
            
            <div class="form-floating mb-3">
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required minlength="8">
                <label for="confirm_password">Confirm Password</label>
            </div>
            
            <button class="w-100 btn btn-lg btn-primary" type="submit">Reset Password</button>
            
            <p class="mt-3 mb-3">
                <a href="/login">Back to login</a>
            </p>
        </form>
    <?php endif; ?>
</main>

<script>
// Add password match validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('reset-password-form');
    if (form) {
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        
        function validatePassword() {
            if (password.value != confirmPassword.value) {
                confirmPassword.setCustomValidity("Passwords don't match");
            } else {
                confirmPassword.setCustomValidity('');
            }
        }
        
        password.addEventListener('change', validatePassword);
        confirmPassword.addEventListener('input', validatePassword);
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>