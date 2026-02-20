<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\PermissionService;

beforeEach(function () {
    $this->permissionService = new PermissionService;

    // Create roles
    $this->userRole = Role::firstOrCreate(
        ['name' => Role::ROLE_USER],
        ['display_name' => 'User', 'level' => Role::LEVEL_USER]
    );

    $this->supportRole = Role::firstOrCreate(
        ['name' => Role::ROLE_SUPPORT],
        ['display_name' => 'Support', 'level' => Role::LEVEL_SUPPORT]
    );

    $this->adminRole = Role::firstOrCreate(
        ['name' => Role::ROLE_ADMIN],
        ['display_name' => 'Administrator', 'level' => Role::LEVEL_ADMIN]
    );

    // Create permissions
    $this->viewUsersPermission = Permission::findOrCreateByName(
        Permission::USERS_VIEW,
        'View Users',
        Permission::CATEGORY_USERS
    );

    $this->editUsersPermission = Permission::findOrCreateByName(
        Permission::USERS_EDIT,
        'Edit Users',
        Permission::CATEGORY_USERS
    );

    // Assign permissions to support role
    $this->supportRole->syncPermissions([$this->viewUsersPermission->id]);

    // Assign all permissions to admin role
    $this->adminRole->syncPermissions([
        $this->viewUsersPermission->id,
        $this->editUsersPermission->id,
    ]);
});

describe('hasRole', function () {
    it('returns true when user has the role', function () {
        $user = User::factory()->create(['role_id' => $this->userRole->id]);

        expect($this->permissionService->hasRole($user, Role::ROLE_USER))->toBeTrue();
    });

    it('returns false when user has a different role', function () {
        $user = User::factory()->create(['role_id' => $this->userRole->id]);

        expect($this->permissionService->hasRole($user, Role::ROLE_ADMIN))->toBeFalse();
    });

    it('returns false when user has no role', function () {
        $user = User::factory()->create(['role_id' => null]);

        expect($this->permissionService->hasRole($user, Role::ROLE_USER))->toBeFalse();
    });
});

describe('hasAnyRole', function () {
    it('returns true when user has one of the roles', function () {
        $user = User::factory()->create(['role_id' => $this->supportRole->id]);

        expect($this->permissionService->hasAnyRole($user, [Role::ROLE_USER, Role::ROLE_SUPPORT]))->toBeTrue();
    });

    it('returns false when user has none of the roles', function () {
        $user = User::factory()->create(['role_id' => $this->userRole->id]);

        expect($this->permissionService->hasAnyRole($user, [Role::ROLE_SUPPORT, Role::ROLE_ADMIN]))->toBeFalse();
    });
});

describe('hasPermission', function () {
    it('returns true for admin role regardless of specific permission', function () {
        $user = User::factory()->create(['role_id' => $this->adminRole->id]);

        expect($this->permissionService->hasPermission($user, Permission::USERS_EDIT))->toBeTrue();
    });

    it('returns true when role has the permission', function () {
        $user = User::factory()->create(['role_id' => $this->supportRole->id]);

        expect($this->permissionService->hasPermission($user, Permission::USERS_VIEW))->toBeTrue();
    });

    it('returns false when role lacks the permission', function () {
        $user = User::factory()->create(['role_id' => $this->supportRole->id]);

        expect($this->permissionService->hasPermission($user, Permission::USERS_EDIT))->toBeFalse();
    });

    it('returns false when user has no role', function () {
        $user = User::factory()->create(['is_admin' => false, 'role_id' => null]);

        expect($this->permissionService->hasPermission($user, Permission::USERS_VIEW))->toBeFalse();
    });
});

describe('isAdmin', function () {
    it('returns true when user has admin role', function () {
        $user = User::factory()->create(['role_id' => $this->adminRole->id]);

        expect($this->permissionService->isAdmin($user))->toBeTrue();
    });

    it('returns false for regular users', function () {
        $user = User::factory()->create(['is_admin' => false, 'role_id' => $this->userRole->id]);

        expect($this->permissionService->isAdmin($user))->toBeFalse();
    });
});

describe('isSupport', function () {
    it('returns true for admin role', function () {
        $user = User::factory()->create(['role_id' => $this->adminRole->id]);

        expect($this->permissionService->isSupport($user))->toBeTrue();
    });

    it('returns true for support role', function () {
        $user = User::factory()->create(['is_admin' => false, 'role_id' => $this->supportRole->id]);

        expect($this->permissionService->isSupport($user))->toBeTrue();
    });

    it('returns false for regular users', function () {
        $user = User::factory()->create(['is_admin' => false, 'role_id' => $this->userRole->id]);

        expect($this->permissionService->isSupport($user))->toBeFalse();
    });
});

describe('assignRole', function () {
    it('assigns role to user', function () {
        $user = User::factory()->create(['role_id' => null]);

        $this->permissionService->assignRole($user, Role::ROLE_SUPPORT);

        $user->refresh();
        expect($user->role_id)->toBe($this->supportRole->id);
    });

    it('does nothing for non-existent role', function () {
        $user = User::factory()->create(['role_id' => $this->userRole->id]);

        $this->permissionService->assignRole($user, 'non_existent_role');

        $user->refresh();
        expect($user->role_id)->toBe($this->userRole->id);
    });
});

describe('removeRole', function () {
    it('removes role from user', function () {
        $user = User::factory()->create(['role_id' => $this->userRole->id]);

        $this->permissionService->removeRole($user);

        $user->refresh();
        expect($user->role_id)->toBeNull();
    });
});

describe('getUserPermissions', function () {
    it('returns all permissions for admin role', function () {
        $user = User::factory()->create(['role_id' => $this->adminRole->id]);

        $permissions = $this->permissionService->getUserPermissions($user);

        expect($permissions)->toContain(Permission::USERS_VIEW);
        expect($permissions)->toContain(Permission::USERS_EDIT);
    });

    it('returns role permissions for regular users', function () {
        $user = User::factory()->create(['role_id' => $this->supportRole->id]);

        $permissions = $this->permissionService->getUserPermissions($user);

        expect($permissions)->toContain(Permission::USERS_VIEW);
        expect($permissions)->not->toContain(Permission::USERS_EDIT);
    });

    it('returns empty array for users without role', function () {
        $user = User::factory()->create(['is_admin' => false, 'role_id' => null]);

        $permissions = $this->permissionService->getUserPermissions($user);

        expect($permissions)->toBeEmpty();
    });
});
