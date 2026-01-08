<?php
require_once dirname(__DIR__) . '/config/database.php';

try {
    $pdo = getDB();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get the project survey ID
    $stmt = $pdo->query("SELECT id FROM surveys WHERE type = 'project' LIMIT 1");
    $survey = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$survey) {
        die("Project survey not found!\n");
    }
    
    $survey_id = $survey['id'];
    echo "Found project survey with ID: $survey_id\n";
    
    // Section 1: Project Overview
    $stmt = $pdo->prepare("INSERT INTO survey_sections (survey_id, name, description, display_order, is_active) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$survey_id, 'Project Overview', 'Basic information about your project', 1, 1]);
    $section1_id = $pdo->lastInsertId();
    echo "Created Section 1 with ID: $section1_id\n";
    
    // Section 1 Questions
    $questions = [
        [$section1_id, 'What is the primary purpose of your project?', 'textarea', 1, 1, 'Describe the main goal and value proposition'],
        [$section1_id, 'Who is the target audience?', 'text', 1, 2, 'Describe your primary users or customers'],
        [$section1_id, 'What is the expected project timeline?', 'radio', 1, 3, 'Select the approximate timeline'],
        [$section1_id, 'What is the project complexity level?', 'radio', 1, 4, 'Based on features and technical requirements']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO survey_questions (section_id, question_text, question_type, is_required, display_order, help_text) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($questions as $q) {
        $stmt->execute($q);
    }
    
    // Get question IDs for adding options
    $stmt = $pdo->prepare("SELECT id, display_order FROM survey_questions WHERE section_id = ?");
    $stmt->execute([$section1_id]);
    $questionMap = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $questionMap[$row['display_order']] = $row['id'];
    }
    
    // Add answer options for timeline question
    $timelineOptions = [
        ['Less than 1 month', 'short', 1, 1],
        ['1-3 months', 'medium', 2, 1],
        ['3-6 months', 'long', 3, 1],
        ['More than 6 months', 'extended', 4, 1]
    ];
    
    $stmt = $pdo->prepare("INSERT INTO survey_answer_options (question_id, option_text, option_value, display_order, is_active) VALUES (?, ?, ?, ?, ?)");
    foreach ($timelineOptions as $opt) {
        $stmt->execute([$questionMap[3], $opt[0], $opt[1], $opt[2], $opt[3]]);
    }
    
    // Add answer options for complexity question
    $complexityOptions = [
        ['Simple (basic CRUD, few features)', 'simple', 1, 1],
        ['Moderate (multiple features, some integrations)', 'moderate', 2, 1],
        ['Complex (many features, multiple integrations)', 'complex', 3, 1],
        ['Enterprise (large-scale, high complexity)', 'enterprise', 4, 1]
    ];
    
    foreach ($complexityOptions as $opt) {
        $stmt->execute([$questionMap[4], $opt[0], $opt[1], $opt[2], $opt[3]]);
    }
    
    // Section 2: Technical Requirements
    $stmt = $pdo->prepare("INSERT INTO survey_sections (survey_id, name, description, display_order, is_active) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$survey_id, 'Technical Requirements', 'Technology preferences and constraints', 2, 1]);
    $section2_id = $pdo->lastInsertId();
    echo "Created Section 2 with ID: $section2_id\n";
    
    // Section 2 Questions
    $questions2 = [
        [$section2_id, 'Preferred programming languages (select all that apply)', 'checkbox', 0, 1, 'Leave blank for AI recommendation'],
        [$section2_id, 'Preferred frontend framework', 'dropdown', 0, 2, 'Select your preferred framework or leave blank'],
        [$section2_id, 'Preferred backend framework', 'dropdown', 0, 3, 'Select your preferred framework or leave blank'],
        [$section2_id, 'Database requirements', 'checkbox', 1, 4, 'Select all that apply'],
        [$section2_id, 'Any specific technology constraints or requirements?', 'textarea', 0, 5, 'e.g., must use React, avoid cloud services']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO survey_questions (section_id, question_text, question_type, is_required, display_order, help_text) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($questions2 as $q) {
        $stmt->execute($q);
    }
    
    // Continue with remaining sections...
    echo "Migration completed successfully!\n";
    
    // Verify the results
    $stmt = $pdo->query("
        SELECT 
            COUNT(DISTINCT ss.id) as sections,
            COUNT(DISTINCT sq.id) as questions,
            COUNT(DISTINCT sao.id) as options
        FROM surveys s
        LEFT JOIN survey_sections ss ON s.id = ss.survey_id
        LEFT JOIN survey_questions sq ON ss.id = sq.section_id
        LEFT JOIN survey_answer_options sao ON sq.id = sao.question_id
        WHERE s.id = $survey_id
    ");
    $counts = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "\nCreated:\n";
    echo "- Sections: {$counts['sections']}\n";
    echo "- Questions: {$counts['questions']}\n";
    echo "- Options: {$counts['options']}\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}