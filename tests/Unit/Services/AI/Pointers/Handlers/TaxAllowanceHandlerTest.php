<?php

declare(strict_types=1);

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
        ->and($res->sourceLabel)->toBe('TaxConfigService')
        ->and($res->sourceVersion)->toBe(app(TaxConfigService::class)->getTaxYear())
        ->and($res->value)->toContain('ISA')
        ->and($res->value)->toContain('£');
});
