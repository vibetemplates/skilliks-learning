<?php
/**
 * 404 Error Page
 */

http_response_code(404);
$page_title = '404 - Page Not Found';

// Try to include header, but don't fail if it doesn't work
@include_once 'includes/header.php';
?>

<div class="container mt-5 text-center">
    <h1 class="display-1">404</h1>
    <h2>Page Not Found</h2>
    <p class="lead">The page you are looking for doesn't exist.</p>
    <a href="/" class="btn btn-primary">Go to Homepage</a>
</div>

<?php @include_once 'includes/footer.php'; ?>