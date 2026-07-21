# Campaign Landing Page System — Implementation Plan

**Owner:** CSJ
**Status:** Draft for review
**Last updated:** 2026-04-25

---

## 1. Goal

Run multiple ad campaigns (LinkedIn for now, others later) where each ad CTA points to a single, data-driven landing page that:

1. Shows campaign-specific copy and a scripted Fyn chat (no LLM, just canned responses).
2. Routes the visitor to register, carrying the campaign context with them.
3. Biases their Fyn-led onboarding so the first questions and recommendations match what they originally clicked on.
4. Logs attribution end-to-end so we can see, per campaign and per A/B variant, how many landed → engaged with Fyn → registered → completed onboarding.

One template. One admin row per campaign. No new pages, no redeploys to launch or update.

---

## 2. What already exists (reuse, don't rebuild)

These are confirmed in the current codebase and become the foundation:

- `resources/js/views/Public/CampaignPage.vue` — already serves `/savetax`, `/biggerpension`, `/paymortgage` and is registered with `meta: { public: true }` in `resources/js/router/index.js` (lines 25, 227–253). Currently slug-hardcoded; we'll data-drive it.
- `resources/js/components/Public/StaticFynChat.vue` — public, no-auth, read-only chat shell with suggested prompts and a "Register for free" CTA. The right starting point for our scripted Fyn variant.
- `resources/js/components/Fyn/FynQuickReplies.vue` — quick-reply bubble component, reusable as-is.
- `resources/js/components/Shared/AiMessageContent.vue` — message renderer, reusable as-is.
- `resources/js/layouts/PublicLayout.vue` — sticky public navbar; the new landing route renders inside this.
- `app/Services/Onboarding/OnboardingChatDirector.php` + `OnboardingStateMachine` — backend state machine that drives Fyn onboarding. 14 canonical states, backend-owned state.
- `app/Services/Onboarding/OnboardingPromptBuilder.php` — composes the asset-capture system prompt for each onboarding turn (method `buildAssetCapturePrompt(User $user, string $focus): string`). **This is the campaign-bias injection point.**
- `users.onboarding_fyn_context` — already exists, already cast to `array` (User.php line 129). We piggyback on this JSON blob for campaign context — no new column needed for the handoff.
- `app/Http/Controllers/Api/AuthController.php` — `register()` already accepts `registration_source` and writes to `PendingRegistration` (line 74). We extend this with campaign fields.
- `app/Http/Middleware/PreviewWriteInterceptor.php` — `EXCLUDED_ROUTES` array (lines 47–73) is where we whitelist the new tracking endpoint.

What does **not** exist and we have to build:

- Any concept of a `campaigns` row — currently every campaign is a hardcoded route entry pointing to a hardcoded view.
- Any A/B variant infrastructure.
- Any first-party attribution cookie or `landing_visits` table.
- Any `utm_*` columns on `users`.
- Any admin UI for campaigns (Insights CMS is the closest pattern to mirror).

---

## 3. Architecture overview

```
LinkedIn ad (UTMs in URL)
        │
        ▼
GET /c/{slug}?utm_source=linkedin&utm_campaign=...&v=A
        │
        ├─► CampaignLandingController@show  (public, no auth)
        │     │
        │     ├─ load Campaign by slug (cached)
        │     ├─ resolve A/B variant (cookie sticky, else hash bucket)
        │     ├─ call /api/track/landing (fire-and-forget) → logs landing_visit, sets fy_attribution cookie
        │     └─ render CampaignLandingView.vue with campaign + variant payload
        │
        ▼
User chats with CampaignFynChat (scripted, no LLM)
        │
        ▼
User clicks "Register for free" → /register?c={slug}&v={variant}
        │
        ├─► AuthController@register
        │     │
        │     ├─ read fy_attribution cookie + URL params
        │     ├─ persist on PendingRegistration: campaign_id, variant_id, landing_visit_id, utm_*
        │     └─ continue normal verify-code flow
        │
        ▼
verify-code → User row created → copy fields from PendingRegistration → users.acquisition_* + users.utm_*
                                                                       → users.onboarding_fyn_context['campaign'] = {...}
        │
        ▼
Onboarding starts → OnboardingPromptBuilder reads onboarding_fyn_context['campaign']
        │
        └─► biases asset_capture order, opening line, emphasis
```

Every box on the left is a database lookup, not a code branch. New campaigns are rows.

