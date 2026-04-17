<?php

declare(strict_types=1);

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('preserves HTML inside body_blocks paragraph for admin insights endpoint', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->postJson('/api/admin/insights/articles', [
            'title' => 'HTML test',
            'summary' => 'Summary',
            'category' => 'pensions',
            'body_blocks' => [
                ['type' => 'paragraph', 'html' => '<p>Hello <strong>world</strong></p>'],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.body_blocks.0.html', '<p>Hello <strong>world</strong></p>');
});
