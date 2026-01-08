<?php
/**
 * Community About Page
 * 
 * Displays information about a specific community
 */

require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Community.php';

// Get community ID from URL parameter
$community_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$community_id) {
    header('Location: /');
    exit();
}

// Get community details
$community = new Community();
$communityData = $community->getById($community_id);

if (!$communityData || !$communityData['is_public'] || !$communityData['is_active']) {
    header('Location: /');
    exit();
}

// Get member count
$db = Database::getInstance()->getConnection();
if (!empty($communityData['display_member_count'])) {
    $memberDisplay = $communityData['display_member_count'];
} else {
    $stmt = $db->prepare("SELECT COUNT(*) as member_count FROM community_members WHERE community_id = ?");
    $stmt->execute([$community_id]);
    $memberCount = $stmt->fetch(PDO::FETCH_ASSOC)['member_count'];
    $memberDisplay = number_format($memberCount);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($communityData['name']); ?> - SkillikS</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/favicon.png">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    
    <style>
        body {
            background-color: #1976d2;
        }
        
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
        }
        
        .navbar {
            min-height: 80px;
            padding: 10px 0;
        }
        
        .about-section {
            padding: 40px 0;
            background-color: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
        }
        
        .community-banner {
            height: 350px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .community-banner img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .join-section {
            background-color: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            margin-top: 30px;
        }
        
        .price-tag {
            font-size: 2rem;
            font-weight: bold;
            color: #28a745;
            margin: 20px 0;
        }
        
        .stats-box {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .stat-item {
            text-align: center;
            padding: 10px;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #1976d2;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .carousel-control-prev,
        .carousel-control-next {
            background-color: rgba(0, 0, 0, 0.5);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .carousel-control-prev {
            left: 10px;
        }
        
        .carousel-control-next {
            right: 10px;
        }
        
        .carousel-indicators {
            position: relative;
            margin-top: 10px;
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="/">
                <img src="/assets/logo.png" alt="SkillikS Logo" style="height: 60px;">
            </a>
            <div id="navbar-header-text" class="ms-3 d-none d-md-flex align-items-center">
                <h3 class="mb-0 text-muted">Learn, Build, Collaborate, Deploy, Maintain</h3>
            </div>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="btn btn-primary" href="/login">Login</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- About Section -->
    <section id="about" class="about-section container" style="margin-top: 120px;">
        <div class="container">
            <!-- Community Banner -->
            <div class="community-banner">
                <?php if (!empty($communityData['banner_url'])): ?>
                    <img src="<?php echo htmlspecialchars($communityData['banner_url']); ?>" alt="<?php echo htmlspecialchars($communityData['name']); ?> Banner">
                <?php endif; ?>
            </div>

            <div class="row">
                <div class="col-lg-8">
                    <h1 class="mb-4"><?php echo htmlspecialchars($communityData['name']); ?></h1>
                    
                    <div class="mb-4">
                        <h3>About This Community</h3>
                        <p class="lead"><?php echo nl2br(htmlspecialchars($communityData['description'])); ?></p>
                    </div>

                    <div class="mb-4">
                        <h3>Community Videos</h3>
                        <?php if (!empty($communityData['youtube_video_url'])): ?>
                            <!-- Main Video -->
                            <div class="ratio ratio-16x9 mb-4">
                                <?php 
                                $videoUrl = $communityData['youtube_video_url'];
                                $videoId = '';
                                if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i', $videoUrl, $match)) {
                                    $videoId = $match[1];
                                }
                                ?>
                                <?php if ($videoId): ?>
                                    <iframe src="https://www.youtube.com/embed/<?php echo htmlspecialchars($videoId); ?>" 
                                            title="<?php echo htmlspecialchars($communityData['name']); ?> Video" 
                                            frameborder="0" 
                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                            allowfullscreen></iframe>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Video Carousel for Additional Videos -->
                            <div id="videoCarousel" class="carousel slide" data-bs-ride="carousel">
                                <div class="carousel-indicators">
                                    <button type="button" data-bs-target="#videoCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                                    <button type="button" data-bs-target="#videoCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
                                    <button type="button" data-bs-target="#videoCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
                                </div>
                                <div class="carousel-inner">
                                    <div class="carousel-item active">
                                        <div class="ratio ratio-16x9">
                                            <div class="d-flex align-items-center justify-content-center bg-light">
                                                <p class="text-muted">Additional video 1 placeholder</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="carousel-item">
                                        <div class="ratio ratio-16x9">
                                            <div class="d-flex align-items-center justify-content-center bg-light">
                                                <p class="text-muted">Additional video 2 placeholder</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="carousel-item">
                                        <div class="ratio ratio-16x9">
                                            <div class="d-flex align-items-center justify-content-center bg-light">
                                                <p class="text-muted">Additional video 3 placeholder</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button class="carousel-control-prev" type="button" data-bs-target="#videoCarousel" data-bs-slide="prev">
                                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                    <span class="visually-hidden">Previous</span>
                                </button>
                                <button class="carousel-control-next" type="button" data-bs-target="#videoCarousel" data-bs-slide="next">
                                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                    <span class="visually-hidden">Next</span>
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>No community videos available yet.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Stats Box -->
                    <div class="stats-box">
                        <div class="row">
                            <div class="col-6">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo htmlspecialchars($memberDisplay); ?></div>
                                    <div class="stat-label">Members</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-item">
                                    <div class="stat-value">50+</div>
                                    <div class="stat-label">Resources</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Join Section -->
                    <div class="join-section">
                        <h3>Ready to Join?</h3>
                        <p>Become part of our thriving community today!</p>
                        
                        <?php if ($communityData['monthly_price'] === null): ?>
                            <div class="price-tag">Contact Us</div>
                        <?php elseif ($communityData['monthly_price'] == 0): ?>
                            <div class="price-tag">FREE</div>
                        <?php else: ?>
                            <div class="price-tag">$<?php echo number_format($communityData['monthly_price'], 2); ?>/month</div>
                        <?php endif; ?>
                        
                        <a href="/register?community=<?php echo urlencode($communityData['slug']); ?>" class="btn btn-primary btn-lg w-100">
                            <i class="bi bi-people-fill me-2"></i> Join Community
                        </a>
                        
                        <a href="#" class="btn btn-outline-secondary btn-lg w-100 mt-3">
                            <i class="bi bi-box-arrow-up-right me-2"></i> Already Join on Skool?
                        </a>
                        
                        <small class="text-muted d-block mt-3">
                            <?php if (!empty($communityData['monthly_price']) && $communityData['monthly_price'] > 0): ?>
                                Cancel anytime. No hidden fees.
                            <?php else: ?>
                                No credit card required.
                            <?php endif; ?>
                        </small>
                    </div>

                    <!-- Additional Info -->
                    <div class="mt-4 text-center">
                        <p class="text-muted">
                            <i class="bi bi-shield-check me-2"></i>
                            Safe & Secure Platform
                        </p>
                        <p class="text-muted">
                            <i class="bi bi-headset me-2"></i>
                            24/7 Community Support
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container text-center">
            <p class="mb-0">&copy; 2024 Kinetic Seas Inc. All rights reserved.</p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>