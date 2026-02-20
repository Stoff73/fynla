<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Assigns RBAC roles to all existing users based on their is_admin flag.
     * After this migration, every user has a role_id set.
     */
    public function up(): void
    {
        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        $userRoleId = DB::table('roles')->where('name', 'user')->value('id');

        if (! $adminRoleId || ! $userRoleId) {
            // Roles not seeded yet — skip (seeder will handle assignment)
            return;
        }

        // Assign admin role to all is_admin=true users without a role
        DB::table('users')
            ->where('is_admin', true)
            ->whereNull('role_id')
            ->update(['role_id' => $adminRoleId]);

        // Assign user role to everyone else without a role
        DB::table('users')
            ->whereNull('role_id')
            ->update(['role_id' => $userRoleId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't remove role assignments — they're harmless and
        // removing them could break access if RBAC is still active
    }
};
