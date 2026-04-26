<?php
/**
 * Authentication and RBAC (Role-Based Access Control) System
 * Handles user login, session management, and role-based permissions
 */

require_once 'Database.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Login user with username/email and password
     */
    public function login($username, $password) {
        try {
            // Allow login with username or email
            $sql = "SELECT id, username, email, password_hash, role, first_name, last_name, is_active 
                    FROM users 
                    WHERE (username = :username OR email = :email) AND is_active = 1";
            
            $user = $this->db->fetch($sql, ['username' => $username, 'email' => $username]);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Update last login
                $this->updateLastLogin($user['id']);
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['logged_in'] = true;
                
                // Log the activity
                $this->logActivity($user['id'], 'login', 'users', $user['id']);
                
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Logout user and destroy session
     */
    public function logout() {
        if ($this->isLoggedIn()) {
            // Log the activity before destroying session
            $this->logActivity($_SESSION['user_id'], 'logout', 'users', $_SESSION['user_id']);
        }
        
        // Destroy all session data
        session_destroy();
        
        // Clear session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * Get current user role
     */
    public function getUserRole() {
        return $_SESSION['role'] ?? null;
    }
    
    /**
     * Get current user ID
     */
    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Check if current user has specific role
     */
    public function hasRole($role) {
        return $this->isLoggedIn() && $this->getUserRole() === $role;
    }
    
    /**
     * Check if current user has permission (based on role hierarchy)
     */
    public function hasPermission($permission) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        $role = $this->getUserRole();
        
        // Define role permissions
        $permissions = [
            'student' => ['view_own_grades', 'view_own_attendance', 'view_own_profile'],
            'teacher' => ['view_student_grades', 'edit_grades', 'mark_attendance', 'view_course_analytics', 'generate_reports'],
            'admin' => ['manage_users', 'manage_courses', 'manage_departments', 'view_system_logs', 'backup_database', 'manage_financial']
        ];
        
        return in_array($permission, $permissions[$role] ?? []);
    }
    
    /**
     * Require authentication - redirect if not logged in
     */
    public function requireAuth() {
        if (!$this->isLoggedIn()) {
            header("Location: login.php");
            exit;
        }
    }
    
    /**
     * Require specific role - redirect if user doesn't have role
     */
    public function requireRole($role) {
        $this->requireAuth();
        
        if (!$this->hasRole($role)) {
            // Redirect to appropriate dashboard based on user's current role
            $this->redirectToDashboard();
            exit;
        }
    }
    
    /**
     * Require specific permission - redirect if user doesn't have permission
     */
    public function requirePermission($permission) {
        $this->requireAuth();
        
        if (!$this->hasPermission($permission)) {
            $_SESSION['error'] = "You don't have permission to access this resource.";
            $this->redirectToDashboard();
            exit;
        }
    }
    
    /**
     * Redirect user to appropriate dashboard based on role
     */
    public function redirectToDashboard() {
        $role = $this->getUserRole();
        
        switch ($role) {
            case 'student':
                header("Location: student/dashboard.php");
                break;
            case 'teacher':
                header("Location: teacher/dashboard.php");
                break;
            case 'admin':
                header("Location: admin/dashboard.php");
                break;
            default:
                header("Location: login.php");
        }
        exit;
    }
    
    /**
     * Update user's last login timestamp
     */
    private function updateLastLogin($userId) {
        $sql = "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = :id";
        $this->db->update($sql, ['id' => $userId]);
    }
    
    /**
     * Log user activity
     */
    public function logActivity($userId, $action, $tableName = null, $recordId = null, $oldValues = null, $newValues = null) {
        try {
            $sql = "INSERT INTO activity_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) 
                    VALUES (:user_id, :action, :table_name, :record_id, :old_values, :new_values, :ip_address, :user_agent)";
            
            $params = [
                'user_id' => $userId,
                'action' => $action,
                'table_name' => $tableName,
                'record_id' => $recordId,
                'old_values' => $oldValues ? json_encode($oldValues) : null,
                'new_values' => $newValues ? json_encode($newValues) : null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ];
            
            $this->db->insert($sql, $params);
        } catch (Exception $e) {
            error_log("Activity logging error: " . $e->getMessage());
        }
    }
    
    /**
     * Get user profile information
     */
    public function getUserProfile($userId) {
        $sql = "SELECT id, username, email, role, first_name, last_name, phone, profile_photo, 
                created_at, last_login 
                FROM users 
                WHERE id = :id";
        
        return $this->db->fetch($sql, ['id' => $userId]);
    }
    
    /**
     * Update user profile
     */
    public function updateProfile($userId, $data) {
        try {
            $this->db->beginTransaction();
            
            // Get old values for logging
            $oldData = $this->getUserProfile($userId);
            
            $sql = "UPDATE users SET 
                    first_name = :first_name, 
                    last_name = :last_name, 
                    phone = :phone, 
                    updated_at = CURRENT_TIMESTAMP";
            
            $params = [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? null,
                'id' => $userId
            ];
            
            // Add email update if provided
            if (isset($data['email'])) {
                $sql .= ", email = :email";
                $params['email'] = $data['email'];
            }
            
            $sql .= " WHERE id = :id";
            
            $result = $this->db->update($sql, $params);
            
            // Log the activity
            $this->logActivity($userId, 'update_profile', 'users', $userId, $oldData, $data);
            
            $this->db->commit();
            return $result > 0;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Profile update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Change user password
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            // Verify current password
            $sql = "SELECT password_hash FROM users WHERE id = :id";
            $user = $this->db->fetch($sql, ['id' => $userId]);
            
            if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
                return false;
            }
            
            // Update password
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET password_hash = :password_hash, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
            $result = $this->db->update($sql, ['password_hash' => $newHash, 'id' => $userId]);
            
            if ($result > 0) {
                $this->logActivity($userId, 'change_password', 'users', $userId);
            }
            
            return $result > 0;
            
        } catch (Exception $e) {
            error_log("Password change error: " . $e->getMessage());
            return false;
        }
    }
}
?>
