<?php
/**
 * OpenAI API Configuration
 * 
 * This file contains the configuration for OpenAI API services.
 * Since this is a private git server and the code will be moved to another machine,
 * the API key is stored directly in this file.
 */

return [
    'api_key' => '',
    // Model configuration
    'model' => 'gpt-4o-mini', // Using GPT-4o mini for cost efficiency
    'max_tokens' => 2000,
    'temperature' => 0.7,
    
    // Quiz generation settings
    'quiz_settings' => [
        'questions_per_lesson' => 15,
        'choices_per_question' => 4,
        'system_prompt' => 'You are an expert educator creating multiple choice quiz questions. Create clear, unambiguous questions that test understanding of the material. Each question should have exactly one correct answer.'
    ]
];
