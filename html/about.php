<?php
/**
 * About Page
 * 
 * Information about the community
 */

$page_title = 'About';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Community.php';

// Require login
requireLogin();

$communityId = getCurrentCommunityId();
$community = new Community();
$communityData = $community->getById($communityId);

require_once 'includes/header.php';
?>

<!-- Main content -->
<main class="container-fluid px-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">About <?php echo htmlspecialchars($communityData['name']); ?></h1>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Community Description</h5>
                    <p class="card-text">
                        <?php echo nl2br(htmlspecialchars($communityData['description'] ?? 'No description available.')); ?>
                    </p>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Community Guidelines</h5>
                    <ul>
                        <li>Be respectful and professional in all interactions</li>
                        <li>Collaborate openly and share knowledge</li>
                        <li>Follow coding standards and best practices</li>
                        <li>Submit quality work and meet deadlines</li>
                        <li>Ask questions when you need help</li>
                        <li>Provide constructive feedback to others</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Community Video</h5>
                    <?php if (!empty($communityData['video_url']) || !empty($communityData['video_embed_code'])): ?>
                        <?php
                        // Process video display
                        $videoHtml = '';
                        
                        if (!empty($communityData['video_embed_code'])) {
                            // Use embed code if provided
                            $videoHtml = $communityData['video_embed_code'];
                        } elseif (!empty($communityData['video_url'])) {
                            $videoUrl = $communityData['video_url'];
                            
                            // YouTube URL patterns
                            if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]+)/', $videoUrl, $matches)) {
                                $videoId = $matches[1];
                                $videoHtml = '<iframe width="100%" height="315" src="https://www.youtube.com/embed/' . $videoId . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
                            }
                            // Vimeo URL patterns
                            elseif (preg_match('/vimeo\.com\/(\d+)/', $videoUrl, $matches)) {
                                $videoId = $matches[1];
                                $videoHtml = '<iframe src="https://player.vimeo.com/video/' . $videoId . '" width="100%" height="315" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>';
                            }
                            // Screencast.com or direct video URL
                            elseif (strpos($videoUrl, 'screencast.com') !== false || preg_match('/\.(mp4|webm|ogg)$/i', $videoUrl)) {
                                $videoHtml = '<video controls width="100%" height="315">
                                    <source src="' . htmlspecialchars($videoUrl) . '" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>';
                            }
                            // Generic iframe for other sources
                            else {
                                $videoHtml = '<iframe src="' . htmlspecialchars($videoUrl) . '" width="100%" height="315" frameborder="0" allowfullscreen></iframe>';
                            }
                        }
                        ?>
                        <div class="ratio ratio-16x9 mb-3">
                            <?php echo $videoHtml; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 bg-light rounded">
                            <i class="bi bi-play-circle display-1 text-muted"></i>
                            <p class="text-muted mt-3">No community video available</p>
                            <?php if (isCurrentUserAdmin()): ?>
                            <a href="/admin/communities.php" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-plus-circle"></i> Add Video
                            </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Community Stats</h5>
                    <?php
                    $db = getDB();
                    
                    // Get member count
                    $stmt = $db->prepare("SELECT COUNT(*) FROM community_members WHERE community_id = :id AND is_active = 1");
                    $stmt->execute([':id' => $communityId]);
                    $memberCount = $stmt->fetchColumn();
                    
                    // Get project count
                    $stmt = $db->prepare("SELECT COUNT(*) FROM projects WHERE community_id = :id");
                    $stmt->execute([':id' => $communityId]);
                    $projectCount = $stmt->fetchColumn();
                    
                    // Get course count
                    $stmt = $db->prepare("SELECT COUNT(*) FROM courses WHERE community_id = :id AND status = 'published'");
                    $stmt->execute([':id' => $communityId]);
                    $courseCount = $stmt->fetchColumn();
                    ?>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="bi bi-people-fill text-primary"></i> 
                            <strong><?php echo $memberCount; ?></strong> Members
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-folder-fill text-success"></i> 
                            <strong><?php echo $projectCount; ?></strong> Projects
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-book-fill text-info"></i> 
                            <strong><?php echo $courseCount; ?></strong> Courses
                        </li>
                    </ul>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Community Settings</h5>
                    <ul class="list-unstyled mb-3">
                        <li class="mb-2">
                            <strong>Visibility:</strong> 
                            <?php echo $communityData['is_public'] ? 'Public' : 'Private'; ?>
                        </li>
                        <li class="mb-2">
                            <strong>Join Policy:</strong> 
                            <?php echo $communityData['requires_approval'] ? 'Requires Approval' : 'Open'; ?>
                        </li>
                        <li class="mb-2">
                            <strong>Created:</strong> 
                            <?php echo date('F j, Y', strtotime($communityData['created_at'])); ?>
                        </li>
                    </ul>
                    
                    <?php if (isCurrentUserAdmin()): ?>
                    <div class="d-flex gap-2 mt-3">
                        <a href="/admin/communities.php" class="btn btn-primary flex-fill">
                            <i class="bi bi-gear"></i> Community Settings
                        </a>
                        <a href="/blog-categories.php" class="btn btn-outline-primary flex-fill">
                            <i class="bi bi-tags"></i> Manage Categories
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Community Map Section -->
    <div class="row mt-5">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Community Members Map</h5>
                </div>
                <div class="card-body p-0">
                    <?php
                    // Get community members with location data
                    $membersQuery = "
                        SELECT 
                            u.id,
                            CONCAT(u.first_name, ' ', u.last_name) as name,
                            u.email,
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
                        ORDER BY cm.role DESC, u.first_name ASC
                    ";
                    
                    $stmt = $db->prepare($membersQuery);
                    $stmt->execute([':community_id' => $communityId]);
                    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Prepare member data for JavaScript
                    $memberData = [];
                    foreach ($members as $member) {
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
                    ?>
                    
                    <?php if (empty($memberData)): ?>
                        <div class="p-4 text-center">
                            <i class="bi bi-geo-alt display-1 text-muted"></i>
                            <p class="mt-3 text-muted">No community members have added their location yet.</p>
                            <a href="profile.php" class="btn btn-primary btn-sm">Add Your Location</a>
                        </div>
                    <?php else: ?>
                        <div id="map" style="height: 500px; width: 100%;"></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php if (!empty($memberData)): ?>
<!-- Include Google Maps API and map initialization -->
<?php 
// Include the maps config
include_once 'config/maps.php';
?>
<script>
// Member data
const members = <?php echo json_encode($memberData); ?>;
let map;
let markers = [];
let infoWindow;

function initMap() {
    // Create map centered on a default location
    map = new google.maps.Map(document.getElementById('map'), {
        zoom: 2,
        center: { lat: 20, lng: 0 },
        mapTypeControl: true,
        streetViewControl: false
    });
    
    infoWindow = new google.maps.InfoWindow();
    
    // Create bounds to fit all markers
    const bounds = new google.maps.LatLngBounds();
    
    // Process members
    members.forEach(member => {
        if (member.lat && member.lng) {
            // Member already has coordinates
            createMarker(member);
            bounds.extend(new google.maps.LatLng(member.lat, member.lng));
        } else if (member.address) {
            // Need to geocode
            geocodeAddress(member);
        }
    });
    
    // Fit map to bounds if we have markers
    if (markers.length > 0) {
        map.fitBounds(bounds);
    }
}

function createMarker(member) {
    const position = { lat: member.lat, lng: member.lng };
    
    // Different colors for different roles
    let iconUrl = 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png';
    if (member.role === 'admin' || member.role === 'owner') {
        iconUrl = 'https://maps.google.com/mapfiles/ms/icons/red-dot.png';
    }
    
    const marker = new google.maps.Marker({
        position: position,
        map: map,
        title: member.name,
        icon: iconUrl
    });
    
    // Info window content
    let content = `<div style="min-width: 200px;">
        <h6>${member.name}</h6>
        <p class="mb-1"><strong>Role:</strong> ${member.role.charAt(0).toUpperCase() + member.role.slice(1)}</p>`;
    
    if (member.city || member.state || member.country) {
        const location = [member.city, member.state, member.country].filter(Boolean).join(', ');
        content += `<p class="mb-0"><small>${location}</small></p>`;
    }
    
    content += '</div>';
    
    marker.addListener('click', () => {
        infoWindow.setContent(content);
        infoWindow.open(map, marker);
    });
    
    markers.push(marker);
}

function geocodeAddress(member) {
    const geocoder = new google.maps.Geocoder();
    
    geocoder.geocode({ address: member.address }, (results, status) => {
        if (status === 'OK' && results[0]) {
            member.lat = results[0].geometry.location.lat();
            member.lng = results[0].geometry.location.lng();
            createMarker(member);
            
            // Update member coordinates in database
            updateMemberCoordinates(member.id, member.lat, member.lng);
        }
    });
}

function updateMemberCoordinates(userId, lat, lng) {
    fetch('/api/update-coordinates.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            user_id: userId,
            latitude: lat,
            longitude: lng
        })
    });
}
</script>
<script async defer 
    src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_MAPS_API_KEY; ?>&callback=initMap">
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>