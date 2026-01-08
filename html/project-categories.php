<?php
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/ProjectCategory.php';
require_once 'classes/Project.php';
require_once 'classes/Community.php';

requireLogin();

$currentCommunityId = getCurrentCommunityId();

// Get current community name
$communityObj = new Community();
$currentCommunity = $communityObj->getById($currentCommunityId);
$currentCommunityName = $currentCommunity ? htmlspecialchars($currentCommunity['name']) : 'Community';

$projectCategory = new ProjectCategory();
$project = new Project();

// Get all active categories
$categories = $projectCategory->findAll(true);

// Add project count for each category
foreach ($categories as &$category) {
    $category['project_count'] = $projectCategory->getProjectCount($category['id']);
}
unset($category); // Break the reference with the last element

$page_title = 'Project Categories';
include 'includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h1 id="categories-heading"><?php echo $currentCommunityName; ?> - Project Categories</h1>
                <div>
                    <a href="create-project.php" class="btn btn-primary" id="new-project-btn">
                        <i class="bi bi-plus-circle"></i> New Project
                    </a>
                    <?php if (isCurrentUserAdmin()): ?>
                        <a href="category-add.php" class="btn btn-success" id="add-category-btn">
                            <i class="bi bi-plus-circle"></i> Add New Category
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Search Form -->
            <div class="card mb-4" id="search-card">
                <div class="card-body">
                    <form action="/search-results.php" method="GET" id="searchForm">
                        <div class="row">
                            <div class="col-md-10">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" 
                                           class="form-control" 
                                           name="q"
                                           id="searchInput" 
                                           placeholder="Search projects by name or skills... (leave blank to show all)" 
                                           autocomplete="off">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100" id="searchBtn">
                                    <i class="bi bi-search"></i> Search
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="mb-4" id="project-buttons-row">
                <a href="pending-projects" class="btn btn-warning" id="pending-projects-btn">
                    <i class="bi bi-clock-history"></i> Pending Projects
                </a>
                <a href="my-projects" class="btn btn-primary" id="my-projects-btn">
                    <i class="bi bi-person-workspace"></i> My Projects
                </a>
            </div>
            
            <div class="row" id="categories-grid">
                <?php foreach ($categories as $category): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 shadow-sm category-card" id="category-<?php echo $category['id']; ?>">
                            <?php if ($category['thumbnail_url']): ?>
                                <img src="<?php echo htmlspecialchars($category['thumbnail_url']); ?>" 
                                     class="card-img-top" 
                                     alt="<?php echo htmlspecialchars($category['name']); ?>"
                                     style="height: 200px; object-fit: cover;">
                            <?php else: ?>
                                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" 
                                     style="height: 200px;">
                                    <i class="bi bi-folder-fill text-muted" style="font-size: 3rem;"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-body">
                                <h2 class="card-title" id="category-title-<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </h2>
                                
                                <?php if ($category['description']): ?>
                                    <p class="card-text text-muted" id="category-desc-<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['description']); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <?php if ($category['skill_level'] && $category['skill_level'] !== 'all'): ?>
                                            <span class="badge bg-info" id="skill-level-<?php echo $category['id']; ?>">
                                                <?php echo ucfirst($category['skill_level']); ?>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <span class="badge bg-secondary" id="project-count-<?php echo $category['id']; ?>">
                                            <?php echo $category['project_count']; ?> 
                                            <?php echo $category['project_count'] == 1 ? 'Project' : 'Projects'; ?>
                                        </span>
                                    </div>
                                    
                                    <div>
                                        <?php if (isCurrentUserAdmin()): ?>
                                            <a href="category-edit.php?id=<?php echo $category['id']; ?>" 
                                               class="btn btn-warning btn-sm"
                                               id="edit-category-<?php echo $category['id']; ?>">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                        <?php endif; ?>
                                        <a href="projects.php?category=<?php echo $category['id']; ?>" 
                                           class="btn btn-primary btn-sm"
                                           id="view-category-<?php echo $category['id']; ?>">
                                            View Projects <i class="bi bi-arrow-right"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($categories)): ?>
                <div class="alert alert-info text-center" id="no-categories-alert">
                    <i class="bi bi-info-circle"></i> No project categories found.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>