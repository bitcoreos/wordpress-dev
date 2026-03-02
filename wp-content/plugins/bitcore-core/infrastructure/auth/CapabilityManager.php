<?php
/**
 * CapabilityManager implementation
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     hyperion
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Capability Manager
 *
 * Provides role-based access control and permission management
 * for the BITCORE plugin with multi-tenancy support.
 *
 * @package BITCORE
 * @subpackage Infrastructure\Auth
 * @since 1.0.0
 */

namespace BITCORE\Infrastructure\Auth;

use WP_User;

/**
 * Capability Manager
 *
 * Manages roles, capabilities, and permission checks for users.
 * Implements role-based access control (RBAC) with tenant awareness.
 *
 * @since 1.0.0
 */
class CapabilityManager {

    /**
     * Role definitions storage
     *
     * @var array<string, array<string, mixed>>
     * @since 1.0.0
     */
    private array $roleDefinitions = [];

    /**
     * Tenant context
     *
     * @var TenantContext|null
     * @since 1.0.0
     */
    private ?TenantContext $tenantContext = null;

    /**
     * User meta key for role storage
     *
     * @var string
     * @since 1.0.0
     */
    private const USER_ROLES_META_KEY = 'bitcore_user_roles';

    /**
     * User meta key for tenant-specific role storage
     *
     * @var string
     * @since 1.0.0
     */
    private const USER_TENANT_ROLES_META_KEY = 'bitcore_tenant_roles';

    /**
     * Constructor
     *
     * @param TenantContext|null $tenantContext Optional tenant context
     * @since 1.0.0
     */
    public function __construct(?TenantContext $tenantContext = null) {
        $this->tenantContext = $tenantContext;
    }

    /**
     * Register a new role
     *
     * Creates a role with defined capabilities and optional inheritance.
     *
     * @param string $role Role identifier
     * @param array $capabilities Array of capability strings
     * @param string|null $inherits Parent role to inherit from
     * @since 1.0.0
     */
    public function registerRole(string $role, array $capabilities, ?string $inherits = null): void {
        $this->roleDefinitions[$role] = [
            'capabilities' => array_unique($capabilities),
            'inherits' => $inherits,
            'role' => $role,
        ];
    }

    /**
     * Add capability to a role
     *
     * @param string $role Role identifier
     * @param string $capability Capability to add
     * @since 1.0.0
     */
    public function addCapability(string $role, string $capability): void {
        if (!isset($this->roleDefinitions[$role])) {
            return;
        }

        if (!in_array($capability, $this->roleDefinitions[$role]['capabilities'], true)) {
            $this->roleDefinitions[$role]['capabilities'][] = $capability;
        }
    }

    /**
     * Remove capability from a role
     *
     * @param string $role Role identifier
     * @param string $capability Capability to remove
     * @since 1.0.0
     */
    public function removeCapability(string $role, string $capability): void {
        if (!isset($this->roleDefinitions[$role])) {
            return;
        }

        $this->roleDefinitions[$role]['capabilities'] = array_values(
            array_diff(
                $this->roleDefinitions[$role]['capabilities'],
                [$capability]
            )
        );
    }

    /**
     * Get all capabilities for a role
     *
     * Returns inherited capabilities as well.
     *
     * @param string $role Role identifier
     * @return array Array of capability strings
     * @since 1.0.0
     */
    public function getRoleCapabilities(string $role): array {
        if (!isset($this->roleDefinitions[$role])) {
            return [];
        }

        $capabilities = $this->roleDefinitions[$role]['capabilities'];
        $inherits = $this->roleDefinitions[$role]['inherits'] ?? null;

        // Recursively merge inherited capabilities
        if ($inherits !== null && isset($this->roleDefinitions[$inherits])) {
            $parentCapabilities = $this->getRoleCapabilities($inherits);
            $capabilities = array_merge($parentCapabilities, $capabilities);
        }

        return array_unique($capabilities);
    }

    /**
     * Check if user has specific capability
     *
     * @param int $userId User ID
     * @param string $capability Capability to check
     * @param array|null $context Optional context for conditional checks
     * @return bool True if user has capability
     * @since 1.0.0
     */
    public function userCan(int $userId, string $capability, ?array $context = null): bool {
        // Check WordPress native capability
        if ($this->checkWordPressCapability($userId, $capability)) {
            return true;
        }

        // Check custom role-based capabilities
        $effectiveCapabilities = $this->getEffectiveCapabilities($userId);

        if (in_array($capability, $effectiveCapabilities, true)) {
            return true;
        }

        // Context-based checks (extensible for future use)
        if ($context !== null) {
            return $this->evaluateContextualPermission($userId, $capability, $context);
        }

        return false;
    }

