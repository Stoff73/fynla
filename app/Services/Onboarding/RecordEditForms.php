<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Agents\CoordinatingAgent;
use App\Models\CriticalIllnessPolicy;
use App\Models\Employment;
use App\Models\IncomeProtectionPolicy;
use App\Models\LifeInsurancePolicy;
use App\Models\Mortgage;
use App\Models\ProtectionProfile;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\AI\Fyn\RecaptureGuard;
use App\Services\Eval\EvalBypassGate;
use App\Services\Expenditure\HouseholdExpenditureWriter;
use App\Services\Income\EmploymentIncomeService;
use App\Services\Retirement\PensionContributionRule;
use App\Services\Stores\InvestmentAccountStore;
use App\Services\Stores\MortgageStore;
use App\Services\Stores\PensionStore;
use App\Services\Stores\PropertyStore;
use App\Services\Stores\SavingsStore;
use App\Services\Tiers\TeaserGate;
use App\Support\SharedExpenditure;
use App\Support\SharedOwnership;
use App\Traits\CalculatesOwnershipShare;
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
    use CalculatesOwnershipShare;

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
        'dependants' => 'children and dependants',
        'gifts' => 'gifts',
        'lpa' => 'Lasting Powers of Attorney',
    ];

    /**
     * Contextual resources that open straight on a record's form: the one
     * record the resource is (resource type => record type). A conversation
     * opened on one of these starts with that form (ContextualConversationService).
     */
    /** The record types Fyn can remove; every other form offers no Remove. */
    public const REMOVABLE_TYPES = ['savings_account', 'investment_account', 'dc_pension', 'db_pension', 'property', 'mortgage', 'life_insurance', 'critical_illness', 'income_protection'];

    public const CONTEXTUAL_FORMS = [
        'employer_benefits' => 'employer_benefits',
        // TODO item 6: "is it being paid?" has to be answerable from /m.
        'state_pension' => 'state_pension',
        // /m Expenditure's "Edit details": the spending form, as it was entered.
        'expenditure' => 'expenditure',
        // /m Personal Information's "Edit details": date of birth, gender and
        // marital status (Fyn capture is forms only, CSJ 2026-10-01).
        'personal_information' => 'personal',
    ];

    /** Contextual resources that are one saved record, opened on its own form. */
    public const RECORD_RESOURCES = ['savings_account', 'investment_account', 'dc_pension', 'property', 'life_insurance', 'critical_illness', 'income_protection'];

    /** The income source rows (/m Income detail) that the other-income form edits. */
    public const OTHER_INCOME_SOURCES = ['dividend', 'interest', 'trust', 'other'];

    public const OTHER_INCOME_LABEL = 'Dividend, interest, trust and other income';

    public function __construct(
        private readonly CoordinatingAgent $agent,
        private readonly TeaserGate $teaserGate,
        private readonly HouseholdExpenditureWriter $expenditureWriter,
    ) {}

    /**
     * The form a contextual resource opens on, or null (see CONTEXTUAL_FORMS).
     * An income source opens the form that edits it: the user's own
     * dividends, interest, trust or other income open the other-income form,
     * and earnings open the job's form when there is one job.
     *
     * @param  array<string, mixed>  $destinationParams  current_destination.params
     */
    public function formForResource(User $user, string $resourceType, array $destinationParams = [], ?int $resourceId = null): ?array
    {
        if ($resourceType === 'income') {
            return $this->formForIncomeSource($user, $destinationParams);
        }
        // A resource that is one saved record ("Edit details" on an account, a
        // pension, a property or a policy) opens that record's form, so a
        // typed change there is read into it (forms only, CSJ 2026-10-01; item 8).
        if ($resourceId !== null && in_array($resourceType, self::RECORD_RESOURCES, true)) {
            return $this->formFor($user, $resourceType, $resourceId);
        }
        $type = self::CONTEXTUAL_FORMS[$resourceType] ?? null;

        return $type === null ? null : $this->formFor($user, $type, (int) $user->id);
    }

    /** @param  array<string, mixed>  $params */
    private function formForIncomeSource(User $user, array $params): ?array
    {
        if (($params['income_owner'] ?? 'user') !== 'user') {
            return null;
        }
        $source = (string) ($params['income_source'] ?? '');
        if ($source === '') {
            // The Income overview: straight to the form when there is one
            // thing to change; otherwise chooserForResource offers them.
            $candidates = $this->candidates($user, 'income');

            return count($candidates) === 1 ? $this->formFor($user, $candidates[0]['type'], (int) $candidates[0]['id']) : null;
        }
        if (in_array($source, self::OTHER_INCOME_SOURCES, true)) {
            return $this->formFor($user, 'other_income', (int) $user->id);
        }
        if (in_array($source, ['employment', 'self_employment'], true)) {
            $jobs = $user->employments()->where('income_type', $source)->get(['id']);

            return $jobs->count() === 1 ? $this->formFor($user, 'employment', (int) $jobs->first()->id) : null;
        }

        return null;
    }

    /**
     * The choice a contextual resource opens on when it holds several records
     * and no one form: the Income overview's jobs and other income. The same
     * bubbles as the edit chooser, so a tap opens that record's form.
     *
     * @param  array<string, mixed>  $destinationParams
     * @return array{prompt: string, bubbles: list<array{id: string, label: string}>}|null
     */
    public function chooserForResource(User $user, string $resourceType, array $destinationParams = []): ?array
    {
        if ($resourceType !== 'income' || ($destinationParams['income_source'] ?? '') !== '') {
            return null;
        }
        $candidates = $this->candidates($user, 'income');
        if (count($candidates) < 2) {
            return null;
        }

        return ['prompt' => self::CHOOSER_PROMPT, 'bubbles' => self::chooserBubbles($candidates)];
    }

    public const CHOOSER_PROMPT = 'Which one needs changing?';

    /** A demo persona's form is never saved (isDemo). */
    public const DEMO_MESSAGE = 'This is a demo account, so changes are not saved. Create your own account to save your details.';

    /**
     * One bubble per record: "edit:<type>:<id>", which the director opens on
     * the record's form (OnboardingChatDirector::handleAction).
     *
     * @param  list<array{type: string, id: int, label: string}>  $candidates
     * @return list<array{id: string, label: string}>
     */
    public static function chooserBubbles(array $candidates): array
    {
        return array_map(static fn (array $candidate): array => ['id' => 'edit:'.$candidate['type'].':'.$candidate['id'], 'label' => $candidate['label']], $candidates);
    }

    /**
     * The blank forms a new record of a section goes on (Fyn's "add", an Add
     * button). Sections held on the user (personal details, the spouse) are
     * only ever changed, never added.
     */
    private const CREATE_FORMS = [
        'savings' => [CaptureForms::SAVINGS, CaptureForms::ISA],
        'investments' => [CaptureForms::INVESTMENT, CaptureForms::ISA],
        'pensions' => [CaptureForms::PENSION],
        'protection' => [CaptureForms::PROTECTION],
        'property' => [CaptureForms::PROPERTY],
        'estate' => [CaptureForms::PROPERTY],
        'income' => [CaptureForms::WORK],
        'expenditure' => [CaptureForms::EXPENDITURE],
        'dependants' => [CaptureForms::DEPENDANTS],
        // Item 9 (CSJ 2026-10-07): the estate how-tos record gifts and Lasting
        // Powers of Attorney in Fynla, through Fyn on every surface.
        'gifts' => [CaptureForms::GIFT],
        'lpa' => [CaptureForms::LPA],
    ];

    /** Sections that hold one record on the user: once saved, an "add" opens it. */
    public const SINGLE_RECORD_SECTIONS = ['personal', 'family', 'spouse', 'expenditure'];

    /**
     * The blank forms for a record type as Fyn's hand-off or an Add button
     * names it (sectionForEntityType); an ISA's own form first when it names
     * an ISA.
     *
     * @return list<string>
     */
    public static function createFormsFor(string $entityType): array
    {
        $forms = self::CREATE_FORMS[self::sectionForEntityType($entityType) ?? ''] ?? [];
        if (str_contains($entityType, 'isa') && in_array(CaptureForms::ISA, $forms, true)) {
            $forms = [CaptureForms::ISA, ...array_diff($forms, [CaptureForms::ISA])];
        }

        return array_values($forms);
    }

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
            'dc_pension', 'db_pension', 'pension', 'retirement' => 'pensions',
            'life_insurance', 'critical_illness', 'income_protection', 'protection_policy', 'protection', 'employer_benefits',
            'life_insurance_policy', 'critical_illness_policy', 'income_protection_policy' => 'protection',
            'property', 'mortgage' => 'property',
            'employment', 'work', 'work_details', 'income' => 'income',
            'spouse', 'spouse_household' => 'spouse',
            'expenditure', 'spending' => 'expenditure',
            'personal', 'personal_details' => 'personal',
            'dependant', 'dependants', 'family_member' => 'dependants',
            'gift', 'gifts', 'estate_gift' => 'gifts',
            'lpa', 'lasting_power_of_attorney', 'power_of_attorney' => 'lpa',
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
                // Always offered: the form's write is an upsert, and "none" is an answer.
                $rows[] = ['type' => 'employer_benefits', 'id' => (int) $user->id, 'label' => 'Your employer benefits'];
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
                // Always offered: the write sets figures on the profile, and
                // "none" is an answer.
                $rows[] = ['type' => 'other_income', 'id' => (int) $user->id, 'label' => self::OTHER_INCOME_LABEL];
                break;
            case 'spouse':
                // A married user can give their spouse's details before any
                // are saved: the form's write is an upsert.
                if (in_array((string) $user->marital_status, ['married', 'civil_partnership'], true)
                    || TaxStrategyHouseholdInput::where('user_id', $user->id)->exists()) {
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
            'expenditure' => $this->expenditureAnswers($model),
            'personal' => [CaptureForms::PERSONAL, CaptureForms::LEAD, array_filter([
                'date_of_birth' => $model->date_of_birth?->format('Y-m-d'),
                'gender' => $model->gender,
                'marital_status' => $model->marital_status,
                'smoking_status' => $model->smoking_status,
                'health_status' => $model->health_status,
            ]), 'Your details'],
            'employer_benefits' => [CaptureForms::EMPLOYER_BENEFITS, CaptureForms::LEAD, $this->employerBenefitsAnswers($model), 'Your employer benefits'],
            'state_pension' => [CaptureForms::STATE_PENSION, CaptureForms::LEAD, array_filter([
                // Only a "yes" is known: the column is NOT NULL DEFAULT 0, so a
                // false may never have been asked, and is left for the user to answer.
                'already_receiving' => $model->already_receiving ? 'yes' : null,
                'forecast_annual' => $model->state_pension_forecast_annual !== null ? (float) $model->state_pension_forecast_annual : null,
                'ni_years_completed' => $model->ni_years_completed,
            ], static fn ($v): bool => $v !== null), 'Your State Pension'],
            'other_income' => [CaptureForms::OTHER_INCOME, CaptureForms::LEAD, array_filter([
                'annual_dividend_income' => self::floatOrNull($model->annual_dividend_income),
                // A figure only when one is recorded: 0 means the Income page
                // uses what the accounts pay (IncomeDefinitionsService::interestIncome).
                'annual_interest_income' => (float) ($model->annual_interest_income ?? 0) > 0 ? (float) $model->annual_interest_income : null,
                'annual_trust_income' => self::floatOrNull($model->annual_trust_income),
                'annual_other_income' => self::floatOrNull($model->annual_other_income),
            ], static fn ($v): bool => $v !== null), 'Your dividend, interest, trust and other income'],
            default => [null, null, [], ''],
        };
        if ($formName === null) {
            return null;
        }

        $schema = self::editSchema($formName, $kindKey, ['type' => $type, 'id' => $id]);
        if ($schema === null) {
            return null;
        }
        if ($type === 'spouse_household') {
            $schema = CaptureForms::spouseHouseholdFor($user, $schema);
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
        // Remove is offered only where delete() will do it.
        $schema['removable'] = in_array($record['type'] ?? null, self::REMOVABLE_TYPES, true);
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
        if (self::isDemo($user)) {
            return ['success' => false, 'message' => self::DEMO_MESSAGE];
        }
        $type = (string) ($form['record']['type'] ?? '');
        $id = (int) ($form['record']['id'] ?? 0);
        $model = $this->find($user, $type, $id);
        if ($model === null) {
            return ['success' => false, 'message' => "I couldn't find that record any more."];
        }

        $result = match ($type) {
            'savings_account', 'investment_account', 'dc_pension', 'life_insurance', 'critical_illness', 'income_protection' => $this->updateRecord($user, $type, $id, $this->changedFields($user, $type, $id, $model, $form), $conversationId),
            'property' => $this->updateProperty($user, $model, $form, $conversationId),
            'employment' => $this->updateEmployment($user, $model, $form),
            'spouse_household' => $this->runTool('capture_spouse_household_data', CaptureForms::toolInputs($form)[CaptureForms::LEAD] ?? [], $user, $conversationId),
            'expenditure' => $this->runTool(
                (CaptureForms::schema((string) ($form['name'] ?? '')) ?? [])['tool'] === 'set_expenditure' ? 'set_expenditure' : 'capture_monthly_expenditure',
                CaptureForms::toolInputs($form)[CaptureForms::LEAD] ?? [], $user, $conversationId),
            'personal' => $this->runTool('capture_personal_details', CaptureForms::toolInputs($form)[CaptureForms::LEAD] ?? [], $user, $conversationId),
            'employer_benefits' => $this->runTool('capture_employer_benefits', CaptureForms::toolInputs($form)[CaptureForms::LEAD] ?? [], $user, $conversationId),
            'state_pension' => $this->runTool('capture_state_pension', CaptureForms::toolInputs($form)[CaptureForms::LEAD] ?? [], $user, $conversationId),
            'other_income' => $this->runTool('update_profile', ['section' => 'income_occupation', 'fields' => CaptureForms::toolInputs($form)[CaptureForms::LEAD] ?? []], $user, $conversationId),
            default => ['error' => true, 'message' => 'That record cannot be changed here.'],
        };

        if (($result['error'] ?? false) === true || (($result['success'] ?? false) !== true && ($result['onboarding_capture'] ?? false) !== true && ($result['updated'] ?? false) !== true)) {
            return ['success' => false, 'message' => (string) ($result['message'] ?? 'The change could not be saved.')];
        }

        $summary = $this->transcriptLine($user, $form);
        // A save that wrote nothing says so (SPEC-crud-handler-contract C5).
        if (($result['updated'] ?? null) === false) {
            return ['success' => true, 'message' => 'Already on file — '.($summary !== '' ? $summary : 'nothing changed.')];
        }

        return ['success' => true, 'message' => 'Updated — '.($summary !== '' ? $summary : 'saved.')];
    }

    /**
     * What the user changed: the posted form's fields set against the same
     * form opened on the stored record. The form derives fields the user never
     * sees (a name from the provider, a type from the kind), and writing those
     * renamed "Premium Bonds" to "NS&I easy access savings", turned a Junior
     * ISA into easy access and "David's SIPP" into "AJ Bell personal pension or
     * SIPP" on a save with nothing changed (51 of 67 local accounts, 2026-10-08).
     * A derived name is written only over the form's own name, so a name the
     * user typed elsewhere survives a change of provider.
     *
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    private function changedFields(User $user, string $type, int $id, Model $model, array $form): array
    {
        $posted = $this->recordFields($type, $form);
        $opened = $this->formFor($user, $type, $id);
        $before = $opened === null ? [] : $this->recordFields($type, $opened);

        $changed = array_filter(
            $posted,
            static fn (mixed $value, string $column): bool => ! array_key_exists($column, $before) || RecaptureGuard::differs($before[$column], $value),
            ARRAY_FILTER_USE_BOTH,
        );
        foreach (['account_name', 'scheme_name'] as $name) {
            if (array_key_exists($name, $changed) && ($before[$name] ?? null) !== $model->getAttribute($name)) {
                unset($changed[$name]);
            }
        }

        return $changed;
    }

    /**
     * A posted form in the user's words: the line saved as their message and
     * the sentence the read-back repeats. An edit form names the record's real
     * ownership (withStoredOwnership); any other form is CaptureForms' line.
     *
     * @param  array<string, mixed>  $form
     */
    public function transcriptLine(User $user, array $form): string
    {
        $record = $form['record'] ?? null;
        $model = is_array($record) ? $this->find($user, (string) ($record['type'] ?? ''), (int) ($record['id'] ?? 0)) : null;

        return CaptureForms::summarise($model === null ? $form : $this->withStoredOwnership($user, $model, $form));
    }

    /**
     * The read-back names the record's real ownership. The edit form carries
     * no ownership fields (editSchema), so the summary otherwise took the
     * capture form's "individual" default and called a joint account
     * individual. The share is the user's own (Rule 6: the stored percentage
     * is the primary owner's).
     *
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    private function withStoredOwnership(User $user, Model $model, array $form): array
    {
        $ownership = $model->getAttribute('ownership_type');
        $kind = array_key_first((array) ($form['answers'] ?? []));
        if ($ownership === null || ! is_string($kind) || ! is_array($form['answers'][$kind])) {
            return $form;
        }

        $form['answers'][$kind]['ownership_type'] = $ownership;
        if (SharedOwnership::isShared($ownership)) {
            $form['answers'][$kind]['ownership_percentage'] = round($this->userShareFraction($model, (int) $user->id) * 100, 2);
        }

        return $form;
    }

    /** @return array{success: bool, message: string} */
    public function delete(User $user, string $type, int $id, int $conversationId): array
    {
        if (self::isDemo($user)) {
            return ['success' => false, 'message' => self::DEMO_MESSAGE];
        }
        $model = $this->find($user, $type, $id);
        if ($model === null) {
            return ['success' => false, 'message' => "I couldn't find that record any more."];
        }
        $label = $this->labelFor($type, $model);
        if (! in_array($type, self::REMOVABLE_TYPES, true)) {
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

    /**
     * Spending opens on the form it was entered with: a breakdown on the
     * category form (Premium), anything else on the one box, so an edit never
     * writes the one box over a breakdown. Figures are the household's, as
     * both forms ask them: stored halves are doubled back first.
     *
     * @return array{0: string, 1: ?string, 2: array<string, mixed>, 3: string}
     */
    private function expenditureAnswers(User $user): array
    {
        $fields = [];
        foreach ([...SharedExpenditure::SHARED_FIELDS, 'rent', 'utilities', 'charitable_donations'] as $field) {
            if (is_numeric($user->getAttribute($field)) && (float) $user->getAttribute($field) > 0) {
                $fields[$field] = (float) $user->getAttribute($field);
            }
        }
        if ($this->expenditureWriter->dividesFor($user)) {
            $fields = SharedExpenditure::householdOf($fields);
        }

        if ($user->expenditure_entry_mode === 'category' && $this->teaserGate->allows($user, 'expenditure_detailed')) {
            $name = CaptureForms::expenditureDetailedNameFor($user);
            $answers = [];
            foreach (CaptureForms::schema($name)['kinds'] as $kind) {
                $given = [];
                foreach ($kind['fields'] as $field) {
                    if (isset($fields[$field])) {
                        $given[$field] = $fields[$field];
                    }
                }
                if ($given !== []) {
                    $answers[$kind['key']] = $given;
                }
            }

            return [$name, null, $answers, 'Your monthly spending'];
        }

        return [CaptureForms::EXPENDITURE, CaptureForms::LEAD, array_filter([
            'monthly_total' => $fields['monthly_expenditure'] ?? null,
            'childcare' => $fields['childcare'] ?? null,
            'charitable_donations' => $fields['charitable_donations'] ?? null,
            // NOT NULL DEFAULT false: only a "yes" is known.
            'is_gift_aid' => $user->is_gift_aid ? 'yes' : null,
        ], static fn ($v): bool => $v !== null), 'Your monthly spending'];
    }

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
        // A bond opens as its own kind, with what was paid in, when, and the
        // 5% taken (item 8).
        if (in_array($account->account_type, ['onshore_bond', 'offshore_bond'], true)) {
            return [CaptureForms::INVESTMENT, $account->account_type, array_filter([
                'provider' => $account->provider,
                'current_value' => (float) $account->current_value,
                'investment_amount' => self::floatOrNull($account->investment_amount ?? null),
                'bond_purchase_date' => $account->bond_purchase_date?->toDateString(),
                'bond_withdrawal_taken' => self::floatOrNull($account->bond_withdrawal_taken ?? null),
            ], static fn ($v): bool => $v !== null && $v !== ''), $label];
        }
        // A General Investment Account is stored as 'gia' (CoordinatingAgent
        // maps the form's personal_investment_account); checking only the
        // input alias opened every stored one as the "other" kind.
        $kind = in_array($account->account_type, ['gia', 'personal_investment_account'], true) ? 'gia' : 'other';
        $answers = ['provider' => $account->provider, 'current_value' => (float) $account->current_value];
        if (self::floatOrNull($account->annual_dividend_income ?? null) !== null) {
            $answers['annual_dividend_income'] = (float) $account->annual_dividend_income;
        }
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
        // One rule for workplace (scheme_type when set, else pension_type):
        // scheme_type is never 'occupational', so every workplace pension
        // used to open here as a personal one.
        $workplace = PensionContributionRule::isWorkplace($pension);
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
        if (! $workplace) {
            $answers += array_filter([
                'annual_drawdown_income' => self::floatOrNull($pension->annual_drawdown_income),
                'pcls_taken' => self::floatOrNull($pension->pcls_taken),
            ], static fn ($v): bool => $v !== null);
        }
        if (trim((string) $pension->beneficiary_name) !== '') {
            $answers['beneficiary_name'] = (string) $pension->beneficiary_name;
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
    /**
     * The employer benefits form filled from the profile: "No, none of these"
     * once the user has said so, the recorded figures otherwise.
     *
     * @return array<string, mixed>
     */
    private function employerBenefitsAnswers(Model $profile): array
    {
        $answers = array_filter([
            'employer_name' => $profile->employer_name,
            'death_in_service_multiple' => self::floatOrNull($profile->death_in_service_multiple),
            'group_ip_benefit_percent' => self::floatOrNull($profile->group_ip_benefit_percent),
            'group_ip_benefit_months' => $profile->group_ip_benefit_months,
            'group_ip_definition' => $profile->group_ip_definition,
            'group_ci_amount' => self::floatOrNull($profile->group_ci_amount),
            'has_employer_pmi' => $profile->has_employer_pmi ? 'yes' : null,
        ], static fn ($v): bool => $v !== null && $v !== '');
        if ($profile->employer_benefits_recorded_at !== null) {
            $answers['provides'] = count(array_diff_key($answers, ['employer_name' => true])) > 0 ? 'yes' : 'no';
        }

        return $answers;
    }

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
        // What they do opens as stored (item 11), so an edit never re-asks it.
        $status = in_array($row->spouse_employment_status, array_column(CaptureForms::SPOUSE_STATUS_OPTIONS, 'value'), true) ? $row->spouse_employment_status : null;
        $answers = [CaptureForms::LEAD => array_filter([
            'spouse_employment_status' => $status,
            'spouse_annual_income' => self::floatOrNull($row->spouse_annual_income),
            'spouse_annual_earnings' => self::floatOrNull($row->spouse_annual_earnings),
        ], static fn ($v, string $k): bool => $k === 'spouse_annual_income' || $v !== null, ARRAY_FILTER_USE_BOTH)];
        // Savings shown ticked when they hold any, so an edit keeps them.
        if ((float) ($row->spouse_existing_savings_balance ?? 0) > 0) {
            $answers['savings'] = array_filter([
                'spouse_existing_savings_balance' => self::floatOrNull($row->spouse_existing_savings_balance),
                'spouse_annual_savings_interest' => self::floatOrNull($row->spouse_annual_savings_interest),
            ], static fn ($v): bool => $v !== null);
        }
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
                // The column the form reads and the ISA allowance counts
                // (ISAContributionLedger), not contributions_ytd.
                'isa_subscription_current_year' => $input['isa_subscription_current_year'] ?? null,
                'annual_dividend_income' => $input['annual_dividend_income'] ?? null,
                'investment_amount' => $input['investment_amount'] ?? null,
                'bond_purchase_date' => $input['bond_purchase_date'] ?? null,
                'bond_withdrawal_taken' => $input['bond_withdrawal_taken'] ?? null,
            ], static fn ($v): bool => $v !== null),
            'dc_pension' => array_filter([
                'provider' => $input['provider'] ?? null,
                'scheme_name' => $input['scheme_name'] ?? null,
                'current_fund_value' => $input['current_fund_value'] ?? null,
                'employee_contribution_percent' => $input['employee_contribution_percent'] ?? null,
                'employer_contribution_percent' => $input['employer_contribution_percent'] ?? null,
                'salary_sacrifice' => $kindKey === 'workplace' ? ($input['salary_sacrifice'] ?? false) : null,
                'monthly_contribution_amount' => $input['monthly_contribution_amount'] ?? null,
                'annual_drawdown_income' => $input['annual_drawdown_income'] ?? null,
                'pcls_taken' => $input['pcls_taken'] ?? null,
                'beneficiary_name' => $input['beneficiary_name'] ?? null,
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
            return ['success' => true, 'updated' => false];
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
        $wrote = ($result['updated'] ?? null) !== false;

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
        if (($result['error'] ?? false) === true) {
            return $result;
        }

        // Two records behind one form: the save wrote if either did.
        return ['updated' => $wrote || ($result['updated'] ?? null) !== false] + $result;
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

    /**
     * A demo persona is shared by every visitor, so its forms never save; some
     * of these writes (a job) go straight to the record, not through a tool
     * that refuses a demo (7a, 2026-10-05). The eval bypass writes as a tool
     * call does (EvalBypassGate).
     */
    private static function isDemo(User $user): bool
    {
        return (bool) $user->is_preview_user && ! EvalBypassGate::isActive($user);
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
            'mortgage' => app(MortgageStore::class)->find($id, $user),
            'life_insurance' => LifeInsurancePolicy::where('id', $id)->where('user_id', $user->id)->first(),
            'critical_illness' => CriticalIllnessPolicy::where('id', $id)->where('user_id', $user->id)->first(),
            'income_protection' => IncomeProtectionPolicy::where('id', $id)->where('user_id', $user->id)->first(),
            'employment' => $user->employments()->where('id', $id)->first(),
            'spouse_household' => TaxStrategyHouseholdInput::firstOrNew(['user_id' => $user->id]),
            'expenditure', 'personal', 'other_income' => $user,
            'employer_benefits' => ProtectionProfile::firstOrNew(['user_id' => $user->id], ProtectionProfile::blankFor($user->id)),
            // One per user, so the user is the key (formForResource passes the user id).
            'state_pension' => $user->statePension()->first(),
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
            'employer_benefits' => 'your employer benefits',
            'state_pension' => 'your State Pension',
            'other_income' => 'your dividend, interest, trust and other income',
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
