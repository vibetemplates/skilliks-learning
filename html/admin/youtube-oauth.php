<?php
require_once '../includes/session.php';
require_once '../config/functions.php';
require_once '../classes/User.php';
require_once '../../vendor/autoload.php';

// Require login and admin role
requireLogin();
$currentUserId = getCurrentUserId();
$userObj = new User();
if (!$userObj->isAdmin($currentUserId)) {
    setFlashMessage('error', 'Access denied. Administrator privileges required.');
    header('Location: /courses');
    exit();
}

$config = require '../config/google-api.php';

$client = new Google\Client();
$client->setApplicationName($config['application_name']);
$client->setClientId($config['oauth_client_id']);
$client->setClientSecret($config['oauth_client_secret']);
$client->setRedirectUri('https://' . $_SERVER['HTTP_HOST'] . '/admin/youtube-oauth.php');
$client->setScopes([
    Google\Service\YouTube::YOUTUBE_READONLY,
    Google\Service\YouTube::YOUTUBEPARTNER
]);
$client->setAccessType('offline');
$client->setPrompt('consent');

// Handle OAuth callback
if (isset($_GET['code'])) {
    try {
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        $client->setAccessToken($token);
        
        // Save the token
        if (!is_dir('../config/tokens')) {
            mkdir('../config/tokens', 0755, true);
        }
        file_put_contents('../config/tokens/youtube-token.json', json_encode($token));
        
        $_SESSION['youtube_oauth_success'] = true;
        header('Location: /admin/fetch-youtube-transcripts.php?oauth=success');
        exit();
    } catch (Exception $e) {
        $_SESSION['youtube_oauth_error'] = $e->getMessage();
        header('Location: /admin/fetch-youtube-transcripts.php?oauth=error');
        exit();
    }
}

// Generate auth URL
$authUrl = $client->createAuthUrl();

$pageTitle = 'YouTube OAuth Authentication';
include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <h1>YouTube OAuth Authentication</h1>
            
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Authenticate with YouTube</h5>
                    <p>To fetch transcripts from private videos or videos with restricted captions, you need to authenticate with your YouTube account.</p>
                    
                    <div class="alert alert-info">
                        <strong>Note:</strong> You will be redirected to Google to authorize access to:
                        <ul>
                            <li>View your YouTube account</li>
                            <li>View and download video captions</li>
                        </ul>
                    </div>
                    
                    <a href="<?= htmlspecialchars($authUrl) ?>" class="btn btn-primary">
                        <i class="fab fa-youtube"></i> Authenticate with YouTube
                    </a>
                    
                    <hr>
                    
                    <p><small>This authentication will be saved and used for all transcript fetching operations.</small></p>
                </div>
            </div>
            
            <div class="mt-3">
                <a href="/admin/fetch-youtube-transcripts.php" class="btn btn-secondary">Back to Transcript Fetcher</a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>