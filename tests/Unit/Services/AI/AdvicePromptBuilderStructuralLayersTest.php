<?php

declare(strict_types=1);

use App\Constants\QuerySchemas;
use App\Models\LifeInsurancePolicy;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\AI\AdvicePromptBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Regression coverage for the structural-prompt-swap bug fixed by
 * deleting AdvicePromptBuilder::isNewUserWithNoData and the EmptyDataGuard
 * branch (April27Updates/eval-system-vs-live-flow-audit.md §2,
 * fixEvalTask.md Task 1).
 *
 * The contract: layers 5 (<financial_context>), 6 (<existing_records>),
 * and 7 (<data_completeness>) MUST be present in every assembled advice
 * prompt regardless of how sparse the user's data is. Empty / partial /
 * full users all produce the same prompt structure — sparse content is
 * surfaced via the existing KYC + DataReadiness machinery (Layer 7 +
 * Layer 9 kyc_status), never via a structural swap.
 *
 * The deleted <new_user_state> block must NEVER be present.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
    $this->builder = app(AdvicePromptBuilder::class);
    $this->classification = [
        'primary' => QuerySchemas::PROTECTION_COVER,
        'related' => [],
        'modules' => ['protection'],
    ];
});

describe('AdvicePromptBuilder structural layers — always render', function () {

    it('renders <financial_context> + <existing_records> + <data_completeness> for an empty user', function () {
        $user = User::factory()->create([
            'first_name' => 'Test',
            'date_of_birth' => null,
            'marital_status' => null,
            'employment_status' => null,
            'annual_employment_income' => null,
            'monthly_expenditure' => null,
        ]);

        $prompt = $this->builder->build(
            user: $user,
            classification: $this->classification,
            kycResult: null,
            currentRoute: null,
            isPreview: false,
            orchestrateAnalysis: null,
        );

        expect($prompt)
            ->toContain('<financial_context>')
            ->toContain('</financial_context>')
            ->toContain('<existing_records>')
            ->toContain('</existing_records>')
            ->toContain('<data_completeness>')
            ->toContain('</data_completeness>');
    });

    it('renders the same structural layers for a partial-data user (DOB + marital + protection only)', function () {
        // The case the deleted isNewUserWithNoData mis-classified: real records
        // exist (one protection policy) but none of the four signals the
        // function checked (income, savings, investments, pensions). Function
        // returned TRUE and the prompt was structurally swapped. Now: layers
        // render regardless.
        $user = User::factory()->create([
            'first_name' => 'Test',
            'date_of_birth' => '1985-04-15',
            'marital_status' => 'married',
            'annual_employment_income' => null,
        ]);

        LifeInsurancePolicy::factory()->create([
            'user_id' => $user->id,
            'sum_assured' => 100000,
        ]);

        $prompt = $this->builder->build(
            user: $user,
            classification: $this->classification,
            kycResult: null,
            currentRoute: null,
            isPreview: false,
            orchestrateAnalysis: null,
        );

        expect($prompt)
            ->toContain('<financial_context>')
            ->toContain('<existing_records>')
            ->toContain('<data_completeness>');
    });

    it('renders the same structural layers for a full-data user', function () {
        $user = User::factory()->create([
            'first_name' => 'Test',
            'date_of_birth' => '1985-04-15',
            'marital_status' => 'married',
            'employment_status' => 'employed',
            'annual_employment_income' => 50000,
            'monthly_expenditure' => 2500,
        ]);

        SavingsAccount::factory()->create(['user_id' => $user->id]);

        $prompt = $this->builder->build(
            user: $user,
            classification: $this->classification,
            kycResult: null,
            currentRoute: null,
            isPreview: false,
            orchestrateAnalysis: null,
        );

        expect($prompt)
            ->toContain('<financial_context>')
            ->toContain('<existing_records>')
            ->toContain('<data_completeness>');
    });

    it('passes the kyc_result prompt_text through to the assembled prompt verbatim', function () {
        // KycGateChecker is the source of truth for empty/partial-data
        // signalling now — the prompt builder is a transparent passthrough
        // for whatever Layer 9 content KycGateChecker produces.
        $user = User::factory()->create();

        $kycResult = [
            'passed' => false,
            'missing' => ['Date of birth', 'Annual income'],
            'prompt_text' => "<kyc_status>\nKYC CHECK: BLOCKED. Missing: Date of birth, Annual income.\n</kyc_status>",
        ];

        $prompt = $this->builder->build(
            user: $user,
            classification: $this->classification,
            kycResult: $kycResult,
            currentRoute: null,
            isPreview: false,
            orchestrateAnalysis: null,
        );

        expect($prompt)
            ->toContain('<kyc_status>')
            ->toContain('KYC CHECK: BLOCKED')
            ->toContain('Date of birth')
            ->toContain('Annual income');
    });
});

