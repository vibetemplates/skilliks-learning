<?php
/**
 * Populate Lesson Skills Table
 * 
 * This script analyzes lesson titles, descriptions, and transcripts
 * to automatically populate the lesson_skills table
 */

require_once dirname(__DIR__) . '/config/database.php';

class LessonSkillsPopulator {
    private $db;
    private $skills = [];
    private $skillKeywords = [];
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->loadSkills();
        $this->buildSkillKeywords();
    }
    
    /**
     * Load all skills from database
     */
    private function loadSkills() {
        $stmt = $this->db->query("SELECT id, name, category, description FROM skills WHERE is_active = 1");
        $this->skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Build keyword mappings for skills
     */
    private function buildSkillKeywords() {
        // Map keywords to skill IDs
        $this->skillKeywords = [
            // APIs and SDKs
            'openai' => ['skill' => 'OpenAI Api', 'variations' => ['openai', 'gpt', 'chatgpt', 'gpt-3', 'gpt-4']],
            'anthropic' => ['skill' => 'Anthropic Claude API', 'variations' => ['anthropic', 'claude', 'claude api']],
            'gemini' => ['skill' => 'Gemini API', 'variations' => ['gemini', 'google ai', 'vertex ai']],
            'langchain' => ['skill' => 'LangChain', 'variations' => ['langchain', 'lang chain']],
            'llamaindex' => ['skill' => 'LlamaIndex (GPT Index)', 'variations' => ['llamaindex', 'llama index', 'gpt index']],
            'huggingface' => ['skill' => 'Hugging Face Transformers', 'variations' => ['hugging face', 'huggingface', 'transformers']],
            
            // Prompt Engineering
            'prompt' => ['skill' => 'Prompting Basics', 'variations' => ['prompt', 'prompting', 'prompt engineering', 'prompt design']],
            'mcp' => ['skill' => 'Model Context Protocol (MCP)', 'variations' => ['mcp', 'model context protocol', 'context protocol']],
            
            // RAG
            'rag' => ['skill' => 'RAG evaluation metrics', 'variations' => ['rag', 'retrieval augmented generation', 'retrieval-augmented']],
            'retrieval' => ['skill' => 'Retrieval Pipelines', 'variations' => ['retrieval pipeline', 'retrieval system', 'information retrieval']],
            'vector' => ['skill' => 'Vector Databases', 'variations' => ['vector', 'vector database', 'vectordb', 'pinecone', 'weaviate', 'chroma']],
            'embedding' => ['skill' => 'Vector Embedding', 'variations' => ['embedding', 'embeddings', 'text embedding', 'vector embedding']],
            
            // Development
            'python' => ['skill' => 'Python', 'variations' => ['python', 'py', 'python3']],
            'javascript' => ['skill' => 'JavaScript', 'variations' => ['javascript', 'js', 'javascript programming']],
            'typescript' => ['skill' => 'TypeScript', 'variations' => ['typescript', 'type script']],
            'nodejs' => ['skill' => 'Node.js', 'variations' => ['node.js', 'nodejs', 'node js']],
            'php' => ['skill' => 'PHP', 'variations' => ['php', 'php programming']],
            'html' => ['skill' => 'HTML', 'variations' => ['html', 'html5', 'hypertext markup']],
            'css' => ['skill' => 'CSS', 'variations' => ['css', 'css3', 'styling', 'stylesheet', 'cascading style']],
            'react' => ['skill' => 'React', 'variations' => ['react', 'reactjs', 'react.js']],
            'vue' => ['skill' => 'Vue.js', 'variations' => ['vue', 'vuejs', 'vue.js']],
            'angular' => ['skill' => 'Angular', 'variations' => ['angular', 'angularjs']],
            'bootstrap' => ['skill' => 'Bootstrap', 'variations' => ['bootstrap', 'bootstrap css']],
            
            // AI Tools
            'cursor' => ['skill' => 'Cursor', 'variations' => ['cursor', 'cursor.ai', 'cursor ai', 'cursor editor']],
            'claude-code' => ['skill' => 'Claude Code', 'variations' => ['claude code', 'claude-code', 'claude.ai code']],
            'gemini-cli' => ['skill' => 'Gemini CLI', 'variations' => ['gemini cli', 'gemini-cli', 'gemini command line']],
            'vscode' => ['skill' => 'Visual Studio Code', 'variations' => ['vscode', 'vs code', 'visual studio code']],
            
            // Cloud Tools
            'colab' => ['skill' => 'Google Collab', 'variations' => ['colab', 'google colab', 'colaboratory']],
            'gradio' => ['skill' => 'Gradio', 'variations' => ['gradio']],
            'streamlit' => ['skill' => 'Streamlit', 'variations' => ['streamlit']],
            
            // Architecture
            'api-design' => ['skill' => 'API Design', 'variations' => ['api design', 'api architecture', 'restful api', 'rest api']],
            'design-patterns' => ['skill' => 'Design Patterns', 'variations' => ['design pattern', 'design patterns', 'software patterns']],
            'microservices' => ['skill' => 'Microservices', 'variations' => ['microservice', 'microservices', 'micro service']],
            
            // Methodology
            'agile' => ['skill' => 'Agile', 'variations' => ['agile', 'agile methodology', 'agile development']],
            'scrum' => ['skill' => 'Scrum', 'variations' => ['scrum', 'scrum master', 'sprint']],
            
            // Database
            'mysql' => ['skill' => 'MySQL', 'variations' => ['mysql', 'mariadb']],
            'apache' => ['skill' => 'Apache', 'variations' => ['apache', 'apache server', 'httpd']],
            
            // Local LLMs
            'ollama' => ['skill' => 'Ollama', 'variations' => ['ollama', 'local llm', 'local model']],
            
            // Development Tools
            'git' => ['skill' => 'Git', 'variations' => ['git', 'github', 'version control']],
            'linux' => ['skill' => 'Linux', 'variations' => ['linux', 'ubuntu', 'debian']],
            'go' => ['skill' => 'Go', 'variations' => [' go ', 'golang', 'go programming']],
            
            // AI/ML Concepts
            'machine-learning' => ['skill' => 'Machine Learning', 'variations' => ['machine learning', 'ml', 'deep learning']],
            'architecture-general' => ['skill' => 'Architecture', 'variations' => ['architecture', 'system architecture']],
            'project-mgmt' => ['skill' => 'Project Management', 'variations' => ['project management', 'project manager']],
            'vibe-coding' => ['skill' => 'Vibe Coding', 'variations' => ['vibe coding', 'ai assisted coding', 'ai-assisted coding']],
        ];
    }
    
    /**
     * Extract skills from lesson content
     */
    public function extractSkillsFromLesson($lesson) {
        $detectedSkills = [];
        
        // Combine all text content for analysis
        $content = strtolower(
            $lesson['title'] . ' ' . 
            ($lesson['description'] ?? '') . ' ' . 
            ($lesson['video_transcript'] ?? '')
        );
        
        // Check each skill keyword
        foreach ($this->skillKeywords as $key => $skillData) {
            foreach ($skillData['variations'] as $variation) {
                // Use word boundaries for better matching (avoid false positives)
                $pattern = '/\b' . preg_quote($variation, '/') . '\b/i';
                if (preg_match($pattern, $content)) {
                    // Find the skill ID
                    $skillId = $this->findSkillId($skillData['skill']);
                    if ($skillId) {
                        // Determine skill level based on content depth
                        $skillLevel = $this->determineSkillLevel($content, $variation);
                        $detectedSkills[$skillId] = $skillLevel;
                    }
                    break; // Found this skill, no need to check other variations
                }
            }
        }
        
        // Also check for exact skill name matches
        foreach ($this->skills as $skill) {
            $skillNameLower = strtolower($skill['name']);
            if (strpos($content, $skillNameLower) !== false && !isset($detectedSkills[$skill['id']])) {
                $skillLevel = $this->determineSkillLevel($content, $skillNameLower);
                $detectedSkills[$skill['id']] = $skillLevel;
            }
        }
        
        return $detectedSkills;
    }
    
    /**
     * Find skill ID by name
     */
    private function findSkillId($skillName) {
        foreach ($this->skills as $skill) {
            if (strcasecmp($skill['name'], $skillName) === 0) {
                return $skill['id'];
            }
        }
        return null;
    }
    
    /**
     * Determine skill level based on content analysis
     */
    private function determineSkillLevel($content, $keyword) {
        $count = substr_count($content, $keyword);
        $contentLength = strlen($content);
        
        // Simple heuristic: frequency and depth indicators
        $advancedTerms = ['advanced', 'expert', 'deep dive', 'mastery', 'optimization', 'architecture'];
        $intermediateTerms = ['intermediate', 'practical', 'hands-on', 'implementation', 'building'];
        $beginnerTerms = ['introduction', 'basics', 'getting started', 'beginner', 'fundamental', 'overview'];
        
        foreach ($beginnerTerms as $term) {
            if (strpos($content, $term) !== false) {
                return 'beginner';
            }
        }
        
        foreach ($advancedTerms as $term) {
            if (strpos($content, $term) !== false) {
                return 'advanced';
            }
        }
        
        foreach ($intermediateTerms as $term) {
            if (strpos($content, $term) !== false) {
                return 'intermediate';
            }
        }
        
        // Default based on frequency
        if ($count > 10 || ($contentLength > 5000 && $count > 5)) {
            return 'intermediate';
        }
        
        return 'beginner';
    }
    
    /**
     * Populate skills for all lessons
     */
    public function populateAllLessons() {
        // Get all lessons
        $stmt = $this->db->query("
            SELECT id, title, description, video_transcript 
            FROM lessons 
            WHERE status = 'published'
            ORDER BY id
        ");
        
        $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $totalLessons = count($lessons);
        $processedCount = 0;
        $skillsAddedCount = 0;
        
        echo "Processing {$totalLessons} lessons...\n\n";
        
        foreach ($lessons as $lesson) {
            $processedCount++;
            echo "Processing lesson {$processedCount}/{$totalLessons}: {$lesson['title']}\n";
            
            // Clear existing skills for this lesson
            $deleteStmt = $this->db->prepare("DELETE FROM lesson_skills WHERE lesson_id = ?");
            $deleteStmt->execute([$lesson['id']]);
            
            // Extract skills
            $detectedSkills = $this->extractSkillsFromLesson($lesson);
            
            if (empty($detectedSkills)) {
                echo "  No skills detected\n";
                continue;
            }
            
            // Insert detected skills
            $insertStmt = $this->db->prepare("
                INSERT INTO lesson_skills (lesson_id, skill_id, skill_level, is_required, added_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($detectedSkills as $skillId => $skillLevel) {
                $skillName = $this->getSkillName($skillId);
                echo "  Adding skill: {$skillName} (Level: {$skillLevel})\n";
                
                $insertStmt->execute([
                    $lesson['id'],
                    $skillId,
                    $skillLevel,
                    0, // is_required = false by default
                    1  // added_by = system user (ID 1)
                ]);
                $skillsAddedCount++;
            }
            
            echo "\n";
        }
        
        echo "Completed! Processed {$processedCount} lessons and added {$skillsAddedCount} skill associations.\n";
    }
    
    /**
     * Get skill name by ID
     */
    private function getSkillName($skillId) {
        foreach ($this->skills as $skill) {
            if ($skill['id'] == $skillId) {
                return $skill['name'];
            }
        }
        return 'Unknown';
    }
    
    /**
     * Show statistics
     */
    public function showStatistics() {
        // Current lesson skills count
        $stmt = $this->db->query("SELECT COUNT(*) FROM lesson_skills");
        $currentCount = $stmt->fetchColumn();
        
        // Lessons without skills
        $stmt = $this->db->query("
            SELECT COUNT(DISTINCT l.id)
            FROM lessons l
            LEFT JOIN lesson_skills ls ON l.id = ls.lesson_id
            WHERE ls.id IS NULL
            AND l.status = 'published'
        ");
        $lessonsWithoutSkills = $stmt->fetchColumn();
        
        // Most common skills
        $stmt = $this->db->query("
            SELECT s.name, COUNT(*) as count
            FROM lesson_skills ls
            JOIN skills s ON ls.skill_id = s.id
            GROUP BY s.id
            ORDER BY count DESC
            LIMIT 10
        ");
        $topSkills = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\n=== Current Statistics ===\n";
        echo "Total lesson-skill associations: {$currentCount}\n";
        echo "Published lessons without skills: {$lessonsWithoutSkills}\n";
        echo "\nTop 10 Most Common Skills:\n";
        foreach ($topSkills as $skill) {
            echo "  - {$skill['name']}: {$skill['count']} lessons\n";
        }
    }
}

// Run the script
try {
    $populator = new LessonSkillsPopulator();
    
    echo "=== Lesson Skills Population Script ===\n\n";
    
    // Show current statistics
    $populator->showStatistics();
    
    echo "\n\nDo you want to proceed with populating lesson skills? (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    
    if (trim($line) === 'yes') {
        echo "\nStarting population process...\n\n";
        $populator->populateAllLessons();
        
        echo "\n=== Updated Statistics ===\n";
        $populator->showStatistics();
    } else {
        echo "Population cancelled.\n";
    }
    
    fclose($handle);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}