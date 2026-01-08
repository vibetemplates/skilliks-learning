<?php

// MailerSend API credentials
$apiKey = 'mlsn.64202a1f1576f7d39bf3466910cbacec21841478ad2f5d015daba398a97b358e';
$templateId = 'z86org8yxr1lew13';

// Email configuration
$emailData = [
    'from' => [
        'email' => 'support@skilliks.ai',
        'name' => 'Skilliks Support'
    ],
    'to' => [
        [
            'email' => 'recipient@example.com',
            'name' => 'Test Recipient'
        ]
    ],
    'subject' => 'Test Email from Skilliks',
    'template_id' => $templateId,
    'personalization' => [
        [
            'email' => 'recipient@example.com',
            'data' => [
                'name' => 'Test User',
                'company' => 'Test Company',
                'message' => 'This is a test message'
            ]
        ]
    ]
];

// Send email using file_get_contents
$options = [
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        'content' => json_encode($emailData),
        'ignore_errors' => true
    ]
];

$context = stream_context_create($options);
$response = @file_get_contents('https://api.mailersend.com/v1/email', false, $context);

// Get HTTP response code
$httpCode = 0;
$error = null;
if ($response === false) {
    $error = 'Failed to connect to MailerSend API';
} else {
    // Extract status code from response headers
    if (isset($http_response_header)) {
        foreach ($http_response_header as $header) {
            if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
                $httpCode = (int)$matches[1];
                break;
            }
        }
    }
}

// Display results
echo "=== MailerSend Email Test ===\n\n";

if ($error) {
    echo "cURL Error: " . $error . "\n";
} else {
    echo "HTTP Status Code: " . $httpCode . "\n";
    echo "Response: " . $response . "\n\n";
    
    if ($httpCode == 202) {
        echo "✓ Email sent successfully!\n";
    } else {
        echo "✗ Failed to send email.\n";
        $responseData = json_decode($response, true);
        if (isset($responseData['message'])) {
            echo "Error: " . $responseData['message'] . "\n";
        }
    }
}

echo "\nConfiguration used:\n";
echo "- Template ID: " . $templateId . "\n";
echo "- From: " . $emailData['from']['email'] . "\n";
echo "- To: " . $emailData['to'][0]['email'] . "\n";