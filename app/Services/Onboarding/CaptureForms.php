<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\User;
use Carbon\Carbon;

/**
 * The ONE home for the structured capture forms Fyn shows in the chat
 * (CSJ 2026-09-15: onboarding captures data in a shape we expect, through
 * the same store as the app form; the model and the extractor stay out
 * of it). One schema per form-capable step; the renderers on web and /m
 * draw the form from the schema and know nothing about property.
 *
 * Each kind names the create tool and entity type its rows go through.
 * Answer keys are the create tool's own field names so nothing is renamed
 * between the form and the store. `mortgage_outstanding_balance: null`
 * means "No mortgage".
 *
 * DO NOT SPLIT THIS FILE. It is long — 1,290-odd lines, 47 methods — and a
 * tech-debt pass will keep proposing to break it into CaptureForms\AccountForms,
 * ProfileForms, HouseholdForms behind a registry. CSJ ruled against that on
 * 2026-09-17 (ruling 53): every schema here is a standalone static method, so
 * the file is a LIST, not a tangle, and length alone is not a reason to move
 * 47 methods. Splitting also forces the shared helpers — pounds() (22 call
 * sites), singleWriteInputs() (7), percent() (6) — into public API on a base
 * class or trait, which is a real cost for a readability-only gain.
 *
 * Adding a form? Add a method here. That is the design, not the debt.
 */
final class CaptureForms
{
    public const PROPERTY = 'property';

    public const ISA = 'isa';

    public const SAVINGS = 'savings';

    public const INVESTMENT = 'investment';

    public const PENSION = 'pension';

    public const SPOUSE_HOUSEHOLD = 'spouse_household';

    public const SPOUSE_ASSETS = 'spouse_assets';

    /** Journey path: date of birth and marital status, ONE write through capture_personal_details. */
    public const PERSONAL = 'personal';

    /** Journey path: the spouse or partner's name, date of birth and email, ONE write through capture_spouse_details (creates and links their account). */
    public const SPOUSE_DETAILS = 'spouse_details';

    /** Journey path: one dependant per save through capture_dependants, looped by base_dependants_more. */
    public const DEPENDANTS = 'dependants';

    /** Employer, role and gross income, ONE write through capture_work_details (journey and Save Tax income step). */
    public const WORK = 'work';

    /** Save Tax and pension check: the date of birth alone, through capture_personal_details. */
    public const DOB = 'dob';

    /** The personal pension or SIPP alone — the pension form for users with no workplace scheme step. */
    public const PENSION_PERSONAL = 'pension_personal';

    /** Monthly spending as one figure (everyone), through capture_monthly_expenditure. */
    public const EXPENDITURE = 'expenditure';

    /** Journey path: life insurance, critical illness cover, income protection — one create_protection_policy per kind. */
    public const PROTECTION = 'protection';

    /** Monthly spending by category (Premium), through set_expenditure; a variant of EXPENDITURE. */
    public const EXPENDITURE_DETAILED = 'expenditure_detailed';

    /** The category form asking first whether the figures are the household's (a spouse is on file). */
    public const EXPENDITURE_DETAILED_HOUSEHOLD = 'expenditure_detailed_household';

    /** The pseudo-kind that holds a schema's lead fields (asked above the kind boxes). */
    public const LEAD = '_lead';

    private const MONEY_MAX = '999999999.99';

    private const MONTHLY_MAX = '999999.99';

    /** @return list<string> */
    public static function names(): array
    {
        return [self::PROPERTY, self::ISA, self::SAVINGS, self::INVESTMENT, self::PENSION, self::SPOUSE_HOUSEHOLD, self::SPOUSE_ASSETS, self::PERSONAL, self::SPOUSE_DETAILS, self::DEPENDANTS, self::WORK, self::DOB, self::PENSION_PERSONAL, self::EXPENDITURE, self::EXPENDITURE_DETAILED, self::EXPENDITURE_DETAILED_HOUSEHOLD, self::PROTECTION];
    }

    /** @return array<string, mixed>|null */
    public static function schema(string $name): ?array
    {
        return match ($name) {
            self::PROPERTY => self::property(),
            self::ISA => self::isa(),
            self::SAVINGS => self::savings(),
            self::INVESTMENT => self::investment(),
            self::PENSION => self::pension(),
            self::SPOUSE_HOUSEHOLD => self::spouseHousehold(),
            self::SPOUSE_ASSETS => self::spouseAssets(),
            self::PERSONAL => self::personal(),
            self::SPOUSE_DETAILS => self::spouseDetails(),
            self::DEPENDANTS => self::dependants(),
            self::WORK => self::work(),
            self::DOB => self::dob(),
            self::PENSION_PERSONAL => self::pensionPersonal(),
            self::EXPENDITURE => self::expenditure(),
            self::EXPENDITURE_DETAILED => self::expenditureDetailed(false),
            self::EXPENDITURE_DETAILED_HOUSEHOLD => self::expenditureDetailed(true),
            self::PROTECTION => self::protection(),
            default => null,
        };
    }

    public static function kindLabel(string $name, string $kind): string
    {
        return self::kind($name, $kind)['label'] ?? $kind;
    }

