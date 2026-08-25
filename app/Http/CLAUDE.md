# HTTP Layer Conventions

This file supplements the root `CLAUDE.md` with HTTP-specific patterns.

> **GOLDEN RULE #20 (CSJ, NEVER IGNORE):** every Fyn change — prompt, vocabulary, behaviour, rendering — is made ONCE, in ONE place, for ALL surfaces and paths. If more than one mechanism implements the behaviour, consolidating to one source is PART of the fix. Full text: root `CLAUDE.md` Rule 20.

## API Response Format

All controllers return a consistent JSON structure:
```json
{
  "success": true|false,
  "message": "Human-readable message",
  "data": { ... }
}
```

**Status codes:**

| Code | Usage |
|------|-------|
| 200 | GET success, updates, deletes |
| 201 | POST resource created |
| 400 | Bad request / validation |
| 401 | Unauthenticated |
| 403 | Forbidden / MFA required |
| 404 | Not found or access denied |
| 422 | Validation failed |
| 423 | Account locked (login) |
| 500 | Server error |

## Controller Pattern

```php
public function __construct(
    private readonly ModuleAgent $agent,
    private readonly SomeService $service
) {}

// Standard CRUD: index(), store(), show(), update(), destroy()
// Domain: analyze(), scenarios(), recommendations(), calculate*()
```

- Inject Agents and Services via constructor (`private readonly`)
- Use `SanitizedErrorResponse` or `HandleApiExceptions` trait for error handling
- Transform models via Resource classes before returning
- Never use `DB` facade directly (use services/models)

## Middleware Stack

**Applied to all API routes (`/api/*`):**
1. Sanctum stateful auth
2. ThrottleRequests (rate limiting)
3. SubstituteBindings
4. **SanitizeInput** - Strips HTML tags, trims whitespace (exempts password fields)
5. **PreviewWriteInterceptor** - Blocks writes from preview users

**Route-level middleware:**

| Middleware | Purpose |
|-----------|---------|
| `auth:sanctum` | Requires Bearer token |
| `mfa.verified` | Checks MFA completion |
| `admin` | Admin-only routes |
| `role:rolename` | Role-based access |
| `throttle:5,1` | 5 requests per minute |

## PreviewWriteInterceptor

Blocks POST/PUT/PATCH/DELETE from preview users. Returns fake success responses.

**Excluded routes** (always allowed): auth routes, preview exit/switch, onboarding, document upload, webhooks.

**Excluded patterns** (calculation endpoints pass through): `/calculate`, `/projections`, `/recalculate`, `/analyze`.

**When adding new auth-related POST routes**, add them to `EXCLUDED_ROUTES` in `PreviewWriteInterceptor.php`.

## Request Validation

All Form Requests extend `FormRequest`:
```php
public function authorize(): bool { return true; }
public function rules(): array { return [...]; }
public function messages(): array { return [...]; }  // Custom error messages
```

**Common validation patterns:**
```php
'ownership_type' => ['nullable', Rule::in(['individual', 'joint', 'tenants_in_common', 'trust'])],
'current_value' => 'required|numeric|min:0|max:999999999.99',
'interest_rate' => 'nullable|numeric|min:0|max:20',
'joint_owner_id' => ['nullable', 'exists:users,id'],
```

Use `ValidationLimits::currencyRules()` and `ValidationLimits::percentageRules()` for consistent rules.

### A rule and its column must agree — but the two directions are NOT symmetric

**Added 2026-08-23**, from the cycle-4 validation sweep. Six separate axes of
rule-vs-schema disagreement were found in one batch, each invisible to a sweep for the
others.

| Direction | Verdict |
|---|---|
| **Rule wider than the column** | **Always a defect.** The value passes validation and dies at the write — `SQLSTATE[22003] Out of range` or `1048 cannot be null`. Nothing legitimises it. |
| **Column wider than the rule** | **Depends entirely on whether anything offers the excluded value.** Refusing what no path can produce is a *decision*. Refusing what the form puts in front of the user is a *defect*. |

Both appeared in **one line** of `MortgageStore`: `capped`/`offset` accepted but unstorable
(direction 1), and `mixed` refused while the form offers it and three request classes allow
it (direction 2). In the **same file**, `ownership_type` refusing `tenants_in_common` is
**correct** — `MortgageNormaliser` coerces it to `joint` and documents that mortgages do not
support it.

**So a test asserting rule and column MATCH would have enforced a regression.** A guard
written to the wrong principle does not merely miss defects — it manufactures them and then
defends them, with all the authority of a green suite. Guards here carry an **exception
list**, and **every exception must name the mechanism that guarantees the excluded value
never arrives**, or the list stops recording decisions and becomes a place to hide drift.