---

## 4. Database changes

### 4.1 New tables

**`campaigns`** (one row per campaign)

| column | type | notes |
|---|---|---|
| id | bigint pk | |
| slug | string, unique | URL slug, e.g. `tax-savings-2026` |
| name | string | internal label |
| status | enum | `draft`, `active`, `paused`, `archived` |
| theme | string | one of `tax_savings`, `retirement`, `protection`, `estate`, `savings`, `investment`, `goals` (drives onboarding bias) |
| headline | string | hero h1 |
| subheadline | string | hero h2 |
| hero_copy | text | longer paragraph below subheadline |
| cta_text | string | button label, e.g. "See how much you could save" |
| fyn_script_json | json | full chat script (see §5.4 schema) |
| onboarding_bias_json | json | `{ priority_steps: [...], opening_line: "...", emphasis: "..." }` |
| linkedin_insight_partner_id | string nullable | optional override per campaign |
| starts_at | timestamp nullable | optional schedule |
| ends_at | timestamp nullable | optional schedule |
| created_by | bigint fk users | |
| timestamps | | |

**`campaign_variants`** (A/B variants for a campaign)

| column | type | notes |
|---|---|---|
| id | bigint pk | |
| campaign_id | bigint fk | |
| key | string | `A`, `B`, `C`… |
| weight | unsigned int | 0–100, sums to 100 across active variants |
| is_active | bool | |
| overrides_json | json | partial overrides of the parent campaign fields (`headline`, `hero_copy`, `cta_text`, `fyn_script_json`) |
| timestamps | | |

A campaign always has at least one variant (`A`, weight 100). Adding `B` at weight 50 splits traffic 50/50.

**`landing_visits`**

| column | type | notes |
|---|---|---|
| id | bigint pk | |
| anonymous_id | uuid | from cookie, generated on first hit |
| campaign_id | bigint fk nullable | null if direct hit on `/c/unknown` |
| variant_id | bigint fk nullable | |
| utm_source | string nullable | |
| utm_medium | string nullable | |
| utm_campaign | string nullable | |
| utm_content | string nullable | |
| utm_term | string nullable | |
| referrer | string nullable | |
| user_agent | string nullable | |
| ip_hash | string nullable | sha256, for unique-visit dedup without storing IP |
| landed_at | timestamp | |
| engaged_with_fyn_at | timestamp nullable | first quick-reply click |
| clicked_register_at | timestamp nullable | |
| converted_user_id | bigint fk users nullable | set at registration |

### 4.2 Columns added to existing tables

**`pending_registrations`** (add):
- `acquisition_campaign_id` bigint fk nullable
- `acquisition_variant_id` bigint fk nullable
- `acquisition_landing_visit_id` bigint fk nullable
- `utm_source`, `utm_medium`, `utm_campaign`, `utm_content`, `utm_term` (all string nullable)

**`users`** (add the same set, copied from PendingRegistration when the User row is created at verify-code):
- `acquisition_campaign_id`, `acquisition_variant_id`, `acquisition_landing_visit_id`
- `utm_source`, `utm_medium`, `utm_campaign`, `utm_content`, `utm_term`
- `registration_source` already used informally — formalise as a string column if not present (check).

No new column for the Fyn handoff — we reuse `users.onboarding_fyn_context` (already JSON cast).

### 4.3 Migration files (create, in order)

1. `database/migrations/2026_05_xx_create_campaigns_table.php`
2. `database/migrations/2026_05_xx_create_campaign_variants_table.php`
3. `database/migrations/2026_05_xx_create_landing_visits_table.php`
4. `database/migrations/2026_05_xx_add_acquisition_fields_to_pending_registrations_table.php`
5. `database/migrations/2026_05_xx_add_acquisition_fields_to_users_table.php`

Plus a `database/seeders/CampaignSeeder.php` for the first 2–3 launch campaigns so dev/staging has something to render.

---

## 5. Backend (Laravel)

### 5.1 Models

- `app/Models/Campaign.php` — `hasMany(CampaignVariant)`, `hasMany(LandingVisit)`, scope `active()`.
- `app/Models/CampaignVariant.php` — `belongsTo(Campaign)`, helper `effectiveField($name)` that merges `overrides_json` over the parent campaign field.
- `app/Models/LandingVisit.php` — `belongsTo(Campaign)`, `belongsTo(CampaignVariant)`, `belongsTo(User, 'converted_user_id')`.

