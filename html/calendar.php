<?php
/**
 * Calendar Page
 * 
 * Displays community calendar with events
 */

$page_title = 'Calendar';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Calendar.php';
require_once 'classes/Project.php';
require_once 'classes/Community.php';

// Require login
requireLogin();

$currentUserId = getCurrentUserId();
$communityId = getCurrentCommunityId();

// Get current community name
$communityObj = new Community();
$currentCommunity = $communityObj->getById($communityId);
$currentCommunityName = $currentCommunity ? htmlspecialchars($currentCommunity['name']) : 'Community';

// Initialize calendar
$calendar = new Calendar();
$project = new Project();

// Check if user can manage events
$canManage = $calendar->canManageEvents($currentUserId, $communityId);

// Get view mode (month, week, day)
$view = $_GET['view'] ?? 'month';

// Get current date or selected date
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$selectedDateTime = new DateTime($selectedDate);

// Calculate date range based on view
switch ($view) {
    case 'week':
        $startDate = clone $selectedDateTime;
        $startDate->modify('monday this week');
        $endDate = clone $startDate;
        $endDate->modify('+6 days');
        break;
    case 'day':
        $startDate = clone $selectedDateTime;
        $endDate = clone $selectedDateTime;
        break;
    case 'month':
    default:
        $startDate = clone $selectedDateTime;
        $startDate->modify('first day of this month');
        $endDate = clone $selectedDateTime;
        $endDate->modify('last day of this month');
        // Extend to full weeks
        $startDate->modify('monday this week');
        $endDate->modify('sunday this week');
        break;
}

// Get events for the date range
$events = $calendar->getEventsByDateRange(
    $communityId,
    $startDate->format('Y-m-d 00:00:00'),
    $endDate->format('Y-m-d 23:59:59')
);

// Get upcoming events
$upcomingEvents = $calendar->getUpcomingEvents($communityId, 5);

