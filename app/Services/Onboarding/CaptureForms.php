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
 * Answer keys are the create tool's own field names so nothing is renamed
 * between the form and the store. `mortgage_outstanding_balance: null`
 * means "No mortgage".
 */
final class CaptureForms
{
    public const PROPERTY = 'property';

    private const MONEY_MAX = '999999999.99';

    private const MONTHLY_MAX = '999999.99';

    /** @return list<string> */
    public static function names(): array
    {
        return [self::PROPERTY];
    }

    /** @return array<string, mixed>|null */
    public static function schema(string $name): ?array
    {
        return match ($name) {
            self::PROPERTY => self::property(),
            default => null,
        };
    }

    public static function kindLabel(string $name, string $kind): string
    {
        foreach (self::schema($name)['kinds'] ?? [] as $entry) {
            if ($entry['key'] === $kind) {
                return $entry['label'];
            }
        }

        return $kind;
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
                $field = $schema['fields'][$fieldKey];
                $rules[$kind['key'].'.'.$fieldKey] = match ($field['type']) {
                    'money' => ['required_with:'.$kind['key'], 'numeric', 'min:0', 'max:'.($fieldKey === 'monthly_rental_income' ? self::MONTHLY_MAX : self::MONEY_MAX)],
                    'money_or_none' => ['present', 'nullable', 'numeric', 'min:0', 'max:'.self::MONEY_MAX],
                    'choice' => ['required_with:'.$kind['key'], 'in:'.implode(',', array_column($field['options'], 'value'))],
                    'percent' => ['nullable', 'numeric', 'min:0.01', 'max:99.99'],
                };
            }
        }

        return $rules;
    }

    /**
     * One create_property input per filled kind, in the handler's own
     * field names. Unstated optional fields are omitted, never null
     * (PropertyNormaliser NOT NULL trap, 2026-09-15). A share travels only
     * with a shared ownership; a shared ownership without a share is 50.
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

            $inputs[$kind['key']] = $input;
        }

        return $inputs;
    }

    /**
     * The transcript line saved as the user's message — plain words, so the
     * conversation reads naturally and the accuracy gate's text evidence
     * names each kind and its ownership.
     */
    public static function summarise(array $form): string
    {
        $schema = self::schema((string) ($form['name'] ?? ''));
        if ($schema === null) {
            return '';
        }

        $sentences = [];
        foreach (self::toolInputs($form) as $kindKey => $input) {
            $parts = [self::kindLabel($schema['name'], $kindKey).' worth '.self::pounds($input['current_value'])];
            $parts[] = $input['has_mortgage']
                ? 'mortgage '.self::pounds($input['mortgage_outstanding_balance'])
                : 'no mortgage';
            if (isset($input['monthly_rental_income'])) {
                $parts[] = 'rent '.self::pounds($input['monthly_rental_income']).' a month';
            }
            $parts[] = str_replace('_', ' ', $input['ownership_type']);
            if (isset($input['ownership_percentage'])) {
                $parts[] = 'my share '.rtrim(rtrim(number_format($input['ownership_percentage'], 2), '0'), '.').'%';
            }
            $sentences[] = implode(', ', $parts).'.';
        }

        return implode(' ', $sentences);
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
                ['key' => 'main_residence', 'label' => 'Home',
                    'fields' => ['current_value', 'mortgage_outstanding_balance', 'ownership_type', 'ownership_percentage']],
                ['key' => 'buy_to_let', 'label' => 'Buy to let',
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
}
