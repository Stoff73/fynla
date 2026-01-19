<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    // Permission categories
    public const CATEGORY_USERS = 'users';
    public const CATEGORY_CONTENT = 'content';
    public const CATEGORY_SETTINGS = 'settings';
    public const CATEGORY_ADMIN = 'admin';

    // Common permissions
    public const USERS_VIEW = 'users.view';
    public const USERS_EDIT = 'users.edit';
    public const USERS_DELETE = 'users.delete';
    public const USERS_IMPERSONATE = 'users.impersonate';

    public const CONTENT_VIEW = 'content.view';
    public const CONTENT_CREATE = 'content.create';
    public const CONTENT_EDIT = 'content.edit';
    public const CONTENT_DELETE = 'content.delete';

    public const SETTINGS_VIEW = 'settings.view';
    public const SETTINGS_EDIT = 'settings.edit';

    public const ADMIN_ACCESS = 'admin.access';
    public const ADMIN_AUDIT_VIEW = 'admin.audit.view';
    public const ADMIN_TAX_CONFIG = 'admin.tax_config';
    public const ADMIN_ERASURE_PROCESS = 'admin.erasure_process';

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'category',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }

    /**
     * Get or create a permission by name
     */
    public static function findOrCreateByName(string $name, string $displayName, ?string $category = null): self
    {
        return self::firstOrCreate(
            ['name' => $name],
            [
                'display_name' => $displayName,
                'category' => $category,
            ]
        );
    }

    /**
     * Get permission by name
     */
    public static function findByName(string $name): ?self
    {
        return self::where('name', $name)->first();
    }

    /**
     * Get all permissions in a category
     */
    public static function inCategory(string $category): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('category', $category)->get();
    }
}