**The six axes, each blind to the others:**
1. `nullable` rule on a **NOT NULL** column — 192 occurrences.
2. Field **fillable and offered by a form but absent from `rules()`** — silently stripped by
   `validated()`; 95 occurrences.
3. Rule **range exceeds column precision** — e.g. `max:100` on `decimal(5,4)`, which stops at
   9.9999.
4. **No `max:` rule at all**, leaving the column as the only guard.
5. The same column written under a **different, prefixed name** in another request — invisible
   to a name-matching sweep.
6. **`app/Services/Stores/` validate separately from `app/Http/Requests/`.**

**A FOURTH structural blind spot: no guard moves a configured rate and asserts on a Vue
template.** Added 2026-08-23. The Rule 2 charitable family was swept **twice and declared
closed twice**; both sweeps covered `app/` and exactly **one** Vue file — and that one only
because it happened to be open for another reason. `RateLiteralsComeFromConfigurationTest`
and `CharitableExemptionVersusRateTestTest` both drive PHP services and assert on **service
output**, so **the entire frontend sits outside the family.** Nine instances across seven
files survived two "complete" sweeps.

**The sharpest instance was authored BY one of those sweeps.** `IHTPlanning.vue:246` — *"The
10% test that decides the reduced rate…"* — was written to explain the statutory
distinction the tax reviewer had just ruled on, **and hardcoded the threshold in the same
breath.** The batch that made three server messages configuration-driven authored a fourth
message with a literal in it, one file over.

**Two further shapes it exposed:**
- **A rate in ARITHMETIC in the frontend** (`futureTaxableEstate * 0.40`) computing a
  *displayed liability* — the class a prose sweep is blind to, on the surface no sweep
  reached.
- **A `v-if` gating on a key NO payload carries.** That is the read-boundary axis one degree
  worse than a Resource dropping a field: **there the field existed and was not sent; here it
  never existed at all.** `grep` finds the key in one template and nowhere else.

**And check reachability before filing.** Four of the nine were in components mounted by
nothing. **A sweep that greps `resources/` and files everything over-reports by a third.**

**Axis 6 is swept for enum lists ONLY.** `StoreEnumRulesMatchColumnsTest` covers `in:`
rules — 17 rules, 1 fixed, 2 classified. **Store numeric bounds have NEVER been swept** —
that is axis 3 repeated one layer over, in the layer `/m` writes through — and they are
**already known to diverge**: `MortgageStore:306` bounds `interest_rate` but says nothing
about `fixed_interest_rate` or `variable_interest_rate`, and `InvestmentAccountStore` sets
no bound on `platform_fee_percent`, **so Fyn accepts a 12% platform fee that the web form
rejects with a 422.** Open as **W-0329**.

**Axis 6 is the one that hides.** `resources/mobile/api.js` has no post/put/patch helper
anywhere, so **Fyn is not one of `/m`'s write paths — it is the only one**, and it writes
through the Stores. The backend looks shared, and it is *at the endpoint*; it diverges one
layer down where the Stores carry their own rules. **Sweeping `app/Http/Requests/` says
nothing about how `/m` writes.**

7. **The Resource omits a field the template gates on** — the same disease at the **read**
   boundary rather than the write.

**Axis 7 is the mirror of the other six.** They all ask *"can what the user typed reach the
column?"* This one asks *"can what the column holds reach the user?"*

`MortgageResource` serialises `fixed_interest_rate` but **not** `fixed_rate_percentage`.
`PropertyDetailInline.vue:319` renders the fixed portion only
`v-if="mortgage.rate_type === 'mixed' && mortgage.fixed_rate_percentage"` — a field the
Resource never sends. The gate reads `undefined`, so **the row is structurally unreachable:
no data can satisfy it.** A user enters a 60% portion at 12%, it saves correctly, and the
detail view shows `Rate Type: Mixed` and no numbers at all. (Verified against both files.)

**Why no sweep finds it:** the rule is right, the column is right, the Store is right, the
write is right. Only the journey home is broken.

**The trap is sibling coupling.** `fixed_interest_rate` *is* serialised, so anyone checking
*"is the rate exposed?"* answers **yes** and stops. The row is hidden by a **different**
field, and nothing warns that a display depends on a sibling the Resource drops.
**When checking whether a value reaches the user, check every field its `v-if` names — not
the value itself.** Open as **W-0351**.

**The same axis, one degree worse: a value computed and READ BY NOTHING.** Second instance
2026-08-23 — `IHTCalculationService` computed `charitable_rate_test_amount`, applying a
**statutory distinction a tax reviewer had to rule on**, and never put it in the result
array. Zero consumers across `app`, `resources` and `tests`. So the card had one charitable
figure to render and two to explain, and the one that survived went out under *"Your will
leaves £20,000 to charity"* — false for both spouses, who each leave £10,000.

