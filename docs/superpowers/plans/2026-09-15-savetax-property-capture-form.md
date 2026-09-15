# Save Tax Property Capture Form Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** At the Save Tax campaign property step, Fyn shows a small structured form in the chat (Home / Buy to let) on web and `/m`; Save writes through the existing property handler with no model call and no extractor.

**Architecture:** One server-side schema (`CaptureForms`) describes the form. The director emits a `capture_form` SSE event for clients that declare `X-Fynla-Forms: 1` and persists the schema on the assistant message so history re-renders it. Save posts `{form}` to the existing messages endpoint; the director builds one `create_property` call per filled kind and runs it through `CoordinatingAgent::executeTool` with the answers as the gate's confirmed facts, then advances exactly as the typed path does. Web and `/m` each get a schema-driven `FynCaptureForm.vue`.

**Tech Stack:** Laravel 10 (Pest), Vue 3 (Vitest), the isolated `/m` Vite bundle, SSE.

**Spec:** `docs/superpowers/specs/2026-09-15-savetax-property-capture-form-design.md`

## Global Constraints

- Scope is the Save Tax campaign step `campaign_property` only. Native iOS keeps the typed prompt: no form is sent unless the request carries `X-Fynla-Forms: 1`.
- The extractor, gate and backstop are not modified. Typed text at the step still goes through `handleAssetCaptureTurn`.
- Every write goes through `CoordinatingAgent::executeTool('create_property', …)` — the one Fyn write path (Rule 20). Unstated optional fields are omitted, never sent as null.
- Canonical enums only: `main_residence`, `buy_to_let`; `individual`, `joint`, `tenants_in_common`.
- No icons anywhere in the form (Rule 15). Palette tokens only, no hex in `<style>`. Currency via the existing helpers. British copy. Required fields carry an asterisk.
- `declare(strict_types=1);` in every PHP file. Pest tests use `it()`, `RefreshDatabase`, `Mockery::close()` in `afterEach`.
- Corpus DATA fields (`turn_type`, `prompt_text`, `form`) live in `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md`, never in PHP (`OnboardingWorkflowTableGoldenMasterTest` enforces this). PHP keeps only `capture_focus`, `next`, `skip_if`.
- The root `CLAUDE.md` server and artisan rules apply verbatim (the forbidden migrate, cache and env flags). Deploy builds only through `./deploy/csjones-fynla/build.sh`, never raw `vite` or `npm run build`.
- Work on branch `feat/savetax-property-capture-form` (already holds the spec). Commit after every task.

---

## File Structure

| File | Responsibility |
|---|---|
| `app/Services/Onboarding/CaptureForms.php` (create) | The schema per form-capable step; converts answers to tool inputs; composes the plain-words transcript line; per-field validation rules |
| `app/Http/Requests/AI/SendAiChatMessageRequest.php` (modify) | Accepts optional `form`; `message` required only without it; shape rules from `CaptureForms` |
| `app/Http/Controllers/Api/AiChatController.php` (modify) | Reads `X-Fynla-Forms`; passes the flag and the `form` payload into the director on the send, stream and start paths; stores `form` on a queued message |
| `app/Services/AI/Loop/ConcurrentTurnQueue.php` (modify) | `enqueue` accepts optional metadata |
| `app/Services/Onboarding/OnboardingChatDirector.php` (modify) | `form` turn emission in `emitTurnForState`; `handleFormTurn`; shared `advanceAfterCapture`; client-capability flag |
| `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md` (modify) | `campaign_property`: `turn_type: form`, `form: property`, shorter prompt |
| `resources/js/components/Fyn/FynCaptureForm.vue` (create) | Web renderer, schema-driven |
| `resources/js/components/Shared/AiChatPanel.vue` (modify) | Renders `capture_form` rows; submit handler |
| `resources/js/store/modules/aiChat.js` (modify) | `capture_form` / `capture_form_errors` in all four SSE routers; history mapping; `submitCaptureForm` action |
| web API layer (modify — Task 8 names the file) | `X-Fynla-Forms: 1` header; `form` in the POST body |
| `resources/mobile/components/FynCaptureForm.vue` (create) | `/m` renderer |
| `resources/mobile/mixins/onboardingChat.js` (modify) | event router, history mapping, submit |
| `resources/mobile/api.js` (modify) | header + `form` body |
| Tests | `tests/Unit/Services/Onboarding/CaptureFormsTest.php`, `tests/Feature/AI/SendAiChatMessageFormRequestTest.php`, `tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php`, web and `/m` Vitest specs |

---

### Task 1: `CaptureForms` — the one schema home

**Files:**
- Create: `app/Services/Onboarding/CaptureForms.php`
- Test: `tests/Unit/Services/Onboarding/CaptureFormsTest.php`

**Interfaces:**
- Produces:
  - `CaptureForms::names(): list<string>` → `['property']`
  - `CaptureForms::schema(string $name): ?array` — the schema array below, or null
  - `CaptureForms::rules(string $name): array<string,array>` — Laravel rules keyed relative to `form.answers` (e.g. `main_residence.current_value`)
  - `CaptureForms::toolInputs(array $form): array<string,array>` — `kind => create_property input`
  - `CaptureForms::summarise(array $form): string` — plain-words transcript line
  - `CaptureForms::kindLabel(string $name, string $kind): string`

Schema shape (this exact array is what the event carries and the renderers read):

```php
[
    'name' => 'property',
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
]
```