    /**
     * One kind's definition: key, label, fields, and the create tool and
     * entity type its rows go through (a cash ISA is a savings row, a
     * stocks and shares ISA an investment row, so the tool is per kind).
     *
     * @return array<string, mixed>|null
     */
    public static function kind(string $name, string $kind): ?array
    {
        foreach (self::schema($name)['kinds'] ?? [] as $entry) {
            if ($entry['key'] === $kind) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Laravel rules keyed relative to `form.answers`. A kind's fields are
     * required only when that kind was filled (`required_with:<kind>`);
     * the mortgage must be present but may be null ("No mortgage").
     *
     * @return array<string, list<string>>
     */
    public static function rules(string $name): array
    {
        $schema = self::schema($name);
        if ($schema === null) {
            return [];
        }

        $rules = [];
        foreach ($schema['lead_fields'] ?? [] as $fieldKey) {
            $rules[self::LEAD.'.'.$fieldKey] = self::fieldRules(self::LEAD, $fieldKey, $schema['fields'][$fieldKey]);
        }
        foreach ($schema['kinds'] as $kind) {
            foreach ($kind['fields'] as $fieldKey) {
                $rules[$kind['key'].'.'.$fieldKey] = self::fieldRules($kind['key'], $fieldKey, $schema['fields'][$fieldKey]);
            }
        }

        return $rules;
    }

    /**
     * The rules for one field of one kind. A required field is required
     * only when its kind was filled; an optional one is nullable. A
     * percent's bounds come from the field (`min`/`max`), defaulting to
     * the ownership-share range.
     *
     * @param  array<string, mixed>  $field
     * @return list<string>
     */
    public static function fieldRules(string $kindKey, string $fieldKey, array $field): array
    {
        $presence = ($field['required'] ?? false) ? 'required_with:'.$kindKey : 'nullable';

        return match ($field['type']) {
            'money' => [$presence, 'numeric', 'min:0', 'max:'.($fieldKey === 'monthly_rental_income' ? self::MONTHLY_MAX : self::MONEY_MAX)],
            'money_or_none' => ['present', 'nullable', 'numeric', 'min:0', 'max:'.self::MONEY_MAX],
            'choice' => [$presence, 'in:'.implode(',', array_column($field['options'], 'value'))],
            'percent' => [$presence, 'numeric', 'min:'.($field['min'] ?? '0.01'), 'max:'.($field['max'] ?? '99.99')],
            'text' => [$presence, 'string', 'max:255'],
            'date' => [$presence, 'date_format:Y-m-d'],
            'email' => [$presence, 'email', 'max:255'],
        };
    }

    /**
     * One create-tool input per filled kind, in the handler's own field
     * names, keyed by kind. The shape is per schema (see the *Inputs
     * methods); unstated optional fields are omitted, never null
     * (PropertyNormaliser NOT NULL trap, 2026-09-15).
     *
     * @param  array{name: string, answers: array<string, array<string, mixed>>}  $form
     * @return array<string, array<string, mixed>>
     */
    public static function toolInputs(array $form): array
    {
        $schema = self::schema((string) ($form['name'] ?? ''));
        if ($schema === null) {
            return [];
        }

        // A single-write schema (the spouse forms) folds every section into
        // ONE tool input, keyed by the schema's own tool.
        if (isset($schema['tool'])) {
            $input = self::singleWriteInputs($schema, (array) ($form['answers'] ?? []));
            if ($input === [] && ! empty($schema['allow_empty'])) {
                // Nothing chosen is itself the answer ("nothing in their own
                // name"): every holding is recorded as zero, not left unknown.
                foreach ($schema['kinds'] as $kind) {
                    foreach ($kind['fields'] as $fieldKey) {
                        if ($schema['fields'][$fieldKey]['type'] === 'money') {
                            $input[$fieldKey] = 0.0;
                        }
                    }
                }
            }

            if ($input !== [] && $schema['name'] === self::DEPENDANTS) {
                // capture_dependants takes a list; the form saves one per turn.
                $input = ['dependants' => [$input]];
            }

            return $input === [] ? [] : [self::LEAD => $input];
        }

        $inputs = [];
        foreach ($schema['kinds'] as $kind) {
            $answers = $form['answers'][$kind['key']] ?? null;
            if (! is_array($answers)) {
                continue;
            }

            $inputs[$kind['key']] = match ($schema['name']) {
                self::PROPERTY => self::propertyInputs($kind, $answers),
                self::ISA => self::isaInputs($kind, $answers),
                self::SAVINGS => self::savingsInputs($kind, $answers),
                self::INVESTMENT => self::investmentInputs($kind, $answers),
                self::PENSION, self::PENSION_PERSONAL => self::pensionInputs($kind, $answers),
                self::PROTECTION => self::protectionInputs($kind, $answers),
            };
        }

        return $inputs;
    }

    /**
     * The transcript line saved as the user's message — plain words, so the
     * conversation reads naturally and the accuracy gate's text evidence
     * names each kind and its ownership. One sentence per filled kind,
     * shaped per schema (see the *Sentence methods).
     */
    public static function summarise(array $form): string
    {
        $schema = self::schema((string) ($form['name'] ?? ''));
        if ($schema === null) {
            return '';
        }

        if (isset($schema['tool'])) {
            return match ($schema['name']) {
                self::PERSONAL, self::DOB => self::personalSentence(self::singleWriteInputs($schema, (array) ($form['answers'] ?? []))),
                self::SPOUSE_DETAILS => self::spouseDetailsSentence(self::singleWriteInputs($schema, (array) ($form['answers'] ?? []))),
                self::DEPENDANTS => self::dependantSentence(self::singleWriteInputs($schema, (array) ($form['answers'] ?? []))),
                self::EXPENDITURE, self::EXPENDITURE_DETAILED, self::EXPENDITURE_DETAILED_HOUSEHOLD => self::expenditureSentence($schema, self::singleWriteInputs($schema, (array) ($form['answers'] ?? []))),
                self::WORK => self::workSentence(self::singleWriteInputs($schema, (array) ($form['answers'] ?? []))),
                default => self::spouseSentence($schema, (array) ($form['answers'] ?? [])),
            };
        }

        $sentences = [];
        foreach (self::toolInputs($form) as $kindKey => $input) {
            $label = self::kindLabel($schema['name'], $kindKey);
            $sentences[] = match ($schema['name']) {
                self::PROPERTY => self::propertySentence($label, $input),
                self::ISA => self::isaSentence($label, $input),
                self::SAVINGS => self::savingsSentence($label, $input),
                self::INVESTMENT => self::investmentSentence($label, $input),
                self::PENSION, self::PENSION_PERSONAL => self::pensionSentence($label, $input),
                self::PROTECTION => self::protectionSentence($label, $input),
            };
        }

        // Nothing chosen on a form that allows it is the answer "none" — the
        // transcript line says so in the user's own voice.
        if ($sentences === [] && ! empty($schema['allow_empty'])) {
            return 'I have none of these.';
        }

        return implode(' ', $sentences);
    }

    /**
     * create_property input. A share travels only with a shared ownership;
     * a shared ownership without a share is 50.
     *
     * @param  array<string, mixed>  $kind
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    private static function propertyInputs(array $kind, array $answers): array
    {
        $input = ['property_type' => $kind['key'], 'current_value' => (float) $answers['current_value']];
        $mortgage = $answers['mortgage_outstanding_balance'] ?? null;
        $input['has_mortgage'] = is_numeric($mortgage) && (float) $mortgage > 0;
        if ($input['has_mortgage']) {
            $input['mortgage_outstanding_balance'] = (float) $mortgage;
        }
        if (in_array('monthly_rental_income', $kind['fields'], true) && is_numeric($answers['monthly_rental_income'] ?? null)) {
            $input['monthly_rental_income'] = (float) $answers['monthly_rental_income'];
        }
        $ownership = (string) ($answers['ownership_type'] ?? 'individual');
        $input['ownership_type'] = $ownership;
        if (in_array($ownership, ['joint', 'tenants_in_common'], true)) {
            $share = $answers['ownership_percentage'] ?? null;
            $input['ownership_percentage'] = is_numeric($share) ? (float) $share : 50.0;
        }

        return $input;
    }

    /** @param  array<string, mixed>  $input */
    private static function propertySentence(string $label, array $input): string
    {
        $parts = [$label.' worth '.self::pounds($input['current_value'])];
        $parts[] = $input['has_mortgage']
            ? 'mortgage '.self::pounds($input['mortgage_outstanding_balance'])
            : 'no mortgage';
        if (isset($input['monthly_rental_income'])) {
            $parts[] = 'rent '.self::pounds($input['monthly_rental_income']).' a month';
        }
        $parts[] = str_replace('_', ' ', $input['ownership_type']);
        if (isset($input['ownership_percentage'])) {
            $parts[] = 'my share '.self::percent($input['ownership_percentage']);
        }

        return implode(', ', $parts).'.';
    }

    /**
     * create_savings_account (cash ISA) or create_investment_account (the
     * other ISA kinds). ISAs are individual by law, so the form has no
     * ownership field and the input states individual outright.
     *
     * @param  array<string, mixed>  $kind
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    private static function isaInputs(array $kind, array $answers): array
    {
        $provider = trim((string) $answers['provider']);
        $paidIn = $answers['paid_in_this_year'] ?? null;

        if ($kind['key'] === 'cash_isa') {
            $input = [
                'account_name' => $provider.' '.$kind['label'],
                'account_type' => 'cash_isa',
                'is_isa' => true,
                'institution' => $provider,
                'current_balance' => (float) $answers['current_value'],
                'ownership_type' => 'individual',
            ];
            if (is_numeric($answers['interest_rate'] ?? null)) {
                $input['interest_rate'] = (float) $answers['interest_rate'];
            }
            if (is_numeric($paidIn)) {
                $input['isa_subscription_amount'] = (float) $paidIn;
            }

            return $input;
        }

        $input = [
            'account_name' => $provider.' '.$kind['label'],
            'account_type' => $kind['key'],
            'isa_type' => $kind['isa_type'],
            'provider' => $provider,
            'current_value' => (float) $answers['current_value'],
            'ownership_type' => 'individual',
        ];
        if (is_numeric($paidIn)) {
            $input['isa_subscription_current_year'] = (float) $paidIn;
        }

        return $input;
    }

    /**
     * create_savings_account. A joint bank account is always 50/50 (CSJ
     * 2026-09-15), so the form asks no share and the input states 50.
     *
     * @param  array<string, mixed>  $kind
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    private static function savingsInputs(array $kind, array $answers): array
    {
        $provider = trim((string) $answers['provider']);
        $input = [
            'account_name' => $provider.' '.lcfirst($kind['label']),
            'account_type' => $kind['key'],
            'institution' => $provider,
            'current_balance' => (float) $answers['current_value'],
            'ownership_type' => (string) ($answers['ownership_type'] ?? 'individual'),
        ];
        // The rate field is required on the savings kinds and optional on
        // the current account, so the two carry different field keys that
        // map to the one tool input.
        $rate = $answers['interest_rate'] ?? $answers['current_account_interest_rate'] ?? null;
        if (is_numeric($rate)) {
            $input['interest_rate'] = (float) $rate;
        }
        if ($input['ownership_type'] === 'joint') {
            $input['ownership_percentage'] = 50.0;
        }

        return $input;
    }

    /**
     * create_investment_account. A share travels only with a joint
     * ownership; joint without a share is 50.
     *
     * @param  array<string, mixed>  $kind
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    private static function investmentInputs(array $kind, array $answers): array
    {
        $provider = trim((string) $answers['provider']);
        // "Other investment" may say what it is; that becomes the name.
        $type = trim((string) ($answers['investment_type'] ?? ''));
        $input = [
            'account_name' => $provider.' '.($type !== '' ? $type : $kind['label']),
            'account_type' => $kind['account_type'],
            'provider' => $provider,
            'current_value' => (float) $answers['current_value'],
            'ownership_type' => (string) ($answers['ownership_type'] ?? 'individual'),
        ];
        if ($input['ownership_type'] === 'joint') {
            $share = $answers['ownership_percentage'] ?? null;
            $input['ownership_percentage'] = is_numeric($share) ? (float) $share : 50.0;
        }

        return $input;
    }

    /** @param  array<string, mixed>  $input */
    private static function isaSentence(string $label, array $input): string
    {
        $provider = $input['institution'] ?? $input['provider'];
        $parts = [$label.' with '.$provider.', balance '.self::pounds($input['current_balance'] ?? $input['current_value'])];
        if (isset($input['interest_rate'])) {
            $parts[] = self::percent($input['interest_rate']).' interest';
        }
        $paidIn = $input['isa_subscription_amount'] ?? $input['isa_subscription_current_year'] ?? null;
        if ($paidIn !== null) {
            $parts[] = self::pounds($paidIn).' paid in this year';
        }

        return implode(', ', $parts).'.';
    }

    /** @param  array<string, mixed>  $input */
    private static function savingsSentence(string $label, array $input): string
    {
        $parts = [$input['institution'].' '.lcfirst($label).', balance '.self::pounds($input['current_balance'])];
        if (isset($input['interest_rate'])) {
            $parts[] = self::percent($input['interest_rate']).' interest';
        }
        $parts[] = $input['ownership_type'];

        return implode(', ', $parts).'.';
    }

    /** @param  array<string, mixed>  $input */
    private static function investmentSentence(string $label, array $input): string
    {
        // The name carries the typed type for "Other investment" ("Freetrade shares").
        $what = str_starts_with($input['account_name'], $input['provider'].' ') && ! str_ends_with($input['account_name'], $label)
            ? ucfirst(substr($input['account_name'], strlen($input['provider']) + 1))
            : $label;
        $parts = [$what.' with '.$input['provider'].' worth '.self::pounds($input['current_value']), $input['ownership_type']];
        if (isset($input['ownership_percentage'])) {
            $parts[] = 'my share '.self::percent($input['ownership_percentage']);
        }

        return implode(', ', $parts).'.';
    }

    /**
     * create_pension (defined contribution). A workplace pension carries the
     * contribution percentages and whether it is salary sacrifice; a
     * personal pension or SIPP carries what the user pays in each year
     * (stored monthly). Unknown values are omitted, never null.
     *
     * @param  array<string, mixed>  $kind
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    private static function pensionInputs(array $kind, array $answers): array
    {
        $provider = trim((string) $answers['provider']);
        $input = [
            'pension_category' => 'dc',
            'scheme_name' => $provider.' '.lcfirst($kind['label']),
            'scheme_type' => $kind['scheme_type'],
            'provider' => $provider,
        ];
        if (is_numeric($answers['current_value'] ?? null)) {
            $input['current_fund_value'] = (float) $answers['current_value'];
        }
        if ($kind['key'] === 'workplace') {
            $input['employee_contribution_percent'] = (float) $answers['employee_contribution_percent'];
            if (is_numeric($answers['employer_contribution_percent'] ?? null)) {
                $input['employer_contribution_percent'] = (float) $answers['employer_contribution_percent'];
            }
            $input['salary_sacrifice'] = ($answers['salary_sacrifice'] ?? 'no') === 'yes';
        } elseif (is_numeric($answers['annual_contribution'] ?? null)) {
            $input['monthly_contribution_amount'] = round(((float) $answers['annual_contribution']) / 12, 2);
        }
        foreach (['annual_drawdown_income', 'pcls_taken'] as $field) {
            if (is_numeric($answers[$field] ?? null)) {
                $input[$field] = (float) $answers[$field];
            }
        }

        return $input;
    }

    /** @param  array<string, mixed>  $input */
    private static function pensionSentence(string $label, array $input): string
    {
        $parts = [$label.' with '.$input['provider']];
        if (isset($input['current_fund_value'])) {
            $parts[] = 'worth '.self::pounds($input['current_fund_value']);
        }
        if (isset($input['employee_contribution_percent'])) {
            $contrib = 'I pay '.self::percent($input['employee_contribution_percent']);
            if (isset($input['employer_contribution_percent'])) {
                $contrib .= ' and my employer '.self::percent($input['employer_contribution_percent']);
            }
            $parts[] = $contrib;
            $parts[] = ($input['salary_sacrifice'] ?? false) ? 'salary sacrifice' : 'not salary sacrifice';
        }
        if (isset($input['monthly_contribution_amount'])) {
            $parts[] = 'I pay in '.self::pounds($input['monthly_contribution_amount'] * 12).' a year';
        }

        return implode(', ', $parts).'.';
    }

    /**
     * The single-write forms — personal, spouse details, date of birth,
     * dependants, work and both expenditure variants — write ONE row: every
     * filled section maps its answers onto the tool's own field names (each
     * form field is named after the tool field it feeds). Blank optional
     * answers are omitted. Named spouseInputs() until it outgrew the spouse
     * forms it was written for (CSJ 2026-09-17).
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, array<string, mixed>>  $answers
     * @return array<string, mixed>
     */
    private static function singleWriteInputs(array $schema, array $answers): array
    {
        $input = [];
        $sections = array_merge([self::LEAD => $schema['lead_fields'] ?? []], array_column($schema['kinds'], 'fields', 'key'));
        foreach ($sections as $sectionKey => $fieldKeys) {
            $given = $answers[$sectionKey] ?? null;
            if (! is_array($given)) {
                continue;
            }
            foreach ($fieldKeys as $fieldKey) {
                $value = $given[$fieldKey] ?? null;
                $type = $schema['fields'][$fieldKey]['type'];
                if (in_array($type, ['text', 'choice', 'date', 'email'], true)) {
                    $value = trim((string) $value);
                    if ($value !== '') {
                        $input[$fieldKey] = $value;
                    }
                } elseif (is_numeric($value)) {
                    $input[$fieldKey] = (float) $value;
                }
            }
        }

        return $input;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, array<string, mixed>>  $answers
     */
    private static function spouseSentence(array $schema, array $answers): string
    {
        $input = self::singleWriteInputs($schema, $answers);
        $parts = [];
        // "I don't know" posts the income as null (money_or_none); it is an
        // answer, not an omission, and the read-back says so.
        $lead = is_array($answers[self::LEAD] ?? null) ? $answers[self::LEAD] : [];
        $incomeUnknown = array_key_exists('spouse_annual_income', $lead) && $lead['spouse_annual_income'] === null;
        if (isset($input['spouse_annual_income'])) {
            $parts[] = 'my spouse earns '.self::pounds($input['spouse_annual_income']).' a year';
        } elseif ($incomeUnknown) {
            $parts[] = "I don't know what my spouse earns";
        }
        $money = [
            'spouse_isa_balance' => 'ISAs', 'spouse_existing_isa_balance' => 'ISAs',
            'spouse_existing_savings_balance' => 'savings', 'spouse_existing_investment_balance' => 'investments',
            'spouse_existing_dividend_holdings_value' => 'dividend-paying shares', 'spouse_existing_pension_balance' => 'their pension',
        ];
        foreach ($money as $key => $noun) {
            if (isset($input[$key])) {
                $parts[] = self::pounds($input[$key]).' in '.$noun.($key === 'spouse_isa_balance' && isset($input['spouse_isa_provider']) ? ' with '.$input['spouse_isa_provider'] : '');
            }
        }
        if (isset($input['spouse_pension_input_annual'])) {
            $parts[] = 'pays '.self::pounds($input['spouse_pension_input_annual']).' a year into their pension'.(isset($input['spouse_pension_provider']) ? ' with '.$input['spouse_pension_provider'] : '');
        }
        if (isset($input['spouse_annual_dividends'])) {
            $parts[] = self::pounds($input['spouse_annual_dividends']).' a year in dividends';
        }
        if (isset($input['spouse_unrealised_gains'])) {
            $parts[] = self::pounds($input['spouse_unrealised_gains']).' of gains not yet realised';
        }
        if ($parts === []) {
            return $schema['name'] === self::SPOUSE_ASSETS ? 'My spouse has nothing in their own name.' : 'My spouse has no income or holdings to add.';
        }
        if ($incomeUnknown && count($parts) === 1) {
            return "I don't know what my spouse earns, and they have no holdings to add.";
        }

        return ucfirst(implode(', ', $parts)).'.';
    }

    private static function percent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2), '0'), '.').'%';
    }

    /**
     * Pence kept, trailing zeros trimmed — these figures are read back to
     * the user on the form they typed them into. The conversational recaps
     * round instead (OnboardingChatDirector::wholePounds()).
     */
    private static function pounds(float $amount): string
    {
        return '£'.rtrim(rtrim(number_format($amount, 2), '0'), '.');
    }

    /** @return array<string, mixed> */
    private static function property(): array
    {
        return [
            'name' => self::PROPERTY,
            'submit_label' => 'Save',
            'kinds' => [
                ['key' => 'main_residence', 'label' => 'Home', 'tool' => 'create_property', 'entity_type' => 'property',
                    'fields' => ['current_value', 'mortgage_outstanding_balance', 'ownership_type', 'ownership_percentage']],
                ['key' => 'secondary_residence', 'label' => 'Second home', 'tool' => 'create_property', 'entity_type' => 'property',
                    'fields' => ['current_value', 'mortgage_outstanding_balance', 'ownership_type', 'ownership_percentage']],
                ['key' => 'buy_to_let', 'label' => 'Buy to let', 'tool' => 'create_property', 'entity_type' => 'property',
                    'fields' => ['current_value', 'mortgage_outstanding_balance', 'monthly_rental_income', 'ownership_type', 'ownership_percentage']],
            ],
            'fields' => [
                'current_value' => ['type' => 'money', 'label' => 'Value', 'required' => true,
                    'hint' => 'The full value of the property, not just your share'],
                'mortgage_outstanding_balance' => ['type' => 'money_or_none', 'label' => 'Mortgage outstanding', 'required' => true,
                    'none_label' => 'No mortgage'],
                'monthly_rental_income' => ['type' => 'money', 'label' => 'Monthly rental income', 'required' => true],
                'ownership_type' => ['type' => 'choice', 'label' => 'Ownership', 'required' => true, 'options' => [
                    ['value' => 'individual', 'label' => 'Individual'],
                    ['value' => 'joint', 'label' => 'Joint'],
                    ['value' => 'tenants_in_common', 'label' => 'Tenants in common'],
                ]],
                'ownership_percentage' => ['type' => 'percent', 'label' => 'Your share %', 'required' => false, 'default' => 50,
                    'required_when' => ['field' => 'ownership_type', 'in' => ['joint', 'tenants_in_common']]],
            ],
        ];
    }

    /**
     * ISAs: Cash and Stocks and Shares (CSJ 2026-09-16: Lifetime and
     * Innovative Finance stay on the typed path). No ownership field — an
     * ISA is individual by law (the investment tool rejects a joint ISA).
     *
     * @return array<string, mixed>
     */
    private static function isa(): array
    {
        $fields = ['provider', 'current_value', 'paid_in_this_year'];

        return [
            'name' => self::ISA,
            'submit_label' => 'Save',
            'kinds' => [
                ['key' => 'cash_isa', 'label' => 'Cash ISA', 'tool' => 'create_savings_account', 'entity_type' => 'savings_account',
                    'fields' => [...$fields, 'interest_rate']],
                ['key' => 'stocks_shares_isa', 'label' => 'Stocks and Shares ISA', 'isa_type' => 'stocks_and_shares',
                    'tool' => 'create_investment_account', 'entity_type' => 'investment_account', 'fields' => $fields],
            ],
            'fields' => [
                'provider' => ['type' => 'text', 'label' => 'Who is it with', 'required' => true],
                'current_value' => ['type' => 'money', 'label' => 'Current balance', 'required' => true],
                'paid_in_this_year' => ['type' => 'money', 'label' => 'Paid in this tax year', 'required' => false,
                    'hint' => 'Leave blank if none'],
                'interest_rate' => ['type' => 'percent', 'label' => 'Interest rate %', 'required' => false, 'min' => 0, 'max' => 20, 'step' => 0.01],
            ],
        ];
    }

    /**
     * Bank and savings accounts. The interest rate is required on the
     * savings kinds and optional on the current account, so the current
     * account carries its own optional rate field (the input mapping joins
     * them). Joint is 50/50 with no share field (CSJ 2026-09-15).
     *
     * @return array<string, mixed>
     */
    private static function savings(): array
    {
        $fields = ['provider', 'current_value', 'interest_rate', 'ownership_type'];

        return [
            'name' => self::SAVINGS,
            'submit_label' => 'Save',
            'kinds_prompt' => 'A current account is for day-to-day money. Easy access, fixed rate and notice accounts are savings that pay interest.',
            'kinds' => [
                ['key' => 'current_account', 'label' => 'Current account', 'tool' => 'create_savings_account', 'entity_type' => 'savings_account',
                    'fields' => ['provider', 'current_value', 'current_account_interest_rate', 'ownership_type']],
                ['key' => 'easy_access', 'label' => 'Easy access savings', 'tool' => 'create_savings_account', 'entity_type' => 'savings_account',
                    'fields' => $fields],
                ['key' => 'fixed', 'label' => 'Fixed rate savings', 'tool' => 'create_savings_account', 'entity_type' => 'savings_account',
                    'fields' => $fields],
                ['key' => 'notice', 'label' => 'Notice account', 'tool' => 'create_savings_account', 'entity_type' => 'savings_account',
                    'fields' => $fields],
            ],
            'fields' => [
                'provider' => ['type' => 'text', 'label' => 'Who is it with', 'required' => true],
                'current_value' => ['type' => 'money', 'label' => 'Balance', 'required' => true],
                'interest_rate' => ['type' => 'percent', 'label' => 'Interest rate %', 'required' => true, 'min' => 0, 'max' => 20, 'step' => 0.01],
                'current_account_interest_rate' => ['type' => 'percent', 'label' => 'Interest rate %', 'required' => false, 'min' => 0, 'max' => 20, 'step' => 0.01,
                    'hint' => 'Leave blank if it pays none'],
                'ownership_type' => ['type' => 'choice', 'label' => 'Ownership', 'required' => true,
                    'hint' => 'Joint means held 50/50 with your spouse or partner', 'options' => [
                        ['value' => 'individual', 'label' => 'Individual'],
                        ['value' => 'joint', 'label' => 'Joint'],
                    ]],
            ],
        ];
    }

    /**
     * Investment accounts. Bonds, VCT/EIS and share schemes stay on the
     * typed path and the app forms.
     *
     * @return array<string, mixed>
     */
    private static function investment(): array
    {
        $fields = ['provider', 'current_value', 'ownership_type', 'ownership_percentage'];

        return [
            'name' => self::INVESTMENT,
            'submit_label' => 'Save',
            // Laura, 2026-09-18: no way to say she had none. Nothing chosen
            // and saved is the answer.
            'allow_empty' => true,
            'kinds_prompt' => 'Choose any you have. If you have no investments, save with none chosen.',
            'kinds' => [
                ['key' => 'gia', 'label' => 'General Investment Account', 'account_type' => 'personal_investment_account',
                    'tool' => 'create_investment_account', 'entity_type' => 'investment_account', 'fields' => $fields],
                ['key' => 'other', 'label' => 'Other investment', 'account_type' => 'other',
                    'tool' => 'create_investment_account', 'entity_type' => 'investment_account', 'fields' => ['provider', 'investment_type', ...array_slice($fields, 1)]],
            ],
            'fields' => [
                'provider' => ['type' => 'text', 'label' => 'Who is it with', 'required' => true],
                // CSJ 2026-09-16: an optional free-text type for "Other investment".
                'investment_type' => ['type' => 'text', 'label' => 'What type of investment is it', 'required' => false,
                    'hint' => 'For example shares, a fund or crowdfunding'],
                'current_value' => ['type' => 'money', 'label' => 'Current value', 'required' => true],
                'ownership_type' => ['type' => 'choice', 'label' => 'Ownership', 'required' => true, 'options' => [
                    ['value' => 'individual', 'label' => 'Individual'],
                    ['value' => 'joint', 'label' => 'Joint'],
                ]],
                'ownership_percentage' => ['type' => 'percent', 'label' => 'Your share %', 'required' => false, 'default' => 50,
                    'required_when' => ['field' => 'ownership_type', 'in' => ['joint']]],
            ],
        ];
    }

    /**
     * Pensions (CSJ 2026-09-16): a workplace pension and a personal pension
     * or SIPP, both defined contribution rows through create_pension.
     * Values may be unknown; the pot-value loop asks later for any missing.
     *
     * @return array<string, mixed>
     */
    private static function pension(): array
    {
        return [
            'name' => self::PENSION,
            'submit_label' => 'Save',
            'kinds' => [
                ['key' => 'workplace', 'label' => 'Workplace pension', 'scheme_type' => 'occupational',
                    'tool' => 'create_pension', 'entity_type' => 'dc_pension',
                    'fields' => ['provider', 'current_value', 'employee_contribution_percent', 'employer_contribution_percent', 'salary_sacrifice']],
                ['key' => 'personal', 'label' => 'Personal pension or SIPP', 'scheme_type' => 'personal',
                    'tool' => 'create_pension', 'entity_type' => 'dc_pension',
                    'fields' => ['provider', 'current_value', 'annual_contribution', 'annual_drawdown_income', 'pcls_taken']],
            ],
            'fields' => [
                'provider' => ['type' => 'text', 'label' => 'Who is it with', 'required' => true],
                'current_value' => ['type' => 'money', 'label' => 'Current value', 'required' => false,
                    'hint' => "Leave blank if you don't know"],
                'employee_contribution_percent' => ['type' => 'percent', 'label' => 'You pay % of salary', 'required' => true, 'min' => 0, 'max' => 100, 'step' => 0.1],
                'employer_contribution_percent' => ['type' => 'percent', 'label' => 'Your employer pays %', 'required' => false, 'min' => 0, 'max' => 100, 'step' => 0.1,
                    'hint' => 'Leave blank if none or unknown'],
                'salary_sacrifice' => ['type' => 'choice', 'label' => 'Salary sacrifice', 'required' => true, 'options' => [
                    ['value' => 'yes', 'label' => 'Yes'],
                    ['value' => 'no', 'label' => 'No'],
                ]],
                'annual_contribution' => ['type' => 'money', 'label' => 'You pay in each year', 'required' => false,
                    'hint' => "Leave blank if you don't pay in"],
                'annual_drawdown_income' => ['type' => 'money', 'label' => 'You draw from it each year', 'required' => false,
                    'hint' => "Leave blank if you haven't started drawing"],
                'pcls_taken' => ['type' => 'money', 'label' => 'Tax-free lump sum already taken', 'required' => false,
                    'hint' => 'Leave blank if none'],
            ],
        ];
    }

    /**
     * Working spouse (CSJ 2026-09-16): their income above the boxes, then
     * "do they have any of the following" — ISAs, pension, investments —
     * all saved in ONE write through capture_spouse_household_data.
     *
     * @return array<string, mixed>
     */
    private static function spouseHousehold(): array
    {
        return [
            'name' => self::SPOUSE_HOUSEHOLD,
            'submit_label' => 'Save',
            'tool' => 'capture_spouse_household_data',
            'entity_type' => 'spouse_household',
            'lead_fields' => ['spouse_annual_income'],
            'allow_empty' => true,
            'kinds_prompt' => 'Do they have any of the following? You can choose more than one, or save with none chosen.',
            'kinds' => [
                ['key' => 'isa', 'label' => 'ISAs', 'fields' => ['spouse_isa_balance', 'spouse_isa_provider']],
                ['key' => 'pension', 'label' => 'A pension', 'fields' => ['spouse_pension_input_annual', 'spouse_existing_pension_balance', 'spouse_pension_provider']],
                ['key' => 'investments', 'label' => 'Investments', 'fields' => ['spouse_annual_dividends', 'spouse_unrealised_gains']],
            ],
            'fields' => [
                // Laura, 2026-09-18: she did not know it and had no way to say so.
                'spouse_annual_income' => ['type' => 'money_or_none', 'label' => 'Their annual income', 'required' => true, 'hint' => 'Before tax',
                    'none_label' => "I don't know"],
                'spouse_isa_balance' => ['type' => 'money', 'label' => 'ISA balance', 'required' => true],
                'spouse_isa_provider' => ['type' => 'text', 'label' => 'Who the ISA is with', 'required' => false],
                'spouse_pension_input_annual' => ['type' => 'money', 'label' => 'They pay in each year', 'required' => false, 'hint' => 'Leave blank if none'],
                'spouse_existing_pension_balance' => ['type' => 'money', 'label' => 'Pension pot value', 'required' => false, 'hint' => "Leave blank if you don't know"],
                'spouse_pension_provider' => ['type' => 'text', 'label' => 'Who the pension is with', 'required' => false],
                'spouse_annual_dividends' => ['type' => 'money', 'label' => 'Dividends they receive each year', 'required' => false, 'hint' => 'Leave blank if none'],
                'spouse_unrealised_gains' => ['type' => 'money', 'label' => 'Gains on investments not yet sold', 'required' => false, 'hint' => 'Leave blank if none or unknown'],
            ],
        ];
    }

    /**
     * Non-working spouse: what they hold in their own name — savings, ISAs,
     * investments, pension — ONE write through capture_spouse_non_working_assets.
     * Nothing chosen and saved means nothing in their own name.
     *
     * @return array<string, mixed>
     */
    private static function spouseAssets(): array
    {
        return [
            'name' => self::SPOUSE_ASSETS,
            'submit_label' => 'Save',
            'tool' => 'capture_spouse_non_working_assets',
            'entity_type' => 'spouse_assets',
            'lead_fields' => [],
            'kinds_prompt' => 'Do they have any of the following in their own name? You can choose more than one, or save with none chosen.',
            'allow_empty' => true,
            'kinds' => [
                ['key' => 'savings', 'label' => 'Savings', 'fields' => ['spouse_existing_savings_balance']],
                ['key' => 'isa', 'label' => 'ISAs', 'fields' => ['spouse_existing_isa_balance']],
                ['key' => 'investments', 'label' => 'Investments', 'fields' => ['spouse_existing_investment_balance', 'spouse_existing_dividend_holdings_value']],
                ['key' => 'pension', 'label' => 'A pension', 'fields' => ['spouse_existing_pension_balance']],
            ],
            'fields' => [
                'spouse_existing_savings_balance' => ['type' => 'money', 'label' => 'Savings balance', 'required' => true],
                'spouse_existing_isa_balance' => ['type' => 'money', 'label' => 'ISA balance', 'required' => true],
                'spouse_existing_investment_balance' => ['type' => 'money', 'label' => 'Investments value', 'required' => true],
                'spouse_existing_dividend_holdings_value' => ['type' => 'money', 'label' => 'Of which dividend-paying shares', 'required' => false, 'hint' => 'Leave blank if none'],
                'spouse_existing_pension_balance' => ['type' => 'money', 'label' => 'Pension pot value', 'required' => true],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private static function personalSentence(array $input): string
    {
        $parts = [];
        if (isset($input['date_of_birth'])) {
            $parts[] = 'I was born on '.Carbon::parse($input['date_of_birth'])->format('j F Y');
        }
        if (isset($input['marital_status'])) {
            $parts[] = "I'm ".self::maritalWords($input['marital_status']);
        }

        return $parts === [] ? '' : ucfirst(implode(' and ', $parts)).'.';
    }

    public static function maritalWords(string $status): string
    {
        return match ($status) {
            'civil_partnership' => 'in a civil partnership',
            default => str_replace('_', ' ', $status),
        };
    }

    /**
     * Journey path personal details: date of birth and marital status, both
     * required, ONE write through capture_personal_details (the handler
     * enforces the 18–105 age bounds).
     *
     * @return array<string, mixed>
     */
    private static function personal(): array
    {
        return [
            'name' => self::PERSONAL,
            'submit_label' => 'Save',
            'tool' => 'capture_personal_details',
            'entity_type' => 'personal',
            'lead_fields' => ['date_of_birth', 'marital_status'],
            'kinds' => [],
            'fields' => [
                'date_of_birth' => ['type' => 'date', 'label' => 'Your date of birth', 'required' => true],
                'marital_status' => ['type' => 'choice', 'label' => 'Marital status', 'required' => true, 'options' => [
                    ['value' => 'single', 'label' => 'Single'],
                    ['value' => 'married', 'label' => 'Married'],
                    ['value' => 'civil_partnership', 'label' => 'In a civil partnership'],
                    ['value' => 'divorced', 'label' => 'Divorced'],
                    ['value' => 'widowed', 'label' => 'Widowed'],
                ]],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private static function spouseDetailsSentence(array $input): string
    {
        $name = trim(($input['first_name'] ?? '').' '.($input['last_name'] ?? ''));
        $parts = ['My spouse is '.($name === '' ? 'as follows' : $name)];
        if (isset($input['date_of_birth'])) {
            $parts[] = 'born on '.Carbon::parse($input['date_of_birth'])->format('j F Y');
        }
        if (isset($input['email'])) {
            $parts[] = 'email '.$input['email'];
        }
        if (isset($input['annual_income'])) {
            $parts[] = 'earning '.self::pounds($input['annual_income']).' a year';
        }

        return implode(', ', $parts).'.';
    }

    /**
     * Journey path spouse or partner details — the fields
     * capture_spouse_details needs to create and link their account (name,
     * date of birth, email) plus their income if known. ONE write.
     *
     * @return array<string, mixed>
     */
    private static function spouseDetails(): array
    {
        return [
            'name' => self::SPOUSE_DETAILS,
            'submit_label' => 'Save',
            'tool' => 'capture_spouse_details',
            'entity_type' => 'spouse',
            'lead_fields' => ['first_name', 'last_name', 'date_of_birth', 'email', 'annual_income'],
            'kinds' => [],
            'fields' => [
                'first_name' => ['type' => 'text', 'label' => 'Their first name', 'required' => true],
                'last_name' => ['type' => 'text', 'label' => 'Their last name', 'required' => false],
                'date_of_birth' => ['type' => 'date', 'label' => 'Their date of birth', 'required' => true],
                'email' => ['type' => 'email', 'label' => 'Their email address', 'required' => true, 'hint' => "I'll create their account and link the two of you so you can plan together"],
                'annual_income' => ['type' => 'money', 'label' => 'Their annual income', 'required' => false, 'hint' => 'Before tax. Leave blank if you are not sure'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private static function dependantSentence(array $input): string
    {
        $noun = match ($input['relationship'] ?? '') {
            'child' => 'child',
            'parent' => 'parent',
            default => 'dependant',
        };
        $name = trim((string) ($input['first_name'] ?? ''));
        $who = 'My '.$noun.($name !== '' ? ' '.$name : '');
        $born = isset($input['date_of_birth']) ? ' was born on '.Carbon::parse($input['date_of_birth'])->format('j F Y') : '';
        $disability = ($input['is_disabled'] ?? null) === 'yes' ? ' and has a disability' : '';

        return $who.$born.$disability.'.';
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private static function workSentence(array $input): string
    {
        $parts = [];
        // "My job is X at Y" avoids an article before the role ("a Operations manager").
        if (isset($input['occupation'])) {
            $parts[] = 'My job is '.$input['occupation'].(isset($input['employer']) ? ' at '.$input['employer'] : '');
        } elseif (isset($input['employer'])) {
            $parts[] = 'I work at '.$input['employer'];
        }
        if (isset($input['annual_income'])) {
            $parts[] = 'I earn '.self::pounds($input['annual_income']).' a year';
        }

        return $parts === [] ? '' : implode(' and ', $parts).'.';
    }

    /**
     * Journey path dependants: ONE dependant per save (who they are, their
     * name, their exact date of birth) through capture_dependants; the loop
     * question after it asks for another. The handler rejects a future or
     * over-120 date of birth.
     *
     * @return array<string, mixed>
     */
    private static function dependants(): array
    {
        return [
            'name' => self::DEPENDANTS,
            'submit_label' => 'Save',
            'tool' => 'capture_dependants',
            'entity_type' => 'dependant',
            'lead_fields' => ['relationship', 'first_name', 'date_of_birth', 'is_disabled'],
            'kinds' => [],
            'fields' => [
                'relationship' => ['type' => 'choice', 'label' => 'Who they are', 'required' => true, 'options' => [
                    ['value' => 'child', 'label' => 'A child'],
                    ['value' => 'parent', 'label' => 'A parent'],
                    ['value' => 'other_dependent', 'label' => 'Another dependant'],
                ]],
                'first_name' => ['type' => 'text', 'label' => 'Their first name', 'required' => false],
                'date_of_birth' => ['type' => 'date', 'label' => 'Their date of birth', 'required' => true, 'hint' => 'The exact date helps keep the plan correct'],
                // Tax-Free Childcare pays more, for longer, for a disabled child.
                'is_disabled' => ['type' => 'choice', 'label' => 'Do they have a disability?', 'required' => false,
                    'hint' => 'For example they receive Disability Living Allowance or have an Education, Health and Care plan', 'options' => [
                        ['value' => 'yes', 'label' => 'Yes'],
                        ['value' => 'no', 'label' => 'No'],
                    ]],
            ],
        ];
    }

    /**
     * Work and income — employer or trading name, role and gross annual
     * income, ONE write through capture_work_details. Reached on the journey
     * path and the Save Tax and pension check income steps alike.
     *
     * @return array<string, mixed>
     */
    private static function work(): array
    {
        return [
            'name' => self::WORK,
            'submit_label' => 'Save',
            'tool' => 'capture_work_details',
            'entity_type' => 'work',
            'lead_fields' => ['employer', 'occupation', 'annual_income'],
            'kinds' => [],
            'fields' => [
                'employer' => ['type' => 'text', 'label' => 'Employer or trading name', 'required' => true],
                'occupation' => ['type' => 'text', 'label' => 'Job title or role', 'required' => true],
                'annual_income' => ['type' => 'money', 'label' => 'Gross annual income', 'required' => true, 'hint' => 'Before tax, including bonuses and commissions'],
            ],
        ];
    }

    /**
     * The campaign date-of-birth step: one date, the same write as the
     * personal form (the handler accepts either field on its own).
     *
     * @return array<string, mixed>
     */
    private static function dob(): array
    {
        return [
            'name' => self::DOB,
            'submit_label' => 'Save',
            'tool' => 'capture_personal_details',
            'entity_type' => 'personal',
            'lead_fields' => ['date_of_birth'],
            'kinds' => [],
            'fields' => [
                'date_of_birth' => ['type' => 'date', 'label' => 'Your date of birth', 'required' => true],
            ],
        ];
    }

    /**
     * The pension form with only the personal pension or SIPP kind: what a
     * self-employed, retired or not-working user is asked at the
     * contributions step, since their workplace-scheme step is skipped.
     *
     * @return array<string, mixed>
     */
    private static function pensionPersonal(): array
    {
        $pension = self::pension();
        $pension['name'] = self::PENSION_PERSONAL;
        $pension['kinds'] = array_values(array_filter($pension['kinds'], static fn (array $kind): bool => $kind['key'] === 'personal'));

        return $pension;
    }

    /**
     * The expenditure form a user gets: one box for everyone; the category
     * form on Premium, with the household question first when a spouse is on
     * file and has not answered it.
     *
     * @return array<string, mixed>
     */
    public static function expenditureVariantFor(User $user, bool $detailedAllowed): array
    {
        if (! $detailedAllowed) {
            return self::expenditure();
        }
        $askHousehold = $user->liveSpouse() !== null && $user->expenditure_sharing_mode_declared_at === null;

        return $askHousehold ? self::expenditureDetailed(true) : self::expenditureDetailed(false);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $input
     */
    private static function expenditureSentence(array $schema, array $input): string
    {
        if ($schema['name'] === self::EXPENDITURE) {
            return isset($input['monthly_total']) ? 'About '.self::pounds($input['monthly_total']).' goes out each month.' : '';
        }
        $parts = [];
        $total = 0.0;
        foreach ($schema['fields'] as $key => $field) {
            if ($field['type'] === 'money' && isset($input[$key])) {
                $parts[] = lcfirst($field['label']).' '.self::pounds($input[$key]);
                $total += $input[$key];
            }
        }
        if ($parts === []) {
            return '';
        }
        $household = match ($input['expenditure_sharing_mode'] ?? null) {
            'joint' => ' These are our household figures.',
            'separate' => ' These are just my own figures.',
            default => '',
        };

        return 'My monthly spending: '.implode(', ', $parts).' — about '.self::pounds($total).' a month in total.'.$household;
    }

    /** @return array<string, mixed> */
    private static function expenditure(): array
    {
        return [
            'name' => self::EXPENDITURE,
            'submit_label' => 'Save',
            'tool' => 'capture_monthly_expenditure',
            'entity_type' => 'expenditure',
            // Childcare, charitable donations and Gift Aid are free-tier fields
            // (CSJ, 2026-09-22): the tax lines a free user sees read them.
            'lead_fields' => ['monthly_total', 'childcare', 'charitable_donations', 'is_gift_aid'],
            'kinds' => [],
            'fields' => [
                'monthly_total' => ['type' => 'money', 'label' => 'What goes out each month', 'required' => true,
                    'hint' => 'Rent or mortgage, bills, food, transport, the lot. A ballpark figure is fine'],
                'childcare' => ['type' => 'money', 'label' => 'Of that, childcare', 'required' => false,
                    'hint' => 'Nursery, childminder, after school. Leave blank if none'],
                'charitable_donations' => ['type' => 'money', 'label' => 'Of that, charitable donations', 'required' => false,
                    'hint' => 'Leave blank if none'],
                'is_gift_aid' => ['type' => 'choice', 'label' => 'Are your donations made under Gift Aid?', 'required' => false, 'options' => [
                    ['value' => 'yes', 'label' => 'Yes'],
                    ['value' => 'no', 'label' => 'No'],
                ]],
            ],
        ];
    }

    /**
     * Category entry (Premium): the web expenditure page's groups as the kind
     * boxes, every category optional, ONE write through set_expenditure.
     *
     * @return array<string, mixed>
     */
    private static function expenditureDetailed(bool $askHousehold): array
    {
        $money = static fn (string $label, ?string $hint = null): array => array_filter(['type' => 'money', 'label' => $label, 'required' => false, 'hint' => $hint]);

        $schema = [
            'name' => $askHousehold ? self::EXPENDITURE_DETAILED_HOUSEHOLD : self::EXPENDITURE_DETAILED,
            'base' => self::EXPENDITURE,
            'submit_label' => 'Save',
            'tool' => 'set_expenditure',
            'entity_type' => 'expenditure',
            'lead_fields' => $askHousehold ? ['expenditure_sharing_mode'] : [],
            'kinds_prompt' => 'Choose the areas you spend on and fill in what you can. Monthly figures, rough is fine.',
            'kinds' => [
                ['key' => 'essential', 'label' => 'Essential living', 'fields' => ['rent', 'utilities', 'food_groceries', 'transport_fuel', 'healthcare_medical', 'insurance']],
                ['key' => 'communication', 'label' => 'Communication and technology', 'fields' => ['mobile_phones', 'internet_tv', 'subscriptions']],
                ['key' => 'lifestyle', 'label' => 'Personal and lifestyle', 'fields' => ['clothing_personal_care', 'entertainment_dining', 'holidays_travel', 'pets']],
                ['key' => 'children', 'label' => 'Children and dependants', 'fields' => ['childcare', 'school_fees', 'school_lunches', 'school_extras', 'university_fees', 'children_activities']],
                ['key' => 'other', 'label' => 'Other', 'fields' => ['gifts_charity', 'charitable_donations', 'other_expenditure']],
            ],
            'fields' => [
                'expenditure_sharing_mode' => ['type' => 'choice', 'label' => 'Are these figures for the whole household, or just you?', 'required' => true, 'options' => [
                    ['value' => 'joint', 'label' => 'The whole household'],
                    ['value' => 'separate', 'label' => 'Just me'],
                ]],
                'rent' => $money('Rent', 'Monthly rent if not a homeowner'),
                'utilities' => $money('Utilities', 'Gas, electricity, water, council tax'),
                'food_groceries' => $money('Food and groceries'),
                'transport_fuel' => $money('Transport and fuel', 'Petrol, public transport, parking'),
                'healthcare_medical' => $money('Healthcare and medical', 'Prescriptions, dental, optician'),
                'insurance' => $money('Insurance (not property)', 'Car, private medical, mobile phone'),
                'mobile_phones' => $money('Mobile phones'),
                'internet_tv' => $money('Internet and TV', 'Broadband, TV licence'),
                'subscriptions' => $money('Subscriptions', 'Streaming, gym memberships'),
                'clothing_personal_care' => $money('Clothing and personal care', 'Clothes, toiletries, haircuts'),
                'entertainment_dining' => $money('Entertainment and dining', 'Restaurants, cinema, activities'),
                'holidays_travel' => $money('Holidays and travel', 'Monthly average for the year'),
                'pets' => $money('Pets', 'Food, vet bills, insurance'),
                'childcare' => $money('Childcare', 'Nursery, childminder, after school'),
                'school_fees' => $money('School fees', 'Private education fees'),
                'school_lunches' => $money('School lunches'),
                'school_extras' => $money('School extras', 'Uniforms, trips, equipment'),
                'university_fees' => $money('University fees', 'Includes accommodation and books'),
                'children_activities' => $money("Children's activities", 'Sports, music lessons, clubs'),
                'gifts_charity' => $money('Gifts and presents', 'Birthday and Christmas gifts'),
                'charitable_donations' => $money('Charitable donations'),
                'other_expenditure' => $money('Other spending', 'Any other monthly expenses'),
            ],
        ];
        if (! $askHousehold) {
            unset($schema['fields']['expenditure_sharing_mode']);
        }

        return $schema;
    }

    /**
     * create_protection_policy input for one kind. Income protection carries
     * a monthly benefit, the others a sum assured; premiums are monthly.
     *
     * @param  array<string, mixed>  $kind
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    private static function protectionInputs(array $kind, array $answers): array
    {
        $input = ['policy_type' => $kind['policy_type'], 'provider' => trim((string) $answers['provider'])];
        if ($kind['key'] === 'income') {
            $input['benefit_amount'] = (float) $answers['benefit_amount'];
        } else {
            $input['sum_assured'] = (float) $answers['sum_assured'];
        }
        if (is_numeric($answers['premium_amount'] ?? null)) {
            $input['premium_amount'] = (float) $answers['premium_amount'];
            $input['premium_frequency'] = 'monthly';
        }
        if (is_numeric($answers['policy_term_years'] ?? null)) {
            $input['policy_term_years'] = (int) $answers['policy_term_years'];
        }

        return $input;
    }

    /** @param  array<string, mixed>  $input */
    private static function protectionSentence(string $label, array $input): string
    {
        $parts = [$label.' with '.$input['provider']];
        if (isset($input['sum_assured'])) {
            $parts[] = self::pounds($input['sum_assured']).' of cover';
        }
        if (isset($input['benefit_amount'])) {
            $parts[] = self::pounds($input['benefit_amount']).' a month benefit';
        }
        if (isset($input['premium_amount'])) {
            $parts[] = self::pounds($input['premium_amount']).' a month premium';
        }
        if (isset($input['policy_term_years'])) {
            $parts[] = $input['policy_term_years'].' year term';
        }

        return implode(', ', $parts).'.';
    }

    /**
     * Journey path protection cover: one policy per kind per save through
     * create_protection_policy; the loop question asks for another.
     *
     * @return array<string, mixed>
     */
    private static function protection(): array
    {
        return [
            'name' => self::PROTECTION,
            'submit_label' => 'Save',
            'kinds' => [
                ['key' => 'life', 'label' => 'Life insurance', 'policy_type' => 'level_term',
                    'tool' => 'create_protection_policy', 'entity_type' => 'life_insurance_policy',
                    'fields' => ['provider', 'sum_assured', 'premium_amount', 'policy_term_years']],
                ['key' => 'critical', 'label' => 'Critical illness cover', 'policy_type' => 'standalone_ci',
                    'tool' => 'create_protection_policy', 'entity_type' => 'critical_illness_policy',
                    'fields' => ['provider', 'sum_assured', 'premium_amount', 'policy_term_years']],
                ['key' => 'income', 'label' => 'Income protection', 'policy_type' => 'income_protection',
                    'tool' => 'create_protection_policy', 'entity_type' => 'income_protection_policy',
                    'fields' => ['provider', 'benefit_amount', 'premium_amount', 'policy_term_years']],
            ],
            'fields' => [
                'provider' => ['type' => 'text', 'label' => 'Who is it with', 'required' => true],
                'sum_assured' => ['type' => 'money', 'label' => 'Cover amount', 'required' => true, 'hint' => 'The lump sum it would pay out'],
                'benefit_amount' => ['type' => 'money', 'label' => 'Monthly benefit', 'required' => true, 'hint' => 'What it would pay each month'],
                'premium_amount' => ['type' => 'money', 'label' => 'Monthly premium', 'required' => false, 'hint' => "Leave blank if you don't know"],
                'policy_term_years' => ['type' => 'percent', 'label' => 'Term in years', 'required' => false, 'min' => 1, 'max' => 50, 'step' => 1,
                    'hint' => 'Leave blank for whole of life or if unsure'],
            ],
        ];
    }
}
