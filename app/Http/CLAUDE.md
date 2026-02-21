# HTTP Layer Conventions

This file supplements the root `CLAUDE.md` with HTTP-specific patterns.

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

All routes in `routes/api.php`, prefixed with `/api/`:

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