### 5.2 Controllers

**`app/Http/Controllers/Api/CampaignLandingController.php`** (public)

```php
public function show(string $slug): JsonResponse
{
    $campaign = Cache::remember("campaign:{$slug}", 300, fn() =>
        Campaign::with('variants')->where('slug', $slug)->active()->firstOrFail()
    );
    $variant = $this->variantSelector->resolveFor($campaign, request());
    return response()->json([
        'campaign' => CampaignResource::make($campaign->withVariantApplied($variant)),
        'variant'  => $variant->key,
    ]);
}
```

Renders no HTML — Vue handles the view, this just serves the JSON config. Routed at `GET /api/campaigns/{slug}` (public).

**`app/Http/Controllers/Api/CampaignTrackingController.php`** (public)

- `POST /api/track/landing` — logs a `LandingVisit` row, sets `fy_attribution` cookie.
- `POST /api/track/event` — accepts `{event: 'fyn_engaged'|'register_clicked', anonymous_id, campaign_slug, variant_key}` and updates timestamps on the visit row. Throttle `60,1`.

**`app/Http/Controllers/Admin/CampaignAdminController.php`** (admin-only)

- Standard CRUD: `index`, `store`, `update`, `destroy`, plus `duplicate` and `pause`.
- Variant CRUD nested: `POST /admin/campaigns/{id}/variants`, `PUT /admin/campaigns/{id}/variants/{vid}`.

### 5.3 Services

**`app/Services/Campaign/CampaignAttributionService.php`** — single source of truth for attribution.

```php
class CampaignAttributionService
{
    public function recordLandingVisit(Request $request, ?Campaign $campaign, ?CampaignVariant $variant): LandingVisit;
    public function readAttributionCookie(Request $request): ?array;
    public function writeAttributionCookie(Response $response, LandingVisit $visit): void;
    public function linkVisitToUser(LandingVisit $visit, User $user): void;
    public function attributionPayloadFor(Request $request): array; // for AuthController
}
```

Cookie name `fy_attribution`, JSON-serialised + signed (Laravel `Cookie::make()->withRaw(false)` does this), 90-day expiry, `httpOnly=false` (frontend reads it on register-click for telemetry), `sameSite=lax`.

**`app/Services/Campaign/VariantSelector.php`** — deterministic A/B assignment.

```php
public function resolveFor(Campaign $c, Request $r): CampaignVariant
{
    // 1. Honour ?v= query param if present and valid (for marketing previews)
    // 2. Else read sticky variant from fy_attribution cookie
    // 3. Else hash bucket: bucket = crc32(anonymousId) % 100
    //    walk active variants in order, sum weights, pick first where sum > bucket
}
```

**`app/Services/Campaign/CampaignOnboardingHandoff.php`** — turns a Campaign+Variant into the JSON blob written to `users.onboarding_fyn_context`.

```php
public function buildContext(Campaign $c, CampaignVariant $v): array
{
    return [
        'campaign_slug' => $c->slug,
        'campaign_theme' => $c->theme,
        'variant_key' => $v->key,
        'priority_steps' => $c->onboarding_bias_json['priority_steps'] ?? [],
        'opening_line' => $c->onboarding_bias_json['opening_line'] ?? null,
        'emphasis' => $c->onboarding_bias_json['emphasis'] ?? null,
    ];
}
```

### 5.4 Fyn script schema (stored in `campaigns.fyn_script_json`)

```json
{
  "opening": {
    "messages": [
      "Hi, I'm Fyn.",
      "I help people work out how much tax they could be saving each year — sometimes thousands."
    ],
    "quick_replies": [
      { "id": "isa", "label": "I want to use my ISA allowance better" },
      { "id": "pension", "label": "I'm not sure I'm getting full pension tax relief" },
      { "id": "marriage", "label": "Can I claim Marriage Allowance?" },
      { "id": "general", "label": "Show me everything I'm missing" }
    ]
  },
  "responses": {
    "isa": {
      "messages": [
        "The ISA allowance for 2025/26 is £20,000 per adult, and any unused allowance doesn't carry over.",
        "I can show you how much of yours is unused once we know a bit about your savings."
      ],
      "quick_replies": [
        { "id": "register_now", "label": "Get my personalised view", "action": "register" }
      ]
    },
    "pension": { "...": "..." },
    "register_now": {
      "action": "register",
      "messages": ["Create a free account in under a minute and I'll walk you through it personally."]
    }
  },
  "register_cta": {
    "headline": "Want this personalised?",
    "body": "Free, takes 2 minutes, no card.",
    "button": "Create my free account"
  }
}
```

