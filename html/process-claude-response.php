<?php
/**
 * Process Claude Response from JSON file
 * This script demonstrates how to properly parse claude0.json format
 */

require_once 'config/database.php';
require_once 'classes/AIResponseManager.php';

// Function to process Claude JSON response file
function processClaudeJsonFile($filePath, $sprintId = null, $promptId = null) {
    // Read the JSON file
    $jsonContent = file_get_contents($filePath);
    if (!$jsonContent) {
        throw new Exception("Could not read file: $filePath");
    }
    
    // Parse the main JSON
    $mainData = json_decode($jsonContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON in file: " . json_last_error_msg());
    }
    
    // Check if response field exists and is a string
    if (!isset($mainData['response'])) {
        throw new Exception("No response field found in JSON");
    }
    
    // Parse the response string as JSON
    $responseData = json_decode($mainData['response'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON in response field: " . json_last_error_msg());
    }
    
    // Now we have the array of messages that AIResponseManager expects
    $aiManager = new AIResponseManager();
    $sessionId = $aiManager->storeClaudeResponse($responseData, $sprintId, $promptId);
    
    return [
        'success' => true,
        'session_id' => $sessionId,
        'message_count' => count($responseData),
        'status' => $mainData['status'] ?? 'unknown'
    ];
}

// Example usage
if (php_sapi_name() === 'cli' || isset($_GET['test'])) {
    try {
        // Process the claude0.json file
        $result = processClaudeJsonFile('/var/www/claude0.json');
        
        echo "Successfully processed Claude response:\n";
        echo "Session ID: " . $result['session_id'] . "\n";
        echo "Messages processed: " . $result['message_count'] . "\n";
        echo "Status: " . $result['status'] . "\n";
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

// Function to integrate with sprint-edit.php or other pages
function handleClaudeResponse($responseJson, $sprintId = null, $promptId = null) {
    try {
        // If the response is a string, parse it
        if (is_string($responseJson)) {
            $mainData = json_decode($responseJson, true);
        } else {
            $mainData = $responseJson;
        }
        
        // Extract and parse the response field
        if (isset($mainData['response']) && is_string($mainData['response'])) {
            $responseData = json_decode($mainData['response'], true);
        } else {
            // Assume it's already the array format
            $responseData = $mainData;
        }
        
        // Store in database
        $aiManager = new AIResponseManager();
        $sessionId = $aiManager->storeClaudeResponse($responseData, $sprintId, $promptId);
        
        // Extract useful information for display
        $result = [
            'success' => true,
            'session_id' => $sessionId,
            'final_response' => '',
            'tools_used' => [],
            'total_cost' => 0
        ];
        
        // Extract information from the response
        foreach ($responseData as $msg) {
            if ($msg['type'] === 'result') {
                $result['final_response'] = $msg['result'] ?? '';
                $result['total_cost'] = $msg['total_cost_usd'] ?? 0;
            }
            
            if ($msg['type'] === 'assistant' && isset($msg['message']['content'])) {
                foreach ($msg['message']['content'] as $content) {
                    if ($content['type'] === 'tool_use') {
                        $result['tools_used'][] = $content['name'];
                    }
                }
            }
        }
        
        return $result;
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}