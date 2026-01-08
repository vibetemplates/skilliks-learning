<?php
/**
 * Test page for clean URLs
 * This page tests various URL patterns to ensure .htaccess is working correctly
 */

require_once 'includes/session.php';
require_once 'config/database.php';

$page_title = 'Clean URL Test';
require_once 'includes/header.php';
?>

<div class="container mt-5">
    <h1>Clean URL Testing</h1>
    <p>This page tests whether the .htaccess rules are working correctly.</p>
    
    <div class="card mt-4">
        <div class="card-header">
            <h3>Current Request Information</h3>
        </div>
        <div class="card-body">
            <dl class="row">
                <dt class="col-sm-3">Request URI:</dt>
                <dd class="col-sm-9"><code><?php echo $_SERVER['REQUEST_URI']; ?></code></dd>
                
                <dt class="col-sm-3">Script Name:</dt>
                <dd class="col-sm-9"><code><?php echo $_SERVER['SCRIPT_NAME']; ?></code></dd>
                
                <dt class="col-sm-3">PHP_SELF:</dt>
                <dd class="col-sm-9"><code><?php echo $_SERVER['PHP_SELF']; ?></code></dd>
            </dl>
        </div>
    </div>
    
    <div class="card mt-4">
        <div class="card-header">
            <h3>Test Links</h3>
        </div>
        <div class="card-body">
            <h5>Root Level Pages (without .php)</h5>
            <div class="list-group mb-3">
                <a href="/login" class="list-group-item list-group-item-action">/login</a>
                <a href="/dashboard" class="list-group-item list-group-item-action">/dashboard</a>
                <a href="/projects" class="list-group-item list-group-item-action">/projects</a>
                <a href="/profile" class="list-group-item list-group-item-action">/profile</a>
            </div>
            
            <h5>Root Level Pages (with .php - should still work)</h5>
            <div class="list-group mb-3">
                <a href="/login" class="list-group-item list-group-item-action">/login</a>
                <a href="/dashboard" class="list-group-item list-group-item-action">/dashboard</a>
                <a href="/projects.php" class="list-group-item list-group-item-action">/projects.php</a>
            </div>
            
            <h5>Subdirectory Pages (without .php)</h5>
            <div class="list-group mb-3">
                <a href="/tickets/new" class="list-group-item list-group-item-action">/tickets/new</a>
                <a href="/tickets/open" class="list-group-item list-group-item-action">/tickets/open</a>
                <a href="/tickets/closed" class="list-group-item list-group-item-action">/tickets/closed</a>
            </div>
            
            <h5>API Endpoints (should keep .php)</h5>
            <div class="list-group mb-3">
                <span class="list-group-item">/api/comments.php (should require .php)</span>
                <span class="list-group-item">/ajax/search.php (should require .php)</span>
            </div>
            
            <h5>Non-existent Pages (should return 404)</h5>
            <div class="list-group mb-3">
                <a href="/nonexistent" class="list-group-item list-group-item-action text-danger">/nonexistent (should be 404)</a>
                <a href="/fake/page" class="list-group-item list-group-item-action text-danger">/fake/page (should be 404)</a>
            </div>
        </div>
    </div>
    
    <div class="card mt-4">
        <div class="card-header">
            <h3>Quick Test Results</h3>
        </div>
        <div class="card-body">
            <?php
            // Test if we can access this page without .php
            $current_url = $_SERVER['REQUEST_URI'];
            if (strpos($current_url, 'test-clean-urls') !== false && strpos($current_url, '.php') === false) {
                echo '<div class="alert alert-success">✓ SUCCESS: You accessed this page without .php in the URL!</div>';
            } else {
                echo '<div class="alert alert-info">ℹ️ Try accessing this page at <a href="/test-clean-urls">/test-clean-urls</a> (without .php)</div>';
            }
            ?>
            
            <h5>Instructions:</h5>
            <ol>
                <li>Click the test links above</li>
                <li>Verify that pages load correctly without .php</li>
                <li>Verify that .php URLs still work</li>
                <li>Verify that non-existent pages return 404</li>
                <li>Check that API endpoints still require .php</li>
            </ol>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>