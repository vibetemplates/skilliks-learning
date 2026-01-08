<?php
/**
 * Google Maps Configuration Example
 * 
 * Copy this file to maps.php and add your API key
 * 
 * To use Google Maps, you need to:
 * 1. Go to https://console.cloud.google.com/
 * 2. Create a new project or select an existing one
 * 3. Enable the Maps JavaScript API and Geocoding API
 * 4. Create credentials (API Key)
 * 5. Restrict the API key to your domain for security
 * 6. Add the API key below
 */

// Google Maps API Key
define('GOOGLE_MAPS_API_KEY', 'YOUR_GOOGLE_MAPS_API_KEY');

// You can get your API key from:
// https://console.cloud.google.com/google/maps-apis/credentials

// IMPORTANT: 
// - Keep your API key secure and never commit it to public repositories
// - Restrict your API key to specific domains in the Google Cloud Console
// - Enable only the APIs you need (Maps JavaScript API and Geocoding API)
// - Add config/maps.php to your .gitignore file