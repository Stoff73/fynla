<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

class PermissionService
{
    /**
     * Check if a user has a specific role
     */
    public function hasRole(User $user, string $roleName): bool
    {
        return $user->role?->name === $roleName;
    }

    /**
     * Check if a user has any of the given roles
     */
    public function hasAnyRole(User $user, array $roleNames): bool
    {
        return in_array($user->role?->name, $roleNames, true);
    }

    /**
     * Check if a user has a specific permission
     */
    public function hasPermission(User $user, string $permissionName): bool
    {
        return $user->role?->hasPermission($permissionName) ?? false;
    }

    /**
     * Check if a user has any of the given permissions
     */
    public function hasAnyPermission(User $user, array $permissions): bool
    {
        return $user->role?->hasAnyPermission($permissions) ?? false;
    }

    /**
     * Check if a user has all of the given permissions
     */
    public function hasAllPermissions(User $user, array $permissions): bool
    {
        return $user->role?->hasAllPermissions($permissions) ?? false;
    }

    /**
     * Check if a user's role is at least the given level
     */
    public function isAtLeastLevel(User $user, int $level): bool
    {
        return $user->role?->isAtLeast($level) ?? false;
    }

    /**
     * Check if user is admin via RBAC role or legacy is_admin boolean.
     *
     * Unified check: returns true if EITHER the is_admin boolean is set
     * OR the user has the RBAC admin role. This ensures consistent
     * admin determination across all middleware and service checks.
     */
    public function isAdmin(User $user): bool
    {
        return $user->is_admin || $this->hasRole($user, Role::ROLE_ADMIN);
    }

    /**
     * Check if user is an advisor via is_advisor boolean flag.
     */
    public function isAdvisor(User $user): bool
    {
        return (bool) $user->is_advisor;
    }

    /**
     * Check if user is at least support level
     */
    public function isSupport(User $user): bool
    {
        return $this->isAdmin($user) || $this->hasRole($user, Role::ROLE_SUPPORT);
    }

    /**
     * Assign a role to a user
     */
    public function assignRole(User $user, string $roleName): void
    {
        $role = Role::findByName($roleName);

        if ($role) {
            $user->role_id = $role->id;
            $user->save();
        }
    }

    /**
     * Remove role from a user
     */
    public function removeRole(User $user): void
    {
        $user->role_id = null;
        $user->save();
    }

    /**
     * Get all permissions for a user based on their role
     */
    public function getUserPermissions(User $user): array
    {
        return $user->role?->permissions?->pluck('name')->toArray() ?? [];
    }

    /**
     * Sync default roles and permissions
     */
    public function syncDefaultRolesAndPermissions(): void
    {
        // Create default roles
        $userRole = Role::firstOrCreate(
            ['name' => Role::ROLE_USER],
            [
                'display_name' => 'User',
                'description' => 'Regular user with access to their own data',
                'level' => Role::LEVEL_USER,
            ]
        );

        $supportRole = Role::firstOrCreate(
            ['name' => Role::ROLE_SUPPORT],
            [
                'display_name' => 'Support',
                'description' => 'Support staff with limited admin capabilities',
                'level' => Role::LEVEL_SUPPORT,
            ]
        );

        $adminRole = Role::firstOrCreate(
            ['name' => Role::ROLE_ADMIN],
            [
                'display_name' => 'Administrator',
                'description' => 'Full system administrator',
                'level' => Role::LEVEL_ADMIN,
            ]
        );

        $advisorRole = Role::firstOrCreate(
            ['name' => Role::ROLE_ADVISOR],
            [
                'display_name' => 'Advisor',
                'description' => 'Financial advisor with client management access',
                'level' => Role::LEVEL_ADVISOR,
            ]
        );

        // Create default permissions
        $permissions = [
            // User permissions
            [Permission::USERS_VIEW, 'View Users', Permission::CATEGORY_USERS],
            [Permission::USERS_EDIT, 'Edit Users', Permission::CATEGORY_USERS],
            [Permission::USERS_DELETE, 'Delete Users', Permission::CATEGORY_USERS],

            // Admin permissions
            [Permission::ADMIN_ACCESS, 'Access Admin Panel', Permission::CATEGORY_ADMIN],
            [Permission::ADMIN_AUDIT_VIEW, 'View Audit Logs', Permission::CATEGORY_ADMIN],
            [Permission::ADMIN_TAX_CONFIG, 'Manage Tax Configuration', Permission::CATEGORY_ADMIN],
            [Permission::ADMIN_ERASURE_PROCESS, 'Process Erasure Requests', Permission::CATEGORY_ADMIN],
            [Permission::ADMIN_BACKUP, 'Manage Database Backups', Permission::CATEGORY_ADMIN],

            // Advisor permissions
            [Permission::ADVISOR_ACCESS, 'Access Advisor Dashboard', Permission::CATEGORY_ADMIN],

            // Settings permissions
            [Permission::SETTINGS_VIEW, 'View Settings', Permission::CATEGORY_SETTINGS],
            [Permission::SETTINGS_EDIT, 'Edit Settings', Permission::CATEGORY_SETTINGS],
        ];

        foreach ($permissions as [$name, $displayName, $category]) {
            Permission::findOrCreateByName($name, $displayName, $category);
        }

        // Assign permissions to roles

        // Support role permissions
        $supportPermissions = [
            Permission::USERS_VIEW,
            Permission::ADMIN_ACCESS,
            Permission::ADMIN_AUDIT_VIEW,
        ];

        $supportRole->syncPermissions(
            Permission::whereIn('name', $supportPermissions)->pluck('id')->toArray()
        );

        // Advisor role permissions
        $advisorPermissions = [
            Permission::ADVISOR_ACCESS,
            Permission::USERS_VIEW,
        ];

        $advisorRole->syncPermissions(
            Permission::whereIn('name', $advisorPermissions)->pluck('id')->toArray()
        );

        // Admin role gets all permissions
        $adminRole->syncPermissions(Permission::all()->pluck('id')->toArray());
    }
}
