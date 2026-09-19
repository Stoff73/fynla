<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Agents\CoordinatingAgent;
use App\Models\CriticalIllnessPolicy;
use App\Models\Employment;
use App\Models\IncomeProtectionPolicy;
use App\Models\LifeInsurancePolicy;
use App\Models\Mortgage;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Income\EmploymentIncomeService;
use App\Services\Stores\InvestmentAccountStore;
use App\Services\Stores\PensionStore;
use App\Services\Stores\PropertyStore;
use App\Services\Stores\SavingsStore;
use Illuminate\Database\Eloquent\Model;

/**
 * The one home for editing a record through a Fyn form (CSJ 2026-09-19,
 * September/September19Updates/azTest-plan.md Batch 4).
 *
 * Three doors reach it — the verify page's "No, change something", a
 * post-onboarding "change my …" in chat, and "Can I change that answer?"
 * mid-walk — and all three do the same thing: list what the user has in
 * the section, open the chosen record in its capture form with the values
 * filled in, and write the change through the existing update handlers.
 * "Add another" never comes here: an add is an add.
 *
 * Ownership is not editable on a form (UpdateRecordAllowlist rejects the
 * ownership columns on purpose), so the edit form drops those fields.
 */
final class RecordEditForms
{
    /** Section id => the capture forms whose records live in it. */
    public const SECTION_LABELS = [
        'savings' => 'bank and savings accounts',
        'investments' => 'investments',
        'pensions' => 'pensions',
        'protection' => 'protection policies',
        'estate' => 'property',
        'property' => 'property',
        'income' => 'work and income',
        'spouse' => 'spouse details',
        'expenditure' => 'spending',
        'personal' => 'personal details',
        'family' => 'personal details',
    ];

    public function __construct(private readonly CoordinatingAgent $agent) {}

    /** Every section a user can be offered to change, in walk order. */
    public static function sections(): array
    {
        return ['personal', 'income', 'savings', 'investments', 'pensions', 'protection', 'estate', 'spouse', 'expenditure'];
    }

    public static function sectionLabel(string $section): string
    {
        return self::SECTION_LABELS[$section] ?? 'details';
    }

    /** The section a record type belongs to, for the advice-side door. */
    public static function sectionForEntityType(string $type): ?string
    {
        return match ($type) {
            'savings_account', 'isa', 'savings', 'bank_account', 'cash_isa' => 'savings',
            'investment_account', 'investment', 'stocks_and_shares_isa', 'gia' => 'investments',
            'dc_pension', 'db_pension', 'pension' => 'pensions',
            'life_insurance', 'critical_illness', 'income_protection', 'protection_policy', 'protection' => 'protection',
            'property', 'mortgage' => 'property',
            'employment', 'work', 'work_details', 'income' => 'income',
            'spouse', 'spouse_household' => 'spouse',
            'expenditure', 'spending' => 'expenditure',
            'personal', 'personal_details' => 'personal',
            default => null,
        };
    }

