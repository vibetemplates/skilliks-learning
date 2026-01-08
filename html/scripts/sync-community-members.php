<?php
/**
 * Script to sync users with default_community_id to community_members table
 * This ensures users who have a default community are actually members of that community
 */

require_once dirname(__DIR__) . '/config/database.php';

$db = Database::getInstance()->getConnection();

try {
    // First, let's see what we're dealing with
    echo "Checking for orphaned users...\n\n";
    
    $stmt = $db->prepare("
        SELECT u.id, u.email, u.first_name, u.last_name, u.default_community_id, c.name as community_name
        FROM users u
        INNER JOIN communities c ON u.default_community_id = c.id
        LEFT JOIN community_members cm 
            ON u.id = cm.user_id 
            AND u.default_community_id = cm.community_id
            AND cm.is_active = 1
        WHERE u.default_community_id IS NOT NULL
        AND cm.id IS NULL
        ORDER BY u.default_community_id, u.id
    ");
    $stmt->execute();
    $orphanedUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($orphanedUsers)) {
        echo "No orphaned users found. All users with default_community_id are members of their communities.\n";
        exit(0);
    }
    
    echo "Found " . count($orphanedUsers) . " orphaned users:\n\n";
    
    // Group by community for better display
    $byComm = [];
    foreach ($orphanedUsers as $user) {
        $commId = $user['default_community_id'];
        if (!isset($byComm[$commId])) {
            $byComm[$commId] = [
                'name' => $user['community_name'],
                'users' => []
            ];
        }
        $byComm[$commId]['users'][] = $user;
    }
    
    foreach ($byComm as $commId => $data) {
        echo "Community: " . $data['name'] . " (ID: $commId)\n";
        foreach ($data['users'] as $user) {
            echo "  - " . $user['first_name'] . " " . $user['last_name'] . " (" . $user['email'] . ")\n";
        }
        echo "\n";
    }
    
    // Ask for confirmation
    echo "Do you want to add these users to their respective communities? (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    
    if (trim($line) != 'yes') {
        echo "Aborted.\n";
        exit(0);
    }
    
    // Add the users to community_members
    echo "\nAdding users to community_members...\n";
    
    $insertStmt = $db->prepare("
        INSERT INTO community_members (community_id, user_id, role, is_active, joined_at)
        VALUES (:community_id, :user_id, 'member', 1, NOW())
    ");
    
    $count = 0;
    foreach ($orphanedUsers as $user) {
        try {
            $insertStmt->execute([
                ':community_id' => $user['default_community_id'],
                ':user_id' => $user['id']
            ]);
            $count++;
            echo ".";
            if ($count % 10 == 0) {
                echo " ($count)\n";
            }
        } catch (PDOException $e) {
            echo "\nError adding user " . $user['email'] . ": " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n\nSuccessfully added $count users to their default communities.\n";
    
    // Verify the fix
    echo "\nVerifying the fix...\n";
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE default_community_id = 1");
    $stmt->execute();
    $usersWithDefault1 = $stmt->fetch()['count'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM community_members WHERE community_id = 1 AND is_active = 1");
    $stmt->execute();
    $membersInComm1 = $stmt->fetch()['count'];
    
    echo "Users with default_community_id = 1: $usersWithDefault1\n";
    echo "Active members in community 1: $membersInComm1\n";
    
    if ($usersWithDefault1 == $membersInComm1) {
        echo "\nSuccess! All users with default_community_id = 1 are now members of the community.\n";
    } else {
        echo "\nWarning: There's still a mismatch. Please check for inactive members or other issues.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}