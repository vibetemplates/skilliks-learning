<?php
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/SkillsDrill.php';

// Require login
requireLogin();

$skillsDrill = new SkillsDrill();
$db = Database::getInstance()->getConnection();

// Get session ID
$sessionId = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;

// Get session info
$stmt = $db->prepare("SELECT s.*, d.title as drill_title, d.lesson_id, l.title as lesson_title, l.course_id
                      FROM skills_drill_sessions s
                      JOIN skills_drills d ON s.drill_id = d.id
                      JOIN lessons l ON d.lesson_id = l.id
                      WHERE s.id = ? AND s.user_id = ?");
$stmt->execute([$sessionId, $_SESSION['user_id']]);
$session = $stmt->fetch();

if (!$session) {
    $_SESSION['error_message'] = "Session not found.";
    header("Location: my-courses.php");
    exit;
}

// Get question details with responses
$stmt = $db->prepare("SELECT q.*, 
                             GROUP_CONCAT(CONCAT(r.attempt_number, ':', r.is_correct, ':', r.points_earned) 
                                          ORDER BY r.attempt_number SEPARATOR '|') as attempts
                      FROM skills_drill_questions q
                      JOIN skills_drill_responses r ON q.id = r.question_id
                      WHERE r.session_id = ?
                      GROUP BY q.id");
$stmt->execute([$sessionId]);
$questions = $stmt->fetchAll();

// Get user stats
$userStats = $skillsDrill->getUserStats($_SESSION['user_id'], $session['drill_id']);

// Calculate session statistics
$totalQuestions = count($questions);
$questionsAnswered = 0;
$firstTryCorrect = 0;
$totalAttempts = 0;

foreach ($questions as $question) {
    $attempts = explode('|', $question['attempts']);
    $totalAttempts += count($attempts);
    
    foreach ($attempts as $attempt) {
        list($attemptNum, $isCorrect, $points) = explode(':', $attempt);
        if ($isCorrect == 1) {
            $questionsAnswered++;
            if ($attemptNum == 1) {
                $firstTryCorrect++;
            }
            break;
        }
    }
}

$successRate = $totalQuestions > 0 ? round(($questionsAnswered / $totalQuestions) * 100) : 0;
$firstTryRate = $totalQuestions > 0 ? round(($firstTryCorrect / $totalQuestions) * 100) : 0;
$avgAttempts = $totalQuestions > 0 ? round($totalAttempts / $totalQuestions, 1) : 0;

$pageTitle = 'Skills Drill Results - ' . htmlspecialchars($session['drill_title']);
require_once 'includes/header.php';
?>
<style>
        .stat-card {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .points-display {
            font-size: 3rem;
            font-weight: bold;
        }
        .attempt-badge {
            margin: 0 2px;
        }
    </style>

<div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">Skills Drill Results</h1>
                <h4 class="text-muted mb-4"><?= htmlspecialchars($session['drill_title']) ?></h4>
                
                <!-- Session Summary -->
                <div class="row mb-4" id="session-summary">
                    <div class="col-md-3">
                        <div class="stat-card bg-primary text-white">
                            <div class="points-display">
                                <?= number_format($session['total_points'], 1) ?>
                            </div>
                            <div>Points Earned</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-success text-white">
                            <div class="h2"><?= $successRate ?>%</div>
                            <div>Success Rate</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-info text-white">
                            <div class="h2"><?= $firstTryRate ?>%</div>
                            <div>First Try Success</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-warning text-white">
                            <div class="h2"><?= $avgAttempts ?></div>
                            <div>Avg Attempts</div>
                        </div>
                    </div>
                </div>
                
                <!-- Question Review -->
                <div class="card mb-4" id="question-review">
                    <div class="card-header">
                        <h5 class="mb-0">Question Review</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Question</th>
                                        <th>Attempts</th>
                                        <th>Points</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $questionNum = 1;
                                    foreach ($questions as $question): 
                                        $attempts = explode('|', $question['attempts']);
                                        $solved = false;
                                        $pointsEarned = 0;
                                        
                                        foreach ($attempts as $attempt) {
                                            list($attemptNum, $isCorrect, $points) = explode(':', $attempt);
                                            if ($isCorrect == 1) {
                                                $solved = true;
                                                $pointsEarned = $points;
                                                break;
                                            }
                                        }
                                    ?>
                                    <tr>
                                        <td><?= $questionNum++ ?></td>
                                        <td><?= htmlspecialchars(substr($question['question_text'], 0, 60)) ?>...</td>
                                        <td>
                                            <?php foreach ($attempts as $i => $attempt): 
                                                list($attemptNum, $isCorrect, $points) = explode(':', $attempt);
                                            ?>
                                                <span class="badge bg-<?= $isCorrect == 1 ? 'success' : 'danger' ?> attempt-badge">
                                                    <?= $i + 1 ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $pointsEarned > 0 ? 'success' : ($pointsEarned < 0 ? 'danger' : 'secondary') ?>">
                                                <?= $pointsEarned > 0 ? '+' : '' ?><?= number_format($pointsEarned, 1) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($solved): ?>
                                                <i class="bi bi-check-circle text-success"></i> Solved
                                            <?php else: ?>
                                                <i class="bi bi-x-circle text-danger"></i> Not Solved
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Overall Progress -->
                <?php if ($userStats): ?>
                <div class="card mb-4" id="overall-progress">
                    <div class="card-header">
                        <h5 class="mb-0">Your Overall Progress</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Lifetime Stats</h6>
                                <ul class="list-unstyled">
                                    <li><strong>Total Points:</strong> <?= number_format($userStats['total_points'], 1) ?></li>
                                    <li><strong>Total Sessions:</strong> <?= $userStats['total_sessions'] ?></li>
                                    <li><strong>Questions Answered:</strong> <?= $userStats['total_questions_answered'] ?></li>
                                    <li><strong>Best Session:</strong> <?= number_format($userStats['best_session_points'], 1) ?> points</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>This Session Performance</h6>
                                <?php if ($session['total_points'] >= $userStats['best_session_points']): ?>
                                    <div class="alert alert-success">
                                        <i class="bi bi-trophy"></i> New Personal Best!
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($session['total_points'] > 0): ?>
                                    <div class="progress mb-3" style="height: 30px;">
                                        <div class="progress-bar bg-success" role="progressbar" 
                                             style="width: <?= min(100, ($session['total_points'] / $totalQuestions) * 100) ?>%">
                                            <?= number_format($session['total_points'], 1) ?> / <?= $totalQuestions ?> possible
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Action Buttons -->
                <div class="text-center" id="action-buttons">
                    <a href="skills-drill-take.php?drill_id=<?= $session['drill_id'] ?>" 
                       class="btn btn-primary">
                        <i class="bi bi-arrow-repeat"></i> Practice Again
                    </a>
                    <a href="lesson-detail.php?id=<?= $session['lesson_id'] ?>" 
                       class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Lesson
                    </a>
                    <a href="my-courses.php" class="btn btn-outline-secondary">
                        <i class="bi bi-house"></i> My Courses
                    </a>
                </div>
            </div>
        </div>
    </div>

<?php require_once 'includes/footer.php'; ?>