describe('AdvicePromptBuilder regression — EmptyDataGuard never re-introduced', function () {

    it('never emits the deleted <new_user_state> block — empty user', function () {
        $user = User::factory()->create([
            'date_of_birth' => null,
            'annual_employment_income' => null,
            'monthly_expenditure' => null,
        ]);

        $prompt = $this->builder->build(
            user: $user,
            classification: $this->classification,
            kycResult: null,
            currentRoute: null,
            isPreview: false,
            orchestrateAnalysis: null,
        );

        expect($prompt)
            ->not->toContain('<new_user_state>')
            ->not->toContain('ZERO financial data')
            ->not->toContain('THE FOLLOWING RULES OVERRIDE EVERY OTHER INSTRUCTION');
    });

    it('never emits the deleted <new_user_state> block — partial-data user', function () {
        $user = User::factory()->create([
            'date_of_birth' => '1985-04-15',
            'marital_status' => 'married',
        ]);

        LifeInsurancePolicy::factory()->create([
            'user_id' => $user->id,
            'sum_assured' => 100000,
        ]);

        $prompt = $this->builder->build(
            user: $user,
            classification: $this->classification,
            kycResult: null,
            currentRoute: null,
            isPreview: false,
            orchestrateAnalysis: null,
        );

        expect($prompt)
            ->not->toContain('<new_user_state>')
            ->not->toContain('ZERO financial data');
    });

    it('never emits the deleted <new_user_state> block — full-data user', function () {
        $user = User::factory()->create([
            'date_of_birth' => '1985-04-15',
            'annual_employment_income' => 50000,
            'monthly_expenditure' => 2500,
        ]);
        SavingsAccount::factory()->create(['user_id' => $user->id]);

        $prompt = $this->builder->build(
            user: $user,
            classification: $this->classification,
            kycResult: null,
            currentRoute: null,
            isPreview: false,
            orchestrateAnalysis: null,
        );

        expect($prompt)->not->toContain('<new_user_state>');
    });
});

describe('AdvicePromptBuilder billing layer — classification-gated', function () {

    it('omits <billing_guidance> for advice classifications', function () {
        $user = User::factory()->create([
            'date_of_birth' => '1985-04-15',
            'marital_status' => 'married',
        ]);

        $advicePrompt = $this->builder->build(
            user: $user,
            classification: ['primary' => QuerySchemas::PROTECTION_COVER, 'modules' => ['protection']],
            isPreview: false,
            orchestrateAnalysis: null,
        );

        expect($advicePrompt)
            ->not->toContain('<billing_guidance>')
            ->not->toContain('get_subscription_status')
            ->not->toContain('You have N invoice');
    });

    it('omits <billing_guidance> for general / out-of-remit / data-entry classifications', function () {
        $user = User::factory()->create();

        foreach ([QuerySchemas::GENERAL, QuerySchemas::OUT_OF_REMIT, QuerySchemas::DATA_ENTRY, QuerySchemas::NAVIGATION] as $primary) {
            $prompt = $this->builder->build(
                user: $user,
                classification: ['primary' => $primary, 'modules' => []],
                isPreview: false,
                orchestrateAnalysis: null,
            );

            expect($prompt)->not->toContain('<billing_guidance>');
        }
    });

    it('injects <billing_guidance> when classification is BILLING', function () {
        $user = User::factory()->create();

        $prompt = $this->builder->build(
            user: $user,
            classification: ['primary' => QuerySchemas::BILLING, 'modules' => []],
            isPreview: false,
            orchestrateAnalysis: null,
        );

        expect($prompt)
            ->toContain('<billing_guidance>')
            ->toContain('get_subscription_status')
            ->toContain('list_invoices')
            ->toContain('You have N invoice');
    });

    it('omits <billing_guidance> in preview mode even when classification is BILLING', function () {
        // Preview personas have no real subscription — the block would mislead.
        $user = User::factory()->create(['is_preview_user' => true]);

        $prompt = $this->builder->build(
            user: $user,
            classification: ['primary' => QuerySchemas::BILLING, 'modules' => []],
            isPreview: true,
            orchestrateAnalysis: null,
        );

        expect($prompt)->not->toContain('<billing_guidance>');
    });
});

describe('AdvicePromptBuilder — same code path produces same prompt structure', function () {

    it('produces identical wrapper-block structure for empty, partial, and full users', function () {
        $emptyUser = User::factory()->create([
            'date_of_birth' => null,
            'annual_employment_income' => null,
        ]);

        $partialUser = User::factory()->create([
            'date_of_birth' => '1985-04-15',
            'marital_status' => 'married',
        ]);
        LifeInsurancePolicy::factory()->create([
            'user_id' => $partialUser->id,
            'sum_assured' => 100000,
        ]);

        $fullUser = User::factory()->create([
            'date_of_birth' => '1985-04-15',
            'marital_status' => 'married',
            'employment_status' => 'employed',
            'annual_employment_income' => 50000,
            'monthly_expenditure' => 2500,
        ]);
        SavingsAccount::factory()->create(['user_id' => $fullUser->id]);

        $extractWrapperTags = function (string $prompt): array {
            // Pull every top-level <tag> seen in the prompt body so we can
            // assert the structural shape is identical across user states.
            preg_match_all('/<(\/)?([a-z_]+)>/', $prompt, $matches);

            return array_values(array_unique($matches[2]));
        };

        $emptyTags = $extractWrapperTags($this->builder->build(
            user: $emptyUser,
            classification: $this->classification,
            isPreview: false,
            orchestrateAnalysis: null,
        ));

        $partialTags = $extractWrapperTags($this->builder->build(
            user: $partialUser,
            classification: $this->classification,
            isPreview: false,
            orchestrateAnalysis: null,
        ));

        $fullTags = $extractWrapperTags($this->builder->build(
            user: $fullUser,
            classification: $this->classification,
            isPreview: false,
            orchestrateAnalysis: null,
        ));

        // Every wrapper tag must appear for every user type. Sparse data
        // surfaces as empty content inside the wrapper, never as a missing
        // wrapper.
        $required = ['financial_context', 'existing_records', 'data_completeness', 'user_profile'];

        foreach ($required as $tag) {
            expect($emptyTags)->toContain($tag);
            expect($partialTags)->toContain($tag);
            expect($fullTags)->toContain($tag);
        }

        // The deleted EmptyDataGuard tag must never appear in any of them.
        expect($emptyTags)->not->toContain('new_user_state');
        expect($partialTags)->not->toContain('new_user_state');
        expect($fullTags)->not->toContain('new_user_state');
    });
});