The frontend chat is a pure state machine over this JSON — no API calls, no LLM cost, no auth.

### 5.5 Routes

```php
// routes/web.php — SPA still serves /c/{slug} via the catch-all, no change needed.

// routes/api.php
Route::get ('/campaigns/{slug}',     [CampaignLandingController::class, 'show']);            // public
Route::post('/track/landing',        [CampaignTrackingController::class, 'landing'])
    ->middleware('throttle:30,1');                                                            // public
Route::post('/track/event',          [CampaignTrackingController::class, 'event'])
    ->middleware('throttle:60,1');                                                            // public

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::apiResource('campaigns', CampaignAdminController::class);
    Route::post   ('campaigns/{campaign}/duplicate',   [CampaignAdminController::class, 'duplicate']);
    Route::patch  ('campaigns/{campaign}/pause',       [CampaignAdminController::class, 'pause']);
    Route::apiResource('campaigns.variants', CampaignVariantAdminController::class);
});
```

### 5.6 PreviewWriteInterceptor

Add to `EXCLUDED_ROUTES` in `app/Http/Middleware/PreviewWriteInterceptor.php`:

```php
'api/track/landing',
'api/track/event',
'api/campaigns',          // public read-only via slug
```

Tracking from preview personas should still write to `landing_visits` so the funnel telemetry is consistent — these rows are anonymous and not user data, so the rule's intent (don't let preview users mutate real data) isn't violated.

### 5.7 Onboarding handoff hook

`app/Services/Onboarding/OnboardingPromptBuilder.php` is where the system prompt is built per asset-capture turn. The current shape is:

```php
public function buildAssetCapturePrompt(User $user, string $focus): string
{
    $layers = [
        CoreIdentity::get($firstName),
        ComplianceRules::get($taxYear),
        $this->assetCaptureInstructions($focus),
    ];
    return implode("\n\n", $layers);
}
```

Hook in by appending a fourth layer when campaign context is present:

```php
public function buildAssetCapturePrompt(User $user, string $focus): string
{
    // ...existing layers...
    $layers = [
        CoreIdentity::get($firstName),
        ComplianceRules::get($taxYear),
        $this->assetCaptureInstructions($focus),
    ];

    $campaign = data_get($user->onboarding_fyn_context, 'campaign');
    if ($campaign) {
        $layers[] = $this->campaignBiasLayer($campaign);
    }

    return implode("\n\n", $layers);
}
```

`campaignBiasLayer(array $campaign): string` returns a short instruction block, e.g.:

> "<campaign_context>The user came in via the **{theme}** campaign ('{campaign_slug}'). Bias your follow-up questions and worked examples towards {emphasis}. If `priority_steps` is set, surface those questions first within the current asset_capture turn.</campaign_context>"

Two further small touches:
1. **Auto-select focus** — `OnboardingChatDirector` should, on the first turn after registration, check `onboarding_fyn_context['campaign']['campaign_theme']` and prefill `users.onboarding_fyn_selection` so the user lands directly in the relevant module rather than being asked to pick.
2. **Opening line** — the director's first emitted message can be the campaign's `opening_line` if present, replacing the default greeting.

Both are 5–10 line touches in `OnboardingChatDirector.php`. No state-machine rewrite, no new states.

### 5.8 AuthController extension

In `register()` (around line 68 where `PendingRegistration::createOrUpdate` is called):

```php
$attribution = app(CampaignAttributionService::class)->attributionPayloadFor($request);

$pending = PendingRegistration::createOrUpdate([
    // ...existing fields...
    'registration_source'           => $request->registration_source ?? 'campaign_landing',
    'acquisition_campaign_id'       => $attribution['campaign_id'] ?? null,
    'acquisition_variant_id'        => $attribution['variant_id'] ?? null,
    'acquisition_landing_visit_id'  => $attribution['landing_visit_id'] ?? null,
    'utm_source'                    => $attribution['utm_source'] ?? null,
    // ...etc
]);
```

In `verifyCode()` (where the User row is created from PendingRegistration, ~line 425), copy the same fields onto the User and set `onboarding_fyn_context['campaign']` via `CampaignOnboardingHandoff::buildContext()`.

