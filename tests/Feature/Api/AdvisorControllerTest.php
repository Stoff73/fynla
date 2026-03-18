<?php

declare(strict_types=1);

use App\Models\AdvisorClient;
use App\Models\ClientActivity;
use App\Models\User;

beforeEach(function () {
    $this->advisor = User::factory()->create(['is_advisor' => true]);
    $this->advisorToken = $this->advisor->createToken('test')->plainTextToken;

    $this->client = User::factory()->create(['is_advisor' => false, 'is_admin' => false]);

    $this->advisorClient = AdvisorClient::factory()->create([
        'advisor_id' => $this->advisor->id,
        'client_id' => $this->client->id,
        'status' => 'active',
        'next_review_due' => now()->subDays(5),
    ]);
});

it('returns 403 for non-advisor user on all advisor endpoints', function () {
    $regularUser = User::factory()->create(['is_advisor' => false]);
    $token = $regularUser->createToken('test')->plainTextToken;

    $this->withToken($token)->getJson('/api/advisor/dashboard')->assertStatus(403);
    $this->withToken($token)->getJson('/api/advisor/clients')->assertStatus(403);
    $this->withToken($token)->getJson('/api/advisor/clients/1')->assertStatus(403);
    $this->withToken($token)->getJson('/api/advisor/clients/1/modules')->assertStatus(403);
    $this->withToken($token)->postJson('/api/advisor/clients/1/enter')->assertStatus(403);
    $this->withToken($token)->postJson('/api/advisor/exit')->assertStatus(403);
    $this->withToken($token)->getJson('/api/advisor/activities')->assertStatus(403);
    $this->withToken($token)->postJson('/api/advisor/activities')->assertStatus(403);
    $this->withToken($token)->putJson('/api/advisor/activities/1')->assertStatus(403);
    $this->withToken($token)->getJson('/api/advisor/reviews-due')->assertStatus(403);
    $this->withToken($token)->getJson('/api/advisor/reports')->assertStatus(403);
});

it('returns dashboard stats for advisor', function () {
    $response = $this->withToken($this->advisorToken)
        ->getJson('/api/advisor/dashboard');

    $response->assertOk()
        ->assertJson(['success' => true])
        ->assertJsonStructure([
            'success',
            'data' => ['clients', 'reviewsDue', 'commsThisWeek', 'reportsThisMonth'],
        ]);
});

it('returns client list for advisor', function () {
    $response = $this->withToken($this->advisorToken)
        ->getJson('/api/advisor/clients');

    $response->assertOk()
        ->assertJson(['success' => true])
        ->assertJsonStructure(['success', 'data']);

    $data = $response->json('data');
    expect($data)->toBeArray();
    expect(count($data))->toBeGreaterThanOrEqual(1);
});

it('returns client detail for assigned client', function () {
    $response = $this->withToken($this->advisorToken)
        ->getJson("/api/advisor/clients/{$this->client->id}");

    $response->assertOk()
        ->assertJson(['success' => true])
        ->assertJsonStructure([
            'success',
            'data' => ['id', 'client_id', 'display_name', 'status', 'module_status', 'activities'],
        ]);
});

it('rejects client detail for unassigned client', function () {
    $unassignedClient = User::factory()->create();

    $response = $this->withToken($this->advisorToken)
        ->getJson("/api/advisor/clients/{$unassignedClient->id}");

    $response->assertStatus(404)
        ->assertJson(['success' => false, 'message' => 'Client not found or not assigned to you.']);
});

it('starts impersonation for assigned client', function () {
    $response = $this->withToken($this->advisorToken)
        ->postJson("/api/advisor/clients/{$this->client->id}/enter");

    $response->assertOk()
        ->assertJson(['success' => true])
        ->assertJsonStructure([
            'success',
            'data' => ['impersonating', 'client'],
        ]);

    expect($response->json('data.impersonating'))->toBeTrue();
    expect($response->json('data.client.id'))->toBe($this->client->id);
});

it('rejects impersonation for unassigned client', function () {
    $unassignedClient = User::factory()->create(['is_advisor' => false, 'is_admin' => false]);

    $response = $this->withToken($this->advisorToken)
        ->postJson("/api/advisor/clients/{$unassignedClient->id}/enter");

    $response->assertStatus(403)
        ->assertJson(['success' => false]);
});

it('ends impersonation', function () {
    // First enter impersonation
    $this->withToken($this->advisorToken)
        ->postJson("/api/advisor/clients/{$this->client->id}/enter")
        ->assertOk();

    // Then exit
    $response = $this->withToken($this->advisorToken)
        ->postJson('/api/advisor/exit');

    $response->assertOk()
        ->assertJson(['success' => true, 'message' => 'Exited client profile.']);
});

it('lists activities filtered by client', function () {
    ClientActivity::factory()->count(3)->create([
        'advisor_client_id' => $this->advisorClient->id,
        'advisor_id' => $this->advisor->id,
        'client_id' => $this->client->id,
    ]);

    $response = $this->withToken($this->advisorToken)
        ->getJson("/api/advisor/activities?client_id={$this->client->id}");

    $response->assertOk()
        ->assertJson(['success' => true])
        ->assertJsonStructure(['success', 'data']);
});

it('creates activity with valid data', function () {
    $activityData = [
        'client_id' => $this->client->id,
        'activity_type' => 'meeting',
        'summary' => 'Annual review meeting',
        'activity_date' => now()->format('Y-m-d'),
    ];

    $response = $this->withToken($this->advisorToken)
        ->postJson('/api/advisor/activities', $activityData);

    $response->assertStatus(201)
        ->assertJson(['success' => true, 'message' => 'Activity logged.'])
        ->assertJsonStructure(['success', 'data', 'message']);
});

it('validates activity data', function () {
    $response = $this->withToken($this->advisorToken)
        ->postJson('/api/advisor/activities', []);

    $response->assertStatus(422);
});

it('returns overdue reviews', function () {
    $response = $this->withToken($this->advisorToken)
        ->getJson('/api/advisor/reviews-due');

    $response->assertOk()
        ->assertJson(['success' => true])
        ->assertJsonStructure(['success', 'data']);

    $data = $response->json('data');
    expect($data)->toBeArray();
    expect(count($data))->toBeGreaterThanOrEqual(1);
});

it('returns suitability reports', function () {
    ClientActivity::factory()->suitabilityReport()->create([
        'advisor_client_id' => $this->advisorClient->id,
        'advisor_id' => $this->advisor->id,
        'client_id' => $this->client->id,
    ]);

    $response = $this->withToken($this->advisorToken)
        ->getJson('/api/advisor/reports');

    $response->assertOk()
        ->assertJson(['success' => true])
        ->assertJsonStructure(['success', 'data']);
});
