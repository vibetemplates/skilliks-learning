<?php
/**
 * DevSystemAPI Class
 * 
 * Handles API communication with development systems
 */

class DevSystemAPI {
    private $db;
    private $apiUrl;
    private $apiKey;
    private $devTool;
    private $timeout = 300; // 5 minutes default timeout
    
    public function __construct($apiUrl, $apiKey = null, $devTool = 'claude') {
        $this->db = getDB();
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->apiKey = $apiKey;
        $this->devTool = $devTool;
    }
    
    /**
     * Send a prompt to the development system
     */
    public function sendPrompt($promptId, $promptData) {
        try {
            // Update status to executing
            $this->updatePromptStatus($promptId, 'executing');
            
            // Prepare the request based on the development tool
            if ($this->devTool === 'skilliks') {
                $endpoint = $this->apiUrl . '/api/chat';
                $headers = [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ];
                // Skilliks doesn't use X-API-Key header
            } else {
                // Claude API
                $endpoint = $this->apiUrl . '/api/run-coder';
                $headers = [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ];
                
                if ($this->apiKey) {
                    $headers[] = 'X-API-Key: ' . $this->apiKey;
                }
            }
            
            // API expects simple format with just "prompt" field
            // Clean the prompt text - remove potential problematic characters
            $cleanPrompt = $promptData['prompt_text'];

            // Ensure proper handling of special characters
            $cleanPrompt = str_replace(["\r\n", "\r", "\n"], ' ', $cleanPrompt);
            $cleanPrompt = trim($cleanPrompt);
            
            // Check if we should use a test prompt (for debugging)
            if (isset($_POST['use_test_prompt']) && $_POST['use_test_prompt'] === '1') {
                $cleanPrompt = 'On templates.php please change the word Marketplace to Market in the hero section';
            } else {
                // Clean the prompt for the API
                // Remove markdown headers
                $cleanPrompt = preg_replace('/^#{1,6}\s+/m', '', $cleanPrompt);
                
                // Convert the prompt to a simple instruction
                // Extract just the implementation request if it exists
                if (preg_match('/Implementation Request[:\s]+(.+?)$/is', $cleanPrompt, $matches)) {
                    $cleanPrompt = trim($matches[1]);
                } else {
                    // Otherwise, just clean up the text
                    $cleanPrompt = strip_tags($cleanPrompt);
                    $cleanPrompt = preg_replace('/\s+/', ' ', $cleanPrompt);
                    $cleanPrompt = trim($cleanPrompt);
                }
                
                // Prepend with context about what file to work on if available
                if (!empty($promptData['work_item_title'])) {
                    $cleanPrompt = "Work on: " . $promptData['work_item_title'] . ". " . $cleanPrompt;
                }
            }
            
            // Prepend session_id to prompt if it exists
            if (!empty($promptData['session_id'])) {
                $cleanPrompt = $promptData['session_id'] . ' ' . $cleanPrompt;
            }
            
            // Prepare payload based on the development tool
            if ($this->devTool === 'skilliks') {
                $payload = [
                    'message' => $cleanPrompt,
                    'async' => true
                ];
            } else {
                // Claude API format
                $payload = [
                    'prompt' => $cleanPrompt
                ];
            }
            
            // Debug output
            error_log("DevSystemAPI Debug - Endpoint: " . $endpoint);
            error_log("DevSystemAPI Debug - Headers: " . print_r($headers, true));
            error_log("DevSystemAPI Debug - Payload: " . json_encode($payload));
            error_log("DevSystemAPI Debug - Payload (unescaped): " . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            
            // Prepare JSON data with proper escaping
            // Use standard JSON encoding to ensure proper escaping of quotes and special characters
            $jsonData = json_encode($payload);
            
            // Add Content-Length header
            $headers[] = 'Content-Length: ' . strlen($jsonData);
            
            // Send the request
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL verification for testing
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_VERBOSE, true); // Enable verbose output
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $curlInfo = curl_getinfo($ch);
            curl_close($ch);
            
            // Debug response
            error_log("DevSystemAPI Debug - HTTP Code: " . $httpCode);
            error_log("DevSystemAPI Debug - Response: " . $response);
            error_log("DevSystemAPI Debug - Curl Info: " . print_r($curlInfo, true));
            
            // Write response to debug directory
            $debugDir = '/var/www/html/uploads/debug';
            if (!is_dir($debugDir)) {
                mkdir($debugDir, 0777, true);
            }
            $debugFile = $debugDir . '/sprint_response_debug_' . date('Y-m-d_H-i-s') . '_prompt_' . $promptId . '.json';
            $debugData = [
                'timestamp' => date('Y-m-d H:i:s'),
                'prompt_id' => $promptId,
                'endpoint' => $endpoint,
                'http_code' => $httpCode,
                'curl_error' => $error,
                'raw_response' => $response,
                'response_length' => strlen($response),
                'curl_info' => $curlInfo,
                'headers_sent' => $headers,
                'payload_sent' => $payload,
                'json_decode_result' => json_decode($response, true),
                'json_last_error' => json_last_error(),
                'json_last_error_msg' => json_last_error_msg()
            ];
            $writeResult = file_put_contents($debugFile, json_encode($debugData, JSON_PRETTY_PRINT));
            if ($writeResult === false) {
                error_log("DevSystemAPI Debug - FAILED to write debug file: " . $debugFile);
                error_log("DevSystemAPI Debug - Error: " . error_get_last()['message']);
            } else {
                error_log("DevSystemAPI Debug - Response written to: " . $debugFile . " (bytes: " . $writeResult . ")");
            }
            
            if ($error) {
                throw new Exception("CURL Error: " . $error);
            }
            
            $responseData = json_decode($response, true);
            
            if ($httpCode >= 200 && $httpCode < 300 && $responseData !== null) {
                // Check for Skilliks response format
                if ($this->devTool === 'skilliks') {
                    error_log("DevSystemAPI sendPrompt - Skilliks response received");
                    error_log("DevSystemAPI sendPrompt - Response data: " . json_encode($responseData));
                    
                    // Skilliks response format
                    if (isset($responseData['status']) && $responseData['status'] === 'success') {
                        // Extract sessionId from various possible locations
                        $sessionId = $responseData['sessionId'] ?? 
                                    $responseData['data']['sessionId'] ?? 
                                    $responseData['data']['sessionId'] ?? null;
                        
                        error_log("DevSystemAPI sendPrompt - Extracted sessionId: " . $sessionId);
                        
                        if ($sessionId) {
                            // Success - update prompt with session info
                            error_log("DevSystemAPI sendPrompt - Calling updatePromptProcessInfo for prompt " . $promptId);
                            $this->updatePromptProcessInfo($promptId, $responseData);
                            return [
                                'success' => true,
                                'response' => $responseData,
                                'session_id' => $sessionId,
                                'async_processing' => $responseData['data']['asyncProcessing'] ?? false
                            ];
                        } else {
                            error_log("DevSystemAPI sendPrompt - No sessionId found in response!");
                            $errorMessage = "Skilliks response missing sessionId";
                            $this->updatePromptError($promptId, $errorMessage);
                            return [
                                'success' => false,
                                'error' => $errorMessage,
                                'http_code' => $httpCode,
                                'raw_response' => $response
                            ];
                        }
                    } else {
                        // Invalid Skilliks response
                        $errorMessage = "Invalid Skilliks response format - missing sessionId";
                        $this->updatePromptError($promptId, $errorMessage);
                        return [
                            'success' => false,
                            'error' => $errorMessage,
                            'http_code' => $httpCode,
                            'raw_response' => $response
                        ];
                    }
                } else {
                    // Claude response format
                    if (isset($responseData['pid']) || isset($responseData['tempFile'])) {
                        // Success - update prompt with process info but keep as executing
                        $this->updatePromptProcessInfo($promptId, $responseData);
                        return [
                            'success' => true,
                            'response' => $responseData,
                            'log_file' => $responseData['tempFile'] ?? null,
                            'process_id' => $responseData['pid'] ?? null
                        ];
                    } else {
                        // 200 response but no valid data - treat as error
                        $errorMessage = "Invalid response format - missing pid/tempFile";
                        $this->updatePromptError($promptId, $errorMessage);
                        return [
                            'success' => false,
                            'error' => $errorMessage,
                            'http_code' => $httpCode,
                            'raw_response' => $response
                        ];
                    }
                }
            } else {
                // Error response
                if ($responseData === null && $response) {
                    // If JSON decode failed, use raw response
                    $errorMessage = "HTTP {$httpCode}: {$response}";
                } else {
                    $errorMessage = $responseData['error'] ?? $responseData['message'] ?? "HTTP {$httpCode}: {$response}";
                }
                $this->updatePromptError($promptId, $errorMessage);
                return [
                    'success' => false,
                    'error' => $errorMessage,
                    'http_code' => $httpCode,
                    'raw_response' => $response
                ];
            }
            
        } catch (Exception $e) {
            // Make sure to update status to failed, not leave it as executing
            $this->updatePromptError($promptId, $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check the status of a prompt
     */
    public function checkPromptStatus($promptId, $externalId) {
        try {
            $endpoint = $this->apiUrl . "/api/prompts/{$externalId}/status";
            $headers = ['Accept: application/json'];
            
            if ($this->apiKey) {
                $headers[] = 'X-API-Key: ' . $this->apiKey;
            }
            
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $data = json_decode($response, true);
                
                // Update local status based on remote status
                if ($data['status'] === 'completed') {
                    // Don't automatically get result, let the caller decide
                    // $this->getPromptResult($promptId, $externalId);
                } elseif ($data['status'] === 'failed') {
                    $this->updatePromptError($promptId, $data['error'] ?? 'Unknown error');
                }
                
                return $data;
            }
            
            return null;
        } catch (Exception $e) {
            error_log("DevSystemAPI checkPromptStatus error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check the status of a running coder process
     */
    public function checkCoderStatus($pid, $tempFile) {
        try {
            // Handle Skilliks differently
            if ($this->devTool === 'skilliks') {
                // For Skilliks, $pid is actually the sessionId
                $endpoint = $this->apiUrl . '/api/status';

                // Skilliks expects POST with JSON body
                $payload = json_encode(['sessionId' => $pid]);

                error_log("DevSystemAPI checkCoderStatus Skilliks - POST: " . $endpoint);
                error_log("DevSystemAPI checkCoderStatus Skilliks - Payload: " . $payload);

                $ch = curl_init($endpoint);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                
                error_log("DevSystemAPI checkCoderStatus Skilliks - HTTP Code: " . $httpCode);
                error_log("DevSystemAPI checkCoderStatus Skilliks - Response: " . $response);
                
                if ($error) {
                    throw new Exception("CURL Error: " . $error);
                }
                
                $responseData = json_decode($response, true);
                
                if ($httpCode === 200 && $responseData !== null) {
                    // Map Skilliks response to expected format
                    $data = $responseData['data'] ?? [];
                    
                    // Determine status based on response structure
                    $status = 'running'; // default
                    $output = '';
                    $complete = false;
                    
                    // Check if we have 'status' field (processing/completed) or 'complete' field (finished)
                    error_log("DevSystemAPI checkCoderStatus - Skilliks data status: " . ($data['status'] ?? 'not set'));
                    error_log("DevSystemAPI checkCoderStatus - Skilliks data complete: " . ($data['complete'] ?? 'not set'));
                    
                    if (isset($data['status']) && $data['status'] === 'processing') {
                        $status = 'running';
                        $complete = false;
                    } elseif (isset($data['status']) && $data['status'] === 'completed') {
                        $status = 'done';  // Use 'done' to match check-prompt-status.php expectations
                        $complete = true;
                        $output = $data['response'] ?? '';
                    } elseif (isset($data['complete']) && $data['complete'] === true) {
                        $status = 'done';  // Use 'done' to match check-prompt-status.php expectations
                        $complete = true;
                        $output = $data['response'] ?? '';
                    }
                    
                    return [
                        'success' => true,
                        'data' => [
                            'status' => $status,
                            'response' => $output,  // check-prompt-status.php expects 'response' field
                            'response_so_far' => $status === 'running' ? '' : '',  // For partial responses
                            'output' => $output,  // Keep for compatibility
                            'complete' => $complete,
                            'sessionId' => $data['sessionId'] ?? $pid,
                            'active' => $data['active'] ?? false,
                            'model' => $data['model'] ?? null,
                            'conversationLength' => $data['conversationLength'] ?? 0,
                            'contextStats' => $data['contextStats'] ?? null,
                            'raw_response' => $responseData
                        ]
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => "HTTP {$httpCode}: {$response}",
                        'http_code' => $httpCode
                    ];
                }
            }
            
            // Original Claude logic
            $endpoint = $this->apiUrl . '/api/check-coder';
            $headers = [
                'Content-Type: application/json',
                'Accept: application/json'
            ];
            
            if ($this->apiKey) {
                $headers[] = 'X-API-Key: ' . $this->apiKey;
            }
            
            // Ensure pid is an integer if it's numeric
            $pidValue = is_numeric($pid) ? intval($pid) : $pid;
            
            $payload = [
                'pid' => $pidValue,
                'outfile' => $tempFile  // Changed from 'tempFile' to 'outfile'
            ];
            
            // Debug output
            error_log("DevSystemAPI checkCoderStatus - Endpoint: " . $endpoint);
            error_log("DevSystemAPI checkCoderStatus - Payload: " . json_encode($payload));
            error_log("DevSystemAPI checkCoderStatus - PID type: " . gettype($pidValue));
            
            $jsonData = json_encode($payload);
            $headers[] = 'Content-Length: ' . strlen($jsonData);
            
            // Try GET request with query parameters first
            $queryParams = http_build_query([
                'pid' => $pidValue,
                'outfile' => $tempFile  // Changed from 'tempFile' to 'outfile'
            ]);
            $getEndpoint = $endpoint . '?' . $queryParams;
            
            // Debug GET attempt
            error_log("DevSystemAPI checkCoderStatus - Trying GET: " . $getEndpoint);
            
            $ch = curl_init($getEndpoint);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'X-API-Key: ' . $this->apiKey]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            // Debug GET response
            error_log("DevSystemAPI checkCoderStatus GET - HTTP Code: " . $httpCode);
            error_log("DevSystemAPI checkCoderStatus GET - Response: " . $response);
            
            if ($error) {
                throw new Exception("CURL Error: " . $error);
            }
            
            // If GET failed with 404, try POST
            if ($httpCode === 404) {
                error_log("DevSystemAPI checkCoderStatus - GET failed with 404, trying POST");
                
                // Reset for POST request
                $ch = curl_init($endpoint);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                
                error_log("DevSystemAPI checkCoderStatus POST - HTTP Code: " . $httpCode);
                error_log("DevSystemAPI checkCoderStatus POST - Response: " . $response);
            }
            
            $responseData = json_decode($response, true);
            
            if ($httpCode >= 200 && $httpCode < 300 && $responseData !== null) {
                return [
                    'success' => true,
                    'data' => $responseData
                ];
            } else {
                $errorMessage = $responseData['error'] ?? $responseData['message'] ?? "HTTP {$httpCode}: {$response}";
                return [
                    'success' => false,
                    'error' => $errorMessage,
                    'http_code' => $httpCode,
                    'raw_response' => $response
                ];
            }
            
        } catch (Exception $e) {
            error_log("DevSystemAPI checkCoderStatus error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get the result of a completed prompt
     */
    public function getPromptResult($promptId, $externalId) {
        try {
            $endpoint = $this->apiUrl . "/api/prompts/{$externalId}/result";
            $headers = ['Accept: application/json'];
            
            if ($this->apiKey) {
                $headers[] = 'X-API-Key: ' . $this->apiKey;
            }
            
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $data = json_decode($response, true);
                $this->updatePromptResponse($promptId, $data);
                return $data;
            }
            
            return null;
        } catch (Exception $e) {
            error_log("DevSystemAPI getPromptResult error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get context from parent prompts
     */
    private function getPromptContext($promptData) {
        if (empty($promptData['parent_prompt_id'])) {
            return '';
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT response_text 
                FROM project_dev_prompts 
                WHERE id = ? AND status = 'completed'
            ");
            $stmt->execute([$promptData['parent_prompt_id']]);
            $parent = $stmt->fetch();
            
            return $parent ? $parent['response_text'] : '';
        } catch (PDOException $e) {
            return '';
        }
    }
    
    /**
     * Update prompt status
     */
    private function updatePromptStatus($promptId, $status) {
        try {
            $stmt = $this->db->prepare("
                UPDATE project_dev_prompts 
                SET status = ?, 
                    executed_at = CASE WHEN ? = 'executing' THEN NOW() ELSE executed_at END,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$status, $status, $promptId]);
        } catch (PDOException $e) {
            error_log("DevSystemAPI updatePromptStatus error: " . $e->getMessage());
        }
    }
    
    /**
     * Update prompt with successful response
     */
    private function updatePromptResponse($promptId, $responseData) {
        try {
            // Check if pid column exists
            $checkStmt = $this->db->prepare("SHOW COLUMNS FROM project_dev_prompts LIKE 'pid'");
            $checkStmt->execute();
            $pidColumnExists = $checkStmt->fetch() !== false;
            
            if ($pidColumnExists) {
                $stmt = $this->db->prepare("
                    UPDATE project_dev_prompts 
                    SET status = 'completed',
                        response_text = ?,
                        log_file_name = ?,
                        pid = ?,
                        completed_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                error_log("DevSystemAPI updatePromptResponse - PID to store: " . ($responseData['pid'] ?? 'null'));
                error_log("DevSystemAPI updatePromptResponse - PID type: " . gettype($responseData['pid'] ?? null));
                error_log("DevSystemAPI updatePromptResponse - PID length: " . strlen($responseData['pid'] ?? ''));
                $stmt->execute([
                    json_encode($responseData),
                    $responseData['tempFile'] ?? null,
                    $responseData['pid'] ?? null,
                    $promptId
                ]);
            } else {
                // Fallback for before migration is run
                $stmt = $this->db->prepare("
                    UPDATE project_dev_prompts 
                    SET status = 'completed',
                        response_text = ?,
                        log_file_name = ?,
                        completed_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    json_encode($responseData),
                    $responseData['tempFile'] ?? null,
                    $promptId
                ]);
            }
            
            // Store the AI response in the new tables
            $this->storeAIResponse($promptId, $responseData);
            
        } catch (PDOException $e) {
            error_log("DevSystemAPI updatePromptResponse error: " . $e->getMessage());
            error_log("DevSystemAPI updatePromptResponse SQL State: " . $e->getCode());
            error_log("DevSystemAPI updatePromptResponse SQL Error Code: " . $e->errorInfo[1]);
            error_log("DevSystemAPI updatePromptResponse - PID attempted: " . ($responseData['pid'] ?? 'null'));
            error_log("DevSystemAPI updatePromptResponse - PID type: " . gettype($responseData['pid'] ?? null));
            error_log("DevSystemAPI updatePromptResponse - PID length: " . strlen($responseData['pid'] ?? ''));
            error_log("DevSystemAPI updatePromptResponse - PID value: '" . ($responseData['pid'] ?? '') . "'");
            error_log("DevSystemAPI updatePromptResponse - Full responseData: " . json_encode($responseData));
            // If it's a truncation warning (1265), log more details
            if ($e->errorInfo[1] == 1265) {
                error_log("DevSystemAPI updatePromptResponse - Data truncation occurred!");
                error_log("DevSystemAPI updatePromptResponse - Checking field values:");
                error_log("  - response_text length: " . strlen(json_encode($responseData)));
                error_log("  - tempFile: " . ($responseData['tempFile'] ?? 'null'));
                error_log("  - tempFile length: " . strlen($responseData['tempFile'] ?? ''));
            }
        }
    }
    
    /**
     * Store AI response in the new AI response tables
     */
    private function storeAIResponse($promptId, $responseData) {
        try {
            // Debug: Log AI response storage attempt
            $debugDir = '/var/www/html/uploads/debug';
            if (!is_dir($debugDir)) {
                mkdir($debugDir, 0777, true);
            }
            $aiDebugFile = $debugDir . '/sprint_ai_response_debug_' . date('Y-m-d_H-i-s') . '_prompt_' . $promptId . '.json';
            $aiDebugData = [
                'timestamp' => date('Y-m-d H:i:s'),
                'prompt_id' => $promptId,
                'response_data' => $responseData,
                'response_data_type' => gettype($responseData),
                'is_array' => is_array($responseData)
            ];
            
            // Get sprint and project info
            $stmt = $this->db->prepare("
                SELECT pdp.sprint_id, s.project_id, p.dev_system_url
                FROM project_dev_prompts pdp
                LEFT JOIN sprints s ON pdp.sprint_id = s.id
                LEFT JOIN projects p ON s.project_id = p.id
                WHERE pdp.id = ?
            ");
            $stmt->execute([$promptId]);
            $promptInfo = $stmt->fetch();
            
            $aiDebugData['prompt_info'] = $promptInfo;
            
            if (!$promptInfo) {
                $aiDebugData['error'] = 'No prompt info found';
                $writeResult = file_put_contents($aiDebugFile, json_encode($aiDebugData, JSON_PRETTY_PRINT));
                if ($writeResult === false) {
                    error_log("DevSystemAPI storeAIResponse - FAILED to write debug file: " . $aiDebugFile);
                    error_log("DevSystemAPI storeAIResponse - Error: " . error_get_last()['message']);
                } else {
                    error_log("DevSystemAPI storeAIResponse - Debug data written to: " . $aiDebugFile . " (bytes: " . $writeResult . ")");
                }
                return;
            }
            
            // Include AIResponseManager if not already loaded
            if (!class_exists('AIResponseManager')) {
                require_once __DIR__ . '/AIResponseManager.php';
            }
            
            $aiManager = new AIResponseManager();
            
            // Determine platform based on API URL
            $platform = 'skilliks'; // Default
            if (strpos($promptInfo['dev_system_url'], 'claude') !== false || 
                strpos($promptInfo['dev_system_url'], 'anthropic') !== false) {
                $platform = 'claude';
            }
            
            $aiDebugData['platform'] = $platform;
            
            // Store based on platform
            if ($platform === 'claude' && is_array($responseData)) {
                $aiDebugData['storing_as'] = 'claude';
                $aiManager->storeClaudeResponse($responseData, $promptInfo['sprint_id'], $promptId);
            } elseif ($platform === 'skilliks') {
                $aiDebugData['storing_as'] = 'skilliks';
                $aiManager->storeSkiliksResponse($responseData, $promptInfo['sprint_id'], $promptId);
            }
            
            $writeResult = file_put_contents($aiDebugFile, json_encode($aiDebugData, JSON_PRETTY_PRINT));
            if ($writeResult === false) {
                error_log("DevSystemAPI storeAIResponse - FAILED to write debug file: " . $aiDebugFile);
                error_log("DevSystemAPI storeAIResponse - Error: " . error_get_last()['message']);
            } else {
                error_log("DevSystemAPI storeAIResponse - Debug data written to: " . $aiDebugFile . " (bytes: " . $writeResult . ")");
            }
            
        } catch (Exception $e) {
            error_log("DevSystemAPI storeAIResponse error: " . $e->getMessage());
            if (isset($aiDebugFile)) {
                $errorData = ['exception' => $e->getMessage()];
                file_put_contents($aiDebugFile, json_encode($errorData, JSON_PRETTY_PRINT), FILE_APPEND);
            }
        }
    }
    
    /**
     * Update prompt with error
     */
    private function updatePromptError($promptId, $error) {
        try {
            $stmt = $this->db->prepare("
                UPDATE project_dev_prompts 
                SET status = 'failed',
                    error_message = ?,
                    completed_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$error, $promptId]);
        } catch (PDOException $e) {
            error_log("DevSystemAPI updatePromptError error: " . $e->getMessage());
        }
    }
    
    /**
     * Update prompt with process info while keeping executing status
     */
    private function updatePromptProcessInfo($promptId, $responseData) {
        error_log("DevSystemAPI updatePromptProcessInfo - Called for prompt " . $promptId);
        error_log("DevSystemAPI updatePromptProcessInfo - DevTool: " . $this->devTool);
        error_log("DevSystemAPI updatePromptProcessInfo - ResponseData keys: " . implode(", ", array_keys($responseData)));
        
        try {
            // Debug: Write database update data to debug directory
            $debugDir = '/var/www/html/uploads/debug';
            if (!is_dir($debugDir)) {
                mkdir($debugDir, 0777, true);
            }
            $dbDebugFile = $debugDir . '/sprint_db_update_debug_' . date('Y-m-d_H-i-s') . '_prompt_' . $promptId . '.json';
            $dbDebugData = [
                'timestamp' => date('Y-m-d H:i:s'),
                'prompt_id' => $promptId,
                'dev_tool' => $this->devTool,
                'pid' => $responseData['pid'] ?? null,
                'tempFile' => $responseData['tempFile'] ?? null,
                'sessionId' => $responseData['sessionId'] ?? null,
                'response_data' => $responseData,
                'encoded_response' => json_encode($responseData),
                'json_last_error' => json_last_error(),
                'json_last_error_msg' => json_last_error_msg()
            ];
            $writeResult = file_put_contents($dbDebugFile, json_encode($dbDebugData, JSON_PRETTY_PRINT));
            if ($writeResult === false) {
                error_log("DevSystemAPI updatePromptProcessInfo - FAILED to write debug file: " . $dbDebugFile);
                error_log("DevSystemAPI updatePromptProcessInfo - Error: " . error_get_last()['message']);
            } else {
                error_log("DevSystemAPI updatePromptProcessInfo - Debug data written to: " . $dbDebugFile . " (bytes: " . $writeResult . ")");
            }
            
            if ($this->devTool === 'skilliks') {
                // For Skilliks, store sessionId in the session_id field
                // SessionId can be at top level or in data object
                $sessionId = $responseData['sessionId'] ?? $responseData['data']['sessionId'] ?? null;
                
                error_log("DevSystemAPI updatePromptProcessInfo Skilliks - SessionID to store: " . $sessionId);
                error_log("DevSystemAPI updatePromptProcessInfo Skilliks - Full response: " . json_encode($responseData));
                
                $stmt = $this->db->prepare("
                    UPDATE project_dev_prompts 
                    SET session_id = ?,
                        response_text = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $sessionId,
                    json_encode($responseData),
                    $promptId
                ]);
                
                error_log("DevSystemAPI updatePromptProcessInfo - SQL executed for Skilliks prompt " . $promptId);
                error_log("DevSystemAPI updatePromptProcessInfo - Rows affected: " . $stmt->rowCount());
            } else {
                // For Claude, store pid and tempFile
                $stmt = $this->db->prepare("
                    UPDATE project_dev_prompts 
                    SET pid = ?,
                        log_file_name = ?,
                        response_text = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                error_log("DevSystemAPI updatePromptProcessInfo Claude - PID to store: " . ($responseData['pid'] ?? 'null'));
                error_log("DevSystemAPI updatePromptProcessInfo Claude - PID length: " . strlen($responseData['pid'] ?? ''));
                $stmt->execute([
                    $responseData['pid'] ?? null,
                    $responseData['tempFile'] ?? null,
                    json_encode($responseData),
                    $promptId
                ]);
            }
        } catch (PDOException $e) {
            error_log("DevSystemAPI updatePromptProcessInfo error: " . $e->getMessage());
            error_log("DevSystemAPI updatePromptProcessInfo SQL State: " . $e->getCode());
            error_log("DevSystemAPI updatePromptProcessInfo SQL Error Code: " . $e->errorInfo[1]);
            if ($this->devTool === 'skilliks') {
                error_log("DevSystemAPI updatePromptProcessInfo - SessionID attempted: " . ($sessionId ?? 'null'));
                error_log("DevSystemAPI updatePromptProcessInfo - SessionID length: " . strlen($sessionId ?? ''));
            } else {
                error_log("DevSystemAPI updatePromptProcessInfo - PID attempted: " . ($responseData['pid'] ?? 'null'));
                error_log("DevSystemAPI updatePromptProcessInfo - PID type: " . gettype($responseData['pid'] ?? null));
                error_log("DevSystemAPI updatePromptProcessInfo - PID length: " . strlen($responseData['pid'] ?? ''));
                error_log("DevSystemAPI updatePromptProcessInfo - PID value: '" . ($responseData['pid'] ?? '') . "'");
            }
            // If it's just a truncation warning, we might want to continue
            if ($e->errorInfo[1] == 1265) {
                error_log("DevSystemAPI updatePromptProcessInfo - Truncation warning, continuing...");
                // Rethrow to let calling code handle it
                throw $e;
            }
        }
    }
    
    /**
     * Process all prompts for a sprint
     */
    public function processSprintPrompts($sprintId) {
        try {
            // Get all pending prompts for the sprint
            $stmt = $this->db->prepare("
                SELECT pdp.*, wi.type, wi.title, wi.priority
                FROM project_dev_prompts pdp
                LEFT JOIN work_items wi ON pdp.work_item_id = wi.id
                WHERE pdp.sprint_id = ? 
                AND pdp.status = 'pending'
                ORDER BY pdp.prompt_order ASC
            ");
            $stmt->execute([$sprintId]);
            $prompts = $stmt->fetchAll();
            
            $results = [];
            
            foreach ($prompts as $prompt) {
                // Check if parent prompt is completed (if any)
                if ($prompt['parent_prompt_id']) {
                    $parentStmt = $this->db->prepare("
                        SELECT status FROM project_dev_prompts WHERE id = ?
                    ");
                    $parentStmt->execute([$prompt['parent_prompt_id']]);
                    $parent = $parentStmt->fetch();
                    
                    if (!$parent || $parent['status'] !== 'completed') {
                        continue; // Skip until parent is done
                    }
                }
                
                // Send the prompt
                $result = $this->sendPrompt($prompt['id'], $prompt);
                $results[] = [
                    'prompt_id' => $prompt['id'],
                    'work_item_id' => $prompt['work_item_id'],
                    'title' => $prompt['title'],
                    'result' => $result
                ];
                
                // Add delay between prompts to avoid overwhelming the API
                sleep(2);
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log("DevSystemAPI processSprintPrompts error: " . $e->getMessage());
            return [];
        }
    }
}