<?php
/**
 * Community Management Page
 * 
 * Allows admins to manage educational communities
 */

require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../config/functions.php';
require_once '../includes/session.php';
require_once '../classes/Community.php';

// Check if user is admin
if (!isCurrentUserAdmin()) {
    header('Location: /dashboard');
    exit;
}

$page_title = 'Community Management';
$current_page = 'communities';

// Initialize Community class
$community = new Community();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $result = $community->create([
                    'name' => $_POST['name'],
                    'description' => $_POST['description'] ?? '',
                    'monthly_price' => !empty($_POST['monthly_price']) ? floatval($_POST['monthly_price']) : null,
                    'is_public' => isset($_POST['is_public']) ? 1 : 0,
                    'requires_approval' => isset($_POST['requires_approval']) ? 1 : 0,
                    'auto_approve_from_email_list' => isset($_POST['auto_approve_from_email_list']) ? 1 : 0,
                    'created_by' => getCurrentUserId()
                ]);
                
                if ($result) {
                    setFlashMessage('success', 'Community created successfully.');
                } else {
                    setFlashMessage('error', 'Failed to create community.');
                }
                break;
                
            case 'update':
                $result = $community->update($_POST['community_id'], [
                    'name' => $_POST['name'],
                    'description' => $_POST['description'] ?? '',
                    'is_public' => isset($_POST['is_public']) ? 1 : 0,
                    'requires_approval' => isset($_POST['requires_approval']) ? 1 : 0,
                    'video_url' => $_POST['video_url'] ?? '',
                    'video_embed_code' => $_POST['video_embed_code'] ?? ''
                ]);
                
                if ($result) {
                    setFlashMessage('success', 'Community updated successfully.');
                } else {
                    setFlashMessage('error', 'Failed to update community.');
                }
                break;
                
            case 'delete':
                $result = $community->delete($_POST['community_id']);
                
                if ($result['success']) {
                    setFlashMessage('success', 'Community deleted successfully.');
                } else {
                    setFlashMessage('error', $result['message'] ?? 'Failed to delete community.');
                }
                break;
                
            case 'update_role':
                $result = $community->updateMemberRole(
                    $_POST['community_id'],
                    $_POST['user_id'],
                    $_POST['role']
                );
                
                if ($result) {
                    setFlashMessage('success', 'Member role updated successfully.');
                } else {
                    setFlashMessage('error', 'Failed to update member role.');
                }
                break;
                
            case 'remove_member':
                $result = $community->removeMember($_POST['community_id'], $_POST['user_id']);
                
                if ($result) {
                    setFlashMessage('success', 'Member removed successfully.');
                } else {
                    setFlashMessage('error', 'Failed to remove member.');
                }
                break;
        }
        
        header('Location: /admin/communities.php');
        exit;
    }
}

// Get all communities
$communities = $community->getAll(['is_active' => true]);

include '../includes/header.php';
?>

