# Lifecycle Email Engine — Design

**Date:** 14 April 2026
**Author:** Brainstormed with Claude, session 51
**Status:** Design approved — pending writing-plans phase
**Related work:** Builds on the trial reminder fix from `April/April14Updates/trialReminderInvestigation.md` (depends on the system cron firing — verification pending session 52)

---

## TL;DR

Build a **single user lifecycle email engine** that ships five distinct campaigns:

1. **Empty trialer** — re-engagement (trial restart, 14 days)
2. **Engaged trialer** — discount conversion (per-user-locked welcome codes, hybrid magic link)
3. **Cancelled trialer** — feedback collection (3 days post-cancel)
4. **Churned subscriber** — feedback collection (3 days post-cancel)
5. **Lapsed subscriber** — payment recovery (5 days into `past_due`)

The engine is one shared `LifecycleEngine` service with five pluggable `LifecycleCampaign` classes. All five campaigns share dedup (`lifecycle_email_log`), magic link infrastructure, snapshot personalisation, and failure isolation. Adding a sixth campaign in future = create one class + register it in config. No engine code change.

---

## 1. The five campaigns

| # | Campaign name (slug) | Eligibility | Trigger | Goal | Carrot | Priority |
|---|---|---|---|---|---|---:|
| 1 | `empty_trialer` | Registered ≥9 days ago, trial expired, no active subscription, **zero data** in any module table | Day 9 from sign-up | Re-engagement | 14-day fresh restart trial (Pro-level access) | 4 |
| 2 | `engaged_trialer` | Registered ≥9 days ago, trial expired, no active subscription, **has data** in at least one module | Day 9 from sign-up | Convert | Personalised summary + per-user-unique discount code (Student/Standard/Family discounted, Pro at full price) | 5 |
| 3 | `cancelled_trialer` | Cancelled mid-trial 3 days ago (`cancelled_at < trial_ends_at`) | 3 days post-cancellation | Feedback collection | 7 quick-pick reasons + optional text | 1 |
| 4 | `churned_subscriber` | Cancelled paid subscription 3 days ago (`cancelled_at >= trial_ends_at`) | 3 days post-cancellation | Feedback collection | Same 7 quick-pick reasons + optional text | 2 |
| 5 | `lapsed_subscriber` | Subscription `status='past_due'` for ≥5 days, still `past_due` at send time | 5 days into past-due window | Payment recovery | Update payment method magic link + 3 quick-pick buttons (`will_fix`, `wants_to_cancel`, `needs_help`) | 3 |

**Priority ordering:** Lower number wins on same-day collision. Cancellation feedback wins over conversion attempts (a user who cancelled today should not also be asked to convert today).

**Lifetime independence:** Each campaign can be received at most once per user, ever. But a user can receive different campaigns over their lifetime (e.g., engaged trialer in March → converts → churns at month 6 → eligible for churned subscriber).

**Pro plan handling:** Trial = Pro-level access for everyone. Plan selection happens at conversion, not at trial start. So Campaign 2 sends to all engaged trialers (not filtered by plan), shows the discount table for Student/Standard/Family with Pro at full price, and lets the user pick their plan at checkout.

---

## 2. Architecture

```
┌─────────────────────────────────────────────────────────┐
│  lifecycle:run-daily  (artisan command, daily at 09:00) │
└────────────────────────┬────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────┐
│  LifecycleEngine                                        │
│  - fetches shared candidates once                       │
│  - iterates each registered campaign in priority order  │
│  - applies cross-campaign collision rules               │
│  - dispatches one email per eligible user               │
│  - records dedup row after successful send              │
│  - per-campaign + per-user failure isolation            │
└────────────────────────┬────────────────────────────────┘
                         │
        ┌────────────────┼─────────────────┐
        ▼                ▼                 ▼
   Campaign 1       Campaign 2        Campaigns 3/4/5
   (Empty)          (Engaged)         (Cancellation/Lapsed)
        │                │                 │
        ▼                ▼                 ▼
    Mail::send       Mail::send         Mail::send
    + dedup row      + dedup row        + dedup row
```

### Key principles

- **The engine doesn't know about specific campaigns.** It iterates a registered list of `LifecycleCampaign` objects. Each implements `name()`, `priority()`, `eligibleUsers()`, `mailable($user)`. Adding a 6th campaign = create one new class + register it. No engine changes.
- **Eligibility is a query, not a flag.** Each campaign's `eligibleUsers()` returns a query/collection that filters live DB state at the moment of the run. We do not stamp `eligible_for_X = true` on the user model.
- **Dedup is one table for all campaigns.** `lifecycle_email_log` with `(user_id, campaign)` unique constraint.
- **Collision resolution lives in the engine, not in campaigns.** Each campaign declares its priority. The engine builds the eligible set per campaign in priority order, removing any user it has already decided to email today.
- **All five campaigns share three sub-systems**: magic link routes, the personalisation snapshot service, and the mail dispatcher.
- **Failure isolation:** Three layers (campaign-level catch, per-user catch, idempotent retries via dedup).

---

## 3. Database schema

### New table: `lifecycle_email_log`

```sql
CREATE TABLE lifecycle_email_log (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    campaign VARCHAR(50) NOT NULL,         -- e.g. 'engaged_trialer'
    sent_at TIMESTAMP NOT NULL,
    clicked_at TIMESTAMP NULL,             -- first click (idempotent)
    action_taken VARCHAR(50) NULL,         -- e.g. 'applied_discount', 'feedback:too_expensive'
    context JSON NULL,                     -- snapshot at send time
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_campaign (user_id, campaign),
    KEY idx_campaign_sent_at (campaign, sent_at)
);
```

### New table: `feedback_responses`

```sql
CREATE TABLE feedback_responses (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    campaign VARCHAR(50) NOT NULL,
    reason_code VARCHAR(50) NOT NULL,
    free_text TEXT NULL,
    clicked_at TIMESTAMP NOT NULL,         -- when the quick-pick button was clicked
    text_submitted_at TIMESTAMP NULL,      -- when (if) the optional text was submitted
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY idx_user_campaign (user_id, campaign),
    KEY idx_reason_code (reason_code),
    KEY idx_campaign_clicked_at (campaign, clicked_at)
);
```

**Important:** A row is created the moment they click the quick-pick button (`clicked_at` filled, `free_text` and `text_submitted_at` both null). The optional text field, if used, is an UPDATE on the existing row. We never lose the click data even if they close the page without typing.

### Schema additions to existing `discount_codes` table

```sql
ALTER TABLE discount_codes
    ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER created_by,
    ADD COLUMN metadata JSON NULL AFTER applicable_cycles,
    ADD CONSTRAINT discount_codes_user_id_foreign
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
```

- `user_id` (nullable): when set, the code is **locked to that user**. `DiscountCodeService::validate()` checks this and rejects any other user. Existing shared codes (`LAUNCH20`, `FYNLA10`, `TRYME`) leave this NULL and behave as before — fully backwards compatible.
- `metadata` JSON (nullable): for type-specific extra data. For the new `lifecycle_welcome` type it stores per-plan-per-cycle discount amounts.

### New discount code type: `lifecycle_welcome`

The `type` column on `discount_codes` is a varchar (verified) so adding a new type does NOT require an enum migration. `DiscountCodeService::calculateDiscount()` gets one new arm:

```php
'lifecycle_welcome' => $this->calculateLifecycleWelcomeDiscount($amountPence, $planSlug, $billingCycle),
```

The new method reads `metadata->plan_amounts->{plan}.{cycle}` (an integer in pence) and returns it. Falls back to 0 if the plan/cycle combination is not in the metadata (e.g., user picked Pro — no discount, clean degradation).

### Schema addition to existing `users` table

```sql
ALTER TABLE users
    ADD COLUMN is_lifecycle_test_user BOOLEAN NOT NULL DEFAULT FALSE,
    ADD INDEX idx_lifecycle_test_user (is_lifecycle_test_user);
```

Used only by the e2e test seeder and cleanup command. Real users always have `false`. Negligible storage.

### Schema additions to existing `notification_preferences` table

Per Q1 in §13, each lifecycle campaign gets its own opt-out toggle so users have line-by-line control in the settings menu.

```sql
ALTER TABLE notification_preferences
    ADD COLUMN lifecycle_empty_trialer       BOOLEAN NOT NULL DEFAULT TRUE,
    ADD COLUMN lifecycle_engaged_trialer     BOOLEAN NOT NULL DEFAULT TRUE,
    ADD COLUMN lifecycle_cancelled_trialer   BOOLEAN NOT NULL DEFAULT TRUE,
    ADD COLUMN lifecycle_churned_subscriber  BOOLEAN NOT NULL DEFAULT TRUE,
    ADD COLUMN lifecycle_lapsed_subscriber   BOOLEAN NOT NULL DEFAULT TRUE;
```

