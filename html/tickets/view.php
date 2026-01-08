<?php
require_once '../includes/session.php';
require_once '../config/database.php';

$pageTitle = 'View Ticket';
$currentPage = 'tickets';

$ticketId = intval($_GET['id'] ?? 0);
if (!$ticketId) {
    header("Location: open.php");
    exit;
}

$isAdmin = isCurrentUserAdmin();
$userId = $_SESSION['user_id'];

try {
    $db = getDB();
    
    // Get ticket details
    $query = "
        SELECT 
            t.*,
            u.first_name, u.last_name, u.email,
            tc.name as category_name,
            a.first_name as assigned_first_name, a.last_name as assigned_last_name
        FROM tickets t
        JOIN users u ON t.user_id = u.id
        JOIN ticket_categories tc ON t.category_id = tc.id
        LEFT JOIN users a ON t.assigned_to = a.id
        WHERE t.id = ?
    ";
    
    // Admin can view all tickets, users only their own
    if (!$isAdmin) {
        $query .= " AND t.user_id = ?";
    }
    
    $stmt = $db->prepare($query);
    if ($isAdmin) {
        $stmt->execute([$ticketId]);
    } else {
        $stmt->execute([$ticketId, $userId]);
    }
    
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ticket) {
        $_SESSION['error'] = "Ticket not found or access denied.";
        header("Location: open.php");
        exit;
    }
    
    // Get ticket replies
    $stmt = $db->prepare("
        SELECT 
            tr.*,
            u.first_name, u.last_name, u.email, u.profile_photo
        FROM ticket_replies tr
        JOIN users u ON tr.user_id = u.id
        WHERE tr.ticket_id = ? 
        ORDER BY tr.created_at ASC
    ");
    $stmt->execute([$ticketId]);
    $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get status history
    $stmt = $db->prepare("
        SELECT 
            tsh.*,
            u.first_name, u.last_name
        FROM ticket_status_history tsh
        JOIN users u ON tsh.user_id = u.id
        WHERE tsh.ticket_id = ?
        ORDER BY tsh.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$ticketId]);
    $statusHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get available staff for assignment (admins only)
    $staffMembers = [];
    if ($isAdmin) {
        $stmt = $db->query("
            SELECT id, first_name, last_name, email 
            FROM users 
            WHERE global_role = 'admin' 
            ORDER BY first_name, last_name
        ");
        $staffMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    error_log("Error fetching ticket: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while loading the ticket.";
    header("Location: open.php");
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'reply') {
        $message = trim($_POST['message'] ?? '');
        $isInternal = isset($_POST['is_internal']) ? 1 : 0;
        
        if (!empty($message)) {
            try {
                $db->beginTransaction();
                
                // Insert reply
                $stmt = $db->prepare("
                    INSERT INTO ticket_replies (ticket_id, user_id, message, is_internal, is_staff_reply)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $ticketId,
                    $userId,
                    $message,
                    $isInternal,
                    $isAdmin ? 1 : 0
                ]);
                
                // Update ticket status if needed
                if ($ticket['status'] === 'new' && $isAdmin) {
                    $stmt = $db->prepare("UPDATE tickets SET status = 'open' WHERE id = ?");
                    $stmt->execute([$ticketId]);
                    
                    // Log status change
                    $stmt = $db->prepare("
                        INSERT INTO ticket_status_history (ticket_id, user_id, old_status, new_status, comment)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$ticketId, $userId, 'new', 'open', 'Status changed on first staff reply']);
                }
                
                $db->commit();
                $_SESSION['success'] = "Reply added successfully.";
                header("Location: view.php?id=" . $ticketId);
                exit;
                
            } catch (PDOException $e) {
                $db->rollBack();
                error_log("Error adding reply: " . $e->getMessage());
                $_SESSION['error'] = "Failed to add reply.";
            }
        }
    }
    
    // Admin actions
    if ($isAdmin) {
        if ($action === 'update_status') {
            $newStatus = $_POST['status'] ?? '';
            $comment = trim($_POST['comment'] ?? '');
            
            if (in_array($newStatus, ['new', 'open', 'in_progress', 'on_hold', 'resolved', 'closed'])) {
                try {
                    $db->beginTransaction();
                    
                    // Update ticket
                    $stmt = $db->prepare("UPDATE tickets SET status = ? WHERE id = ?");
                    $stmt->execute([$newStatus, $ticketId]);
                    
                    // Log status change
                    $stmt = $db->prepare("
                        INSERT INTO ticket_status_history (ticket_id, user_id, old_status, new_status, comment)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$ticketId, $userId, $ticket['status'], $newStatus, $comment]);
                    
                    $db->commit();
                    $_SESSION['success'] = "Status updated successfully.";
                    header("Location: view.php?id=" . $ticketId);
                    exit;
                    
                } catch (PDOException $e) {
                    $db->rollBack();
                    error_log("Error updating status: " . $e->getMessage());
                    $_SESSION['error'] = "Failed to update status.";
                }
            }
        }
        
        if ($action === 'assign') {
            $assignTo = intval($_POST['assign_to'] ?? 0);
            
            try {
                $db->beginTransaction();
                
                // Update ticket
                $stmt = $db->prepare("UPDATE tickets SET assigned_to = ? WHERE id = ?");
                $stmt->execute([$assignTo ?: null, $ticketId]);
                
                // Log assignment
                if ($assignTo) {
                    $stmt = $db->prepare("
                        INSERT INTO ticket_assignments (ticket_id, assigned_to, assigned_by)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$ticketId, $assignTo, $userId]);
                }
                
                $db->commit();
                $_SESSION['success'] = "Assignment updated successfully.";
                header("Location: view.php?id=" . $ticketId);
                exit;
                
            } catch (PDOException $e) {
                $db->rollBack();
                error_log("Error updating assignment: " . $e->getMessage());
                $_SESSION['error'] = "Failed to update assignment.";
            }
        }
    }
}

