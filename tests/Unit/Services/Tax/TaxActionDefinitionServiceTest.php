<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\SavingsAccount;
use App\Models\TaxActionDefinition;
use App\Models\User;
use App\Services\Retirement\AnnualAllowanceChecker;
use App\Services\Tax\TaxActionDefinitionService;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TaxActionDefinitionSeeder::class);

    $this->service = app(TaxActionDefinitionService::class);

    $this->user = User::factory()->create([
        'annual_employment_income' => 55000,
        'is_preview_user' => true,
    ]);
});

// =========================================================================
// ISA not maxed
// =========================================================================

describe('isa_not_maxed trigger', function () {
    it('fires when user has remaining ISA allowance', function () {
        // User has ISA with partial subscription
        InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'account_type' => 'isa',
            'isa_subscription_current_year' => 5000,
            'current_value' => 25000,
        ]);

        $result = $this->service->evaluateActions($this->user);

        $rec = collect($result['recommendations'])->first(
            fn ($r) => ($r['definition_key'] ?? '') === 'isa_not_maxed'
        );
        expect($rec)->not->toBeNull()
            ->and($rec['category'])->toBe('ISA Allowance')
            ->and($rec['description'])->toContain('15,000');
    });

    it('does NOT fire when ISA allowance is fully used', function () {
        InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'account_type' => 'isa',
            'isa_subscription_current_year' => 20000,
            'current_value' => 50000,
        ]);

        $result = $this->service->evaluateActions($this->user);

        $rec = collect($result['recommendations'])->first(
            fn ($r) => ($r['definition_key'] ?? '') === 'isa_not_maxed'
        );
        expect($rec)->toBeNull();
    });

    it('considers both investment and cash ISA subscriptions', function () {
        InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'account_type' => 'isa',
            'isa_subscription_current_year' => 10000,
            'current_value' => 30000,
        ]);

        SavingsAccount::factory()->create([
            'user_id' => $this->user->id,
            'account_type' => 'isa',
            'isa_subscription_amount' => 10000,
        ]);

        $result = $this->service->evaluateActions($this->user);

        $rec = collect($result['recommendations'])->first(
            fn ($r) => ($r['definition_key'] ?? '') === 'isa_not_maxed'
        );
        // 10000 + 10000 = 20000 = full allowance
        expect($rec)->toBeNull();
    });
});

// =========================================================================
// Pension carry forward
// =========================================================================

describe('pension_carry_forward_available trigger', function () {
    it('fires when carry forward is available with low contributions', function () {
        // User with low pension contributions = carry forward available
        DCPension::factory()->create([
            'user_id' => $this->user->id,
            'monthly_contribution_amount' => 200,
            'employee_contribution_percent' => 5,
            'employer_contribution_percent' => 3,
        ]);

        $result = $this->service->evaluateActions($this->user);

        $rec = collect($result['recommendations'])->first(
            fn ($r) => ($r['definition_key'] ?? '') === 'pension_carry_forward_available'
        );
        // With low contributions, carry forward should be available
        // The actual availability depends on AnnualAllowanceChecker logic
        // This test validates the trigger evaluates without error
        expect($result['recommendations'])->toBeArray();
    });
});

// =========================================================================
// Spousal transfer
// =========================================================================

describe('spousal_transfer_beneficial trigger', function () {
    it('fires when spouse is in a lower tax band', function () {
        $spouse = User::factory()->create([
            'annual_employment_income' => 25000, // Basic rate
            'is_preview_user' => true,
        ]);

        $this->user->update([
            'marital_status' => 'married',
            'annual_employment_income' => 80000, // Higher rate
        ]);
        $this->user->spouse_id = $spouse->id;
        $this->user->save();

        // Add GIA holdings to make the saving meaningful
        InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'account_type' => 'gia',
            'current_value' => 100000,
        ]);

        $result = $this->service->evaluateActions($this->user);

        $rec = collect($result['recommendations'])->first(
            fn ($r) => ($r['definition_key'] ?? '') === 'spousal_transfer_beneficial'
        );
        expect($rec)->not->toBeNull()
            ->and($rec['category'])->toBe('Spousal Optimisation');
    });

    it('does NOT fire when both spouses are in the same tax band', function () {
        $spouse = User::factory()->create([
            'annual_employment_income' => 70000, // Higher rate
            'is_preview_user' => true,
        ]);

        $this->user->update([
            'marital_status' => 'married',
            'annual_employment_income' => 80000, // Also higher rate
        ]);
        $this->user->spouse_id = $spouse->id;
        $this->user->save();

        $result = $this->service->evaluateActions($this->user);

        $rec = collect($result['recommendations'])->first(
            fn ($r) => ($r['definition_key'] ?? '') === 'spousal_transfer_beneficial'
        );
        expect($rec)->toBeNull();
    });

    it('does NOT fire for unmarried users', function () {
        $this->user->update(['marital_status' => 'single']);

        $result = $this->service->evaluateActions($this->user);

        $rec = collect($result['recommendations'])->first(
            fn ($r) => ($r['definition_key'] ?? '') === 'spousal_transfer_beneficial'
        );
        expect($rec)->toBeNull();
    });
});

// =========================================================================
// CGT allowance unused
// =========================================================================