// Get user's projects and courses for the event form
if ($canManage) {
    $userProjects = $project->findByMember($currentUserId);
    
    // Get courses
    $db = getDB();
    $stmt = $db->prepare("
        SELECT id, title 
        FROM courses 
        WHERE community_id = :community_id 
        AND status = 'published'
        ORDER BY title ASC
    ");
    $stmt->execute([':community_id' => $communityId]);
    $courses = $stmt->fetchAll();
}

require_once 'includes/header.php';
?>

<!-- Main content -->
<main class="container-fluid px-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><?php echo $currentCommunityName; ?> - Calendar</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <button type="button" class="btn btn-sm btn-outline-secondary <?php echo $view === 'month' ? 'active' : ''; ?>" 
                        onclick="changeView('month')">Month</button>
                <button type="button" class="btn btn-sm btn-outline-secondary <?php echo $view === 'week' ? 'active' : ''; ?>" 
                        onclick="changeView('week')">Week</button>
                <button type="button" class="btn btn-sm btn-outline-secondary <?php echo $view === 'day' ? 'active' : ''; ?>" 
                        onclick="changeView('day')">Day</button>
            </div>
            <?php if ($canManage): ?>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createEventModal">
                <i class="bi bi-plus-circle"></i> New Event
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <!-- Calendar View -->
        <div class="col-lg-9">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <button class="btn btn-sm btn-outline-secondary" onclick="navigateCalendar('prev')">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="navigateCalendar('today')">
                                Today
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="navigateCalendar('next')">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                        <h5 class="mb-0" id="calendar-title">
                            <?php echo $selectedDateTime->format('F Y'); ?>
                        </h5>
                        <div>
                            <!-- Legend -->
                            <small class="text-muted">
                                <span class="badge bg-primary">Community</span>
                                <span class="badge bg-success">Project</span>
                                <span class="badge bg-info">Course</span>
                            </small>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if ($view === 'month'): ?>
                        <!-- Month View -->
                        <div class="calendar-month">
                            <table class="table table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th width="14.28%">Mon</th>
                                        <th width="14.28%">Tue</th>
                                        <th width="14.28%">Wed</th>
                                        <th width="14.28%">Thu</th>
                                        <th width="14.28%">Fri</th>
                                        <th width="14.28%">Sat</th>
                                        <th width="14.28%">Sun</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $currentDate = clone $startDate;
                                    $monthNum = $selectedDateTime->format('n');
                                    
                                    while ($currentDate <= $endDate):
                                        if ($currentDate->format('N') == 1): // Start new row on Monday
                                    ?>
                                    <tr>
                                    <?php endif; ?>
                                        <td class="calendar-day <?php echo $currentDate->format('n') != $monthNum ? 'text-muted' : ''; ?> 
                                                   <?php echo $currentDate->format('Y-m-d') == date('Y-m-d') ? 'bg-light' : ''; ?>"
                                            style="height: 100px; vertical-align: top;">
                                            <div class="day-number"><?php echo $currentDate->format('j'); ?></div>
                                            <div class="day-events">
                                                <?php
                                                foreach ($events as $event) {
                                                    $eventStart = new DateTime($event['start_datetime']);
                                                    $eventEnd = new DateTime($event['end_datetime']);
                                                    
                                                    if ($eventStart->format('Y-m-d') <= $currentDate->format('Y-m-d') &&
                                                        $eventEnd->format('Y-m-d') >= $currentDate->format('Y-m-d')) {
                                                        
                                                        $badgeClass = 'bg-primary';
                                                        if ($event['project_id']) $badgeClass = 'bg-success';
                                                        if ($event['course_id']) $badgeClass = 'bg-info';
                                                        
                                                        $eventTime = $event['all_day'] ? 'All Day' : $eventStart->format('g:i A');
                                                ?>
                                                    <div class="calendar-event mb-1" 
                                                         onclick="viewEvent(<?php echo $event['id']; ?>)"
                                                         style="cursor: pointer;">
                                                        <small class="badge <?php echo $badgeClass; ?> text-truncate d-block">
                                                            <?php echo htmlspecialchars($eventTime . ' - ' . $event['title']); ?>
                                                        </small>
                                                    </div>
                                                <?php
                                                    }
                                                }
                                                ?>
                                            </div>
                                        </td>
                                    <?php if ($currentDate->format('N') == 7): // End row on Sunday ?>
                                    </tr>
                                    <?php 
                                        endif;
                                        $currentDate->modify('+1 day');
                                    endwhile;
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    <?php elseif ($view === 'week'): ?>
                        <!-- Week View -->
                        <div class="calendar-week">
                            <table class="table table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th width="10%">Time</th>
                                        <?php
                                        $weekDate = clone $startDate;
                                        for ($i = 0; $i < 7; $i++):
                                        ?>
                                        <th width="12.85%" class="<?php echo $weekDate->format('Y-m-d') == date('Y-m-d') ? 'bg-light' : ''; ?>">
                                            <?php echo $weekDate->format('D j'); ?>
                                        </th>
                                        <?php 
                                            $weekDate->modify('+1 day');
                                        endfor;
                                        ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php for ($hour = 0; $hour < 24; $hour++): ?>
                                    <tr>
                                        <td class="text-muted small"><?php echo sprintf('%02d:00', $hour); ?></td>
                                        <?php
                                        $weekDate = clone $startDate;
                                        for ($i = 0; $i < 7; $i++):
                                        ?>
                                        <td class="calendar-hour">
                                            <?php
                                            foreach ($events as $event) {
                                                $eventStart = new DateTime($event['start_datetime']);
                                                $eventHour = (int)$eventStart->format('G');
                                                
                                                if ($eventStart->format('Y-m-d') == $weekDate->format('Y-m-d') && 
                                                    $eventHour == $hour && !$event['all_day']) {
                                                    
                                                    $badgeClass = 'bg-primary';
                                                    if ($event['project_id']) $badgeClass = 'bg-success';
                                                    if ($event['course_id']) $badgeClass = 'bg-info';
                                            ?>
                                                <div class="calendar-event" onclick="viewEvent(<?php echo $event['id']; ?>)"
                                                     style="cursor: pointer;">
                                                    <small class="badge <?php echo $badgeClass; ?> text-truncate d-block">
                                                        <?php echo htmlspecialchars($event['title']); ?>
                                                    </small>
                                                </div>
                                            <?php
                                                }
                                            }
                                            ?>
                                        </td>
                                        <?php 
                                            $weekDate->modify('+1 day');
                                        endfor;
                                        ?>
                                    </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <!-- Day View -->
                        <div class="calendar-day-view">
                            <table class="table table-bordered mb-0">
                                <tbody>
                                    <?php for ($hour = 0; $hour < 24; $hour++): ?>
                                    <tr>
                                        <td width="15%" class="text-muted small"><?php echo sprintf('%02d:00', $hour); ?></td>
                                        <td>
                                            <?php
                                            foreach ($events as $event) {
                                                $eventStart = new DateTime($event['start_datetime']);
                                                $eventHour = (int)$eventStart->format('G');
                                                
                                                if ($eventHour == $hour && !$event['all_day']) {
                                                    $badgeClass = 'bg-primary';
                                                    if ($event['project_id']) $badgeClass = 'bg-success';
                                                    if ($event['course_id']) $badgeClass = 'bg-info';
                                            ?>
                                                <div class="calendar-event mb-2 p-2 border rounded" 
                                                     onclick="viewEvent(<?php echo $event['id']; ?>)"
                                                     style="cursor: pointer;">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <h6 class="mb-1"><?php echo htmlspecialchars($event['title']); ?></h6>
                                                            <p class="mb-1 text-muted small">
                                                                <?php echo $eventStart->format('g:i A') . ' - ' . (new DateTime($event['end_datetime']))->format('g:i A'); ?>
                                                            </p>
                                                            <?php if ($event['location']): ?>
                                                            <p class="mb-1 small">
                                                                <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($event['location']); ?>
                                                            </p>
                                                            <?php endif; ?>
                                                            <?php if ($event['zoom_link']): ?>
                                                            <p class="mb-0 small">
                                                                <i class="bi bi-camera-video"></i> 
                                                                <a href="<?php echo htmlspecialchars($event['zoom_link']); ?>" target="_blank">Join Zoom</a>
                                                            </p>
                                                            <?php endif; ?>
                                                        </div>
                                                        <span class="badge <?php echo $badgeClass; ?>">
                                                            <?php 
                                                            if ($event['project_id']) echo 'Project';
                                                            elseif ($event['course_id']) echo 'Course';
                                                            else echo 'Community';
                                                            ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php
                                                }
                                            }
                                            
                                            // Show all-day events at the top
                                            if ($hour == 0) {
                                                foreach ($events as $event) {
                                                    if ($event['all_day']) {
                                                        $badgeClass = 'bg-primary';
                                                        if ($event['project_id']) $badgeClass = 'bg-success';
                                                        if ($event['course_id']) $badgeClass = 'bg-info';
                                            ?>
                                                <div class="calendar-event mb-2 p-2 border rounded bg-light" 
                                                     onclick="viewEvent(<?php echo $event['id']; ?>)"
                                                     style="cursor: pointer;">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <h6 class="mb-1"><?php echo htmlspecialchars($event['title']); ?></h6>
                                                            <p class="mb-0 text-muted small">All Day Event</p>
                                                        </div>
                                                        <span class="badge <?php echo $badgeClass; ?>">
                                                            <?php 
                                                            if ($event['project_id']) echo 'Project';
                                                            elseif ($event['course_id']) echo 'Course';
                                                            else echo 'Community';
                                                            ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php
                                                    }
                                                }
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-3">
            <!-- Upcoming Events -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Upcoming Events</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($upcomingEvents)): ?>
                        <p class="text-muted mb-0">No upcoming events</p>
                    <?php else: ?>
                        <?php foreach ($upcomingEvents as $event): 
                            $eventDate = new DateTime($event['start_datetime']);
                            $badgeClass = 'bg-primary';
                            if ($event['project_id']) $badgeClass = 'bg-success';
                            if ($event['course_id']) $badgeClass = 'bg-info';
                        ?>
                        <div class="mb-3 pb-3 border-bottom" onclick="viewEvent(<?php echo $event['id']; ?>)"
                             style="cursor: pointer;">
                            <div class="d-flex justify-content-between mb-1">
                                <h6 class="mb-0"><?php echo htmlspecialchars($event['title']); ?></h6>
                                <span class="badge <?php echo $badgeClass; ?> small">
                                    <?php 
                                    if ($event['project_id']) echo 'Project';
                                    elseif ($event['course_id']) echo 'Course';
                                    else echo 'Community';
                                    ?>
                                </span>
                            </div>
                            <p class="text-muted small mb-1">
                                <?php echo $eventDate->format('M j, Y'); ?>
                                <?php if (!$event['all_day']): ?>
                                    at <?php echo $eventDate->format('g:i A'); ?>
                                <?php else: ?>
                                    - All Day
                                <?php endif; ?>
                            </p>
                            <?php if ($event['location']): ?>
                            <p class="small mb-0">
                                <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($event['location']); ?>
                            </p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Mini Calendar -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">Jump to Date</h5>
                </div>
                <div class="card-body">
                    <input type="date" class="form-control" id="jump-date" 
                           value="<?php echo $selectedDate; ?>"
                           onchange="jumpToDate(this.value)">
                </div>
            </div>
        </div>
    </div>
</main>

<?php if ($canManage): ?>
<!-- Create Event Modal -->
<div class="modal fade" id="createEventModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createEventForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label for="event-title" class="form-label">Event Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="event-title" name="title" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="event-start" class="form-label">Start Date/Time <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" id="event-start" name="start_datetime" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="event-end" class="form-label">End Date/Time <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" id="event-end" name="end_datetime" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="event-all-day" name="all_day">
                            <label class="form-check-label" for="event-all-day">
                                All Day Event
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="event-description" class="form-label">Description</label>
                        <textarea class="form-control" id="event-description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="event-location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="event-location" name="location">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="event-zoom" class="form-label">Zoom Link</label>
                                <input type="url" class="form-control" id="event-zoom" name="zoom_link" 
                                       placeholder="https://zoom.us/j/...">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="event-project" class="form-label">Link to Project (Optional)</label>
                                <select class="form-select" id="event-project" name="project_id">
                                    <option value="">None</option>
                                    <?php foreach ($userProjects as $proj): ?>
                                    <option value="<?php echo $proj['id']; ?>">
                                        <?php echo htmlspecialchars($proj['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="event-course" class="form-label">Link to Course (Optional)</label>
                                <select class="form-select" id="event-course" name="course_id">
                                    <option value="">None</option>
                                    <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>">
                                        <?php echo htmlspecialchars($course['title']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="event-color" class="form-label">Event Color</label>
                                <input type="color" class="form-control form-control-color" id="event-color" 
                                       name="color" value="#0d6efd">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="event-recurrence" class="form-label">Recurrence</label>
                                <select class="form-select" id="event-recurrence" name="recurrence_type">
                                    <option value="none">Does not repeat</option>
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3" id="recurrence-end-container" style="display: none;">
                        <label for="event-recurrence-end" class="form-label">Repeat Until</label>
                        <input type="date" class="form-control" id="event-recurrence-end" name="recurrence_end_date">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Event</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- View Event Modal -->
<div class="modal fade" id="viewEventModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="view-event-title">Event Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="view-event-content">
                <!-- Event details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <?php if ($canManage): ?>
                <button type="button" class="btn btn-primary" id="edit-event-btn" style="display: none;">
                    <i class="bi bi-pencil"></i> Edit
                </button>
                <button type="button" class="btn btn-danger" id="delete-event-btn" style="display: none;">
                    <i class="bi bi-trash"></i> Delete
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.calendar-month td {
    position: relative;
}

.calendar-month .day-number {
    font-weight: 500;
    margin-bottom: 5px;
}

.calendar-month .calendar-event {
    font-size: 0.75rem;
    overflow: hidden;
}

.calendar-week td.calendar-hour,
.calendar-day-view td {
    height: 60px;
    vertical-align: top;
}

.calendar-week .calendar-event {
    font-size: 0.75rem;
}
</style>

<script>
// Calendar navigation
function changeView(view) {
    const currentParams = new URLSearchParams(window.location.search);
    currentParams.set('view', view);
    window.location.search = currentParams.toString();
}

function navigateCalendar(direction) {
    const currentParams = new URLSearchParams(window.location.search);
    const view = currentParams.get('view') || 'month';
    const currentDate = currentParams.get('date') || '<?php echo date('Y-m-d'); ?>';
    const date = new Date(currentDate);
    
    if (direction === 'today') {
        currentParams.set('date', new Date().toISOString().split('T')[0]);
    } else if (direction === 'prev') {
        switch (view) {
            case 'day':
                date.setDate(date.getDate() - 1);
                break;
            case 'week':
                date.setDate(date.getDate() - 7);
                break;
            default:
                date.setMonth(date.getMonth() - 1);
        }
        currentParams.set('date', date.toISOString().split('T')[0]);
    } else if (direction === 'next') {
        switch (view) {
            case 'day':
                date.setDate(date.getDate() + 1);
                break;
            case 'week':
                date.setDate(date.getDate() + 7);
                break;
            default:
                date.setMonth(date.getMonth() + 1);
        }
        currentParams.set('date', date.toISOString().split('T')[0]);
    }
    
    window.location.search = currentParams.toString();
}

function jumpToDate(date) {
    const currentParams = new URLSearchParams(window.location.search);
    currentParams.set('date', date);
    window.location.search = currentParams.toString();
}

// Event handling
document.getElementById('event-all-day')?.addEventListener('change', function() {
    const startInput = document.getElementById('event-start');
    const endInput = document.getElementById('event-end');
    
    if (this.checked) {
        startInput.type = 'date';
        endInput.type = 'date';
    } else {
        startInput.type = 'datetime-local';
        endInput.type = 'datetime-local';
    }
});

document.getElementById('event-recurrence')?.addEventListener('change', function() {
    const endContainer = document.getElementById('recurrence-end-container');
    if (this.value !== 'none') {
        endContainer.style.display = 'block';
    } else {
        endContainer.style.display = 'none';
    }
});

// Create event form submission
document.getElementById('createEventForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    
    // Add community ID
    data.community_id = <?php echo $communityId; ?>;
    
    // Convert all_day checkbox
    data.all_day = data.all_day ? 1 : 0;
    
    // Send AJAX request
    fetch('/api/calendar-create.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error creating event: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to create event');
    });
});

// View event details
function viewEvent(eventId) {
    fetch(`/api/calendar-get.php?id=${eventId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const event = data.event;
                const content = document.getElementById('view-event-content');
                
                // Format dates
                const startDate = new Date(event.start_datetime);
                const endDate = new Date(event.end_datetime);
                
                let html = `
                    <h4>${escapeHtml(event.title)}</h4>
                    <p class="text-muted">
                        ${formatEventDate(startDate, endDate, event.all_day == '1')}
                    </p>
                `;
                
                if (event.description) {
                    html += `<p>${escapeHtml(event.description)}</p>`;
                }
                
                if (event.location) {
                    html += `<p><i class="bi bi-geo-alt"></i> ${escapeHtml(event.location)}</p>`;
                }
                
                if (event.zoom_link) {
                    html += `<p><i class="bi bi-camera-video"></i> <a href="${escapeHtml(event.zoom_link)}" target="_blank">Join Zoom Meeting</a></p>`;
                }
                
                if (event.project_name) {
                    html += `<p><span class="badge bg-success">Project: ${escapeHtml(event.project_name)}</span></p>`;
                }
                
                if (event.course_title) {
                    html += `<p><span class="badge bg-info">Course: ${escapeHtml(event.course_title)}</span></p>`;
                }
                
                html += `<p class="text-muted small">Created by ${escapeHtml(event.creator_first_name + ' ' + event.creator_last_name)}</p>`;
                
                content.innerHTML = html;
                
                <?php if ($canManage): ?>
                // Show edit/delete buttons if user can manage
                document.getElementById('edit-event-btn').style.display = 'block';
                document.getElementById('delete-event-btn').style.display = 'block';
                
                document.getElementById('edit-event-btn').onclick = function() {
                    window.location.href = `/calendar-edit.php?id=${eventId}`;
                };
                
                document.getElementById('delete-event-btn').onclick = function() {
                    if (confirm('Are you sure you want to delete this event?')) {
                        deleteEvent(eventId);
                    }
                };
                <?php endif; ?>
                
                // Show modal
                new bootstrap.Modal(document.getElementById('viewEventModal')).show();
            } else {
                alert('Error loading event details');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load event details');
        });
}

function deleteEvent(eventId) {
    fetch('/api/calendar-delete.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ event_id: eventId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error deleting event: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to delete event');
    });
}

function formatEventDate(start, end, allDay) {
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    
    if (allDay) {
        if (start.toDateString() === end.toDateString()) {
            return start.toLocaleDateString(undefined, options);
        } else {
            return `${start.toLocaleDateString(undefined, options)} - ${end.toLocaleDateString(undefined, options)}`;
        }
    } else {
        const timeOptions = { hour: 'numeric', minute: '2-digit' };
        if (start.toDateString() === end.toDateString()) {
            return `${start.toLocaleDateString(undefined, options)} • ${start.toLocaleTimeString(undefined, timeOptions)} - ${end.toLocaleTimeString(undefined, timeOptions)}`;
        } else {
            return `${start.toLocaleDateString(undefined, options)} ${start.toLocaleTimeString(undefined, timeOptions)} - ${end.toLocaleDateString(undefined, options)} ${end.toLocaleTimeString(undefined, timeOptions)}`;
        }
    }
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}
</script>

<?php require_once 'includes/footer.php'; ?>