**5 independent toggles, no master switch.** Each defaults to `TRUE` (opted-in) and can be flipped independently.

#### Existing user handling — explicit

This must work for the three user populations on the day the migration runs:

| User population | Mechanism | Result |
|---|---|---|
| **A. Users WITH an existing `notification_preferences` row** (most users) | `ALTER TABLE ... ADD COLUMN ... DEFAULT TRUE` — MySQL backfills the new columns to `TRUE` for every existing row as part of the DDL. No separate `UPDATE` statement needed. | All 14 preferences (9 existing + 5 new) immediately visible, all on. |
| **B. Users with NO `notification_preferences` row yet** (lazy-created on first access) | `NotificationPreference::getOrCreateForUser($userId)` defaults block is updated to include the 5 new fields, all `true`. Row gets created the first time the user hits the settings page or the engine queries them. | Row materialised with all 14 fields on. |
| **C. New users registering after the migration** | Same as B — first call to `getOrCreateForUser` creates a row with all 14 defaults. | Row created with all 14 fields on. |

**The settings menu UI must surface all 14 toggles for every user, regardless of which population they're in.** This is a hard requirement, not a nice-to-have.

**Discovery during writing-plans phase (Option B chosen):** The existing notification preferences UI **only exists in the mobile app** at `resources/js/mobile/views/NotificationSettings.vue` and uses the API at `/v1/mobile/notifications/preferences`. There is no web equivalent — web users currently cannot manage notification preferences at all.

This means we need:

1. **Mobile** — augment the existing `NotificationSettings.vue` to add the 5 lifecycle toggles (small change, ~30 lines).
2. **Web (NEW)** — build a new web page `NotificationPreferences.vue` under `resources/js/components/UserProfile/` showing all 14 toggles (9 existing + 5 lifecycle), exposed as a tab in the user profile settings.
3. **API (NEW web endpoint)** — the existing `App\Http\Controllers\Api\V1\Mobile\NotificationPreferenceController` is namespaced under `/v1/mobile/`. Create a new controller `App\Http\Controllers\Api\NotificationPreferenceController` (without the Mobile namespace) that exposes identical endpoints under `/api/notifications/preferences`. Both controllers call the same `NotificationPreference::getOrCreateForUser()` and `update()` patterns. No refactor of the existing mobile controller — it stays as-is to preserve mobile compatibility.

Resolves the original §10.5 open question and closes off the unsubscribe footer URL (§7).

#### Updates to `NotificationPreference.php` model

Two changes:

```php
// Add to $fillable
protected $fillable = [
    // ... existing 9 fields ...
    'lifecycle_empty_trialer',
    'lifecycle_engaged_trialer',
    'lifecycle_cancelled_trialer',
    'lifecycle_churned_subscriber',
    'lifecycle_lapsed_subscriber',
];

// Add to $casts
protected $casts = [
    // ... existing 9 casts ...
    'lifecycle_empty_trialer' => 'boolean',
    'lifecycle_engaged_trialer' => 'boolean',
    'lifecycle_cancelled_trialer' => 'boolean',
    'lifecycle_churned_subscriber' => 'boolean',
    'lifecycle_lapsed_subscriber' => 'boolean',
];

// Update getOrCreateForUser() defaults block
public static function getOrCreateForUser(int $userId): self
{
    return self::firstOrCreate(
        ['user_id' => $userId],
        [
            // ... existing 9 defaults ...
            'lifecycle_empty_trialer' => true,
            'lifecycle_engaged_trialer' => true,
            'lifecycle_cancelled_trialer' => true,
            'lifecycle_churned_subscriber' => true,
            'lifecycle_lapsed_subscriber' => true,
        ]
    );
}
```

### Indexes on `subscriptions` table

```sql
CREATE INDEX idx_subs_status_trial      ON subscriptions (status, trial_ends_at);
CREATE INDEX idx_subs_status_period     ON subscriptions (status, current_period_end);
CREATE INDEX idx_subs_status_cancelled  ON subscriptions (status, cancelled_at);
```

These power the campaign eligibility queries. They're cheap (small table, narrow keys) and turn full table scans into index range scans. Will verify in the plan phase whether any of them already exist before generating new migrations.

### New discount code generation flow (Campaign 2)

For each Engaged Trialer about to receive Campaign 2, generate one row:

```
code               = 'WELCOME_X8K2N9'  (collision-checked random suffix)
type               = 'lifecycle_welcome'
value              = 0  (unused for this type)
user_id            = $user->id
max_uses           = 1
max_uses_per_user  = 1
applicable_plans   = ['student', 'standard', 'family']
applicable_cycles  = ['monthly', 'yearly']
starts_at          = now()
expires_at         = now()->addDays(7)   ← urgency baked into the code
is_active          = true
metadata           = {
    "plan_amounts": {
        "student.monthly": 100,    "student.yearly": 801,
        "standard.monthly": 500,   "standard.yearly": 4500,
        "family.monthly": 400,     "family.yearly": 5000
    },
    "campaign": "engaged_trialer",
    "issued_via": "lifecycle_email"
}
```

The generated code string is also stored in `lifecycle_email_log.context` for forensics.

### New discount code validation check

`DiscountCodeService::validate()` gets one new check, near the top:

```php
if ($discount->user_id !== null && $discount->user_id !== $userId) {
    return $this->invalid('This discount code is not valid for your account.');
}
```

Existing shared-code paths are unaffected.

### Storage estimate

- ~50 Campaign 2 emails per day at peak (rough current scale)
- 1 row per email
- ~18,250 rows per year
- Each row ~500 bytes → ~9 MB/year
- Negligible. Can prune `expires_at < now() - 6 months` in a future cleanup if it ever matters.

---

## 4. Eligibility resolvers

### The campaign interface

```php
interface LifecycleCampaign
{
    public function name(): string;              // e.g. 'engaged_trialer'
    public function priority(): int;              // collision priority, lower wins
    public function eligibleUsers(): Collection;  // candidates (engine still applies dedup)
    public function mailable(User $user): Mailable;
}
```

### Universal exclusions (applied by the engine)

Before any campaign-specific logic runs:

- `is_preview_user = true` → excluded
- `is_lifecycle_test_user = true` → excluded *unless* `lifecycle:e2e-test` artisan command is the caller
- Soft-deleted users → excluded automatically by Eloquent `SoftDeletes`
- Already in `lifecycle_email_log` for the campaign being checked → excluded
- Already emailed today by an earlier-priority campaign → excluded
- `notification_preferences.lifecycle_<campaign>` is `false` → excluded (per-campaign opt-out)

### Notification preference filter — handles the "no preference row" case

Each campaign filters by its corresponding `notification_preferences` column. **Users without a `notification_preferences` row at all are treated as opted-in** (matches the `getOrCreateForUser()` default behaviour). The query shape:

```php
->where(function ($q) use ($preferenceColumn) {
    $q->whereDoesntHave('notificationPreference')          // no row = opted in
      ->orWhereHas('notificationPreference', fn ($q2) =>
            $q2->where($preferenceColumn, true)             // row exists, flag is true
        );
})
```

The engine knows which preference column applies to each campaign via `config('lifecycle.campaign_to_preference')` — a one-to-one mapping (see §6 config file).

### Campaigns 1 and 2 — shared base candidate fetch

Campaigns 1 and 2 share their base candidate query (trial expired, no active subscription, registered ≥9 days ago). The engine fetches once, then partitions by "has data" / "no data" via a single batch UNION query in `LifecycleSnapshotService::findUserIdsWithData()`:

```php
public function findUserIdsWithData(array $userIds): Collection
{
    return DB::table('properties')->whereIn('user_id', $userIds)->select('user_id')
        ->union(DB::table('dc_pensions')->whereIn('user_id', $userIds)->select('user_id'))
        ->union(DB::table('savings_accounts')->whereIn('user_id', $userIds)->select('user_id'))
        ->union(DB::table('investment_accounts')->whereIn('user_id', $userIds)->select('user_id'))
        ->union(DB::table('life_insurance_policies')->whereIn('user_id', $userIds)->select('user_id'))
        ->union(DB::table('goals')->whereIn('user_id', $userIds)->select('user_id'))
        ->pluck('user_id')
        ->unique();
}
```

