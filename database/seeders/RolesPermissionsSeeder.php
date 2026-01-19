<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\Auth\PermissionService;
use Illuminate\Database\Seeder;

class RolesPermissionsSeeder extends Seeder
{
    public function __construct(
        private PermissionService $permissionService
    ) {}

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->permissionService->syncDefaultRolesAndPermissions();

        $this->command->info('Default roles and permissions seeded successfully.');
    }
}
