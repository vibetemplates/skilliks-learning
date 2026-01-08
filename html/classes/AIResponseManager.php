<?php
/**
 * AI Response Manager
 * Handles storing and retrieving AI conversation responses from Claude and Skilliks
 */

class AIResponseManager {
    private $db;
    private $resultUsageData = null;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Parse embedded tool calls/results from text content
     * This handles both Claude Code format (separated by \n\n) and Skilliks format (separated by \n)
     */
    private function parseEmbeddedToolCalls($contentText) {
        $jsonObjects = [];

        // Check if it's Claude Code format (separated by \n\n)
        if (strpos($contentText, "}\n\n{") !== false) {
            // Claude format with \n\n separator
            $fixedJson = str_replace("}\n\n{", "},{", $contentText);
            if (!str_starts_with($fixedJson, '[')) {
                $fixedJson = '[' . $fixedJson;
            }
            if (!str_ends_with($fixedJson, ']')) {
                $fixedJson = $fixedJson . ']';
            }
            $jsonObjects = json_decode($fixedJson, true);
        }
        // Check if it's Skilliks format (separated by single \n)
        elseif (strpos($contentText, "}\n{") !== false) {
            // Split by newline to get individual JSON objects
            $lines = explode("\n", $contentText);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line)) {
                    $obj = json_decode($line, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($obj)) {
                        // Convert Skilliks format to Claude format
                        if (isset($obj['type']) && $obj['type'] === 'tool_use' && isset($obj['tools'])) {
                            // This is Skilliks format - convert each tool
                            foreach ($obj['tools'] as $tool) {
                                if (isset($tool['function'])) {
                                    $convertedObj = [
                                        'type' => 'tool_call',
                                        'name' => $tool['function']['name'] ?? 'unknown',
                                        'id' => uniqid('tool_'),
                                        'input' => json_decode($tool['function']['arguments'] ?? '{}', true)
                                    ];
                                    $jsonObjects[] = $convertedObj;
                                }
                            }
                        } else {
                            // Already in expected format
                            $jsonObjects[] = $obj;
                        }
                    }
                }
            }
        }
        // Try to parse as a single array
        else {
            $parsed = json_decode($contentText, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                $jsonObjects = $parsed;
            }
        }

        return $jsonObjects;
    }

    /**
     * Store Claude conversation response
     */
    public function storeClaudeResponse($responseData, $sprintId = null, $promptId = null) {
        try {
            $this->db->beginTransaction();
            
            // Extract session info from first message
            $sessionId = $responseData[0]['session_id'] ?? uniqid('claude_');
            $model = null;
            $cwd = null;
            $tools = [];
            
            // Find init message
            foreach ($responseData as $msg) {
                if ($msg['type'] === 'system' && $msg['subtype'] === 'init') {
                    $model = $msg['model'];
                    $cwd = $msg['cwd'];
                    $tools = $msg['tools'] ?? [];
                    break;
                }
            }
            
            // Create session
            $stmt = $this->db->prepare("
                INSERT INTO ai_sessions (id, platform, model, working_directory, sprint_id, prompt_id, created_at)
                VALUES (?, 'claude', ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$sessionId, $model, $cwd, $sprintId, $promptId]);
            
            // Store tools
            foreach ($tools as $order => $toolName) {
                $stmt = $this->db->prepare("
                    INSERT INTO ai_session_tools (session_id, tool_name, tool_order)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$sessionId, $toolName, $order]);
            }
            
            // Process messages
            $sequence = 0;
            $totalCost = 0;
            $totalInputTokens = 0;
            $totalOutputTokens = 0;
            $toolsUsedCount = 0;
            $finalResponse = '';
            $apiDuration = 0;
            $totalDuration = 0;
            $numTurns = 0;
            
            foreach ($responseData as $msg) {
                $sequence++;
                
                // Store message
                $messageId = $msg['message']['id'] ?? null;
                $type = $msg['type'];
                $subtype = $msg['subtype'] ?? null;
                $role = $msg['message']['role'] ?? null;
                $parentToolUseId = $msg['parent_tool_use_id'] ?? null;
                
                $stmt = $this->db->prepare("
                    INSERT INTO ai_messages (message_id, session_id, prompt_id, type, subtype, role, sequence_number, parent_tool_use_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$messageId, $sessionId, $promptId, $type, $subtype, $role, $sequence, $parentToolUseId]);
                $messageDbId = $this->db->lastInsertId();
                
                // Store message content
                if (isset($msg['message']['content'])) {
                    foreach ($msg['message']['content'] as $content) {
                        $contentType = $content['type'] ?? 'text';
                        $contentText = $content['text'] ?? ($content['content'] ?? null);

                        // Check if this is embedded JSON tool calls/results
                        if ($contentType === 'text' && is_string($contentText) &&
                            (strpos($contentText, '{"type":"tool_call"') !== false ||
                             strpos($contentText, '{"type":"tool_use"') !== false ||
                             strpos($contentText, '{"type":"tool_result"') !== false)) {

                            // Parse embedded tool calls
                            $jsonObjects = $this->parseEmbeddedToolCalls($contentText);

                            if (!empty($jsonObjects)) {
                                foreach ($jsonObjects as $parsed) {
                                    if (empty($parsed)) continue;

                                    if (isset($parsed['type'])) {
                                        if ($parsed['type'] === 'tool_call') {
                                            // For TodoWrite, use empty text. For other tools, format the input
                                            $inputText = '';
                                            $toolName = $parsed['name'] ?? null;
                                            if ($toolName && $toolName !== 'TodoWrite' && isset($parsed['input'])) {
                                                if (is_array($parsed['input'])) {
                                                    $inputParts = [];
                                                    foreach ($parsed['input'] as $key => $value) {
                                                        if (is_string($value) || is_numeric($value)) {
                                                            $inputParts[] = "$key: $value";
                                                        } elseif (is_bool($value)) {
                                                            $inputParts[] = "$key: " . ($value ? 'true' : 'false');
                                                        } else {
                                                            $inputParts[] = "$key: " . json_encode($value);
                                                        }
                                                    }
                                                    $inputText = implode("\n", $inputParts);
                                                } else {
                                                    $inputText = (string)$parsed['input'];
                                                }
                                            }

                                            $stmt = $this->db->prepare("
                                                INSERT INTO ai_message_content
                                                (message_id, content_type, content_text, content_data, tool_use_id, tool_name)
                                                VALUES (?, 'tool_use', ?, ?, ?, ?)
                                            ");
                                            $stmt->execute([
                                                $messageDbId,
                                                $inputText,
                                                json_encode($parsed),
                                                $parsed['id'] ?? null,
                                                $parsed['name'] ?? null
                                            ]);

                                            // Store tool execution record
                                            if (isset($parsed['name'])) {
                                                $toolsUsedCount++;
                                                $stmt = $this->db->prepare("
                                                    INSERT INTO ai_tool_executions
                                                    (session_id, message_id, tool_use_id, tool_name, parameters)
                                                    VALUES (?, ?, ?, ?, ?)
                                                ");
                                                $stmt->execute([
                                                    $sessionId,
                                                    $messageDbId,
                                                    $parsed['id'] ?? null,
                                                    $parsed['name'],
                                                    json_encode($parsed['input'] ?? [])
                                                ]);
                                            }
                                        } elseif ($parsed['type'] === 'tool_result') {
                                            // Store tool result with actual text
                                            $resultText = $parsed['content'] ?? '';

                                            $stmt = $this->db->prepare("
                                                INSERT INTO ai_message_content
                                                (message_id, content_type, content_text, content_data, tool_use_id, is_error)
                                                VALUES (?, 'tool_result', ?, ?, ?, ?)
                                            ");
                                            $stmt->execute([
                                                $messageDbId,
                                                $resultText,
                                                json_encode($parsed),
                                                $parsed['tool_call_id'] ?? null,
                                                isset($parsed['is_error']) && $parsed['is_error'] ? 1 : 0
                                            ]);

                                            // Update tool execution with result
                                            if (isset($parsed['tool_call_id'])) {
                                                $stmt = $this->db->prepare("
                                                    UPDATE ai_tool_executions
                                                    SET result = ?, is_error = ?
                                                    WHERE tool_use_id = ?
                                                ");
                                                $stmt->execute([
                                                    json_encode(['content' => $resultText, 'exit_code' => $parsed['exit_code'] ?? null]),
                                                    isset($parsed['is_error']) && $parsed['is_error'] ? 1 : 0,
                                                    $parsed['tool_call_id']
                                                ]);
                                            }
                                        } elseif ($parsed['type'] === 'text') {
                                            // Store regular text content
                                            $stmt = $this->db->prepare("
                                                INSERT INTO ai_message_content
                                                (message_id, content_type, content_text, content_data)
                                                VALUES (?, 'text', ?, ?)
                                            ");
                                            $stmt->execute([
                                                $messageDbId,
                                                $parsed['text'] ?? '',
                                                json_encode($parsed)
                                            ]);

                                            // Capture final response
                                            $finalResponse .= ($parsed['text'] ?? '') . "\n";
                                        }
                                    }
                                }
                            } else {
                                // Could not parse - store as plain text
                                $stmt = $this->db->prepare("
                                    INSERT INTO ai_message_content
                                    (message_id, content_type, content_text, content_data)
                                    VALUES (?, ?, ?, ?)
                                ");
                                $stmt->execute([
                                    $messageDbId,
                                    $contentType,
                                    $contentText,
                                    json_encode($content)
                                ]);
                            }
                        } else {
                            // Not embedded JSON - store normally
                            $rawText = $content['text'] ?? ($content['content'] ?? null);
                            $contentText = is_array($rawText) ? json_encode($rawText) : $rawText;
                            $toolUseId = $content['id'] ?? null;
                            $toolName = $content['name'] ?? null;
                            $isError = isset($content['is_error']) ? ($content['is_error'] ? 1 : 0) : 0;

                            $stmt = $this->db->prepare("
                                INSERT INTO ai_message_content
                                (message_id, content_type, content_text, content_data, tool_use_id, tool_name, is_error)
                                VALUES (?, ?, ?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $messageDbId,
                                $contentType,
                                $contentText,
                                json_encode($content),
                                $toolUseId,
                                $toolName,
                                $isError
                            ]);

                            // Track tool usage
                            if ($contentType === 'tool_use') {
                                $toolsUsedCount++;
                                $stmt = $this->db->prepare("
                                    INSERT INTO ai_tool_executions (session_id, message_id, tool_use_id, tool_name, parameters)
                                    VALUES (?, ?, ?, ?, ?)
                                ");
                                $stmt->execute([$sessionId, $messageDbId, $toolUseId, $toolName, json_encode($content['input'] ?? [])]);
                            }

                            // Capture final response text
                            if ($type === 'assistant' && $contentType === 'text') {
                                $finalResponse .= $contentText . "\n";
                            }
                        }
                    }
                }
                
                // Store token usage
                if (isset($msg['message']['usage'])) {
                    $usage = $msg['message']['usage'];
                    $stmt = $this->db->prepare("
                        INSERT INTO ai_token_usage 
                        (session_id, message_id, input_tokens, output_tokens, cache_creation_tokens, 
                         cache_read_tokens, total_tokens, cache_creation_5m_tokens, cache_creation_1h_tokens, 
                         service_tier)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $sessionId,
                        $messageDbId,
                        $usage['input_tokens'] ?? 0,
                        $usage['output_tokens'] ?? 0,
                        $usage['cache_creation_input_tokens'] ?? 0,
                        $usage['cache_read_input_tokens'] ?? 0,
                        ($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0),
                        $usage['cache_creation']['ephemeral_5m_input_tokens'] ?? 0,
                        $usage['cache_creation']['ephemeral_1h_input_tokens'] ?? 0,
                        $usage['service_tier'] ?? null
                    ]);
                    
                    $totalInputTokens += $usage['input_tokens'] ?? 0;
                    $totalOutputTokens += $usage['output_tokens'] ?? 0;
                }
                
                // Store aggregated token usage for result messages
                if ($type === 'result') {
                    // Check if result message has its own usage field
                    if (isset($msg['usage'])) {
                        $resultUsage = $msg['usage'];
                        $stmt = $this->db->prepare("
                            INSERT INTO ai_token_usage 
                            (session_id, message_id, input_tokens, output_tokens, cache_read_tokens, 
                             cache_creation_tokens, total_tokens, cost_usd, service_tier)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $sessionId,
                            $messageDbId,
                            $resultUsage['input_tokens'] ?? 0,
                            $resultUsage['output_tokens'] ?? 0,
                            $resultUsage['cache_read_input_tokens'] ?? 0,
                            $resultUsage['cache_creation_input_tokens'] ?? 0,
                            ($resultUsage['input_tokens'] ?? 0) + ($resultUsage['output_tokens'] ?? 0),
                            $msg['total_cost_usd'] ?? $totalCost,
                            $resultUsage['service_tier'] ?? null
                        ]);
                        
                        // Update totals with result message usage
                        $totalInputTokens = $resultUsage['input_tokens'] ?? $totalInputTokens;
                        $totalOutputTokens = $resultUsage['output_tokens'] ?? $totalOutputTokens;
                        
                        // Store result usage data for prompt update
                        $this->resultUsageData = [
                            'input_tokens' => $resultUsage['input_tokens'] ?? 0,
                            'output_tokens' => $resultUsage['output_tokens'] ?? 0,
                            'cache_read_tokens' => $resultUsage['cache_read_input_tokens'] ?? 0,
                            'cache_creation_tokens' => $resultUsage['cache_creation_input_tokens'] ?? 0,
                            'total_cost_usd' => $msg['total_cost_usd'] ?? 0
                        ];
                    } elseif ($totalInputTokens > 0 || $totalOutputTokens > 0) {
                        // Fallback: Store aggregated token usage if no usage field in result
                        $stmt = $this->db->prepare("
                            INSERT INTO ai_token_usage 
                            (session_id, message_id, input_tokens, output_tokens, total_tokens, cost_usd)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $sessionId,
                            $messageDbId,
                            $totalInputTokens,
                            $totalOutputTokens,
                            $totalInputTokens + $totalOutputTokens,
                            $msg['total_cost_usd'] ?? $totalCost
                        ]);
                    }
                    
                    // Track result message data
                    $apiDuration = $msg['duration_api_ms'] ?? 0;
                    $totalDuration = $msg['duration_ms'] ?? 0;
                    $numTurns = $msg['num_turns'] ?? 0;
                    $totalCost = $msg['total_cost_usd'] ?? 0;
                    $finalResponse = $msg['result'] ?? $finalResponse;
                }
            }
            
            // Store aggregated results
            $stmt = $this->db->prepare("
                INSERT INTO ai_conversation_results 
                (session_id, sprint_id, prompt_id, platform, final_response, api_duration_ms,
                 total_duration_ms, num_turns, total_cost_usd, total_input_tokens, total_output_tokens, 
                 tools_used_count)
                VALUES (?, ?, ?, 'claude', ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $sessionId, $sprintId, $promptId, trim($finalResponse), 
                $apiDuration, $totalDuration, $numTurns, $totalCost, $totalInputTokens, 
                $totalOutputTokens, $toolsUsedCount
            ]);
            
            // Update session status
            $stmt = $this->db->prepare("
                UPDATE ai_sessions 
                SET status = 'completed', completed_at = NOW(), conversation_length = ?
                WHERE id = ?
            ");
            $stmt->execute([$sequence, $sessionId]);
            
            $this->db->commit();
            return $sessionId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error storing Claude response: " . $e->getMessage());
            error_log("Error trace: " . $e->getTraceAsString());
            throw $e;
        }
    }
    
    /**
     * Store Skilliks response
     */
    public function storeSkiliksResponse($responseData, $sprintId = null, $promptId = null) {
        try {
            $this->db->beginTransaction();
            
            $sessionId = $responseData['sessionId'] ?? uniqid('skilliks_');
            $data = $responseData['data'] ?? $responseData;
            
            // Create session (matching Claude's structure)
            $stmt = $this->db->prepare("
                INSERT INTO ai_sessions 
                (id, platform, model, working_directory, sprint_id, prompt_id, created_at)
                VALUES (?, 'skilliks', ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $sessionId,
                $data['model'] ?? 'unknown',
                '/var/www', // Default working directory for Skilliks
                $sprintId,
                $promptId
            ]);
            
            // Store tools as session tools (like Claude does)
            if (isset($data['toolsExecuted']) && is_array($data['toolsExecuted'])) {
                $toolNames = array_unique(array_column($data['toolsExecuted'], 'tool'));
                foreach ($toolNames as $order => $toolName) {
                    $stmt = $this->db->prepare("
                        INSERT INTO ai_session_tools (session_id, tool_name, tool_order)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$sessionId, $toolName, $order]);
                }
            }
            
            // Initialize totals for aggregation
            $sequence = 0;
            $totalInputTokens = 0;
            $totalOutputTokens = 0;
            $toolsUsedCount = 0;
            $finalResponse = $data['response'] ?? '';
            $totalCost = 0;
            
            // Store user message (the prompt that was sent)
            $sequence++;
            $stmt = $this->db->prepare("
                INSERT INTO ai_messages 
                (message_id, session_id, prompt_id, type, subtype, role, sequence_number)
                VALUES (?, ?, ?, 'user', NULL, 'user', ?)
            ");
            $userMessageId = uniqid('msg_');
            $stmt->execute([$userMessageId, $sessionId, $promptId, $sequence]);
            $userMsgDbId = $this->db->lastInsertId();
            
            // Store user message content (we don't have the original prompt text here, so we'll mark it)
            $stmt = $this->db->prepare("
                INSERT INTO ai_message_content 
                (message_id, content_type, content_text, content_data)
                VALUES (?, 'text', ?, ?)
            ");
            $stmt->execute([
                $userMsgDbId,
                '[User prompt - see project_dev_prompts.prompt_text]',
                json_encode(['type' => 'text', 'text' => '[User prompt]'])
            ]);
            
            // Store tool executions as separate messages (like Claude does)
            if (isset($data['toolsExecuted']) && is_array($data['toolsExecuted'])) {
                foreach ($data['toolsExecuted'] as $toolIndex => $tool) {
                    $toolsUsedCount++;
                    
                    // Tool use message
                    $sequence++;
                    $toolUseId = uniqid('tool_');
                    $toolMsgId = uniqid('msg_');
                    $stmt = $this->db->prepare("
                        INSERT INTO ai_messages 
                        (message_id, session_id, prompt_id, type, subtype, role, sequence_number)
                        VALUES (?, ?, ?, 'assistant', NULL, 'assistant', ?)
                    ");
                    $stmt->execute([$toolMsgId, $sessionId, $promptId, $sequence]);
                    $toolMsgDbId = $this->db->lastInsertId();
                    
                    // Tool use content
                    $stmt = $this->db->prepare("
                        INSERT INTO ai_message_content 
                        (message_id, content_type, content_text, content_data, tool_use_id, tool_name)
                        VALUES (?, 'tool_use', ?, ?, ?, ?)
                    ");
                    $toolContent = [
                        'type' => 'tool_use',
                        'id' => $toolUseId,
                        'name' => $tool['tool'] ?? 'unknown',
                        'input' => $tool['parameters'] ?? []
                    ];
                    $stmt->execute([
                        $toolMsgDbId,
                        json_encode($tool['parameters'] ?? []),
                        json_encode($toolContent),
                        $toolUseId,
                        $tool['tool'] ?? 'unknown'
                    ]);
                    
                    // Tool execution record
                    $stmt = $this->db->prepare("
                        INSERT INTO ai_tool_executions 
                        (session_id, message_id, tool_use_id, tool_name, parameters)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $sessionId,
                        $toolMsgDbId,
                        $toolUseId,
                        $tool['tool'] ?? 'unknown',
                        json_encode($tool['parameters'] ?? [])
                    ]);
                    
                    // Tool result message
                    $sequence++;
                    $resultMsgId = uniqid('msg_');
                    $stmt = $this->db->prepare("
                        INSERT INTO ai_messages 
                        (message_id, session_id, prompt_id, type, subtype, role, sequence_number, parent_tool_use_id)
                        VALUES (?, ?, ?, 'result', NULL, NULL, ?, ?)
                    ");
                    $stmt->execute([$resultMsgId, $sessionId, $promptId, $sequence, $toolUseId]);
                    $resultMsgDbId = $this->db->lastInsertId();
                    
                    // Tool result content
                    $result = $tool['result'] ?? null;
                    $resultText = is_string($result) ? $result : json_encode($result);
                    $stmt = $this->db->prepare("
                        INSERT INTO ai_message_content 
                        (message_id, content_type, content_text, content_data, tool_use_id, is_error)
                        VALUES (?, 'result', ?, ?, ?, ?)
                    ");
                    $resultContent = [
                        'type' => 'result',
                        'tool_use_id' => $toolUseId,
                        'content' => $result
                    ];
                    $stmt->execute([
                        $resultMsgDbId,
                        $resultText,
                        json_encode($resultContent),
                        $toolUseId,
                        0 // Assuming no errors for now
                    ]);
                }
            }
            
            // Store the final assistant response
            $sequence++;
            $assistantMsgId = uniqid('msg_');
            $stmt = $this->db->prepare("
                INSERT INTO ai_messages 
                (message_id, session_id, prompt_id, type, subtype, role, sequence_number)
                VALUES (?, ?, ?, 'assistant', NULL, 'assistant', ?)
            ");
            $stmt->execute([$assistantMsgId, $sessionId, $promptId, $sequence]);
            $assistantMsgDbId = $this->db->lastInsertId();
            
            // Store assistant response content
            if (!empty($finalResponse)) {
                $stmt = $this->db->prepare("
                    INSERT INTO ai_message_content 
                    (message_id, content_type, content_text, content_data)
                    VALUES (?, 'text', ?, ?)
                ");
                $contentData = [
                    'type' => 'text',
                    'text' => $finalResponse
                ];
                $stmt->execute([
                    $assistantMsgDbId,
                    $finalResponse,
                    json_encode($contentData)
                ]);
            }
            
            // Store token usage (map Skilliks fields to Claude's structure)
            if (isset($data['tokenUsage'])) {
                $usage = $data['tokenUsage'];
                $totalInputTokens = $usage['promptTokens'] ?? 0;
                $totalOutputTokens = $usage['candidateTokens'] ?? 0;
                
                $stmt = $this->db->prepare("
                    INSERT INTO ai_token_usage 
                    (session_id, message_id, input_tokens, output_tokens, total_tokens, 
                     cache_creation_tokens, cache_read_tokens)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $sessionId,
                    $assistantMsgDbId,
                    $totalInputTokens,
                    $totalOutputTokens,
                    $totalInputTokens + $totalOutputTokens,
                    0, // No cache creation tokens for Skilliks
                    $usage['cachedContentTokens'] ?? 0
                ]);
                
                // Store usage data for prompt update
                $this->resultUsageData = [
                    'input_tokens' => $totalInputTokens,
                    'output_tokens' => $totalOutputTokens,
                    'cache_read_tokens' => $usage['cachedContentTokens'] ?? 0,
                    'cache_creation_tokens' => 0,
                    'total_cost_usd' => $totalCost
                ];
            }
            
            // Calculate duration
            $apiDuration = 0;
            $totalDuration = 0;
            if (isset($data['startTime']) && isset($data['completionTime'])) {
                $start = new DateTime($data['startTime']);
                $end = new DateTime($data['completionTime']);
                $totalDuration = ($end->getTimestamp() - $start->getTimestamp()) * 1000;
                $apiDuration = $totalDuration; // For Skilliks, API duration is the total duration
            }
            
            // Store aggregated results (matching Claude's structure)
            $stmt = $this->db->prepare("
                INSERT INTO ai_conversation_results 
                (session_id, sprint_id, prompt_id, platform, final_response, api_duration_ms,
                 total_duration_ms, num_turns, total_cost_usd, total_input_tokens, 
                 total_output_tokens, tools_used_count)
                VALUES (?, ?, ?, 'skilliks', ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $sessionId,
                $sprintId,
                $promptId,
                trim($finalResponse),
                $apiDuration,
                $totalDuration,
                $data['conversationLength'] ?? 1, // num_turns
                $totalCost,
                $totalInputTokens,
                $totalOutputTokens,
                $toolsUsedCount
            ]);
            
            // Update session status (matching Claude's approach)
            $stmt = $this->db->prepare("
                UPDATE ai_sessions 
                SET status = 'completed', completed_at = NOW(), conversation_length = ?
                WHERE id = ?
            ");
            $stmt->execute([$sequence, $sessionId]);
            
            // Store context stats (Skilliks specific)
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
            
            $this->db->commit();
            return $sessionId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error storing Skilliks response: " . $e->getMessage());
            error_log("Error trace: " . $e->getTraceAsString());
            throw $e;
        }
    }
    
    /**
     * Get conversation summary
     */
    public function getConversationSummary($sessionId) {
        $stmt = $this->db->prepare("
            SELECT 
                s.*,
                r.final_response,
                r.total_duration_ms,
                r.total_cost_usd,
                r.total_input_tokens,
                r.total_output_tokens,
                r.tools_used_count
            FROM ai_sessions s
            LEFT JOIN ai_conversation_results r ON s.id = r.session_id
            WHERE s.id = ?
        ");
        $stmt->execute([$sessionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get conversation messages
     */
    public function getConversationMessages($sessionId) {
        $stmt = $this->db->prepare("
            SELECT 
                m.*,
                GROUP_CONCAT(
                    CONCAT('{\"type\":\"', c.content_type, 
                           '\",\"text\":\"', REPLACE(c.content_text, '\"', '\\\"'), '\"}')
                    SEPARATOR ','
                ) as contents
            FROM ai_messages m
            LEFT JOIN ai_message_content c ON m.id = c.message_id
            WHERE m.session_id = ?
            GROUP BY m.id
            ORDER BY m.sequence_number
        ");
        $stmt->execute([$sessionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get tool executions for a session
     */
    public function getToolExecutions($sessionId) {
        $stmt = $this->db->prepare("
            SELECT * FROM ai_tool_executions
            WHERE session_id = ?
            ORDER BY executed_at
        ");
        $stmt->execute([$sessionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get result usage data from last stored response
     */
    public function getResultUsageData() {
        return $this->resultUsageData;
    }
}