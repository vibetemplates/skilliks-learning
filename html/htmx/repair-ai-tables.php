<?php
/**
 * Repair AI tables by reprocessing response arrays from project_dev_prompts
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../classes/AIResponseManager.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo '<div class="alert alert-danger">Unauthorized</div>';
    exit;
}

// Get prompt ID from request
$promptId = $_POST['prompt_id'] ?? null;

if (!$promptId) {
    http_response_code(400);
    echo '<div class="alert alert-danger">Missing prompt ID</div>';
    exit;
}

$db = getDB();

try {
    // Get prompt details and verify user has access
    $query = $db->prepare("
        SELECT
            pdp.*,
            s.project_id,
            p.created_by,
            pm.user_id as member_id
        FROM project_dev_prompts pdp
        JOIN sprints s ON pdp.sprint_id = s.id
        JOIN projects p ON s.project_id = p.id
        LEFT JOIN project_members pm ON p.id = pm.project_id AND pm.user_id = ?
        WHERE pdp.id = ?
    ");
    $query->execute([$_SESSION['user_id'], $promptId]);
    $prompt = $query->fetch(PDO::FETCH_ASSOC);

    if (!$prompt) {
        http_response_code(404);
        echo '<div class="alert alert-danger">Prompt not found</div>';
        exit;
    }

    // Check if user has permission (creator or member)
    if ($prompt['created_by'] != $_SESSION['user_id'] && !$prompt['member_id']) {
        http_response_code(403);
        echo '<div class="alert alert-danger">Access denied</div>';
        exit;
    }

    // Check if response_text contains a valid array
    $responseText = $prompt['response_text'];

    if (empty($responseText)) {
        echo '<div class="alert alert-warning">No response data to repair</div>';
        exit;
    }

    // Try to decode the response
    $responseData = json_decode($responseText, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo '<div class="alert alert-danger">Response data is not valid JSON: ' . json_last_error_msg() . '</div>';
        exit;
    }

    // Determine response type
    $responseType = null;
    $isClaudeFormat = false;
    $isSingleResult = false;

    // Check if it's a single result object with token data
    if (isset($responseData['type']) && $responseData['type'] === 'result' &&
        isset($responseData['usage']) && isset($responseData['total_cost_usd'])) {
        $isSingleResult = true;
        $isClaudeFormat = true;
        $responseType = 'single_result';
    }
    // Check if it's an array format
    elseif (is_array($responseData) && isset($responseData[0])) {
        // Check for the expected structure with system init, assistant message, and result
        $hasSystemInit = false;
        $hasAssistantMessage = false;

        foreach ($responseData as $item) {
            if (isset($item['type'])) {
                if ($item['type'] === 'system' && $item['subtype'] === 'init') {
                    $hasSystemInit = true;
                }
                if ($item['type'] === 'assistant' && isset($item['message'])) {
                    $hasAssistantMessage = true;
                }
            }
        }

        $isClaudeFormat = $hasSystemInit || $hasAssistantMessage;
        $responseType = 'array_messages';
    }

    if (!$isClaudeFormat) {
        echo '<div class="alert alert-warning">Response data does not appear to be in a supported format</div>';
        exit;
    }

    // Clear existing AI table entries for this prompt
    $db->beginTransaction();

    try {
        // Delete existing entries in reverse order of foreign key dependencies
        $tables = [
            'ai_token_usage',
            'ai_tool_executions',
            'ai_message_content',
            'ai_messages',
            'ai_context_stats',
            'ai_conversation_results',
            'ai_session_tools'
        ];

        foreach ($tables as $table) {
            if ($table === 'ai_session_tools' || $table === 'ai_context_stats' || $table === 'ai_conversation_results') {
                // These reference session_id
                $stmt = $db->prepare("
                    DELETE FROM $table
                    WHERE session_id IN (
                        SELECT id FROM ai_sessions WHERE prompt_id = ?
                    )
                ");
            } else {
                // These reference prompt_id directly or through ai_messages
                if ($table === 'ai_messages') {
                    $stmt = $db->prepare("DELETE FROM $table WHERE prompt_id = ?");
                } else {
                    $stmt = $db->prepare("
                        DELETE FROM $table
                        WHERE message_id IN (SELECT id FROM ai_messages WHERE prompt_id = ?)
                    ");
                }
            }
            $stmt->execute([$promptId]);
        }

        // Delete sessions
        $stmt = $db->prepare("DELETE FROM ai_sessions WHERE prompt_id = ?");
        $stmt->execute([$promptId]);

        // Now reprocess the response based on type
        $sessionId = null;
        $tokenDataUpdated = false;

        if ($isSingleResult) {
            // Handle single result object with token data
            // Extract token data directly
            $sessionId = $responseData['session_id'] ?? uniqid('claude_result_');

            // Update prompt with token data
            if (isset($responseData['usage']) && isset($responseData['total_cost_usd'])) {
                $updateStmt = $db->prepare("
                    UPDATE project_dev_prompts
                    SET input_tokens = ?,
                        output_tokens = ?,
                        cache_read_tokens = ?,
                        cache_creation_tokens = ?,
                        total_cost_usd = ?,
                        session_id = ?
                    WHERE id = ?
                ");
                $updateStmt->execute([
                    $responseData['usage']['input_tokens'] ?? null,
                    $responseData['usage']['output_tokens'] ?? null,
                    $responseData['usage']['cache_read_input_tokens'] ?? null,
                    $responseData['usage']['cache_creation_input_tokens'] ?? null,
                    $responseData['total_cost_usd'],
                    $sessionId,
                    $promptId
                ]);
                $tokenDataUpdated = true;
            }

            // Optionally convert to array format for AIResponseManager
            $responseData = [$responseData];
        }

        // Extract session info from first message (for array format)
        $model = null;
        $cwd = null;
        $tools = [];

        if (!$isSingleResult) {
            foreach ($responseData as $msg) {
                if ($msg['type'] === 'system' && $msg['subtype'] === 'init') {
                    $sessionId = $msg['session_id'] ?? uniqid('claude_');
                    $model = $msg['model'] ?? null;
                    $cwd = $msg['cwd'] ?? null;
                    $tools = $msg['tools'] ?? [];
                    break;
                } elseif (isset($msg['session_id'])) {
                    $sessionId = $msg['session_id'];
                }
            }
        }

        if (!$sessionId) {
            $sessionId = uniqid('claude_');
        }

        // Create session
        $stmt = $db->prepare("
            INSERT INTO ai_sessions (id, platform, model, working_directory, sprint_id, prompt_id, created_at)
            VALUES (?, 'claude', ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$sessionId, $model, $cwd, $prompt['sprint_id'], $promptId]);

        // Store tools if available
        foreach ($tools as $order => $toolName) {
            $stmt = $db->prepare("
                INSERT INTO ai_session_tools (session_id, tool_name, tool_order)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$sessionId, $toolName, $order]);
        }

        // Process messages
        $sequence = 0;
        foreach ($responseData as $msg) {
            $sequence++;

            // Store message
            $messageId = $msg['message']['id'] ?? ($msg['uuid'] ?? null);
            $type = $msg['type'];
            $subtype = $msg['subtype'] ?? null;
            $role = $msg['message']['role'] ?? null;

            $stmt = $db->prepare("
                INSERT INTO ai_messages (message_id, session_id, prompt_id, type, subtype, role, sequence_number)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$messageId, $sessionId, $promptId, $type, $subtype, $role, $sequence]);
            $messageDbId = $db->lastInsertId();

            // Store message content - for Claude Code format, content may be in a single text field
            if (isset($msg['message']['content'])) {
                foreach ($msg['message']['content'] as $content) {
                    $contentType = $content['type'] ?? 'text';
                    $contentText = $content['text'] ?? ($content['content'] ?? null);

                    // Check if this is a text field with embedded JSON tool calls/results
                    if ($contentType === 'text' && is_string($contentText)) {
                        // The content may have different formats depending on the AI system used
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

                        if (!empty($jsonObjects)) {
                            // Process each JSON object in the array
                            foreach ($jsonObjects as $parsed) {
                                if (empty($parsed)) continue;

                                // This is a valid JSON object - store it properly
                                if (isset($parsed['type'])) {
                                    if ($parsed['type'] === 'tool_call') {
                                        // For TodoWrite, use empty text. For other tools, include the input
                                        $inputText = '';
                                        if ($parsed['name'] !== 'TodoWrite' && isset($parsed['input'])) {
                                            // For non-TodoWrite tools, include input parameters as readable text
                                            if (is_array($parsed['input'])) {
                                                // Format the input in a readable way
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

                                        $stmt = $db->prepare("
                                            INSERT INTO ai_message_content
                                            (message_id, content_type, content_text, content_data, tool_use_id, tool_name)
                                            VALUES (?, 'tool_use', ?, ?, ?, ?)
                                        ");
                                        $stmt->execute([
                                            $messageDbId,
                                            $inputText,
                                            json_encode($parsed),  // Full JSON data stored in content_data
                                            $parsed['id'] ?? null,
                                            $parsed['name'] ?? null
                                        ]);

                                        // Store tool execution record
                                        if (isset($parsed['name'])) {
                                            $stmt = $db->prepare("
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
                                        // Store tool result
                                        // content_text should be the actual result text, not JSON
                                        $resultText = $parsed['content'] ?? '';

                                        $stmt = $db->prepare("
                                            INSERT INTO ai_message_content
                                            (message_id, content_type, content_text, content_data, tool_use_id, is_error)
                                            VALUES (?, 'tool_result', ?, ?, ?, ?)
                                        ");
                                        $stmt->execute([
                                            $messageDbId,
                                            $resultText,  // Actual result text
                                            json_encode($parsed),  // Full JSON data
                                            $parsed['tool_call_id'] ?? null,
                                            isset($parsed['is_error']) && $parsed['is_error'] ? 1 : 0
                                        ]);

                                        // Update tool execution with result
                                        if (isset($parsed['tool_call_id'])) {
                                            $stmt = $db->prepare("
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
                                        $stmt = $db->prepare("
                                            INSERT INTO ai_message_content
                                            (message_id, content_type, content_text, content_data)
                                            VALUES (?, 'text', ?, ?)
                                        ");
                                        $stmt->execute([
                                            $messageDbId,
                                            $parsed['text'] ?? '',
                                            json_encode($parsed)
                                        ]);
                                    }
                                }
                            }
                        }
                    } else {
                        // Not embedded JSON - store as is
                        $stmt = $db->prepare("
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
                }
            }

            // Store result information if present
            if ($msg['type'] === 'result') {
                $totalCost = $msg['total_cost_usd'] ?? null;
                $apiDuration = $msg['duration_api_ms'] ?? null;
                $totalDuration = $msg['duration_ms'] ?? null;
                $numTurns = $msg['num_turns'] ?? null;
                $resultText = $msg['result'] ?? null;

                if ($totalCost || $apiDuration || $totalDuration) {
                    $stmt = $db->prepare("
                        INSERT INTO ai_conversation_results
                        (session_id, sprint_id, prompt_id, platform, total_cost_usd, api_duration_ms, total_duration_ms, num_turns, final_response)
                        VALUES (?, ?, ?, 'claude', ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $sessionId,
                        $prompt['sprint_id'] ?? null,
                        $promptId,
                        $totalCost,
                        $apiDuration,
                        $totalDuration,
                        $numTurns,
                        $resultText
                    ]);
                }

                // Store usage data if present
                if (isset($msg['usage'])) {
                    $usage = $msg['usage'];
                    $stmt = $db->prepare("
                        INSERT INTO ai_token_usage
                        (message_id, input_tokens, output_tokens, total_tokens, cost_usd)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $totalTokens = ($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0);
                    $stmt->execute([
                        $messageDbId,
                        $usage['input_tokens'] ?? 0,
                        $usage['output_tokens'] ?? 0,
                        $totalTokens,
                        $totalCost
                    ]);
                }
            }
        }

        $db->commit();

        // Create appropriate success message
        if ($tokenDataUpdated) {
            echo '<div class="alert alert-success">
                <i class="bi bi-check-circle"></i> Token data extracted and saved successfully
                <button class="btn btn-sm btn-outline-success ms-2" onclick="location.reload()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>';
        } else {
            echo '<div class="alert alert-success">
                <i class="bi bi-check-circle"></i> AI tables repaired successfully
                <button class="btn btn-sm btn-outline-success ms-2" onclick="location.reload()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>';
        }

    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error repairing AI tables for prompt $promptId: " . $e->getMessage());
        echo '<div class="alert alert-danger">Failed to repair AI tables: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }

} catch (Exception $e) {
    error_log("Error in repair-ai-tables.php: " . $e->getMessage());
    echo '<div class="alert alert-danger">An error occurred: ' . htmlspecialchars($e->getMessage()) . '</div>';
}