<?php
/**
 * Community Map Page
 * 
 * Displays community members on a Google Maps interface
 */

$page_title = 'Community Map';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'config/maps.php';

// Require login
requireLogin();

$communityId = getCurrentCommunityId();
$currentUserId = getCurrentUserId();
$db = getDB();

// Get members with location data
$stmt = $db->prepare("
    SELECT 
        u.id,
        u.first_name,
        u.last_name,
        CONCAT(u.first_name, ' ', u.last_name) as name,
        u.email,
        u.github_username,
        u.profile_photo,
        u.location_address,
        u.location_city,
        u.location_state,
        u.location_country,
        u.location_latitude,
        u.location_longitude,
        u.location_privacy,
        cm.role as community_role,
        cm.joined_at
    FROM community_members cm
    JOIN users u ON cm.user_id = u.id
    WHERE cm.community_id = :community_id
    AND cm.is_active = 1
    AND u.location_privacy IN ('public', 'community')
    AND (u.location_latitude IS NOT NULL OR u.location_address IS NOT NULL)
    ORDER BY u.first_name ASC, u.last_name ASC
");
$stmt->execute([':community_id' => $communityId]);
$members = $stmt->fetchAll();

// Prepare member data for JavaScript
$memberData = [];
foreach ($members as $member) {
    // Only include members with coordinates or addresses
    if ($member['location_latitude'] && $member['location_longitude']) {
        $memberData[] = [
            'id' => $member['id'],
            'name' => htmlspecialchars($member['name']),
            'role' => $member['community_role'],
            'city' => htmlspecialchars($member['location_city'] ?? ''),
            'state' => htmlspecialchars($member['location_state'] ?? ''),
            'country' => htmlspecialchars($member['location_country'] ?? ''),
            'lat' => floatval($member['location_latitude']),
            'lng' => floatval($member['location_longitude']),
            'photo' => $member['profile_photo'] ? true : false
        ];
    } elseif ($member['location_address'] || $member['location_city']) {
        // Members with addresses but no coordinates (will need geocoding)
        $address = trim(implode(', ', array_filter([
            $member['location_address'],
            $member['location_city'],
            $member['location_state'],
            $member['location_country']
        ])));
        
        if ($address) {
            $memberData[] = [
                'id' => $member['id'],
                'name' => htmlspecialchars($member['name']),
                'role' => $member['community_role'],
                'address' => htmlspecialchars($address),
                'city' => htmlspecialchars($member['location_city'] ?? ''),
                'state' => htmlspecialchars($member['location_state'] ?? ''),
                'country' => htmlspecialchars($member['location_country'] ?? ''),
                'photo' => $member['profile_photo'] ? true : false
            ];
        }
    }
}

require_once 'includes/header.php';
?>

<!-- Main content -->
<main class="container-fluid px-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Community Map</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetMap()">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset View
                </button>
            </div>
            <span class="badge bg-secondary"><?php echo count($memberData); ?> Members</span>
        </div>
    </div>

    <?php if (empty($memberData)): ?>
        <div class="alert alert-info">
            <h4 class="alert-heading">No Members on Map</h4>
            <p>No community members have added their location yet.</p>
            <hr>
            <p class="mb-0">
                <a href="profile.php" class="btn btn-primary btn-sm">Add Your Location</a>
            </p>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-lg-9">
                <div class="card">
                    <div class="card-body p-0">
                        <div id="map" style="height: 600px; width: 100%;"></div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Map Legend</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6>Member Roles</h6>
                            <div class="d-flex align-items-center mb-2">
                                <img src="https://maps.google.com/mapfiles/ms/icons/red-dot.png" alt="Admin" class="me-2">
                                <span>Admin</span>
                            </div>
                            <div class="d-flex align-items-center mb-2">
                                <img src="https://maps.google.com/mapfiles/ms/icons/blue-dot.png" alt="Member" class="me-2">
                                <span>Member</span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <h6>Statistics</h6>
                            <ul class="list-unstyled mb-0">
                                <li><strong>Total Members:</strong> <?php echo count($memberData); ?></li>
                                <?php 
                                $countries = array_unique(array_column($memberData, 'country'));
                                $countries = array_filter($countries);
                                ?>
                                <li><strong>Countries:</strong> <?php echo count($countries); ?></li>
                            </ul>
                        </div>
                        
                        <div>
                            <h6>Privacy Note</h6>
                            <p class="text-muted small mb-0">
                                Only members who have chosen to share their location with the community are shown on this map.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0">Your Location</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Check if current user has location
                        $userHasLocation = false;
                        foreach ($members as $member) {
                            if ($member['id'] == $currentUserId && 
                                ($member['location_latitude'] || $member['location_address'])) {
                                $userHasLocation = true;
                                break;
                            }
                        }
                        ?>
                        
                        <?php if ($userHasLocation): ?>
                            <p class="text-success mb-2">
                                <i class="bi bi-check-circle"></i> Your location is on the map
                            </p>
                            <a href="profile.php" class="btn btn-sm btn-outline-primary">
                                Update Location
                            </a>
                        <?php else: ?>
                            <p class="text-muted mb-2">
                                You haven't added your location yet.
                            </p>
                            <a href="profile.php" class="btn btn-sm btn-primary">
                                Add Your Location
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php if (!empty($memberData)): ?>
<script>
// Member data for the map
const members = <?php echo json_encode($memberData); ?>;
let map;
let markers = [];
let infoWindow;
let bounds;

function initMap() {
    // Create map centered on a default location
    map = new google.maps.Map(document.getElementById('map'), {
        zoom: 2,
        center: { lat: 20, lng: 0 },
        mapTypeId: 'roadmap',
        styles: [
            {
                featureType: 'water',
                elementType: 'geometry',
                stylers: [{ color: '#e9e9e9' }, { lightness: 17 }]
            }
        ]
    });
    
    infoWindow = new google.maps.InfoWindow();
    bounds = new google.maps.LatLngBounds();
    
    // Create markers for members with coordinates
    members.forEach(member => {
        if (member.lat && member.lng) {
            createMarker(member);
        } else if (member.address) {
            // Geocode addresses that don't have coordinates
            geocodeAddress(member);
        }
    });
    
    // Fit map to show all markers
    if (markers.length > 0) {
        setTimeout(() => {
            map.fitBounds(bounds);
            // Don't zoom in too much for single markers
            if (markers.length === 1) {
                map.setZoom(10);
            }
        }, 500);
    }
}

function createMarker(member) {
    const position = { lat: member.lat, lng: member.lng };
    
    // Choose marker color based on role
    const markerIcon = member.role === 'admin' 
        ? 'https://maps.google.com/mapfiles/ms/icons/red-dot.png'
        : 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png';
    
    const marker = new google.maps.Marker({
        position: position,
        map: map,
        title: member.name,
        icon: markerIcon,
        animation: google.maps.Animation.DROP
    });
    
    // Create info window content
    const location = [member.city, member.state, member.country]
        .filter(Boolean)
        .join(', ');
    
    const contentString = `
        <div style="min-width: 200px;">
            <h6 class="mb-2">${member.name}</h6>
            ${location ? `<p class="mb-1 text-muted">${location}</p>` : ''}
            <p class="mb-0">
                <span class="badge bg-${member.role === 'admin' ? 'danger' : 'primary'}">${member.role}</span>
            </p>
            ${member.photo ? `<a href="team-member?id=${member.id}" class="btn btn-sm btn-link p-0 mt-2">View Profile</a>` : ''}
        </div>
    `;
    
    marker.addListener('click', () => {
        infoWindow.setContent(contentString);
        infoWindow.open(map, marker);
    });
    
    markers.push(marker);
    bounds.extend(position);
}

function geocodeAddress(member) {
    const geocoder = new google.maps.Geocoder();
    
    geocoder.geocode({ address: member.address }, (results, status) => {
        if (status === 'OK' && results[0]) {
            member.lat = results[0].geometry.location.lat();
            member.lng = results[0].geometry.location.lng();
            createMarker(member);
            
            // Update database with coordinates via AJAX
            updateMemberCoordinates(member.id, member.lat, member.lng);
        } else {
            console.error('Geocode failed for ' + member.name + ': ' + status);
        }
    });
}

function updateMemberCoordinates(userId, lat, lng) {
    // Send coordinates to server to cache them
    fetch('api/update-location-coordinates.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            user_id: userId,
            latitude: lat,
            longitude: lng
        })
    });
}

