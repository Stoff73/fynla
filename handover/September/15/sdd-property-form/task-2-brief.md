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
Expected: the four 422 cases pass. The last case will still fail until Task 6 (the controller reads `message` and passes null into the director) — leave it red; it goes green in Task 6.

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint app/Http/Requests/AI/SendAiChatMessageRequest.php tests/Feature/AI/SendAiChatMessageFormRequestTest.php
git add app/Http/Requests/AI/SendAiChatMessageRequest.php tests/Feature/AI/SendAiChatMessageFormRequestTest.php
git commit -m "feat(ai-chat): the messages request accepts a structured capture-form answer"
```

---