This is one round-trip to the DB regardless of candidate count. Six `UNION` clauses, each `WHERE user_id IN (...)` against an indexed `user_id` column. Scales to 100k+ users in well under a second.

The engine memoises the partition result for the duration of the run — fetched once, reused twice (Campaigns 1 and 2 share them), discarded at end of run.

### Campaign 1 — `EmptyTrialerCampaign`

```php
public function eligibleUsers(): Collection
{
    return $this->engine->trialAfterEndCandidates()
        ->reject(fn ($u) => $this->engine->candidateHasData($u->id));
}
```

Underlying base query (the shared one):

```php
User::query()
    ->where('created_at', '<=', now()->subDays(config('lifecycle.eligibility_anchor_days')))
    ->whereHas('subscriptions', fn ($q) => $q
        ->whereIn('status', ['expired'])
        ->orWhere(fn ($q2) => $q2
            ->where('status', 'trialing')
            ->where('trial_ends_at', '<', now())
        )
    )
    ->whereDoesntHave('subscriptions', fn ($q) => $q->whereIn('status', ['active', 'past_due']))
    ->get();
```

### Campaign 2 — `EngagedTrialerCampaign`

```php
public function eligibleUsers(): Collection
{
    return $this->engine->trialAfterEndCandidates()
        ->filter(fn ($u) => $this->engine->candidateHasData($u->id));
}
```

### Campaign 3 — `CancelledTrialerCampaign`

```php
public function eligibleUsers(): Collection
{
    return User::query()
        ->whereHas('subscriptions', fn ($q) => $q
            ->where('status', 'cancelled')
            ->whereNotNull('cancelled_at')
            ->whereNotNull('trial_started_at')
            ->whereColumn('cancelled_at', '<', 'trial_ends_at')
            ->whereDate('cancelled_at', now()->subDays(3)->toDateString())
        )
        ->whereDoesntHave('subscriptions', fn ($q) => $q->whereIn('status', ['active', 'trialing']))
        ->get();
}
```

**Open question to verify in implementation:** I have not yet read the actual "cancel during trial" flow code. The above assumes that cancelling mid-trial sets `subscriptions.status = 'cancelled'` AND keeps `trial_started_at` populated AND populates `cancelled_at`. If the actual cancel flow does something different, this query will need to adapt. Verify in the plan phase before writing the campaign class.

### Campaign 4 — `ChurnedSubscriberCampaign`

```php
public function eligibleUsers(): Collection
{
    return User::query()
        ->whereHas('subscriptions', fn ($q) => $q
            ->where('status', 'cancelled')
            ->whereNotNull('cancelled_at')
            ->whereColumn('cancelled_at', '>=', 'trial_ends_at')
            ->whereDate('cancelled_at', now()->subDays(3)->toDateString())
        )
        ->whereDoesntHave('subscriptions', fn ($q) => $q->whereIn('status', ['active', 'trialing']))
        ->get();
}
```

### Campaign 5 — `LapsedSubscriberCampaign`

```php
public function eligibleUsers(): Collection
{
    return User::query()
        ->whereHas('subscriptions', fn ($q) => $q
            ->where('status', 'past_due')
            ->where('current_period_end', '<', now()->subDays(5))
        )
        ->get();
}
```

`current_period_end` is the lapse start anchor — no new column needed because `past_due` only happens after `current_period_end` has passed and the auto-renewal payment failed.

### The dispatcher loop

```php
public function run(): array
{
    $stats = [];
    $emailedToday = collect();

    foreach ($this->campaigns->sortBy(fn ($c) => $c->priority()) as $campaign) {
        try {
            $eligible = $campaign->eligibleUsers()
                ->reject(fn ($u) => $u->is_preview_user)
                ->reject(fn ($u) => $u->is_lifecycle_test_user && ! $this->testMode)
                ->reject(fn ($u) => $emailedToday->contains($u->id))
                ->reject(fn ($u) => LifecycleEmailLog::where('user_id', $u->id)
                    ->where('campaign', $campaign->name())
                    ->exists());

            foreach ($eligible as $user) {
                try {
                    $this->dispatchEmail($campaign, $user);
                    $emailedToday->push($user->id);
                    $stats[$campaign->name()]['sent']++;
                } catch (\Throwable $e) {
                    Log::error('Lifecycle email send failed', [
                        'campaign' => $campaign->name(),
                        'user_id' => $user->id,
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                    ]);
                    $stats[$campaign->name()]['errored']++;
                }
            }
        } catch (\Throwable $e) {
            Log::error('Lifecycle campaign failed', [
                'campaign' => $campaign->name(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            $stats[$campaign->name()]['errored']++;
        }
    }

    return $stats;
}
```

---

## 5. Magic link routes and action handlers

### Routes

Five public routes under `/lifecycle/`, all gated by Laravel's `signed` middleware:

```php
Route::middleware('signed')->prefix('lifecycle')->group(function () {
    Route::get('/restart-trial',  [LifecycleActionController::class, 'restartTrial'])
         ->name('lifecycle.restart-trial');
    Route::get('/apply-discount', [LifecycleActionController::class, 'applyDiscount'])
         ->name('lifecycle.apply-discount');
    Route::get('/feedback',       [LifecycleActionController::class, 'feedback'])
         ->name('lifecycle.feedback');
    Route::get('/update-payment', [LifecycleActionController::class, 'updatePayment'])
         ->name('lifecycle.update-payment');
});

Route::post('/lifecycle/feedback-text', [LifecycleActionController::class, 'submitFeedbackText'])
     ->name('lifecycle.feedback-text')
     ->middleware('signed');
```

### Token signing

Laravel's `URL::temporarySignedRoute()` signs URLs with HMAC including the route name, parameters, and expiry. The signature is added as a `?signature=<hash>` query param. The `signed` middleware on the route validates both the signature and the expiry.

Default TTL: `config('lifecycle.magic_link_ttl_days', 7)` — 7 days from email send.

### Action handler 1 — Apply discount (Campaign 2)

```php
public function applyDiscount(Request $request)
{
    $userId   = (int) $request->query('user_id');
    $campaign = $request->query('campaign');
    $code     = $request->query('code');

    $this->markClicked($userId, $campaign, action: 'applied_discount');

    session([
        'lifecycle.pending_discount' => [
            'code'    => $code,
            'user_id' => $userId,
            'expires' => now()->addHour(),
        ],
    ]);

    if (auth()->check() && auth()->id() === $userId) {
        return redirect()->route('checkout.index', ['discount_code' => $code]);
    }

    return redirect()
        ->route('login')
        ->with('intended_after_login', route('checkout.index', ['discount_code' => $code]))
        ->with('lifecycle_message', 'Sign in to claim your welcome discount.');
}
```

**No auto-login.** Forcing a normal login is a tiny extra friction that prevents account-takeover risk via leaked or forwarded emails.

### Action handler 2 — Restart trial (Campaign 1)

```php
public function restartTrial(Request $request)
{
    $userId = (int) $request->query('user_id');
    $user   = User::findOrFail($userId);

    $this->markClicked($userId, 'empty_trialer', action: 'restarted_trial');

    $this->trialService->restartTrial($user, days: config('lifecycle.trial_restart_days', 14));

    if (auth()->check() && auth()->id() === $userId) {
        return redirect()
            ->route('dashboard')
            ->with('success', 'Welcome back! Your Fynla trial is active for another 14 days.');
    }

    return redirect()
        ->route('login')
        ->with('lifecycle_message', 'Sign in to access your reactivated Fynla trial.');
}
```

The new `TrialService::restartTrial(User $user, int $days)` method:

