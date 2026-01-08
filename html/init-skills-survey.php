<?php
/**
 * Initialize Skills Survey
 * 
 * Creates the default skills survey for all communities
 */

require_once 'config/database.php';

$db = getDB();

try {
    // Start transaction
    $db->beginTransaction();
    
    // Get all communities
    $stmt = $db->query("SELECT id FROM communities");
    $communities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($communities)) {
        // Create default community if none exist
        $stmt = $db->prepare("INSERT INTO communities (name, slug, description, is_public, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute(['Default Community', 'default', 'Default community for all users', 1]);
        $communityId = $db->lastInsertId();
        $communities = [['id' => $communityId]];
    }
    
    foreach ($communities as $community) {
        $communityId = $community['id'];
        
        // Check if survey already exists
        $stmt = $db->prepare("SELECT id FROM surveys WHERE community_id = ? AND type = 'skills'");
        $stmt->execute([$communityId]);
        if ($stmt->fetch()) {
            echo "Skills survey already exists for community $communityId\n";
            continue;
        }
        
        // Create the survey
        $stmt = $db->prepare("
            INSERT INTO surveys (community_id, name, description, type, is_active, requires_completion) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $communityId,
            'Skills Assessment Survey',
            'Help us understand your interests, skills, and availability to better match you with projects and learning opportunities.',
            'skills',
            1,
            1
        ]);
        $surveyId = $db->lastInsertId();
        
        echo "Created skills survey (ID: $surveyId) for community $communityId\n";
        
        // Create sections
        $sections = [
            ['Interests', 'Tell us about your interests and what you want to learn', 1],
            ['Skills', 'Let us know your current skill levels', 2],
            ['Experience', 'Share your experience and background', 3],
            ['Availability', 'Help us understand your availability', 4]
        ];
        
        $sectionIds = [];
        foreach ($sections as $section) {
            $stmt = $db->prepare("
                INSERT INTO survey_sections (survey_id, name, description, display_order, is_active) 
                VALUES (?, ?, ?, ?, 1)
            ");
            $stmt->execute([$surveyId, $section[0], $section[1], $section[2]]);
            $sectionIds[$section[0]] = $db->lastInsertId();
        }
        
        // Create questions and answer options
        
        // Interests section
        $stmt = $db->prepare("
            INSERT INTO survey_questions (section_id, question_text, question_type, is_required, display_order) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        // Ranking question
        $stmt->execute([
            $sectionIds['Interests'],
            'Put the topics that interest you in order of most interested to least interested. If you are not interested at all, leave the item in the left column.',
            'ranking',
            0,
            1
        ]);
        $rankingQuestionId = $db->lastInsertId();
        
        // Add ranking options
        $rankingOptions = [
            ['Building Automations', 'Create automated workflows and scripts to streamline repetitive tasks', 1],
            ['MCP Servers', 'Build Model Context Protocol servers for AI integration', 2],
            ['AI Applications', 'Develop applications powered by artificial intelligence', 3],
            ['Vibe Coding', 'Code in a relaxed, collaborative environment focusing on creativity', 4],
            ['No-Code Tools', 'Build applications using visual development platforms', 5],
            ['Mobile Development', 'Create apps for iOS and Android devices', 6],
            ['Web Development', 'Build modern web applications and websites', 7],
            ['Data Analysis', 'Analyze and visualize data to gain insights', 8],
            ['Game Development', 'Create interactive games and simulations', 9],
            ['DevOps & Cloud', 'Deploy and manage applications in cloud environments', 10]
        ];
        
        $stmt = $db->prepare("
            INSERT INTO survey_answer_options (question_id, option_text, option_value, description, display_order, is_active) 
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        
        foreach ($rankingOptions as $option) {
            $stmt->execute([$rankingQuestionId, $option[0], $option[0], $option[1], $option[2]]);
        }
        
        // Skills section - checkboxes
        $questStmt = $db->prepare("
            INSERT INTO survey_questions (section_id, question_text, question_type, is_required, display_order) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $questStmt->execute([
            $sectionIds['Skills'],
            'Which of the following skills do you currently have?',
            'checkbox',
            0,
            1
        ]);
        $skillsQuestionId = $db->lastInsertId();
        
        $skills = [
            ['Python', 'python', 'Programming language for AI, data science, and automation', 1],
            ['JavaScript', 'javascript', 'Programming language for web and server development', 2],
            ['HTML/CSS', 'html-css', 'Web page structure and styling', 3],
            ['React', 'react', 'JavaScript framework for building user interfaces', 4],
            ['Node.js', 'nodejs', 'JavaScript runtime for server-side development', 5],
            ['Git', 'git', 'Version control system for code management', 6],
            ['SQL', 'sql', 'Database query language', 7],
            ['Docker', 'docker', 'Container platform for application deployment', 8],
            ['Machine Learning', 'ml', 'Building and training AI models', 9],
            ['REST APIs', 'rest-api', 'Building and consuming web services', 10]
        ];
        
        $optStmt = $db->prepare("
            INSERT INTO survey_answer_options (question_id, option_text, option_value, description, display_order, is_active) 
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        
        foreach ($skills as $skill) {
            $optStmt->execute([$skillsQuestionId, $skill[0], $skill[1], $skill[2], $skill[3]]);
        }
        
        // Experience section
        $questStmt->execute([
            $sectionIds['Experience'],
            'How many years of programming experience do you have?',
            'dropdown',
            1,
            1
        ]);
        $experienceQuestionId = $db->lastInsertId();
        
        $experienceOptions = [
            ['None', '0', 1],
            ['Less than 1 year', '0.5', 2],
            ['1-2 years', '1.5', 3],
            ['3-5 years', '4', 4],
            ['5+ years', '6', 5]
        ];
        
        foreach ($experienceOptions as $option) {
            $optStmt->execute([$experienceQuestionId, $option[0], $option[1], null, $option[2]]);
        }
        
        // Availability section
        $questStmt->execute([
            $sectionIds['Availability'],
            'How many hours per week can you dedicate to projects?',
            'dropdown',
            1,
            1
        ]);
        $hoursQuestionId = $db->lastInsertId();
        
        $hoursOptions = [
            ['Less than 5 hours', '3', 1],
            ['5-10 hours', '7', 2],
            ['10-20 hours', '15', 3],
            ['20-30 hours', '25', 4],
            ['30+ hours', '35', 5]
        ];
        
        foreach ($hoursOptions as $option) {
            $optStmt->execute([$hoursQuestionId, $option[0], $option[1], null, $option[2]]);
        }
        
        // Team availability
        $questStmt->execute([
            $sectionIds['Availability'],
            'Are you available for team meetings and collaboration?',
            'radio',
            1,
            2
        ]);
        $teamQuestionId = $db->lastInsertId();
        
        $teamOptions = [
            ['Yes, I can attend regular team meetings', 'yes', 1],
            ['Limited availability for meetings', 'limited', 2],
            ['Prefer asynchronous collaboration only', 'async', 3]
        ];
        
        foreach ($teamOptions as $option) {
            $optStmt->execute([$teamQuestionId, $option[0], $option[1], null, $option[2]]);
        }
    }
    
    // Commit transaction
    $db->commit();
    echo "\nSkills survey initialization completed successfully!\n";
    
} catch (Exception $e) {
    $db->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}