    /**
     * What the user has in the section, labelled the way they would name it.
     *
     * @return list<array{type: string, id: int, label: string}>
     */
    public function candidates(User $user, string $section): array
    {
        $rows = [];
        switch ($section) {
            case 'savings':
                foreach (app(SavingsStore::class)->forUserWithJointOwner($user) as $account) {
                    $rows[] = ['type' => 'savings_account', 'id' => (int) $account->id, 'label' => self::accountLabel($account->account_name, $account->institution)];
                }
                break;
            case 'investments':
                foreach (app(InvestmentAccountStore::class)->forUserWithJointOwner($user) as $account) {
                    $rows[] = ['type' => 'investment_account', 'id' => (int) $account->id, 'label' => self::accountLabel($account->account_name, $account->provider)];
                }
                break;
            case 'pensions':
                foreach (app(PensionStore::class)->forUserByType($user, 'dc') as $pension) {
                    $rows[] = ['type' => 'dc_pension', 'id' => (int) $pension->id, 'label' => self::accountLabel($pension->scheme_name, $pension->provider)];
                }
                foreach (app(PensionStore::class)->forUserByType($user, 'db') as $pension) {
                    $rows[] = ['type' => 'db_pension', 'id' => (int) $pension->id, 'label' => self::accountLabel($pension->scheme_name, $pension->provider)];
                }
                break;
            case 'protection':
                foreach (LifeInsurancePolicy::where('user_id', $user->id)->get() as $policy) {
                    $rows[] = ['type' => 'life_insurance', 'id' => (int) $policy->id, 'label' => trim(($policy->provider ?? '').' life insurance')];
                }
                foreach (CriticalIllnessPolicy::where('user_id', $user->id)->get() as $policy) {
                    $rows[] = ['type' => 'critical_illness', 'id' => (int) $policy->id, 'label' => trim(($policy->provider ?? '').' critical illness cover')];
                }
                foreach (IncomeProtectionPolicy::where('user_id', $user->id)->get() as $policy) {
                    $rows[] = ['type' => 'income_protection', 'id' => (int) $policy->id, 'label' => trim(($policy->provider ?? '').' income protection')];
                }
                break;
            case 'estate':
            case 'property':
                // The campaign verify config names the section 'property'; the
                // journey path names it 'estate'. Same records.
                foreach (app(PropertyStore::class)->forUserWithJointOwner($user) as $property) {
                    $rows[] = ['type' => 'property', 'id' => (int) $property->id, 'label' => self::propertyLabel($property)];
                }
                break;
            case 'income':
                foreach ($user->employments()->orderBy('id')->get() as $job) {
                    $rows[] = ['type' => 'employment', 'id' => (int) $job->id, 'label' => trim(($job->employer ?: 'Your job').($job->occupation ? ', '.$job->occupation : ''))];
                }
                break;
            case 'spouse':
                if (TaxStrategyHouseholdInput::where('user_id', $user->id)->exists()) {
                    $rows[] = ['type' => 'spouse_household', 'id' => (int) $user->id, 'label' => "Your spouse's details"];
                }
                break;
            case 'expenditure':
                if ((float) ($user->monthly_expenditure ?? 0) > 0) {
                    $rows[] = ['type' => 'expenditure', 'id' => (int) $user->id, 'label' => 'Your monthly spending'];
                }
                break;
            case 'personal':
            case 'family':
                $rows[] = ['type' => 'personal', 'id' => (int) $user->id, 'label' => 'Your details'];
                break;
        }

        return $rows;
    }

    /**
     * The record's capture form with its values filled in, or null when the
     * type has no form (a defined benefit pension) — the typed edit remains.
     *
     * @return array{name: string, schema: array<string, mixed>, answers: array<string, array<string, mixed>>, record: array{type: string, id: int}, label: string}|null
     */
    public function formFor(User $user, string $type, int $id): ?array
    {
        $model = $this->find($user, $type, $id);
        if ($model === null) {
            return null;
        }

        [$formName, $kindKey, $answers, $label] = match ($type) {
            'savings_account' => $this->savingsAnswers($model),
            'investment_account' => $this->investmentAnswers($model),
            'dc_pension' => $this->pensionAnswers($model),
            'property' => $this->propertyAnswers($model),
            'life_insurance' => [CaptureForms::PROTECTION, 'life', $this->policyAnswers($model, 'sum_assured'), trim(($model->provider ?? '').' life insurance')],
            'critical_illness' => [CaptureForms::PROTECTION, 'critical', $this->policyAnswers($model, 'sum_assured'), trim(($model->provider ?? '').' critical illness cover')],
            'income_protection' => [CaptureForms::PROTECTION, 'income', $this->policyAnswers($model, 'benefit_amount'), trim(($model->provider ?? '').' income protection')],
            'employment' => [CaptureForms::WORK, CaptureForms::LEAD, array_filter([
                'employer' => $model->employer,
                'occupation' => $model->occupation,
                'annual_income' => $model->annual_income !== null ? (float) $model->annual_income : null,
            ], static fn ($v): bool => $v !== null && $v !== ''), trim(($model->employer ?: 'Your job').($model->occupation ? ', '.$model->occupation : ''))],
            'spouse_household' => [CaptureForms::SPOUSE_HOUSEHOLD, null, $this->spouseAnswers($model), "Your spouse's details"],
            'expenditure' => [CaptureForms::EXPENDITURE, CaptureForms::LEAD, ['monthly_total' => (float) $model->monthly_expenditure], 'Your monthly spending'],
            'personal' => [CaptureForms::PERSONAL, CaptureForms::LEAD, array_filter([
                'date_of_birth' => $model->date_of_birth?->format('Y-m-d'),
                'marital_status' => $model->marital_status,
            ]), 'Your details'],
            default => [null, null, [], ''],
        };
        if ($formName === null) {
            return null;
        }

        $schema = self::editSchema($formName, $kindKey, ['type' => $type, 'id' => $id]);
        if ($schema === null) {
            return null;
        }

        return [
            'name' => $formName,
            'schema' => $schema,
            'answers' => $kindKey === null ? $answers : [$kindKey => $answers],
            'record' => ['type' => $type, 'id' => $id],
            'label' => $label,
        ];
    }