// Function to get status badge class
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'new': return 'bg-primary';
        case 'open': return 'bg-info';
        case 'in_progress': return 'bg-warning';
        case 'on_hold': return 'bg-secondary';
        case 'resolved': return 'bg-success';
        case 'closed': return 'bg-dark';
        default: return 'bg-secondary';
    }
}

// Function to get priority badge class
function getPriorityBadgeClass($priority) {
    switch ($priority) {
        case 'urgent': return 'bg-danger';
        case 'high': return 'bg-warning';
        case 'normal': return 'bg-info';
        case 'low': return 'bg-secondary';
        default: return 'bg-secondary';
    }
}

require_once '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-lg-8">
            <!-- Ticket Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="bi bi-ticket-detailed"></i> 
                            Ticket #<?php echo htmlspecialchars($ticket['ticket_number']); ?>
                        </h4>
                        <div>
                            <span class="badge <?php echo getPriorityBadgeClass($ticket['priority']); ?> me-2">
                                <?php echo ucfirst($ticket['priority']); ?> Priority
                            </span>
                            <span class="badge <?php echo getStatusBadgeClass($ticket['status']); ?>">
                                <?php echo str_replace('_', ' ', ucfirst($ticket['status'])); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <h5><?php echo htmlspecialchars($ticket['subject']); ?></h5>
                    <div class="text-muted mb-3">
                        <small>
                            Created by <?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?>
                            on <?php echo date('M d, Y g:i A', strtotime($ticket['created_at'])); ?>
                        </small>
                    </div>
                    <div class="mb-3">
                        <strong>Category:</strong> <?php echo htmlspecialchars($ticket['category_name']); ?>
                    </div>
                    <div class="ticket-description">
                        <?php echo nl2br(htmlspecialchars($ticket['description'])); ?>
                    </div>
                </div>
            </div>
            
            <!-- Conversation Thread -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-chat-dots"></i> Conversation</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($replies)): ?>
                        <p class="text-muted text-center">No replies yet.</p>
                    <?php else: ?>
                        <?php foreach ($replies as $reply): ?>
                            <?php if (!$reply['is_internal'] || $isAdmin): ?>
                                <div class="d-flex mb-3 <?php echo $reply['is_internal'] ? 'opacity-75' : ''; ?>">
                                    <div class="flex-shrink-0">
                                        <?php if ($reply['profile_photo']): ?>
                                            <img src="/uploads/avatars/<?php echo htmlspecialchars($reply['profile_photo']); ?>" 
                                                 class="rounded-circle" width="40" height="40" alt="Profile">
                                        <?php else: ?>
                                            <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center" 
                                                 style="width: 40px; height: 40px;">
                                                <i class="bi bi-person text-white"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <div class="border rounded p-3 <?php echo $reply['is_staff_reply'] ? 'bg-light' : ''; ?>">
                                            <div class="d-flex justify-content-between mb-2">
                                                <strong>
                                                    <?php echo htmlspecialchars($reply['first_name'] . ' ' . $reply['last_name']); ?>
                                                    <?php if ($reply['is_staff_reply']): ?>
                                                        <span class="badge bg-primary ms-1">Staff</span>
                                                    <?php endif; ?>
                                                    <?php if ($reply['is_internal']): ?>
                                                        <span class="badge bg-warning ms-1">Internal Note</span>
                                                    <?php endif; ?>
                                                </strong>
                                                <small class="text-muted">
                                                    <?php echo date('M d, Y g:i A', strtotime($reply['created_at'])); ?>
                                                </small>
                                            </div>
                                            <div><?php echo nl2br(htmlspecialchars($reply['message'])); ?></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <!-- Reply Form -->
                    <?php if (!in_array($ticket['status'], ['closed', 'resolved']) || $isAdmin): ?>
                        <hr class="my-4">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="reply">
                            <div class="mb-3">
                                <label for="message" class="form-label">Add Reply</label>
                                <textarea class="form-control" id="message" name="message" rows="4" required></textarea>
                            </div>
                            <?php if ($isAdmin): ?>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="is_internal" name="is_internal">
                                    <label class="form-check-label" for="is_internal">
                                        Internal note (not visible to user)
                                    </label>
                                </div>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send"></i> Send Reply
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> This ticket is closed. No new replies can be added.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Ticket Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> Ticket Information</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Status:</dt>
                        <dd class="col-sm-7">
                            <span class="badge <?php echo getStatusBadgeClass($ticket['status']); ?>">
                                <?php echo str_replace('_', ' ', ucfirst($ticket['status'])); ?>
                            </span>
                        </dd>
                        
                        <dt class="col-sm-5">Priority:</dt>
                        <dd class="col-sm-7">
                            <span class="badge <?php echo getPriorityBadgeClass($ticket['priority']); ?>">
                                <?php echo ucfirst($ticket['priority']); ?>
                            </span>
                        </dd>
                        
                        <dt class="col-sm-5">Category:</dt>
                        <dd class="col-sm-7"><?php echo htmlspecialchars($ticket['category_name']); ?></dd>
                        
                        <dt class="col-sm-5">Assigned To:</dt>
                        <dd class="col-sm-7">
                            <?php if ($ticket['assigned_to']): ?>
                                <?php echo htmlspecialchars($ticket['assigned_first_name'] . ' ' . $ticket['assigned_last_name']); ?>
                            <?php else: ?>
                                <span class="text-muted">Unassigned</span>
                            <?php endif; ?>
                        </dd>
                        
                        <dt class="col-sm-5">Created:</dt>
                        <dd class="col-sm-7"><?php echo date('M d, Y', strtotime($ticket['created_at'])); ?></dd>
                        
                        <?php if ($ticket['closed_at']): ?>
                            <dt class="col-sm-5">Closed:</dt>
                            <dd class="col-sm-7"><?php echo date('M d, Y', strtotime($ticket['closed_at'])); ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
            
            <?php if ($isAdmin): ?>
                <!-- Admin Actions -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-gear"></i> Actions</h5>
                    </div>
                    <div class="card-body">
                        <!-- Update Status -->
                        <form method="POST" action="" class="mb-3">
                            <input type="hidden" name="action" value="update_status">
                            <label for="status" class="form-label">Update Status</label>
                            <select class="form-select mb-2" id="status" name="status">
                                <option value="new" <?php echo $ticket['status'] == 'new' ? 'selected' : ''; ?>>New</option>
                                <option value="open" <?php echo $ticket['status'] == 'open' ? 'selected' : ''; ?>>Open</option>
                                <option value="in_progress" <?php echo $ticket['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="on_hold" <?php echo $ticket['status'] == 'on_hold' ? 'selected' : ''; ?>>On Hold</option>
                                <option value="resolved" <?php echo $ticket['status'] == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                <option value="closed" <?php echo $ticket['status'] == 'closed' ? 'selected' : ''; ?>>Closed</option>
                            </select>
                            <input type="text" class="form-control mb-2" name="comment" placeholder="Comment (optional)">
                            <button type="submit" class="btn btn-sm btn-primary w-100">Update Status</button>
                        </form>
                        
                        <!-- Assign Ticket -->
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="assign">
                            <label for="assign_to" class="form-label">Assign To</label>
                            <select class="form-select mb-2" id="assign_to" name="assign_to">
                                <option value="0">Unassigned</option>
                                <?php foreach ($staffMembers as $staff): ?>
                                    <option value="<?php echo $staff['id']; ?>" 
                                            <?php echo $ticket['assigned_to'] == $staff['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-sm btn-primary w-100">Update Assignment</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Status History -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Status History</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($statusHistory)): ?>
                        <p class="text-muted text-center mb-0">No status changes yet.</p>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($statusHistory as $history): ?>
                                <div class="mb-3">
                                    <small class="text-muted d-block">
                                        <?php echo date('M d, Y g:i A', strtotime($history['created_at'])); ?>
                                    </small>
                                    <div>
                                        <?php if ($history['old_status']): ?>
                                            <span class="badge bg-secondary"><?php echo ucfirst($history['old_status']); ?></span>
                                            <i class="bi bi-arrow-right mx-1"></i>
                                        <?php endif; ?>
                                        <span class="badge bg-primary"><?php echo ucfirst($history['new_status']); ?></span>
                                    </div>
                                    <small class="text-muted">
                                        by <?php echo htmlspecialchars($history['first_name'] . ' ' . $history['last_name']); ?>
                                    </small>
                                    <?php if ($history['comment']): ?>
                                        <div class="mt-1">
                                            <small><em><?php echo htmlspecialchars($history['comment']); ?></em></small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.ticket-description {
    white-space: pre-wrap;
    word-wrap: break-word;
}
.timeline > div:not(:last-child) {
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 0.5rem;
}
</style>

<?php require_once '../includes/footer.php'; ?>