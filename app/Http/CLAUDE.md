# HTTP Layer Conventions

Supplements the root `CLAUDE.md`. **Before writing or reviewing validation rules, Resources or view-model mappings, load the `data-integrity-traps` skill** — it carries the seven axes of rule-vs-column drift and the read-boundary failures that cost a cycle.

## Response Format

```json
{ "success": true, "message": "Human-readable message", "data": { } }
```

| Code | Usage |
|---|---|
| 200 / 201 | Success / resource created |
| 401 / 403 | Unauthenticated / forbidden or MFA required |
| 404 | Not found **or access denied** |
| 422 | Validation failed |
| 423 | Account locked (login) |

## Controllers

- Inject Agents and Services via constructor, `private readonly`.
- Standard CRUD `index/store/show/update/destroy`; domain methods `analyze()`, `scenarios()`, `recommendations()`, `calculate*()`.
- Transform models through Resource classes before returning.
- **Never use the `DB` facade directly** — go through services and models. Enforced by an arch test.
- Errors via `SanitizedErrorResponse` or `HandleApiExceptions`: `$this->errorResponse($e, 'Context', 500)`, `$this->notFoundResponse('Resource type')`, `$this->validationErrorResponse('Message', $errors)`. Production returns generic messages only.

## Middleware

Applied to all `/api/*`: Sanctum stateful auth → ThrottleRequests → SubstituteBindings → **SanitizeInput** (strips HTML, trims; exempts password fields) → **PreviewWriteInterceptor**.

Route-level: `auth:sanctum`, `mfa.verified`, `admin`, `role:{name}`, `throttle:5,1`.

**PreviewWriteInterceptor** blocks POST/PUT/PATCH/DELETE from preview users and returns fake success. Excluded: auth routes, preview exit/switch, onboarding, document upload, webhooks. Calculation endpoints pass through by pattern (`/calculate`, `/projections`, `/recalculate`, `/analyze`). **Adding a new auth-related POST route means adding it to `EXCLUDED_ROUTES`** (Rule 7).

## Request Validation

Form Requests extend `FormRequest` with `authorize()`, `rules()`, `messages()`.

```php
'ownership_type' => ['nullable', Rule::in(['individual', 'joint', 'tenants_in_common', 'trust'])],
'current_value'  => 'required|numeric|min:0|max:999999999.99',
'joint_owner_id' => ['nullable', 'exists:users,id'],
```

Use `ValidationLimits::currencyRules()` and `percentageRules()`.

**`app/Services/Stores/` validate separately from `app/Http/Requests/`** — and the Stores are how Fyn (and therefore `/m`) writes, because `resources/mobile/api.js` has no post/put/patch helper at all. **Sweeping `app/Http/Requests/` tells you nothing about how `/m` writes.** See the `data-integrity-traps` skill.

## API Resources

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

`whenLoaded()` for relationships, `when()` for conditional fields. **A field a template's `v-if` names must be serialised** — check every field the gate reads, not just the value being displayed.

## Routes

- `routes/api.php` — the web + `/m` API, prefixed `/api/`.
- `routes/api_v1.php` — the **native iOS** surface, prefixed `/api/v1/`. Native auth/session (`/native/auth/session/exchange|refresh`) sits behind `native.client`, `native.version` and `native.session`. **These routes do not exist on production.**
- `routes/e2e.php` — browser-scenario support, non-production only.

Rate limiting: `throttle:5,1` on auth, `throttle:api` general, `throttle:export` (3/hour) on exports. **Named limiters for inline paths** — an unnamed throttle shares one per-IP bucket.

## Authentication

Laravel Sanctum, token-based:

1. `POST /api/auth/login` (email, password)
2. MFA enabled → `requires_mfa: true` + `mfa_token`; otherwise a code is emailed and `requires_verification: true`
3. `POST /api/auth/verify-code` → Bearer token
4. Subsequent requests: `Authorization: Bearer {token}`