    /**
     * The capture form narrowed to one record: only its kind, no ownership
     * fields, "Save changes" on the button, and the record it edits.
     *
     * @return array<string, mixed>|null
     */
    public static function editSchema(string $formName, ?string $kindKey, array $record): ?array
    {
        $schema = CaptureForms::schema($formName);
        if ($schema === null) {
            return null;
        }
        if ($kindKey !== null && $kindKey !== CaptureForms::LEAD) {
            $kinds = array_values(array_filter($schema['kinds'], static fn (array $kind): bool => $kind['key'] === $kindKey));
            if ($kinds === []) {
                return null;
            }
            $kinds[0]['fields'] = array_values(array_diff($kinds[0]['fields'], ['ownership_type', 'ownership_percentage']));
            $schema['kinds'] = $kinds;
        }
        unset($schema['kinds_prompt'], $schema['allow_empty']);
        $schema['submit_label'] = 'Save changes';
        $schema['edit'] = true;
        $schema['record'] = $record;

        return $schema;
    }

    /**
     * Write the change. `$form` is the posted form: name, answers and record.
     *
     * @param  array{name: string, answers: array<string, mixed>, record: array{type: string, id: int}}  $form
     * @return array{success: bool, message: string}
     */
    public function update(User $user, array $form, int $conversationId): array
    {
        $type = (string) ($form['record']['type'] ?? '');
        $id = (int) ($form['record']['id'] ?? 0);
        $model = $this->find($user, $type, $id);
        if ($model === null) {
            return ['success' => false, 'message' => "I couldn't find that record any more."];
        }

        $result = match ($type) {
            'savings_account', 'investment_account', 'dc_pension', 'life_insurance', 'critical_illness', 'income_protection' => $this->updateRecord($user, $type, $id, $this->recordFields($type, $form), $conversationId),
            'property' => $this->updateProperty($user, $model, $form, $conversationId),
            'employment' => $this->updateEmployment($user, $model, $form),
            'spouse_household' => $this->runTool('capture_spouse_household_data', CaptureForms::toolInputs($form)[CaptureForms::LEAD] ?? [], $user, $conversationId),
            'expenditure' => $this->runTool('capture_monthly_expenditure', CaptureForms::toolInputs($form)[CaptureForms::LEAD] ?? [], $user, $conversationId),
            'personal' => $this->runTool('capture_personal_details', CaptureForms::toolInputs($form)[CaptureForms::LEAD] ?? [], $user, $conversationId),
            default => ['error' => true, 'message' => 'That record cannot be changed here.'],
        };

        if (($result['error'] ?? false) === true || (($result['success'] ?? false) !== true && ($result['onboarding_capture'] ?? false) !== true && ($result['updated'] ?? false) !== true)) {
            return ['success' => false, 'message' => (string) ($result['message'] ?? 'The change could not be saved.')];
        }

        $summary = CaptureForms::summarise($form);

        return ['success' => true, 'message' => 'Updated — '.($summary !== '' ? $summary : 'saved.')];
    }