function resetMap() {
    if (markers.length > 0) {
        map.fitBounds(bounds);
        if (markers.length === 1) {
            map.setZoom(10);
        }
    }
}

// Initialize map when page loads
window.onload = function() {
    // Load Google Maps script dynamically
    const script = document.createElement('script');
    script.src = 'https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_MAPS_API_KEY; ?>&callback=initMap&libraries=places';
    script.async = true;
    script.defer = true;
    script.onerror = function() {
        document.getElementById('map').innerHTML = `
            <div style="padding: 40px; text-align: center;">
                <h3>Unable to Load Google Maps</h3>
                <p>Please check the following:</p>
                <ul style="text-align: left; display: inline-block;">
                    <li>Google Maps API key is configured correctly</li>
                    <li>Billing is enabled in Google Cloud Console</li>
                    <li>Maps JavaScript API is enabled</li>
                    <li>API key restrictions allow this domain</li>
                </ul>
                <p><a href="/test-maps-api.php" target="_blank">Run Diagnostics</a></p>
            </div>
        `;
    };
    document.head.appendChild(script);
};
</script>
<?php endif; ?>

<style>
/* Map styles */
#map {
    border-radius: 0.375rem;
}

.gm-style-iw-d {
    overflow: hidden !important;
}
</style>

<?php require_once 'includes/footer.php'; ?>