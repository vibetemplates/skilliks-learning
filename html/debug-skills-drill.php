<?php
// Debug script to check skills drill status
require_once 'config/database.php';
require_once 'classes/SkillsDrill.php';

$db = getDB();

// Check which lessons have transcripts
$stmt = $db->query("
    SELECT l.id, l.title, c.title as course_title, 
           CASE WHEN l.video_transcript IS NULL OR l.video_transcript = '' THEN 'No' ELSE 'Yes' END as has_transcript,
           CASE WHEN sd.id IS NULL THEN 'No' ELSE 'Yes' END as has_drill,
           sd.id as drill_id,
           (SELECT COUNT(*) FROM skills_drill_questions WHERE drill_id = sd.id) as question_count
    FROM lessons l
    LEFT JOIN courses c ON l.course_id = c.id
    LEFT JOIN skills_drills sd ON l.id = sd.lesson_id
    ORDER BY c.title, l.order_index
");

$lessons = $stmt->fetchAll();

// Get lesson ID from URL parameter
$lessonId = isset($_GET['lesson_id']) ? intval($_GET['lesson_id']) : null;

?>
<!DOCTYPE html>
<html>
<head>
    <title>Skills Drill Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .yes { color: green; font-weight: bold; }
        .no { color: red; }
        .info { background-color: #e7f3fe; border: 1px solid #b3d9ff; padding: 10px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>Skills Drill Debug Information</h1>
    
    <?php if ($lessonId): ?>
        <?php
        $skillsDrill = new SkillsDrill();
        $drill = $skillsDrill->getByLessonId($lessonId);
        ?>
        <div class="info">
            <h2>Debug for Lesson ID: <?php echo $lessonId; ?></h2>
            <?php if ($drill): ?>
                <p><strong>Drill Found!</strong></p>
                <ul>
                    <li>Drill ID: <?php echo $drill['id']; ?></li>
                    <li>Title: <?php echo htmlspecialchars($drill['title']); ?></li>
                    <li>Created: <?php echo $drill['created_at']; ?></li>
                </ul>
                <?php
                $questions = $skillsDrill->getQuestions($drill['id'], null, false);
                ?>
                <p>Number of questions: <?php echo count($questions); ?></p>
            <?php else: ?>
                <p><strong>No drill found for this lesson.</strong></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <h2>All Lessons Overview</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Course</th>
                <th>Lesson</th>
                <th>Has Transcript</th>
                <th>Has Drill</th>
                <th>Questions</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lessons as $lesson): ?>
                <tr>
                    <td><?php echo $lesson['id']; ?></td>
                    <td><?php echo htmlspecialchars($lesson['course_title']); ?></td>
                    <td><?php echo htmlspecialchars($lesson['title']); ?></td>
                    <td class="<?php echo $lesson['has_transcript'] === 'Yes' ? 'yes' : 'no'; ?>">
                        <?php echo $lesson['has_transcript']; ?>
                    </td>
                    <td class="<?php echo $lesson['has_drill'] === 'Yes' ? 'yes' : 'no'; ?>">
                        <?php echo $lesson['has_drill']; ?>
                    </td>
                    <td><?php echo $lesson['question_count'] ?: '-'; ?></td>
                    <td>
                        <a href="?lesson_id=<?php echo $lesson['id']; ?>">Debug</a>
                        <?php if ($lesson['has_drill'] === 'Yes'): ?>
                            | <a href="/skills-drill-take.php?drill_id=<?php echo $lesson['drill_id']; ?>">Take Drill</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <h2>Summary</h2>
    <?php
    $totalLessons = count($lessons);
    $lessonsWithTranscripts = array_filter($lessons, function($l) { return $l['has_transcript'] === 'Yes'; });
    $lessonsWithDrills = array_filter($lessons, function($l) { return $l['has_drill'] === 'Yes'; });
    ?>
    <ul>
        <li>Total lessons: <?php echo $totalLessons; ?></li>
        <li>Lessons with transcripts: <?php echo count($lessonsWithTranscripts); ?></li>
        <li>Lessons with skills drills: <?php echo count($lessonsWithDrills); ?></li>
    </ul>
</body>
</html>