- Updates the user's most recent `Subscription` record (does not create a new one — preserves audit history)
- Sets `status = 'trialing'`, `trial_started_at = now()`, `trial_ends_at = now()->addDays($days)`
- Clears `data_retention_starts_at` (so the data purge countdown stops)
- Updates `users.plan = 'pro'` (since trial = pro-level access)
- Updates `users.trial_ends_at = $newTrialEnd`
- Idempotent: if the user is already `trialing` with future `trial_ends_at`, no-op (don't extend further)

### Action handler 3 — Feedback quick-pick (Campaigns 3, 4, 5)

```php
public function feedback(Request $request)
{
    $userId   = (int) $request->query('user_id');
    $campaign = $request->query('campaign');
    $reason   = $request->query('reason');

    $allowedReasons = config("lifecycle.feedback_reasons.{$campaign}");
    abort_unless($allowedReasons && in_array($reason, $allowedReasons, true), 400);

    FeedbackResponse::updateOrCreate(
        ['user_id' => $userId, 'campaign' => $campaign],
        ['reason_code' => $reason, 'clicked_at' => now()]
    );

    $this->markClicked($userId, $campaign, action: "feedback:{$reason}");

    return view('lifecycle.feedback-thanks', [
        'campaign' => $campaign,
        'reason'   => $reason,
        'user_id'  => $userId,
        'signed_token' => $request->fullUrl(),
    ]);
}

public function submitFeedbackText(Request $request)
{
    $request->validate(['free_text' => 'required|string|max:2000']);

    FeedbackResponse::where('user_id', $request->input('user_id'))
        ->where('campaign', $request->input('campaign'))
        ->update([
            'free_text'         => $request->input('free_text'),
            'text_submitted_at' => now(),
        ]);

    return view('lifecycle.feedback-text-thanks');
}
```

### Action handler 4 — Update payment (Campaign 5)

```php
public function updatePayment(Request $request)
{
    $userId = (int) $request->query('user_id');

    $this->markClicked($userId, 'lapsed_subscriber', action: 'clicked_update_payment');

    if (auth()->check() && auth()->id() === $userId) {
        return redirect()->route('account.billing');
    }

    return redirect()
        ->route('login')
        ->with('intended_after_login', route('account.billing'))
        ->with('lifecycle_message', 'Sign in to update your payment method.');
}
```

### Click tracking on `lifecycle_email_log`

The `markClicked()` helper used by all four action handlers:

```php
private function markClicked(int $userId, string $campaign, string $action): void
{
    LifecycleEmailLog::where('user_id', $userId)
        ->where('campaign', $campaign)
        ->whereNull('clicked_at')   // first click wins (idempotent)
        ->update([
            'clicked_at'   => now(),
            'action_taken' => $action,
        ]);
}
```

### Security model

| Risk | Mitigation |
|---|---|
| Forged `user_id` (impersonation) | HMAC signature in `signed` middleware |
| Replayed link after expiry | 7-day expiry timestamp validated by `signed` middleware |
| Account takeover via leaked email | No auto-login — forces normal authentication |
| Discount code shared (sender of original email) | Per-user `discount_codes.user_id` lock — code rejects other users at checkout |
| Discount used after campaign window | `discount_codes.expires_at = now + 7 days` at generation |
| Feedback button replay/spam | `updateOrCreate` is idempotent — second click is a no-op |
| Free text field XSS / HTML injection | Standard Laravel validation + `e()` escaping in admin views |
| Free text field too long | `max:2000` validation |
| User clicks restart-trial twice | `TrialService::restartTrial()` is idempotent — checks current state, no-op if already valid |

---

## 6. Scheduled command, configuration, and runtime wiring

### Artisan command

```
app/Console/Commands/RunLifecycleEngine.php
```

```php
class RunLifecycleEngine extends Command
{
    protected $signature = 'lifecycle:run-daily';
    protected $description = 'Send lifecycle emails (re-engagement, conversion, feedback, recovery)';

    public function handle(LifecycleEngine $engine): int
    {
        if (! config('lifecycle.enabled')) {
            $this->warn('Lifecycle engine is disabled via config.');
            return Command::SUCCESS;
        }

        // Defence-in-depth: refuse to run if test users haven't been cleaned up
        $staleTestUsers = User::where('is_lifecycle_test_user', true)->count();
        if ($staleTestUsers > 0) {
            Log::error('Lifecycle engine refusing to run: stale test users present', [
                'count' => $staleTestUsers,
            ]);
            $this->error("Refusing to run — {$staleTestUsers} test users still exist. Run 'php artisan lifecycle:e2e-cleanup' first.");
            return Command::FAILURE;
        }

        $stats = $engine->run();

        foreach ($stats as $campaign => $counts) {
            $this->info(sprintf(
                '%s: %d sent, %d skipped, %d errored',
                $campaign,
                $counts['sent'] ?? 0,
                $counts['skipped'] ?? 0,
                $counts['errored'] ?? 0
            ));
        }

        return Command::SUCCESS;
    }
}
```

### Kernel.php integration

One new line in `app/Console/Kernel.php`. Per Q4 in §13, scheduled at **08:30** — 30 minutes ahead of the existing 09:00 cluster so lifecycle emails arrive first in users' inboxes (better visibility for Campaign 2 conversion):

```php
$schedule->command('lifecycle:run-daily')->dailyAt('08:30');
```

### Configuration file

`config/lifecycle.php`:

```php
return [
    'enabled' => env('LIFECYCLE_ENGINE_ENABLED', true),

    'campaigns' => [
        \App\Services\Lifecycle\Campaigns\CancelledTrialerCampaign::class,
        \App\Services\Lifecycle\Campaigns\ChurnedSubscriberCampaign::class,
        \App\Services\Lifecycle\Campaigns\LapsedSubscriberCampaign::class,
        \App\Services\Lifecycle\Campaigns\EmptyTrialerCampaign::class,
        \App\Services\Lifecycle\Campaigns\EngagedTrialerCampaign::class,
    ],

    'trial_restart_days' => 14,
    'magic_link_ttl_days' => 7,
    'discount_code_ttl_days' => 7,
    'cancellation_feedback_delay_days' => 3,
    'lapsed_recovery_threshold_days' => 5,
    'eligibility_anchor_days' => 9,

    'campaign2_discounts' => [
        'student.monthly'  => 100,
        'student.yearly'   => 801,
        'standard.monthly' => 500,
        'standard.yearly'  => 4500,
        'family.monthly'   => 400,
        'family.yearly'    => 5000,
    ],

    'feedback_reasons' => [
        'cancelled_trialer'   => ['too_expensive', 'missing_features', 'found_alternative', 'not_what_expected', 'bugs_or_ux', 'personal_change', 'other'],
        'churned_subscriber'  => ['too_expensive', 'missing_features', 'found_alternative', 'not_what_expected', 'bugs_or_ux', 'personal_change', 'other'],
        'lapsed_subscriber'   => ['will_fix', 'wants_to_cancel', 'needs_help'],
    ],

    // Maps each campaign slug to its corresponding notification_preferences column.
    // Used by LifecycleEngine::eligibleUsers() to apply the opt-out filter.
    'campaign_to_preference' => [
        'empty_trialer'       => 'lifecycle_empty_trialer',
        'engaged_trialer'     => 'lifecycle_engaged_trialer',
        'cancelled_trialer'   => 'lifecycle_cancelled_trialer',
        'churned_subscriber'  => 'lifecycle_churned_subscriber',
        'lapsed_subscriber'   => 'lifecycle_lapsed_subscriber',
    ],

    'test_recipient_override' => env('LIFECYCLE_TEST_RECIPIENT', null),
];
```

### Service container registration (`AppServiceProvider`)

```php
public function register(): void
{
    $this->app->singleton(LifecycleEngine::class, function ($app) {
        $campaigns = collect(config('lifecycle.campaigns'))
            ->map(fn ($class) => $app->make($class));

        return new LifecycleEngine(
            campaigns: $campaigns,
            snapshotService: $app->make(LifecycleSnapshotService::class),
            discountGenerator: $app->make(LifecycleDiscountCodeGenerator::class),
        );
    });
}
```

### Failure model

Three layers of failure isolation:

1. **Campaign-level isolation.** If `EngagedTrialerCampaign::eligibleUsers()` throws, the engine catches it, logs it, and continues to the next campaign.
2. **Per-user isolation.** Within a campaign, if dispatching to one user fails, the engine catches it, logs it, and continues to the next user.
3. **Idempotent retries via dedup.** The dedup row is inserted **after** the mail send succeeds. If the send fails, no dedup row is written, and the user remains eligible for the next day's run.

**Accepted race window:** mail succeeds → INSERT into `lifecycle_email_log` fails (DB hiccup) → user re-emailed tomorrow. Window is ~10ms, exceedingly unlikely. Hardening to a `pending → sent → failed` state machine deferred to v2 if it ever proves necessary.

---

## 7. Email templates and the personalisation snapshot

### `LifecycleSnapshotService`

```php
class LifecycleSnapshotService
{
    public function isEmpty(User $user): bool;
    public function findUserIdsWithData(array $userIds): Collection;  // batch (Section 4)
    public function buildContext(User $user): array;
}
```

`buildContext()` returns:

```php
[
    'first_name' => 'James',
    'completion_pct' => 62,
    'modules_with_data' => [
        ['name' => 'Properties',  'count' => 2, 'label' => 'properties'],
        ['name' => 'Pensions',    'count' => 1, 'label' => 'pension'],
        ['name' => 'Savings',     'count' => 5, 'label' => 'savings accounts'],
        ['name' => 'Investments', 'count' => 2, 'label' => 'investment accounts'],
        ['name' => 'Family',      'count' => 4, 'label' => 'family members'],
    ],
    'modules_remaining' => ['Protection', 'Goals'],
    'days_since_signup' => 9,
]
```

**No monetary values, no income figures, no net worth.** Per the brainstorming, personalisation is qualitative — counts and module names only.

### Shared Blade partials

```
resources/views/emails/lifecycle/
  ├── _layout.blade.php           ← shared HTML shell, head, body wrapper, footer
  ├── _button.blade.php           ← the standard CTA button
  ├── _quick-picks.blade.php      ← inline button row used by 3/4/5
  ├── empty-trialer.blade.php     ← Campaign 1
  ├── engaged-trialer.blade.php   ← Campaign 2 (incl. HTML completion bar)
  ├── cancelled-trialer.blade.php ← Campaign 3
  ├── churned-subscriber.blade.php ← Campaign 4
  └── lapsed-subscriber.blade.php  ← Campaign 5
```

### Fynla design palette mapping for HTML email

HTML email cannot use Tailwind classes — every style must be inline. The palette mapping (hex codes from `fynlaDesignGuide.md` v1.3.0):

| Use | Hex | Token |
|---|---|---|
| Page background | `#F7F6F4` | `eggshell-500` |
| Card background | `#FFFFFF` | white |
| Body text | `#1F2A44` | `horizon-500` |
| Muted text | `#717171` | `neutral-500` |
| Primary CTA bg | `#E83E6D` | `raspberry-500` |
| Primary CTA text | `#FFFFFF` | white |
| Secondary CTA border | `#1F2A44` | `horizon-500` |
| Success accent | `#20B486` | `spring-500` |
| Subtle highlight bg | `#FDFAF7` | `savannah-100` |
| Footer divider | `#EEEEEE` | `light-gray` |

### Tangential cleanup — fix existing `trial-expiration-reminder.blade.php`

The existing trial reminder template uses `#3b82f6` and `#f0f9ff` (generic blue, NOT from the Fynla palette). This is a pre-existing design system violation. **As part of this work, the template's hex codes are updated to match the Fynla palette** (raspberry CTA, horizon body, etc.). One file, ~15 hex codes swapped. No copy or layout changes.

### Mail classes

```
app/Mail/Lifecycle/
  ├── EmptyTrialerMail.php
  ├── EngagedTrialerMail.php
  ├── CancelledTrialerMail.php
  ├── ChurnedSubscriberMail.php
  └── LapsedSubscriberMail.php
```

Each takes the user, the snapshot context, the magic links, and the discount code (Campaign 2 only) as constructor args. The `content()` method maps these into the Blade template's expected variables. Subject lines support a `{first_name}` token with a graceful fallback if `first_name` is null.

### Unsubscribe footer (all 5 templates)

Per Q1 in §13, every lifecycle email template includes a footer that points to the user's notification settings:

```
You're receiving this because you signed up for Fynla.
You can manage which Fynla emails you receive in your
account settings: https://fynla.org/account/notifications
```

**Note:** No one-click "unsubscribe all" link in the footer (per Q1's confirmation). The user must visit settings to opt out — either of the specific lifecycle email type or of any other notification preference. Settings is the single point of control.

This footer is **only added to the 5 new lifecycle templates**. The existing transactional email templates (trial reminder, renewal reminder, data retention warning) are deliberately left alone — they are required communication and don't honour notification preferences.

### Email content shapes

#### Campaign 1 — Empty trialer fresh restart

- **Subject:** `It's been a while — come back and try Fynla again`
- **Personalisation:** `first_name` only
- **Primary CTA:** `[ START MY 14-DAY TRIAL ]` → `lifecycle.restart-trial` magic link
- **No secondary CTAs**

#### Campaign 2 — Engaged trialer discount + summary

- **Subject:** `Your Fynla picture so far, {first_name} — and 25-45% off to finish it`
- **Personalisation:** Full snapshot context (completion %, modules, family count)
- **Visual:** HTML table-based progress bar showing completion %
- **Discount table:** Student/Standard/Family with monthly + yearly + percentage saved + Pro at full price
- **Primary CTA:** `[ CLAIM YOUR DISCOUNT ]` → `lifecycle.apply-discount` magic link
- **Fallback:** Visible discount code (`WELCOME_X8K2N9`) shown below the button

#### Campaign 3 — Cancelled trialer feedback

- **Subject:** `Sorry to see you go — what could we have done better?`
- **Personalisation:** `first_name` only
- **CTAs:** 7 quick-pick buttons (`too_expensive`, `missing_features`, `found_alternative`, `not_what_expected`, `bugs_or_ux`, `personal_change`, `other`)
- Each is a magic link to `lifecycle.feedback?reason=X`

#### Campaign 4 — Churned subscriber feedback

- **Subject:** `Thank you for being a Fynla subscriber — we'd love your feedback`
- **Personalisation:** `first_name` + `subscription_duration` (formatted)
- Same 7 quick-picks as Campaign 3

#### Campaign 5 — Lapsed subscriber recovery

- **Subject:** `Your Fynla payment didn't go through — let's fix it`
- **Personalisation:** `first_name` + `grace_period_end` (computed from `current_period_end + 7 days`)
- **Primary CTA:** `[ UPDATE PAYMENT METHOD ]` → `lifecycle.update-payment` magic link
- **Secondary:** 3 quick-pick buttons (`will_fix`, `wants_to_cancel`, `needs_help`) → `lifecycle.feedback`

### What's NOT in v1

| Skipped | Why |
|---|---|
| Email queue (`Mail::queue()`) | Synchronous send is fine for ~50 emails/day. One keyword change later if needed. |
| A/B test framework | YAGNI — let real data tell us if we need to test variants. |
| Open/click pixel tracking | Magic link clicks ARE the click tracking. Open pixels are blocked by most modern clients and create deliverability risk. |
| Admin metrics dashboard | Logs + the `lifecycle_email_log` and `feedback_responses` tables are sufficient for forensics. Dashboard deferred to a future session. |
| Multi-step sequences (e.g., Campaign 2b) | Each campaign is one-shot per user lifetime. Sequences add complexity to the dedup model. Re-evaluate after launch data. |
| Localisation / i18n | Whole app is currently English-only. |
| Dark mode email styles | Most clients don't honour them. Fynla palette works on light + dark backgrounds. |
| Plain-text alternative (multipart/alternative) | Can be added later via Laravel's `text:` view support. Not gating launch. |

---

## 8. Testing strategy

### Layer 1 — Unit tests (~25 methods)

Pure logic, mocked dependencies, fast. Cover snapshot service, discount generator, engine dispatch logic, `TrialService::restartTrial`, augmentations to `DiscountCodeService`.

```
tests/Unit/Services/Lifecycle/
  ├── LifecycleSnapshotServiceTest.php
  ├── LifecycleDiscountCodeGeneratorTest.php
  └── LifecycleEngineTest.php

tests/Unit/Services/Payment/
  ├── DiscountCodeServiceTest.php  (augmented — new lifecycle_welcome + user_id lock tests)
  └── TrialServiceTest.php          (augmented — new restartTrial tests)
```

### Layer 2 — Pest feature tests (~30 methods)

Full HTTP stack, real DB via `RefreshDatabase`, `Mail::fake()` so mailables are asserted without sending.

```
tests/Feature/Lifecycle/
  ├── LifecycleActionControllerTest.php  (5 action handlers + edge cases)
  ├── LifecycleEngineCommandTest.php     (artisan command behaviour)
  └── Campaigns/
      ├── EmptyTrialerCampaignTest.php
      ├── EngagedTrialerCampaignTest.php
      ├── CancelledTrialerCampaignTest.php
      ├── ChurnedSubscriberCampaignTest.php
      └── LapsedSubscriberCampaignTest.php
```

Also one Pest end-to-end happy-path test for Campaign 2 (`tests/Feature/Lifecycle/LifecycleEngineEndToEndTest.php`) — keeps the integration path covered in CI.

**Notification preference filter coverage (5 new tests):** Each campaign test class adds one test:

- `it excludes users with lifecycle_<campaign> = false` — create eligible user, set preference to false, run engine, assert NOT emailed
- Plus: `it includes users with no notification_preferences row at all` — verifies the "no row = opted in" fallback works correctly

This covers the per-campaign opt-out mechanism end-to-end at the feature level.

### Layer 3 — Live end-to-end validation suite (NEW)

This is the meaningful layer. Real database, real SMTP, real magic link clicks, real discount apply, real `lifecycle_email_log` writes. Mandatory before launch.

#### Three new pieces of infrastructure

##### 1. `LifecycleTestSeeder`

Creates 5 dummy users, one per campaign state, each with `is_lifecycle_test_user = true`, deterministic emails (`lifecycle-e2e-N@fynla.test`), and a known password (`Password1!`):

- **User 1 (TestEmpty)** — Empty trialer (Campaign 1): created 9 days ago, expired trial, no module data
- **User 2 (TestEngaged)** — Engaged trialer (Campaign 2): created 9 days ago, expired trial, has module data (2 properties, 1 pension, 5 savings, 2 investments, 4 family members)
- **User 3 (TestCancelled)** — Cancelled trialer (Campaign 3): cancelled mid-trial 3 days ago
- **User 4 (TestChurned)** — Churned subscriber (Campaign 4): cancelled paid subscription 3 days ago
- **User 5 (TestLapsed)** — Lapsed subscriber (Campaign 5): `status=past_due` for 5+ days

All 5 users have password `Password1!` (consistent with existing test conventions in `CLAUDE.md`).

##### 2. Recipient override

```php
// In LifecycleEngine::dispatchEmail()
$recipient = $user->is_lifecycle_test_user && config('lifecycle.test_recipient_override')
    ? config('lifecycle.test_recipient_override')
    : $user->email;

Mail::to($recipient)->send($mailable);
```

The override **only applies to test users** (defence in depth — even with a fat-fingered env var, no real users are affected). Test users still personalise with their first name so each email is identifiable in the inbox.

##### 3. Two new artisan commands

```bash
# Setup + run engine against test users only
php artisan lifecycle:e2e-test --recipient=chris@fynla.org [--keep]

# Cleanup — removes test users and all linked data
php artisan lifecycle:e2e-cleanup
```

`lifecycle:e2e-test` does:
1. Sets `config('lifecycle.test_recipient_override')` for this run
2. Runs `LifecycleTestSeeder` (creates 5 dummy users)
3. Runs `LifecycleEngine::run()` with `testMode = true` (the engine recognises `is_lifecycle_test_user` users)
4. Prints magic link URLs, test user IDs, generated discount codes, and `lifecycle_email_log` row IDs to the CLI
5. Does NOT auto-cleanup (left for explicit cleanup step)

`lifecycle:e2e-cleanup` does:
1. Finds all users where `is_lifecycle_test_user = true`
2. Deletes their `lifecycle_email_log`, `discount_codes` (lifecycle_welcome only), `feedback_responses`, `subscriptions`, module records, then user records themselves
3. Reports counts of deletions
4. Filter is the entry point for every delete — cannot accidentally touch real user data

##### Defence-in-depth safety check

`lifecycle:run-daily` refuses to run if any `is_lifecycle_test_user` rows exist. Means even if e2e-cleanup is forgotten, the worst case is the daily engine fails to run (visible failure) rather than emailing test users with real campaigns (silent failure).

#### Mandatory pre-launch verification protocol

The full 12-step manual checklist runs before lifecycle:run-daily is enabled:

```
□ 1. SSH to production
□ 2. php artisan lifecycle:e2e-test --recipient=chris@fynla.org
□ 3. Open chris@fynla.org inbox
□ 4. Confirm 5 emails received within 60 seconds
□ 5. For each email, verify:
       - Subject line correct (with personalisation)
       - Body renders correctly
       - Buttons/links visible
       - Personalisation tokens correct (no {first_name} leakage)
       - Footer correct

□ 6. Campaign 1 (TestEmpty):
       - Click "Start my 14-day trial"
       - Verify redirect to login (not auto-login)
       - Log in as TestEmpty with Password1!
       - Verify dashboard shows "Welcome back" toast
       - Verify subscriptions.status=trialing, trial_ends_at = today + 14 days
       - Verify users.plan = pro
       - Verify lifecycle_email_log.clicked_at + action_taken='restarted_trial'

□ 7. Campaign 2 (TestEngaged):
       - Inspect email — completion bar renders, module list shows 5 entries
       - Click "Claim your discount"
       - Verify redirect to login → log in as TestEngaged with Password1!
       - Verify checkout page loads with discount code in query/session
       - Pick Standard monthly plan
       - Verify discount applied: £10.99 - £5.00 = £5.99 displayed
       - DO NOT actually pay (cancel out — no real charge)
       - Verify the magic link cannot be reused from a different account
         (sign out, paste the URL, log in as TestChurned → expect rejection)

□ 8. Campaign 3 (TestCancelled):
       - Click "Too expensive"
       - Verify thank-you page renders
       - Verify feedback_responses row created with reason_code='too_expensive'
       - Submit optional text "test feedback text"
       - Verify free_text + text_submitted_at populated
       - Click a different reason on same email → verify reason_code REPLACED

□ 9. Campaign 4 (TestChurned):
       - Mirror of Campaign 3
       - Subject line should say "Thank you for being a Fynla subscriber"

□ 10. Campaign 5 (TestLapsed):
       - Click "Update payment method"
       - Verify redirect to /account/billing
       - Verify lifecycle_email_log.clicked_at + action_taken='clicked_update_payment'
       - Click "I'll fix it shortly" quick-pick
       - Verify feedback_responses row with reason_code='will_fix'
       - Click "I want to cancel" — verify reason_code updates to 'wants_to_cancel'

□ 11. Edge cases:
       - Tampered URL: change one character of signature → verify 403
       - Expired URL: edit ?expires= to past timestamp → verify 403
       - Notification preference opt-out:
           - Log in as TestEngaged
           - Open settings → notifications → toggle "Engaged trialer" off
           - Manually re-trigger lifecycle:e2e-test
           - Verify engaged_trialer email is NOT sent (other 4 still sent)
           - Toggle back on, re-run, verify it's sent

□ 12. Cleanup:
       - php artisan lifecycle:e2e-cleanup
       - Verify all 5 test users gone
       - Verify lifecycle_email_log has zero is_lifecycle_test_user rows
       - Verify feedback_responses, discount_codes (lifecycle_welcome), and subscriptions all clean
```

#### Test review report (NEW deliverable)

After running the 12-step protocol, the operator writes a **test review report** to:

```
April/AprilNUpdates/lifecycleEngineE2EReport.md
fynlaBrain/April/AprilNUpdates/lifecycleEngineE2EReport.md
```

The report includes:

- Date and operator
- Result for each of the 12 steps (PASS / FAIL / NOTE)
- Issues found and resolutions applied
- Final sign-off: "ready to launch" or "blocking issues — see [specific items]"

**No screenshots required.** The report is a written verification artefact, not a visual one. The discipline is in the act of writing it down — every claim of "PASS" is a claim that the operator personally verified that step.

#### What's not tested (manual or deferred)

- Email visual rendering across clients (Outlook, Gmail, Apple Mail) — manual visual inspection during step 5
- SMTP delivery / bounce handling — proven by existing trial reminder system
- Conversion rate metrics — runtime observations, not unit tests

---

## 9. File map

### New files

```
app/Console/Commands/
  ├── RunLifecycleEngine.php
  ├── RunLifecycleEngineE2ETest.php
  └── RunLifecycleEngineE2ECleanup.php

app/Http/Controllers/Lifecycle/
  └── LifecycleActionController.php

app/Mail/Lifecycle/
  ├── EmptyTrialerMail.php
  ├── EngagedTrialerMail.php
  ├── CancelledTrialerMail.php
  ├── ChurnedSubscriberMail.php
  └── LapsedSubscriberMail.php

app/Models/
  ├── LifecycleEmailLog.php
  └── FeedbackResponse.php

app/Services/Lifecycle/
  ├── LifecycleEngine.php
  ├── LifecycleSnapshotService.php
  ├── LifecycleDiscountCodeGenerator.php
  ├── Contracts/
  │   └── LifecycleCampaign.php
  └── Campaigns/
      ├── EmptyTrialerCampaign.php
      ├── EngagedTrialerCampaign.php
      ├── CancelledTrialerCampaign.php
      ├── ChurnedSubscriberCampaign.php
      └── LapsedSubscriberCampaign.php

config/
  └── lifecycle.php

database/migrations/
  ├── YYYY_MM_DD_create_lifecycle_email_log_table.php
  ├── YYYY_MM_DD_create_feedback_responses_table.php
  ├── YYYY_MM_DD_add_user_id_and_metadata_to_discount_codes.php
  ├── YYYY_MM_DD_add_is_lifecycle_test_user_to_users.php
  ├── YYYY_MM_DD_add_lifecycle_columns_to_notification_preferences.php
  └── YYYY_MM_DD_add_subscriptions_indexes.php  (only if missing)

database/seeders/
  └── LifecycleTestSeeder.php

resources/views/emails/lifecycle/
  ├── _layout.blade.php
  ├── _button.blade.php
  ├── _quick-picks.blade.php
  ├── empty-trialer.blade.php
  ├── engaged-trialer.blade.php
  ├── cancelled-trialer.blade.php
  ├── churned-subscriber.blade.php
  ├── lapsed-subscriber.blade.php
  ├── feedback-thanks.blade.php
  └── feedback-text-thanks.blade.php

routes/web.php
  └── (5 new lifecycle routes added)

tests/Unit/Services/Lifecycle/
  ├── LifecycleSnapshotServiceTest.php
  ├── LifecycleDiscountCodeGeneratorTest.php
  └── LifecycleEngineTest.php

tests/Feature/Lifecycle/
  ├── LifecycleActionControllerTest.php
  ├── LifecycleEngineCommandTest.php
  ├── LifecycleEngineEndToEndTest.php
  └── Campaigns/
      ├── EmptyTrialerCampaignTest.php
      ├── EngagedTrialerCampaignTest.php
      ├── CancelledTrialerCampaignTest.php
      ├── ChurnedSubscriberCampaignTest.php
      └── LapsedSubscriberCampaignTest.php

database/factories/
  └── (UserFactory augmented with 5 new state methods)
```

### Modified files

```
app/Console/Kernel.php
  - 1 line added: $schedule->command('lifecycle:run-daily')->dailyAt('09:00')

app/Providers/AppServiceProvider.php
  - LifecycleEngine singleton binding

app/Models/User.php
  - is_lifecycle_test_user added to $fillable + $casts
  - hasMany('lifecycleEmails') relation

app/Models/NotificationPreference.php
  - 5 lifecycle_* fields added to $fillable + $casts
  - getOrCreateForUser() defaults updated to include 5 new fields (all true)

resources/js/mobile/views/NotificationSettings.vue
  - 5 new toggles added to the toggleItems array under a "Lifecycle emails" section header
  - No structural changes — same toggle component, same persistence flow
  - ~30 lines added

resources/js/components/UserProfile/NotificationPreferences.vue   (NEW)
  - Brand-new web settings page (mirrors the mobile component's structure)
  - All 14 toggles (9 existing + 5 lifecycle), grouped into 3 sections:
    * Account (security_alerts, payment_alerts)
    * Feature alerts (policy_renewals, goal_milestones, contribution_reminders,
                       market_updates, fyn_daily_insight, mortgage_rate_alerts)
    * Lifecycle emails (5 new)
  - Persists via the new /api/notifications/preferences endpoint
  - ~250 lines

resources/js/components/UserProfile/Settings.vue
  - Add a new "Notifications" tab linking to NotificationPreferences.vue
  - ~10 lines added

app/Http/Controllers/Api/NotificationPreferenceController.php   (NEW)
  - Mirrors the existing mobile controller's show() and update() methods
  - Returns/accepts all 14 fields (the existing controller only handles 8 — needs
    fix for estate_alerts which IS in the model but missing from controller's
    show() response — fold this fix in as scope-adjacent cleanup)
  - ~70 lines

routes/api.php
  - 2 new routes: GET/PUT /api/notifications/preferences
  - Both behind auth:sanctum middleware

app/Models/Subscription.php
  - (no changes — existing fields cover all our needs)

app/Models/DiscountCode.php
  - user_id + metadata added to $fillable + $casts
  - calculateDiscount() + isValid() + new logic for lifecycle_welcome type

app/Services/Payment/DiscountCodeService.php
  - validate() — new user_id lock check
  - calculateDiscount() — new lifecycle_welcome arm

app/Services/Payment/TrialService.php
  - new restartTrial() method

resources/views/emails/trial-expiration-reminder.blade.php
  - hex code swap to Fynla palette (tangential cleanup, in scope)

tests/Unit/Services/Payment/DiscountCodeServiceTest.php
  - augmented with 4-5 new tests

tests/Unit/Services/Payment/TrialServiceTest.php
  - augmented with 4-5 new tests
```

### Total

- **~30 new files**
- **~10 modified files**
- **5 new migrations** (one if subscription indexes already exist)
- **1 new config file**
- **~50 new test methods** (Layer 1 + Layer 2)
- **2 new artisan commands for the e2e suite** + 1 for the daily run
- **~15 hex codes swapped** in the existing trial reminder template (tangential cleanup)

---

## 10. Open questions for implementation phase

These don't block the design — they're things to verify when reading the actual code in the writing-plans phase, not now:

1. **Cancel-mid-trial flow:** verify what exact subscription state is set when a user cancels during trial. Section 4's Campaign 3 query assumes `status='cancelled'` + `trial_started_at` populated + `cancelled_at` populated. May need adjustment.
2. **Existing subscription indexes:** check whether `idx_subs_status_trial`, `idx_subs_status_period`, `idx_subs_status_cancelled` already exist. Skip the migration if so.
3. **Discount type column on `discount_codes`:** verified to be varchar (not enum) in schema, but worth re-checking before generating the migration to add `lifecycle_welcome`.
4. **`first_name` nullability on `users`:** check whether legacy users may have null `first_name`. The fallback subject line strings should be tested against the real DB shape.
5. **Vue settings component path:** RESOLVED during writing-plans phase. Found that notification preferences UI exists ONLY in mobile (`resources/js/mobile/views/NotificationSettings.vue`). Per Option B (chosen), a new web component at `resources/js/components/UserProfile/NotificationPreferences.vue` is being added as part of this work. See §3 for the full Option B treatment.
6. **Existing notification preference save endpoint:** RESOLVED. The mobile endpoint at `/v1/mobile/notifications/preferences` uses an `UpdateNotificationPreferencesRequest` Form Request that we need to update to allow the 5 new fields. The new web endpoint at `/api/notifications/preferences` will use the same Form Request (or its own copy — to be decided in the plan).
7. **`UpdateNotificationPreferencesRequest` rules:** find the existing Form Request and update its `rules()` method to include the 5 new lifecycle boolean fields.
8. **Estate alerts field gap in mobile controller:** noticed during investigation that the mobile `NotificationPreferenceController::show()` returns 8 fields but the model has 9 (`estate_alerts` is missing from the response). Worth fixing as scope-adjacent cleanup since we're already touching this area — flag in the plan as a small fix. **Confirm with user before bundling.**

---

## 11. Out of scope / explicitly NOT in v1

- Email queue (sync send is fine at current scale)
- A/B test framework
- Open/click pixel tracking
- Admin metrics dashboard for lifecycle emails
- Multi-step sequences (e.g., Campaign 2 follow-up if no click)
- Localisation / i18n
- Dark mode email styles
- Plain-text email alternative (`multipart/alternative`)
- Email deliverability monitoring beyond Laravel logs
- Lifecycle email opt-out preference on `notification_preferences` (deferred to question 10.5 above)

---

## 12. Dependencies and prerequisites

1. **The system cron must be firing on production.** This was added by CSJ at the end of session 51 but verification is pending session 52 (see `April/April14Updates/trialReminderInvestigation.md` and `April/April15Updates/CSJTODO.md`). **The lifecycle engine cannot ship until cron is verified working** because it depends on `lifecycle:run-daily` actually being triggered each morning.
2. **`notifications` table on production.** Already created in session 51 (PR commit `f50428b`). No further action needed.
3. **Existing trial reminder system stays in place.** This work does not modify or replace `trials:send-reminders` — the lifecycle engine fires AFTER the trial expires, so the two systems are sequential, not overlapping. See §13 for the full relationship analysis.

---

## 13. Relationship to existing email commands

The lifecycle engine does not exist in isolation. Several existing scheduled commands already send emails to overlapping user populations, and it's important the new engine plays nicely with them. This section documents the existing email surface, the day-by-day overlap analysis, and the decisions made about how the lifecycle engine relates to each.

### 13.1 The existing email command matrix

| Command | Schedule slot | Trigger | Target state | Honours `notification_preferences`? |
|---|---|---|---|---|
| `trials:send-reminders` | 09:00 daily | 3/2/1 days before `trial_ends_at` | `status='trialing'` | **No** — always sent (transactional) |
| `subscriptions:send-renewal-reminders` | 09:00 daily | 7 days before `current_period_end` | `status='active'` | **No** — always sent (transactional) |
| `data-retention:send-warnings` | 09:00 daily | Days 1, 15, 20-29 of the 30-day grace period | `status='expired'` AND `data_retention_starts_at IS NOT NULL` | **No** — always sent (transactional) |
| `notifications:policy-renewals` | 09:00 daily | Per-policy expiry dates | Active users with policies | **Yes** (`policy_renewals` flag) |
| `protection:send-alerts` | 09:15 daily | Various protection alert conditions | Active users with protection data | **Yes** (`policy_renewals` flag — shared) |
| `notifications:mortgage-rate-alerts` | 09:30 daily | When user's mortgage rate is uncompetitive | Active users with mortgages | **Yes** (`mortgage_rate_alerts` flag) |
| `savings:send-alerts` | 10:00 daily | Savings rate expiry, ISA allowance warnings, emergency fund alerts | Active users with savings data | **Yes** (mixed — multiple flags) |
| `estate:send-alerts` | 10:30 daily | Gift exemption windows, trust anniversaries | Active users with estate data | **Yes** (`estate_alerts` flag) |
| `notifications:daily-insight` | 08:00 daily | Daily push notification (not email) | Mobile users with `fyn_daily_insight = true` | **Yes** (`fyn_daily_insight` flag) |
| **`lifecycle:run-daily` (NEW)** | **08:30 daily** | **Per-campaign — see §1** | **Per-campaign — see §1** | **Yes** (5 new per-campaign flags) |

### 13.2 The pattern

The existing commands fall into two categories:

**Transactional emails** (always sent, no opt-out):
- `trials:send-reminders` — required communication during a trial
- `subscriptions:send-renewal-reminders` — required heads-up before charging a card
- `data-retention:send-warnings` — required notice before deleting data (legal/GDPR consideration)

**Feature alerts** (honour `notification_preferences`):
- `notifications:policy-renewals`, `protection:send-alerts`, `notifications:mortgage-rate-alerts`, `savings:send-alerts`, `estate:send-alerts`, `notifications:daily-insight`

**The lifecycle engine sits in a third category: re-engagement and feedback.** These emails are *promotional-feeling* (especially Campaign 2's discount offer). Per Q1 below, they honour notification preferences, with each campaign individually toggleable.

### 13.3 Day-by-day overlap analysis (post-trial users)

The lifecycle engine's most active overlap surface is the post-trial period. Mapped out by day from sign-up:

| Days from sign-up | User state | Existing emails sent | Lifecycle engine sends | Total |
|---:|---|---|---|---:|
| Day 5 | `trialing`, day 5 of trial | trial reminder (3 days left) | — | 1 |
| Day 6 | `trialing`, day 6 of trial | trial reminder (2 days left) | — | 1 |
| Day 7 | `trialing`, day 7 of trial | trial reminder (1 day left) | — | 1 |
| 00:05 day 8 | `trials:expire` runs | (no email) | — | — |
| **Day 8** (retention day 1) | `expired`, day 1 of grace | data-retention warning (day 1 in EMAIL_DAYS) | — | 1 |
| **Day 9** (retention day 2) | `expired`, day 2 of grace | (day 2 NOT in EMAIL_DAYS) | **empty/engaged trialer** | 1 |
| Days 10-21 | `expired`, days 3-14 of grace | (none — gap in EMAIL_DAYS) | (dedup-ed since day 9) | 0 |
| **Day 22** (retention day 15) | `expired`, day 15 of grace | data-retention warning (day 15) | (dedup-ed) | 1 |
| **Day 27** (retention day 20) | `expired`, day 20 | data-retention warning (day 20) | (dedup-ed) | 1 |
| Days 28-36 | `expired`, days 21-29 | data-retention warnings (daily urgency) | (dedup-ed) | 1/day |
| Day 37 | `data-retention:purge-expired` runs | data deleted | — | — |

**Key insight:** Day 9 is in the gap between `data-retention:send-warnings` Day 1 (= sign-up day 8) and Day 15 (= sign-up day 22). The two systems are sequential by accident of timing — **no single user receives both a data retention warning AND a lifecycle email on the same day**.

### 13.4 Other overlap considerations

| Scenario | Risk | Mitigation |
|---|---|---|
| Active subscriber with `past_due` status + has feature-alert-eligible data | Could receive Campaign 5 (lapsed) AND a feature alert (e.g., savings rate) on the same morning | Campaign 5's "needs help" framing is compatible with concurrent feature alerts. Both pieces of information are useful. **No mitigation required.** |
| Churned subscriber within 30 days of churning + still in `notification_preferences` | Could receive Campaign 4 feedback email AND a stale feature alert if the alert command isn't filtering for `status='active'` | Verify in implementation: feature alert commands should filter by subscription state. **§10 open question added.** |
| User opts out of 1 lifecycle preference but stays opted in to others | They receive 4 lifecycle emails over their lifecycle, not 5 | This is the intended behaviour. Per-campaign granularity is the whole point of Q1's choice. |

### 13.5 Decisions captured from session 51 brainstorm

#### Q1 — Should the lifecycle engine honour notification preferences?

**Decision: YES, per-campaign granularity.** Add 5 boolean columns to `notification_preferences` (one per lifecycle campaign), each defaulting to `TRUE`. Surface as 5 line-by-line toggles in the user settings menu under a new "Lifecycle emails" section. No master switch — each toggle is independent.

**Rationale:** Lifecycle emails are promotional-feeling (especially Campaign 2's discount offer) and users should be able to opt out. Per-campaign granularity gives the user maximum control without master-switch UI complexity. Existing users get all 5 set to `TRUE` automatically via `ALTER TABLE ... DEFAULT TRUE` (§3 explains the migration).

**Implementation impact:** New migration, model updates, eligibility filter in every campaign, Vue settings component update, 5 new tests.

#### Q2 — Should any existing commands be folded into the lifecycle engine?

**Decision: NO. Leave existing commands alone.**

**Rationale:** The existing trial reminder, renewal reminder, and data retention warning commands work, are tested, and have their own dedup. Refactoring them into the lifecycle engine would touch tested production code without adding user-visible value. The lifecycle engine handles the **moments that nothing currently handles**: empty trialer outreach, engaged trialer conversion, cancellation feedback, churn feedback, lapsed recovery. Consolidation is a v2 conversation.

**Implementation impact:** None — preserves existing behaviour.

#### Q3 — Same-morning email count cap?

**Decision: (a) + (b) — no global cap, but the engine's internal "one lifecycle email per user per day" rule stands.**

**Rationale:** The existing systems already partition users by subscription state (active/trialing/expired/cancelled/past_due), so the realistic worst case for a single user receiving multiple emails on the same morning is small. Adding a global cap would require a new "global send log" that all commands write to — significant cross-cutting change for a problem that may not exist. Revisit if real data shows it's a problem.

**Implementation impact:** None — the engine already enforces single-lifecycle-email-per-day via the collision rules in §4.

#### Q4 — Time-of-day spread

**Decision: (b) — Move lifecycle engine to 08:30, 30 minutes ahead of the existing 09:00 cluster.**

**Rationale:** Lifecycle emails (especially Campaign 2's discount conversion) benefit from being the first thing in the user's morning inbox. Moving to 08:30 gets them out before the 09:00 transactional batch and any feature alerts (which run 09:15-10:30). The existing 08:00 daily insight is a push notification, not an email, so it doesn't compete for inbox attention.

**Implementation impact:** Single Kernel.php change (§6).

### 13.6 What the lifecycle engine does NOT touch

To be explicit:

- **Does NOT modify** `trials:send-reminders`, `subscriptions:send-renewal-reminders`, `data-retention:send-warnings`, `notifications:policy-renewals`, `protection:send-alerts`, `notifications:mortgage-rate-alerts`, `savings:send-alerts`, `estate:send-alerts`, `notifications:daily-insight`
- **Does NOT add** unsubscribe footers to the existing transactional or feature alert templates
- **Does NOT consolidate** any existing dedup tables (`trial_reminder_log`, `renewal_reminder_log`, `data_retention_email_log`) into `lifecycle_email_log`
- **Does NOT change** the existing `notification_preferences` columns or behaviour — only **adds** 5 new columns

The new engine is purely additive. If you disable it (`LIFECYCLE_ENGINE_ENABLED=false`), the rest of Fynla's email systems continue working exactly as they did before.

---

## End of design
