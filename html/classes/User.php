<?php
/**
 * User Class
 * 
 * Handles team member database operations
 */

require_once dirname(dirname(__FILE__)) . '/config/database.php';
require_once dirname(dirname(__FILE__)) . '/config/constants.php';
require_once dirname(dirname(__FILE__)) . '/config/functions.php';

class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Create a new user
     */
    public function create($data) {
        try {
            // Get default community ID
            $defaultCommunityId = null;
            $assignedCommunityId = null;
            
            // If registering with a specific community, track it separately
            if (!empty($data['community_id']) && $data['community_id'] > 0) {
                $assignedCommunityId = $data['community_id'];
            } else {
                // Check if email is specifically allowed for community 1 (paid community)
                $stmt = $this->db->prepare("SELECT id FROM community_allowed_users WHERE community_id = 1 AND email = ?");
                $stmt->execute([$data['email']]);
                $allowed_for_community_1 = $stmt->fetch();
                
                if ($allowed_for_community_1) {
                    // Email is specifically allowed for community 1
                    $assignedCommunityId = 1;
                } else {
                    // Email is not allowed for community 1, assign to community 2
                    $assignedCommunityId = 2;
                }
            }
            
            // Don't set default_community_id yet - wait to see if they get actual membership
            
            $sql = "INSERT INTO users (email, password_hash, first_name, last_name, student_id, github_username, global_role, verification_token, default_community_id) 
                    VALUES (:email, :password_hash, :first_name, :last_name, :student_id, :github_username, 'user', :verification_token, :default_community_id)";
            
            $stmt = $this->db->prepare($sql);
            
            // Generate verification token
            $verificationToken = generateRandomString(32);
            
            $params = [
                ':email' => $data['email'],
                ':password_hash' => password_hash($data['password'], PASSWORD_DEFAULT, ['cost' => BCRYPT_COST]),
                ':first_name' => $data['first_name'],
                ':last_name' => $data['last_name'],
                ':student_id' => $data['student_id'] ?? null,
                ':github_username' => $data['github_username'] ?? null,
                ':verification_token' => $verificationToken,
                ':default_community_id' => null // Don't set default community until we know if they get membership
            ];
            
            if ($stmt->execute($params)) {
                $userId = $this->db->lastInsertId();
                
                // Add user to their default community as a member
                $communityStatus = 'none';
                $autoApproved = false;
                $communityName = null;
                
                if ($assignedCommunityId) {
                    // Special validation for community 1 (paid community)
                    if ($assignedCommunityId == 1) {
                        // Check if email is in community_allowed_users for community 1
                        $allowedStmt = $this->db->prepare("SELECT id FROM community_allowed_users WHERE community_id = 1 AND email = :email");
                        $allowedStmt->execute([':email' => $data['email']]);
                        if (!$allowedStmt->fetch()) {
                            // Email not allowed for community 1, prevent registration
                            return false;
                        }
                    }
                    
                    // Check if community requires approval
                    $approvalStmt = $this->db->prepare("SELECT name, requires_approval, auto_approve_from_email_list FROM communities WHERE id = :id");
                    $approvalStmt->execute([':id' => $assignedCommunityId]);
                    $community = $approvalStmt->fetch();
                    
                    if ($community) {
                        $communityName = $community['name'];
                        
                        // Check for auto-approval
                        require_once dirname(__DIR__) . '/classes/CommunityAutoApproval.php';
                        $autoApproval = new CommunityAutoApproval();
                        $autoApproved = $autoApproval->isAutoApproved($assignedCommunityId, $data['email'], null);
                        
                        if (!$community['requires_approval'] || $autoApproved) {
                            // Direct membership for communities that don't require approval or auto-approved users
                            $memberStmt = $this->db->prepare("
                                INSERT INTO community_members (community_id, user_id, role, is_active, joined_at) 
                                VALUES (:community_id, :user_id, 'member', 1, NOW())
                                ON DUPLICATE KEY UPDATE is_active = 1
                            ");
                            $memberStmt->execute([
                                ':community_id' => $assignedCommunityId,
                                ':user_id' => $userId
                            ]);
                            
                            $communityStatus = $autoApproved ? 'auto_approved' : 'joined';
                            
                            // Mark email as processed if it was auto-approved from free_community_emails
                            if ($autoApproved && $community['auto_approve_from_email_list'] == 1) {
                                $processStmt = $this->db->prepare("UPDATE free_community_emails SET processed = 1, processed_at = NOW() WHERE email = :email AND processed = 0");
                                $processStmt->execute([':email' => $data['email']]);
                            }
                        } else {
                            // Create join request for communities that require approval
                            $requestStmt = $this->db->prepare("
                                INSERT INTO community_join_requests (community_id, user_id, status, requested_at) 
                                VALUES (:community_id, :user_id, 'pending', NOW())
                                ON DUPLICATE KEY UPDATE status = 'pending', requested_at = NOW()
                            ");
                            $requestStmt->execute([
                                ':community_id' => $assignedCommunityId,
                                ':user_id' => $userId
                            ]);
                            
                            $communityStatus = 'pending_approval';
                        }
                        
                        // Only set the current session community if user got actual membership
                        if (!empty($data['community_id']) && $communityStatus !== 'pending_approval') {
                            $_SESSION['current_community_id'] = $assignedCommunityId;
                        }
                        
                        // Update user's default community only if they got actual membership
                        if ($communityStatus === 'joined' || $communityStatus === 'auto_approved') {
                            $updateStmt = $this->db->prepare("UPDATE users SET default_community_id = :community_id WHERE id = :user_id");
                            $updateStmt->execute([
                                ':community_id' => $assignedCommunityId,
                                ':user_id' => $userId
                            ]);
                        }
                    }
                }
                
                return [
                    'id' => $userId,
                    'verification_token' => $verificationToken,
                    'community_status' => $communityStatus,
                    'community_name' => $communityName,
                    'auto_approved' => $autoApproved
                ];
            }
        } catch (PDOException $e) {
            error_log("User creation error: " . $e->getMessage());
            return false;
        }
        
        return false;
    }
    
    /**
     * Register a new user
     */
    public function register($data) {
        // Check if email already exists
        if ($this->findByEmail($data['email'])) {
            return ['success' => false, 'error' => 'Email already exists.'];
        }
        
        // Create the user
        $result = $this->create($data);
        
        if ($result && isset($result['id'])) {
            return [
                'success' => true, 
                'user_id' => $result['id'], 
                'verification_token' => $result['verification_token'],
                'community_status' => $result['community_status'] ?? 'none',
                'community_name' => $result['community_name'] ?? null,
                'auto_approved' => $result['auto_approved'] ?? false
            ];
        } else {
            return ['success' => false, 'error' => 'Failed to create user account.'];
        }
    }
    
    /**
     * Find user by email
     */
    public function findByEmail($email) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        return $stmt->fetch();
    }
    
    /**
     * Find user by ID
     */
    public function findById($id) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }
    
    /**
     * Verify user email
     */
    public function verifyEmail($token) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET email_verified = TRUE, verification_token = NULL 
            WHERE verification_token = :token AND email_verified = FALSE
        ");
        
        $stmt->execute([':token' => $token]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Update user profile
     */
    public function update($userId, $data) {
        $allowedFields = ['first_name', 'last_name', 'student_id', 'github_username', 'bio', 'skills', 'profile_photo'];
        $updates = [];
        $params = [':id' => $userId];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }
    
    /**
     * Update last login
     */
    public function updateLastLogin($userId) {
        $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
        return $stmt->execute([':id' => $userId]);
    }
    
    /**
     * Authenticate user
     */
    public function authenticate($email, $password) {
        $user = $this->findByEmail($email);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            if (!$user['email_verified']) {
                return ['error' => 'Please verify your email before logging in.'];
            }
            
            $this->updateLastLogin($user['id']);
            return $user;
        }
        
        return false;
    }
    
    /**
     * Generate password reset token
     */
    public function generateResetToken($email) {
        $user = $this->findByEmail($email);
        if (!$user) {
            return false;
        }
        
        $token = generateRandomString(32);
        $expires = date('Y-m-d H:i:s', strtotime('+1 day'));
        
        $stmt = $this->db->prepare("
            UPDATE users 
            SET reset_token = :token, reset_token_expires = :expires 
            WHERE id = :id
        ");
        
        $stmt->execute([
            ':token' => $token,
            ':expires' => $expires,
            ':id' => $user['id']
        ]);
        
        return $token;
    }
    
    /**
     * Reset password with token
     */
    public function resetPassword($token, $newPassword) {
        $stmt = $this->db->prepare("
            SELECT id FROM users 
            WHERE reset_token = :token 
            AND reset_token_expires > NOW()
        ");
        
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        $stmt = $this->db->prepare("
            UPDATE users 
            SET password_hash = :password_hash, 
                reset_token = NULL, 
                reset_token_expires = NULL 
            WHERE id = :id
        ");
        
        return $stmt->execute([
            ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT, ['cost' => BCRYPT_COST]),
            ':id' => $user['id']
        ]);
    }
    
    /**
     * Assign role to user
     */
    public function assignRole($userId, $roleId, $projectId = null, $assignedBy = null) {
        $stmt = $this->db->prepare("
            INSERT INTO user_roles (user_id, role_id, project_id, assigned_by)
            VALUES (:user_id, :role_id, :project_id, :assigned_by)
            ON DUPLICATE KEY UPDATE assigned_by = :assigned_by, assigned_at = NOW()
        ");
        
        return $stmt->execute([
            ':user_id' => $userId,
            ':role_id' => $roleId,
            ':project_id' => $projectId,
            ':assigned_by' => $assignedBy
        ]);
    }
    
    /**
     * Get user roles
     */
    public function getRoles($userId, $projectId = null) {
        $sql = "SELECT r.*, ur.project_id 
                FROM user_roles ur 
                JOIN roles r ON ur.role_id = r.id 
                WHERE ur.user_id = :user_id";
        
        $params = [':user_id' => $userId];
        
        if ($projectId !== null) {
            $sql .= " AND (ur.project_id = :project_id OR ur.project_id IS NULL)";
            $params[':project_id'] = $projectId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Search users
     */
    public function search($query, $limit = 20) {
        $stmt = $this->db->prepare("
            SELECT id, email, first_name, last_name, github_username 
            FROM users 
            WHERE (
                email LIKE :query 
                OR first_name LIKE :query 
                OR last_name LIKE :query 
                OR github_username LIKE :query
                OR CONCAT(first_name, ' ', last_name) LIKE :query
            )
            AND email_verified = TRUE
            LIMIT :limit
        ");
        
        $stmt->bindValue(':query', '%' . $query . '%');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get user's projects
     */
    public function getProjects($userId) {
        $stmt = $this->db->prepare("
            SELECT p.*, pm.join_date, pm.status as member_status
            FROM project_members pm
            JOIN projects p ON pm.project_id = p.id
            WHERE pm.user_id = :user_id
            AND pm.status = 'approved'
            ORDER BY pm.join_date DESC
        ");
        
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }
    
    
    /**
     * Update user global role (site admin only)
     */
    public function updateGlobalRole($userId, $isAdmin, $adminId, $reason = '') {
        // Check if current user is global admin
        if (!$this->isGlobalAdmin($adminId)) {
            return ['success' => false, 'error' => 'Only global administrators can change global roles.'];
        }
        
        try {
            $this->db->beginTransaction();
            
            $newRole = $isAdmin ? 'admin' : 'user';
            
            // Update users table
            $stmt = $this->db->prepare("UPDATE users SET global_role = :role WHERE id = :id");
            $stmt->execute([':role' => $newRole, ':id' => $userId]);
            
            // Update global_admins table
            if ($isAdmin) {
                // Add to global_admins
                $stmt = $this->db->prepare("INSERT INTO global_admins (user_id, granted_by, notes) 
                                           VALUES (:user_id, :granted_by, :notes)
                                           ON DUPLICATE KEY UPDATE granted_by = :granted_by2, granted_at = NOW()");
                $stmt->execute([
                    ':user_id' => $userId,
                    ':granted_by' => $adminId,
                    ':granted_by2' => $adminId,
                    ':notes' => $reason ?: 'Promoted to global admin'
                ]);
            } else {
                // Remove from global_admins
                $stmt = $this->db->prepare("DELETE FROM global_admins WHERE user_id = :user_id");
                $stmt->execute([':user_id' => $userId]);
            }
            
            // Log the change
            $stmt = $this->db->prepare("INSERT INTO role_change_log 
                                       (user_id, old_role, new_role, changed_by, reason, change_type)
                                       VALUES (:user_id, :old_role, :new_role, :changed_by, :reason, 'global')");
            $stmt->execute([
                ':user_id' => $userId,
                ':old_role' => $isAdmin ? 'user' : 'admin',
                ':new_role' => $newRole,
                ':changed_by' => $adminId,
                ':reason' => $reason
            ]);
            
            $this->db->commit();
            return ['success' => true];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Global role update error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error occurred.'];
        }
    }
    
    /**
     * Update user role (deprecated - for backwards compatibility)
     */
    public function updateRole($userId, $newRole, $adminId) {
        // Map old roles to new system
        if ($newRole === 'admin') {
            return $this->updateGlobalRole($userId, true, $adminId, 'Legacy role update');
        } else {
            return $this->updateGlobalRole($userId, false, $adminId, 'Legacy role update');
        }
    }
    
    /**
     * Check if user is global admin
     */
    public function isGlobalAdmin($userId) {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM global_admins WHERE user_id = :id");
            $stmt->execute([':id' => $userId]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Check if user is admin (global admin)
     * @deprecated Use isGlobalAdmin() instead
     */
    public function isAdmin($userId) {
        return $this->isGlobalAdmin($userId);
    }
    
    /**
     * Check if user is project manager or admin
     */
    public function isProjectManagerOrAdmin($userId) {
        try {
            // Check if global admin first
            if ($this->isGlobalAdmin($userId)) {
                return true;
            }
            
            // Check community roles
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM community_members 
                WHERE user_id = :id 
                AND role IN ('admin', 'moderator', 'owner') 
                AND is_active = 1
            ");
            $stmt->execute([':id' => $userId]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Get user's global role
     */
    public function getGlobalRole($userId) {
        try {
            $stmt = $this->db->prepare("SELECT global_role FROM users WHERE id = :id");
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch();
            return $user ? $user['global_role'] : 'user';
        } catch (PDOException $e) {
            return 'user';
        }
    }
    
    /**
     * Get user role
     * @deprecated Use getGlobalRole() or getCommunityRole() instead
     */
    public function getUserRole($userId) {
        // Return 'admin' if global admin, otherwise 'member'
        return $this->isGlobalAdmin($userId) ? 'admin' : 'member';
    }
    
    /**
     * Get user's role in a specific community
     */
    public function getCommunityRole($userId, $communityId) {
        try {
            $stmt = $this->db->prepare("
                SELECT role FROM community_members 
                WHERE user_id = :user_id 
                AND community_id = :community_id 
                AND is_active = 1
            ");
            $stmt->execute([
                ':user_id' => $userId,
                ':community_id' => $communityId
            ]);
            $result = $stmt->fetch();
            return $result ? $result['role'] : null;
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * Get all communities where user has a role
     */
    public function getUserCommunityRoles($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT cm.*, c.name as community_name, c.slug as community_slug
                FROM community_members cm
                JOIN communities c ON cm.community_id = c.id
                WHERE cm.user_id = :user_id 
                AND cm.is_active = 1
                AND c.is_active = 1
                ORDER BY cm.role DESC, c.name ASC
            ");
            $stmt->execute([':user_id' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Update user profile
     */
    public function updateProfile($userId, $profileData) {
        try {
            $stmt = $this->db->prepare("
                UPDATE users SET 
                    skill_level = :skill_level,
                    years_programming_experience = :years_programming_experience,
                    years_project_management_experience = :years_project_management_experience,
                    programming_languages = :programming_languages,
                    database_technologies = :database_technologies,
                    timezone = :timezone,
                    best_meeting_times = :best_meeting_times,
                    ai_assisted_coding_current = :ai_assisted_coding_current,
                    ai_assisted_coding_goal = :ai_assisted_coding_goal,
                    mcp_servers_current = :mcp_servers_current,
                    mcp_servers_goal = :mcp_servers_goal,
                    ai_automations_current = :ai_automations_current,
                    ai_automations_goal = :ai_automations_goal,
                    startup_operations_current = :startup_operations_current,
                    startup_operations_goal = :startup_operations_goal,
                    ai_security_current = :ai_security_current,
                    ai_security_goal = :ai_security_goal,
                    ai_infrastructure_current = :ai_infrastructure_current,
                    ai_infrastructure_goal = :ai_infrastructure_goal,
                    rag_current = :rag_current,
                    rag_goal = :rag_goal,
                    local_models_current = :local_models_current,
                    local_models_goal = :local_models_goal,
                    supervised_fine_tuning_current = :supervised_fine_tuning_current,
                    supervised_fine_tuning_goal = :supervised_fine_tuning_goal,
                    bio = :bio,
                    skills = :skills,
                    location_address = :location_address,
                    location_city = :location_city,
                    location_state = :location_state,
                    location_country = :location_country,
                    location_privacy = :location_privacy,
                    location_updated_at = NOW(),
                    updated_at = NOW()
                WHERE id = :id
            ");
            
            $params = [
                ':skill_level' => $profileData['skill_level'],
                ':years_programming_experience' => $profileData['years_programming_experience'],
                ':years_project_management_experience' => $profileData['years_project_management_experience'],
                ':programming_languages' => $profileData['programming_languages'],
                ':database_technologies' => $profileData['database_technologies'],
                ':timezone' => $profileData['timezone'],
                ':best_meeting_times' => $profileData['best_meeting_times'],
                ':ai_assisted_coding_current' => $profileData['ai_assisted_coding_current'],
                ':ai_assisted_coding_goal' => $profileData['ai_assisted_coding_goal'],
                ':mcp_servers_current' => $profileData['mcp_servers_current'],
                ':mcp_servers_goal' => $profileData['mcp_servers_goal'],
                ':ai_automations_current' => $profileData['ai_automations_current'],
                ':ai_automations_goal' => $profileData['ai_automations_goal'],
                ':startup_operations_current' => $profileData['startup_operations_current'],
                ':startup_operations_goal' => $profileData['startup_operations_goal'],
                ':ai_security_current' => $profileData['ai_security_current'],
                ':ai_security_goal' => $profileData['ai_security_goal'],
                ':ai_infrastructure_current' => $profileData['ai_infrastructure_current'],
                ':ai_infrastructure_goal' => $profileData['ai_infrastructure_goal'],
                ':rag_current' => $profileData['rag_current'],
                ':rag_goal' => $profileData['rag_goal'],
                ':local_models_current' => $profileData['local_models_current'],
                ':local_models_goal' => $profileData['local_models_goal'],
                ':supervised_fine_tuning_current' => $profileData['supervised_fine_tuning_current'],
                ':supervised_fine_tuning_goal' => $profileData['supervised_fine_tuning_goal'],
                ':bio' => $profileData['bio'],
                ':skills' => $profileData['skills'],
                ':location_address' => $profileData['location_address'] ?? '',
                ':location_city' => $profileData['location_city'] ?? '',
                ':location_state' => $profileData['location_state'] ?? '',
                ':location_country' => $profileData['location_country'] ?? '',
                ':location_privacy' => $profileData['location_privacy'] ?? 'community',
                ':id' => $userId
            ];
            
            $result = $stmt->execute($params);
            
            if ($result) {
                return ['success' => true];
            } else {
                $errorInfo = $stmt->errorInfo();
                error_log("Profile update failed - Error Info: " . print_r($errorInfo, true));
                return ['success' => false, 'error' => 'Failed to update profile: ' . ($errorInfo[2] ?? 'Unknown error')];
            }
        } catch (PDOException $e) {
            error_log("Profile update PDO exception: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Update account information
     */
    public function updateAccount($userId, $accountData) {
        try {
            $stmt = $this->db->prepare("
                UPDATE users SET 
                    first_name = :first_name,
                    last_name = :last_name,
                    email = :email,
                    student_id = :student_id,
                    github_username = :github_username,
                    updated_at = NOW()
                WHERE id = :id
            ");
            
            $params = [
                ':first_name' => $accountData['first_name'],
                ':last_name' => $accountData['last_name'],
                ':email' => $accountData['email'],
                ':student_id' => $accountData['student_id'],
                ':github_username' => $accountData['github_username'],
                ':id' => $userId
            ];
            
            $result = $stmt->execute($params);
            
            if ($result) {
                return ['success' => true];
            } else {
                $errorInfo = $stmt->errorInfo();
                return ['success' => false, 'error' => 'Failed to update account: ' . ($errorInfo[2] ?? 'Unknown error')];
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate entry
                return ['success' => false, 'error' => 'Email address is already in use.'];
            }
            error_log("Account update PDO exception: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Change user password
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            // First verify current password
            $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = :id");
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
                return ['success' => false, 'error' => 'Current password is incorrect.'];
            }
            
            // Update password
            $stmt = $this->db->prepare("
                UPDATE users SET 
                    password_hash = :password_hash,
                    updated_at = NOW()
                WHERE id = :id
            ");
            
            $result = $stmt->execute([
                ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT, ['cost' => BCRYPT_COST]),
                ':id' => $userId
            ]);
            
            if ($result) {
                return ['success' => true];
            } else {
                return ['success' => false, 'error' => 'Failed to update password.'];
            }
        } catch (PDOException $e) {
            error_log("Password change PDO exception: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error occurred.'];
        }
    }
    
    /**
     * Get notification preferences
     */
    public function getNotificationPreferences($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT email_task_assigned, email_task_completed, email_project_updates, 
                       email_feature_promoted, email_weekly_digest, browser_notifications
                FROM user_settings 
                WHERE user_id = :user_id
            ");
            $stmt->execute([':user_id' => $userId]);
            $settings = $stmt->fetch();
            
            // Return defaults if no settings found
            if (!$settings) {
                return [
                    'email_task_assigned' => 1,
                    'email_task_completed' => 1,
                    'email_project_updates' => 1,
                    'email_feature_promoted' => 1,
                    'email_weekly_digest' => 0,
                    'browser_notifications' => 0
                ];
            }
            
            return $settings;
        } catch (PDOException $e) {
            // Return defaults on error
            return [
                'email_task_assigned' => 1,
                'email_task_completed' => 1,
                'email_project_updates' => 1,
                'email_feature_promoted' => 1,
                'email_weekly_digest' => 0,
                'browser_notifications' => 0
            ];
        }
    }
    
    /**
     * Update notification preferences
     */
    public function updateNotificationPreferences($userId, $notificationData) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO user_settings (
                    user_id, email_task_assigned, email_task_completed, email_project_updates,
                    email_feature_promoted, email_weekly_digest, browser_notifications
                ) VALUES (
                    :user_id, :email_task_assigned, :email_task_completed, :email_project_updates,
                    :email_feature_promoted, :email_weekly_digest, :browser_notifications
                ) ON DUPLICATE KEY UPDATE
                    email_task_assigned = VALUES(email_task_assigned),
                    email_task_completed = VALUES(email_task_completed),
                    email_project_updates = VALUES(email_project_updates),
                    email_feature_promoted = VALUES(email_feature_promoted),
                    email_weekly_digest = VALUES(email_weekly_digest),
                    browser_notifications = VALUES(browser_notifications),
                    updated_at = NOW()
            ");
            
            $params = [
                ':user_id' => $userId,
                ':email_task_assigned' => $notificationData['email_task_assigned'],
                ':email_task_completed' => $notificationData['email_task_completed'],
                ':email_project_updates' => $notificationData['email_project_updates'],
                ':email_feature_promoted' => $notificationData['email_feature_promoted'],
                ':email_weekly_digest' => $notificationData['email_weekly_digest'],
                ':browser_notifications' => $notificationData['browser_notifications']
            ];
            
            $result = $stmt->execute($params);
            
            if ($result) {
                return ['success' => true];
            } else {
                return ['success' => false, 'error' => 'Failed to update notification preferences.'];
            }
        } catch (PDOException $e) {
            error_log("Notification preferences update PDO exception: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error occurred.'];
        }
    }
    
    /**
     * Get privacy settings
     */
    public function getPrivacySettings($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT profile_public, show_email, show_github, show_skills, allow_direct_messages
                FROM user_settings 
                WHERE user_id = :user_id
            ");
            $stmt->execute([':user_id' => $userId]);
            $settings = $stmt->fetch();
            
            // Return defaults if no settings found
            if (!$settings) {
                return [
                    'profile_public' => 1,
                    'show_email' => 0,
                    'show_github' => 1,
                    'show_skills' => 1,
                    'allow_direct_messages' => 1
                ];
            }
            
            return $settings;
        } catch (PDOException $e) {
            // Return defaults on error
            return [
                'profile_public' => 1,
                'show_email' => 0,
                'show_github' => 1,
                'show_skills' => 1,
                'allow_direct_messages' => 1
            ];
        }
    }
    
    /**
     * Update privacy settings
     */
    public function updatePrivacySettings($userId, $privacyData) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO user_settings (
                    user_id, profile_public, show_email, show_github, show_skills, allow_direct_messages
                ) VALUES (
                    :user_id, :profile_public, :show_email, :show_github, :show_skills, :allow_direct_messages
                ) ON DUPLICATE KEY UPDATE
                    profile_public = VALUES(profile_public),
                    show_email = VALUES(show_email),
                    show_github = VALUES(show_github),
                    show_skills = VALUES(show_skills),
                    allow_direct_messages = VALUES(allow_direct_messages),
                    updated_at = NOW()
            ");
            
            $params = [
                ':user_id' => $userId,
                ':profile_public' => $privacyData['profile_public'],
                ':show_email' => $privacyData['show_email'],
                ':show_github' => $privacyData['show_github'],
                ':show_skills' => $privacyData['show_skills'],
                ':allow_direct_messages' => $privacyData['allow_direct_messages']
            ];
            
            $result = $stmt->execute($params);
            
            if ($result) {
                return ['success' => true];
            } else {
                return ['success' => false, 'error' => 'Failed to update privacy settings.'];
            }
        } catch (PDOException $e) {
            error_log("Privacy settings update PDO exception: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error occurred.'];
        }
    }
    
    /**
     * Get display preferences
     */
    public function getDisplayPreferences($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT theme_preference, items_per_page, default_task_view, show_completed_tasks, compact_mode
                FROM user_settings 
                WHERE user_id = :user_id
            ");
            $stmt->execute([':user_id' => $userId]);
            $settings = $stmt->fetch();
            
            // Return defaults if no settings found
            if (!$settings) {
                return [
                    'theme_preference' => 'auto',
                    'items_per_page' => 20,
                    'default_task_view' => 'list',
                    'show_completed_tasks' => 1,
                    'compact_mode' => 0
                ];
            }
            
            return $settings;
        } catch (PDOException $e) {
            // Return defaults on error
            return [
                'theme_preference' => 'auto',
                'items_per_page' => 20,
                'default_task_view' => 'list',
                'show_completed_tasks' => 1,
                'compact_mode' => 0
            ];
        }
    }
    
    /**
     * Update display preferences
     */
    public function updateDisplayPreferences($userId, $displayData) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO user_settings (
                    user_id, theme_preference, items_per_page, default_task_view, 
                    show_completed_tasks, compact_mode
                ) VALUES (
                    :user_id, :theme_preference, :items_per_page, :default_task_view,
                    :show_completed_tasks, :compact_mode
                ) ON DUPLICATE KEY UPDATE
                    theme_preference = VALUES(theme_preference),
                    items_per_page = VALUES(items_per_page),
                    default_task_view = VALUES(default_task_view),
                    show_completed_tasks = VALUES(show_completed_tasks),
                    compact_mode = VALUES(compact_mode),
                    updated_at = NOW()
            ");
            
            $params = [
                ':user_id' => $userId,
                ':theme_preference' => $displayData['theme_preference'],
                ':items_per_page' => $displayData['items_per_page'],
                ':default_task_view' => $displayData['default_task_view'],
                ':show_completed_tasks' => $displayData['show_completed_tasks'],
                ':compact_mode' => $displayData['compact_mode']
            ];
            
            $result = $stmt->execute($params);
            
            if ($result) {
                return ['success' => true];
            } else {
                return ['success' => false, 'error' => 'Failed to update display preferences.'];
            }
        } catch (PDOException $e) {
            error_log("Display preferences update PDO exception: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error occurred.'];
        }
    }
    
    /**
     * Upload and update user avatar
     */
    public function uploadAvatar($userId, $file) {
        try {
            // Validate file
            if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                return ['success' => false, 'error' => 'Invalid file upload.'];
            }
            
            // Check file type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $fileType = mime_content_type($file['tmp_name']);
            if (!in_array($fileType, $allowedTypes)) {
                return ['success' => false, 'error' => 'Invalid file type. Please upload JPG, PNG, GIF, or WebP images.'];
            }
            
            // Check file size (5MB limit)
            if ($file['size'] > 5 * 1024 * 1024) {
                return ['success' => false, 'error' => 'File size too large. Maximum 5MB allowed.'];
            }
            
            // Create uploads directory if it doesn't exist
            $uploadDir = dirname(dirname(__FILE__)) . '/uploads/avatars/';
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    return ['success' => false, 'error' => 'Failed to create upload directory.'];
                }
            }
            
            // Generate unique filename
            $extension = $this->getFileExtension($fileType);
            $filename = 'avatar_' . $userId . '_' . time() . '.' . $extension;
            $filepath = $uploadDir . $filename;
            
            // Get current avatar to delete later
            $currentUser = $this->findById($userId);
            $oldAvatar = $currentUser['profile_photo'] ?? null;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Resize image to standard size
                $this->resizeImage($filepath, 200, 200);
                
                // Update database
                $stmt = $this->db->prepare("UPDATE users SET profile_photo = :filename WHERE id = :id");
                $result = $stmt->execute([
                    ':filename' => $filename,
                    ':id' => $userId
                ]);
                
                if ($result) {
                    // Delete old avatar if it exists
                    if ($oldAvatar && file_exists($uploadDir . $oldAvatar)) {
                        unlink($uploadDir . $oldAvatar);
                    }
                    
                    return ['success' => true, 'filename' => $filename];
                } else {
                    // Delete uploaded file if database update failed
                    if (file_exists($filepath)) {
                        unlink($filepath);
                    }
                    return ['success' => false, 'error' => 'Failed to update profile photo in database.'];
                }
            } else {
                return ['success' => false, 'error' => 'Failed to save uploaded file.'];
            }
            
        } catch (Exception $e) {
            error_log("Avatar upload error: " . $e->getMessage());
            return ['success' => false, 'error' => 'An error occurred while uploading the avatar.'];
        }
    }
    
    /**
     * Remove user avatar
     */
    public function removeAvatar($userId) {
        try {
            $currentUser = $this->findById($userId);
            $currentAvatar = $currentUser['profile_photo'] ?? null;
            
            if ($currentAvatar) {
                // Delete file
                $uploadDir = dirname(dirname(__FILE__)) . '/uploads/avatars/';
                $filepath = $uploadDir . $currentAvatar;
                if (file_exists($filepath)) {
                    unlink($filepath);
                }
                
                // Update database
                $stmt = $this->db->prepare("UPDATE users SET profile_photo = NULL WHERE id = :id");
                $result = $stmt->execute([':id' => $userId]);
                
                if ($result) {
                    return ['success' => true];
                } else {
                    return ['success' => false, 'error' => 'Failed to update database.'];
                }
            } else {
                return ['success' => false, 'error' => 'No avatar to remove.'];
            }
            
        } catch (Exception $e) {
            error_log("Avatar removal error: " . $e->getMessage());
            return ['success' => false, 'error' => 'An error occurred while removing the avatar.'];
        }
    }
    
    /**
     * Get file extension from MIME type
     */
    private function getFileExtension($mimeType) {
        switch ($mimeType) {
            case 'image/jpeg':
                return 'jpg';
            case 'image/png':
                return 'png';
            case 'image/gif':
                return 'gif';
            case 'image/webp':
                return 'webp';
            default:
                return 'jpg';
        }
    }
    
    /**
     * Delete a user (admin only)
     */
    public function delete($userId, $adminId) {
        // Check if current user is global admin
        if (!$this->isGlobalAdmin($adminId)) {
            return ['success' => false, 'error' => 'Only global administrators can delete users.'];
        }
        
        // Prevent self-deletion
        if ($userId === $adminId) {
            return ['success' => false, 'error' => 'You cannot delete your own account.'];
        }
        
        try {
            $this->db->beginTransaction();
            
            // Delete related records first (to avoid foreign key constraints)
            
            // Delete from global_admins if user is admin
            $stmt = $this->db->prepare("DELETE FROM global_admins WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $userId]);
            
            // Delete from community_members
            $stmt = $this->db->prepare("DELETE FROM community_members WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $userId]);
            
            // Delete from project_members
            $stmt = $this->db->prepare("DELETE FROM project_members WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $userId]);
            
            // Delete from course_enrollments
            $stmt = $this->db->prepare("DELETE FROM course_enrollments WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $userId]);
            
            // Delete from user_settings
            $stmt = $this->db->prepare("DELETE FROM user_settings WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $userId]);
            
            // Delete from survey_responses
            $stmt = $this->db->prepare("DELETE FROM survey_responses WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $userId]);
            
            // Delete from survey_completions
            $stmt = $this->db->prepare("DELETE FROM survey_completions WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $userId]);
            
            // Update tasks to unassign them instead of deleting
            $stmt = $this->db->prepare("UPDATE tasks SET assignee_id = NULL WHERE assignee_id = :user_id");
            $stmt->execute([':user_id' => $userId]);
            
            // Update comments to mark as deleted user instead of deleting
            $stmt = $this->db->prepare("UPDATE comments SET user_id = NULL WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $userId]);
            
            // Finally, delete the user
            $stmt = $this->db->prepare("DELETE FROM users WHERE id = :user_id");
            $result = $stmt->execute([':user_id' => $userId]);
            
            if ($result && $stmt->rowCount() > 0) {
                $this->db->commit();
                return ['success' => true];
            } else {
                $this->db->rollBack();
                return ['success' => false, 'error' => 'User not found or could not be deleted.'];
            }
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("User deletion error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error occurred while deleting user.'];
        }
    }

    /**
     * Resize image to specified dimensions
     */
    private function resizeImage($filepath, $width, $height) {
        try {
            $imageInfo = getimagesize($filepath);
            if (!$imageInfo) {
                return false;
            }
            
            $originalWidth = $imageInfo[0];
            $originalHeight = $imageInfo[1];
            $imageType = $imageInfo[2];
            
            // Calculate dimensions to maintain aspect ratio
            $aspectRatio = $originalWidth / $originalHeight;
            if ($width / $height > $aspectRatio) {
                $width = $height * $aspectRatio;
            } else {
                $height = $width / $aspectRatio;
            }
            
            // Create image resource from file
            switch ($imageType) {
                case IMAGETYPE_JPEG:
                    $source = imagecreatefromjpeg($filepath);
                    break;
                case IMAGETYPE_PNG:
                    $source = imagecreatefrompng($filepath);
                    break;
                case IMAGETYPE_GIF:
                    $source = imagecreatefromgif($filepath);
                    break;
                case IMAGETYPE_WEBP:
                    $source = imagecreatefromwebp($filepath);
                    break;
                default:
                    return false;
            }
            
            if (!$source) {
                return false;
            }
            
            // Create new image with desired dimensions
            $resized = imagecreatetruecolor(200, 200);
            
            // Preserve transparency for PNG and GIF
            if ($imageType == IMAGETYPE_PNG || $imageType == IMAGETYPE_GIF) {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
                imagefill($resized, 0, 0, $transparent);
            }
            
            // Resize image
            imagecopyresampled($resized, $source, 0, 0, 0, 0, 200, 200, $originalWidth, $originalHeight);
            
            // Save resized image
            switch ($imageType) {
                case IMAGETYPE_JPEG:
                    imagejpeg($resized, $filepath, 85);
                    break;
                case IMAGETYPE_PNG:
                    imagepng($resized, $filepath);
                    break;
                case IMAGETYPE_GIF:
                    imagegif($resized, $filepath);
                    break;
                case IMAGETYPE_WEBP:
                    imagewebp($resized, $filepath, 85);
                    break;
            }
            
            // Clean up
            imagedestroy($source);
            imagedestroy($resized);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Image resize error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update user's membership plan
     */
    public function updatePlan($userId, $plan) {
        $validPlans = ['developer', 'learner', 'manager', 'all'];
        
        if (!in_array($plan, $validPlans)) {
            return ['success' => false, 'error' => 'Invalid plan selected'];
        }
        
        try {
            $stmt = $this->db->prepare("UPDATE users SET plan = :plan WHERE id = :id");
            $stmt->execute([
                ':plan' => $plan,
                ':id' => $userId
            ]);
            
            return ['success' => true];
        } catch (PDOException $e) {
            error_log("Plan update error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to update plan'];
        }
    }
}