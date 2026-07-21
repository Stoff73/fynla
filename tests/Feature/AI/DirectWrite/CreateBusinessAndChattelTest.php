<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\BusinessInterest;
use App\Models\Chattel;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

// 0.5.m create_business_interest

it('create_business_interest persists a BusinessInterest', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_business_interest', [
        'business_name' => 'Acme Consulting Ltd',
        'business_type' => 'limited_company',
        'estimated_value' => 250000,
        'annual_revenue' => 200000,
        'annual_profit' => 80000,
        'ownership_percentage' => 100,
    ], $user);

    expect($result['success'])->toBeTrue();
    expect($result['entity_type'])->toBe('business_interest');

    $bi = BusinessInterest::find($result['entity_id']);
    expect($bi)->not->toBeNull();
    expect($bi->business_name)->toBe('Acme Consulting Ltd');
    expect($bi->business_type)->toBe('limited_company');
    expect((float) $bi->current_valuation)->toBe(250000.0);
});

it('create_business_interest validation_failed without business_name', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $result = app(CoordinatingAgent::class)->executeTool('create_business_interest', [
        'business_type' => 'limited_company',
    ], $user);
    expect($result['error_type'])->toBe('validation_failed');
});

it('create_business_interest blocks preview users', function (): void {
    $user = User::factory()->create(['is_preview_user' => true]);
    $result = app(CoordinatingAgent::class)->executeTool('create_business_interest', [
        'business_name' => 'X', 'business_type' => 'limited_company',
    ], $user);
    expect($result['blocked'])->toBeTrue();
});

// 0.5.n create_chattel

it('create_chattel persists a Chattel mapped to canonical chattel_type', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_chattel', [
        'description' => 'Diamond engagement ring',
        'category' => 'jewellery',
        'estimated_value' => 8000,
        'purchase_value' => 6000,
    ], $user);

    expect($result['success'])->toBeTrue();
    expect($result['entity_type'])->toBe('chattel');

    $c = Chattel::find($result['entity_id']);
    expect($c)->not->toBeNull();
    expect($c->chattel_type)->toBe('jewelry'); // jewellery -> jewelry mapping
    expect($c->name)->toBe('Diamond engagement ring');
    expect((float) $c->current_value)->toBe(8000.0);
});

it('create_chattel maps each AI category to canonical chattel_type', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $expected = [
        'jewellery' => 'jewelry',
        'art' => 'art',
        'antiques' => 'antique',
        'collectibles' => 'collectible',
        'vehicles' => 'vehicle',
    ];
    foreach ($expected as $aiCat => $dbType) {
        $result = app(CoordinatingAgent::class)->executeTool('create_chattel', [
            'description' => "Test {$aiCat}",
            'category' => $aiCat,
            'estimated_value' => 100,
        ], $user);
        expect(Chattel::find($result['entity_id'])->chattel_type)->toBe($dbType);
    }
});

it('create_chattel blocks preview users', function (): void {
    $user = User::factory()->create(['is_preview_user' => true]);
    $result = app(CoordinatingAgent::class)->executeTool('create_chattel', [
        'description' => 'X', 'estimated_value' => 1,
    ], $user);
    expect($result['blocked'])->toBeTrue();
});

it('business + chattel return shapes have no fill_form action', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $b = app(CoordinatingAgent::class)->executeTool('create_business_interest', [
        'business_name' => 'X', 'business_type' => 'sole_trader',
    ], $user);
    $c = app(CoordinatingAgent::class)->executeTool('create_chattel', [
        'description' => 'X', 'estimated_value' => 1,
    ], $user);

    foreach ([$b, $c] as $r) {
        expect($r)->not->toHaveKey('action');
        expect($r)->not->toHaveKey('fields');
    }
});
