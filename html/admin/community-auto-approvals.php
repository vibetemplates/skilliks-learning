<?php
/**
 * Community Auto-Approvals Management
 * 
 * Allows admins to manage auto-approval list for a community
 */

require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../config/functions.php';
require_once '../includes/session.php';
require_once '../classes/Community.php';
require_once '../classes/CommunityAutoApproval.php';

// Check if user is admin
if (!isCurrentUserAdmin()) {
    header('Location: /dashboard');
    exit;
}

// Get community ID from URL
$community_id = isset($_GET['community_id']) ? (int)$_GET['community_id'] : 0;

if (!$community_id) {
    header('Location: /admin/communities.php');
    exit;
}

// Initialize classes
$community = new Community();
$autoApproval = new CommunityAutoApproval();

// Get community details
$communityData = $community->getById($community_id);

if (!$communityData) {
    setFlashMessage('error', 'Community not found.');
    header('Location: /admin/communities.php');
    exit;
}

$page_title = 'Auto-Approvals - ' . htmlspecialchars($communityData['name']);
$current_page = 'communities';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $email = trim($_POST['email'] ?? '');
                $username = trim($_POST['username'] ?? '');
                $description = trim($_POST['description'] ?? '');
                
                if ($email || $username) {
                    try {
                        $autoApproval->add($community_id, 
                            getCurrentUserId(),
                            $email ?: null, 
                            $username ?: null, 
                            $description
                        );
                        setFlashMessage('success', 'Auto-approval rule added successfully.');
                    } catch (Exception $e) {
                        setFlashMessage('error', 'Failed to add auto-approval rule: ' . $e->getMessage());
                    }
                }
                break;
                
            case 'toggle':
                if (isset($_POST['rule_id'])) {
                    $autoApproval->toggleActive($_POST['rule_id']);
                    setFlashMessage('success', 'Auto-approval rule status updated.');
                }
                break;
                
            case 'delete':
                if (isset($_POST['rule_id'])) {
                    $autoApproval->delete($_POST['rule_id']);
                    setFlashMessage('success', 'Auto-approval rule deleted.');
                }
                break;
        }
        
        // Redirect to prevent form resubmission
        header('Location: /admin/community-auto-approvals.php?community_id=' . $community_id);
        exit;
    }
}

// Get all auto-approval rules for this community
$rules = $autoApproval->getByCommunity($community_id);

require_once '../includes/header.php';
?>

<main class="container-fluid px-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><?php echo $page_title; ?></h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="/admin/communities.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Communities
                    </a>
                </div>
            </div>


            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Community Details</h5>
                </div>
                <div class="card-body">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($communityData['name']); ?></p>
                    <p><strong>Description:</strong> <?php echo htmlspecialchars($communityData['description'] ?? 'No description'); ?></p>
                    <p><strong>Requires Approval:</strong> 
                        <?php if ($communityData['requires_approval']): ?>
                            <span class="badge bg-warning">Yes</span>
                        <?php else: ?>
                            <span class="badge bg-success">No</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Add Auto-Approval Rule</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           placeholder="user@example.com">
                                    <small class="form-text text-muted">Leave empty to match by username only</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           placeholder="github_username">
                                    <small class="form-text text-muted">Leave empty to match by email only</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <input type="text" class="form-control" id="description" name="description" 
                                           placeholder="Optional note">
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Add Rule
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Auto-Approval Rules</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($rules)): ?>
                        <p class="text-muted">No auto-approval rules have been configured for this community.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Email</th>
                                        <th>Username</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                        <th>Added By</th>
                                        <th>Added On</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rules as $rule): ?>
                                        <tr>
                                            <td>
                                                <?php if ($rule['email']): ?>
                                                    <code><?php echo htmlspecialchars($rule['email']); ?></code>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($rule['username']): ?>
                                                    <code><?php echo htmlspecialchars($rule['username']); ?></code>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($rule['description'] ?? '-'); ?></td>
                                            <td>
                                                <?php if ($rule['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($rule['first_name'] . ' ' . $rule['last_name']); ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($rule['created_at'])); ?></td>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="toggle">
                                                    <input type="hidden" name="rule_id" value="<?php echo $rule['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary" 
                                                            title="<?php echo $rule['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                        <i class="bi bi-<?php echo $rule['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;" 
                                                      onsubmit="return confirm('Are you sure you want to delete this rule?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="rule_id" value="<?php echo $rule['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
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

<?php require_once '../includes/footer.php'; ?>