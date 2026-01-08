<?php
/**
 * PromptGenerator Class
 * 
 * Generates development prompts from work items
 */

class PromptGenerator {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Generate prompts for all work items in a sprint
     */
    public function generateSprintPrompts($sprintId, $projectId) {
        try {
            // Get all approved work items in the sprint
            $stmt = $this->db->prepare("
                SELECT wi.*, 
                       parent.title as parent_title,
                       parent.type as parent_type
                FROM sprint_backlog wi
                LEFT JOIN work_items parent ON wi.parent_id = parent.id
                WHERE wi.sprint_id = ?
                ORDER BY wi.position ASC, wi.priority DESC
            ");
            $stmt->execute([$sprintId]);
            $workItems = $stmt->fetchAll();
            
            $prompts = [];
            $promptOrder = 1;
            
            foreach ($workItems as $item) {
                $prompt = $this->generateWorkItemPrompt($item);
                if ($prompt) {
                    $prompts[] = [
                        'project_id' => $projectId,
                        'sprint_id' => $sprintId,
                        'work_item_id' => $item['id'],
                        'prompt_text' => $prompt,
                        'prompt_order' => $promptOrder++,
                        'parent_prompt_id' => null // Will be set later for dependent items
                    ];
                }
            }
            
            return $prompts;
        } catch (PDOException $e) {
            error_log("PromptGenerator generateSprintPrompts error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Generate prompt for a single work item
     */
    public function generateWorkItemPrompt($workItem) {
        switch ($workItem['type']) {
            case 'story':
                return $this->generateStoryPrompt($workItem);
            case 'task':
                return $this->generateTaskPrompt($workItem);
            case 'bug':
                return $this->generateBugPrompt($workItem);
            case 'spike':
                return $this->generateSpikePrompt($workItem);
            case 'epic':
                return $this->generateEpicPrompt($workItem);
            default:
                return null;
        }
    }
    
    /**
     * Generate prompt for user story
     */
    private function generateStoryPrompt($item) {
        $prompt = "## User Story: {$item['title']}\n\n";
        
        // Parse description for user story format
        $description = $item['description'] ?? '';
        if (preg_match('/As a (.+), I want (.+) so that (.+)/i', $description, $matches)) {
            $prompt .= "As a {$matches[1]}\n";
            $prompt .= "I want {$matches[2]}\n";
            $prompt .= "So that {$matches[3]}\n\n";
        } else {
            $prompt .= "### Description\n{$description}\n\n";
        }
        
        // Add acceptance criteria
        $acceptanceCriteria = $this->getAcceptanceCriteria($item['id']);
        if (!empty($acceptanceCriteria)) {
            $prompt .= "### Acceptance Criteria\n";
            foreach ($acceptanceCriteria as $criteria) {
                $prompt .= "- {$criteria['description']}\n";
            }
            $prompt .= "\n";
        }
        
        // Add technical details from prompt field
        if (!empty($item['prompt'])) {
            $prompt .= "### Technical Implementation\n{$item['prompt']}\n\n";
        }
        
        // Add parent context
        if (!empty($item['parent_title'])) {
            $prompt .= "### Context\nThis story is part of {$item['parent_type']}: {$item['parent_title']}\n\n";
        }
        
        $prompt .= "### Implementation Request\n";
        $prompt .= "Please implement this user story following best practices and existing code patterns in the project. ";
        $prompt .= "Ensure all acceptance criteria are met and the implementation is properly tested.\n";
        
        return $prompt;
    }
    
    /**
     * Generate prompt for task
     */
    private function generateTaskPrompt($item) {
        $prompt = "## Task: {$item['title']}\n\n";
        
        if (!empty($item['description'])) {
            $prompt .= "### Description\n{$item['description']}\n\n";
        }
        
        if (!empty($item['prompt'])) {
            $prompt .= "### Technical Details\n{$item['prompt']}\n\n";
        }
        
        if (!empty($item['parent_title'])) {
            $prompt .= "### Parent Item\nThis task is part of {$item['parent_type']}: {$item['parent_title']}\n\n";
        }
        
        $prompt .= "### Implementation Request\n";
        $prompt .= "Please complete this task following the project's coding standards and conventions. ";
        
        if ($item['estimate_hours']) {
            $prompt .= "Estimated effort: {$item['estimate_hours']} hours. ";
        }
        
        $prompt .= "\n";
        
        return $prompt;
    }
    
    /**
     * Generate prompt for bug fix
     */
    private function generateBugPrompt($item) {
        $prompt = "## Bug Fix: {$item['title']}\n\n";
        
        $description = $item['description'] ?? '';
        
        // Try to parse bug description for structure
        if (preg_match('/Steps to reproduce:(.+?)Expected:(.+?)Actual:(.+)/is', $description, $matches)) {
            $prompt .= "### Steps to Reproduce\n" . trim($matches[1]) . "\n\n";
            $prompt .= "### Expected Behavior\n" . trim($matches[2]) . "\n\n";
            $prompt .= "### Actual Behavior\n" . trim($matches[3]) . "\n\n";
        } else {
            $prompt .= "### Bug Description\n{$description}\n\n";
        }
        
        if (!empty($item['prompt'])) {
            $prompt .= "### Additional Information\n{$item['prompt']}\n\n";
        }
        
        $prompt .= "### Fix Request\n";
        $prompt .= "Please identify the root cause of this bug and implement a fix. ";
        $prompt .= "Include appropriate error handling and add tests to prevent regression. ";
        $prompt .= "Priority: {$item['priority']}\n";
        
        return $prompt;
    }
    
    /**
     * Generate prompt for spike
     */
    private function generateSpikePrompt($item) {
        $prompt = "## Technical Spike: {$item['title']}\n\n";
        
        $prompt .= "### Research Objective\n";
        $prompt .= $item['description'] ?? 'Research and provide recommendations.';
        $prompt .= "\n\n";
        
        if (!empty($item['prompt'])) {
            $prompt .= "### Specific Areas to Investigate\n{$item['prompt']}\n\n";
        }
        
        $prompt .= "### Deliverables\n";
        $prompt .= "- Technical analysis and findings\n";
        $prompt .= "- Proof of concept code (if applicable)\n";
        $prompt .= "- Recommendations for implementation\n";
        $prompt .= "- Risk assessment and mitigation strategies\n\n";
        
        if ($item['story_points']) {
            $prompt .= "Time boxed to: {$item['story_points']} story points\n";
        }
        
        return $prompt;
    }
    
    /**
     * Generate prompt for epic
     */
    private function generateEpicPrompt($item) {
        $prompt = "## Epic Overview: {$item['title']}\n\n";
        
        $prompt .= "### Epic Description\n";
        $prompt .= $item['description'] ?? 'High-level feature implementation.';
        $prompt .= "\n\n";
        
        if (!empty($item['prompt'])) {
            $prompt .= "### Technical Approach\n{$item['prompt']}\n\n";
        }
        
        // Get child items
        $children = $this->getChildWorkItems($item['id']);
        if (!empty($children)) {
            $prompt .= "### Child Items\n";
            foreach ($children as $child) {
                $prompt .= "- {$child['type']}: {$child['title']}\n";
            }
            $prompt .= "\n";
        }
        
        $prompt .= "### Implementation Note\n";
        $prompt .= "This epic will be implemented through its child stories and tasks. ";
        $prompt .= "Please ensure consistency across all related implementations.\n";
        
        return $prompt;
    }
    
    /**
     * Get acceptance criteria for a work item
     */
    private function getAcceptanceCriteria($workItemId) {
        try {
            $stmt = $this->db->prepare("
                SELECT description 
                FROM acceptance_criteria 
                WHERE work_item_id = ? 
                ORDER BY position ASC
            ");
            $stmt->execute([$workItemId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get child work items
     */
    private function getChildWorkItems($parentId) {
        try {
            $stmt = $this->db->prepare("
                SELECT wi.type, wi.title 
                FROM work_items wi
                JOIN work_item_relations wir ON wi.id = wir.child_id
                WHERE wir.parent_id = ?
                ORDER BY wi.position ASC
            ");
            $stmt->execute([$parentId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Save generated prompts to database
     */
    public function savePrompts($prompts) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO project_dev_prompts (
                    project_id, work_item_id, sprint_id, parent_prompt_id,
                    prompt_text, prompt_order, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");

            $savedPrompts = [];

            foreach ($prompts as $prompt) {
                // Sanitize the prompt text before saving
                $sanitizedPromptText = sanitizePromptText($prompt['prompt_text']);

                $result = $stmt->execute([
                    $prompt['project_id'],
                    $prompt['work_item_id'],
                    $prompt['sprint_id'],
                    $prompt['parent_prompt_id'],
                    $sanitizedPromptText,
                    $prompt['prompt_order']
                ]);

                if ($result) {
                    $promptId = $this->db->lastInsertId();
                    $savedPrompts[] = array_merge($prompt, ['id' => $promptId]);
                }
            }
            
            return $savedPrompts;
        } catch (PDOException $e) {
            error_log("PromptGenerator savePrompts error: " . $e->getMessage());
            return [];
        }
    }
}