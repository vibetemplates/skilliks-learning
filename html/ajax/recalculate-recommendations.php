<?php
/**
 * AJAX endpoint to recalculate course recommendations based on current survey responses
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/Survey.php';
require_once '../classes/SurveyNarrative.php';
require_once '../classes/Course.php';
require_once '../classes/CourseRecommendation.php';

// Require login
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check for AJAX request
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'recalculate_recommendations') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$currentUserId = getCurrentUserId();
$currentCommunityId = getCurrentCommunityId();

try {
    // Initialize classes
    $survey = new Survey();
    $surveyNarrative = new SurveyNarrative();
    $db = getDB();
    $courseRecommendation = new CourseRecommendation($db);
    
    // Get the skills survey for current community
    $skillsSurvey = $survey->getSurveyByCommunity($currentCommunityId, 'skills');
    
    if (!$skillsSurvey) {
        throw new Exception('Survey not found');
    }
    
    $surveyId = $skillsSurvey['id'];
    
    // Save current form responses (partial or complete)
    $sections = $survey->getSurveySections($surveyId);
    $savedCount = 0;
    
    foreach ($sections as $section) {
        $questions = $survey->getSectionQuestions($section['id']);
        
        foreach ($questions as $question) {
            $questionId = $question['id'];
            $fieldName = 'question_' . $questionId;
            
            // Save response based on question type
            switch ($question['question_type']) {
                case 'text':
                case 'textarea':
                    if (isset($_POST[$fieldName]) && trim($_POST[$fieldName]) !== '') {
                        $answerText = trim($_POST[$fieldName]);
                        if ($survey->saveResponse($surveyId, $currentUserId, $questionId, $answerText, null)) {
                            $savedCount++;
                        }
                    }
                    break;
                    
                case 'radio':
                case 'dropdown':
                    if (isset($_POST[$fieldName]) && $_POST[$fieldName] > 0) {
                        $answerOptionId = intval($_POST[$fieldName]);
                        if ($survey->saveResponse($surveyId, $currentUserId, $questionId, null, $answerOptionId)) {
                            $savedCount++;
                        }
                    }
                    break;
                    
                case 'checkbox':
                    if (isset($_POST[$fieldName]) && !empty($_POST[$fieldName])) {
                        if ($survey->saveMultipleResponses($surveyId, $currentUserId, $questionId, $_POST[$fieldName])) {
                            $savedCount++;
                        }
                    }
                    break;
                    
                case 'ranking':
                    $rankingField = $fieldName . '_ranking';
                    if (isset($_POST[$rankingField]) && !empty($_POST[$rankingField])) {
                        $rankingData = json_decode($_POST[$rankingField], true);
                        if (!empty($rankingData)) {
                            if ($survey->saveRankingResponses($surveyId, $currentUserId, $questionId, $rankingData)) {
                                $savedCount++;
                            }
                        }
                    }
                    break;
            }
        }
    }
    
    // Update completion percentage
    $completionPercentage = $survey->updateCompletionPercentage($surveyId, $currentUserId);
    
    // Check if user has enough responses to generate meaningful recommendations
    $userResponseCount = $db->prepare("
        SELECT COUNT(DISTINCT question_id) as response_count 
        FROM survey_responses 
        WHERE survey_id = ? AND user_id = ?
    ");
    $userResponseCount->execute([$surveyId, $currentUserId]);
    $responseCount = $userResponseCount->fetch()['response_count'];
    
    // Generate recommendations even with partial data
    $result = $courseRecommendation->generateAndStoreRecommendations($currentUserId);
    
    $message = "Course recommendations have been updated based on your ";
    if ($completionPercentage < 30 || $responseCount < 5) {
        $message .= "limited survey responses. We've provided beginner-friendly courses to get you started. Complete more of the survey for more personalized recommendations.";
    } else if ($completionPercentage < 70) {
        $message .= "partial survey responses ({$completionPercentage}% complete). Complete more questions for better recommendations.";
    } else {
        $message .= "survey responses ({$completionPercentage}% complete).";
    }
    
    $message .= " Generated {$result['total_stored']} recommendations.";
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => $message,
        'redirect' => '/recommended-courses',
        'stats' => [
            'completion_percentage' => $completionPercentage,
            'response_count' => $responseCount,
            'recommendations_generated' => $result['total_generated'],
            'recommendations_stored' => $result['total_stored'],
            'beginner_courses' => $result['beginner_courses'],
            'interest_based' => $result['interest_based']
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Recalculate recommendations error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while generating recommendations. Please try again.'
    ]);
}