    /** @return array{success: bool, message: string} */
    public function delete(User $user, string $type, int $id, int $conversationId): array
    {
        $model = $this->find($user, $type, $id);
        if ($model === null) {
            return ['success' => false, 'message' => "I couldn't find that record any more."];
        }
        $label = $this->labelFor($type, $model);
        if (! in_array($type, ['savings_account', 'investment_account', 'dc_pension', 'db_pension', 'property', 'mortgage', 'life_insurance', 'critical_illness', 'income_protection'], true)) {
            return ['success' => false, 'message' => "{$label} can be changed but not removed here."];
        }
        $token = hash('sha256', $user->id.'|'.$type.'|'.$id.'|'.now()->format('Y-m-d'));
        $result = $this->runTool('delete_record', ['entity_type' => $type, 'entity_id' => $id, 'confirmation_token' => $token], $user, $conversationId);
        if (($result['error'] ?? false) === true || ($result['success'] ?? false) !== true) {
            return ['success' => false, 'message' => (string) ($result['message'] ?? 'The record could not be removed.')];
        }

        return ['success' => true, 'message' => "Removed {$label}."];
    }

    // ─── record → answers ────────────────────────────────────────────────

    /** @return array{0: string, 1: string, 2: array<string, mixed>, 3: string} */
    private function savingsAnswers(Model $account): array
    {
        $label = self::accountLabel($account->account_name, $account->institution);
        if ($account->account_type === 'cash_isa') {
            return [CaptureForms::ISA, 'cash_isa', array_filter([
                'provider' => $account->institution,
                'current_value' => (float) $account->current_balance,
                'paid_in_this_year' => self::floatOrNull($account->isa_subscription_amount ?? null),
                'interest_rate' => self::floatOrNull($account->interest_rate),
            ], static fn ($v): bool => $v !== null && $v !== ''), $label];
        }
        $kind = in_array($account->account_type, ['current_account', 'easy_access', 'fixed', 'notice'], true) ? $account->account_type : 'easy_access';
        $rateKey = $kind === 'current_account' ? 'current_account_interest_rate' : 'interest_rate';

        return [CaptureForms::SAVINGS, $kind, array_filter([
            'provider' => $account->institution,
            'current_value' => (float) $account->current_balance,
            $rateKey => self::floatOrNull($account->interest_rate),
        ], static fn ($v): bool => $v !== null && $v !== ''), $label];
    }

    /** @return array{0: string, 1: string, 2: array<string, mixed>, 3: string} */
    private function investmentAnswers(Model $account): array
    {
        $label = self::accountLabel($account->account_name, $account->provider);
        if ($account->isa_type !== null || $account->account_type === 'isa') {
            return [CaptureForms::ISA, 'stocks_shares_isa', array_filter([
                'provider' => $account->provider,
                'current_value' => (float) $account->current_value,
                'paid_in_this_year' => self::floatOrNull($account->isa_subscription_current_year ?? null),
            ], static fn ($v): bool => $v !== null && $v !== ''), $label];
        }
        $kind = $account->account_type === 'personal_investment_account' ? 'gia' : 'other';
        $answers = ['provider' => $account->provider, 'current_value' => (float) $account->current_value];
        if ($kind === 'other') {
            $type = trim(str_replace((string) $account->provider, '', (string) $account->account_name));
            if ($type !== '') {
                $answers['investment_type'] = $type;
            }
        }

        return [CaptureForms::INVESTMENT, $kind, $answers, $label];
    }