<main class="container-fluid px-4">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-1">Community Management</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="/admin/">Admin</a></li>
                                <li class="breadcrumb-item active">Communities</li>
                            </ol>
                        </nav>
                    </div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCommunityModal">
                        <i class="bi bi-plus-circle"></i> Create Community
                    </button>
                </div>

                <!-- Communities Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">All Communities</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($communities)): ?>
                            <p class="text-muted text-center py-4">No communities found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Description</th>
                                            <th>Price</th>
                                            <th>Members</th>
                                            <th>Projects</th>
                                            <th>Courses</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($communities as $comm): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($comm['name']); ?></strong>
                                                <br>
                                                <small class="text-muted">/<?php echo htmlspecialchars($comm['slug']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($comm['description'] ?: 'No description'); ?></td>
                                            <td>
                                                <?php if (!empty($comm['monthly_price']) && $comm['monthly_price'] > 0): ?>
                                                    <span class="badge bg-success">$<?php echo number_format($comm['monthly_price'], 2); ?>/mo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Free</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $comm['member_count']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $comm['project_count']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success"><?php echo $comm['course_count']; ?></span>
                                            </td>
                                            <td>
                                                <?php if ($comm['is_public']): ?>
                                                    <span class="badge bg-success">Public</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Private</span>
                                                <?php endif; ?>
                                                
                                                <?php if ($comm['requires_approval']): ?>
                                                    <span class="badge bg-warning">Approval Required</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?php echo date('M d, Y', strtotime($comm['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <button type="button" class="btn btn-outline-secondary" 
                                                            onclick="viewMembers(<?php echo $comm['id']; ?>)"
                                                            title="View Members">
                                                        <i class="bi bi-people"></i>
                                                    </button>
                                                    <?php if ($comm['requires_approval']): ?>
                                                    <a href="/admin/community-auto-approvals.php?community_id=<?php echo $comm['id']; ?>" 
                                                       class="btn btn-outline-warning" 
                                                       title="Manage Auto-Approvals">
                                                        <i class="bi bi-check-circle"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    <a href="/admin/community-edit.php?id=<?php echo $comm['id']; ?>" 
                                                       class="btn btn-outline-primary" 
                                                       title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <?php if ($comm['slug'] !== 'default'): ?>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            onclick="deleteCommunity(<?php echo $comm['id']; ?>, '<?php echo htmlspecialchars($comm['name']); ?>')"
                                                            title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
</main>

<!-- Create Community Modal -->
<div class="modal fade" id="createCommunityModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Community</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Community Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required>
                        <div class="form-text">This will be displayed throughout the application.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        <div class="form-text">A brief description of this community.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="monthly_price" class="form-label">Monthly Price</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="monthly_price" name="monthly_price" 
                                   step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-text">Leave empty or set to 0 for free communities.</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_public" name="is_public">
                            <label class="form-check-label" for="is_public">
                                Public Community
                            </label>
                            <div class="form-text">Public communities are visible to all users.</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="requires_approval" name="requires_approval" checked>
                            <label class="form-check-label" for="requires_approval">
                                Require Approval to Join
                            </label>
                            <div class="form-text">New members must be approved by a community admin.</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="auto_approve_from_email_list" name="auto_approve_from_email_list">
                            <label class="form-check-label" for="auto_approve_from_email_list">
                                Auto-approve from Email List
                            </label>
                            <div class="form-text">Automatically approve members whose email is in the free_community_emails table.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Community</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Community Modal -->
<div class="modal fade" id="editCommunityModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="community_id" id="edit_community_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Community</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Community Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_is_public" name="is_public">
                            <label class="form-check-label" for="edit_is_public">
                                Public Community
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_requires_approval" name="requires_approval">
                            <label class="form-check-label" for="edit_requires_approval">
                                Require Approval to Join
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_video_url" class="form-label">Video URL <small class="text-muted">(YouTube, Vimeo, Screencast)</small></label>
                        <input type="url" class="form-control" id="edit_video_url" name="video_url" placeholder="https://www.youtube.com/watch?v=...">
                        <small class="form-text text-muted">Enter a video URL to display on the About page</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_video_embed_code" class="form-label">Or Custom Embed Code</label>
                        <textarea class="form-control" id="edit_video_embed_code" name="video_embed_code" rows="3" placeholder="<iframe src='...'></iframe>"></textarea>
                        <small class="form-text text-muted">If you have a custom embed code, paste it here (overrides URL)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Community</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Members Modal -->
<div class="modal fade" id="membersModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Community Members</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="membersContent">
                    <div class="text-center py-4">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Store communities data for client-side operations
const communitiesData = <?php echo json_encode($communities); ?>;

function editCommunity(id) {
    const community = communitiesData.find(c => c.id == id);
    if (!community) return;
    
    document.getElementById('edit_community_id').value = community.id;
    document.getElementById('edit_name').value = community.name;
    document.getElementById('edit_description').value = community.description || '';
    document.getElementById('edit_is_public').checked = community.is_public == 1;
    document.getElementById('edit_requires_approval').checked = community.requires_approval == 1;
    document.getElementById('edit_video_url').value = community.video_url || '';
    document.getElementById('edit_video_embed_code').value = community.video_embed_code || '';
    
    new bootstrap.Modal(document.getElementById('editCommunityModal')).show();
}

function deleteCommunity(id, name) {
    if (!confirm(`Are you sure you want to delete the community "${name}"?\n\nThis action cannot be undone.`)) {
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="community_id" value="${id}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function viewMembers(communityId) {
    const modal = new bootstrap.Modal(document.getElementById('membersModal'));
    modal.show();
    
    // Load members via AJAX
    fetch(`/api/community-members.php?community_id=${communityId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayMembers(data.members, communityId);
            } else {
                document.getElementById('membersContent').innerHTML = 
                    '<div class="alert alert-danger">Failed to load members.</div>';
            }
        })
        .catch(error => {
            document.getElementById('membersContent').innerHTML = 
                '<div class="alert alert-danger">Error loading members.</div>';
        });
}

function displayMembers(members, communityId) {
    if (members.length === 0) {
        document.getElementById('membersContent').innerHTML = 
            '<p class="text-muted text-center">No members found.</p>';
        return;
    }
    
    let html = '<div class="table-responsive"><table class="table table-sm">';
    html += '<thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Joined</th><th>Actions</th></tr></thead><tbody>';
    
    members.forEach(member => {
        html += `
            <tr>
                <td>${escapeHtml(member.name)}</td>
                <td>${escapeHtml(member.email)}</td>
                <td>
                    <select class="form-select form-select-sm" onchange="updateMemberRole(${communityId}, ${member.user_id}, this.value)">
                        <option value="member" ${member.role === 'member' ? 'selected' : ''}>Member</option>
                        <option value="moderator" ${member.role === 'moderator' ? 'selected' : ''}>Moderator</option>
                        <option value="admin" ${member.role === 'admin' ? 'selected' : ''}>Admin</option>
                        <option value="owner" ${member.role === 'owner' ? 'selected' : ''}>Owner</option>
                    </select>
                </td>
                <td><small>${new Date(member.joined_at).toLocaleDateString()}</small></td>
                <td>
                    <button class="btn btn-sm btn-outline-danger" onclick="removeMember(${communityId}, ${member.user_id})">
                        <i class="bi bi-x-circle"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    
    html += '</tbody></table></div>';
    document.getElementById('membersContent').innerHTML = html;
}

function updateMemberRole(communityId, userId, role) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="update_role">
        <input type="hidden" name="community_id" value="${communityId}">
        <input type="hidden" name="user_id" value="${userId}">
        <input type="hidden" name="role" value="${role}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function removeMember(communityId, userId) {
    if (!confirm('Are you sure you want to remove this member?')) {
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="remove_member">
        <input type="hidden" name="community_id" value="${communityId}">
        <input type="hidden" name="user_id" value="${userId}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include '../includes/footer.php'; ?>