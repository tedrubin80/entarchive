-- RBAC (Role-Based Access Control) Database Schema
-- For next session implementation

-- User Roles Table
CREATE TABLE user_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    priority_level INT NOT NULL DEFAULT 0, -- Higher number = more permissions
    is_system_role BOOLEAN DEFAULT FALSE, -- Cannot be deleted
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_priority_level (priority_level),
    INDEX idx_name (name)
);

-- Permissions Table
CREATE TABLE permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(150) NOT NULL,
    description TEXT,
    category VARCHAR(50) NOT NULL, -- e.g., 'collection', 'user_management', 'system'
    resource VARCHAR(50), -- What the permission applies to
    action VARCHAR(50), -- What action is allowed (create, read, update, delete, execute)
    is_system_permission BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_category (category),
    INDEX idx_resource_action (resource, action),
    INDEX idx_name (name)
);

-- Role-Permission Mapping
CREATE TABLE role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    granted_by INT, -- User ID who granted this permission
    
    FOREIGN KEY (role_id) REFERENCES user_roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL,
    
    UNIQUE KEY unique_role_permission (role_id, permission_id),
    INDEX idx_role_id (role_id),
    INDEX idx_permission_id (permission_id)
);

-- Update Users Table
ALTER TABLE users ADD COLUMN role_id INT NOT NULL DEFAULT 4 AFTER password_hash; -- Default to 'standard_user'
ALTER TABLE users ADD COLUMN is_active BOOLEAN DEFAULT TRUE AFTER role_id;
ALTER TABLE users ADD COLUMN last_login_at TIMESTAMP NULL AFTER is_active;
ALTER TABLE users ADD COLUMN login_attempts INT DEFAULT 0 AFTER last_login_at;
ALTER TABLE users ADD COLUMN locked_until TIMESTAMP NULL AFTER login_attempts;
ALTER TABLE users ADD COLUMN created_by INT NULL AFTER locked_until;
ALTER TABLE users ADD COLUMN approved_at TIMESTAMP NULL AFTER created_by;

-- Add foreign key constraints to users table
ALTER TABLE users ADD FOREIGN KEY (role_id) REFERENCES user_roles(id) ON DELETE RESTRICT;
ALTER TABLE users ADD FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL;

-- User Sessions Enhanced
ALTER TABLE user_sessions ADD COLUMN role_id INT AFTER user_id;
ALTER TABLE user_sessions ADD COLUMN permissions_cache JSON AFTER role_id;
ALTER TABLE user_sessions ADD COLUMN last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER permissions_cache;

-- Collection Access Control
CREATE TABLE collection_sharing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    collection_owner_id INT NOT NULL,
    shared_with_user_id INT,
    shared_with_role_id INT,
    permission_level ENUM('read', 'write', 'admin') DEFAULT 'read',
    is_public BOOLEAN DEFAULT FALSE,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    
    FOREIGN KEY (collection_owner_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_with_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_with_role_id) REFERENCES user_roles(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_owner (collection_owner_id),
    INDEX idx_shared_user (shared_with_user_id),
    INDEX idx_shared_role (shared_with_role_id),
    INDEX idx_public (is_public)
);

-- Audit Log for Security
CREATE TABLE security_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action_type ENUM(
        'login_success', 'login_failed', 'logout', 'password_change', 
        'role_change', 'permission_grant', 'permission_revoke',
        'user_create', 'user_delete', 'user_activate', 'user_deactivate',
        'collection_access', 'system_setting_change', 'data_export',
        'totp_setup', 'totp_disable', 'backup_code_used'
    ) NOT NULL,
    resource_type VARCHAR(50), -- 'user', 'collection', 'system', etc.
    resource_id INT, -- ID of the affected resource
    details JSON, -- Additional context about the action
    ip_address VARCHAR(45),
    user_agent TEXT,
    session_id VARCHAR(128),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_user_id (user_id),
    INDEX idx_action_type (action_type),
    INDEX idx_created_at (created_at),
    INDEX idx_resource (resource_type, resource_id)
);

-- System Settings with Role-Based Access
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    category VARCHAR(50) NOT NULL,
    description TEXT,
    required_role_level INT DEFAULT 1, -- Minimum role level to modify
    is_public BOOLEAN DEFAULT FALSE, -- Can be viewed by all users
    last_modified_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (last_modified_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_category (category),
    INDEX idx_required_role (required_role_level),
    INDEX idx_setting_key (setting_key)
);

-- Feature Flags with Role-Based Control
CREATE TABLE feature_flags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    flag_name VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(150) NOT NULL,
    description TEXT,
    is_enabled BOOLEAN DEFAULT FALSE,
    required_role_level INT DEFAULT 4, -- Standard user by default
    target_percentage INT DEFAULT 100, -- For gradual rollouts
    conditions JSON, -- Additional conditions for flag activation
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_flag_name (flag_name),
    INDEX idx_is_enabled (is_enabled),
    INDEX idx_required_role (required_role_level)
);

-- Insert Default Roles
INSERT INTO user_roles (name, display_name, description, priority_level, is_system_role) VALUES
('super_admin', 'Super Administrator', 'Full system access with all permissions', 1, TRUE),
('admin', 'Administrator', 'Administrative access with user management', 2, TRUE),
('power_user', 'Power User', 'Advanced features and API access', 3, TRUE),
('standard_user', 'Standard User', 'Basic collection management', 4, TRUE),
('read_only', 'Read-Only User', 'View-only access to collections', 5, TRUE),
('guest', 'Guest User', 'Limited public access', 6, TRUE);

-- Insert Core Permissions
INSERT INTO permissions (name, display_name, description, category, resource, action, is_system_permission) VALUES
-- Collection Management
('collection.create', 'Create Collections', 'Create new collection items', 'collection', 'collection', 'create', TRUE),
('collection.read', 'View Collections', 'View collection items', 'collection', 'collection', 'read', TRUE),
('collection.update', 'Edit Collections', 'Modify collection items', 'collection', 'collection', 'update', TRUE),
('collection.delete', 'Delete Collections', 'Remove collection items', 'collection', 'collection', 'delete', TRUE),
('collection.import', 'Import Data', 'Import collection data from files', 'collection', 'collection', 'import', TRUE),
('collection.export', 'Export Data', 'Export collection data', 'collection', 'collection', 'export', TRUE),

-- User Management
('user.create', 'Create Users', 'Create new user accounts', 'user_management', 'user', 'create', TRUE),
('user.read', 'View Users', 'View user information', 'user_management', 'user', 'read', TRUE),
('user.update', 'Edit Users', 'Modify user accounts', 'user_management', 'user', 'update', TRUE),
('user.delete', 'Delete Users', 'Remove user accounts', 'user_management', 'user', 'delete', TRUE),
('user.manage_roles', 'Manage User Roles', 'Assign and modify user roles', 'user_management', 'user', 'manage_roles', TRUE),

-- System Administration
('system.settings', 'System Settings', 'Access system configuration', 'system', 'system', 'configure', TRUE),
('system.database', 'Database Access', 'Direct database management', 'system', 'database', 'admin', TRUE),
('system.logs', 'View System Logs', 