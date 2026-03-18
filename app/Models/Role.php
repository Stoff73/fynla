<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    // Default roles
    public const ROLE_USER = 'user';

    public const ROLE_SUPPORT = 'support';

    public const ROLE_ADMIN = 'admin';

    // Role levels (higher = more permissions)
    public const LEVEL_USER = 0;

    public const LEVEL_SUPPORT = 50;

    public const LEVEL_ADMIN = 100;

    public const ROLE_ADVISOR = 'advisor';

    public const LEVEL_ADVISOR = 25;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'level',
    ];

    protected $casts = [
        'level' => 'integer',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Check if this role has a specific permission
     */
    public function hasPermission(string $permissionName): bool
    {
        return $this->permissions()->where('name', $permissionName)->exists();
    }

    /**
     * Check if this role has any of the given permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        return $this->permissions()->whereIn('name', $permissions)->exists();
    }

    /**
     * Check if this role has all of the given permissions
     */
    public function hasAllPermissions(array $permissions): bool
    {
        $count = $this->permissions()->whereIn('name', $permissions)->count();

        return $count === count($permissions);
    }

    /**
     * Give this role a permission
     */
    public function givePermission(Permission $permission): void
    {
        $this->permissions()->syncWithoutDetaching([$permission->id]);
    }

    /**
     * Remove a permission from this role
     */
    public function removePermission(Permission $permission): void
    {
        $this->permissions()->detach($permission->id);
    }

    /**
     * Sync permissions (replace all)
     */
    public function syncPermissions(array $permissionIds): void
    {
        $this->permissions()->sync($permissionIds);
    }

    /**
     * Check if this role is at least the given level
     */
    public function isAtLeast(int $level): bool
    {
        return $this->level >= $level;
    }

    /**
     * Get role by name
     */
    public static function findByName(string $name): ?self
    {
        return self::where('name', $name)->first();
    }

    /**
     * Get the user role
     */
    public static function getUserRole(): ?self
    {
        return self::findByName(self::ROLE_USER);
    }

    /**
     * Get the support role
     */
    public static function getSupportRole(): ?self
    {
        return self::findByName(self::ROLE_SUPPORT);
    }

    /**
     * Get the admin role
     */
    public static function getAdminRole(): ?self
    {
        return self::findByName(self::ROLE_ADMIN);
    }

    /**
     * Get the advisor role
     */
    public static function getAdvisorRole(): ?self
    {
        return self::findByName(self::ROLE_ADVISOR);
    }
}