**The check:** `grep` for a key a service sets and **count its consumers. Zero means either
dead code or a distinction that never reaches the user — and the two look identical from
inside the service.**

**Why it is harder to see than the `MortgageResource` case:** there, a field simply was not
serialised. Here the engine does the *difficult* part correctly and drops the result before
any consumer can see it. **Service right, controller right, component right about what it
was given. Only the journey home is broken.** Both present as a *presentation* bug while the
cause is a *publication* one, and both are invisible to every sweep aimed at the write path.

**Third instance, same night, and the boundary is not always a Resource.** `IHTPlanning.vue`
built its view-model with a **hand-written mapping** that **enumerates fields rather than
spreading them** — so a newly published field was dropped one layer *below* the controller
and one layer *above* the template. Service right, controller right, component right.
**An allowlist nobody thought of as a boundary.**

**A value can be correct at every layer and still never arrive. Testing the ends does not
test the join.** Three instances in one night — a service computing a value nothing read, a
Resource omitting a field a `v-if` names, and a component mapping that enumerates fields
instead of spreading them. **The third was found inside the fix for the first two.**

**A field absent from a hand-written mapping is invisible in exactly the way a field absent
from a Resource is.** When you publish a value, follow it to the template — the join is a
layer, and it is usually the one with no tests.

**Use `?? null`, not `|| 0`, when the consumer distinguishes "nothing to show" from "zero".**
A zero-default collapses those two into one and the card cannot tell them apart.

**The tell:** a key present in the view-model that does **not** exist in the API response
means a mapping is being hand-built somewhere between them.

**Why the tests missed it — worth knowing before you trust a component suite.** The Vitest
cases injected the view-model **directly** via `setData`, supplying the object the mapping
was supposed to produce. **They proved the template and skipped the mapping entirely** —
seven green cases over a card that rendered wrong on the live page. The Feature test
asserted the endpoint publishes the field, which it did. **Neither test covered the join.**
That is the Fixture variant (`tests/CLAUDE.md` §4) sitting exactly on an integration seam.

Guards: `tests/Unit/Database/ValidationMaxFitsColumnPrecisionTest.php` and
`StoreEnumRulesMatchColumnsTest.php`.

## API Resources

Resources extend `JsonResource` and transform models for API output:
```php
public function toArray(Request $request): array {
    return [
        'id' => $this->id,
        'date' => $this->created_at?->toIso8601String(),
        'mortgages' => MortgageResource::collection($this->whenLoaded('mortgages')),
        'notice_days' => $this->when($this->access_type === 'notice', $this->notice_period_days),
    ];
}
```

Use `$this->whenLoaded()` for relationships and `$this->when()` for conditional fields.

## Route Structure

Three route files:

- `routes/api.php` — the web + `/m` API, prefixed `/api/`
- `routes/api_v1.php` — the **native iOS** surface, prefixed `/api/v1/`. Native auth/session lives here (`/native/auth/session/exchange|refresh`), behind `native.client` (`IdentifyNativeClient`), `native.version` (`EnforceNativeVersion`) and `native.session` (`EnsureActiveNativeSession`). **These routes do not exist on production** — see root `CLAUDE.md` → Mobile Clients.
- `routes/e2e.php` — browser-scenario support, non-production only

Pattern in `routes/api.php`:

```php
// Public: auth/register, auth/login, preview/personas
// Authenticated: all module routes wrapped in auth:sanctum
Route::middleware('auth:sanctum')->prefix('module')->group(function () {
    Route::get('/', [Controller::class, 'index']);
    Route::post('/', [Controller::class, 'store']);
    Route::put('/{id}', [Controller::class, 'update']);
    Route::delete('/{id}', [Controller::class, 'destroy']);
    Route::post('/analyze', [Controller::class, 'analyze']);
});
```

**Rate limiting:** `throttle:5,1` on auth endpoints, `throttle:api` on general endpoints, `throttle:export` (3/hour) on exports.

## Authentication

**Type:** Laravel Sanctum (token-based)

**Login flow:**
1. `POST /api/auth/login` (email, password)
2. If MFA enabled: returns `requires_mfa: true` + `mfa_token`
3. If no MFA: sends verification code via email, returns `requires_verification: true`
4. `POST /api/auth/verify-code` with code → returns Bearer token
5. All subsequent requests: `Authorization: Bearer {token}`

## Error Handling

**SanitizedErrorResponse trait:**
```php
$this->errorResponse($exception, 'Context', 500);
$this->notFoundResponse('Resource type');
$this->validationErrorResponse('Message', $errors);
```

In production, only generic messages returned. In debug mode, includes exception class, file, and line.

**Validation errors** return 422:
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": { "field": ["Error message"] }
}
```
