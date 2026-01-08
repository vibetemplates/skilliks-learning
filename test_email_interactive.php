#!/usr/bin/env php
<?php

// MailerSend API credentials
$apiKey = 'mlsn.64202a1f1576f7d39bf3466910cbacec21841478ad2f5d015daba398a97b358e';
$templateId = 'z86org8yxr1lew13';

// Parse command line arguments
$options = getopt("", ["to:", "from:", "name:", "subject:", "help"]);

if (isset($options['help']) || empty($options)) {
    echo "MailerSend Email Test Script\n";
    echo "============================\n\n";
    echo "Usage: php test_email_interactive.php --to=email@example.com [options]\n\n";
    echo "Options:\n";
    echo "  --to=EMAIL        Recipient email address (required)\n";
    echo "  --from=EMAIL      Sender email address (default: info@yourdomain.com)\n";
    echo "  --name=NAME       Recipient name (default: Test Recipient)\n";
    echo "  --subject=TEXT    Email subject (uses template default if not specified)\n";
    echo "  --help            Show this help message\n\n";
    echo "Example:\n";
    echo "  php test_email_interactive.php --to=user@example.com --name=\"John Doe\"\n\n";
    exit(0);
}

if (!isset($options['to'])) {
    echo "Error: Recipient email (--to) is required.\n";
    echo "Use --help for usage information.\n";
    exit(1);
}

// Set defaults
$toEmail = $options['to'];
$fromEmail = $options['from'] ?? 'support@skilliks.ai';
$recipientName = $options['name'] ?? 'Test Recipient';

// Build email data
$emailData = [
    'from' => [
        'email' => $fromEmail,
        'name' => 'Skilliks'
    ],
    'to' => [
        [
            'email' => $toEmail,
            'name' => $recipientName
        ]
    ],
    'subject' => $options['subject'] ?? 'Test Email from Skilliks',
    'template_id' => $templateId,
    'personalization' => [
        [
            'email' => $toEmail,
            'data' => [
                'name' => $recipientName,
                'company' => 'Test Company',
                'message' => 'This is a test email sent at ' . date('Y-m-d H:i:s'),
                'year' => date('Y')
            ]
        ]
    ]
];

echo "Sending email...\n";
echo "From: $fromEmail\n";
echo "To: $toEmail ($recipientName)\n";
echo "Template: $templateId\n\n";

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
if ($error) {
    echo "❌ cURL Error: " . $error . "\n";
    exit(1);
} else {
    if ($httpCode == 202) {
        echo "✅ Email sent successfully!\n";
        $responseData = json_decode($response, true);
        if (isset($responseData['message_id'])) {
            echo "Message ID: " . $responseData['message_id'] . "\n";
        }
    } else {
        echo "❌ Failed to send email.\n";
        echo "HTTP Status Code: $httpCode\n";
        $responseData = json_decode($response, true);
        if (isset($responseData['message'])) {
            echo "Error: " . $responseData['message'] . "\n";
        }
        if (isset($responseData['errors'])) {
            echo "Errors:\n";
            foreach ($responseData['errors'] as $field => $errors) {
                foreach ($errors as $error) {
                    echo "  - $field: $error\n";
                }
            }
        }
        echo "\nFull response: " . $response . "\n";
        exit(1);
    }
}