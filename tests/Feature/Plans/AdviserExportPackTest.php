<?php

declare(strict_types=1);

use App\Models\Estate\Liability;
use App\Models\Goal;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Plans\AdviserExportPackService;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.payment_enabled' => true]);
    $this->seed(TierConfigurationSeeder::class);
});

it('denies Free users with a structured Premium action', function () {
    $user = User::factory()->create(['tier' => 'free']);

    $this->actingAs($user, 'sanctum')
        ->get('/api/plans/adviser-export-pack')
        ->assertForbidden()
        ->assertJsonPath('error', 'capability_denied')
        ->assertJsonPath('capability', 'advisor_export')
        ->assertJsonPath('required_tier', 'premium');
});

it('returns a real dated PDF download for Premium', function () {
    $user = User::factory()->create([
        'tier' => 'premium',
        'first_name' => 'Avery',
        'surname' => 'Morgan',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->get('/api/plans/adviser-export-pack')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect($response->headers->get('content-disposition'))
        ->toContain('attachment')
        ->toContain('fynla-adviser-export-'.now()->toDateString().'.pdf')
        ->and($response->getContent())->toStartWith('%PDF-');
});

it('builds the adviser pack from live financial data without chat transcripts or invented empty sections', function () {
    $user = User::factory()->create([
        'tier' => 'premium',
        'first_name' => 'Avery',
        'surname' => 'Morgan',
        'annual_employment_income' => 60000,
        'monthly_expenditure' => 1800,
    ]);
    InvestmentAccount::factory()->gia()->create([
        'user_id' => $user->id,
        'account_name' => 'Core portfolio',
        'current_value' => 25000,
    ]);
    Liability::factory()->personalLoan()->create([
        'user_id' => $user->id,
        'liability_name' => 'Bank loan',
        'current_balance' => 5000,
    ]);
    Goal::factory()->create([
        'user_id' => $user->id,
        'goal_name' => 'Home deposit',
        'target_amount' => 40000,
    ]);

    $pack = app(AdviserExportPackService::class)->buildPack($user);
    $html = View::make('plans.adviser-export-pack', ['pack' => $pack])->render();

    expect($pack)->toHaveKeys([
        'generated_at', 'data_as_at', 'profile', 'assets', 'liabilities',
        'income_expenditure', 'goals', 'assumptions', 'plan_summaries',
    ])->not->toHaveKey('conversations')
        ->and($html)->toContain('Financial adviser export pack')
        ->toContain('Avery Morgan')
        ->toContain('Core portfolio')
        ->toContain('Bank loan')
        ->toContain('Home deposit')
        ->toContain('This document is not financial advice')
        ->not->toContain('Fyn chat')
        ->not->toContain('No risk profile available');
});

it('keeps statement extraction and investment cost analysis behind their Premium capabilities', function (string $uri, string $capability) {
    $user = User::factory()->create(['tier' => 'free']);

    $this->actingAs($user, 'sanctum')
        ->getJson($uri)
        ->assertForbidden()
        ->assertJsonPath('error', 'capability_denied')
        ->assertJsonPath('capability', $capability)
        ->assertJsonPath('required_tier', 'premium');
})->with([
    'statement upload' => ['/api/documents/upload', 'statement_upload'],
    'investment cost analysis' => ['/api/investment/fees/analyze', 'investment_cost_analysis'],
]);
