<?php
require_once '../includes/session.php';
require_once '../config/database.php';

$pageTitle = 'Submit New Ticket';
$currentPage = 'tickets';

// Get ticket categories
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, name FROM ticket_categories WHERE is_active = 1 ORDER BY sort_order, name");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    $categories = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $category_id = $_POST['category_id'] ?? '';
    $priority = $_POST['priority'] ?? 'normal';
    $description = trim($_POST['description'] ?? '');
    
    $errors = [];
    
    // Validate inputs
    if (empty($subject)) {
        $errors[] = "Subject is required.";
    }
    if (empty($category_id)) {
        $errors[] = "Please select a category.";
    }
    if (empty($description)) {
        $errors[] = "Description is required.";
    }
    if (!in_array($priority, ['low', 'normal', 'high', 'urgent'])) {
        $priority = 'normal';
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Generate unique ticket number
            $year = date('Y');
            $stmt = $db->prepare("SELECT COUNT(*) + 1 as next_num FROM tickets WHERE YEAR(created_at) = ?");
            $stmt->execute([$year]);
            $nextNum = $stmt->fetch()['next_num'];
            $ticketNumber = sprintf("%s-%06d", $year, $nextNum);
            
            // Insert ticket
            $stmt = $db->prepare("
                INSERT INTO tickets (ticket_number, user_id, category_id, subject, description, status, priority)
                VALUES (?, ?, ?, ?, ?, 'new', ?)
            ");
            $stmt->execute([
                $ticketNumber,
                $_SESSION['user_id'],
                $category_id,
                $subject,
                $description,
                $priority
            ]);
            
            $ticketId = $db->lastInsertId();
            
            // Log status history
            $stmt = $db->prepare("
                INSERT INTO ticket_status_history (ticket_id, user_id, new_status, comment)
                VALUES (?, ?, 'new', 'Ticket created')
            ");
            $stmt->execute([$ticketId, $_SESSION['user_id']]);
            
            $db->commit();
            
            $_SESSION['success'] = "Ticket #$ticketNumber has been created successfully.";
            header("Location: view.php?id=" . $ticketId);
            exit;
            
        } catch (PDOException $e) {
            $db->rollBack();
            error_log("Error creating ticket: " . $e->getMessage());
            $errors[] = "An error occurred while creating the ticket. Please try again.";
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="bi bi-ticket-detailed"></i> Submit New Ticket</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="subject" name="subject" 
                                   value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" required>
                            <div class="form-text">Brief summary of your issue or request</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">Select a category...</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" 
                                                <?php echo (($_POST['category_id'] ?? '') == $category['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="priority" class="form-label">Priority</label>
                                <select class="form-select" id="priority" name="priority">
                                    <option value="low" <?php echo (($_POST['priority'] ?? 'normal') == 'low') ? 'selected' : ''; ?>>
                                        Low - Can wait
                                    </option>
                                    <option value="normal" <?php echo (($_POST['priority'] ?? 'normal') == 'normal') ? 'selected' : ''; ?>>
                                        Normal - Standard request
                                    </option>
                                    <option value="high" <?php echo (($_POST['priority'] ?? 'normal') == 'high') ? 'selected' : ''; ?>>
                                        High - Important issue
                                    </option>
                                    <option value="urgent" <?php echo (($_POST['priority'] ?? 'normal') == 'urgent') ? 'selected' : ''; ?>>
                                        Urgent - Critical issue
                                    </option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="8" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            <div class="form-text">Please provide as much detail as possible about your issue or request</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="attachments" class="form-label">Attachments</label>
                            <input type="file" class="form-control" id="attachments" name="attachments[]" multiple>
                            <div class="form-text">You can attach screenshots or documents (Max 5MB per file)</div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="/dashboard" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send"></i> Submit Ticket
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>