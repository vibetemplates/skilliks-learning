<?php
require_once 'config/database.php';
require_once 'config/constants.php';
require_once 'config/functions.php';
require_once 'includes/session.php';
requireLogin();

$db = getDB();
$userId = $_SESSION['user_id'];

// Handle API key generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'generate') {
        // Generate new API key
        $apiKey = bin2hex(random_bytes(32)); // 64 character hex string
        
        try {
            $stmt = $db->prepare("UPDATE users SET api_key = ? WHERE id = ?");
            $stmt->execute([$apiKey, $userId]);
            
            $_SESSION['flash_success'] = 'API key generated successfully. Please copy it now as it won\'t be shown again.';
            $_SESSION['new_api_key'] = $apiKey;
            header('Location: api-keys.php');
            exit;
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Failed to generate API key.';
        }
    } elseif ($_POST['action'] === 'revoke') {
        // Revoke API key
        try {
            $stmt = $db->prepare("UPDATE users SET api_key = NULL WHERE id = ?");
            $stmt->execute([$userId]);
            
            $_SESSION['flash_success'] = 'API key revoked successfully.';
            header('Location: api-keys.php');
            exit;
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Failed to revoke API key.';
        }
    }
}

// Get current API key status
$stmt = $db->prepare("SELECT api_key FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
$hasApiKey = !empty($user['api_key']);

// Check for newly generated key
$newApiKey = null;
if (isset($_SESSION['new_api_key'])) {
    $newApiKey = $_SESSION['new_api_key'];
    unset($_SESSION['new_api_key']);
}

$pageTitle = 'API Keys';
require_once 'includes/header.php';

// Get flash messages
$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>

<div class="container-fluid" style="margin-top: 20px;">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <h1>API Keys</h1>
            
            <?php if ($flash_success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($flash_success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($flash_error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($flash_error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($newApiKey): ?>
                <div class="alert alert-warning" role="alert">
                    <h5 class="alert-heading">New API Key Generated</h5>
                    <p>Please copy your API key now. For security reasons, it won't be displayed again.</p>
                    <div class="input-group mb-3">
                        <input type="text" class="form-control font-monospace" value="<?php echo htmlspecialchars($newApiKey); ?>" readonly id="apiKeyInput">
                        <button class="btn btn-outline-secondary" type="button" onclick="copyApiKey()">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">API Access</h5>
                    <p class="card-text">
                        Use API keys to authenticate requests to the <?php echo APP_NAME; ?> API. 
                        Keep your API key secure and never share it publicly.
                    </p>
                    
                    <?php if ($hasApiKey): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-key"></i> You have an active API key.
                        </div>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to revoke your API key? This action cannot be undone.');">
                            <input type="hidden" name="action" value="revoke">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-times"></i> Revoke API Key
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="generate">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-key"></i> Generate New API Key
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-body">
                    <h5 class="card-title">API Documentation</h5>
                    <p>The API base URL is: <code><?php echo rtrim(APP_URL, '/'); ?>/api/v1</code></p>
                    
                    <h6 class="mt-4">Authentication</h6>
                    <p>Include your API key in requests using one of these methods:</p>
                    <ul>
                        <li>Header: <code>X-API-Key: YOUR_API_KEY</code></li>
                        <li>Header: <code>Authorization: Bearer YOUR_API_KEY</code></li>
                        <li>Query parameter: <code>?api_key=YOUR_API_KEY</code></li>
                    </ul>
                    
                    <h6 class="mt-4">Available Endpoints</h6>
                    
                    <h6 class="text-muted">Communities</h6>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Method</th>
                                <th>Endpoint</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge bg-success">GET</span></td>
                                <td><code>/communities</code></td>
                                <td>List communities</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-success">GET</span></td>
                                <td><code>/communities/{id}</code></td>
                                <td>Get community details</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-primary">POST</span></td>
                                <td><code>/communities</code></td>
                                <td>Create new community</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-warning">PUT</span></td>
                                <td><code>/communities/{id}</code></td>
                                <td>Update community</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h6 class="text-muted">Projects</h6>
                    <table class="table">
                        <tbody>
                            <tr>
                                <td><span class="badge bg-success">GET</span></td>
                                <td><code>/projects?community_id={id}</code></td>
                                <td>List projects in a community</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-success">GET</span></td>
                                <td><code>/projects/{id}</code></td>
                                <td>Get project details</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-success">GET</span></td>
                                <td><code>/projects/{id}/skills</code></td>
                                <td>Get project required skills</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-success">GET</span></td>
                                <td><code>/projects/{id}/members</code></td>
                                <td>Get project team members</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h6 class="text-muted">Programs & Courses</h6>
                    <table class="table">
                        <tbody>
                            <tr>
                                <td><span class="badge bg-success">GET</span></td>
                                <td><code>/programs?community_id={id}</code></td>
                                <td>List programs in a community</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-success">GET</span></td>
                                <td><code>/courses?community_id={id}</code></td>
                                <td>List courses in a community</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-success">GET</span></td>
                                <td><code>/courses/{id}/lessons</code></td>
                                <td>List lessons in a course</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h6 class="mt-4">Example Requests</h6>
                    <pre class="bg-light p-3"><code># List communities
curl -H "X-API-Key: YOUR_API_KEY" \
  <?php echo rtrim(APP_URL, '/'); ?>/api/v1/communities

# List projects in community 1
curl -H "X-API-Key: YOUR_API_KEY" \
  <?php echo rtrim(APP_URL, '/'); ?>/api/v1/projects?community_id=1

# Get project details
curl -H "X-API-Key: YOUR_API_KEY" \
  <?php echo rtrim(APP_URL, '/'); ?>/api/v1/projects/123

# Get project skills
curl -H "X-API-Key: YOUR_API_KEY" \
  <?php echo rtrim(APP_URL, '/'); ?>/api/v1/projects/123/skills

# List courses in community
curl -H "X-API-Key: YOUR_API_KEY" \
  <?php echo rtrim(APP_URL, '/'); ?>/api/v1/courses?community_id=1

# Get lessons in course
curl -H "X-API-Key: YOUR_API_KEY" \
  <?php echo rtrim(APP_URL, '/'); ?>/api/v1/courses/456/lessons</code></pre>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyApiKey() {
    const input = document.getElementById('apiKeyInput');
    input.select();
    document.execCommand('copy');
    
    // Show feedback
    const button = event.target.closest('button');
    const originalHtml = button.innerHTML;
    button.innerHTML = '<i class="fas fa-check"></i> Copied!';
    button.classList.remove('btn-outline-secondary');
    button.classList.add('btn-success');
    
    setTimeout(() => {
        button.innerHTML = originalHtml;
        button.classList.remove('btn-success');
        button.classList.add('btn-outline-secondary');
    }, 2000);
}
</script>

<?php require_once 'includes/footer.php'; ?>