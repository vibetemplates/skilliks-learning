<?php
/**
 * Discover Communities Page
 * 
 * Lists all available communities that users can join
 */

$page_title = 'My Communities';

// Check authentication first
require_once 'includes/session.php';
requireLogin();

require_once 'config/database.php';
require_once 'classes/Community.php';
require_once 'includes/header.php';

// Initialize Community class
$community = new Community();

// Get current community ID
$currentCommunityId = getCurrentCommunityId();

// Get search query if provided
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Set up filters for public communities
$filters = [
    'is_active' => 1,
    'is_public' => 1
];

if (!empty($search)) {
    $filters['search'] = $search;
}

// Get user's communities first
$userCommunities = $community->getUserCommunities($_SESSION['user_id']);
$userCommunityIds = array_column($userCommunities, 'id');

// Get all public communities
$allCommunities = $community->getAll($filters);

// Separate communities into member and non-member
$memberCommunities = [];
$nonMemberCommunities = [];

foreach ($allCommunities as $comm) {
    if (in_array($comm['id'], $userCommunityIds)) {
        $memberCommunities[] = $comm;
    } else {
        $nonMemberCommunities[] = $comm;
    }
}

// Get user's pending join requests
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("
    SELECT community_id 
    FROM community_join_requests 
    WHERE user_id = ? AND status = 'pending'
");
$stmt->execute([$_SESSION['user_id']]);
$pendingRequests = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<main class="container-fluid px-4 py-3">
            <div id="alert-container"></div>
            
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">My Communities</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button id="discoverBtn" class="btn btn-primary me-2">
                        <i class="bi bi-compass"></i> Discover
                    </button>
                    <form class="d-flex" method="GET" action="">
                        <input class="form-control form-control-sm me-2" type="search" name="search" 
                               placeholder="Search communities..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-sm btn-outline-secondary" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Member Communities Section -->
            <div id="memberCommunities">
                <?php if (empty($memberCommunities)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 
                        You are not a member of any communities yet. Click the Discover button to find communities to join!
                    </div>
                <?php else: ?>
                    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
                        <?php foreach ($memberCommunities as $comm): ?>
                        <div class="col">
                            <div class="card h-100 <?php echo $comm['id'] != $currentCommunityId ? 'member-community-card' : ''; ?>" 
                                 data-community-id="<?php echo $comm['id']; ?>" 
                                 style="<?php echo $comm['id'] != $currentCommunityId ? 'cursor: pointer;' : ''; ?>">
                                <?php if ($comm['banner_url']): ?>
                                    <img src="<?php echo htmlspecialchars($comm['banner_url']); ?>" 
                                         class="card-img-top" alt="<?php echo htmlspecialchars($comm['name']); ?> banner"
                                         style="height: 150px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="card-img-top bg-secondary d-flex align-items-center justify-content-center" 
                                         style="height: 150px;">
                                        <i class="bi bi-building text-white" style="font-size: 3rem;"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <?php if ($comm['logo_url']): ?>
                                            <img src="<?php echo htmlspecialchars($comm['logo_url']); ?>" 
                                                 alt="<?php echo htmlspecialchars($comm['name']); ?> logo"
                                                 class="me-2" style="width: 24px; height: 24px; object-fit: contain;">
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($comm['name']); ?>
                                    </h5>
                                    
                                    <p class="card-text">
                                        <?php echo htmlspecialchars(substr($comm['description'] ?? 'No description available', 0, 150)); ?>
                                        <?php if (strlen($comm['description'] ?? '') > 150): ?>...<?php endif; ?>
                                    </p>
                                    
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <small class="text-muted">
                                            <i class="bi bi-people"></i> <?php echo $comm['member_count']; ?> members<br>
                                            <i class="bi bi-folder"></i> <?php echo $comm['project_count']; ?> projects<br>
                                            <i class="bi bi-book"></i> <?php echo $comm['course_count']; ?> courses
                                        </small>
                                        
                                        <div>
                                            <?php if (in_array($comm['id'], $userCommunityIds)): ?>
                                                <?php if ($comm['id'] == $currentCommunityId): ?>
                                                    <button class="btn btn-sm btn-success" disabled>
                                                        <i class="bi bi-check-circle-fill"></i> Current
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted small">
                                                        <i class="bi bi-hand-index"></i> Click to select
                                                    </span>
                                                <?php endif; ?>
                                            <?php elseif (in_array($comm['id'], $pendingRequests)): ?>
                                                <button class="btn btn-sm btn-warning" disabled>
                                                    <i class="bi bi-clock"></i> Pending Approval
                                                </button>
                                            <?php else: ?>
                                                <?php if ($comm['requires_approval']): ?>
                                                    <button class="btn btn-sm btn-primary join-community" 
                                                            data-community-id="<?php echo $comm['id']; ?>">
                                                        <i class="bi bi-person-plus"></i> Request to Join
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-primary join-community" 
                                                            data-community-id="<?php echo $comm['id']; ?>">
                                                        <i class="bi bi-person-plus"></i> Join
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Non-Member Communities Section (Hidden by default) -->
            <div id="nonMemberCommunities" style="display: none;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="h4">Discover New Communities</h3>
                    <button id="backBtn" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to My Communities
                    </button>
                </div>
                
                <?php if (empty($nonMemberCommunities)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 
                        <?php if (!empty($search)): ?>
                            No other communities found matching "<?php echo htmlspecialchars($search); ?>".
                        <?php else: ?>
                            No other public communities available at this time.
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
                        <?php foreach ($nonMemberCommunities as $comm): ?>
                        <div class="col">
                            <div class="card h-100">
                                <?php if ($comm['banner_url']): ?>
                                    <img src="<?php echo htmlspecialchars($comm['banner_url']); ?>" 
                                         class="card-img-top" alt="<?php echo htmlspecialchars($comm['name']); ?> banner"
                                         style="height: 150px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="card-img-top bg-secondary d-flex align-items-center justify-content-center" 
                                         style="height: 150px;">
                                        <i class="bi bi-building text-white" style="font-size: 3rem;"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <?php if ($comm['logo_url']): ?>
                                            <img src="<?php echo htmlspecialchars($comm['logo_url']); ?>" 
                                                 alt="<?php echo htmlspecialchars($comm['name']); ?> logo"
                                                 class="me-2" style="width: 24px; height: 24px; object-fit: contain;">
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($comm['name']); ?>
                                    </h5>
                                    
                                    <p class="card-text">
                                        <?php echo htmlspecialchars(substr($comm['description'] ?? 'No description available', 0, 150)); ?>
                                        <?php if (strlen($comm['description'] ?? '') > 150): ?>...<?php endif; ?>
                                    </p>
                                    
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <small class="text-muted">
                                            <i class="bi bi-people"></i> <?php echo $comm['member_count']; ?> members<br>
                                            <i class="bi bi-folder"></i> <?php echo $comm['project_count']; ?> projects<br>
                                            <i class="bi bi-book"></i> <?php echo $comm['course_count']; ?> courses
                                        </small>
                                        
                                        <div>
                                            <?php if (in_array($comm['id'], $pendingRequests)): ?>
                                                <button class="btn btn-sm btn-warning" disabled>
                                                    <i class="bi bi-clock"></i> Pending Approval
                                                </button>
                                            <?php else: ?>
                                                <?php if ($comm['requires_approval']): ?>
                                                    <button class="btn btn-sm btn-primary join-community" 
                                                            data-community-id="<?php echo $comm['id']; ?>">
                                                        <i class="bi bi-person-plus"></i> Request to Join
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-primary join-community" 
                                                            data-community-id="<?php echo $comm['id']; ?>">
                                                        <i class="bi bi-person-plus"></i> Join
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
</main>

<style>
.member-community-card {
    transition: transform 0.2s, box-shadow 0.2s;
}

.member-community-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}
</style>

<script>
// Handle view toggling
document.addEventListener('DOMContentLoaded', function() {
    const discoverBtn = document.getElementById('discoverBtn');
    const backBtn = document.getElementById('backBtn');
    const memberCommunities = document.getElementById('memberCommunities');
    const nonMemberCommunities = document.getElementById('nonMemberCommunities');
    const pageTitle = document.querySelector('.h2');
    
    // Show discover view
    discoverBtn.addEventListener('click', function() {
        memberCommunities.style.display = 'none';
        nonMemberCommunities.style.display = 'block';
        pageTitle.textContent = 'Discover Communities';
        discoverBtn.style.display = 'none';
    });
    
    // Show member communities view
    backBtn.addEventListener('click', function() {
        memberCommunities.style.display = 'block';
        nonMemberCommunities.style.display = 'none';
        pageTitle.textContent = 'My Communities';
        discoverBtn.style.display = 'block';
    });
    
    // Handle join community
    const joinButtons = document.querySelectorAll('.join-community');
    
    joinButtons.forEach(button => {
        button.addEventListener('click', function() {
            const communityId = this.getAttribute('data-community-id');
            const buttonText = this.innerHTML;
            
            // Disable button and show loading
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Joining...';
            
            // Make request to join community
            fetch('/api/community-join.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ community_id: communityId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.requires_approval && !data.auto_approved) {
                        // Show pending status for requests requiring approval
                        this.className = 'btn btn-sm btn-warning';
                        this.innerHTML = '<i class="bi bi-clock"></i> Pending Approval';
                        this.disabled = true;
                        
                        // Show success message
                        showAlert('success', data.message);
                    } else {
                        // Update button to show member status
                        this.className = 'btn btn-sm btn-success community-switch';
                        this.innerHTML = '<i class="bi bi-check-circle"></i> Member';
                        this.setAttribute('data-community-id', communityId);
                        
                        // Convert to switch button
                        this.removeEventListener('click', arguments.callee);
                        this.addEventListener('click', function(e) {
                            e.preventDefault();
                            switchCommunity(communityId);
                        });
                        
                        // Show success message
                        showAlert('success', data.message);
                    }
                } else {
                    // Restore original button
                    this.disabled = false;
                    this.innerHTML = buttonText;
                    showAlert('danger', data.message || 'Failed to join community');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.disabled = false;
                this.innerHTML = buttonText;
                showAlert('danger', 'Failed to join community');
            });
        });
    });
    
    // Handle switching to member communities
    const switchButtons = document.querySelectorAll('.community-switch');
    switchButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const communityId = this.getAttribute('data-community-id');
            switchCommunity(communityId);
        });
    });
    
    // Handle clicking on member community cards for immediate navigation
    const memberCommunityCards = document.querySelectorAll('.member-community-card');
    memberCommunityCards.forEach(card => {
        card.addEventListener('click', function(e) {
            // Don't trigger if clicking on a button
            if (e.target.tagName === 'BUTTON' || e.target.closest('button')) {
                return;
            }
            
            const communityId = this.getAttribute('data-community-id');
            // Show loading state
            this.style.opacity = '0.7';
            this.style.pointerEvents = 'none';
            
            // Switch community and navigate to dashboard
            switchCommunity(communityId);
        });
    });
});

function switchCommunity(communityId) {
    fetch('/api/switch-community.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ community_id: communityId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = '/dashboard';
        } else {
            showAlert('danger', 'Failed to switch community: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'Failed to switch community');
    });
}

function showAlert(type, message) {
    const alertContainer = document.getElementById('alert-container');
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    alertContainer.appendChild(alertDiv);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}
</script>

<?php require_once 'includes/footer.php'; ?>