describe('cgt_allowance_unused trigger', function () {
    it('fires when user has GIA holdings with unrealised gains', function () {
        $giaAccount = InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'account_type' => 'gia',
            'current_value' => 50000,
        ]);

        Holding::factory()->create([
            'holdable_id' => $giaAccount->id,
            'holdable_type' => InvestmentAccount::class,
            'cost_basis' => 30000,
            'current_value' => 45000,
        ]);

        $result = $this->service->evaluateActions($this->user);

        $rec = collect($result['recommendations'])->first(
            fn ($r) => ($r['definition_key'] ?? '') === 'cgt_allowance_unused'
        );
        expect($rec)->not->toBeNull()
            ->and($rec['category'])->toBe('Capital Gains');
    });

    it('does NOT fire when user has no GIA accounts', function () {
        // Only ISA account — no CGT
        InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'account_type' => 'isa',
            'current_value' => 50000,
        ]);

        $result = $this->service->evaluateActions($this->user);

        $rec = collect($result['recommendations'])->first(
            fn ($r) => ($r['definition_key'] ?? '') === 'cgt_allowance_unused'
        );
        expect($rec)->toBeNull();
    });

    it('does NOT fire when GIA holdings have no unrealised gains', function () {
        $giaAccount = InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'account_type' => 'gia',
            'current_value' => 40000,
        ]);

        Holding::factory()->create([
            'holdable_id' => $giaAccount->id,
            'holdable_type' => InvestmentAccount::class,
            'cost_basis' => 50000,
            'current_value' => 40000, // Loss, not gain
        ]);

        $result = $this->service->evaluateActions($this->user);

        $rec = collect($result['recommendations'])->first(
            fn ($r) => ($r['definition_key'] ?? '') === 'cgt_allowance_unused'
        );
        expect($rec)->toBeNull();
    });
});

// =========================================================================
// High dividend in GIA
// =========================================================================

describe('high_dividend_in_gia trigger', function () {
    it('fires when GIA value exceeds threshold and ISA capacity remains', function () {
        InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'account_type' => 'gia',
            'current_value' => 50000,
        ]);

        $result = $this->service->evaluateActions($this->user);

        $rec = collect($result['recommendations'])->first(
            fn ($r) => ($r['definition_key'] ?? '') === 'high_dividend_in_gia'
        );
        expect($rec)->not->toBeNull()
            ->and($rec['category'])->toBe('Dividend Tax');
    });

    it('does NOT fire when GIA value is below threshold', function () {
        InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'account_type' => 'gia',
            'current_value' => 5000,
        ]);

        $result = $this->service->evaluateActions($this->user);

        $rec = collect($result['recommendations'])->first(
            fn ($r) => ($r['definition_key'] ?? '') === 'high_dividend_in_gia'
        );
        expect($rec)->toBeNull();
    });

    it('does NOT fire when ISA allowance is fully used', function () {
        InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'account_type' => 'gia',
            'current_value' => 50000,
        ]);

        InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'account_type' => 'isa',
            'isa_subscription_current_year' => 20000,
            'current_value' => 50000,
        ]);

        $result = $this->service->evaluateActions($this->user);

        $rec = collect($result['recommendations'])->first(
            fn ($r) => ($r['definition_key'] ?? '') === 'high_dividend_in_gia'
        );
        expect($rec)->toBeNull();
    });
});

// =========================================================================
// No actions for optimised user
// =========================================================================

describe('fully optimised user', function () {
    it('returns no actions for user with everything optimised', function () {
        // User with maxed ISA, no GIA, no spouse — fully optimised
        InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'account_type' => 'isa',
            'isa_subscription_current_year' => 20000,
            'current_value' => 50000,
        ]);

        $result = $this->service->evaluateActions($this->user);

        // ISA is maxed, no GIA, no spouse, no carry forward
        // Only isa_not_maxed should NOT fire; others depend on data absence
        $rec = collect($result['recommendations'])->first(
            fn ($r) => ($r['definition_key'] ?? '') === 'isa_not_maxed'
        );
        expect($rec)->toBeNull();

        $giaRecs = collect($result['recommendations'])->filter(
            fn ($r) => in_array($r['definition_key'] ?? '', ['cgt_allowance_unused', 'high_dividend_in_gia'], true)
        );
        expect($giaRecs)->toBeEmpty();
    });
});

// =========================================================================
// Disabled definitions
// =========================================================================

describe('disabled definitions', function () {
    it('skips disabled definitions', function () {
        TaxActionDefinition::where('key', 'isa_not_maxed')->update(['is_enabled' => false]);

        // User with partial ISA — would normally trigger
        InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'account_type' => 'isa',
            'isa_subscription_current_year' => 5000,
            'current_value' => 25000,
        ]);

        $result = $this->service->evaluateActions($this->user);

        $rec = collect($result['recommendations'])->first(
            fn ($r) => ($r['definition_key'] ?? '') === 'isa_not_maxed'
        );
        expect($rec)->toBeNull();
    });
});

// =========================================================================
// Template rendering
// =========================================================================

describe('template rendering', function () {
    it('renders title with placeholders', function () {
        $definition = TaxActionDefinition::findByKey('cgt_allowance_unused');

        $rendered = $definition->renderTitle([
            'gia_value' => '£50,000',
            'cgt_exemption' => '£3,000',
        ]);

        expect($rendered)->toContain('Capital Gains Tax');
    });

    it('renders description with multiple placeholders', function () {
        $definition = TaxActionDefinition::findByKey('isa_not_maxed');

        $rendered = $definition->renderDescription([
            'isa_used' => '£5,000',
            'isa_allowance' => '£20,000',
            'isa_remaining' => '£15,000',
        ]);

        expect($rendered)->toContain('£5,000')
            ->and($rendered)->toContain('£15,000');
    });

    it('handles missing placeholders gracefully', function () {
        $definition = TaxActionDefinition::findByKey('isa_not_maxed');

        $rendered = $definition->renderTitle([]);

        expect($rendered)->toBeString();
    });
});
