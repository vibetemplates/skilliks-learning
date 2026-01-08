<?php
/**
 * Login Page
 * 
 * Based on Bootstrap sign-in example
 */

require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: /dashboard');
    exit;
}

// Capture redirect parameters from URL
$redirect_action = $_GET['redirect'] ?? '';
$community_id = $_GET['community'] ?? '';

// Store redirect information in session if provided
if ($redirect_action === 'join' && $community_id) {
    // Get community slug for the redirect
    $db = getDB();
    $stmt = $db->prepare("SELECT slug FROM communities WHERE id = ? AND is_active = 1");
    $stmt->execute([$community_id]);
    $community = $stmt->fetch();
    
    if ($community) {
        $_SESSION['redirect_after_login'] = '/register?community=' . urlencode($community['slug']);
    }
}

// Remove server-side form handling - now handled by HTMX endpoint

// Get remembered email
$remembered_email = $_COOKIE['remember_user'] ?? '';

$page_title = 'Login';
require_once 'includes/header.php';
?>

<main class="form-signin text-center">
    <div x-data="loginForm()" x-init="init()">
        <?php
        // Build form action URL with redirect parameters
        $form_action = '/login';
        $query_params = [];
        if ($redirect_action) {
            $query_params['redirect'] = $redirect_action;
        }
        if ($community_id) {
            $query_params['community'] = $community_id;
        }
        if (!empty($query_params)) {
            $form_action .= '?' . http_build_query($query_params);
        }
        ?>
        <form hx-post="/htmx/login.php" 
              hx-target="#login-messages" 
              hx-swap="innerHTML"
              @submit="handleSubmit"
              class="needs-validation"
              novalidate>
            
            <img src="/assets/logo.png" alt="SkillikS Logo" class="mb-3" style="max-width: 150px; height: auto;">
            <h1 class="h3 mb-3 fw-normal">Sign in to SkillikS</h1>

            <!-- Message area for HTMX responses -->
            <div id="login-messages"></div>

            <div class="form-floating mb-3">
                <input type="email" 
                       class="form-control" 
                       id="email" 
                       name="email" 
                       placeholder="name@example.com" 
                       value="<?php echo htmlspecialchars($remembered_email); ?>" 
                       x-model="email"
                       @blur="validateEmail"
                       :class="{'is-invalid': emailError && emailTouched}"
                       required 
                       autofocus>
                <label for="email">Email address</label>
                <div class="invalid-feedback" x-show="emailError && emailTouched" x-text="emailError"></div>
            </div>
            
            <div class="form-floating mb-3 position-relative">
                <input :type="showPassword ? 'text' : 'password'" 
                       class="form-control" 
                       id="password" 
                       name="password" 
                       placeholder="Password" 
                       x-model="password"
                       @blur="validatePassword"
                       :class="{'is-invalid': passwordError && passwordTouched}"
                       required>
                <label for="password">Password</label>
                <button type="button" 
                        class="btn btn-sm position-absolute end-0 top-50 translate-middle-y me-2"
                        @click="showPassword = !showPassword"
                        style="z-index: 10;">
                    <i class="bi" :class="showPassword ? 'bi-eye-slash' : 'bi-eye'"></i>
                </button>
                <div class="invalid-feedback" x-show="passwordError && passwordTouched" x-text="passwordError"></div>
            </div>

            <div class="checkbox mb-3">
                <label>
                    <input type="checkbox" name="remember" value="1" x-model="remember" <?php echo $remembered_email ? 'checked' : ''; ?>> Remember me
                </label>
            </div>
            
            <button class="w-100 btn btn-lg btn-primary" 
                    type="submit"
                    :disabled="!isFormValid || isSubmitting"
                    x-text="isSubmitting ? 'Signing in...' : 'Sign in'">
                Sign in
            </button>
            
            <p class="mt-3 mb-3">
                <a href="/forgot-password.php">Forgot Password?</a>
            </p>
            
            <p class="mt-3 mb-3">
                Don't have an account? <a href="/register">Register</a>
            </p>
        </form>
    </div>
</main>

<script>
function loginForm() {
    return {
        email: '<?php echo htmlspecialchars($remembered_email); ?>',
        password: '',
        remember: <?php echo $remembered_email ? 'true' : 'false'; ?>,
        showPassword: false,
        emailError: '',
        passwordError: '',
        emailTouched: false,
        passwordTouched: false,
        isSubmitting: false,
        
        init() {
            // Set initial validation state if email is pre-filled
            if (this.email) {
                this.validateEmail();
            }
        },
        
        validateEmail() {
            this.emailTouched = true;
            if (!this.email) {
                this.emailError = 'Email is required';
            } else if (!this.email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                this.emailError = 'Please enter a valid email address';
            } else {
                this.emailError = '';
            }
        },
        
        validatePassword() {
            this.passwordTouched = true;
            if (!this.password) {
                this.passwordError = 'Password is required';
            } else {
                this.passwordError = '';
            }
        },
        
        get isFormValid() {
            return this.email && 
                   this.password && 
                   !this.emailError && 
                   !this.passwordError;
        },
        
        handleSubmit(event) {
            this.emailTouched = true;
            this.passwordTouched = true;
            this.validateEmail();
            this.validatePassword();
            
            if (!this.isFormValid) {
                event.preventDefault();
                event.stopPropagation();
            } else {
                this.isSubmitting = true;
            }
        }
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>