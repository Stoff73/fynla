<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Services\AI\AiProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * A change typed to Fyn fills in the record's form; nothing is saved until the
 * user presses Save (CSJ 2026-10-05, option A: "Fyn fills in the form for them";
 * 2026-10-01: all capture through forms).
 *
 * Reads, from the typed message, a new value for any of the form's own fields,
 * section by section, checks each against that field's own rule
 * (CaptureForms::fieldRules), and returns the form with those values in place
 * of the recorded ones. On a blank form with several kinds (the setup steps'
 * accounts and pensions) the message's kind is the section filled, which opens
 * that kind on every surface. Several record forms are read in one call
 * (fillAny), and a record's form is filled only when the message is about that
 * record, never a different or new one. Nothing is returned when the message
 * gives nothing for the form (a question, say), so the turn is answered as
 * before.
 */
final class TypedFormFill
{
    /**
     * @param  array{schema: array<string, mixed>, answers: array<string, array<string, mixed>>, record?: array<string, mixed>|null, label?: string}  $form  RecordEditForms::formFor, or a walk step's form
     * @return array<string, mixed>|null the form with `answers` filled in and `filled` naming the fields
     */
    public function fill(array $form, string $message): ?array
    {
        return $this->fillAny([$form], $message)[0] ?? null;
    }

    /**
     * Every form the message fills, keyed as given.
     *
     * @param  array<int, array{schema: array<string, mixed>, answers: array<string, array<string, mixed>>, record?: array<string, mixed>|null, label?: string}>  $forms
     * @return array<int, array<string, mixed>>
     */
    public function fillAny(array $forms, string $message): array
    {
        $sectionsByForm = [];
        foreach ($forms as $index => $form) {
            $sectionsByForm[$index] = self::sections($form['schema']);
        }
        if (trim($message) === '' || array_filter($sectionsByForm) === []) {
            return [];
        }

        $extracted = $this->extract($forms, $sectionsByForm, $message);
        $result = [];
        foreach ($forms as $index => $form) {
            $filled = self::apply($form, $sectionsByForm[$index], (array) ($extracted[(string) $index] ?? []));
            if ($filled !== null) {
                $result[$index] = $filled;
            }
        }

        return $result;
    }

    /**
     * The values read for one form, each checked against its field's own rule.
     *
     * @param  array<string, mixed>  $form
     * @param  array<string, array{label: string|null, fields: list<string>}>  $sections
     * @param  array<string, mixed>  $extracted  section key => field key => value
     * @return array<string, mixed>|null
     */
    private static function apply(array $form, array $sections, array $extracted): ?array
    {
        $answers = $form['answers'];
        $filled = [];
        foreach ($extracted as $sectionKey => $values) {
            if (! isset($sections[$sectionKey]) || ! is_array($values)) {
                continue;
            }
            foreach ($values as $key => $value) {
                if (! in_array($key, $sections[$sectionKey]['fields'], true)) {
                    continue;
                }
                $definition = $form['schema']['fields'][$key];
                $value = is_string($value) ? trim($value) : $value;
                // The field's own rule, less its presence part: a value is given.
                $rules = array_values(array_filter(
                    CaptureForms::fieldRules($sectionKey, $key, $definition),
                    static fn (string $rule): bool => ! str_starts_with($rule, 'required_with') && $rule !== 'present',
                ));
                if ($value === null || $value === '' || Validator::make(['v' => $value], ['v' => $rules])->fails()) {
                    continue;
                }
                if (in_array($definition['type'], ['money', 'money_or_none', 'percent'], true)) {
                    $value = (float) $value;
                }
                if (($answers[$sectionKey][$key] ?? null) == $value) {
                    continue;
                }
                // A kind with any answer is the kind chosen: both form
                // renderers open a kind whose values arrive.
                $answers[$sectionKey] = array_replace((array) ($answers[$sectionKey] ?? []), [$key => $value]);
                $filled[] = $key;
            }
        }

        return $filled === [] ? null : array_replace($form, ['answers' => $answers, 'filled' => $filled]);
    }

