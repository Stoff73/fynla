<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

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
 */
final class CaptureForms
{
    public const PROPERTY = 'property';

    public const ISA = 'isa';

    public const SAVINGS = 'savings';

    public const INVESTMENT = 'investment';

    private const MONEY_MAX = '999999999.99';

    private const MONTHLY_MAX = '999999.99';

    /** @return list<string> */
    public static function names(): array
    {
        return [self::PROPERTY, self::ISA, self::SAVINGS, self::INVESTMENT];
    }

    /** @return array<string, mixed>|null */
    public static function schema(string $name): ?array
    {
        return match ($name) {
            self::PROPERTY => self::property(),
            self::ISA => self::isa(),
            self::SAVINGS => self::savings(),
            self::INVESTMENT => self::investment(),
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

        $sentences = [];
        foreach (self::toolInputs($form) as $kindKey => $input) {
            $label = self::kindLabel($schema['name'], $kindKey);
            $sentences[] = match ($schema['name']) {
                self::PROPERTY => self::propertySentence($label, $input),
                self::ISA => self::isaSentence($label, $input),
                self::SAVINGS => self::savingsSentence($label, $input),
                self::INVESTMENT => self::investmentSentence($label, $input),
            };
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
        $input = [
            'account_name' => $provider.' '.$kind['label'],
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
        $parts = [$label.' with '.$input['provider'].' worth '.self::pounds($input['current_value']), $input['ownership_type']];
        if (isset($input['ownership_percentage'])) {
            $parts[] = 'my share '.self::percent($input['ownership_percentage']);
        }

        return implode(', ', $parts).'.';
    }

    private static function percent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2), '0'), '.').'%';
    }

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
     * ISAs: any combination of the four kinds. No ownership field — an ISA
     * is individual by law (the investment tool rejects a joint ISA).
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
                ['key' => 'lifetime_isa', 'label' => 'Lifetime ISA', 'isa_type' => 'lifetime',
                    'tool' => 'create_investment_account', 'entity_type' => 'investment_account', 'fields' => $fields],
                ['key' => 'innovative_finance_isa', 'label' => 'Innovative Finance ISA', 'isa_type' => 'innovative_finance',
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
                'ownership_type' => ['type' => 'choice', 'label' => 'Ownership', 'required' => true, 'options' => [
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
            'kinds' => [
                ['key' => 'gia', 'label' => 'General Investment Account', 'account_type' => 'personal_investment_account',
                    'tool' => 'create_investment_account', 'entity_type' => 'investment_account', 'fields' => $fields],
                ['key' => 'other', 'label' => 'Other investment', 'account_type' => 'other',
                    'tool' => 'create_investment_account', 'entity_type' => 'investment_account', 'fields' => $fields],
            ],
            'fields' => [
                'provider' => ['type' => 'text', 'label' => 'Who is it with', 'required' => true],
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
}
