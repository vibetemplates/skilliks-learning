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
    
    // Check if sections already exist
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM survey_sections WHERE survey_id = ?");
    $stmt->execute([$survey_id]);
    $existingCount = $stmt->fetchColumn();
    
    if ($existingCount >= 4) {
        echo "Survey already has $existingCount sections. Migration appears to be complete.\n";
        exit(0);
    }
    
    // Get question IDs for section 2
    $stmt = $pdo->prepare("SELECT id FROM survey_sections WHERE survey_id = ? AND display_order = 2");
    $stmt->execute([$survey_id]);
    $section2 = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($section2) {
        $section2_id = $section2['id'];
        
        // Get question IDs for adding options
        $stmt = $pdo->prepare("SELECT id, display_order FROM survey_questions WHERE section_id = ?");
        $stmt->execute([$section2_id]);
        $questionMap2 = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $questionMap2[$row['display_order']] = $row['id'];
        }
        
        $stmt = $pdo->prepare("INSERT INTO survey_answer_options (question_id, option_text, option_value, display_order, is_active) VALUES (?, ?, ?, ?, ?)");
        
        // Add programming language options
        $langOptions = [
            ['JavaScript/TypeScript', 'javascript', 1, 1],
            ['Python', 'python', 2, 1],
            ['Java', 'java', 3, 1],
            ['C#/.NET', 'csharp', 4, 1],
            ['Go', 'go', 5, 1],
            ['Ruby', 'ruby', 6, 1],
            ['PHP', 'php', 7, 1],
            ['Rust', 'rust', 8, 1]
        ];
        
        foreach ($langOptions as $opt) {
            $stmt->execute([$questionMap2[1], $opt[0], $opt[1], $opt[2], $opt[3]]);
        }
        
        // Add frontend framework options
        $frontendOptions = [
            ['No preference (AI will recommend)', 'none', 1, 1],
            ['React', 'react', 2, 1],
            ['Vue.js', 'vue', 3, 1],
            ['Angular', 'angular', 4, 1],
            ['Svelte', 'svelte', 5, 1],
            ['Next.js', 'nextjs', 6, 1],
            ['Plain HTML/CSS/JS', 'vanilla', 7, 1]
        ];
        
        foreach ($frontendOptions as $opt) {
            $stmt->execute([$questionMap2[2], $opt[0], $opt[1], $opt[2], $opt[3]]);
        }
        
        // Add backend framework options
        $backendOptions = [
            ['No preference (AI will recommend)', 'none', 1, 1],
            ['Express.js (Node)', 'express', 2, 1],
            ['Django (Python)', 'django', 3, 1],
            ['Flask (Python)', 'flask', 4, 1],
            ['Spring Boot (Java)', 'spring', 5, 1],
            ['ASP.NET Core', 'aspnet', 6, 1],
            ['Ruby on Rails', 'rails', 7, 1],
            ['Laravel (PHP)', 'laravel', 8, 1],
            ['FastAPI (Python)', 'fastapi', 9, 1]
        ];
        
        foreach ($backendOptions as $opt) {
            $stmt->execute([$questionMap2[3], $opt[0], $opt[1], $opt[2], $opt[3]]);
        }
        
        // Add database options
        $dbOptions = [
            ['Relational database (MySQL, PostgreSQL)', 'relational', 1, 1],
            ['NoSQL database (MongoDB, DynamoDB)', 'nosql', 2, 1],
            ['In-memory cache (Redis, Memcached)', 'cache', 3, 1],
            ['No database needed', 'none', 4, 1]
        ];
        
        foreach ($dbOptions as $opt) {
            $stmt->execute([$questionMap2[4], $opt[0], $opt[1], $opt[2], $opt[3]]);
        }
    }
    
    // Section 3: Architecture & Deployment
    $stmt = $pdo->prepare("INSERT INTO survey_sections (survey_id, name, description, display_order, is_active) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$survey_id, 'Architecture & Deployment', 'System design and deployment preferences', 3, 1]);
    $section3_id = $pdo->lastInsertId();
    echo "Created Section 3 with ID: $section3_id\n";
    
    // Section 3 Questions
    $questions3 = [
        [$section3_id, 'Preferred architecture pattern', 'radio', 1, 1, 'Select the architecture that best fits your needs'],
        [$section3_id, 'Deployment target', 'radio', 1, 2, 'Where will the application be deployed?'],
        [$section3_id, 'Expected user load', 'radio', 1, 3, 'Estimated concurrent users at peak'],
        [$section3_id, 'Security requirements', 'checkbox', 0, 4, 'Select all that apply'],
        [$section3_id, 'Integration requirements', 'checkbox', 0, 5, 'External services or APIs to integrate']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO survey_questions (section_id, question_text, question_type, is_required, display_order, help_text) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($questions3 as $q) {
        $stmt->execute($q);
    }
    
    // Get question IDs for section 3
    $stmt = $pdo->prepare("SELECT id, display_order FROM survey_questions WHERE section_id = ?");
    $stmt->execute([$section3_id]);
    $questionMap3 = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $questionMap3[$row['display_order']] = $row['id'];
    }
    
    $stmt = $pdo->prepare("INSERT INTO survey_answer_options (question_id, option_text, option_value, display_order, is_active) VALUES (?, ?, ?, ?, ?)");
    
    // Add architecture options
    $archOptions = [
        ['Monolithic', 'monolithic', 1, 1],
        ['Microservices', 'microservices', 2, 1],
        ['Serverless', 'serverless', 3, 1],
        ['Event-driven', 'event-driven', 4, 1],
        ['Let AI recommend', 'ai-recommend', 5, 1]
    ];
    
    foreach ($archOptions as $opt) {
        $stmt->execute([$questionMap3[1], $opt[0], $opt[1], $opt[2], $opt[3]]);
    }
    
    // Add deployment target options
    $deployOptions = [
        ['Cloud (AWS, Azure, GCP)', 'cloud', 1, 1],
        ['On-premises servers', 'on-premise', 2, 1],
        ['Hybrid (cloud + on-premises)', 'hybrid', 3, 1],
        ['Edge/IoT devices', 'edge', 4, 1],
        ['Container platform (Kubernetes)', 'kubernetes', 5, 1]
    ];
    
    foreach ($deployOptions as $opt) {
        $stmt->execute([$questionMap3[2], $opt[0], $opt[1], $opt[2], $opt[3]]);
    }
    
    // Add user load options
    $loadOptions = [
        ['Less than 100', 'small', 1, 1],
        ['100 - 1,000', 'medium', 2, 1],
        ['1,000 - 10,000', 'large', 3, 1],
        ['More than 10,000', 'enterprise', 4, 1]
    ];
    
    foreach ($loadOptions as $opt) {
        $stmt->execute([$questionMap3[3], $opt[0], $opt[1], $opt[2], $opt[3]]);
    }
    
    // Add security requirement options
    $securityOptions = [
        ['User authentication', 'auth', 1, 1],
        ['Role-based access control', 'rbac', 2, 1],
        ['Data encryption', 'encryption', 3, 1],
        ['GDPR/Privacy compliance', 'gdpr', 4, 1],
        ['PCI compliance', 'pci', 5, 1],
        ['HIPAA compliance', 'hipaa', 6, 1]
    ];
    
    foreach ($securityOptions as $opt) {
        $stmt->execute([$questionMap3[4], $opt[0], $opt[1], $opt[2], $opt[3]]);
    }
    
    // Add integration options
    $integrationOptions = [
        ['Payment processing', 'payment', 1, 1],
        ['Email/SMS notifications', 'notifications', 2, 1],
        ['Social media APIs', 'social', 3, 1],
        ['Analytics/Monitoring', 'analytics', 4, 1],
        ['Third-party APIs', 'third-party', 5, 1],
        ['Legacy system integration', 'legacy', 6, 1]
    ];
    
    foreach ($integrationOptions as $opt) {
        $stmt->execute([$questionMap3[5], $opt[0], $opt[1], $opt[2], $opt[3]]);
    }
    
    // Section 4: Team & Development
    $stmt = $pdo->prepare("INSERT INTO survey_sections (survey_id, name, description, display_order, is_active) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$survey_id, 'Team & Development', 'Team structure and development practices', 4, 1]);
    $section4_id = $pdo->lastInsertId();
    echo "Created Section 4 with ID: $section4_id\n";
    
    // Section 4 Questions
    $questions4 = [
        [$section4_id, 'Team size', 'radio', 1, 1, 'Number of developers working on the project'],
        [$section4_id, 'Team experience level', 'radio', 1, 2, 'Average experience level of the team'],
        [$section4_id, 'Development methodology', 'radio', 0, 3, 'Preferred development approach'],
        [$section4_id, 'Priority ranking (drag to reorder)', 'ranking', 1, 4, 'Rank these priorities from most to least important'],
        [$section4_id, 'Additional requirements or constraints', 'textarea', 0, 5, 'Any other important information for project planning']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO survey_questions (section_id, question_text, question_type, is_required, display_order, help_text) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($questions4 as $q) {
        $stmt->execute($q);
    }
    
    // Get question IDs for section 4
    $stmt = $pdo->prepare("SELECT id, display_order FROM survey_questions WHERE section_id = ?");
    $stmt->execute([$section4_id]);
    $questionMap4 = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $questionMap4[$row['display_order']] = $row['id'];
    }
    
    $stmt = $pdo->prepare("INSERT INTO survey_answer_options (question_id, option_text, option_value, display_order, is_active) VALUES (?, ?, ?, ?, ?)");
    
    // Add team size options
    $teamOptions = [
        ['Solo developer', '1', 1, 1],
        ['2-3 developers', '2-3', 2, 1],
        ['4-8 developers', '4-8', 3, 1],
        ['More than 8 developers', '8+', 4, 1]
    ];
    
    foreach ($teamOptions as $opt) {
        $stmt->execute([$questionMap4[1], $opt[0], $opt[1], $opt[2], $opt[3]]);
    }
    
    // Add experience level options
    $expOptions = [
        ['Beginner (< 2 years)', 'beginner', 1, 1],
        ['Intermediate (2-5 years)', 'intermediate', 2, 1],
        ['Senior (5-10 years)', 'senior', 3, 1],
        ['Expert (10+ years)', 'expert', 4, 1]
    ];
    
    foreach ($expOptions as $opt) {
        $stmt->execute([$questionMap4[2], $opt[0], $opt[1], $opt[2], $opt[3]]);
    }
    
    // Add methodology options
    $methodOptions = [
        ['Agile/Scrum', 'agile', 1, 1],
        ['Waterfall', 'waterfall', 2, 1],
        ['Kanban', 'kanban', 3, 1],
        ['DevOps/Continuous', 'devops', 4, 1],
        ['No specific methodology', 'none', 5, 1]
    ];
    
    foreach ($methodOptions as $opt) {
        $stmt->execute([$questionMap4[3], $opt[0], $opt[1], $opt[2], $opt[3]]);
    }
    
    // Add priority ranking options
    $priorityOptions = [
        ['Development speed', 'speed', 1, 1],
        ['Code quality', 'quality', 2, 1],
        ['Scalability', 'scalability', 3, 1],
        ['Cost efficiency', 'cost', 4, 1],
        ['Security', 'security', 5, 1]
    ];
    
    foreach ($priorityOptions as $opt) {
        $stmt->execute([$questionMap4[4], $opt[0], $opt[1], $opt[2], $opt[3]]);
    }
    
    echo "\nMigration completed successfully!\n";
    
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
    
    echo "\nTotal created:\n";
    echo "- Sections: {$counts['sections']}\n";
    echo "- Questions: {$counts['questions']}\n";
    echo "- Options: {$counts['options']}\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}