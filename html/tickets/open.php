<?php
require_once '../includes/session.php';
require_once '../config/database.php';

$pageTitle = 'Open Tickets';
$currentPage = 'tickets';

$isAdmin = isCurrentUserAdmin();
$userId = $_SESSION['user_id'];

// Get filter parameters
$categoryFilter = $_GET['category'] ?? '';
$priorityFilter = $_GET['priority'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

try {
    $db = getDB();
    
    // Build the query
    $whereConditions = ["t.status IN ('new', 'open', 'in_progress', 'on_hold')"];
    $params = [];
    
    // Admin sees all tickets, users see only their own
    if (!$isAdmin) {
        $whereConditions[] = "t.user_id = ?";
        $params[] = $userId;
    }
    
    // Apply filters
    if ($categoryFilter) {
        $whereConditions[] = "t.category_id = ?";
        $params[] = $categoryFilter;
    }
    
    if ($priorityFilter) {
        $whereConditions[] = "t.priority = ?";
        $params[] = $priorityFilter;
    }
    
    if ($searchQuery) {
        $whereConditions[] = "(t.subject LIKE ? OR t.description LIKE ? OR t.ticket_number LIKE ?)";
        $searchTerm = "%$searchQuery%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $whereClause = implode(" AND ", $whereConditions);
    
    // Count total tickets
    $countQuery = "SELECT COUNT(*) FROM tickets t WHERE $whereClause";
    $stmt = $db->prepare($countQuery);
    $stmt->execute($params);
    $totalTickets = $stmt->fetchColumn();
    $totalPages = ceil($totalTickets / $perPage);
    
    // Get tickets
    $query = "
        SELECT 
            t.*,
            u.first_name, u.last_name, u.email,
            tc.name as category_name,
            a.first_name as assigned_first_name, a.last_name as assigned_last_name,
            (SELECT COUNT(*) FROM ticket_replies WHERE ticket_id = t.id) as reply_count,
            TIMESTAMPDIFF(HOUR, t.created_at, NOW()) as hours_ago
        FROM tickets t
        JOIN users u ON t.user_id = u.id
        JOIN ticket_categories tc ON t.category_id = tc.id
        LEFT JOIN users a ON t.assigned_to = a.id
        WHERE $whereClause
        ORDER BY 
            FIELD(t.priority, 'urgent', 'high', 'normal', 'low'),
            t.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $perPage;
    $params[] = $offset;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get categories for filter
    $stmt = $db->query("SELECT id, name FROM ticket_categories WHERE is_active = 1 ORDER BY sort_order, name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Error fetching tickets: " . $e->getMessage());
    $tickets = [];
    $totalTickets = 0;
    $totalPages = 0;
}

// Function to format time ago
function formatTimeAgo($hours) {
    if ($hours < 1) return "Just now";
    if ($hours < 24) return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    $days = floor($hours / 24);
    if ($days < 7) return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    $weeks = floor($days / 7);
    if ($weeks < 4) return $weeks . " week" . ($weeks > 1 ? "s" : "") . " ago";
    $months = floor($days / 30);
    return $months . " month" . ($months > 1 ? "s" : "") . " ago";
}

// Function to get status badge class
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'new': return 'bg-primary';
        case 'open': return 'bg-info';
        case 'in_progress': return 'bg-warning';
        case 'on_hold': return 'bg-secondary';
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
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-ticket-detailed"></i> Open Tickets</h2>
                <a href="new.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> New Ticket
                </a>
            </div>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-3">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="Ticket #, subject, or description"
                                   value="<?php echo htmlspecialchars($searchQuery); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="category" class="form-label">Category</label>
                            <select class="form-select" id="category" name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>"
                                            <?php echo $categoryFilter == $category['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="priority" class="form-label">Priority</label>
                            <select class="form-select" id="priority" name="priority">
                                <option value="">All Priorities</option>
                                <option value="urgent" <?php echo $priorityFilter == 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                                <option value="high" <?php echo $priorityFilter == 'high' ? 'selected' : ''; ?>>High</option>
                                <option value="normal" <?php echo $priorityFilter == 'normal' ? 'selected' : ''; ?>>Normal</option>
                                <option value="low" <?php echo $priorityFilter == 'low' ? 'selected' : ''; ?>>Low</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="bi bi-search"></i> Filter
                            </button>
                            <a href="open.php" class="btn btn-secondary">Clear</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Tickets Table -->
            <div class="card">
                <div class="card-body">
                    <?php if (empty($tickets)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-ticket fs-1 text-muted"></i>
                            <p class="mt-3 text-muted">No open tickets found.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Ticket #</th>
                                        <th>Subject</th>
                                        <?php if ($isAdmin): ?>
                                            <th>User</th>
                                        <?php endif; ?>
                                        <th>Category</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Assigned To</th>
                                        <th>Replies</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tickets as $ticket): ?>
                                        <tr style="cursor: pointer;" onclick="window.location='view.php?id=<?php echo $ticket['id']; ?>'">
                                            <td class="fw-bold">#<?php echo htmlspecialchars($ticket['ticket_number']); ?></td>
                                            <td>
                                                <div class="text-truncate" style="max-width: 300px;">
                                                    <?php echo htmlspecialchars($ticket['subject']); ?>
                                                </div>
                                            </td>
                                            <?php if ($isAdmin): ?>
                                                <td><?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?></td>
                                            <?php endif; ?>
                                            <td><?php echo htmlspecialchars($ticket['category_name']); ?></td>
                                            <td>
                                                <span class="badge <?php echo getPriorityBadgeClass($ticket['priority']); ?>">
                                                    <?php echo ucfirst($ticket['priority']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo getStatusBadgeClass($ticket['status']); ?>">
                                                    <?php echo str_replace('_', ' ', ucfirst($ticket['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($ticket['assigned_to']): ?>
                                                    <?php echo htmlspecialchars($ticket['assigned_first_name'] . ' ' . $ticket['assigned_last_name']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Unassigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($ticket['reply_count'] > 0): ?>
                                                    <span class="badge bg-secondary"><?php echo $ticket['reply_count']; ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">0</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo formatTimeAgo($ticket['hours_ago']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Ticket pagination" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($searchQuery); ?>&category=<?php echo $categoryFilter; ?>&priority=<?php echo $priorityFilter; ?>">
                                            Previous
                                        </a>
                                    </li>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($searchQuery); ?>&category=<?php echo $categoryFilter; ?>&priority=<?php echo $priorityFilter; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($searchQuery); ?>&category=<?php echo $categoryFilter; ?>&priority=<?php echo $priorityFilter; ?>">
                                            Next
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>