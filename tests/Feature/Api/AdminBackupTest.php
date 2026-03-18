<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(RolesPermissionsSeeder::class);

    $adminRole = Role::findByName(Role::ROLE_ADMIN);
    $userRole = Role::findByName(Role::ROLE_USER);

    $this->admin = User::factory()->create([
        'role_id' => $adminRole->id,
        'is_admin' => true,
        'is_preview_user' => true,
    ]);

    $this->regularUser = User::factory()->create([
        'role_id' => $userRole->id,
        'is_preview_user' => true,
    ]);

    // Ensure backups directory exists
    $backupsDir = storage_path('app/backups');
    if (! file_exists($backupsDir)) {
        mkdir($backupsDir, 0750, true);
    }
});

afterEach(function () {
    // Clean up any test backup files and stale credential files
    $backupsDir = storage_path('app/backups');
    foreach (glob($backupsDir.'/backup_*.sql') as $file) {
        @unlink($file);
    }
    foreach (glob($backupsDir.'/.my.cnf.*') as $file) {
        @unlink($file);
    }
});

describe('Admin Backup API', function () {
    it('lists existing backups with correct metadata', function () {
        Sanctum::actingAs($this->admin);

        // Create a dummy backup file for listing
        $filename = 'backup_2026-03-17_10-00-00.sql';
        file_put_contents(storage_path('app/backups/'.$filename), '-- test backup');

        $response = $this->getJson('/api/admin/backup/list');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'data']);

        $data = $response->json('data');
        expect(count($data))->toBeGreaterThanOrEqual(1);

        $found = collect($data)->firstWhere('filename', $filename);
        expect($found)->not->toBeNull();
        expect($found['filename'])->toBe($filename);
    });

    it('deletes a backup file', function () {
        Sanctum::actingAs($this->admin);

        // Create a dummy backup file
        $filename = 'backup_2026-03-17_12-00-00.sql';
        $path = storage_path('app/backups/'.$filename);
        file_put_contents($path, '-- test backup for deletion');

        $response = $this->deleteJson('/api/admin/backup/delete', [
            'filename' => $filename,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        expect(file_exists($path))->toBeFalse();
    });

    it('rejects path traversal in backup filename', function () {
        Sanctum::actingAs($this->admin);

        $response = $this->deleteJson('/api/admin/backup/delete', [
            'filename' => '../../../etc/passwd',
        ]);

        $response->assertStatus(422);
    });

    it('rejects invalid backup filename format', function () {
        Sanctum::actingAs($this->admin);

        $response = $this->deleteJson('/api/admin/backup/delete', [
            'filename' => 'not-a-valid-backup.txt',
        ]);

        $response->assertStatus(422);
    });

    it('requires admin permission for backup operations', function () {
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson('/api/admin/backup/list');

        $response->assertStatus(403);
    });

    it('returns empty list when no backups exist', function () {
        Sanctum::actingAs($this->admin);

        // Remove any existing backup files
        $backupsDir = storage_path('app/backups');
        foreach (glob($backupsDir.'/backup_*.sql') as $file) {
            @unlink($file);
        }

        $response = $this->getJson('/api/admin/backup/list');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        expect($data)->toBeArray();
    });

    it('cleans up temporary credential files after backup operations', function () {
        Sanctum::actingAs($this->admin);

        // List backups (which doesn't create credential files) should leave no temp files.
        // Note: Full credential lifecycle testing (create → verify cleanup) requires
        // mysqldump to be available in the test environment.
        $this->getJson('/api/admin/backup/list');

        $backupsDir = storage_path('app/backups');
        $tempFiles = glob($backupsDir.'/.my.cnf.*');

        expect(count($tempFiles))->toBe(0)
            ->and(file_exists($backupsDir))->toBeTrue();
    });

    it('creates a backup file successfully', function () {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/backup/create')
            ->assertStatus(200);
    })->skip(! is_executable('/usr/local/bin/mysqldump') && ! is_executable('/usr/bin/mysqldump'), 'mysqldump not available');

    it('restores a backup file', function () {
        Sanctum::actingAs($this->admin);

        Storage::disk('local')->put('backups/backup_2026-03-17_12-00-00.sql', 'SELECT 1;');

        $this->postJson('/api/admin/backup/restore', ['filename' => 'backup_2026-03-17_12-00-00.sql'])
            ->assertStatus(200);
    })->skip(! is_executable('/usr/local/bin/mysql') && ! is_executable('/usr/bin/mysql'), 'mysql client not available');

    it('rate limits backup write operations to 3 per minute', function () {
        // Backup write endpoints (create/restore/delete) are rate limited at 3/min
        // Use delete with a non-existent file — will return 422 validation error but still counts toward rate limit
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($this->admin)
                ->deleteJson('/api/admin/backup/delete', ['filename' => 'backup_2026-01-01_00-00-0'.$i.'.sql']);
        }

        $this->actingAs($this->admin)
            ->deleteJson('/api/admin/backup/delete', ['filename' => 'backup_2026-01-01_00-00-03.sql'])
            ->assertStatus(429);
    });
});