---

## 6. Frontend (Vue)

### 6.1 Routes

In `resources/js/router/index.js` add **one** new route (the existing hardcoded ones can stay during migration):

```js
const CampaignLandingView = () => import('@/views/Public/CampaignLandingView.vue');
// ...
{
  path: '/c/:slug',
  name: 'CampaignLanding',
  component: CampaignLandingView,
  meta: { public: true },
}
```

### 6.2 Components

- `resources/js/views/Public/CampaignLandingView.vue` — page wrapper. On mount: read `:slug` and `?v=`, fetch `/api/campaigns/{slug}`, fire `/api/track/landing`, render hero + `<CampaignFynChat>`.
- `resources/js/components/Public/CampaignFynChat.vue` — copy of `StaticFynChat.vue` extended with a state machine that walks `fyn_script_json`. No streaming, no API calls. Reuses `<FynQuickReplies>` and `<AiMessageContent>`. Plain-text only per CLAUDE.md §14.
- `resources/js/components/Public/CampaignHero.vue` — small presentational component for the headline / subheadline / hero_copy / CTA. Receives variant-applied campaign as a prop.

### 6.3 Service

`resources/js/services/campaignService.js`:
```js
export default {
  async getCampaign(slug, variantOverride) { /* GET /api/campaigns/:slug?v=... */ },
  async trackLanding(payload)              { /* POST /api/track/landing */ },
  async trackEvent(event, payload)         { /* POST /api/track/event */ },
};
```

### 6.4 Store

No new Vuex module needed. Campaign data is page-local; attribution lives in cookie + backend.

### 6.5 Register page integration

`resources/js/views/Auth/RegisterView.vue` — on submit, read `fy_attribution` cookie, include in the registration payload (already-existing `registration_source` field becomes `'campaign_landing:{slug}'`). The cookie is the source of truth; URL params are a fallback if the cookie was blocked.

---

## 7. Attribution flow (sequence)

```
1. Ad click → /c/tax-savings?utm_source=linkedin&utm_campaign=tax-savings-q2&utm_content=variant-a
2. Vue mounts, fetches /api/campaigns/tax-savings → returns campaign + assigned variant
3. Vue fires POST /api/track/landing with { slug, variant, utm_*, referrer, anonymous_id (from cookie or new uuid) }
4. Backend writes landing_visits row, sets-cookie fy_attribution = {anonymous_id, campaign_id, variant_id, landing_visit_id, first_seen_at}
5. User chats with scripted Fyn → first quick-reply click → POST /api/track/event {event:'fyn_engaged', anonymous_id} → updates engaged_with_fyn_at
6. User clicks "Register" → /register?c=tax-savings&v=A; tracking event {event:'register_clicked'} fires
7. Register form submit → /api/auth/register includes attribution payload from cookie → PendingRegistration captures it
8. verify-code → User row created with full attribution + onboarding_fyn_context['campaign']; landing_visits.converted_user_id linked
9. Onboarding starts → OnboardingPromptBuilder reads campaign context → biased Fyn flow
10. Onboarding completes → users.onboarding_completed_at = now() → funnel row complete
```

LinkedIn Insight Tag fires at step 2 (page load) and step 7 (registration thank-you) for LinkedIn-side conversion reporting. Independent of our own attribution.

---

## 8. A/B testing

Per §5.3 `VariantSelector`, assignment is deterministic and sticky:

1. `?v=` query param wins (lets marketing share preview links).
2. Else cookie's `variant_id` wins (user revisits get the same variant).
3. Else hash-bucket on `anonymous_id` against active-variant weights.

**To launch a test:** add a `B` variant row with weight 50, drop `A`'s weight to 50. Save.
**To roll out a winner:** set winner's weight to 100, set loser to `is_active=false`. Save.
**To preview a draft:** create variant with `is_active=false`, share `/c/slug?v=B`.

Conversion rates per variant are a join from `landing_visits` → `users` (via `converted_user_id`) grouped by `variant_id`.

---

## 9. Admin UI

Mirror the existing Insights CMS pattern. Routes under `/admin/campaigns`:

