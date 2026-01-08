<?php
/**
 * Public Communities Page
 * 
 * Lists all public communities that users can explore and join
 */

require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'includes/session.php';
require_once 'classes/Community.php';

$page_title = 'Discover Communities';

// Initialize Community class
$community = new Community();

// Get all public communities
$communities = $community->getAll(['is_public' => 1, 'is_active' => 1]);

// Check if user is logged in
$isLoggedIn = isLoggedIn();
$userId = $isLoggedIn ? getCurrentUserId() : null;

// Get user's communities if logged in
$userCommunityIds = [];
if ($isLoggedIn) {
    $userCommunities = $community->getUserCommunities($userId);
    $userCommunityIds = array_column($userCommunities, 'id');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - SkillikS</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/favicon.png">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    
    <style>
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
        }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 40px;
        }
        
        .community-card {
            transition: transform 0.2s;
            height: 100%;
        }
        
        .community-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .member-count {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .community-stats {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="/">
                <img src="/logo.png" alt="SkillikS Logo" height="30" class="d-inline-block align-text-top me-2">
                SkillikS
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link" href="/">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="/communities.php">Communities</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/blog.php">Blog</a>
                    </li>
                    <?php if ($isLoggedIn): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/dashboard">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-primary ms-2" href="/communities">Join Now</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container text-center">
            <h1 class="display-4 fw-bold">Discover Communities</h1>
            <p class="lead">Join communities of learners and professionals advancing their AI skills together</p>
        </div>
    </div>

    <!-- Communities Grid -->
    <div class="container mb-5">
        <?php if (empty($communities)): ?>
            <div class="text-center py-5">
                <i class="bi bi-people display-1 text-muted"></i>
                <h3 class="mt-3">No Communities Available</h3>
                <p class="text-muted">Check back soon for new learning communities!</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($communities as $comm): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card community-card position-relative">
                        
                        <?php if (!empty($comm['banner_url'])): ?>
                            <img src="<?php echo htmlspecialchars($comm['banner_url']); ?>" 
                                 class="card-img-top" 
                                 alt="<?php echo htmlspecialchars($comm['name']); ?> Banner"
                                 style="height: 200px; object-fit: cover;">
                        <?php else: ?>
                            <div class="card-img-top bg-gradient" 
                                 style="height: 200px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body">
                            <h5 class="card-title mb-3"><?php echo htmlspecialchars($comm['name']); ?></h5>
                            
                            <p class="card-text">
                                <?php echo htmlspecialchars($comm['description'] ?: 'Join this community to learn and grow together!'); ?>
                            </p>
                            
                            <div class="community-stats">
                                <div class="stat-item">
                                    <i class="bi bi-tag-fill"></i>
                                    <span>
                                        <?php if ($comm['monthly_price'] === null): ?>
                                            Contact Us
                                        <?php elseif ($comm['monthly_price'] == 0): ?>
                                            Free
                                        <?php else: ?>
                                            $<?php echo number_format($comm['monthly_price'], 2); ?>/mo
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="stat-item">
                                    <i class="bi bi-people-fill"></i>
                                    <span><?php echo number_format($comm['member_count']); ?> members</span>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <?php if ($isLoggedIn && in_array($comm['id'], $userCommunityIds)): ?>
                                    <a href="/dashboard" class="btn btn-success w-100">
                                        <i class="bi bi-check-circle"></i> Member
                                    </a>
                                <?php elseif ($isLoggedIn): ?>
                                    <?php if ($comm['requires_approval']): ?>
                                        <button class="btn btn-primary w-100" onclick="requestToJoin(<?php echo $comm['id']; ?>)">
                                            <i class="bi bi-person-plus"></i> Request to Join
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-primary w-100" onclick="joinCommunity(<?php echo $comm['id']; ?>)">
                                            <i class="bi bi-person-plus"></i> Join Community
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="/register?community=<?php echo urlencode($comm['slug']); ?>" class="btn btn-primary w-100">
                                        <i class="bi bi-person-plus"></i> Join Community
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container text-center">
            <p class="mb-0">SkillikS © <?php echo date('Y'); ?> Kinetic Seas Inc</p>
        </div>
    </footer>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if ($isLoggedIn): ?>
    <script>
    function joinCommunity(communityId) {
        if (confirm('Are you sure you want to join this community?')) {
            fetch('/api/join-community.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ community_id: communityId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Successfully joined the community!');
                    location.reload();
                } else {
                    alert('Failed to join community: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while joining the community');
            });
        }
    }
    
    function requestToJoin(communityId) {
        if (confirm('Are you sure you want to request to join this community? An admin will need to approve your request.')) {
            fetch('/api/request-join-community.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ community_id: communityId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Your request to join has been submitted! You will be notified once approved.');
                    location.reload();
                } else {
                    alert('Failed to submit request: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while submitting your request');
            });
        }
    }
    </script>
    <?php endif; ?>
</body>
</html>