    /** @return array{0: string, 1: string, 2: array<string, mixed>, 3: string} */
    private function pensionAnswers(Model $pension): array
    {
        $label = self::accountLabel($pension->scheme_name, $pension->provider);
        $workplace = ($pension->scheme_type ?? '') === 'occupational';
        $answers = array_filter([
            'provider' => $pension->provider,
            'current_value' => self::floatOrNull($pension->current_fund_value),
        ], static fn ($v): bool => $v !== null && $v !== '');
        if ($workplace) {
            $answers += array_filter([
                'employee_contribution_percent' => self::floatOrNull($pension->employee_contribution_percent),
                'employer_contribution_percent' => self::floatOrNull($pension->employer_contribution_percent),
            ], static fn ($v): bool => $v !== null);
            $answers['salary_sacrifice'] = $pension->salary_sacrifice ? 'yes' : 'no';
        } elseif (self::floatOrNull($pension->monthly_contribution_amount) !== null) {
            $answers['annual_contribution'] = round(((float) $pension->monthly_contribution_amount) * 12, 2);
        }

        return [CaptureForms::PENSION, $workplace ? 'workplace' : 'personal', $answers, $label];
    }

    /** @return array{0: string, 1: string, 2: array<string, mixed>, 3: string} */
    private function propertyAnswers(Model $property): array
    {
        $kind = in_array($property->property_type, ['main_residence', 'secondary_residence', 'buy_to_let'], true) ? $property->property_type : 'main_residence';
        $mortgage = $property->mortgages()->orderBy('id')->first();
        $answers = [
            'current_value' => (float) $property->current_value,
            'mortgage_outstanding_balance' => $mortgage !== null ? (float) $mortgage->outstanding_balance : null,
        ];
        if ($kind === 'buy_to_let') {
            $answers['monthly_rental_income'] = (float) ($property->monthly_rental_income ?? 0);
        }

        return [CaptureForms::PROPERTY, $kind, $answers, self::propertyLabel($property)];
    }

    /** @return array<string, mixed> */
    private function policyAnswers(Model $policy, string $amountKey): array
    {
        return array_filter([
            'provider' => $policy->provider,
            $amountKey => self::floatOrNull($policy->{$amountKey}),
            'premium_amount' => self::floatOrNull($policy->premium_amount),
            'policy_term_years' => $policy->getAttribute('policy_term_years') !== null ? (int) $policy->getAttribute('policy_term_years') : null,
        ], static fn ($v): bool => $v !== null && $v !== '');
    }

    /** @return array<string, array<string, mixed>> */
    private function spouseAnswers(TaxStrategyHouseholdInput $row): array
    {
        $answers = [CaptureForms::LEAD => ['spouse_annual_income' => self::floatOrNull($row->spouse_annual_income)]];
        $isa = array_filter(['spouse_isa_balance' => self::floatOrNull($row->spouse_isa_balance), 'spouse_isa_provider' => $row->spouse_isa_provider], static fn ($v): bool => $v !== null && $v !== '');
        if ($isa !== []) {
            $answers['isa'] = $isa;
        }
        $pension = array_filter([
            'spouse_pension_input_annual' => self::floatOrNull($row->spouse_pension_input_annual),
            'spouse_existing_pension_balance' => self::floatOrNull($row->spouse_existing_pension_balance),
            'spouse_pension_provider' => $row->spouse_pension_provider,
        ], static fn ($v): bool => $v !== null && $v !== '');
        if ($pension !== []) {
            $answers['pension'] = $pension;
        }
        $investments = array_filter([
            'spouse_annual_dividends' => self::floatOrNull($row->spouse_annual_dividends),
            'spouse_unrealised_gains' => self::floatOrNull($row->spouse_unrealised_gains),
        ], static fn ($v): bool => $v !== null);
        if ($investments !== []) {
            $answers['investments'] = $investments;
        }

        return $answers;
    }

    // ─── answers → update ────────────────────────────────────────────────

