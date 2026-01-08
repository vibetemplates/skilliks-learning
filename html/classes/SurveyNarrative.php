<?php
/**
 * Survey Narrative Generator
 * 
 * Generates a narrative description from user's survey responses
 * for use with LLM course recommendation
 */

class SurveyNarrative {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Generate a narrative from user's survey responses
     * 
     * @param int $userId
     * @return string
     */
    public function generateNarrative($userId) {
        $responses = $this->getUserResponses($userId);
        
        if (empty($responses)) {
            return $this->getBeginnerDefaultNarrative();
        }
        
        // Group responses by question for easier processing
        $groupedResponses = $this->groupResponsesByQuestion($responses);
        
        // Check if we have minimal responses
        $responseCount = count($groupedResponses);
        if ($responseCount < 5) {
            // Include partial data but supplement with beginner defaults
            $narrative = $this->buildNarrative($groupedResponses);
            $narrative .= "\n### Note\n";
            $narrative .= "This profile is based on limited survey responses ({$responseCount} questions answered). ";
            $narrative .= "Recommending beginner-friendly courses until more information is provided.\n";
            return $narrative;
        }
        
        // Build the narrative
        $narrative = $this->buildNarrative($groupedResponses);
        
        return $narrative;
    }
    
    /**
     * Get all survey responses for a user
     * 
     * @param int $userId
     * @return array
     */
    private function getUserResponses($userId) {
        $sql = "SELECT 
                    sq.id as question_id,
                    sq.question_text,
                    sq.question_type,
                    sr.answer_text,
                    sao.option_text as selected_option,
                    sr.rank_value,
                    ss.name as section_name
                FROM survey_responses sr
                JOIN survey_questions sq ON sr.question_id = sq.id
                LEFT JOIN survey_answer_options sao ON sr.answer_option_id = sao.id
                LEFT JOIN survey_sections ss ON sq.section_id = ss.id
                WHERE sr.user_id = :user_id
                ORDER BY ss.display_order, sq.display_order, sr.rank_value";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Group responses by question for easier processing
     * 
     * @param array $responses
     * @return array
     */
    private function groupResponsesByQuestion($responses) {
        $grouped = [];
        
        foreach ($responses as $response) {
            $questionId = $response['question_id'];
            
            if (!isset($grouped[$questionId])) {
                $grouped[$questionId] = [
                    'question_text' => $response['question_text'],
                    'question_type' => $response['question_type'],
                    'section_name' => $response['section_name'],
                    'answers' => []
                ];
            }
            
            if ($response['question_type'] === 'checkbox' || $response['question_type'] === 'ranking') {
                $grouped[$questionId]['answers'][] = [
                    'text' => $response['answer_text'] ?? $response['selected_option'],
                    'rank' => $response['rank_value']
                ];
            } else {
                $grouped[$questionId]['answers'] = $response['answer_text'] ?? $response['selected_option'];
            }
        }
        
        return $grouped;
    }
    
    /**
     * Build a narrative from grouped responses
     * 
     * @param array $groupedResponses
     * @return string
     */
    private function buildNarrative($groupedResponses) {
        $narrative = "## User Skills Survey Summary\n\n";
        
        // Extract key information
        $goals = [];
        $experience = [];
        $interests = [];
        $careerGoals = [];
        $timeCommitment = [];
        $technicalBackground = [];
        
        foreach ($groupedResponses as $questionId => $data) {
            $questionText = $data['question_text'];
            $answers = $data['answers'];
            
            // Goals in learning AI
            if (strpos($questionText, 'goals in learning Artificial Intelligence') !== false) {
                foreach ($answers as $answer) {
                    $goals[] = $answer['text'];
                }
            }
            
            // AI experience questions
            elseif (strpos($questionText, 'ChatGPT') !== false) {
                $experience['ai_assistants'] = $answers;
            }
            elseif (strpos($questionText, 'AI APIs') !== false) {
                $experience['apis'] = $answers;
            }
            elseif (strpos($questionText, 'prompt engineering') !== false) {
                $experience['prompt_engineering'] = $answers;
            }
            elseif (strpos($questionText, 'Vector databases') !== false) {
                $experience['vector_db'] = $answers;
            }
            elseif (strpos($questionText, 'AI-powered applications') !== false) {
                $experience['built_apps'] = $answers;
            }
            
            // Technical background
            elseif (strpos($questionText, 'technical/IT fields') !== false) {
                $technicalBackground['level'] = $answers;
            }
            elseif (strpos($questionText, 'Python Programming') !== false) {
                if (is_array($answers)) {
                    $technicalBackground['python'] = implode(', ', array_column($answers, 'text'));
                } else {
                    $technicalBackground['python'] = $answers;
                }
            }
            elseif (strpos($questionText, 'Javascript/Typescript') !== false) {
                $technicalBackground['javascript'] = $answers;
            }
            
            // Career goals ranking
            elseif (strpos($questionText, 'rank you career goals') !== false) {
                foreach ($answers as $answer) {
                    $careerGoals[] = [
                        'goal' => $answer['text'],
                        'rank' => $answer['rank']
                    ];
                }
                // Sort by rank (lower number = higher priority)
                usort($careerGoals, function($a, $b) {
                    return $a['rank'] - $b['rank'];
                });
            }
            
            // Topics of interest ranking
            elseif (strpos($questionText, 'topics that interest you') !== false) {
                foreach ($answers as $answer) {
                    if ($answer['rank'] !== null) {
                        $interests[] = [
                            'topic' => $answer['text'],
                            'rank' => $answer['rank']
                        ];
                    }
                }
                // Sort by rank
                usort($interests, function($a, $b) {
                    return $a['rank'] - $b['rank'];
                });
            }
            
            // Time commitment
            elseif (strpos($questionText, 'hours per week') !== false) {
                $timeCommitment[] = $questionText . ": " . $answers;
            }
        }
        
        // Build the narrative
        $narrative .= "### Learning Goals\n";
        $narrative .= "The user wants to:\n";
        foreach ($goals as $goal) {
            $narrative .= "- " . $goal . "\n";
        }
        
        $narrative .= "\n### Current Experience Level\n";
        $narrative .= "- AI Assistants: " . ($experience['ai_assistants'] ?? 'Not specified') . "\n";
        $narrative .= "- AI APIs: " . ($experience['apis'] ?? 'Not specified') . "\n";
        $narrative .= "- Prompt Engineering: " . ($experience['prompt_engineering'] ?? 'Not specified') . "\n";
        $narrative .= "- Vector Databases: " . ($experience['vector_db'] ?? 'Not specified') . "\n";
        $narrative .= "- Built AI Applications: " . ($experience['built_apps'] ?? 'Not specified') . "\n";
        
        $narrative .= "\n### Technical Background\n";
        $narrative .= "- Experience Level: " . ($technicalBackground['level'] ?? 'Not specified') . "\n";
        $narrative .= "- Python: " . ($technicalBackground['python'] ?? 'Not specified') . "\n";
        $narrative .= "- JavaScript/TypeScript: " . ($technicalBackground['javascript'] ?? 'Not specified') . "\n";
        
        $narrative .= "\n### Career Goals (Ranked by Priority)\n";
        $topGoals = array_slice($careerGoals, 0, 5);
        foreach ($topGoals as $i => $goal) {
            $narrative .= ($i + 1) . ". " . $goal['goal'] . "\n";
        }
        
        $narrative .= "\n### Topics of Interest (Ranked by Priority)\n";
        $topInterests = array_slice($interests, 0, 10);
        foreach ($topInterests as $i => $interest) {
            $narrative .= ($i + 1) . ". " . $interest['topic'] . "\n";
        }
        
        $narrative .= "\n### Time Commitment\n";
        foreach ($timeCommitment as $time) {
            $narrative .= "- " . $time . "\n";
        }
        
        $narrative .= "\n### Recommendation Focus\n";
        $narrative .= "Based on this profile, the user appears to be a beginner with ";
        
        // Determine focus based on top career goals and interests
        if (!empty($careerGoals)) {
            $topGoal = $careerGoals[0]['goal'];
            if (strpos($topGoal, 'Project Manager') !== false) {
                $narrative .= "strong interest in AI project management. ";
                $narrative .= "They should focus on courses that cover AI fundamentals, project management methodologies, ";
                $narrative .= "and understanding AI capabilities without deep technical implementation.";
            } elseif (strpos($topGoal, 'Founder') !== false) {
                $narrative .= "entrepreneurial aspirations in the AI space. ";
                $narrative .= "They should focus on courses covering AI fundamentals, product development, ";
                $narrative .= "and practical AI applications for business.";
            } else {
                $narrative .= "interest in technical AI roles. ";
                $narrative .= "They should start with foundational courses in AI concepts and programming basics.";
            }
        }
        
        return $narrative;
    }
    
    
    /**
     * Get user's skill interests mapped to actual skills
     * 
     * @param int $userId
     * @return array Array of skill IDs
     */
    public function getUserSkillInterests($userId) {
        $interests = $this->getTopInterests($userId, 20); // Get more interests
        $skillIds = [];
        
        // Map interest text to skill names/categories
        $interestToSkillMap = [
            'AI Assisted Coding (Vibe Coding)' => ['Cursor', 'Claude Code', 'Gemini CLI', 'Visual Studio Code'],
            'AI Concepts - Retreival Augmented Generation' => ['RAG evaluation metrics', 'Retrieval Pipelines', 'Vector Databases', 'Vector Embedding'],
            'AI Concepts - Supervised Fine Tuning' => ['Hugging Face Transformers', 'OpenAI Api'],
            'RAG - Vector Embedding' => ['Vector Embedding', 'Vector Databases'],
            'RAG - Knowledge Graphs' => ['Knowledge Graphs', 'Graph Databases'],
            'Prompt Engineering' => ['Prompting Basics'],
            'Context Engineering' => ['Model Context Protocol (MCP)'],
            'AI Agents' => ['LangChain', 'LlamaIndex (GPT Index)'],
            'Multi-Agent Systems' => ['LangChain', 'Agent Frameworks'],
            'DevOps & Cloud' => ['Google Collab', 'AWS AI APIs', 'Azure Cognitive Services'],
            'Local Models' => ['Ollama', 'Hugging Face Transformers'],
            'Project Management' => ['Agile', 'Scrum'],
            'Project Architecture' => ['API Design', 'Design Patterns', 'Microservices']
        ];
        
        // Get skill IDs for each interest
        foreach ($interests as $interest) {
            if (isset($interestToSkillMap[$interest])) {
                foreach ($interestToSkillMap[$interest] as $skillName) {
                    $sql = "SELECT id FROM skills WHERE name = :name AND is_active = 1";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute(['name' => $skillName]);
                    $skillId = $stmt->fetchColumn();
                    
                    if ($skillId && !in_array($skillId, $skillIds)) {
                        $skillIds[] = $skillId;
                    }
                }
            }
        }
        
        return $skillIds;
    }
    
    /**
     * Get default narrative for beginners with no/minimal survey responses
     * 
     * @return string
     */
    private function getBeginnerDefaultNarrative() {
        return "## User Skills Survey Summary\n\n" .
               "### New User - Beginner Profile\n" .
               "This user has not yet completed the skills survey. Based on default settings:\n\n" .
               "### Learning Goals\n" .
               "- To get general AI knowledge\n" .
               "- To explore AI capabilities and applications\n" .
               "- To learn foundational concepts\n\n" .
               "### Current Experience Level\n" .
               "- New to AI and programming\n" .
               "- No prior experience assumed\n\n" .
               "### Recommendation Focus\n" .
               "Recommending beginner-friendly courses covering:\n" .
               "- AI fundamentals and concepts\n" .
               "- Introduction to AI tools\n" .
               "- Basic programming concepts\n" .
               "- Getting started with AI assistants\n\n" .
               "Complete the skills survey for personalized recommendations.";
    }
    
    /**
     * Enhanced getTopInterests with defaults for users with limited responses
     * 
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getTopInterests($userId, $limit = 5) {
        // First try to get user's actual interests
        $sql = "SELECT sao.option_text
                FROM survey_responses sr
                JOIN survey_answer_options sao ON sr.answer_option_id = sao.id
                JOIN survey_questions sq ON sr.question_id = sq.id
                WHERE sr.user_id = :user_id
                AND sq.question_text LIKE '%rank the topics that interest you%'
                AND sr.rank_value IS NOT NULL
                ORDER BY sr.rank_value ASC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $interests = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // If user has no interests or very few, add beginner defaults
        if (count($interests) < 3) {
            $beginnerDefaults = [
                'AI Fundamentals',
                'Getting Started with AI',
                'Introduction to Programming',
                'AI Tools Overview',
                'Beginner Projects'
            ];
            
            // Merge with existing interests, avoiding duplicates
            foreach ($beginnerDefaults as $default) {
                if (!in_array($default, $interests) && count($interests) < $limit) {
                    $interests[] = $default;
                }
            }
        }
        
        return array_slice($interests, 0, $limit);
    }
}