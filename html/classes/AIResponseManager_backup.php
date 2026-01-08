<?php
// Backup of the original storeSkiliksResponse method before refactoring

public function storeSkiliksResponse($responseData, $sprintId = null, $promptId = null) {
    try {
        $this->db->beginTransaction();
        
        $sessionId = $responseData['sessionId'] ?? uniqid('skilliks_');
        $data = $responseData['data'] ?? $responseData;
        
        // Create session
        $stmt = $this->db->prepare("
            INSERT INTO ai_sessions 
            (id, platform, model, status, active, sprint_id, prompt_id, 
             conversation_length, created_at, started_at, completed_at, last_activity)
            VALUES (?, 'skilliks', ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)
        ");
        $stmt->execute([
            $sessionId,
            $data['model'] ?? null,
            $data['status'] ?? 'completed',
            $data['active'] ?? true,
            $sprintId,
            $promptId,
            $data['conversationLength'] ?? 0,
            $data['startTime'] ?? null,
            $data['completionTime'] ?? null,
            $data['lastActivity'] ?? null
        ]);
        
        // Store context stats
        if (isset($data['contextStats'])) {
            $stats = $data['contextStats'];
            $stmt = $this->db->prepare("
                INSERT INTO ai_context_stats 
                (session_id, message_count, token_count, token_limit, 
                 utilization_percent, tool_execution_count)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $sessionId,
                $stats['messageCount'] ?? 0,
                $stats['tokenCount'] ?? 0,
                $stats['tokenLimit'] ?? 0,
                $stats['utilizationPercent'] ?? 0,
                $stats['toolExecutionCount'] ?? 0
            ]);
        }
        
        // Store main response message
        $stmt = $this->db->prepare("
            INSERT INTO ai_messages (session_id, prompt_id, type, role, sequence_number)
            VALUES (?, ?, 'assistant', 'assistant', 1)
        ");
        $stmt->execute([$sessionId, $promptId]);
        $messageId = $this->db->lastInsertId();
        
        // Store response content
        if (isset($data['response'])) {
            $stmt = $this->db->prepare("
                INSERT INTO ai_message_content (message_id, content_type, content_text)
                VALUES (?, 'response', ?)
            ");
            $stmt->execute([$messageId, $data['response']]);
        }
        
        // Store tools executed
        if (isset($data['toolsExecuted']) && is_array($data['toolsExecuted'])) {
            foreach ($data['toolsExecuted'] as $tool) {
                $stmt = $this->db->prepare("
                    INSERT INTO ai_tool_executions 
                    (session_id, message_id, tool_name, parameters, result, result_summary)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                $result = $tool['result'] ?? null;
                $resultSummary = is_string($result) ? $result : null;
                $resultJson = is_array($result) || is_object($result) ? json_encode($result) : null;
                
                $stmt->execute([
                    $sessionId,
                    $messageId,
                    $tool['tool'] ?? 'unknown',
                    json_encode($tool['parameters'] ?? []),
                    $resultJson,
                    $resultSummary
                ]);
            }
        }
        
        // Store token usage
        if (isset($data['tokenUsage'])) {
            $usage = $data['tokenUsage'];
            $stmt = $this->db->prepare("
                INSERT INTO ai_token_usage 
                (session_id, message_id, prompt_tokens, candidate_tokens, 
                 total_tokens, cached_content_tokens)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $sessionId,
                $messageId,
                $usage['promptTokens'] ?? 0,
                $usage['candidateTokens'] ?? 0,
                $usage['totalTokens'] ?? 0,
                $usage['cachedContentTokens'] ?? null
            ]);
        }
        
        // Store conversation results
        if (isset($data['status']) && $data['status'] === 'completed') {
            $stmt = $this->db->prepare("
                INSERT INTO ai_conversation_results 
                (session_id, sprint_id, prompt_id, platform, final_response,
                 api_duration_ms, total_duration_ms)
                VALUES (?, ?, ?, 'skilliks', ?, ?, ?)
            ");
            
            $startTime = strtotime($data['startTime'] ?? 'now');
            $endTime = strtotime($data['completionTime'] ?? 'now');
            $duration = ($endTime - $startTime) * 1000;
            
            $stmt->execute([
                $sessionId,
                $sprintId,
                $promptId,
                $data['response'] ?? '',
                $duration,
                $duration
            ]);
        }
        
        $this->db->commit();
        return $sessionId;
        
    } catch (Exception $e) {
        $this->db->rollBack();
        error_log("Error storing Skilliks response: " . $e->getMessage());
        throw $e;
    }
}
?>