- List view: table of campaigns with status, theme, traffic last 7 days, conversion rate, edit/duplicate/pause buttons.
- Edit view: form with all `campaigns` fields, plus a JSON editor (with schema validation) for `fyn_script_json` and `onboarding_bias_json`. Live preview pane that renders `<CampaignLandingView>` with the in-progress data.
- Variants tab: add/edit/weight-slider per variant, each with its own override editor.
- Analytics tab: per-variant funnel — landed → engaged → register_clicked → registered → onboarded.

Build this last (Phase 4) — until then, manage via tinker / seeder / direct SQL. Seeder + tinker is fine for the first 2–3 campaigns.

---

## 10. Privacy / GDPR

- `fy_attribution` cookie is first-party and reasonably "strictly necessary" for the marketing-analytics use, but be safe: gate `/api/track/landing` and `/api/track/event` behind cookie consent. If consent is declined, skip the cookie write and use sessionStorage for the duration of the visit only — visit row is still logged but with `anonymous_id = null` and no follow-on linkage.
- `landing_visits.ip_hash` is sha256(ip + per-env salt), not raw IP. Stored for unique-visit dedup only.
- LinkedIn Insight Tag also goes behind consent.
- Add a one-liner to the Privacy Policy: "We use first-party cookies to remember which advert brought you to us, so the product can be tailored to what you originally clicked on."

---

## 11. Testing

### 11.1 Pest tests

- `tests/Unit/Services/Campaign/VariantSelectorTest.php` — sticky cookie wins, hash bucketing distributes per weights, `?v=` override.
- `tests/Unit/Services/Campaign/CampaignAttributionServiceTest.php` — cookie read/write, visit logging, link-to-user.
- `tests/Unit/Services/Campaign/CampaignOnboardingHandoffTest.php` — campaign + variant → onboarding context shape.
- `tests/Feature/CampaignLandingTest.php` — `GET /api/campaigns/{slug}` returns active campaign, 404s on archived, applies variant overrides.
- `tests/Feature/CampaignTrackingTest.php` — `POST /api/track/landing` writes row + cookie; preview-user request still writes (per PreviewWriteInterceptor exclusion).
- `tests/Feature/RegisterWithCampaignTest.php` — register with attribution cookie copies fields onto PendingRegistration → User → onboarding_fyn_context.

### 11.2 Browser tests (per CLAUDE.md non-negotiable rules)

End-to-end Playwright run on local dev:
1. Hit `/c/tax-savings?utm_source=linkedin&utm_campaign=test`.
2. Verify hero copy, click a quick reply, verify scripted response.
3. Click register, fill form, submit, fetch verify code from DB, enter code.
4. Verify dashboard shows; verify `users.acquisition_campaign_id`, `users.onboarding_fyn_context['campaign']`, `landing_visits.converted_user_id` are all set in the DB.
5. Verify Fyn's onboarding opening line is the campaign's `opening_line`.

---

## 12. Phased rollout

**Phase 1 — Foundation (no user-visible change):**
Migrations, models, `CampaignAttributionService`, `VariantSelector`, `/api/track/*` endpoints, PreviewWriteInterceptor exclusion, seeder with one campaign. Pest unit tests.

**Phase 2 — Public landing page:**
`/c/{slug}` route, `CampaignLandingView.vue`, `CampaignFynChat.vue`, `CampaignHero.vue`, `/api/campaigns/{slug}` endpoint. Reuse `<FynQuickReplies>` and `<AiMessageContent>`. Browser test the scripted chat.

**Phase 3 — Registration handoff:**
Extend `AuthController::register` and `verifyCode` to read attribution and write `users.acquisition_*` + `users.onboarding_fyn_context['campaign']`. `OnboardingPromptBuilder::applyCampaignBias`. Browser test end-to-end on `csjones.co/fynla` with a real LinkedIn-style URL.

**Phase 4 — Admin UI:**
List/create/edit/duplicate/pause + variants + analytics tab.

**Phase 5 — Migration of existing campaign pages:**
Move `/savetax`, `/biggerpension`, `/paymortgage` to rows in `campaigns`. Add 301 redirects. Delete the hardcoded route entries and `CampaignPage.vue` (or keep as legacy until clear).

**Phase 6 — LinkedIn Insight Tag + cookie consent integration.**

Each phase ships independently to `csjones.co/fynla` first, soak-tests, then PRs to main.

---

## 13. Open questions for CSJ

