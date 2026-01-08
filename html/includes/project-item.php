<?php
/**
 * Project Item Template
 * 
 * Variables expected:
 * - $project: Project array with all project data
 * - $currentUserId: Current user ID
 * - $projectObj: Project object instance
 */
?>
<div id="project-item-<?php echo $project['id']; ?>" class="card h-100 shadow-sm project-item" data-required-skills="<?php echo htmlspecialchars(json_encode($project['required_skill_ids'])); ?>">
    <!-- Card Image -->
    <?php if ($project['thumbnail_url']): ?>
        <img src="<?php echo htmlspecialchars($project['thumbnail_url']); ?>" 
             class="card-img-top" 
             alt="<?php echo htmlspecialchars($project['name']); ?>"
             style="height: 200px; object-fit: cover;">
    <?php else: ?>
        <div class="card-img-top bg-light d-flex align-items-center justify-content-center" 
             style="height: 200px;">
            <i class="bi bi-folder-fill text-muted" style="font-size: 3rem;"></i>
        </div>
    <?php endif; ?>
    
    <!-- Card Header -->
    <div class="card-header" style="background: linear-gradient(135deg, #e0e0e0 0%, #d5d5d5 100%) !important;">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center flex-grow-1">
                <i class="bi bi-folder<?php echo $project['status'] == 'planning' ? '-plus' : ($project['status'] == 'active' ? '-check' : ''); ?> text-<?php echo $project['status'] == 'planning' ? 'warning' : ($project['status'] == 'active' ? 'success' : 'primary'); ?> me-2"></i>
                <h2 class="mb-0 me-2">
                    <a href="/project-detail?id=<?php echo $project['id']; ?>" class="text-decoration-none text-dark">
                        <?php echo htmlspecialchars($project['name']); ?>
                    </a>
                </h2>
                
                <!-- Status badges -->
                <?php if ($project['status'] == 'planning'): ?>
                    <span class="badge bg-warning text-dark ms-1">Planning</span>
                <?php elseif ($project['status'] == 'active'): ?>
                    <span class="badge bg-success ms-1">Active</span>
                <?php endif; ?>
                
                <?php if ($projectObj->isMember($project['id'], $currentUserId)): ?>
                    <span class="badge bg-primary ms-1">Member</span>
                <?php elseif ($project['created_by'] == $currentUserId): ?>
                    <span class="badge bg-info ms-1">Creator</span>
                <?php elseif ($project['status'] == 'active'): ?>
                    <span class="badge bg-secondary ms-1">Open</span>
                <?php endif; ?>
            </div>
            
            <!-- Voting buttons -->
            <div class="d-flex align-items-center">
                <button type="button" class="btn btn-sm btn-outline-success vote-btn" 
                        data-type="project" data-id="<?php echo $project['id']; ?>" data-vote="up"
                        title="Upvote this project">
                    <i class="bi bi-arrow-up-circle"></i>
                </button>
                <span class="mx-2 vote-count" id="vote-count-project-<?php echo $project['id']; ?>">
                    <?php echo $project['vote_count'] ?? 0; ?>
                </span>
                <button type="button" class="btn btn-sm btn-outline-danger vote-btn" 
                        data-type="project" data-id="<?php echo $project['id']; ?>" data-vote="down"
                        title="Downvote this project">
                    <i class="bi bi-arrow-down-circle"></i>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Card Body -->
    <div id="project-info-<?php echo $project['id']; ?>" class="card-body">
        <!-- Creator and member info -->
        <div class="mb-3">
            <small class="text-muted d-block">
                Created by <?php echo htmlspecialchars($project['creator_first_name'] . ' ' . $project['creator_last_name']); ?>
            </small>
            <small class="text-muted">
                <i class="bi bi-people"></i> <?php echo $project['member_count']; ?> members
                (<?php echo $project['working_count']; ?> working, <?php echo $project['completed_count']; ?> completed)
            </small>
        </div>
        
        <!-- Join Project Button for non-members -->
        <?php if ($project['status'] == 'active' && !$projectObj->isMember($project['id'], $currentUserId) && $project['created_by'] != $currentUserId): ?>
            <div class="mb-3" id="join-project-<?php echo $project['id']; ?>">
                <form hx-post="/htmx/join-project.php" 
                      hx-target="#join-project-<?php echo $project['id']; ?>"
                      hx-swap="innerHTML">
                    <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-person-plus"></i> Join This Project
                    </button>
                </form>
            </div>
        <?php endif; ?>
        
        <!-- Description -->
        <p class="card-text"><?php echo htmlspecialchars($project['description'] ?? 'No description available'); ?></p>
        
        <!-- Course code if exists -->
        <?php if ($project['course_code']): ?>
            <div class="mb-2">
                <span class="badge bg-secondary"><?php echo htmlspecialchars($project['course_code']); ?></span>
            </div>
        <?php endif; ?>
        
        <!-- Skills and Courses Section -->
        <div class="row mt-3">
            <!-- Required Skills Column -->
            <div class="col-md-6">
                <?php if (!empty($project['skills'])): ?>
                    <div>
                        <h6 class="text-muted mb-2">Required Skills:</h6>
                        <?php foreach ($project['skills'] as $skill): ?>
                            <span class="badge bg-<?php echo $skill['importance_level'] == 'required' ? 'danger' : ($skill['importance_level'] == 'preferred' ? 'warning' : 'info'); ?> me-1 mb-1">
                                <?php echo htmlspecialchars($skill['name']); ?>
                                <?php if ($skill['importance_level'] == 'required'): ?>*<?php endif; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Recommended Courses Column -->
            <div class="col-md-6">
                <?php if (!empty($project['recommended_courses'])): ?>
                    <div>
                        <h6 class="text-muted mb-2">Recommended Courses:</h6>
                        <?php foreach (array_slice($project['recommended_courses'], 0, 3) as $course): ?>
                            <div class="d-flex align-items-center mb-1">
                                <i class="bi bi-book text-muted me-2"></i>
                                <a href="/course-detail?id=<?php echo $course['id']; ?>" class="text-decoration-none small">
                                    <?php echo htmlspecialchars($course['title']); ?>
                                </a>
                                <span class="ms-2 badge bg-secondary"><?php echo $course['matching_skills']; ?></span>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($project['recommended_courses']) > 3): ?>
                            <small class="text-muted">+<?php echo count($project['recommended_courses']) - 3; ?> more...</small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Required Courses Row (if any) -->
        <?php if (!empty($project['required_courses'])): ?>
            <div class="row mt-3">
                <div class="col-12">
                    <h6 class="text-muted mb-2">Required Courses:</h6>
                    <?php foreach ($project['required_courses'] as $course): ?>
                        <span class="badge bg-danger me-1 mb-1">
                            <?php echo htmlspecialchars($course['title']); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- User Tasks (for My Projects only) -->
        <?php if (!empty($project['user_tasks'])): ?>
            <div class="mt-3 pt-3 border-top">
                <h6 class="text-muted mb-2">Your Tasks:</h6>
                <div class="small">
                    <?php foreach ($project['user_tasks'] as $task): ?>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="<?php echo $task['status'] == 'done' ? 'text-decoration-line-through text-muted' : ''; ?>">
                                <i class="bi bi-<?php echo $task['status'] == 'done' ? 'check-circle-fill text-success' : ($task['status'] == 'in_progress' ? 'play-circle-fill text-primary' : 'circle'); ?> me-1"></i>
                                <?php echo htmlspecialchars($task['title']); ?>
                            </span>
                            <?php if ($task['due_date']): ?>
                                <small class="text-muted"><?php echo date('M j', strtotime($task['due_date'])); ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
    </div>
    
    <!-- Card Footer -->
    <div class="card-footer">
        <a href="/project-detail?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-eye"></i> View Details
        </a>
    </div>
</div>