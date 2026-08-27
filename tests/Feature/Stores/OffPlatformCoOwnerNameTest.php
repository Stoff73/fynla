<?php

declare(strict_types=1);

use App\Http\Resources\SavingsAccountResource;
use App\Models\User;
use App\Services\AI\AiToolDefinitions;
use App\Services\Stores\IngestSource;
use App\Services\Stores\InvestmentAccountStore;
use App\Services\Stores\SavingsStore;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0042 — a shared savings or investment account can now name an off-platform
 * co-owner, as a property, mortgage or chattel already could.
 *
 * W-0025's house rule is that a shared asset names its counterparty: either a
 * linked `joint_owner_id` or a free-text `joint_owner_name`. Four tables could
 * only express the first half, so a joint account held with someone not on the
 * platform was anonymous — the user could say it was shared and never say with
 * whom. CSJ direction 2026-08-26: add it to all four.
 *
 * These pin the ROUND TRIP, because a column nothing carries is the same as no
 * column: the store must accept it, persist it, and hand it back.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    $this->user = User::factory()->create();
});

it('persists an off-platform co-owner on a shared savings account', function () {
    $account = app(SavingsStore::class)->create([
        'account_name' => 'Joint Saver',
        'institution' => 'Nationwide',
        'account_type' => 'easy_access',
        'current_balance' => 20000,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'joint_owner_name' => 'Mike Barrett',
    ], $this->user, IngestSource::FORM);

    expect($account->fresh()->joint_owner_name)->toBe('Mike Barrett')
        ->and($account->fresh()->joint_owner_id)->toBeNull();
});

it('persists an off-platform co-owner on a shared investment account', function () {
    $account = app(InvestmentAccountStore::class)->create([
        'account_name' => 'Joint GIA',
        'provider' => 'Vanguard',
        'account_type' => 'gia',
        'current_value' => 50000,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'joint_owner_name' => 'Mike Barrett',
    ], $this->user, IngestSource::FORM);

    expect($account->fresh()->joint_owner_name)->toBe('Mike Barrett')
        ->and($account->fresh()->joint_owner_id)->toBeNull();
});

it('publishes the name on the savings resource so a surface can render it', function () {
    $account = app(SavingsStore::class)->create([
        'account_name' => 'Joint Saver',
        'institution' => 'Nationwide',
        'account_type' => 'easy_access',
        'current_balance' => 20000,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'joint_owner_name' => 'Mike Barrett',
    ], $this->user, IngestSource::FORM);

    $payload = (new SavingsAccountResource($account))->toArray(request());

    // Unwrapped, beside the linked-account relation: a reader asking "who is the
    // other half" has to see both, and the relation alone is now an incomplete
    // answer.
    expect($payload)->toHaveKey('joint_owner_name')
        ->and($payload['joint_owner_name'])->toBe('Mike Barrett');
});

it('lets Fyn name an off-platform co-owner, which is the only write path on /m', function () {
    // Rule 19 — /m carries no ownership form of its own and writes through Fyn, so
    // if the tool cannot carry the field the capability does not exist there at all.
    $tools = app(AiToolDefinitions::class)->getTools();

    foreach (['create_savings_account', 'create_investment_account'] as $name) {
        $tool = collect($tools)->first(fn (array $t) => ($t['function']['name'] ?? $t['name'] ?? '') === $name);
        $props = $tool['function']['parameters']['properties'] ?? $tool['input_schema']['properties'] ?? [];

        expect($tool)->not->toBeNull("{$name} is missing from the catalogue")
            ->and($props)->toHaveKey('joint_owner_name');
    }
});
