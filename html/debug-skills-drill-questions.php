<?php
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/SkillsDrill.php';
require_once 'classes/User.php';

// Require login
requireLogin();

$userObj = new User();
if (!$userObj->isAdmin($_SESSION['user_id'])) {
    header("Location: /");
    exit;
}

$skillsDrill = new SkillsDrill();
$db = Database::getInstance()->getConnection();

$drillId = isset($_GET['drill_id']) ? intval($_GET['drill_id']) : 0;

echo "<h1>Debug Skills Drill Questions</h1>";
echo "<pre>";

// Get drill info
$drill = $skillsDrill->getById($drillId);
echo "Drill Info:\n";
print_r($drill);
echo "\n\n";

// Get questions
$questions = $skillsDrill->getQuestions($drillId, null, false);
echo "Questions Count: " . count($questions) . "\n\n";

foreach ($questions as $index => $question) {
    echo "Question " . ($index + 1) . ":\n";
    echo "ID: " . $question['id'] . "\n";
    echo "Text: " . $question['question_text'] . "\n";
    echo "Options Count: " . count($question['options']) . "\n";
    
    foreach ($question['options'] as $optIndex => $option) {
        echo "  Option " . ($optIndex + 1) . ": " . $option['answer_text'] . " (Correct: " . ($option['is_correct'] ? 'Yes' : 'No') . ")\n";
    }
    echo "\n";
}

// Check if questions exist in DB
$stmt = $db->prepare("SELECT COUNT(*) as count FROM skills_drill_questions WHERE drill_id = ?");
$stmt->execute([$drillId]);
$questionCount = $stmt->fetch();
echo "Questions in DB: " . $questionCount['count'] . "\n";

$stmt = $db->prepare("SELECT COUNT(*) as count FROM skills_drill_answer_options WHERE question_id IN (SELECT id FROM skills_drill_questions WHERE drill_id = ?)");
$stmt->execute([$drillId]);
$optionCount = $stmt->fetch();
echo "Answer Options in DB: " . $optionCount['count'] . "\n";

echo "</pre>";
?>