<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\Handlers\TaxAllowanceHandler;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(TaxConfigurationSeeder::class));

it('fetches the live ISA + pension allowances with the active tax year as source version', function (): void {
    $user = User::factory()->create();
    $handler = app(TaxAllowanceHandler::class);

    $res = $handler->fetch(new FetchContext($user, 'what is my isa allowance'));

    expect($handler->id())->toBe('tax_allowance')
        ->and($res->sourceLabel)->toBe('TaxConfigService and saved ISA records')
        ->and($res->sourceVersion)->toBe(app(TaxConfigService::class)->getTaxYear())
        ->and($res->value)->toContain('ISA')
        ->and($res->value)->toContain('£');
});

it('includes the users recorded ISA usage, remaining allowance and subscription account', function (): void {
    $year = app(TaxConfigService::class)->getTaxYear();
    $user = User::factory()->create();
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'account_name' => 'Barclays Cash ISA',
        'institution' => 'Barclays',
        'account_type' => 'cash_isa',
        'is_isa' => true,
        'isa_type' => 'cash',
        'current_balance' => 20000,
        'isa_subscription_year' => $year,
        'isa_subscription_amount' => 5000,
    ]);

    $res = app(TaxAllowanceHandler::class)->fetch(new FetchContext(
        $user,
        'How much of my ISA allowance have I used, what remains, and which account contains the subscription?',
    ));

    expect($res->value)->toContain('£5,000 used')
        ->and($res->value)->toContain('£15,000 remaining')
        ->and($res->value)->toContain('Barclays Cash ISA')
        ->and($res->value)->toContain('£5,000 subscribed');
});
