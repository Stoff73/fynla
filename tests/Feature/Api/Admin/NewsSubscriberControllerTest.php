<?php

declare(strict_types=1);

use App\Models\News\NewsSubscriber;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesPermissionsSeeder::class);
    $adminRole = Role::findByName(Role::ROLE_ADMIN);
    $userRole = Role::findByName(Role::ROLE_USER);
    $this->admin = User::factory()->create(['role_id' => $adminRole->id, 'is_admin' => true]);
    $this->user = User::factory()->create(['role_id' => $userRole->id, 'is_admin' => false]);
});

it('returns a paginated list of subscribers for admins', function () {
    Sanctum::actingAs($this->admin);

    NewsSubscriber::factory()->count(3)->confirmed()->create();
    NewsSubscriber::factory()->count(2)->create();
    NewsSubscriber::factory()->unsubscribed()->create();

    $response = $this->getJson('/api/admin/news-subscribers');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'email', 'status', 'source', 'created_at', 'confirmed_at', 'unsubscribed_at']],
            'meta' => ['current_page', 'last_page', 'total'],
        ]);

    expect($response->json('meta.total'))->toBe(6);
});

it('rejects non-admin users', function () {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/admin/news-subscribers')->assertForbidden();
});

it('rejects unauthenticated requests', function () {
    $this->getJson('/api/admin/news-subscribers')->assertUnauthorized();
});

it('filters by status', function () {
    Sanctum::actingAs($this->admin);

    NewsSubscriber::factory()->count(2)->confirmed()->create();
    NewsSubscriber::factory()->count(3)->create();

    $response = $this->getJson('/api/admin/news-subscribers?status=confirmed');

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(2);
});

it('searches by email', function () {
    Sanctum::actingAs($this->admin);

    NewsSubscriber::factory()->create(['email' => 'hello@unique.test']);
    NewsSubscriber::factory()->count(3)->create();

    $response = $this->getJson('/api/admin/news-subscribers?search=unique');

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(1);
});

it('exports all subscribers as CSV for admins', function () {
    Sanctum::actingAs($this->admin);

    NewsSubscriber::factory()->confirmed()->create(['email' => 'csv1@example.com']);
    NewsSubscriber::factory()->create(['email' => 'csv2@example.com']);

    $response = $this->get('/api/admin/news-subscribers/export');

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $body = $response->streamedContent();
    expect($body)->toContain('email,status,source');
    expect($body)->toContain('csv1@example.com');
    expect($body)->toContain('csv2@example.com');
});