    /**
     * The allowlisted update fields for a record-backed form answer.
     *
     * @return array<string, mixed>
     */
    private function recordFields(string $type, array $form): array
    {
        $inputs = CaptureForms::toolInputs($form);
        $input = reset($inputs) ?: [];
        $kindKey = (string) (array_key_first($inputs) ?? '');

        return match ($type) {
            'savings_account' => array_filter([
                'institution' => $input['institution'] ?? null,
                'account_name' => $input['account_name'] ?? null,
                'account_type' => $input['account_type'] ?? null,
                'current_balance' => $input['current_balance'] ?? null,
                'interest_rate' => $input['interest_rate'] ?? null,
                'isa_subscription_amount' => $input['isa_subscription_amount'] ?? null,
            ], static fn ($v): bool => $v !== null),
            'investment_account' => array_filter([
                'provider' => $input['provider'] ?? null,
                'account_name' => $input['account_name'] ?? null,
                'current_value' => $input['current_value'] ?? null,
                'contributions_ytd' => $input['isa_subscription_current_year'] ?? null,
            ], static fn ($v): bool => $v !== null),
            'dc_pension' => array_filter([
                'provider' => $input['provider'] ?? null,
                'scheme_name' => $input['scheme_name'] ?? null,
                'current_fund_value' => $input['current_fund_value'] ?? null,
                'employee_contribution_percent' => $input['employee_contribution_percent'] ?? null,
                'employer_contribution_percent' => $input['employer_contribution_percent'] ?? null,
                'salary_sacrifice' => $kindKey === 'workplace' ? ($input['salary_sacrifice'] ?? false) : null,
                'monthly_contribution_amount' => $input['monthly_contribution_amount'] ?? null,
            ], static fn ($v): bool => $v !== null),
            'life_insurance', 'critical_illness' => array_filter([
                'provider' => $input['provider'] ?? null,
                'sum_assured' => $input['sum_assured'] ?? null,
                'premium_amount' => $input['premium_amount'] ?? null,
                'premium_frequency' => isset($input['premium_amount']) ? 'monthly' : null,
            ], static fn ($v): bool => $v !== null),
            'income_protection' => array_filter([
                'provider' => $input['provider'] ?? null,
                'benefit_amount' => $input['benefit_amount'] ?? null,
                'premium_amount' => $input['premium_amount'] ?? null,
                'premium_frequency' => isset($input['premium_amount']) ? 'monthly' : null,
            ], static fn ($v): bool => $v !== null),
            default => [],
        };
    }

    /** @return array<string, mixed> */
    private function updateRecord(User $user, string $type, int $id, array $fields, int $conversationId): array
    {
        if ($fields === []) {
            return ['error' => true, 'message' => 'Nothing changed.'];
        }

        return $this->runTool('update_record', ['entity_type' => $type, 'entity_id' => $id, 'fields' => $fields], $user, $conversationId);
    }

