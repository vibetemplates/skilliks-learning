<?php
/**
 * Script to copy courses, programs, surveys, and project categories between communities
 * 
 * Usage: php copy_community_content.php [source_community_id] [target_community_id] [--clean]
 * 
 * Options:
 *   --clean  Remove existing content from target community before copying
 */

require_once dirname(__DIR__) . '/config/database.php';

// Parse command line arguments
$sourceCommunityId = $argv[1] ?? 1;
$targetCommunityId = $argv[2] ?? 2;
$cleanFirst = in_array('--clean', $argv);

if ($sourceCommunityId == $targetCommunityId) {
    die("Error: Source and target community IDs must be different\n");
}

$db = Database::getInstance()->getConnection();

// Helper function to log messages
function logMessage($message, $type = 'INFO') {
    echo "[" . date('Y-m-d H:i:s') . "] [$type] $message\n";
}

// Start transaction
$db->beginTransaction();

try {
    // Clean existing data if requested
    if ($cleanFirst) {
        logMessage("Cleaning existing data from community $targetCommunityId...");
        
        // Delete in reverse order of dependencies
        $db->exec("DELETE FROM survey_answer_options WHERE question_id IN (
            SELECT q.id FROM survey_questions q 
            JOIN survey_sections s ON q.section_id = s.id 
            WHERE s.survey_id IN (SELECT id FROM surveys WHERE community_id = $targetCommunityId)
        )");
        
        $db->exec("DELETE FROM survey_questions WHERE section_id IN (
            SELECT id FROM survey_sections WHERE survey_id IN (
                SELECT id FROM surveys WHERE community_id = $targetCommunityId
            )
        )");
        
        $db->exec("DELETE FROM survey_sections WHERE survey_id IN (
            SELECT id FROM surveys WHERE community_id = $targetCommunityId
        )");
        
        $db->exec("DELETE FROM surveys WHERE community_id = $targetCommunityId");
        logMessage("Cleaned surveys");
        
        $db->exec("DELETE FROM lessons WHERE course_id IN (
            SELECT id FROM courses WHERE community_id = $targetCommunityId
        )");
        
        $db->exec("DELETE FROM courses WHERE community_id = $targetCommunityId");
        logMessage("Cleaned courses");
        
        $db->exec("DELETE FROM programs WHERE community_id = $targetCommunityId");
        logMessage("Cleaned programs");
        
        $db->exec("DELETE FROM project_categories WHERE community_id = $targetCommunityId");
        logMessage("Cleaned project categories");
    }
    
    // Copy Programs
    logMessage("Starting to copy programs...");
    
    $stmt = $db->prepare("
        SELECT * FROM programs 
        WHERE community_id = :source_community_id
    ");
    $stmt->execute(['source_community_id' => $sourceCommunityId]);
    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $programMapping = [];
    
    foreach ($programs as $program) {
        $oldId = $program['id'];
        unset($program['id']);
        $program['community_id'] = $targetCommunityId;
        
        // Make slug unique by appending community ID
        if (!empty($program['slug'])) {
            $baseSlug = $program['slug'] . '-c' . $targetCommunityId;
            $program['slug'] = $baseSlug;
            
            // Check if slug already exists and make it unique
            $counter = 1;
            $checkSlugStmt = $db->prepare("SELECT id FROM programs WHERE slug = :slug");
            $checkSlugStmt->execute(['slug' => $program['slug']]);
            
            while ($checkSlugStmt->fetch()) {
                $program['slug'] = $baseSlug . '-' . $counter;
                $counter++;
                $checkSlugStmt->execute(['slug' => $program['slug']]);
            }
        }
        
        $columns = implode(', ', array_keys($program));
        $placeholders = ':' . implode(', :', array_keys($program));
        
        $insertStmt = $db->prepare("
            INSERT INTO programs ($columns) 
            VALUES ($placeholders)
        ");
        $insertStmt->execute($program);
        $newId = $db->lastInsertId();
        $programMapping[$oldId] = $newId;
        
        logMessage("Copied program: {$program['name']} (Old ID: $oldId -> New ID: $newId)");
    }
    
    logMessage("Copied " . count($programMapping) . " programs");
    
    // Copy Courses
    logMessage("Starting to copy courses...");
    
    $stmt = $db->prepare("
        SELECT * FROM courses 
        WHERE community_id = :source_community_id
    ");
    $stmt->execute(['source_community_id' => $sourceCommunityId]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $courseMapping = [];
    
    foreach ($courses as $course) {
        $oldId = $course['id'];
        unset($course['id']);
        $course['community_id'] = $targetCommunityId;
        
        // Update program_id if it exists and was mapped
        if (!empty($course['program_id']) && isset($programMapping[$course['program_id']])) {
            $course['program_id'] = $programMapping[$course['program_id']];
        }
        
        $columns = implode(', ', array_keys($course));
        $placeholders = ':' . implode(', :', array_keys($course));
        
        $insertStmt = $db->prepare("
            INSERT INTO courses ($columns) 
            VALUES ($placeholders)
        ");
        $insertStmt->execute($course);
        $newId = $db->lastInsertId();
        $courseMapping[$oldId] = $newId;
        
        logMessage("Copied course: {$course['title']}");
        
        // Copy lessons
        $lessonStmt = $db->prepare("
            SELECT * FROM lessons 
            WHERE course_id = :course_id
        ");
        $lessonStmt->execute(['course_id' => $oldId]);
        $lessons = $lessonStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($lessons as $lesson) {
            unset($lesson['id']);
            $lesson['course_id'] = $newId;
            
            $lessonColumns = implode(', ', array_keys($lesson));
            $lessonPlaceholders = ':' . implode(', :', array_keys($lesson));
            
            $insertLessonStmt = $db->prepare("
                INSERT INTO lessons ($lessonColumns) 
                VALUES ($lessonPlaceholders)
            ");
            $insertLessonStmt->execute($lesson);
        }
        
        logMessage("  - Copied " . count($lessons) . " lessons");
    }
    
    logMessage("Copied " . count($courseMapping) . " courses");
    
    // Copy Surveys
    logMessage("Starting to copy surveys...");
    
    $stmt = $db->prepare("
        SELECT * FROM surveys 
        WHERE community_id = :source_community_id
    ");
    $stmt->execute(['source_community_id' => $sourceCommunityId]);
    $surveys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $surveyMapping = [];
    
    foreach ($surveys as $survey) {
        $oldId = $survey['id'];
        unset($survey['id']);
        $survey['community_id'] = $targetCommunityId;
        
        // Add created_by if missing
        if (!isset($survey['created_by'])) {
            $survey['created_by'] = 1;
        }
        
        $columns = implode(', ', array_keys($survey));
        $placeholders = ':' . implode(', :', array_keys($survey));
        
        $insertStmt = $db->prepare("
            INSERT INTO surveys ($columns) 
            VALUES ($placeholders)
        ");
        $insertStmt->execute($survey);
        $newId = $db->lastInsertId();
        $surveyMapping[$oldId] = $newId;
        
        logMessage("Copied survey: {$survey['name']}");
        
        // Copy survey sections
        $sectionStmt = $db->prepare("
            SELECT * FROM survey_sections 
            WHERE survey_id = :survey_id
            ORDER BY display_order
        ");
        $sectionStmt->execute(['survey_id' => $oldId]);
        $sections = $sectionStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($sections as $section) {
            $oldSectionId = $section['id'];
            unset($section['id']);
            $section['survey_id'] = $newId;
            
            $sColumns = implode(', ', array_keys($section));
            $sPlaceholders = ':' . implode(', :', array_keys($section));
            
            $insertSectionStmt = $db->prepare("
                INSERT INTO survey_sections ($sColumns) 
                VALUES ($sPlaceholders)
            ");
            $insertSectionStmt->execute($section);
            $newSectionId = $db->lastInsertId();
            
            // Copy questions
            $questionStmt = $db->prepare("
                SELECT * FROM survey_questions 
                WHERE section_id = :section_id
                ORDER BY display_order
            ");
            $questionStmt->execute(['section_id' => $oldSectionId]);
            $questions = $questionStmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($questions as $question) {
                $oldQuestionId = $question['id'];
                unset($question['id']);
                $question['section_id'] = $newSectionId;
                
                $qColumns = implode(', ', array_keys($question));
                $qPlaceholders = ':' . implode(', :', array_keys($question));
                
                $insertQuestionStmt = $db->prepare("
                    INSERT INTO survey_questions ($qColumns) 
                    VALUES ($qPlaceholders)
                ");
                $insertQuestionStmt->execute($question);
                $newQuestionId = $db->lastInsertId();
                
                // Copy answer options
                $optionStmt = $db->prepare("
                    SELECT * FROM survey_answer_options 
                    WHERE question_id = :question_id
                    ORDER BY display_order
                ");
                $optionStmt->execute(['question_id' => $oldQuestionId]);
                $options = $optionStmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($options as $option) {
                    unset($option['id']);
                    $option['question_id'] = $newQuestionId;
                    
                    $oColumns = implode(', ', array_keys($option));
                    $oPlaceholders = ':' . implode(', :', array_keys($option));
                    
                    $insertOptionStmt = $db->prepare("
                        INSERT INTO survey_answer_options ($oColumns) 
                        VALUES ($oPlaceholders)
                    ");
                    $insertOptionStmt->execute($option);
                }
            }
        }
        
        logMessage("  - Copied sections, questions and options");
    }
    
    logMessage("Copied " . count($surveyMapping) . " surveys");
    
    // Copy Project Categories
    logMessage("Starting to copy project categories...");
    
    $stmt = $db->prepare("
        SELECT * FROM project_categories 
        WHERE community_id = :source_community_id
    ");
    $stmt->execute(['source_community_id' => $sourceCommunityId]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $categoryMapping = [];
    
    foreach ($categories as $category) {
        $oldId = $category['id'];
        unset($category['id']);
        $category['community_id'] = $targetCommunityId;
        
        // Make name unique by appending community ID if needed
        $originalName = $category['name'];
        $checkStmt = $db->prepare("SELECT id FROM project_categories WHERE name = :name");
        $checkStmt->execute(['name' => $category['name']]);
        if ($checkStmt->fetch()) {
            $category['name'] = $category['name'] . ' (Community ' . $targetCommunityId . ')';
        }
        
        $columns = implode(', ', array_keys($category));
        $placeholders = ':' . implode(', :', array_keys($category));
        
        $insertStmt = $db->prepare("
            INSERT INTO project_categories ($columns) 
            VALUES ($placeholders)
        ");
        $insertStmt->execute($category);
        $newId = $db->lastInsertId();
        $categoryMapping[$oldId] = $newId;
        
        logMessage("Copied project category: {$originalName}" . ($originalName != $category['name'] ? " as {$category['name']}" : ""));
    }
    
    logMessage("Copied " . count($categoryMapping) . " project categories");
    
    // Commit transaction
    $db->commit();
    logMessage("All data copied successfully!", 'SUCCESS');
    
    // Summary
    logMessage("\n=== SUMMARY ===");
    logMessage("Programs copied: " . count($programMapping));
    logMessage("Courses copied: " . count($courseMapping));
    logMessage("Surveys copied: " . count($surveyMapping));
    logMessage("Project categories copied: " . count($categoryMapping));
    
} catch (Exception $e) {
    $db->rollBack();
    logMessage("Error: " . $e->getMessage(), 'ERROR');
    logMessage("Stack trace: " . $e->getTraceAsString(), 'ERROR');
    exit(1);
}