<?php
/**
 * Google API Configuration
 * 
 * This file contains the configuration for Google API services.
 * Since this is a private git server and the code will be moved to another machine,
 * the API key and OAuth credentials are stored directly in this file.
 */

return [
    'api_key' => '',
    'oauth_client_id' => '',
    'oauth_client_secret' => '',
    
    // Additional Google API configuration can be added here
    'application_name' => 'Project Tracker',
    'scopes' => [
        // Add required scopes as needed
        // Example: Google\Service\Drive::DRIVE_READONLY
    ]
];
