<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * A change typed to Fyn fills in the record's form; nothing is saved until the
 * user presses Save (CSJ 2026-10-05, option A: "Fyn fills in the form for them";
 * 2026-10-01: all capture through forms).
 *
 * Reads, from the typed message, a new value for any of the form's own fields,
 * checks each against that field's own rule (CaptureForms::fieldRules), and
 * returns the form with those values in place of the recorded ones. Null when
 * the message changes nothing on the form (a question, say), so the turn is
 * answered as before.
 */
final class TypedFormFill
{
    private const ENDPOINT = 'https://api.x.ai/v1/chat/completions';

    /**
     * @param  array{name: string, schema: array<string, mixed>, answers: array<string, array<string, mixed>>, record: array<string, mixed>, label: string}  $form  RecordEditForms::formFor
     * @return array{name: string, schema: array<string, mixed>, answers: array<string, array<string, mixed>>, record: array<string, mixed>, label: string, filled: list<string>}|null
     */
    public function fill(array $form, string $message): ?array
    {
        $fields = self::fields($form['schema']);
        if ($fields === [] || trim($message) === '') {
            return null;
        }

        $extracted = $this->extract($fields, $form['answers'], $message);
        $answers = $form['answers'];
        $filled = [];
        foreach ($extracted as $key => $value) {
            if (! isset($fields[$key])) {
                continue;
            }
            $field = $fields[$key];
            $value = is_string($value) ? trim($value) : $value;
            // The field's own rule, less its presence part: a value is given.
            $rules = array_values(array_filter(
                CaptureForms::fieldRules($field['section'], $key, $field['definition']),
                static fn (string $rule): bool => ! str_starts_with($rule, 'required_with') && $rule !== 'present',
            ));
            if ($value === null || $value === '' || Validator::make(['v' => $value], ['v' => $rules])->fails()) {
                continue;
            }
            if (in_array($field['definition']['type'], ['money', 'money_or_none', 'percent'], true)) {
                $value = (float) $value;
            }
            if (($answers[$field['section']][$key] ?? null) == $value) {
                continue;
            }
            $answers[$field['section']][$key] = $value;
            $filled[] = $key;
        }

        return $filled === [] ? null : array_replace($form, ['answers' => $answers, 'filled' => $filled]);
    }

    /**
     * The form's fields, each with the section its answer sits under.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, array{section: string, definition: array<string, mixed>}>
     */
    private static function fields(array $schema): array
    {
        $fields = [];
        foreach ($schema['lead_fields'] ?? [] as $key) {
            $fields[$key] = ['section' => CaptureForms::LEAD, 'definition' => $schema['fields'][$key]];
        }
        foreach ($schema['kinds'] ?? [] as $kind) {
            foreach ($kind['fields'] as $key) {
                $fields[$key] = ['section' => $kind['key'], 'definition' => $schema['fields'][$key]];
            }
        }

        return $fields;
    }

    /**
     * @param  array<string, array{section: string, definition: array<string, mixed>}>  $fields
     * @param  array<string, array<string, mixed>>  $answers
     * @return array<string, mixed>
     */
    private function extract(array $fields, array $answers, string $message): array
    {
        $apiKey = config('services.xai.api_key');
        if (empty($apiKey)) {
            return [];
        }

        $catalogue = [];
        foreach ($fields as $key => $field) {
            $definition = $field['definition'];
            $catalogue[] = array_filter([
                'key' => $key,
                'label' => $definition['label'] ?? $key,
                'type' => $definition['type'],
                'options' => isset($definition['options']) ? array_column($definition['options'], 'value') : null,
                'current' => $answers[$field['section']][$key] ?? null,
            ], static fn ($v): bool => $v !== null);
        }

        $system = <<<'PROMPT'
A user is changing one of their records and typed a message. The record's form fields are listed with their current values. Return the NEW value the message states for each field it changes, and nothing else. Output strict JSON: {"fields": {"<key>": <value>}}.
Rules:
- Only fields the message gives a new value for. If it changes none (a question, a greeting), return {"fields": {}}.
- choice: one of the listed options, exactly. date: YYYY-MM-DD. money and percent: a plain number, no symbols. text: the words given.
- Never guess or carry a value over from the current one.
- JSON only, no prose, no markdown fences.
PROMPT;

        try {
            $response = Http::withHeaders(['Authorization' => 'Bearer '.$apiKey])
                ->timeout(30)
                ->post(self::ENDPOINT, [
                    'model' => config('services.xai.vision_model', 'grok-4.3'),
                    'max_completion_tokens' => 400,
                    'temperature' => 0,
                    'reasoning_effort' => 'none',
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => json_encode(['fields' => $catalogue, 'message' => $message], JSON_UNESCAPED_UNICODE)],
                    ],
                ]);

            if (! $response->successful()) {
                return [];
            }

            $decoded = json_decode((string) ($response->json()['choices'][0]['message']['content'] ?? ''), true);

            return is_array($decoded['fields'] ?? null) ? $decoded['fields'] : [];
        } catch (\Throwable $e) {
            Log::warning('[TypedFormFill] extraction failed', ['error' => $e->getMessage()]);

            return [];
        }
    }
}
