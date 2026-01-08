<?php
/**
 * Registration Page
 * 
 * User registration form
 */

// Load necessary files before any output
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/User.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: /dashboard');
    exit;
}

// Initialize variables
$first_name = '';
$last_name = '';
$email = '';
$errors = [];
$community_slug = $_GET['community'] ?? null;
$community = null;
$requires_skool = false;

// If community is specified, check if it requires skool.com registration
if ($community_slug) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT id, name, requires_skool_registration, skool_url FROM communities WHERE slug = ? AND is_active = 1");
    $stmt->execute([$community_slug]);
    $community = $stmt->fetch();
    
    if ($community) {
        $requires_skool = (bool)$community['requires_skool_registration'];
    }
}

// Handle registration form submission BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate input
    if (empty($first_name) || empty($last_name)) {
        $errors[] = 'First and last name are required.';
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email address is required.';
    }
    
    if (empty($password) || strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }
    
    // Check if email is allowed for communities requiring skool.com registration
    if ($community && $requires_skool && !empty($email)) {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT id FROM community_allowed_users WHERE community_id = ? AND email = ?");
        $stmt->execute([$community['id'], $email]);
        
        if (!$stmt->fetch()) {
            $errors[] = 'This email address is not authorized to register for this community. Please ensure you are registered on skool.com first.';
        }
    }
    
    if (empty($errors)) {
        $user = new User();
        $registration_data = [
            'email' => $email,
            'password' => $password,
            'first_name' => $first_name,
            'last_name' => $last_name
        ];
        
        // Determine which community to assign based on email
        $pdo = getDB();
        
        if ($community) {
            // If a specific community was selected, use it
            $registration_data['community_id'] = $community['id'];
        }
        // If no community selected, User.php will handle the assignment logic
        
        $result = $user->register($registration_data);
        
        if ($result['success']) {
            // Customize message based on community status
            $message = 'Registration successful!';
            
            if ($result['community_status'] === 'auto_approved') {
                $message .= ' You have been automatically approved for ' . htmlspecialchars($result['community_name']) . '.';
            } elseif ($result['community_status'] === 'pending_approval') {
                $message .= ' Your request to join ' . htmlspecialchars($result['community_name']) . ' is pending approval.';
            } elseif ($result['community_status'] === 'joined' && $result['community_name']) {
                $message .= ' You have been added to ' . htmlspecialchars($result['community_name']) . '.';
            }
            
            $message .= ' Please log in.';
            
            setFlashMessage('success', $message);
            header('Location: /login');
            exit;
        } else {
            $errors[] = $result['error'];
        }
    }
}

// NOW include header after all potential redirects
$page_title = 'Register';
require_once 'includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <img src="/assets/logo.png" alt="SkillikS Logo" class="mb-3" style="max-width: 150px; height: auto;">
                        <h1 class="h3 mb-3 fw-normal">Create Your Account</h1>
                        <?php if ($community): ?>
                            <p class="text-muted">Registering for <strong><?php echo htmlspecialchars($community['name']); ?></strong></p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $error): ?>
                                <div><?php echo htmlspecialchars($error); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="/register">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           placeholder="First Name" value="<?php echo htmlspecialchars($first_name); ?>" required>
                                    <label for="first_name">First Name</label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           placeholder="Last Name" value="<?php echo htmlspecialchars($last_name); ?>" required>
                                    <label for="last_name">Last Name</label>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($requires_skool): ?>
                            <div class="alert alert-info mb-3 registration-requirement">
                                <i class="bi bi-info-circle"></i> This community requires registration on skool.com before creating your account on SkillikS
                                <?php if (!empty($community['skool_url'])): ?>
                                    <br><a href="<?php echo htmlspecialchars($community['skool_url']); ?>" target="_blank" class="alert-link">Join Here <i class="bi bi-box-arrow-up-right"></i></a>
                                    <br><small>You will be able to register within 5 minutes of registering on skool.com</small>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="form-floating mb-3">
                            <input type="email" class="form-control" id="email" name="email" 
                                   placeholder="name@example.com" value="<?php echo htmlspecialchars($email); ?>" required>
                            <label for="email">Email address</label>
                        </div>
                        
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Password" required>
                            <label for="password">Password</label>
                            <div class="form-text">Must be at least 8 characters long</div>
                        </div>
                        
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                   placeholder="Confirm Password" required>
                            <label for="confirm_password">Confirm Password</label>
                        </div>
                        
                        <button class="w-100 btn btn-lg btn-primary mb-3" type="submit">Register</button>
                        
                        <p class="text-center">
                            Already have an account? <a href="/login">Sign in</a>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>