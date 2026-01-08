<?php
/**
 * Test Google Maps API Configuration
 */

require_once 'config/maps.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Google Maps API Test</title>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .status { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success { background-color: #d4edda; color: #155724; }
        .error { background-color: #f8d7da; color: #721c24; }
        .info { background-color: #d1ecf1; color: #0c5460; }
        code { background-color: #f8f9fa; padding: 2px 5px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>Google Maps API Configuration Test</h1>
    
    <div class="status info">
        <strong>API Key Status:</strong> 
        <?php if (defined('GOOGLE_MAPS_API_KEY') && GOOGLE_MAPS_API_KEY !== 'YOUR_GOOGLE_MAPS_API_KEY'): ?>
            <span style="color: green;">✓ API Key is configured</span>
        <?php else: ?>
            <span style="color: red;">✗ API Key is not configured properly</span>
        <?php endif; ?>
    </div>
    
    <div class="status info">
        <strong>API Key (masked):</strong> 
        <code><?php 
            if (defined('GOOGLE_MAPS_API_KEY')) {
                $key = GOOGLE_MAPS_API_KEY;
                echo substr($key, 0, 10) . '...' . substr($key, -4);
            } else {
                echo 'Not defined';
            }
        ?></code>
    </div>
    
    <h2>Common Issues and Solutions:</h2>
    
    <ol>
        <li>
            <strong>API Key Not Activated:</strong>
            <ul>
                <li>Go to <a href="https://console.cloud.google.com/apis/library" target="_blank">Google Cloud Console - APIs</a></li>
                <li>Make sure these APIs are enabled:
                    <ul>
                        <li>Maps JavaScript API</li>
                        <li>Geocoding API</li>
                    </ul>
                </li>
            </ul>
        </li>
        
        <li>
            <strong>Billing Not Enabled:</strong>
            <ul>
                <li>Google Maps requires a billing account (but includes $200/month free credit)</li>
                <li>Go to <a href="https://console.cloud.google.com/billing" target="_blank">Billing Settings</a></li>
                <li>Add a billing account to your project</li>
            </ul>
        </li>
        
        <li>
            <strong>API Key Restrictions:</strong>
            <ul>
                <li>Go to <a href="https://console.cloud.google.com/apis/credentials" target="_blank">API Credentials</a></li>
                <li>Click on your API key</li>
                <li>Under "Application restrictions", select "HTTP referrers"</li>
                <li>Add your domain: <code><?php echo $_SERVER['HTTP_HOST']; ?>/*</code></li>
                <li>For local development, also add: <code>localhost/*</code></li>
            </ul>
        </li>
    </ol>
    
    <h2>Test Simple Map:</h2>
    <div id="map" style="height: 400px; width: 100%; border: 1px solid #ccc;"></div>
    
    <script>
    function initMap() {
        console.log('InitMap called');
        try {
            const map = new google.maps.Map(document.getElementById('map'), {
                center: { lat: 37.7749, lng: -122.4194 },
                zoom: 10
            });
            document.getElementById('map-status').innerHTML = '<span style="color: green;">✓ Map loaded successfully!</span>';
        } catch (error) {
            console.error('Map error:', error);
            document.getElementById('map-status').innerHTML = '<span style="color: red;">✗ Error loading map: ' + error.message + '</span>';
        }
    }
    
    // Check if Google Maps is loaded
    window.addEventListener('load', function() {
        setTimeout(function() {
            if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
                document.getElementById('map-status').innerHTML = '<span style="color: red;">✗ Google Maps JavaScript API failed to load</span>';
            }
        }, 3000);
    });
    </script>
    
    <div class="status info" id="map-status">
        <strong>Map Status:</strong> Loading...
    </div>
    
    <h2>Console Output:</h2>
    <p>Open your browser's Developer Console (F12) to see any error messages from Google Maps.</p>
    
    <script async defer 
        src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_MAPS_API_KEY; ?>&callback=initMap">
    </script>
</body>
</html>