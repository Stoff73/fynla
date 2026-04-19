<?php

declare(strict_types=1);

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('preserves HTML inside body_blocks paragraph for admin insights endpoint', function () {
    $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
    $adminRole = \App\Models\Role::findByName(\App\Models\Role::ROLE_ADMIN);
    $admin = User::factory()->create([
        'role_id' => $adminRole->id,
        'is_admin' => true,
    ]);

    // Paragraph blocks allow only inline tags: <strong><em><a><br>
    \Laravel\Sanctum\Sanctum::actingAs($admin);

    $this->postJson('/api/admin/insights/articles', [
        'title' => 'HTML test',
        'summary' => 'Summary',
        'category' => 'pensions',
        'body_blocks' => [
            ['type' => 'paragraph', 'html' => 'Hello <strong>world</strong>'],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.body_blocks.0.html', 'Hello <strong>world</strong>');
});

it('preserves <p> inside callout blocks (block tag allowlist)', function () {
    $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
    $adminRole = \App\Models\Role::findByName(\App\Models\Role::ROLE_ADMIN);
    $admin = User::factory()->create([
        'role_id' => $adminRole->id,
        'is_admin' => true,
    ]);

    \Laravel\Sanctum\Sanctum::actingAs($admin);

    $this->postJson('/api/admin/insights/articles', [
        'title' => 'Callout test',
        'summary' => 'Summary',
        'category' => 'pensions',
        'body_blocks' => [
            ['type' => 'callout', 'variant' => 'info', 'html' => '<p>Block content</p>'],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.body_blocks.0.html', '<p>Block content</p>');
});