    /**
     * Check if user has any of the specified capabilities
     *
     * @param int $userId User ID
     * @param array $capabilities Array of capabilities to check
     * @return bool True if user has at least one capability
     * @since 1.0.0
     */
    public function userCanAny(int $userId, array $capabilities): bool {
        foreach ($capabilities as $capability) {
            if ($this->userCan($userId, $capability)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user has all specified capabilities
     *
     * @param int $userId User ID
     * @param array $capabilities Array of capabilities to check
     * @return bool True if user has all capabilities
     * @since 1.0.0
     */
    public function userCanAll(int $userId, array $capabilities): bool {
        foreach ($capabilities as $capability) {
            if (!$this->userCan($userId, $capability)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get effective capabilities for a user
     *
     * Returns all capabilities the user has across all assigned roles.
     *
     * @param int $userId User ID
     * @param int|null $tenantId Optional tenant ID for tenant-specific roles
     * @return array Array of capability strings
     * @since 1.0.0
     */
    public function getEffectiveCapabilities(int $userId, ?int $tenantId = null): array {
        $allCapabilities = [];
        $userRoles = $this->getUserRoles($userId, $tenantId);

        foreach ($userRoles as $role) {
            $roleCaps = $this->getRoleCapabilities($role);
            $allCapabilities = array_merge($allCapabilities, $roleCaps);
        }

        // Remove duplicates and reindex
        return array_values(array_unique($allCapabilities));
    }

    /**
     * Assign a role to a user
     *
     * @param int $userId User ID
     * @param string $role Role to assign
     * @param int|null $tenantId Optional tenant ID for tenant-specific assignment
     * @since 1.0.0
     */
    public function assignRole(int $userId, string $role, ?int $tenantId = null): void {
        if ($tenantId !== null) {
            $this->assignTenantRole($userId, $role, $tenantId);
            return;
        }

        $userRoles = $this->getUserRoles($userId);
        if (!in_array($role, $userRoles, true)) {
            $userRoles[] = $role;
            update_user_meta($userId, self::USER_ROLES_META_KEY, $userRoles);
        }
    }

    /**
     * Revoke a role from a user
     *
     * @param int $userId User ID
     * @param string $role Role to revoke
     * @param int|null $tenantId Optional tenant ID for tenant-specific revocation
     * @since 1.0.0
     */
    public function revokeRole(int $userId, string $role, ?int $tenantId = null): void {
        if ($tenantId !== null) {
            $this->revokeTenantRole($userId, $role, $tenantId);
            return;
        }

        $userRoles = $this->getUserRoles($userId);
        $userRoles = array_values(array_diff($userRoles, [$role]));
        update_user_meta($userId, self::USER_ROLES_META_KEY, $userRoles);
    }

    /**
     * Check resource-level access
     *
     * Verifies user has permission to perform action on specific resource.
     *
     * @param int $userId User ID
     * @param string $resource Resource type (e.g., 'post', 'user', 'settings')
     * @param string $action Action to perform (e.g., 'read', 'write', 'delete')
     * @param mixed $resourceId Specific resource identifier
     * @return bool True if access granted
     * @since 1.0.0
     */
    public function checkResourceAccess(int $userId, string $resource, string $action, mixed $resourceId): bool {
        // Build resource capability: {action}_{resource}
        $capability = $action . '_' . $resource;

        // Check basic capability
        if (!$this->userCan($userId, $capability)) {
            return false;
        }

        // Resource ownership check (extensible pattern)
        if ($resource === 'post') {
            $post = get_post($resourceId);
            if ($post && $post->post_author == $userId) {
                // Owner always has access to own resources
                return true;
            }
        }

        // Additional resource-specific checks can be added here

        return true;
    }

    /**
     * Check WordPress native capability
     *
     * @param int $userId User ID
     * @param string $capability Capability to check
     * @return bool True if WordPress user has capability
     * @since 1.0.0
     */
    private function checkWordPressCapability(int $userId, string $capability): bool {
        $user = get_userdata($userId);
        if (!$user instanceof WP_User) {
            return false;
        }

        // Temporarily switch current user for proper capability checking
        $currentUser = wp_get_current_user();
        wp_set_current_user($userId);
        $hasCap = current_user_can($capability);
        wp_set_current_user($currentUser->ID);

        return $hasCap;
    }

    /**
     * Get user roles from meta
     *
     * @param int $userId User ID
     * @param int|null $tenantId Optional tenant filter
     * @return array Array of role strings
     * @since 1.0.0
     */
    private function getUserRoles(int $userId, ?int $tenantId = null): array {
        if ($tenantId !== null) {
            return $this->getTenantSpecificRoles($userId, $tenantId);
        }

        $roles = get_user_meta($userId, self::USER_ROLES_META_KEY, true);
        return is_array($roles) ? $roles : [];
    }

    /**
     * Get tenant-specific roles
     *
     * @param int $userId User ID
     * @param int $tenantId Tenant ID
     * @return array Array of role strings
     * @since 1.0.0
     */
    private function getTenantSpecificRoles(int $userId, int $tenantId): array {
        $tenantRoles = get_user_meta($userId, self::USER_TENANT_ROLES_META_KEY, true);
        
        if (!is_array($tenantRoles)) {
            return [];
        }

        return $tenantRoles[$tenantId] ?? [];
    }

    /**
     * Assign role to user for specific tenant
     *
     * @param int $userId User ID
     * @param string $role Role to assign
     * @param int $tenantId Tenant ID
     * @since 1.0.0
     */
    private function assignTenantRole(int $userId, string $role, int $tenantId): void {
        $tenantRoles = get_user_meta($userId, self::USER_TENANT_ROLES_META_KEY, true);
        
        if (!is_array($tenantRoles)) {
            $tenantRoles = [];
        }

        if (!isset($tenantRoles[$tenantId])) {
            $tenantRoles[$tenantId] = [];
        }

        if (!in_array($role, $tenantRoles[$tenantId], true)) {
            $tenantRoles[$tenantId][] = $role;
            update_user_meta($userId, self::USER_TENANT_ROLES_META_KEY, $tenantRoles);
        }
    }

    /**
     * Revoke role from user for specific tenant
     *
     * @param int $userId User ID
     * @param string $role Role to revoke
     * @param int $tenantId Tenant ID
     * @since 1.0.0
     */
    private function revokeTenantRole(int $userId, string $role, int $tenantId): void {
        $tenantRoles = get_user_meta($userId, self::USER_TENANT_ROLES_META_KEY, true);
        
        if (!is_array($tenantRoles) || !isset($tenantRoles[$tenantId])) {
            return;
        }

        $tenantRoles[$tenantId] = array_values(
            array_diff($tenantRoles[$tenantId], [$role])
        );

        update_user_meta($userId, self::USER_TENANT_ROLES_META_KEY, $tenantRoles);
    }

    /**
     * Evaluate contextual permission
     *
     * Placeholder for future contextual permission logic.
     *
     * @param int $userId User ID
     * @param string $capability Capability to check
     * @param array $context Contextual data
     * @return bool Result of evaluation
     * @since 1.0.0
     */
    private function evaluateContextualPermission(int $userId, string $capability, array $context): bool {
        // Extensible hook for complex permission logic
        // Can be overridden or extended via filters
        return false;
    }
}

/*
 * Test Scaffold:
 * TODO: Implement unit tests
 * 
 * Role Management Tests:
 * - Test registerRole() creates role with capabilities
 * - Test registerRole() with inheritance chains
 * - Test addCapability() adds to existing role
 * - Test removeCapability() removes from role
 * - Test getRoleCapabilities() returns inherited caps
 * 
 * Permission Checking Tests:
 * - Test userCan() with WordPress native caps
 * - Test userCan() with custom role caps
 * - Test userCan() with context parameter
 * - Test userCanAny() returns true if any match
 * - Test userCanAll() returns true if all match
 * 
 * Tenant-Aware Tests:
 * - Test getEffectiveCapabilities() without tenant
 * - Test getEffectiveCapabilities() with tenant
 * - Test assignRole() with global scope
 * - Test assignRole() with tenant scope
 * - Test revokeRole() removes from correct scope
 * 
 * Resource Access Tests:
 * - Test checkResourceAccess() for owned resources
 * - Test checkResourceAccess() for non-owned resources
 * - Test checkResourceAccess() with custom resource types
 * 
 * Edge Cases:
 * - Test with non-existent user ID
 * - Test with empty role definitions
 * - Test capability inheritance loops
 * - Test concurrent tenant role assignments
 */