Answer shape the client posts (keys are the tool's own field names, so nothing is renamed on the way to the store):

```json
{"name": "property", "answers": {
  "main_residence": {"current_value": 750000, "mortgage_outstanding_balance": 325000, "ownership_type": "joint", "ownership_percentage": 50},
  "buy_to_let":     {"current_value": 450000, "mortgage_outstanding_balance": null, "monthly_rental_income": 1000, "ownership_type": "individual"}
}}
```

`mortgage_outstanding_balance: null` means "No mortgage" (`has_mortgage => false`); a number means `has_mortgage => true`. A kind absent from `answers` was not filled.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Services\Onboarding\CaptureForms;

it('lists the property form and returns null for an unknown form', function (): void {
    expect(CaptureForms::names())->toBe(['property'])
        ->and(CaptureForms::schema('property')['name'])->toBe('property')
        ->and(CaptureForms::schema('bank'))->toBeNull();
});

it('marks the required fields and the conditional share', function (): void {
    $fields = CaptureForms::schema('property')['fields'];
    expect($fields['current_value']['required'])->toBeTrue()
        ->and($fields['mortgage_outstanding_balance']['required'])->toBeTrue()
        ->and($fields['monthly_rental_income']['required'])->toBeTrue()
        ->and($fields['ownership_type']['required'])->toBeTrue()
        ->and($fields['ownership_percentage']['required'])->toBeFalse()
        ->and($fields['ownership_percentage']['required_when'])->toBe(['field' => 'ownership_type', 'in' => ['joint', 'tenants_in_common']])
        ->and(array_column($fields['ownership_type']['options'], 'value'))->toBe(['individual', 'joint', 'tenants_in_common']);
});

it('builds one create_property input per filled kind with nothing unstated', function (): void {
    $inputs = CaptureForms::toolInputs(['name' => 'property', 'answers' => [
        'main_residence' => ['current_value' => 750000, 'mortgage_outstanding_balance' => 325000, 'ownership_type' => 'joint', 'ownership_percentage' => 50],
        'buy_to_let' => ['current_value' => 450000, 'mortgage_outstanding_balance' => null, 'monthly_rental_income' => 1000, 'ownership_type' => 'individual'],
    ]]);

    expect(array_keys($inputs))->toBe(['main_residence', 'buy_to_let'])
        ->and($inputs['main_residence'])->toBe([
            'property_type' => 'main_residence', 'current_value' => 750000.0, 'has_mortgage' => true,
            'mortgage_outstanding_balance' => 325000.0, 'ownership_type' => 'joint', 'ownership_percentage' => 50.0,
        ])
        ->and($inputs['buy_to_let'])->toBe([
            'property_type' => 'buy_to_let', 'current_value' => 450000.0, 'has_mortgage' => false,
            'monthly_rental_income' => 1000.0, 'ownership_type' => 'individual',
        ]);
});

it('drops a share sent for an individual owner and defaults a missing shared one to 50', function (): void {
    $inputs = CaptureForms::toolInputs(['name' => 'property', 'answers' => [
        'main_residence' => ['current_value' => 1, 'mortgage_outstanding_balance' => null, 'ownership_type' => 'individual', 'ownership_percentage' => 40],
        'buy_to_let' => ['current_value' => 1, 'mortgage_outstanding_balance' => null, 'monthly_rental_income' => 1, 'ownership_type' => 'tenants_in_common'],
    ]]);

    expect($inputs['main_residence'])->not->toHaveKey('ownership_percentage')
        ->and($inputs['buy_to_let']['ownership_percentage'])->toBe(50.0);
});

it('composes the transcript line in plain words', function (): void {
    $line = CaptureForms::summarise(['name' => 'property', 'answers' => [
        'main_residence' => ['current_value' => 750000, 'mortgage_outstanding_balance' => 325000, 'ownership_type' => 'joint', 'ownership_percentage' => 50],
        'buy_to_let' => ['current_value' => 450000, 'mortgage_outstanding_balance' => null, 'monthly_rental_income' => 1000, 'ownership_type' => 'individual'],
    ]]);

    expect($line)->toBe('Home worth £750,000, mortgage £325,000, joint, my share 50%. Buy to let worth £450,000, no mortgage, rent £1,000 a month, individual.');
});

it('produces validation rules per kind and field', function (): void {
    $rules = CaptureForms::rules('property');
    expect($rules['main_residence.current_value'])->toBe(['required_with:main_residence', 'numeric', 'min:0', 'max:999999999.99'])
        ->and($rules['main_residence.mortgage_outstanding_balance'])->toBe(['present', 'nullable', 'numeric', 'min:0', 'max:999999999.99'])
        ->and($rules['buy_to_let.monthly_rental_income'])->toBe(['required_with:buy_to_let', 'numeric', 'min:0', 'max:999999.99'])
        ->and($rules['buy_to_let.ownership_type'])->toBe(['required_with:buy_to_let', 'in:individual,joint,tenants_in_common'])
        ->and($rules['buy_to_let.ownership_percentage'])->toBe(['nullable', 'numeric', 'min:0.01', 'max:99.99'])
        ->and($rules)->not->toHaveKey('main_residence.monthly_rental_income');
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/CaptureFormsTest.php`
Expected: FAIL — `Class "App\Services\Onboarding\CaptureForms" not found`

- [ ] **Step 3: Write the class**

```php
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
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/CaptureFormsTest.php`
Expected: 6 passed. If `summarise` differs by a comma, fix the class, not the expectation — the expectation is the copy CSJ reads in the transcript.

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint app/Services/Onboarding/CaptureForms.php tests/Unit/Services/Onboarding/CaptureFormsTest.php
git add app/Services/Onboarding/CaptureForms.php tests/Unit/Services/Onboarding/CaptureFormsTest.php
git commit -m "feat(onboarding): CaptureForms — the one schema home for the chat capture forms; property first"
```

---

### Task 2: Request accepts `form`

**Files:**
- Modify: `app/Http/Requests/AI/SendAiChatMessageRequest.php` (rules at lines 26-31)
- Test: `tests/Feature/AI/SendAiChatMessageFormRequestTest.php`

**Interfaces:**
- Consumes: `CaptureForms::names()`, `CaptureForms::rules()`
- Produces: validated input `form` = `{name, answers}` or absent; `message` may be absent when `form` is present. Controller reads `$request->input('form')`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\GDPR\ConsentService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true]);
    app(ConsentService::class)->recordConsent($this->user, UserConsent::TYPE_AI_CHAT, true);
    $this->conversation = AiConversation::create(['user_id' => $this->user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding']);
    Sanctum::actingAs($this->user);
});

function postForm($test, int $conversationId, array $body)
{
    return $test->postJson("/api/ai-chat/conversations/{$conversationId}/messages", $body);
}

it('rejects an unknown form name', function (): void {
    postForm($this, $this->conversation->id, ['form' => ['name' => 'bank', 'answers' => []]])
        ->assertStatus(422)->assertJsonValidationErrors(['form.name']);
});

it('rejects a kind the schema does not have and a bad ownership value', function (): void {
    postForm($this, $this->conversation->id, ['form' => ['name' => 'property', 'answers' => [
        'castle' => ['current_value' => 1],
        'main_residence' => ['current_value' => 1, 'mortgage_outstanding_balance' => null, 'ownership_type' => 'shared'],
    ]]])->assertStatus(422)->assertJsonValidationErrors(['form.answers.castle', 'form.answers.main_residence.ownership_type']);
});

it('requires the mortgage key to be present even when null, and a value for a filled kind', function (): void {
    postForm($this, $this->conversation->id, ['form' => ['name' => 'property', 'answers' => [
        'buy_to_let' => ['ownership_type' => 'individual', 'monthly_rental_income' => 900],
    ]]])->assertStatus(422)->assertJsonValidationErrors(['form.answers.buy_to_let.current_value', 'form.answers.buy_to_let.mortgage_outstanding_balance']);
});

it('still requires a message when there is no form', function (): void {
    postForm($this, $this->conversation->id, [])->assertStatus(422)->assertJsonValidationErrors(['message']);
});

it('accepts a well-formed property answer with no message', function (): void {
    // Past validation the controller streams; a 200 with a streamed body is
    // the proof the request class let it through (the director's own
    // behaviour is Task 5's test).
    postForm($this, $this->conversation->id, ['form' => ['name' => 'property', 'answers' => [
        'main_residence' => ['current_value' => 750000, 'mortgage_outstanding_balance' => 325000, 'ownership_type' => 'joint', 'ownership_percentage' => 50],
    ]]])->assertOk();
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `./vendor/bin/pest tests/Feature/AI/SendAiChatMessageFormRequestTest.php`
Expected: the first three fail (no `form` rules exist, so the requests fail on `message` instead), the last fails with 422 on `message`.

- [ ] **Step 3: Extend the request class**

Replace the `rules()` method and add `withValidator`:

```php
    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'message' => ['required_without:form', 'nullable', 'string', 'max:2000'],
            'current_route' => ['nullable', 'string', 'max:255'],
            // A structured capture-form answer (CaptureForms). Shape only —
            // business rules live in the store the director writes through.
            'form' => ['sometimes', 'array'],
            'form.name' => ['required_with:form', 'string', 'in:'.implode(',', CaptureForms::names())],
            'form.answers' => ['required_with:form', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $form = $this->input('form');
            if (! is_array($form) || $validator->errors()->has('form.name')) {
                return;
            }
            $schema = CaptureForms::schema((string) ($form['name'] ?? ''));
            if ($schema === null) {
                return;
            }
            $knownKinds = array_column($schema['kinds'], 'key');
            foreach (array_keys((array) ($form['answers'] ?? [])) as $kind) {
                if (! in_array($kind, $knownKinds, true)) {
                    $validator->errors()->add('form.answers.'.$kind, 'Unknown property kind.');
                }
            }
            $nested = ValidatorFacade::make(
                (array) ($form['answers'] ?? []),
                CaptureForms::rules((string) $form['name']),
            );
            foreach ($nested->errors()->toArray() as $key => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add('form.answers.'.$key, $message);
                }
            }
        });
    }
```

Add the imports at the top of the file:

```php
use App\Services\Onboarding\CaptureForms;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Validator;
```

- [ ] **Step 4: Run the test**

Run: `./vendor/bin/pest tests/Feature/AI/SendAiChatMessageFormRequestTest.php`
Expected: all five pass. (Ruling 3, 2026-09-15: the nested validator must be built from `CaptureForms::rules()` filtered to the kinds present in `form.answers` — `array_filter(..., fn ($key) => array_key_exists(strtok($key, '.'), $answers), ARRAY_FILTER_USE_KEY)` — otherwise the absent kind's `present` mortgage rule 422s every single-kind submission. The fifth case passes once that filter is in place; it never depended on Task 6.)

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint app/Http/Requests/AI/SendAiChatMessageRequest.php tests/Feature/AI/SendAiChatMessageFormRequestTest.php
git add app/Http/Requests/AI/SendAiChatMessageRequest.php tests/Feature/AI/SendAiChatMessageFormRequestTest.php
git commit -m "feat(ai-chat): the messages request accepts a structured capture-form answer"
```

---

### Task 3: The corpus makes `campaign_property` a form turn

**Files:**
- Modify: `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md` (the `campaign_property` block, ~line 168)
- Modify: `app/Services/Onboarding/OnboardingStateMachine.php` docblock line 36 (list `form` as a turn type; no state change)
- Test: `tests/Unit/Services/Onboarding/CaptureFormsTest.php` (append), plus the existing `OnboardingWorkflowTableGoldenMasterTest` and `CampaignSectionFlowTest` must stay green

**Interfaces:**
- Produces: `OnboardingStateMachine::getState('campaign_property')` returns `turn_type => 'form'`, `form => 'property'`, `prompt_text` (new copy), and keeps `capture_focus => 'property'`, `next`, `skip_if`.

- [ ] **Step 1: Write the failing test** (append to `CaptureFormsTest.php`)

```php
it('the property step is a form turn owned by the corpus', function (): void {
    \App\Services\Onboarding\OnboardingStateMachine::flushTransitionTableCache();
    $state = \App\Services\Onboarding\OnboardingStateMachine::getState(\App\Services\Onboarding\OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY);

    expect($state['turn_type'])->toBe('form')
        ->and($state['form'])->toBe('property')
        ->and($state['capture_focus'])->toBe('property')
        ->and($state['prompt_text'])->toBe('Now your property. **Tell me about your home and any buy to let — fill in the boxes below and tap Save.**');
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/CaptureFormsTest.php --filter="form turn"`
Expected: FAIL — `turn_type` is `delegated`.

- [ ] **Step 3: Edit the corpus block**

In `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md` replace the `campaign_property` block with:

```yaml
campaign_property:
  turn_type: form
  form: property
  prompt_text: "Now your property. **Tell me about your home and any buy to let — fill in the boxes below and tap Save.**"
  capture_field: null
  next: { branch: enterCampaignVerify }
```

In `OnboardingStateMachine.php` line 36 change the docblock to:

```
 *   turn_type:    'bubbles' | 'free_text' | 'delegated' | 'grouped_extract' | 'form' | 'terminal'
```

- [ ] **Step 4: Run the tests**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/CaptureFormsTest.php tests/Unit/Services/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php tests/Unit/Services/Onboarding/CampaignSectionFlowTest.php tests/Unit/Services/Onboarding/PensioncheckSectionsTest.php && php artisan fyn:procedural:validate | grep onboarding.workflow`
Expected: all pass; the validator lists `onboarding.workflow.fyn-onboarding v1` as active. If a golden-master fixture for the corpus text exists and fails, regenerate it the way its docblock says and commit the fixture with this task.

- [ ] **Step 5: Commit**

```bash
git add fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md app/Services/Onboarding/OnboardingStateMachine.php tests/Unit/Services/Onboarding/CaptureFormsTest.php
git commit -m "feat(onboarding): the Save Tax property step is a form turn"
```

---

### Task 4: The director emits the form turn (and the typed prompt for clients without forms)

**Files:**
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php` — `emitTurnForState` (the `if ($turnType === 'bubbles')` branch at ~line 1002), a new property + setter near the class top, and the `handleUserMessage` dispatch at ~line 382
- Test: `tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php` (create)

**Interfaces:**
- Produces:
  - `OnboardingChatDirector::setClientSupportsForms(bool $supports): void` — the controller calls it per request (Task 6)
  - SSE event `['type' => 'capture_form', 'prompt_text' => string, 'form' => <schema array>]`
  - Persisted assistant message: content = prompt text, `metadata.capture_form` = the schema array, `metadata.onboarding_step`, `metadata.turn_intent = 'step_prompt'`
  - A typed message at a `form` state goes to `handleAssetCaptureTurn` (the delegated path) unchanged.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Property;
use App\Models\User;
use App\Services\Onboarding\CaptureForms;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fyn\FynStreamHarness;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    OnboardingStateMachine::flushTransitionTableCache();
});

afterEach(function (): void {
    Mockery::close();
});

function formStepUser(string $step = OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY): User
{
    return User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'first_name' => 'Chris',
        'marital_status' => 'married',
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_step' => $step,
        'onboarding_fyn_selection' => 'savetax',
        'funnel_answers' => ['campaign' => 'savetax', 'assets' => ['property', 'investments']],
    ]);
}

function formConversation(User $user): AiConversation
{
    return AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding'])->fresh();
}

it('emits the property form to a client that can render forms and persists the schema for history', function (): void {
    $user = formStepUser();
    $conversation = formConversation($user);
    $director = app(OnboardingChatDirector::class);
    $director->setClientSupportsForms(true);

    $events = iterator_to_array($director->emitTurnForState($user, $conversation, OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY, OnboardingStateMachine::getState(OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY)), false);

    $form = collect($events)->firstWhere('type', 'capture_form');
    expect($form)->not->toBeNull()
        ->and($form['form'])->toBe(CaptureForms::schema('property'))
        ->and($form['prompt_text'])->toContain('Now your property')
        ->and(collect($events)->where('type', 'content'))->toHaveCount(0)
        ->and(collect($events)->last()['type'])->toBe('done');

    $saved = AiMessage::where('conversation_id', $conversation->id)->where('role', 'assistant')->latest('id')->first();
    // toEqual, not toBe: MySQL's JSON column reorders object keys on round-trip.
    expect($saved->metadata['capture_form'])->toEqual(CaptureForms::schema('property'))
        ->and($saved->metadata['onboarding_step'])->toBe(OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY)
        ->and($saved->metadata['turn_intent'])->toBe('step_prompt');
});

it('emits the typed prompt instead when the client has not declared forms — native stays as it was', function (): void {
    $user = formStepUser();
    $conversation = formConversation($user);
    $director = app(OnboardingChatDirector::class);
    $director->setClientSupportsForms(false);

    $events = iterator_to_array($director->emitTurnForState($user, $conversation, OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY, OnboardingStateMachine::getState(OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY)), false);

    expect(collect($events)->firstWhere('type', 'capture_form'))->toBeNull()
        ->and(collect($events)->firstWhere('type', 'content')['text'])->toContain('Now your property');
});

it('sends a typed sentence at the form step down the existing capture path', function (): void {
    $user = formStepUser();
    $conversation = formConversation($user);
    FynStreamHarness::fake()
        ->toolTurn('create_property', ['property_type' => 'buy_to_let', 'current_value' => 450000, 'has_mortgage' => true, 'mortgage_outstanding_balance' => 100000, 'monthly_rental_income' => 1000, 'ownership_type' => 'individual'], 'toolu_btl')
        ->bind();

    iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage($user, $conversation, 'A buy to let worth 450000, mortgage 100000, rent 1000 a month, mine'), false);

    expect(Property::where('user_id', $user->id)->where('property_type', 'buy_to_let')->exists())->toBeTrue();
});
```

`emitTurnForState` is private today; make it `public` in Step 3 (it is the seam every turn goes through, and the test drives it directly so no model is needed to reach the property step).

- [ ] **Step 2: Run it to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php`
Expected: FAIL — `setClientSupportsForms` undefined / `emitTurnForState` not public.

- [ ] **Step 3: Implement**

Near the top of the class (after the constructor's promoted properties) add:

```php
    /**
     * Whether the client behind this request renders capture forms
     * (`X-Fynla-Forms: 1`, sent by the web and /m bundles). Native does not
     * yet, and keeps the typed prompt for a form turn. Set per request by
     * AiChatController; never read from headers here.
     */
    private bool $clientSupportsForms = false;

    public function setClientSupportsForms(bool $supports): void
    {
        $this->clientSupportsForms = $supports;
    }
```

Change `private function emitTurnForState(` to `public function emitTurnForState(`.

In `emitTurnForState`, immediately before `if ($turnType === 'bubbles') {` insert:

```php
        if ($turnType === 'form' && $this->clientSupportsForms) {
            $schema = CaptureForms::schema((string) ($state['form'] ?? ''));
            if ($schema !== null) {
                yield [
                    'type' => 'capture_form',
                    'prompt_text' => $promptText,
                    'form' => $schema,
                ];
                $assistantMessage = $this->saveMessage($conversation, 'assistant', $promptText, [
                    'metadata' => [
                        'capture_form' => $schema,
                        'onboarding_step' => $stateId,
                        'turn_intent' => $turnIntent->value,
                    ],
                ]);
                yield ['type' => 'done', 'message_id' => $assistantMessage->id];

                return;
            }
        }
```

(A `form` turn for a client without forms, or with an unknown schema, falls through to the existing `else` branch and gets the plain text prompt — no other change.)

In `handleUserMessage`, change the delegated dispatch at ~line 382 from

```php
        if (($state['turn_type'] ?? '') === 'delegated') {
```

to

```php
        // A form turn answered with typed text takes the same delegated
        // capture path as before the form existed (the extractor and the
        // gate are the fallback, untouched).
        if (in_array($state['turn_type'] ?? '', ['delegated', 'form'], true)) {
```

`CaptureForms` is in the same namespace as the director, so no import is needed.

- [ ] **Step 4: Run the test**

Run: `./vendor/bin/pest tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php`
Expected: 3 passed.

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint app/Services/Onboarding/OnboardingChatDirector.php tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php
git add app/Services/Onboarding/OnboardingChatDirector.php tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php
git commit -m "feat(onboarding): the director emits a capture_form turn to clients that render forms"
```

---

### Task 5: The director saves a form answer through the property handler

**Files:**
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php` — `handleUserMessage` signature and head; new `handleFormTurn`; new `advanceAfterCapture` extracted from `handleAssetCaptureTurn` (the block from `$this->recordProgress(` after the `capture_complete` yield through the next-state emission that follows `onboarding_advance`, ~lines 4155-4200)
- Test: `tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php` (append)

**Interfaces:**
- Consumes: `CaptureForms::toolInputs`, `CaptureForms::kindLabel`, `CoordinatingAgent::executeTool(..., confirmedFacts: [...])`
- Produces:
  - `handleUserMessage(User $user, AiConversation $conversation, string $message, ?string $currentRoute = null, bool $persistUserMessage = true, ?array $form = null)`
  - Events on success: per kind `tool_use running`, `entity_created`, `tool_use complete`; then `capture_complete`, `onboarding_advance`, the next turn, `done`
  - Events on any failure: `['type' => 'capture_form_errors', 'form' => 'property', 'errors' => ['<kind>' => ['message' => string, 'fields' => array<string,string>]]]`, one `content` line, `done`; the step stays parked; landed kinds stay saved
  - A `form` answer on a state that is not this form: one `content` line "That form is no longer open — let's carry on from where we are." then the current turn re-emitted.

- [ ] **Step 1: Write the failing tests** (append to `PropertyCaptureFormTurnTest.php`)

```php
function propertyAnswers(array $overrides = []): array
{
    return ['name' => 'property', 'answers' => array_replace_recursive([
        'main_residence' => ['current_value' => 750000, 'mortgage_outstanding_balance' => 325000, 'ownership_type' => 'joint', 'ownership_percentage' => 50],
        'buy_to_let' => ['current_value' => 450000, 'mortgage_outstanding_balance' => null, 'monthly_rental_income' => 1000, 'ownership_type' => 'individual'],
    ], $overrides)];
}

it('saves both kinds from a form answer with no model call and advances to verify', function (): void {
    $user = formStepUser();
    $conversation = formConversation($user);
    FynStreamHarness::fake()->bind(); // no turns queued: any model call fails the test

    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage(
        $user, $conversation, CaptureForms::summarise(propertyAnswers()), null, true, propertyAnswers()
    ), false);

    $home = Property::where('user_id', $user->id)->where('property_type', 'main_residence')->first();
    $btl = Property::where('user_id', $user->id)->where('property_type', 'buy_to_let')->first();
    expect($home)->not->toBeNull()
        ->and((float) $home->current_value)->toBe(750000.0)
        ->and((float) $home->outstanding_mortgage)->toBe(325000.0)
        ->and($home->ownership_type)->toBe('joint')
        ->and((float) $home->ownership_percentage)->toBe(50.0)
        ->and($home->tenure_type)->toBe('freehold')
        ->and($btl)->not->toBeNull()
        ->and((float) $btl->current_value)->toBe(450000.0)
        ->and((float) $btl->outstanding_mortgage)->toBe(0.0)
        ->and((float) $btl->monthly_rental_income)->toBe(1000.0)
        ->and($btl->ownership_type)->toBe('individual')
        ->and(collect($events)->where('type', 'entity_created'))->toHaveCount(2)
        ->and(collect($events)->first()['type'])->toBe('form_received')
        ->and(collect($events)->first()['text'])->toBe(CaptureForms::summarise(propertyAnswers()))
        ->and(collect($events)->firstWhere('type', 'capture_complete'))->not->toBeNull()
        ->and(collect($events)->firstWhere('type', 'capture_form_errors'))->toBeNull()
        ->and($user->fresh()->onboarding_fyn_step)->toBe('campaign_verify_announce');

    $userRow = AiMessage::where('conversation_id', $conversation->id)->where('role', 'user')->latest('id')->first();
    expect($userRow->content)->toContain('Home worth £750,000')
        ->and($userRow->metadata['form'])->toBe(propertyAnswers());
});

it('saves one kind alone', function (): void {
    $user = formStepUser();
    $conversation = formConversation($user);
    FynStreamHarness::fake()->bind();
    $form = ['name' => 'property', 'answers' => ['buy_to_let' => ['current_value' => 200000, 'mortgage_outstanding_balance' => 50000, 'monthly_rental_income' => 850, 'ownership_type' => 'tenants_in_common', 'ownership_percentage' => 60]]];

    iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage($user, $conversation, CaptureForms::summarise($form), null, true, $form), false);

    $btl = Property::where('user_id', $user->id)->first();
    expect(Property::where('user_id', $user->id)->count())->toBe(1)
        ->and($btl->ownership_type)->toBe('tenants_in_common')
        ->and((float) $btl->ownership_percentage)->toBe(60.0)
        ->and($user->fresh()->onboarding_fyn_step)->toBe('campaign_verify_announce');
});

it('reports a refused kind on the form, keeps the landed one, and stays on the step', function (): void {
    // Free holds two properties; a third is refused by the tier cap.
    $user = formStepUser();
    $conversation = formConversation($user);
    FynStreamHarness::fake()->bind();
    Property::create(['user_id' => $user->id, 'property_type' => 'secondary_residence', 'current_value' => 100000, 'ownership_type' => 'individual', 'ownership_percentage' => 100, 'address_line_1' => 'Second home', 'city' => 'Unknown', 'postcode' => 'N/A']);

    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage(
        $user, $conversation, CaptureForms::summarise(propertyAnswers()), null, true, propertyAnswers()
    ), false);

    $errors = collect($events)->firstWhere('type', 'capture_form_errors');
    expect(Property::where('user_id', $user->id)->where('property_type', 'main_residence')->exists())->toBeTrue()
        ->and(Property::where('user_id', $user->id)->where('property_type', 'buy_to_let')->exists())->toBeFalse()
        ->and($errors)->not->toBeNull()
        ->and($errors['errors'])->toHaveKey('buy_to_let')
        ->and($errors['errors']['buy_to_let']['message'])->toContain('property limit')
        ->and(collect($events)->where('type', 'content')->pluck('text')->implode(' '))->toContain('Buy to let')
        ->and($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY);
});

it('never invokes the model for a form answer', function (): void {
    $user = formStepUser();
    $conversation = formConversation($user);
    FynStreamHarness::fake()->bind();

    iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage($user, $conversation, CaptureForms::summarise(propertyAnswers()), null, true, propertyAnswers()), false);

    expect(AiMessage::where('conversation_id', $conversation->id)->where('role', 'assistant')->whereNotNull('tool_calls')->exists())->toBeFalse();
});

it('tells the user a stale form is no longer open and re-emits the current step', function (): void {
    $user = formStepUser(OnboardingStateMachine::STATE_CAMPAIGN_DOB);
    $conversation = formConversation($user);
    FynStreamHarness::fake()->bind();

    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage($user, $conversation, 'Home worth £1', null, true, propertyAnswers()), false);

    expect(Property::where('user_id', $user->id)->exists())->toBeFalse()
        ->and(collect($events)->where('type', 'content')->pluck('text')->implode(' '))->toContain('no longer open')
        ->and(collect($events)->where('type', 'content')->pluck('text')->implode(' '))->toContain('date of birth')
        ->and($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_DOB);
});
```

If `Property::create` needs further NOT NULL columns, read `database/migrations` for `properties` and add them; do not pass null for a defaulted column.

- [ ] **Step 2: Run to verify they fail**

Run: `./vendor/bin/pest tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php`
Expected: the five new tests fail (`handleUserMessage` accepts 5 arguments).

- [ ] **Step 3: Implement**

Change the signature:

```php
    public function handleUserMessage(
        User $user,
        AiConversation $conversation,
        string $message,
        ?string $currentRoute = null,
        bool $persistUserMessage = true,
        ?array $form = null
    ): \Generator {
        if ($persistUserMessage) {
            $this->saveMessage($conversation, 'user', $message, $form !== null ? ['metadata' => ['form' => $form]] : []);
        }
```

Immediately after the `$state === null` guard (the `Unknown onboarding step` return) insert:

```php
        if ($form !== null) {
            if (($state['turn_type'] ?? '') !== 'form' || ($state['form'] ?? null) !== ($form['name'] ?? null)) {
                // A form submitted after the step moved on (a second tab, a
                // late tap). Nothing is written; the walk carries on.
                $line = "That form is no longer open — let's carry on from where we are.";
                yield ['type' => 'content', 'text' => $line];
                $this->saveMessage($conversation, 'assistant', $line, ['metadata' => ['onboarding_step' => $currentStateId, 'turn_intent' => FynTurnIntent::CaptureClarification->value]]);
                yield from $this->emitTurnForState($user, $conversation, $currentStateId, $state, includeTransitionHeader: false);

                return;
            }

            yield from $this->handleFormTurn($user, $conversation, $message, $currentStateId, $state, $form);

            return;
        }
```

Add the new method next to `handleAssetCaptureTurn`:

```php
    /**
     * A structured capture-form answer (CaptureForms). Every filled kind is
     * one create call through the same handler, gate, tier cap, spouse
     * memory and audit trail as a typed capture — with the user's answers
     * handed to the gate as confirmed facts. No model, no extractor.
     *
     * @param  array{name: string, answers: array<string, array<string, mixed>>}  $form
     */
    private function handleFormTurn(
        User $user,
        AiConversation $conversation,
        string $message,
        string $currentStateId,
        array $state,
        array $form
    ): \Generator {
        $recordsCreated = [];
        $errors = [];

        // The clients post the form with no typed text and show a placeholder
        // user row; this is the transcript line (composed once, in
        // CaptureForms) they replace it with.
        yield ['type' => 'form_received', 'text' => $message];

        foreach (CaptureForms::toolInputs($form) as $kind => $input) {
            yield ['type' => 'tool_use', 'tool' => 'create_property', 'status' => 'running'];
            $facts = ['ownership_type' => $input['ownership_type']];
            if (isset($input['ownership_percentage'])) {
                $facts['ownership_percentage'] = $input['ownership_percentage'];
            }
            try {
                $result = $this->coordinatingAgent->executeTool('create_property', $input, $user, $conversation->id, confirmedFacts: $facts);
            } catch (\Throwable $e) {
                Log::error('[OnboardingChatDirector] Form capture write failed', ['user_id' => $user->id, 'kind' => $kind, 'error' => $e->getMessage()]);
                $result = ['error' => true, 'message' => 'Unable to save the record. Please try again.'];
            }
            yield ['type' => 'tool_use', 'tool' => 'create_property', 'status' => 'complete'];

            if (($result['success'] ?? false) === true && isset($result['entity_id'])) {
                $row = ['type' => 'entity_created', 'entity_type' => 'property', 'entity_id' => $result['entity_id'], 'name' => CaptureForms::kindLabel($form['name'], $kind)];
                $recordsCreated[] = self::recordRowFromEvent($row);
                yield $row;

                continue;
            }

            $errors[$kind] = [
                'message' => (string) ($result['message'] ?? 'The write failed.'),
                'fields' => is_array($result['errors'] ?? null)
                    ? array_map(static fn ($m): string => is_array($m) ? (string) ($m[0] ?? '') : (string) $m, $result['errors'])
                    : [],
            ];
        }

        if ($errors !== []) {
            yield ['type' => 'capture_form_errors', 'form' => $form['name'], 'errors' => $errors];
            $lines = [];
            foreach ($errors as $kind => $error) {
                $lines[] = CaptureForms::kindLabel($form['name'], $kind).': '.rtrim($error['message'], '.').'.';
            }
            $text = ($recordsCreated !== [] ? rtrim($this->buildCaptureCompleteSummary($recordsCreated), '. ').'. ' : '')
                ."I couldn't save ".implode(' ', $lines);
            yield ['type' => 'content', 'text' => $text];
            $saved = $this->saveMessage($conversation, 'assistant', $text, ['metadata' => [
                'onboarding_step' => $currentStateId,
                'capture_write_failed' => true,
                'turn_intent' => FynTurnIntent::CaptureClarification->value,
            ]]);
            $this->recordProgress($user, $currentStateId, ['selection' => 'savetax', 'raw_message' => mb_substr($message, 0, 500)]);
            yield ['type' => 'done', 'message_id' => $saved->id];

            return;
        }

        yield [
            'type' => 'capture_complete',
            'summary' => $this->buildCaptureCompleteSummary($recordsCreated),
            'records_created' => $recordsCreated,
        ];
        yield from $this->advanceAfterCapture($user, $conversation, $currentStateId, $message, 'savetax');
    }
```

Extract the advance block. In `handleAssetCaptureTurn`, everything after the `capture_complete` yield — from `$this->recordProgress(` through the end of the method — moves verbatim into a new method and is replaced by:

```php
        yield from $this->advanceAfterCapture($user, $conversation, $currentStateId, $message, $selection);
```

The new method:

```php
    /**
     * Record the answer, move to the next state and emit its turn — the one
     * advance every capture path takes (typed, backstop, form).
     */
    private function advanceAfterCapture(User $user, AiConversation $conversation, string $currentStateId, string $message, string $selection): \Generator
    {
        // …the moved block, byte-identical, with `$selection` now a parameter…
    }
```

Move the lines with an editor cut and paste, not by retyping. Then run `git diff --stat` and read the diff of `handleAssetCaptureTurn`: it must show only the removal and the one-line `yield from`.

- [ ] **Step 4: Run the tests**

Run: `./vendor/bin/pest tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php tests/Feature/Onboarding/PropertyCaptureLiveSentenceTest.php tests/Feature/Onboarding/CaptureRefusalRetryTest.php`
Expected: all pass (the last two prove the typed path still advances through the extracted helper).

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint app/Services/Onboarding/OnboardingChatDirector.php tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php
git add app/Services/Onboarding/OnboardingChatDirector.php tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php
git commit -m "feat(onboarding): a form answer writes each kind through the property handler and advances like a typed capture"
```

---

### Task 6: The controller carries the flag and the form through send, stream and start

**Files:**
- Modify: `app/Http/Controllers/Api/AiChatController.php` — `sendMessage` (~201-280), the stream method (~430-450), and the onboarding start method (find it with `grep -n "function startOnboarding" app/Http/Controllers/Api/AiChatController.php`)
- Modify: `app/Services/AI/Loop/ConcurrentTurnQueue.php` — `enqueue` (line 73)
- Test: `tests/Feature/AI/SendAiChatMessageFormRequestTest.php` (append) and the last case from Task 2

**Interfaces:**
- Consumes: `OnboardingChatDirector::setClientSupportsForms`, `handleUserMessage(..., form: $form)`, `CaptureForms::summarise`
- Produces: header contract `X-Fynla-Forms: 1`; queued messages keep `metadata.form`

- [ ] **Step 1: Write the failing tests** (append)

```php
function formStepHttpUser(): User
{
    $user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => false, 'onboarding_fyn_path' => 'campaign', 'onboarding_fyn_step' => \App\Services\Onboarding\OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY, 'onboarding_fyn_selection' => 'savetax', 'funnel_answers' => ['campaign' => 'savetax', 'assets' => ['property']]]);
    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);

    return $user;
}

it('streams the property form to a client declaring forms and the typed prompt to one that does not', function (): void {
    $user = formStepHttpUser();
    Sanctum::actingAs($user);
    \Tests\Support\Fyn\FynStreamHarness::fake()->bind();

    // The start endpoint re-emits the current step's turn for a resumed walk.
    $withForms = $this->withHeader('X-Fynla-Forms', '1')->postJson('/api/ai-chat/onboarding/start')->assertOk()->streamedContent();
    expect($withForms)->toContain('"type":"capture_form"');

    $without = $this->postJson('/api/ai-chat/onboarding/start')->assertOk()->streamedContent();
    expect($without)->not->toContain('"type":"capture_form"')
        ->and($without)->toContain('Now your property');
});

it('saves a posted form answer and records the plain-words line as the user message', function (): void {
    $user = formStepHttpUser();
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding']);
    Sanctum::actingAs($user);
    \Tests\Support\Fyn\FynStreamHarness::fake()->bind();

    $body = $this->withHeader('X-Fynla-Forms', '1')->postJson("/api/ai-chat/conversations/{$conversation->id}/messages", ['form' => ['name' => 'property', 'answers' => [
        'main_residence' => ['current_value' => 750000, 'mortgage_outstanding_balance' => 325000, 'ownership_type' => 'joint', 'ownership_percentage' => 50],
    ]]])->assertOk()->streamedContent();

    expect(\App\Models\Property::where('user_id', $user->id)->count())->toBe(1)
        ->and(\App\Models\AiMessage::where('conversation_id', $conversation->id)->where('role', 'user')->latest('id')->value('content'))->toBe('Home worth £750,000, mortgage £325,000, joint, my share 50%.')
        ->and($body)->toContain('"type":"onboarding_advance"');
});
```

If the start endpoint for a resumed campaign user emits the resume greeting (Continue / Something else) rather than the step, post `['message' => 'Continue']` to the conversation it created with the same header and assert on that stream instead; the assertion is on the presence or absence of `capture_form`.

- [ ] **Step 2: Run to verify they fail**

Run: `./vendor/bin/pest tests/Feature/AI/SendAiChatMessageFormRequestTest.php`
Expected: the two new tests and Task 2's last test fail.

- [ ] **Step 3: Implement**

In `ConcurrentTurnQueue::enqueue`:

```php
    public function enqueue(AiConversation $conversation, string $content, array $metadata = []): ?AiMessage
    {
        if ($this->isFull($conversation)) {
            return null;
        }

        $attributes = ['role' => 'user', 'content' => $content, 'status' => AiMessageStatus::Queued];
        if ($metadata !== []) {
            $attributes['metadata'] = $metadata;
        }

        return $conversation->messages()->create($attributes);
    }
```

In `AiChatController::sendMessage`, replace

```php
        $message = $request->input('message');
        $currentRoute = $request->input('current_route');
```

with

```php
        $form = $request->input('form');
        $form = is_array($form) ? $form : null;
        // A form answer arrives with no typed text; the transcript line is
        // composed in ONE place (CaptureForms) so every surface reads the same.
        $message = $form !== null ? CaptureForms::summarise($form) : (string) $request->input('message');
        $currentRoute = $request->input('current_route');
        $this->onboardingDirector->setClientSupportsForms($this->clientSupportsForms($request));
```

Change the enqueue line to `$queued = $this->queue->enqueue($conversation, $message, $form !== null ? ['form' => $form] : []);` and the director call to `$this->onboardingDirector->handleUserMessage($user, $conversation, $message, $currentRoute, true, $form)`.

In the stream method, after `$message = $queued->content;` add:

```php
        $queuedMetadata = is_array($queued->metadata) ? $queued->metadata : [];
        $form = is_array($queuedMetadata['form'] ?? null) ? $queuedMetadata['form'] : null;
        $this->onboardingDirector->setClientSupportsForms($this->clientSupportsForms($request));
```

and change its director call to `$this->onboardingDirector->handleUserMessage($user, $conversation, $message, $currentRoute, false, $form)`.

In the onboarding start method, before its director call add `$this->onboardingDirector->setClientSupportsForms($this->clientSupportsForms($request));`.

Add the helper at the bottom of the controller and the import `use App\Services\Onboarding\CaptureForms;`:

```php
    /** The web and /m bundles send `X-Fynla-Forms: 1`; native does not yet. */
    private function clientSupportsForms(Request $request): bool
    {
        return trim((string) $request->header('X-Fynla-Forms')) === '1';
    }
```

- [ ] **Step 4: Run the tests**

Run: `./vendor/bin/pest tests/Feature/AI/SendAiChatMessageFormRequestTest.php tests/Feature/Onboarding/StateMachineWalkthroughTest.php tests/Feature/AI`
Expected: all pass.

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint app/Http/Controllers/Api/AiChatController.php app/Services/AI/Loop/ConcurrentTurnQueue.php tests/Feature/AI/SendAiChatMessageFormRequestTest.php
git add app/Http/Controllers/Api/AiChatController.php app/Services/AI/Loop/ConcurrentTurnQueue.php tests/Feature/AI/SendAiChatMessageFormRequestTest.php
git commit -m "feat(ai-chat): the messages endpoint carries the forms capability and a form answer through send, queue and stream"
```

---

### Task 7: Web store and API — the form event, the form submission, history

**Files:**
- Modify: `resources/js/services/aiChatService.js` — `sendMessageStream` (lines 84-113) and the three sibling SSE fetches (`streamQueuedMessage` :149, `startOnboardingStream` :231, `postActionStream` :292)
- Modify: `resources/js/store/modules/aiChat.js` — mutations near `ADD_MESSAGE` (:116); `loadConversation` normalisation (:414-436); `sendMessage` (:463, its switch at :559); the other three switches (:946, :1191, :1464)
- Test: `tests/frontend/store/aiChatCaptureForm.test.js` (create), modelled on `tests/frontend/store/aiChatEvents.test.js`

**Interfaces:**
- Consumes: SSE events `capture_form {prompt_text, form}`, `capture_form_errors {form, errors}`, `form_received {text}` (Tasks 4-5); header `X-Fynla-Forms: 1` (Task 6)
- Produces:
  - `aiChatService.sendMessageStream(conversationId, message, currentRoute, { signal, form })` — body `{ message, current_route, form }` (`message` omitted when `form` is given)
  - store action `sendMessage(ctx, arg)` where `arg` is a string (today) **or** `{ form }` — one path for both
  - message rows: `{ role: 'capture_form', content: prompt_text, metadata: { capture_form: schema, errors: null } }`
  - mutation `SET_CAPTURE_FORM_ERRORS(state, errors)` — sets `metadata.errors` on the latest `capture_form` row
  - mutation `SET_TEMP_USER_CONTENT(state, { id, content })`

- [ ] **Step 1: Write the failing test**

```js
import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('@/services/aiChatService', () => ({
  default: { sendMessageStream: vi.fn(), streamQueuedMessage: vi.fn(), getConversation: vi.fn() },
}));
vi.mock('@/services/analyticsService', () => ({ default: { trackChatMessageSent: vi.fn() } }));

import aiChat from '@/store/modules/aiChat';
import aiChatService from '@/services/aiChatService';

function streamReader(events) {
  const payload = `${events.map((event) => `data: ${JSON.stringify(event)}`).join('\n\n')}\n\n`;
  const chunks = [new TextEncoder().encode(payload)];
  return { read: vi.fn(async () => (chunks.length > 0 ? { done: false, value: chunks.shift() } : { done: true, value: undefined })) };
}

const schema = { name: 'property', submit_label: 'Save', kinds: [{ key: 'main_residence', label: 'Home', fields: ['current_value'] }], fields: { current_value: { type: 'money', label: 'Value', required: true } } };

function makeCtx(overrides = {}) {
  const state = { ...aiChat.state(), currentConversation: { id: 7 }, messages: [], ...overrides };
  const commit = vi.fn((type, payload) => { if (aiChat.mutations[type]) aiChat.mutations[type](state, payload); });
  const dispatch = vi.fn();
  return { state, commit, dispatch, rootState: { auth: { user: { id: 1 } } } };
}

describe('capture form in the chat store', () => {
  beforeEach(() => vi.clearAllMocks());

  it('renders a capture_form event as a form row and never trips the empty-response banner', async () => {
    aiChatService.sendMessageStream.mockResolvedValue(streamReader([
      { type: 'capture_form', prompt_text: 'Now your property.', form: schema },
      { type: 'done', message_id: 9 },
    ]));
    const ctx = makeCtx();

    await aiChat.actions.sendMessage(ctx, 'Continue');

    const row = ctx.state.messages.find((m) => m.role === 'capture_form');
    expect(row).toBeTruthy();
    expect(row.content).toBe('Now your property.');
    expect(row.metadata.capture_form).toEqual(schema);
    expect(ctx.state.error).toBeNull();
  });

  it('posts a form answer with the forms header and no message, then replaces the placeholder user row', async () => {
    aiChatService.sendMessageStream.mockResolvedValue(streamReader([
      { type: 'form_received', text: 'Home worth £750,000, no mortgage, individual.' },
      { type: 'done', message_id: 10 },
    ]));
    const ctx = makeCtx();
    const form = { name: 'property', answers: { main_residence: { current_value: 750000, mortgage_outstanding_balance: null, ownership_type: 'individual' } } };

    await aiChat.actions.sendMessage(ctx, { form });

    expect(aiChatService.sendMessageStream).toHaveBeenCalledWith(7, null, expect.anything(), expect.objectContaining({ form }));
    const userRow = ctx.state.messages.find((m) => m.role === 'user');
    expect(userRow.content).toBe('Home worth £750,000, no mortgage, individual.');
  });

  it('attaches capture_form_errors to the latest form row', async () => {
    aiChatService.sendMessageStream.mockResolvedValue(streamReader([
      { type: 'capture_form_errors', form: 'property', errors: { buy_to_let: { message: 'You have reached your plan\'s property limit.', fields: {} } } },
      { type: 'done', message_id: 11 },
    ]));
    const ctx = makeCtx({ messages: [{ id: 'cf_1', role: 'capture_form', content: '', metadata: { capture_form: schema, errors: null } }] });

    await aiChat.actions.sendMessage(ctx, { form: { name: 'property', answers: {} } });

    expect(ctx.state.messages[0].metadata.errors.buy_to_let.message).toContain('property limit');
  });

  it('re-renders a persisted form on history load as text plus a form row', async () => {
    aiChatService.getConversation.mockResolvedValue({ data: { data: { id: 7, messages: [
      { id: 40, role: 'assistant', content: 'Now your property.', metadata: { capture_form: schema, onboarding_step: 'campaign_property' }, created_at: 'x' },
    ] } } });
    const ctx = makeCtx();

    await aiChat.actions.loadConversation(ctx, 7);

    const roles = ctx.state.messages.map((m) => m.role);
    expect(roles).toEqual(['assistant', 'capture_form']);
    expect(ctx.state.messages[1].metadata.capture_form).toEqual(schema);
  });
});
```

Read `tests/frontend/store/aiChatEvents.test.js` first and copy its exact `makeCtx`/`getConversation` response shape if it differs from the above — the assertions are what matter.

- [ ] **Step 2: Run to verify it fails**

Run: `npx vitest run tests/frontend/store/aiChatCaptureForm.test.js`
Expected: FAIL — no `capture_form` row; `sendMessageStream` called with a string.

- [ ] **Step 3: Implement**

`aiChatService.js` — add one constant after the imports and use it in all four SSE fetches:

```js
// Declares this client renders Fyn's structured capture forms (the /m
// bundle sends the same header; native does not yet, and gets the typed
// prompt for a form turn instead).
const FORMS_HEADER = { 'X-Fynla-Forms': '1' };
```

In each of the four `fetch(...)` calls add `...FORMS_HEADER,` inside `headers`. Change `sendMessageStream`:

```js
async sendMessageStream(conversationId, message, currentRoute = null, { signal, form = null } = {}) {
    const token = await getToken();
    const body = { current_route: currentRoute };
    if (form) {
        body.form = form;
    } else {
        body.message = message;
    }
    const response = await fetch(`${apiBaseURL}/api/ai-chat/conversations/${conversationId}/messages`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'text/event-stream', 'Authorization': `Bearer ${token}`, ...FORMS_HEADER },
        body: JSON.stringify(body),
        credentials: 'same-origin',
        signal,
    });
```

`aiChat.js` — mutations next to `ADD_MESSAGE`:

```js
SET_TEMP_USER_CONTENT(state, { id, content }) {
    const row = state.messages.find((m) => m.id === id);
    if (row) row.content = content;
},
SET_CAPTURE_FORM_ERRORS(state, errors) {
    for (let i = state.messages.length - 1; i >= 0; i -= 1) {
        if (state.messages[i].role === 'capture_form') {
            state.messages[i].metadata = { ...state.messages[i].metadata, errors };
            return;
        }
    }
},
```

A builder next to `entityWriteMessage`:

```js
function captureFormMessage(event) {
    return {
        id: 'cf_' + Date.now(),
        role: 'capture_form',
        content: event.prompt_text || '',
        metadata: { capture_form: event.form || null, errors: null },
        created_at: new Date().toISOString(),
    };
}
```

`sendMessage` head — accept a string or `{ form }`:

```js
async sendMessage({ commit, dispatch, state, rootState }, arg) {
    if (!state.currentConversation) return;
    const form = arg && typeof arg === 'object' ? (arg.form || null) : null;
    const message = form ? null : arg;
    const displayMessage = form ? 'Saving your property details…' : stripTags(message);
    const tempId = 'temp_' + Date.now();
    commit('ADD_MESSAGE', { id: tempId, role: 'user', content: displayMessage, created_at: new Date().toISOString() });
```

and pass `form` into the service call: `aiChatService.sendMessageStream(state.currentConversation.id, message, currentRoute, { signal: abortController.signal, form })`.

In the `sendMessage` switch (:559) add, next to `case 'quick_replies'`:

```js
case 'form_received':
    commit('SET_TEMP_USER_CONTENT', { id: tempId, content: event.text || '' });
    break;
case 'capture_form':
    if (state.streamingText) {
        commit('ADD_MESSAGE', { id: 'cf_text_' + Date.now(), role: 'assistant', content: state.streamingText, created_at: new Date().toISOString() });
        commit('SET_STREAMING_TEXT', '');
    }
    commit('ADD_MESSAGE', captureFormMessage(event));
    break;
case 'capture_form_errors':
    commit('SET_CAPTURE_FORM_ERRORS', event.errors || {});
    break;
```

Add the same three cases to the switches at :946 (`streamNextQueued`), :1191 (`postAction`) and :1464 (`startOnboardingConversation`). In those three there is no `tempId`; for `form_received` there they do nothing (`break;`) — only a direct form submission has a placeholder row.

History normalisation (:414-436) — before the `if (m.role === 'assistant' && hasBubbles)` branch add:

```js
const captureForm = m?.metadata?.capture_form;
if (m.role === 'assistant' && captureForm && typeof captureForm === 'object') {
    if (m.content) {
        normalised.push({ ...m, metadata: { ...m.metadata, capture_form: undefined } });
    }
    normalised.push({ id: `cf_${m.id}`, role: 'capture_form', content: '', metadata: { capture_form: captureForm, errors: null }, created_at: m.created_at });
    return; // inside the forEach callback; use `continue` if it is a for-loop
}
```

- [ ] **Step 4: Run the tests**

Run: `npx vitest run tests/frontend/store`
Expected: the new file passes and the existing store tests stay green.

- [ ] **Step 5: Commit**

```bash
git add resources/js/services/aiChatService.js resources/js/store/modules/aiChat.js tests/frontend/store/aiChatCaptureForm.test.js
git commit -m "feat(web): the chat store renders capture_form turns and posts form answers through the one send path"
```

---

### Task 8: Web renderer — `FynCaptureForm.vue` in the chat panel

**Files:**
- Create: `resources/js/components/Fyn/FynCaptureForm.vue`
- Modify: `resources/js/components/Shared/AiChatPanel.vue` — template next to `FynQuickReplies` (:182-189), import/registration (:431/:444), computed near `latestQuickRepliesIndex` (:589-600), a method near `handleQuickReplySelect` (:1220)
- Test: `tests/frontend/components/Fyn/FynCaptureForm.test.js` (create)

**Interfaces:**
- Consumes: the schema array (Task 1), store action `sendMessage({ form })` (Task 7), `currencyMixin.formatCurrency`
- Produces: component props `schema`, `errors`, `disabled`, `locked`, `values`; emit `submit(form)` with `{ name, answers }` holding only the opened kinds

Behaviour the component must have:
- Two option boxes from `schema.kinds`; clicking toggles a kind open. Both may be open.
- Per open kind, fields in `kind.fields` order. `money` → `<input type="number" inputmode="decimal" min="0" step="1">` with a `£` prefix span; `money_or_none` → the same input plus a checkbox labelled `field.none_label` that sets the value to `null` and disables the input; `choice` → a radio group; `percent` → number input 0.01–99.99, shown only when `required_when` is satisfied, pre-filled with `field.default`.
- Labels from `field.label`; an asterisk `*` appended when `field.required` is true, or `required_when` is satisfied. The asterisk is text, no icon.
- Save (`schema.submit_label`) disabled unless at least one kind is open and every required field of each open kind has a value (`null` counts as a value only for `money_or_none` with the none box ticked).
- `errors[kind].message` renders under that kind's box; `errors[kind].fields[field]` beside the field; both in `text-raspberry-600`.
- `locked` renders inputs disabled with `values[kind][field]` filled in and no Save button.
- Classes: box `rounded-lg border-2 border-raspberry-500 px-3 py-2 text-raspberry-500 bg-white`, open box adds `bg-raspberry-50`; inputs `form-input`; labels `label`; hint `form-hint`; Save `btn-primary btn-sm`. No hex, no icons.

- [ ] **Step 1: Write the failing test**

```js
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import FynCaptureForm from '@/components/Fyn/FynCaptureForm.vue';

const schema = {
  name: 'property', submit_label: 'Save',
  kinds: [
    { key: 'main_residence', label: 'Home', fields: ['current_value', 'mortgage_outstanding_balance', 'ownership_type', 'ownership_percentage'] },
    { key: 'buy_to_let', label: 'Buy to let', fields: ['current_value', 'mortgage_outstanding_balance', 'monthly_rental_income', 'ownership_type', 'ownership_percentage'] },
  ],
  fields: {
    current_value: { type: 'money', label: 'Value', required: true, hint: 'The full value' },
    mortgage_outstanding_balance: { type: 'money_or_none', label: 'Mortgage outstanding', required: true, none_label: 'No mortgage' },
    monthly_rental_income: { type: 'money', label: 'Monthly rental income', required: true },
    ownership_type: { type: 'choice', label: 'Ownership', required: true, options: [{ value: 'individual', label: 'Individual' }, { value: 'joint', label: 'Joint' }, { value: 'tenants_in_common', label: 'Tenants in common' }] },
    ownership_percentage: { type: 'percent', label: 'Your share %', required: false, default: 50, required_when: { field: 'ownership_type', in: ['joint', 'tenants_in_common'] } },
  },
};

const box = (w, label) => w.findAll('button').find((b) => b.text() === label);

describe('FynCaptureForm', () => {
  it('shows the two kinds closed with Save disabled', () => {
    const w = mount(FynCaptureForm, { props: { schema } });
    expect(box(w, 'Home')).toBeTruthy();
    expect(box(w, 'Buy to let')).toBeTruthy();
    expect(w.find('input').exists()).toBe(false);
    expect(w.find('button[type="submit"]').attributes('disabled')).toBeDefined();
  });

  it('opens Home to its fields with asterisks on the required ones', async () => {
    const w = mount(FynCaptureForm, { props: { schema } });
    await box(w, 'Home').trigger('click');
    const labels = w.findAll('label').map((l) => l.text());
    expect(labels).toContain('Value *');
    expect(labels).toContain('Mortgage outstanding *');
    expect(labels).toContain('Ownership *');
    expect(labels.some((l) => l.startsWith('Your share'))).toBe(false);
    expect(labels).not.toContain('Monthly rental income *');
  });

  it('reveals the share at 50 for Joint and enables Save once required fields are filled', async () => {
    const w = mount(FynCaptureForm, { props: { schema } });
    await box(w, 'Home').trigger('click');
    await w.find('input[name="main_residence.current_value"]').setValue('750000');
    await w.find('input[name="main_residence.mortgage_outstanding_balance"]').setValue('325000');
    expect(w.find('button[type="submit"]').attributes('disabled')).toBeDefined();
    await w.find('input[type="radio"][value="joint"]').setValue(true);
    const share = w.find('input[name="main_residence.ownership_percentage"]');
    expect(share.exists()).toBe(true);
    expect(share.element.value).toBe('50');
    expect(w.findAll('label').map((l) => l.text())).toContain('Your share % *');
    expect(w.find('button[type="submit"]').attributes('disabled')).toBeUndefined();
  });

  it('emits only the opened kinds, with No mortgage as null', async () => {
    const w = mount(FynCaptureForm, { props: { schema } });
    await box(w, 'Buy to let').trigger('click');
    await w.find('input[name="buy_to_let.current_value"]').setValue('450000');
    await w.find('input[name="buy_to_let.mortgage_outstanding_balance__none"]').setValue(true);
    await w.find('input[name="buy_to_let.monthly_rental_income"]').setValue('1000');
    await w.find('input[type="radio"][value="individual"]').setValue(true);
    await w.find('form').trigger('submit');
    expect(w.emitted('submit')[0][0]).toEqual({ name: 'property', answers: {
      buy_to_let: { current_value: 450000, mortgage_outstanding_balance: null, monthly_rental_income: 1000, ownership_type: 'individual' },
    } });
  });

  it('renders kind and field errors and locks with values', async () => {
    const w = mount(FynCaptureForm, { props: { schema, errors: { buy_to_let: { message: 'You have reached your plan\'s property limit.', fields: { current_value: 'Too large' } } } } });
    await box(w, 'Buy to let').trigger('click');
    expect(w.text()).toContain('property limit');
    expect(w.text()).toContain('Too large');

    const locked = mount(FynCaptureForm, { props: { schema, locked: true, values: { main_residence: { current_value: 750000, mortgage_outstanding_balance: 325000, ownership_type: 'joint', ownership_percentage: 50 } } } });
    expect(locked.find('button[type="submit"]').exists()).toBe(false);
    expect(locked.find('input[name="main_residence.current_value"]').element.value).toBe('750000');
    expect(locked.find('input[name="main_residence.current_value"]').attributes('disabled')).toBeDefined();
  });
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `npx vitest run tests/frontend/components/Fyn/FynCaptureForm.test.js`
Expected: FAIL — module not found.

- [ ] **Step 3: Write the component**

```vue
<template>
  <form class="fyn-capture-form" @submit.prevent="submit">
    <div class="flex flex-wrap gap-2">
      <button
        v-for="kind in schema.kinds"
        :key="kind.key"
        type="button"
        class="rounded-lg border-2 border-raspberry-500 px-3 py-2 text-sm font-medium text-raspberry-500 bg-white transition-standard"
        :class="{ 'bg-raspberry-50': isOpen(kind.key) }"
        :aria-pressed="isOpen(kind.key) ? 'true' : 'false'"
        :disabled="disabled || locked"
        @click="toggle(kind.key)"
      >{{ kind.label }}</button>
    </div>

    <div v-for="kind in openKinds" :key="kind.key" class="mt-3 rounded-lg border border-light-gray bg-white p-3">
      <p class="text-body-sm font-medium text-horizon-500">{{ kind.label }}</p>
      <p v-if="errors?.[kind.key]?.message" class="mt-1 text-xs text-raspberry-600">{{ errors[kind.key].message }}</p>

      <div v-for="fieldKey in visibleFields(kind)" :key="fieldKey" class="form-group mt-2">
        <label class="label" :for="inputId(kind.key, fieldKey)">{{ labelFor(kind.key, fieldKey) }}</label>

        <template v-if="field(fieldKey).type === 'money' || field(fieldKey).type === 'money_or_none'">
          <div class="flex items-center gap-2">
            <span class="text-sm text-neutral-500">£</span>
            <input
              :id="inputId(kind.key, fieldKey)"
              :name="kind.key + '.' + fieldKey"
              type="number"
              inputmode="decimal"
              min="0"
              step="1"
              class="form-input"
              :disabled="disabled || locked || isNone(kind.key, fieldKey)"
              :value="answers[kind.key][fieldKey] ?? ''"
              @input="setNumber(kind.key, fieldKey, $event.target.value)"
            >
          </div>
          <label v-if="field(fieldKey).type === 'money_or_none'" class="mt-1 flex items-center gap-2 text-sm text-neutral-500">
            <input
              type="checkbox"
              :name="kind.key + '.' + fieldKey + '__none'"
              :checked="isNone(kind.key, fieldKey)"
              :disabled="disabled || locked"
              @change="setNone(kind.key, fieldKey, $event.target.checked)"
            >
            {{ field(fieldKey).none_label }}
          </label>
        </template>

        <div v-else-if="field(fieldKey).type === 'choice'" class="flex flex-wrap gap-3">
          <label v-for="option in field(fieldKey).options" :key="option.value" class="flex items-center gap-1 text-sm text-horizon-500">
            <input
              type="radio"
              :name="kind.key + '.' + fieldKey"
              :value="option.value"
              :checked="answers[kind.key][fieldKey] === option.value"
              :disabled="disabled || locked"
              @change="setChoice(kind.key, fieldKey, option.value)"
            >
            {{ option.label }}
          </label>
        </div>

        <input
          v-else-if="field(fieldKey).type === 'percent'"
          :id="inputId(kind.key, fieldKey)"
          :name="kind.key + '.' + fieldKey"
          type="number"
          inputmode="decimal"
          min="0.01"
          max="99.99"
          step="0.01"
          class="form-input"
          :disabled="disabled || locked"
          :value="answers[kind.key][fieldKey] ?? ''"
          @input="setNumber(kind.key, fieldKey, $event.target.value)"
        >

        <p v-if="field(fieldKey).hint" class="form-hint">{{ field(fieldKey).hint }}</p>
        <p v-if="errors?.[kind.key]?.fields?.[fieldKey]" class="mt-1 text-xs text-raspberry-600">{{ errors[kind.key].fields[fieldKey] }}</p>
      </div>
    </div>

    <div v-if="!locked" class="mt-3">
      <button type="submit" class="btn-primary btn-sm" :disabled="disabled || !isValid">{{ schema.submit_label || 'Save' }}</button>
    </div>
  </form>
</template>

<script>
/**
 * A server-driven capture form inside the Fyn chat (CaptureForms on the
 * server is the one schema home; this component knows kinds and field
 * types, never property). Emits `submit` with { name, answers } holding
 * only the opened kinds. No icons; the asterisk is text.
 */
export default {
  name: 'FynCaptureForm',
  props: {
    schema: { type: Object, required: true },
    errors: { type: Object, default: null },
    disabled: { type: Boolean, default: false },
    locked: { type: Boolean, default: false },
    values: { type: Object, default: null },
  },
  emits: ['submit'],
  data() {
    const open = {};
    const answers = {};
    const none = {};
    (this.schema.kinds || []).forEach((kind) => {
      const given = this.values && this.values[kind.key] ? this.values[kind.key] : null;
      open[kind.key] = Boolean(given);
      answers[kind.key] = given ? { ...given } : {};
      none[kind.key] = {};
      if (given && Object.prototype.hasOwnProperty.call(given, 'mortgage_outstanding_balance') && given.mortgage_outstanding_balance === null) {
        none[kind.key].mortgage_outstanding_balance = true;
      }
    });
    return { open, answers, none };
  },
  computed: {
    openKinds() { return (this.schema.kinds || []).filter((k) => this.open[k.key]); },
    isValid() {
      if (this.openKinds.length === 0) return false;
      return this.openKinds.every((kind) => this.visibleFields(kind).every((fieldKey) => {
        if (!this.isRequired(kind.key, fieldKey)) return true;
        const type = this.field(fieldKey).type;
        if (type === 'money_or_none') return this.isNone(kind.key, fieldKey) || this.hasNumber(kind.key, fieldKey);
        if (type === 'choice') return Boolean(this.answers[kind.key][fieldKey]);
        return this.hasNumber(kind.key, fieldKey);
      }));
    },
  },
  methods: {
    field(key) { return this.schema.fields[key]; },
    isOpen(kindKey) { return Boolean(this.open[kindKey]); },
    toggle(kindKey) { this.open[kindKey] = !this.open[kindKey]; },
    inputId(kindKey, fieldKey) { return `fyn-form-${kindKey}-${fieldKey}`; },
    isNone(kindKey, fieldKey) { return Boolean(this.none[kindKey] && this.none[kindKey][fieldKey]); },
    hasNumber(kindKey, fieldKey) { const v = this.answers[kindKey][fieldKey]; return typeof v === 'number' && !Number.isNaN(v); },
    conditionMet(kindKey, fieldKey) {
      const when = this.field(fieldKey).required_when;
      if (!when) return true;
      return (when.in || []).includes(this.answers[kindKey][when.field]);
    },
    visibleFields(kind) { return kind.fields.filter((fieldKey) => !this.field(fieldKey).required_when || this.conditionMet(kind.key, fieldKey)); },
    isRequired(kindKey, fieldKey) {
      const f = this.field(fieldKey);
      return Boolean(f.required) || (Boolean(f.required_when) && this.conditionMet(kindKey, fieldKey));
    },
    labelFor(kindKey, fieldKey) { return this.field(fieldKey).label + (this.isRequired(kindKey, fieldKey) ? ' *' : ''); },
    setNumber(kindKey, fieldKey, raw) {
      const n = raw === '' ? null : Number(raw);
      this.answers[kindKey] = { ...this.answers[kindKey], [fieldKey]: n === null || Number.isNaN(n) ? undefined : n };
    },
    setNone(kindKey, fieldKey, checked) {
      this.none[kindKey] = { ...this.none[kindKey], [fieldKey]: checked };
      if (checked) this.answers[kindKey] = { ...this.answers[kindKey], [fieldKey]: null };
      else { const next = { ...this.answers[kindKey] }; delete next[fieldKey]; this.answers[kindKey] = next; }
    },
    setChoice(kindKey, fieldKey, value) {
      const next = { ...this.answers[kindKey], [fieldKey]: value };
      const share = this.field('ownership_percentage');
      if (share && share.required_when && share.required_when.field === fieldKey) {
        if ((share.required_when.in || []).includes(value)) { if (next.ownership_percentage === undefined) next.ownership_percentage = share.default; }
        else delete next.ownership_percentage;
      }
      this.answers[kindKey] = next;
    },
    submit() {
      if (!this.isValid || this.locked || this.disabled) return;
      const answers = {};
      this.openKinds.forEach((kind) => {
        const out = {};
        this.visibleFields(kind).forEach((fieldKey) => {
          const v = this.answers[kind.key][fieldKey];
          if (v !== undefined) out[fieldKey] = v;
        });
        answers[kind.key] = out;
      });
      this.$emit('submit', { name: this.schema.name, answers });
    },
  },
};
</script>

<style scoped>
.fyn-capture-form { padding: 8px 0; }
</style>
```

`AiChatPanel.vue` — after the `FynQuickReplies` block add:

```html
<!-- Structured capture form (Fyn onboarding form turn) -->
<FynCaptureForm
  v-if="msg.role === 'capture_form'"
  :schema="msg.metadata?.capture_form"
  :errors="msg.metadata?.errors || null"
  :disabled="streaming || loading"
  :locked="!isCaptureFormOpen(idx)"
  :values="captureFormValues(idx)"
  @submit="handleCaptureFormSubmit"
/>
```

Import and register it next to `FynQuickReplies` (:431/:444). Add to `computed`:

```js
latestCaptureFormIndex() {
    for (let i = this.messages.length - 1; i >= 0; i -= 1) {
        if (this.messages[i]?.role === 'capture_form') return i;
    }
    return -1;
},
```

Add to `methods`:

```js
// A form is open only while it is the newest form and nothing has been
// answered after it; a refresh mid-step re-renders it open.
isCaptureFormOpen(idx) {
    if (idx !== this.latestCaptureFormIndex) return false;
    return !this.messages.slice(idx + 1).some((m) => m.role === 'user');
},
captureFormValues(idx) {
    const answered = this.messages.slice(idx + 1).find((m) => m.role === 'user' && m.metadata?.form?.answers);
    return answered ? answered.metadata.form.answers : null;
},
async handleCaptureFormSubmit(form) {
    if (this.streaming || this.loading) return;
    window.dispatchEvent(new Event('fyn-chat-interaction'));
    if (!await this.ensureConversation()) return;
    analyticsService.trackChatMessageSent(0);
    await this.sendMessage({ form });
},
```

- [ ] **Step 4: Run the tests**

Run: `npx vitest run tests/frontend/components/Fyn tests/frontend/store`
Expected: all pass.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/Fyn/FynCaptureForm.vue resources/js/components/Shared/AiChatPanel.vue tests/frontend/components/Fyn/FynCaptureForm.test.js
git commit -m "feat(web): FynCaptureForm renders a capture_form turn in the chat panel and posts its answer"
```

---

### Task 9: `/m` transport and mixin — the form event, the submission, history

**Files:**
- Modify: `resources/mobile/api.js` — `apiStream` headers (:101-110)
- Modify: `resources/mobile/mixins/onboardingChat.js` — `handleFynEvent` (:360-556), `send` (:583-637), `loadConversationTranscript` (:260-308)
- Test: `resources/mobile/mixins/__tests__/onboardingChat.spec.js` (append)

**Interfaces:**
- Consumes: the same three events as Task 7; header `X-Fynla-Forms: 1`
- Produces:
  - `/m` message rows gain an optional `form` field: `{ role: 'fyn', text, bubbles, form: { schema, errors: null, answers: null, locked: false } }`
  - mixin method `submitCaptureForm(form)` — the same `send` path with `{ form }` instead of `{ message }`

- [ ] **Step 1: Write the failing tests** (append to the spec, reusing its `Host` and mocked `apiStream`)

```js
describe('capture forms', () => {
  const schema = { name: 'property', submit_label: 'Save', kinds: [{ key: 'main_residence', label: 'Home', fields: ['current_value'] }], fields: { current_value: { type: 'money', label: 'Value', required: true } } };

  it('renders a capture_form event as a form on the Fyn row and marks the reply as received', () => {
    const w = mount(Host);
    const cursor = { reply: { role: 'fyn', text: '', bubbles: [] }, got: false };
    w.vm.messages = [cursor.reply];
    w.vm.handleFynEvent(cursor, { type: 'capture_form', prompt_text: 'Now your property.', form: schema });
    expect(cursor.got).toBe(true);
    expect(cursor.reply.text).toBe('Now your property.');
    expect(cursor.reply.form.schema).toEqual(schema);
    expect(cursor.reply.form.locked).toBe(false);
  });

  it('posts a form answer with the forms header and no message, and replaces the placeholder user row', async () => {
    const { apiStream } = await import('../../api.js');
    apiStream.mockImplementation(async (path, body, token, onDelta, onEvent) => {
      onEvent({ type: 'form_received', text: 'Home worth £750,000, no mortgage, individual.' });
      onEvent({ type: 'done' });
      return { ok: true, status: 200, text: '' };
    });
    const w = mount(Host);
    w.vm.conversationId = 7;
    const form = { name: 'property', answers: { main_residence: { current_value: 750000 } } };

    await w.vm.submitCaptureForm(form);

    const body = apiStream.mock.calls.at(-1)[1];
    expect(body.form).toEqual(form);
    expect(body.message).toBeUndefined();
    const user = w.vm.messages.find((m) => m.role === 'user');
    expect(user.text).toBe('Home worth £750,000, no mortgage, individual.');
  });

  it('locks every earlier form and attaches errors to the latest', () => {
    const w = mount(Host);
    const row = { role: 'fyn', text: 'x', bubbles: [], form: { schema, errors: null, answers: null, locked: false } };
    w.vm.messages = [row];
    w.vm.handleFynEvent({ reply: row, got: true }, { type: 'capture_form_errors', form: 'property', errors: { main_residence: { message: 'Too many', fields: {} } } });
    expect(row.form.errors.main_residence.message).toBe('Too many');
  });

  it('re-renders a persisted form from history as a locked-or-open form row', async () => {
    const { apiGet } = await import('../../api.js');
    apiGet.mockResolvedValueOnce({ ok: true, status: 200, data: { data: { messages: [
      { role: 'assistant', content: 'Now your property.', metadata: { capture_form: schema } },
    ] } } });
    const w = mount(Host);
    w.vm.conversationId = 7;
    await w.vm.loadConversationTranscript();
    expect(w.vm.messages.at(-1).form.schema).toEqual(schema);
    expect(w.vm.messages.at(-1).form.locked).toBe(false);
  });
});
```

Copy the exact `apiGet` response envelope the existing `loadConversationTranscript` test in that spec uses.

- [ ] **Step 2: Run to verify they fail**

Run: `npx vitest run resources/mobile/mixins`
Expected: the four new cases fail.

- [ ] **Step 3: Implement**

`api.js` `apiStream` headers — add `'X-Fynla-Forms': '1',` after `'Accept': 'text/event-stream',`. (One line; the GET/POST helpers do not need it.)

`onboardingChat.js` — in `handleFynEvent`, before the `quick_replies` branch:

```js
if (ev.type === 'form_received') {
  const placeholder = [...this.messages].reverse().find((m) => m.role === 'user' && m.formPlaceholder);
  if (placeholder) { placeholder.text = ev.text || placeholder.text; delete placeholder.formPlaceholder; }
  return;
}
if (ev.type === 'capture_form') {
  // A form turn. Like quick_replies: a fresh row if the cursor already
  // carries streamed text; cursor.got so the empty-response trap is quiet.
  if (cursor.reply.text) {
    cursor.reply = { role: 'fyn', text: '', bubbles: [] };
    this.messages.push(cursor.reply);
  }
  cursor.got = true;
  if (ev.prompt_text) cursor.reply.text = ev.prompt_text;
  cursor.reply.form = { schema: ev.form || null, errors: null, answers: null, locked: false };
  this.$nextTick(this.scrollFyn);
  return;
}
if (ev.type === 'capture_form_errors') {
  const latest = [...this.messages].reverse().find((m) => m.form);
  if (latest) latest.form = { ...latest.form, errors: ev.errors || {} };
  return;
}
```

`send` — split its body so a form can reuse it. Change the signature and the two places that read the text:

```js
async send(text = null, form = null) {
  const draft = form ? '' : (text ?? this.draft).trim();
  if (!form && !draft) return;
  if (this.sending) return;
  this.draft = '';
  this.messages.forEach((m) => { m.bubbles = []; if (m.form) m.form = { ...m.form, locked: true }; });
  this.messages.push(form
    ? { role: 'user', text: 'Saving your property details…', bubbles: [], formPlaceholder: true }
    : { role: 'user', text: draft, bubbles: [] });
  // …unchanged down to the apiStream call, whose body becomes:
  const body = { current_route: (this.$route && this.$route.path) || '/dashboard' };
  if (form) body.form = form; else body.message = draft;
  const result = await apiStream(`/api/ai-chat/conversations/${cid}/messages`, body, store.token, (piece) => { this.appendFynText(cursor, piece); }, (ev) => this.handleFynEvent(cursor, ev));
```

Add the method:

```js
submitCaptureForm(form) {
  return this.send(null, form);
},
```

`loadConversationTranscript` — in the `map`, read the persisted schema and lock every form but the last row's:

```js
const captureForm = metadata.capture_form && typeof metadata.capture_form === 'object' ? metadata.capture_form : null;
return {
  role: m.role === 'user' ? 'user' : 'fyn',
  text: m.content || '',
  bubbles,
  actionBubbles: Boolean(metadata.action_bubbles),
  ...(captureForm ? { form: { schema: captureForm, errors: null, answers: metadata.form_answers || null, locked: false } } : {}),
};
```

and after `mapped.forEach((m, i) => { if (i < mapped.length - 1) m.bubbles = []; });` add:

```js
mapped.forEach((m, i) => { if (m.form && i < mapped.length - 1) m.form = { ...m.form, locked: true }; });
```

- [ ] **Step 4: Run the tests**

Run: `npx vitest run resources/mobile/mixins`
Expected: all pass.

- [ ] **Step 5: Commit**

```bash
git add resources/mobile/api.js resources/mobile/mixins/onboardingChat.js resources/mobile/mixins/__tests__/onboardingChat.spec.js
git commit -m "feat(m): the onboarding chat mixin renders capture_form turns and posts form answers through the one send path"
```

---

### Task 10: `/m` renderer — `FynCaptureForm.vue` in both chat templates

**Files:**
- Create: `resources/mobile/components/FynCaptureForm.vue`
- Modify: `resources/mobile/views/Dashboard.vue` (:321-335) and `resources/mobile/components/MobileChrome.vue` (:141-155) — one tag each inside the message loop
- Modify: `resources/mobile/views/dashboard.css` — `.md-fyn__form*` classes after `.md-fyn__bubble` (:1879)
- Test: `resources/mobile/components/__tests__/FynCaptureForm.spec.js` (create)

**Interfaces:**
- Consumes: the schema; row `m.form = { schema, errors, answers, locked }`; mixin `submitCaptureForm(form)` (Task 9); `formatCurrency` from `resources/mobile/utils/currency.js` for the locked display
- Produces: props `schema`, `errors`, `disabled`, `locked`, `values`; emit `submit(form)` — the same contract as the web component

The behaviour and the emitted payload are identical to Task 8 (same kinds, fields, asterisks, none box, share reveal, Save gating, errors, locked). The differences are only presentational: no Tailwind on `/m`, so classes are `md-fyn__form`, `md-fyn__form-kind` (+ `--open`), `md-fyn__form-field`, `md-fyn__form-label`, `md-fyn__form-hint`, `md-fyn__form-error`, and the existing `.m-field` on inputs and `.m-btn` on Save. Palette via `var(--raspberry-500)`, `var(--horizon-200)`, `var(--neutral-500)`, `var(--raspberry-600)`; radius via `var(--radius-md)`.

- [ ] **Step 1: Write the failing test**

Copy `tests/frontend/components/Fyn/FynCaptureForm.test.js` from Task 8 to `resources/mobile/components/__tests__/FynCaptureForm.spec.js`, changing only the import to `../FynCaptureForm.vue`. The same five cases must pass — the two renderers share one contract.

- [ ] **Step 2: Run to verify it fails**

Run: `npx vitest run resources/mobile/components/__tests__/FynCaptureForm.spec.js`
Expected: FAIL — module not found.

- [ ] **Step 3: Write the component, the CSS and the two template lines**

Component: the Task 8 template with the class swaps above (the `<script>` is the same code, copied — the bundles are isolated by architecture and share no modules except `store/modules/auth.js`). Keep the `name`/`id` attributes identical to Task 8 so the shared test passes.

CSS, appended after `.md-fyn__bubble:disabled` in `dashboard.css`:

```css
.md-fyn__form { padding: 8px 0; }
.md-fyn__form-kinds { display: flex; flex-wrap: wrap; gap: 8px; }
.md-fyn__form-kind {
  background: var(--white);
  border: 2px solid var(--raspberry-500);
  color: var(--raspberry-500);
  padding: 0.5rem 0.875rem;
  border-radius: var(--radius-md);
  font-size: 0.875rem;
  font-weight: 600;
  font-family: var(--font-primary);
  cursor: pointer;
}
.md-fyn__form-kind--open { background: var(--raspberry-50); }
.md-fyn__form-kind:disabled { opacity: 0.5; cursor: not-allowed; }
.md-fyn__form-section { margin-top: 12px; border: 1px solid var(--horizon-200); border-radius: var(--radius-md); padding: 12px; background: var(--white); }
.md-fyn__form-field { margin-top: 10px; }
.md-fyn__form-label { display: block; font-size: 0.8125rem; font-weight: 600; color: var(--neutral-500); margin-bottom: 4px; }
.md-fyn__form-money { display: flex; align-items: center; gap: 8px; }
.md-fyn__form-choices { display: flex; flex-wrap: wrap; gap: 12px; font-size: 0.875rem; }
.md-fyn__form-choices label { display: flex; align-items: center; gap: 4px; }
.md-fyn__form-hint { font-size: 0.75rem; color: var(--neutral-500); margin-top: 4px; }
.md-fyn__form-error { font-size: 0.75rem; color: var(--raspberry-600); margin-top: 4px; }
.md-fyn__form-actions { margin-top: 12px; }
```

Template line, added in **both** `Dashboard.vue` and `MobileChrome.vue` directly after the `.md-fyn__bubbles` block inside the message loop:

```html
<FynCaptureForm
  v-if="m.form && m.form.schema"
  :schema="m.form.schema"
  :errors="m.form.errors"
  :disabled="sending"
  :locked="m.form.locked"
  :values="m.form.answers"
  @submit="submitCaptureForm"
/>
```

Import and register `FynCaptureForm` in both files' `components`. (Two one-line template insertions; the behaviour lives in the one component. The duplicated message-loop template itself is pre-existing debt — note it in the PR, do not refactor it here.)

- [ ] **Step 4: Run the tests and build the bundle**

Run: `npx vitest run resources/mobile && npm run build:mobile 2>&1 | tail -3`
Expected: tests pass; the mobile bundle builds without warnings about the new component.

- [ ] **Step 5: Commit**

```bash
git add resources/mobile/components/FynCaptureForm.vue resources/mobile/views/Dashboard.vue resources/mobile/components/MobileChrome.vue resources/mobile/views/dashboard.css resources/mobile/components/__tests__/FynCaptureForm.spec.js
git commit -m "feat(m): FynCaptureForm renders a capture_form turn in the /m chat and posts its answer"
```

---

### Task 11: Deploy the branch to csjones and verify in CSJ's Chrome, web then `/m`

**Files:** none new. Build outputs `public/build/` and `public/m-build/`.

- [ ] **Step 1: Run the full affected suites once, alone**

Run: `./vendor/bin/pest tests/Feature/Onboarding tests/Unit/Services/Onboarding tests/Feature/AI tests/Unit/Services/AI tests/Feature/Property tests/Feature/Tiers tests/Architecture && npx vitest run`
Expected: 0 failed. Record the counts for the PR.

- [ ] **Step 2: Build both bundles and deploy the branch to csjones**

```bash
./deploy/csjones-fynla/build.sh
git push -u origin feat/savetax-property-capture-form
rsync -az -e "ssh -p 18765 -i ~/.ssh/fynlaDev" public/build/ u163-ptanegf9edny@ssh.csjones.co:~/www/csjones.co/fynla-app/public/build/
rsync -az -e "ssh -p 18765 -i ~/.ssh/fynlaDev" public/m-build/ u163-ptanegf9edny@ssh.csjones.co:~/www/csjones.co/fynla-app/public/m-build/
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co 'cd ~/www/csjones.co/fynla-app && git fetch -q origin && git checkout -q feat/savetax-property-capture-form && git pull -q origin feat/savetax-property-capture-form && php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan config:cache && php artisan fyn:procedural:validate | grep onboarding.workflow'
```

`rsync` without `--delete` so an in-flight session keeps its old chunks (warn CSJ before replacing the bundle if they are mid-test).

- [ ] **Step 3: Web walk in CSJ's Chrome (claude-in-chrome, never headless)**

Register a fresh account on `https://csjones.co/fynla` (or reset an existing gate-0915 test user to `campaign_property`), verify the code from the server, walk the Save Tax funnel to the property step. Confirm, with CSJ watching: the form appears with Home and Buy to let; asterisks on the required fields; Joint reveals the share at 50; No mortgage disables the amount; Save is disabled until valid; Save posts; the transcript shows the plain-words line; the walk continues to the verify page where both records show the right value, mortgage, share and rent; the DOB prompt follows. Then a second account for the error path: three properties, the third refused on its box with the plan-limit message and the two saved ones intact.

- [ ] **Step 4: `/m` walk** (per the `verify-m` skill — cold navigation to `/m` on csjones with the desktop token bridge does not fire; use the documented path)

Same checks in the `/m` chat on the dashboard and in the docked bar on a module screen.

- [ ] **Step 5: Native untouched**

`curl` the messages endpoint with a native-style request (no `X-Fynla-Forms` header) for a user at the property step and confirm the stream carries the typed prompt and no `capture_form` event.

- [ ] **Step 6: Commit nothing; record the evidence**

Screenshots to `.playwright-mcp/gate-<date>/` or the Chrome captures; note test users and conversation ids for the PR body.

---

### Task 12: PR to dev

- [ ] **Step 1: Open the PR** against `dev` from `feat/savetax-property-capture-form` with: the spec link; one line per task; the suite counts from Task 11 Step 1; the live evidence from Task 11 Steps 3-5; the deploy notes (PHP + `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md` + both bundles; no migration; run `fyn:procedural:validate`); the adjacent debt noted (duplicated `/m` message-loop template; native renderer for the form is a follow-up).
- [ ] **Step 2: Put csjones back on dev** after CSJ's go: `git checkout dev && git pull origin dev` on the server, re-cache config.
- [ ] **Step 3:** CSJ decides the merge and the release (`/release`). Never recommend deploying.

---

## Self-review against the spec

- Schema, kinds, fields, asterisks, ownership options, share default → Task 1, 8, 10.
- `turn_type: form` on the corpus-owned state → Task 3.
- `capture_form` event, persisted schema, history re-render → Tasks 4, 7, 9.
- Client capability header, native fallback → Tasks 4, 6, 7, 9, 11 Step 5.
- Submission through the one endpoint, request shape validation → Tasks 2, 6.
- Writes through `executeTool` with confirmed facts, no null optionals, advance like typed → Task 5.
- Field errors, tier cap, refresh, stale form, typed fallback → Tasks 5, 7, 8, 9, 10.
- Locked historical forms → Tasks 8, 9, 10.
- Testing on Pest, Vitest and a watched browser walk on web and `/m` → every task's tests plus Task 11.
- Out of scope held: no extractor/gate changes, no native renderer, no other step.

Type consistency: the event names `capture_form`, `capture_form_errors`, `form_received`; the row role `capture_form`; `metadata.capture_form` (schema) on the assistant row and `metadata.form` (answers) on the user row; the header `X-Fynla-Forms: 1`; `sendMessage({ form })` on web and `submitCaptureForm(form)` → `send(null, form)` on `/m`; `CaptureForms::{names,schema,rules,toolInputs,summarise,kindLabel}` — used with those exact names throughout.
