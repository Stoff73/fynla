<?php

declare(strict_types=1);

use App\Models\Document;
use App\Models\User;
use App\Services\Documents\DocumentAllowanceGate;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TierConfigurationSeeder::class);
});

it('blocks Free uploads immediately and points to Premium', function () {
    $user = User::factory()->create(['tier' => 'free']);

    $result = app(DocumentAllowanceGate::class)->check($user, 1);

    expect($result)->not->toBeNull()
        ->and($result['entity_key'])->toBe('document_upload')
        ->and($result['limit'])->toBe(0)
        ->and($result['target_tier'])->toMatchArray([
            'tier' => 'premium',
            'display_name' => 'Premium',
        ]);
});

it('allows Premium document counts without a count ceiling', function () {
    $user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    Document::factory(20)->create([
        'user_id' => $user->id,
        'file_size' => 1024,
    ]);

    expect(app(DocumentAllowanceGate::class)->check($user, 1024))->toBeNull();
});

it('enforces Premium storage without offering a nonexistent higher tier', function () {
    $user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    Document::factory()->create([
        'user_id' => $user->id,
        'file_size' => 1024 * 1024 * 1024,
    ]);

    $result = app(DocumentAllowanceGate::class)->check($user, 1);

    expect($result)->not->toBeNull()
        ->and($result['entity_key'])->toBe('document_storage')
        ->and($result['target_tier'])->toBeNull()
        ->and(strtolower($result['reason']))->toContain('remove documents')
        ->not->toContain('upgrade');
});