    /**
     * A property edit is the property plus the mortgage secured on it: the
     * form shows one balance box, the database holds two records.
     *
     * @return array<string, mixed>
     */
    private function updateProperty(User $user, Model $property, array $form, int $conversationId): array
    {
        $inputs = CaptureForms::toolInputs($form);
        $input = reset($inputs) ?: [];
        $fields = array_filter([
            'current_value' => $input['current_value'] ?? null,
            'property_type' => $input['property_type'] ?? null,
            'monthly_rental_income' => $input['monthly_rental_income'] ?? null,
        ], static fn ($v): bool => $v !== null);
        $result = $this->runTool('update_record', ['entity_type' => 'property', 'entity_id' => (int) $property->id, 'fields' => $fields], $user, $conversationId);
        if (($result['error'] ?? false) === true) {
            return $result;
        }

        $mortgage = $property->mortgages()->orderBy('id')->first();
        $balance = ($input['has_mortgage'] ?? false) ? (float) $input['mortgage_outstanding_balance'] : null;
        if ($mortgage !== null && $balance !== null) {
            $result = $this->runTool('update_record', ['entity_type' => 'mortgage', 'entity_id' => (int) $mortgage->id, 'fields' => ['outstanding_balance' => $balance]], $user, $conversationId);
        } elseif ($mortgage !== null && $balance === null) {
            $token = hash('sha256', $user->id.'|mortgage|'.$mortgage->id.'|'.now()->format('Y-m-d'));
            $result = $this->runTool('delete_record', ['entity_type' => 'mortgage', 'entity_id' => (int) $mortgage->id, 'confirmation_token' => $token], $user, $conversationId);
        } elseif ($mortgage === null && $balance !== null) {
            $result = $this->runTool('create_mortgage', ['property_id' => (int) $property->id, 'outstanding_balance' => $balance], $user, $conversationId);
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function updateEmployment(User $user, Employment $job, array $form): array
    {
        $input = CaptureForms::toolInputs($form)[CaptureForms::LEAD] ?? [];
        app(EmploymentIncomeService::class)->updateJob(
            $user,
            $job,
            isset($input['employer']) ? (string) $input['employer'] : null,
            isset($input['occupation']) ? (string) $input['occupation'] : null,
            isset($input['annual_income']) ? (float) $input['annual_income'] : null,
        );

        return ['success' => true, 'updated' => true];
    }

    /** @return array<string, mixed> */
    private function runTool(string $tool, array $input, User $user, int $conversationId): array
    {
        try {
            return $this->agent->executeTool($tool, $input, $user, $conversationId);
        } catch (\Throwable $e) {
            report($e);

            return ['error' => true, 'message' => 'The change could not be saved. Please try again.'];
        }
    }

    // ─── lookups ─────────────────────────────────────────────────────────

    private function find(User $user, string $type, int $id): ?Model
    {
        return match ($type) {
            'savings_account' => app(SavingsStore::class)->find($id, $user),
            'investment_account' => app(InvestmentAccountStore::class)->find($id, $user),
            'dc_pension' => app(PensionStore::class)->find($id, 'dc', $user),
            'db_pension' => app(PensionStore::class)->find($id, 'db', $user),
            'property' => app(PropertyStore::class)->find($id, $user),
            'mortgage' => Mortgage::where('id', $id)->where('user_id', $user->id)->first(),
            'life_insurance' => LifeInsurancePolicy::where('id', $id)->where('user_id', $user->id)->first(),
            'critical_illness' => CriticalIllnessPolicy::where('id', $id)->where('user_id', $user->id)->first(),
            'income_protection' => IncomeProtectionPolicy::where('id', $id)->where('user_id', $user->id)->first(),
            'employment' => $user->employments()->where('id', $id)->first(),
            'spouse_household' => TaxStrategyHouseholdInput::where('user_id', $user->id)->first(),
            'expenditure', 'personal' => $user,
            default => null,
        };
    }

    private function labelFor(string $type, Model $model): string
    {
        return match ($type) {
            'savings_account' => self::accountLabel($model->account_name, $model->institution),
            'investment_account' => self::accountLabel($model->account_name, $model->provider),
            'dc_pension', 'db_pension' => self::accountLabel($model->scheme_name, $model->provider),
            'property' => self::propertyLabel($model),
            'life_insurance' => trim(($model->provider ?? '').' life insurance'),
            'critical_illness' => trim(($model->provider ?? '').' critical illness cover'),
            'income_protection' => trim(($model->provider ?? '').' income protection'),
            default => 'that record',
        };
    }

    private static function accountLabel(?string $name, ?string $provider): string
    {
        $name = trim((string) $name);
        if ($name !== '') {
            return $name;
        }

        return trim((string) $provider) !== '' ? trim((string) $provider) : 'Unnamed account';
    }

    private static function propertyLabel(Model $property): string
    {
        $address = trim((string) ($property->address_line_1 ?? ''));
        $type = match ($property->property_type) {
            'main_residence' => 'Home',
            'secondary_residence' => 'Second home',
            'buy_to_let' => 'Buy to let',
            default => 'Property',
        };
        if ($address === '' || in_array(strtolower($address), ['main residence', 'home', 'property'], true)) {
            return $type.' worth '.self::pounds((float) $property->current_value);
        }

        return $address;
    }

    private static function pounds(float $value): string
    {
        return '£'.number_format($value, 0);
    }

    private static function floatOrNull(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }
}
