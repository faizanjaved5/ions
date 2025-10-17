<?php
/**
 * ION Platform - Role-Based Access Control System
 * Centralized permissions management for all sections
 */

// Role definitions and their capabilities
class IONRoles {
    
    // Define all available roles
    const ROLES = [
        'Owner'    => 'Platform Owner - Full Access',
        'Admin'    => 'Administrator - Management Access', 
        'Member'   => 'Member - Standard Access',
        'Creator'  => 'Content Creator - Upload Only',
        'Viewer'   => 'Viewer - Read Only Access'
    ];

    // Define section access permissions
    const SECTION_PERMISSIONS = [
        'IONS' => [
            'access' => ['Owner', 'Admin'],
            'add'    => ['Owner', 'Admin'],
            'edit'   => ['Owner', 'Admin'],
            'delete' => ['Owner']
        ],
        'IONEERS' => [
            'access' => ['Owner', 'Admin'],
            'add'    => ['Owner', 'Admin'],
            'edit'   => ['Owner', 'Admin'],
            'delete' => ['Owner']
        ],
        'ION_VIDS' => [
            'access' => ['Owner', 'Admin', 'Member', 'Creator', 'Viewer'],
            'upload' => ['Owner', 'Admin', 'Member', 'Creator'],
            'edit'   => ['Owner', 'Admin'],
            'delete' => ['Owner', 'Admin']
        ],
        'IONSIGHTS' => [
            'access' => ['Owner', 'Admin'],
            'view'   => ['Owner', 'Admin']
        ]
    ];

    // Feature-based permissions
    const FEATURE_PERMISSIONS = [
        'user_management' => ['Owner', 'Admin'],
        'system_settings' => ['Owner'],
        'bulk_operations' => ['Owner', 'Admin'],
        'export_data'     => ['Owner', 'Admin'],
        'view_analytics'  => ['Owner', 'Admin'],
        'moderate_content'=> ['Owner', 'Admin']
    ];

    /**
     * Check if user has access to a section
     */
    public static function canAccessSection($userRole, $section) {
        if (!$userRole || !isset(self::SECTION_PERMISSIONS[$section])) {
            return false;
        }
        
        return in_array($userRole, self::SECTION_PERMISSIONS[$section]['access']);
    }

    /**
     * Check if user can perform an action in a section
     */
    public static function canPerformAction($userRole, $section, $action) {
        if (!$userRole || !isset(self::SECTION_PERMISSIONS[$section][$action])) {
            return false;
        }
        
        return in_array($userRole, self::SECTION_PERMISSIONS[$section][$action]);
    }

    /**
     * Check if user has a specific feature permission
     */
    public static function hasFeaturePermission($userRole, $feature) {
        if (!$userRole || !isset(self::FEATURE_PERMISSIONS[$feature])) {
            return false;
        }
        
        return in_array($userRole, self::FEATURE_PERMISSIONS[$feature]);
    }

    /**
     * Get all sections accessible to a user role
     */
    public static function getAccessibleSections($userRole) {
        $accessible = [];
        
        foreach (self::SECTION_PERMISSIONS as $section => $permissions) {
            if (self::canAccessSection($userRole, $section)) {
                $accessible[] = $section;
            }
        }
        
        return $accessible;
    }

    /**
     * Validate if a role exists
     */
    public static function isValidRole($role) {
        return array_key_exists($role, self::ROLES);
    }

    /**
     * Get role description
     */
    public static function getRoleDescription($role) {
        return self::ROLES[$role] ?? 'Unknown Role';
    }

    /**
     * Redirect unauthorized users
     */
    public static function requireAccess($userRole, $section, $redirectUrl = '/login/') {
        if (!self::canAccessSection($userRole, $section)) {
            error_log("Access denied: User role '$userRole' attempted to access '$section'");
            header("Location: $redirectUrl");
            exit();
        }
    }

    /**
     * Check if user can see navigation item
     */
    public static function showNavigation($userRole, $section) {
        return self::canAccessSection($userRole, $section);
    }
}

/**
 * Helper functions for backward compatibility
 */

function canAccessSection($userRole, $section) {
    return IONRoles::canAccessSection($userRole, $section);
}

function canAdd($userRole, $section) {
    return IONRoles::canPerformAction($userRole, $section, 'add');
}

function canEdit($userRole, $section) {
    return IONRoles::canPerformAction($userRole, $section, 'edit');
}

function canDelete($userRole, $section) {
    return IONRoles::canPerformAction($userRole, $section, 'delete');
}

function requireSectionAccess($userRole, $section) {
    IONRoles::requireAccess($userRole, $section);
}

/**
 * Auto-include this file to make roles available globally
 */
if (!function_exists('getUserRole')) {
    function getUserRole() {
        return $_SESSION['user_role'] ?? 'Viewer';
    }
}

// Log role system initialization
error_log("ION Roles system initialized");
?>