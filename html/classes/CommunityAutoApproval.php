<?php
require_once __DIR__ . '/../config/database.php';

class CommunityAutoApproval {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Add an auto-approval rule
     */
    public function add($community_id, $created_by, $email = null, $username = null, $description = '') {
        if (!$email && !$username) {
            throw new Exception("Either email or username must be provided");
        }
        
        $sql = "INSERT INTO community_auto_approvals 
                (community_id, email, username, description, created_by) 
                VALUES (:community_id, :email, :username, :description, :created_by)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'community_id' => $community_id,
            'email' => $email,
            'username' => $username,
            'description' => $description,
            'created_by' => $created_by
        ]);
    }
    
    /**
     * Check if a user is auto-approved for a community
     */
    public function isAutoApproved($community_id, $email, $username) {
        // First check the regular auto-approval rules
        $sql = "SELECT COUNT(*) as count 
                FROM community_auto_approvals 
                WHERE community_id = :community_id 
                AND is_active = 1
                AND ((email IS NOT NULL AND email = :email)
                     OR (username IS NOT NULL AND username = :username))";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'community_id' => $community_id,
            'email' => $email,
            'username' => $username
        ]);
        
        $result = $stmt->fetch();
        if ($result['count'] > 0) {
            return true;
        }
        
        // Check if community has auto-approve from email list enabled
        $sql = "SELECT auto_approve_from_email_list 
                FROM communities 
                WHERE id = :community_id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['community_id' => $community_id]);
        $community = $stmt->fetch();
        
        if ($community && $community['auto_approve_from_email_list'] == 1) {
            // Check if email exists in free_community_emails table
            $sql = "SELECT COUNT(*) as count 
                    FROM free_community_emails 
                    WHERE email = :email";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['email' => $email]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                // Email found in the list - auto-approve
                // Note: We cannot mark as processed since the table doesn't have that column
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get all auto-approval rules for a community
     */
    public function getByCommunity($community_id) {
        $sql = "SELECT caa.*, u.first_name, u.last_name 
                FROM community_auto_approvals caa
                JOIN users u ON caa.created_by = u.id
                WHERE caa.community_id = :community_id
                ORDER BY caa.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['community_id' => $community_id]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Update an auto-approval rule
     */
    public function update($id, $email = null, $username = null, $description = '', $is_active = true) {
        if (!$email && !$username) {
            throw new Exception("Either email or username must be provided");
        }
        
        $sql = "UPDATE community_auto_approvals 
                SET email = :email, 
                    username = :username, 
                    description = :description,
                    is_active = :is_active
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'email' => $email,
            'username' => $username,
            'description' => $description,
            'is_active' => $is_active
        ]);
    }
    
    /**
     * Delete an auto-approval rule
     */
    public function delete($id) {
        $sql = "DELETE FROM community_auto_approvals WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }
    
    /**
     * Toggle active status
     */
    public function toggleActive($id) {
        $sql = "UPDATE community_auto_approvals 
                SET is_active = NOT is_active 
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }
}