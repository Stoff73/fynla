<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->seed(RolesPermissionsSeeder::class);
    $adminRole = Role::findByName(Role::ROLE_ADMIN);

    $this->admin = User::factory()->create([
        'role_id' => $adminRole->id,
        'is_admin' => true,
    ]);
});

it('uploads an image and returns three paths', function () {
    Sanctum::actingAs($this->admin);

    $file = UploadedFile::fake()->image('photo.jpg', 2000, 1200);

    $this->postJson('/api/admin/insights/images', [
        'image' => $file,
        'slug' => 'my-article',
    ])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['path', 'card_path', 'thumb_path']]);
});

it('rejects files larger than 10 MB', function () {
    Sanctum::actingAs($this->admin);

    $file = UploadedFile::fake()->image('huge.jpg')->size(11 * 1024);

    $this->postJson('/api/admin/insights/images', ['image' => $file, 'slug' => 'a'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('image');
});

it('rejects non-image formats', function () {
    Sanctum::actingAs($this->admin);

    $file = UploadedFile::fake()->create('file.pdf', 100, 'application/pdf');

    $this->postJson('/api/admin/insights/images', ['image' => $file, 'slug' => 'a'])
        ->assertUnprocessable();
});

it('forbids non-admin users', function () {
    $userRole = Role::findByName(Role::ROLE_USER);
    $user = User::factory()->create(['role_id' => $userRole->id, 'is_admin' => false]);

    Sanctum::actingAs($user);

    $file = UploadedFile::fake()->image('p.jpg');

    $this->postJson('/api/admin/insights/images', ['image' => $file, 'slug' => 'a'])
        ->assertForbidden();
});