1. **Migrate existing campaign pages or keep parallel?** Recommend migrate (Phase 5) so there's one system, but the existing pages have SEO; we'd need redirects from `/savetax` → `/c/save-tax` (or keep the old slugs and just point them at the new dynamic system).
2. **A/B variant scope.** Should a variant be able to swap the entire Fyn script (full-flow test) or only the surface copy (`headline`, `cta_text`)? Recommend: support both via `overrides_json`, enforce nothing.
3. **Conversion attribution window.** 90-day cookie? 30-day? LinkedIn's default is 30 days. Recommend 90 to match longer B2B finance-decision cycles.
4. **Where in admin UI?** New top-level section, or under an "Acquisition" group alongside Insights and Awin? Recommend: new top-level "Campaigns".
5. **Multi-campaign onboarding bias.** If a user lands on campaign A, leaves, then lands on campaign B and registers — which bias wins? Recommend: last-touch (B), but log both on the User for analytics.
6. **Fyn script i18n.** GB-only for now? Recommend yes — defer until there's a non-UK market.
7. **Awin coordination.** The Awin integration (deployed 15 April) tracks subscription conversions. Should our `landing_visits.utm_source = 'awin'` rows be cross-referenced? Probably yes, but it's a Phase 4+ analytics concern, not a Phase 1 blocker.

---

## 14. File-level checklist

New files:
- `database/migrations/2026_05_xx_create_campaigns_table.php`
- `database/migrations/2026_05_xx_create_campaign_variants_table.php`
- `database/migrations/2026_05_xx_create_landing_visits_table.php`
- `database/migrations/2026_05_xx_add_acquisition_fields_to_pending_registrations_table.php`
- `database/migrations/2026_05_xx_add_acquisition_fields_to_users_table.php`
- `database/seeders/CampaignSeeder.php`
- `app/Models/Campaign.php`
- `app/Models/CampaignVariant.php`
- `app/Models/LandingVisit.php`
- `app/Http/Controllers/Api/CampaignLandingController.php`
- `app/Http/Controllers/Api/CampaignTrackingController.php`
- `app/Http/Controllers/Admin/CampaignAdminController.php`
- `app/Http/Controllers/Admin/CampaignVariantAdminController.php`
- `app/Http/Resources/CampaignResource.php`
- `app/Http/Requests/StoreCampaignRequest.php`, `UpdateCampaignRequest.php`
- `app/Services/Campaign/CampaignAttributionService.php`
- `app/Services/Campaign/VariantSelector.php`
- `app/Services/Campaign/CampaignOnboardingHandoff.php`
- `resources/js/views/Public/CampaignLandingView.vue`
- `resources/js/components/Public/CampaignFynChat.vue`
- `resources/js/components/Public/CampaignHero.vue`
- `resources/js/services/campaignService.js`
- `tests/Unit/Services/Campaign/*Test.php`
- `tests/Feature/CampaignLandingTest.php`, `CampaignTrackingTest.php`, `RegisterWithCampaignTest.php`

Edited files:
- `routes/api.php` — add public + admin routes per §5.5
- `resources/js/router/index.js` — add `/c/:slug` route
- `app/Http/Middleware/PreviewWriteInterceptor.php` — extend `EXCLUDED_ROUTES`
- `app/Http/Controllers/Api/AuthController.php` — extend `register` and `verifyCode` to copy attribution
- `app/Models/User.php` — add fillable fields for new columns
- `app/Models/PendingRegistration.php` — same
- `app/Services/Onboarding/OnboardingPromptBuilder.php` — append `campaignBiasLayer` to `buildAssetCapturePrompt`
- `app/Services/Onboarding/OnboardingChatDirector.php` — auto-select focus + opening-line override from campaign context
- `resources/js/views/Auth/RegisterView.vue` — read cookie, include in payload

---

## 15. Estimated scope

Rough sizing for an experienced dev (you):

| Phase | Effort |
|---|---|
| 1. Foundation (DB + services + tracking) | 1.5–2 days |
| 2. Public landing page + scripted chat | 1.5 days |
| 3. Registration handoff + onboarding bias | 1 day |
| 4. Admin UI | 2–3 days |
| 5. Migrate existing pages | 0.5 day |
| 6. LinkedIn tag + consent | 0.5 day |

Total: ~7–8 days for the full system, ~3–4 days to first usable end-to-end on dev.

---

**Recommend starting with Phase 1 + 2 + 3 as a single PR to `dev`** — that gets you a working campaign system with manual seeder management. Phases 4–6 are pure additions on top.