    /**
     * The form's sections — its lead fields, then each kind — with their fields.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, array{label: string|null, fields: list<string>}>
     */
    private static function sections(array $schema): array
    {
        $sections = [];
        if (($schema['lead_fields'] ?? []) !== []) {
            $sections[CaptureForms::LEAD] = ['label' => null, 'fields' => array_values($schema['lead_fields'])];
        }
        foreach ($schema['kinds'] ?? [] as $kind) {
            $sections[$kind['key']] = ['label' => $kind['label'] ?? $kind['key'], 'fields' => array_values($kind['fields'])];
        }

        return $sections;
    }

    /**
     * @param  array<int, array<string, mixed>>  $forms
     * @param  array<int, array<string, array{label: string|null, fields: list<string>}>>  $sectionsByForm
     * @return array<string, mixed> form index => section key => field key => value
     */
    private function extract(array $forms, array $sectionsByForm, string $message): array
    {
        $connection = AiProvider::helperConnection();
        if (! AiProvider::isConfigured($connection)) {
            return [];
        }

        $catalogue = [];
        foreach ($forms as $index => $form) {
            $sections = [];
            foreach ($sectionsByForm[$index] as $sectionKey => $section) {
                $fields = [];
                foreach ($section['fields'] as $key) {
                    $definition = $form['schema']['fields'][$key];
                    $fields[] = array_filter([
                        'key' => $key,
                        'label' => $definition['label'] ?? $key,
                        'type' => $definition['type'],
                        'options' => isset($definition['options']) ? array_column($definition['options'], 'value') : null,
                        'current' => $form['answers'][$sectionKey][$key] ?? null,
                    ], static fn ($v): bool => $v !== null);
                }
                $sections[] = array_filter(['key' => $sectionKey, 'kind' => $section['label'], 'fields' => $fields], static fn ($v): bool => $v !== null);
            }
            $catalogue[] = array_filter([
                'form' => (string) $index,
                // A form holding a saved record names it.
                'record' => ($form['record'] ?? null) !== null ? (string) ($form['label'] ?? 'saved record') : null,
                'sections' => $sections,
            ], static fn ($v): bool => $v !== null);
        }

        $system = <<<'PROMPT'
A user typed a message where Fyn can show them a form. The forms are listed. A form with a "record" holds that saved record; a form without one is blank. Each form has sections; a section with a "kind" is one kind of record (an easy access account, a workplace pension), and each field is listed with any current value. Return the NEW value the message states for each field it gives, under its form and section, and nothing else. Output strict JSON: {"forms": {"<form>": {"<section key>": {"<field key>": <value>}}}}.
Rules:
- Only fields the message gives a value for. If it gives none (a question, a greeting), return {"forms": {}}.
- Fill a form holding a record only when the message is about that record. Never put a different or new record (another provider, a new account) on it.
- A new record the message describes goes on the blank form, under the section for its kind.
- Put a value under a kind's section only when the message describes that kind of record. If it is not clear which kind, leave that value out.
- choice: one of the listed options, exactly. date: YYYY-MM-DD. money and percent: a plain number, no symbols. text: the words given.
- Never guess or carry a value over from the current one.
- JSON only, no prose, no markdown fences.
PROMPT;

        try {
            $response = AiProvider::postChatCompletion($connection, [
                'max_completion_tokens' => 600,
                'temperature' => 0,
                'reasoning_effort' => 'none',
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => json_encode(['forms' => $catalogue, 'message' => $message], JSON_UNESCAPED_UNICODE)],
                ],
            ], 30);

            if (! $response->successful()) {
                return [];
            }

            $decoded = json_decode((string) ($response->json()['choices'][0]['message']['content'] ?? ''), true);

            return is_array($decoded['forms'] ?? null) ? $decoded['forms'] : [];
        } catch (\Throwable $e) {
            Log::warning('[TypedFormFill] extraction failed', ['error' => $e->getMessage()]);

            return [];
        }
    }
}
