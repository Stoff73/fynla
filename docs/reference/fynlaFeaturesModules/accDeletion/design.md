# Account Deletion Rework — Design Spec

> **Date:** 2026-05-07
> **Branch:** `accountDeletionRework` (off `dev`)
> **Owner:** CSJ
> **Companion docs:**
> - `accDeletion.md` (in this folder) — audit of the current broken implementation
> - To follow: implementation plan via `superpowers:writing-plans`

---

## 1. Problem statement

Today, "deleting an account" in Fynla actually deletes user data from the database. There are two divergent code paths:

- **Settings → Privacy → Delete** uses `DataErasureService`, which **hard-deletes** the user row and all related financial records. The audit also identified a foreign-key bug on `life_events.joint_owner_id` that will silently block this whole path for any user who is the joint owner of a spouse's life event.
- **Trial expiry + 30-day grace + retention overlay "Delete & Start Again"** uses `DataPurgeService`, which **soft-deletes the user but scrubs all PII** and **hard-deletes all financial data**, anonymising audit logs.

Neither is acceptable for a UK-regulated firm. FCA COBS 11.5 (record-keeping), HMRC retention rules, and AML obligations require Fynla to retain client records — including identifying information, transaction history, and audit trails — for a minimum period (typically 5–7 years post-relationship-end). Both code paths today destroy data that must be retained.

Separately, the user experience needs three deletion entry points to behave consistently:
1. Settings → Privacy → Delete (manual)
2. Trial / cancelled-subscription auto-expiry after the 30-day grace window (system-initiated)
3. "Delete All Data & Start Again" CTA on the retention overlay shown during the grace window (manual but post-expiry)

And users must be able to return: if a previously-deleted user logs in or registers with the same email, they should be offered restoration of their previous state rather than blocked.

## 2. Goals

- **G1.** No user data is ever destroyed by a user-facing deletion action. Account "deletion" is a soft-delete that retains all rows, fields, and files.
- **G2.** A single service handles all three deletion trigger paths. Differentiation is metadata-only (reason + source).
- **G3.** When a user has paid for a period that has not yet ended, deletion is *scheduled* for the end of that period. The user retains full access until then, is notified at scheduling, reminded 7 days and 1 day prior, and can cancel the scheduled deletion at any time before it executes.
- **G4.** A returning user (login or re-register with a previously-deleted email) is offered restoration. Restoration brings them back to their previous state with all financial data intact; subscription is not auto-resumed.
- **G5.** A separate retention-purge cron runs only after the legal retention period has elapsed (default 7 years). This is the *only* code path that ever hard-deletes user data.
- **G6.** The pre-existing `life_events.joint_owner_id` foreign-key bug is fixed as part of this rework.

## 3. Non-goals

- **NG1.** Changing the 30-day grace period after trial / subscription expiry. Existing behaviour is correct.
- **NG2.** Changing the verification wizard in `PrivacySettings.vue` (2FA / email code / typed phrase). Keep as-is, just repoint the final action.
- **NG3.** Moving payment / subscription record retention. Subscriptions and payments stay where they are, soft-deletable via cascade only at the eventual hard-purge.
- **NG4.** Implementing GDPR data export changes. Already covered by `DataExportService`.
- **NG5.** Adding a "fresh start with same email" path. One person = one record.
- **NG6.** Building a regulator-facing data-extraction tool. Out of scope; we just need the data to *exist*.

## 4. Lifecycle states

A user account moves through these states:

```
                                 ┌─────────────┐
                                 │   ACTIVE    │
                                 │ (deleted_at │
                                 │   IS NULL)  │
                                 └──────┬──────┘
                                        │
                  ┌─────────────────────┼─────────────────────┐
                  │ user_requested      │                     │ trial_expired /
                  │ AND active paid sub │                     │ cancelled_grace_ended /
                  │                     │                     │ user_requested w/o paid sub
                  ▼                     │                     ▼
         ┌──────────────────┐           │           ┌───────────────────┐
         │   SCHEDULED      │           │           │     DELETED       │
         │ (deletion_       │  cron     │           │  (deleted_at SET, │
         │  scheduled_for   ├───────────┘           │   reason+source   │
         │  IS NOT NULL,    │                       │   recorded,       │
         │   deleted_at     │                       │   tokens revoked, │
         │   IS NULL)       │                       │   sub cancelled)  │
         └────────┬─────────┘                       └─────────┬─────────┘
                  │                                           │
                  │ user cancels                              │ retention period
                  │ scheduled deletion                        │ elapses (default
                  ▼                                           │ 7 years)
              (back to ACTIVE)                                ▼
                                                     ┌───────────────────┐
                                                     │      PURGED       │
                                                     │ (hard-delete; all │
                                                     │  rows + files     │
                                                     │  removed)         │
                                                     └───────────────────┘
```

**State invariants:**

| State | `deleted_at` | `deletion_scheduled_for` | `deletion_reason` | Can log in? | Sees app? |
|-------|--------------|--------------------------|-------------------|-------------|-----------|
| ACTIVE | null | null | null | yes | yes (full) |
| SCHEDULED | null | non-null, future | non-null | yes | yes (full, with scheduled-deletion banner) |
| DELETED | non-null | null | non-null | no (login returns restorable response) | no |
| PURGED | non-null (until row hard-deleted) | n/a | n/a | no | no |

`SCHEDULED` is a substate of `ACTIVE` — the user is logged in normally and uses the app. The scheduled date is just a future event that has not yet fired.

## 5. The three trigger paths

```
┌─────────────────────────────────────────────────────────────────────────┐
│ Path 1: Settings → Privacy → Delete                                     │
│   (PrivacySettings.vue 3-step wizard)                                   │
│   → POST /api/auth/gdpr/erasure/execute                                 │
│                                                                         │
│   If user has active paid sub w/ current_period_end > NOW:              │
│     → AccountDeletionService::scheduleDeletion(user,                    │
│           reason='user_requested',                                      │
│           source='settings_privacy',                                    │
│           executes_at=current_period_end)                               │
│   Else (free / trial / expired):                                        │
│     → AccountDeletionService::deleteAccount(user,                       │
│           reason='user_requested',                                      │
│           source='settings_privacy')                                    │
├─────────────────────────────────────────────────────────────────────────┤
│ Path 2: Trial / cancelled-sub grace ends (30 days after expiry)         │
│   (Replaces today's PurgeExpiredUserData cron)                          │
│   → New: accounts:execute-grace-deletions cron, daily                   │
│                                                                         │
│   For each user where subscription.status=expired AND                   │
│   data_retention_starts_at <= NOW - 30 days:                            │
│     → AccountDeletionService::deleteAccount(user,                       │
│           reason='trial_expired' | 'subscription_cancelled_grace_ended',│
│           source='auto_expiration_grace')                               │
├─────────────────────────────────────────────────────────────────────────┤
│ Path 3: DataRetentionOverlay "Delete & Start Again" (during grace)      │
│   → POST /api/payment/delete-all-data (kept; repointed)                 │
│                                                                         │
│   Already in grace (sub status=expired, current_period_end < NOW),      │
│   so always immediate:                                                  │
│     → AccountDeletionService::deleteAccount(user,                       │
│           reason='user_requested',                                      │
│           source='expiration_modal')                                    │
└─────────────────────────────────────────────────────────────────────────┘
```

Path 1 is the only path that can result in scheduling. Paths 2 and 3 always delete immediately because the user has no remaining paid period.

**Definition: "active paid subscription with remaining period."** A subscription row exists with `status = 'active'` (not `trialing`, `expired`, `cancelled`, or `past_due`) AND `current_period_end > NOW`. Trial users, free users, and users in any post-expiry state delete immediately. Cancelled-but-not-yet-expired subscriptions (where `status = 'cancelled'` but `current_period_end > NOW`) also schedule, because the user has paid through that date.

## 6. Schema changes

### 6.1 New columns on `users` (one migration)

```php
$table->timestamp('deletion_scheduled_for')->nullable()->after('trial_ends_at');
$table->enum('deletion_reason', [
    'user_requested',
    'trial_expired',
    'subscription_cancelled_grace_ended',
    'admin_initiated',
    'legacy_purged',
])->nullable()->after('deletion_scheduled_for');
$table->string('deletion_source', 50)->nullable()->after('deletion_reason');
$table->timestamp('restored_at')->nullable()->after('deletion_source');
$table->timestamp('purge_eligible_at')->nullable()->after('restored_at');

$table->index('deletion_scheduled_for');
$table->index('purge_eligible_at');
```

Notes:
- `deleted_at` already exists via the `SoftDeletes` trait.
- `deletion_scheduled_for` is the wall-clock time at which `accounts:execute-scheduled-deletions` will fire the actual deletion. Null = no pending deletion.
- `deletion_reason` is set at scheduling time (path 1 with paid sub) OR at deletion time (paths 2 / 3 / path 1 immediate). It persists through to PURGED state for audit.
- `deletion_source` is the entry point. Same persistence.
- `restored_at` records the most recent restoration (null = never restored). Multiple restorations would overwrite this; the audit log retains the full history.
- `purge_eligible_at` is computed at deletion time as `deleted_at + retention_period_years`. Indexed so the monthly hard-purge cron can query efficiently.
- `legacy_purged` reason captures users that have already been soft-deleted-with-PII-scrubbed by today's `DataPurgeService`. They are not restorable.

### 6.2 Foreign-key fix on `life_events.joint_owner_id`

Per the audit doc, this FK has no `onDelete` clause, defaulting to RESTRICT. Although our new primary path soft-deletes (so the FK is rarely exercised), the eventual hard-purge cron WILL hit it. New migration:

```php
Schema::table('life_events', function (Blueprint $table) {
    $table->dropForeign(['joint_owner_id']);
    $table->foreign('joint_owner_id')
        ->references('id')->on('users')
        ->nullOnDelete();
});
```

This brings `life_events` in line with the convention documented in `database/CLAUDE.md`.

### 6.3 Fix `DataPurgeService::getDeletionOrder()` schema mismatch (path 3 current 500)

`DataPurgeService::getDeletionOrder()` lists `data_retention_email_log` and `renewal_reminder_log` for `DELETE WHERE user_id = ?` cleanup, but neither table has a `user_id` column — they only have `subscription_id`. MySQL throws `Unknown column 'user_id' in 'where clause'`, the wrapping transaction rolls back, and `PaymentController::deleteAllData` returns the generic "Failed to delete data" 500. **This is the root cause of the error CSJ has hit on path 3 in the current implementation** (verified 2026-05-07 against the live schema).

The rewrite implicitly fixes this for the user-facing path: `AccountDeletionService::deleteAccount` (the new path 3 target) does not touch any financial or log table, so the bug never fires. Both rows cascade from `subscriptions.id` anyway, so they're correctly cleaned up at the eventual hard-purge.

For the renamed `RetentionPurgeService` (the eventual 7-year cron), remove these two table names from `getDeletionOrder()`. They will be cleaned up by the FK cascade when `subscriptions` is deleted at the end of the order. While in this method, audit the rest of the list against current schema — at the time of writing, the other 57 entries all carry a `user_id` column. The implementation plan should include a one-shot schema sanity check to confirm before shipping.

### 6.4 No new tables

Audit trail uses existing `audit_logs` with new action codes:
- `ACTION_ACCOUNT_DELETION_SCHEDULED`
- `ACTION_ACCOUNT_DELETION_CANCELLED`
- `ACTION_ACCOUNT_DELETED`
- `ACTION_ACCOUNT_RESTORED`
- `ACTION_ACCOUNT_PURGED`

(Plus the existing `ACTION_ERASURE_REQUESTED` / `ACTION_ERASURE_COMPLETED` retained for backward audit compatibility.)

## 7. Services

### 7.1 New: `App\Services\Account\AccountDeletionService`

```php
class AccountDeletionService
{
    public function scheduleDeletion(
        User $user,
        string $reason,
        string $source,
        Carbon $executesAt
    ): void;

    public function cancelScheduledDeletion(User $user): void;

    public function deleteAccount(
        User $user,
        string $reason,
        string $source
    ): void;

    public function restoreAccount(User $user): void;

    public function canBeRestored(User $user): bool;

    public function isScheduledForDeletion(User $user): bool;
}
```

`scheduleDeletion` (transactional):
1. Refuse if user is already DELETED or already SCHEDULED.
2. Audit log `ACCOUNT_DELETION_SCHEDULED` with reason+source+executesAt.
3. Set `deletion_scheduled_for`, `deletion_reason`, `deletion_source`.
4. Queue `AccountDeletionScheduledEmail`.

`cancelScheduledDeletion` (transactional):
1. Refuse if user is not currently SCHEDULED.
2. Audit log `ACCOUNT_DELETION_CANCELLED`.
3. Null out `deletion_scheduled_for`, `deletion_reason`, `deletion_source`.
4. Queue `AccountDeletionCancelledEmail`.

`deleteAccount` (transactional):
1. Audit log `ACCOUNT_DELETED` with reason+source+actor.
2. Revoke all `personal_access_tokens` for this user.
3. Delete all `user_sessions` for this user.
4. Cancel any active `subscriptions` row (status → `cancelled` if `active`, leave alone if `expired`/`trialing`/`cancelled` already). Do not delete the row.
5. Set `users.deleted_at = NOW`. Persist `deletion_reason` / `deletion_source`. Set `purge_eligible_at = NOW + retention_period_years` (read from `config('retention.account_years')`, default 7). Null out `deletion_scheduled_for` (whether or not this came from a scheduled state).
6. Queue `AccountDeletionConfirmationEmail`.
7. **Touch nothing else.** No financial data, no audit logs, no joint-owner cleanup, no PII scrubbing.

`restoreAccount` (transactional):
1. Refuse if user is PURGED (purge_eligible_at < NOW AND row hard-deleted) — won't reach this branch because the row is gone.
2. Refuse if `deletion_reason = 'legacy_purged'`.
3. Audit log `ACCOUNT_RESTORED`.
4. Set `deleted_at = null`, `restored_at = NOW`. Keep `deletion_reason` / `deletion_source` for historical record (do not null). Null `purge_eligible_at`.
5. Issue a fresh Sanctum token for the user.
6. Queue `AccountRestorationConfirmationEmail`.
7. Frontend redirects to `/subscription/select` (subscription is *not* auto-resumed).

### 7.2 Renamed: `App\Services\Account\RetentionPurgeService`

The current `App\Services\Payment\DataPurgeService` becomes `App\Services\Account\RetentionPurgeService`. Public surface:

```php
class RetentionPurgeService
{
    public function purgeUser(User $user): array;
}
```

Internals stay almost identical to today's `DataPurgeService::purgeUserData()` — it already does what we want at the eventual hard-purge: clean reverse references, delete document files, delete polymorphic holdings, delete in dependency order, anonymise audit logs, soft-delete user with PII scrub. The difference is *when* it runs: only after `purge_eligible_at` has passed, never on user request.

The `cleanupReverseReferences` step inside it handles the joint-owner edge case at purge time (sets `joint_owner_id` to null on other users' records).

### 7.3 Deleted: `App\Services\GDPR\DataErasureService`

Obsolete. Remove the file. Repoint `GDPRController::executeErasure` to `AccountDeletionService`. Repoint `GDPRController::requestErasure` / `confirmErasure` to remain as bookkeeping (the `ErasureRequest` row is still useful as a verification-flow nonce store) but `processErasure` becomes a thin pass-through.

### 7.4 Modified: `App\Services\Payment\TrialService`

`expireTrials()` and `expireCancelledSubscriptions()` retain their current behaviour (mark sub status, set `data_retention_starts_at`). They do NOT directly trigger deletion — that's the new grace-deletion cron's job.

## 8. Routes

| Route | Method | Status | Notes |
|---|---|---|---|
| `/api/auth/gdpr/erasure/execute` | POST | **REPOINT** | Calls `AccountDeletionService::scheduleDeletion` or `deleteAccount` based on subscription state |
| `/api/auth/gdpr/erasure/cancel-scheduled` | POST | **NEW** | Calls `AccountDeletionService::cancelScheduledDeletion` |
| `/api/payment/delete-all-data` | POST | **REPOINT** | Calls `AccountDeletionService::deleteAccount` (always immediate; user is in grace) |
| `/api/auth/login` | POST | **MODIFY** | Detects trashed user + correct password → returns `account_deleted_restorable: true` instead of normal token |
| `/api/auth/register` | POST | **MODIFY** | Detects trashed email → returns `account_deleted_restorable: true` (requires user to verify password to confirm identity before restoration) |
| `/api/auth/restore` | POST | **NEW** | Restoration endpoint, takes a short-lived restoration token issued by login or register |

`PreviewWriteInterceptor::EXCLUDED_ROUTES` (per CLAUDE.md rule #8) gains:
- `/api/auth/restore`
- `/api/auth/gdpr/erasure/cancel-scheduled`

## 9. Auth flow changes

### 9.1 Login flow with trashed users

```
POST /api/auth/login { email, password }
  ↓
LoginController::login()
  ↓
$user = User::withTrashed()->where('email', $email)->first();
  ↓
If $user && $user->trashed():
  - if !Hash::check($password, $user->password) → return generic 401 (do not leak that account exists)
  - if $user->deletion_reason === 'legacy_purged' → return generic 401 (cannot be restored; password is randomised by purge anyway)
  - else → return 200 {
      "account_deleted_restorable": true,
      "deleted_at": "2026-03-12T...",
      "deletion_reason": "user_requested",
      "deletion_source": "settings_privacy",
      "restoration_token": "<short-lived signed JWT, 5 min>",
      "first_name": "<for greeting>"
    }
  ↓
Else if $user is active and SCHEDULED:
  - Normal login proceeds (user is active, just has a future deletion date)
  - Returned user payload includes `deletion_scheduled_for` so frontend can show banner
  ↓
Else: standard login flow (existing 2FA / email-code)
```

### 9.2 Register flow with trashed email

```
POST /api/auth/register { email, password, ... }
  ↓
RegisterController::register()
  ↓
$user = User::withTrashed()->where('email', $email)->first();
  ↓
If $user && $user->trashed() && $user->deletion_reason !== 'legacy_purged':
  - return 200 {
      "account_deleted_restorable": true,
      "requires_password_verification": true,
      "deleted_at": ...,
      "deletion_reason": ...,
      "first_name": ...
    }
  - Frontend shows RestoreAccountModal asking for the password (the original password is intact since we don't scrub on soft-delete)
  - Frontend then POSTs to /api/auth/restore/check { email, password } to verify and receive a restoration_token
  ↓
Else if $user (active or legacy_purged): standard "email in use" 422 error
  ↓
Else: standard registration
```

### 9.3 Restoration endpoint

```
POST /api/auth/restore { restoration_token }
  ↓
Verify token signature + not-expired + matches a trashed user
  ↓
AccountDeletionService::restoreAccount($user)
  ↓
Return 200 {
  "token": "<new Sanctum token>",
  "user": { ... },
  "redirect_to": "/subscription/select"
}
```

### 9.4 Pre-check endpoint (registration path)

```
POST /api/auth/restore/check { email, password }
  ↓
Locate withTrashed user by email
  ↓
If not trashed or wrong password: return 401 generic
  ↓
Return 200 {
  "restoration_token": "<short-lived signed JWT, 5 min>"
}
```

## 10. UI changes

### 10.1 `PrivacySettings.vue` (Settings → Privacy tab)

Three states based on the user's deletion status:

**State A — Active, no scheduled deletion:**
- Show existing "Delete Your Data or Account" button + 3-step wizard.
- Wizard step 3 confirmation copy depends on whether scheduling will apply:
  - If `subscription.current_period_end > NOW`: "Your account will be scheduled for deletion on [date], the end of your current paid period. You'll keep full access until then. We retain your records for 7 years for regulatory compliance, after which they are permanently deleted. You can cancel the scheduled deletion or restore your account during this period."
  - Else (free/trial/expired): "Your account will be deactivated immediately. We retain your records for 7 years for regulatory compliance. You can restore your account at any time within that period by logging in."

**State B — Active, scheduled for deletion:**
- Replace the wizard button with a banner: "⚠ Your account is scheduled for deletion on [date]. Your records will be retained for 7 years after that for regulatory compliance."
- Below the banner: "Cancel scheduled deletion" button.
- Confirmation modal on click: "This will cancel your scheduled deletion. Your account will remain active." → POST `/api/auth/gdpr/erasure/cancel-scheduled`.

**State C — Deleted user:** unreachable here (deleted users can't access settings).

### 10.2 `DataRetentionOverlay.vue` (grace-period overlay)

- Existing UI mostly unchanged.
- Update copy on confirmation step: "This will deactivate your account immediately. Your records are retained for 7 years for regulatory compliance, then permanently deleted. You can restore your account at any time within that period by logging in."
- After successful deletion, redirect to `/login` (not `/`) so the restore path is one click away.

### 10.3 New: `RestoreAccountModal.vue`

Triggered by login or register when API returns `account_deleted_restorable: true`. Shows:
- Greeting using the returned `first_name`
- Deletion date and reason in plain English
- Two buttons: "Restore my account" (primary) / "Cancel" (secondary, returns to login/register)
- For register flow: includes a password input (required to verify identity before restoration)
- Submitting "Restore" calls `/api/auth/restore` with the token; on success redirects to `redirect_to`

### 10.4 `Login.vue`, `Register.vue`

Detect the `account_deleted_restorable: true` response shape and mount `RestoreAccountModal`. Pass through token + metadata.

### 10.5 Scheduled-deletion banner on dashboard

When the logged-in user's `deletion_scheduled_for` is non-null (state B), show a top-of-page banner across all authenticated views:
- "Your account is scheduled for deletion on [date] ([N] days). [Cancel scheduled deletion]"
- "Cancel scheduled deletion" deep-links to Settings → Privacy.

Component: `components/Account/ScheduledDeletionBanner.vue`. Mount inside `AppLayout.vue` under the existing `TrialCountdownBanner`.

### 10.6 Joint-owner display

For all surfaces that show a joint owner's name (Property cards, Savings cards, Investment cards, Goal cards, etc.), append a "(Deactivated)" inline badge when `joint_owner.deleted_at IS NOT NULL`. The card stays functional.

Surface list:
- `components/Property/PropertyCard.vue`
- `components/Savings/SavingsAccountCard.vue`
- `components/Investment/InvestmentAccountCard.vue`
- `components/Estate/Bequests/*` (where applicable)
- Any other component that calls `formatOwnershipType()` or shows a joint-owner name

This is a low-risk change; just a conditional badge.

## 11. Email notifications

| Mailable | Trigger | Window |
|---|---|---|
| `AccountDeletionScheduledEmail` | `scheduleDeletion()` | Immediate |
| `AccountDeletionReminder7Days` | Cron `accounts:send-deletion-reminders` | 7 days before scheduled deletion |
| `AccountDeletionReminder1Day` | Same cron | 24h before scheduled deletion |
| `AccountDeletionConfirmationEmail` | `deleteAccount()` | Immediate |
| `AccountDeletionCancelledEmail` | `cancelScheduledDeletion()` | Immediate |
| `AccountRestorationConfirmationEmail` | `restoreAccount()` | Immediate |

All Blade templates live under `resources/views/emails/account/` and use the master layout per the project's `email-template` skill conventions.

To prevent duplicate reminders on cron retries, store sent reminders in a small log (reuse the existing `data_retention_email_log` table — it already exists for similar purposes).

## 12. Cron jobs

### 12.1 New: `accounts:execute-scheduled-deletions`

```php
// app/Console/Commands/ExecuteScheduledDeletions.php
// Schedule: dailyAt('00:10')
// (after trials:expire at 00:05, before purge cron)

public function handle(AccountDeletionService $service): int
{
    $users = User::withTrashed()
        ->whereNull('deleted_at')
        ->whereNotNull('deletion_scheduled_for')
        ->where('deletion_scheduled_for', '<=', now())
        ->get();

    foreach ($users as $user) {
        $service->deleteAccount(
            $user,
            $user->deletion_reason,
            $user->deletion_source
        );
    }

    return Command::SUCCESS;
}
```

### 12.2 New: `accounts:send-deletion-reminders`

Daily at `00:20`. For each user with `deletion_scheduled_for` between (NOW + 6.5 days, NOW + 7.5 days) AND no `7day_reminder` log entry → send 7-day reminder + log. Same for 1-day window. Idempotent.

### 12.3 Replaced: `data-retention:purge-expired` → `accounts:execute-grace-deletions`

The current `PurgeExpiredUserData` command is renamed `accounts:execute-grace-deletions`. Behaviour change:
- It targets `subscriptions.status='expired'` AND `data_retention_starts_at <= NOW - 30 days` (same as today).
- For each: instead of calling `DataPurgeService::purgeUserData()`, calls `AccountDeletionService::deleteAccount(user, 'trial_expired'|'subscription_cancelled_grace_ended', 'auto_expiration_grace')`.
- Hard-deletes nothing.

### 12.4 New: `accounts:purge-after-retention`

Monthly (e.g. `monthlyOn(1, '02:00')`). For each user where `purge_eligible_at IS NOT NULL` AND `purge_eligible_at <= NOW`:
- Call `RetentionPurgeService::purgeUser($user)` (the only path that ever hard-deletes).
- Audit log `ACCOUNT_PURGED` (which itself becomes anonymised by the purge).

`config/retention.php`:
```php
return [
    'account_years' => env('ACCOUNT_RETENTION_YEARS', 7),
];
```

## 13. Restoration semantics — what the user gets back

After `restoreAccount()` succeeds:
- All financial records (goals, policies, pensions, investments, savings, properties, mortgages, business interests, chattels, family members, consents, life events, documents) — intact.
- User profile fields (name, DOB, NI number, address, employment, income, expenditure) — intact.
- Joint-owner links pointing back at this user from spouses' records — intact (we never broke them).
- Audit log history — intact.
- Subscription record — intact, in whatever status it held at deletion time (`cancelled` if the user came through path 1 with an active sub, `expired` if they came through paths 2/3 in grace). User must re-subscribe regardless.
- Sanctum tokens — fresh (issued at restore time).
- Sessions — fresh.
- AI chat history — intact (cascades preserved).

What is *not* restored:
- Subscription billing. The user picks a plan after restoration.
- A trial. They've already had one. (Edge case: if they were deleted before their original trial expired, do they get the remaining trial back? **Decision: no.** Simpler model: deletion is deletion; restore re-enters as a free user. They can purchase a paid plan if they want.)

## 14. Joint-owner edge case (spec)

Throughout the soft-deleted lifetime of a deleted user, joint-owner links from spouses' records remain pointing at the deleted user's row. The frontend shows "(Deactivated)" inline (per §10.6) but functionally the joint record is unaffected:
- Spouse continues to own their share at their existing `ownership_percentage`.
- Calculations involving the joint record use the full record, including the deactivated joint owner's data, but they don't appear in *that user's* views (because the user can't log in).
- If the spouse explicitly removes the joint owner via UI affordance, we set `joint_owner_id = null` on the spouse's record. The deactivated user's data is unaffected.

At the eventual hard-purge (years later), `RetentionPurgeService::cleanupReverseReferences()` nulls the `joint_owner_id` on spouses' records, completing the cleanup.

## 15. Migration of existing soft-deleted users

Today's `DataPurgeService` has already been run on some users (those whose 30-day grace expired before this rework ships). Their PII is scrubbed and financial data is deleted. They cannot be restored.

One-shot migration as part of this feature:
```sql
UPDATE users
SET deletion_reason = 'legacy_purged',
    deletion_source = 'auto_expiration_grace',
    purge_eligible_at = deleted_at  -- already eligible, will be removed by next purge cron
WHERE deleted_at IS NOT NULL
  AND deletion_reason IS NULL;
```

These users' login/register attempts will hit the `legacy_purged` branch in §9.1/9.2 and be told the account does not exist (effectively — they get a generic 401).

## 16. Sequencing of cron jobs

Daily cron schedule (in order of execution):

| Time | Command | Purpose |
|---|---|---|
| 00:05 | `trials:expire` (existing) | Mark expired trials/cancelled subs, set `data_retention_starts_at` |
| 00:10 | `accounts:execute-scheduled-deletions` (NEW) | Fire user-scheduled deletions whose date has passed |
| 00:15 | `accounts:execute-grace-deletions` (RENAMED) | Fire grace-period-ended deletions (replaces today's `data-retention:purge-expired`) |
| 00:20 | `accounts:send-deletion-reminders` (NEW) | Send 7-day and 1-day reminders for scheduled deletions |
| 02:00 (monthly, 1st) | `accounts:purge-after-retention` (NEW) | Hard-purge users past retention period |

Order matters: scheduled deletions fire before grace deletions, so a user who is both scheduled and past grace gets the explicit-action audit trail.

## 17. Testing strategy outline

This section enumerates what testing must cover; the implementation plan will produce the actual test files.

**Unit tests (Pest, `tests/Unit/Services/Account/`):**
- `AccountDeletionService::scheduleDeletion` — happy path, refuses if already scheduled, refuses if already deleted, audit log written, email queued.
- `AccountDeletionService::cancelScheduledDeletion` — happy path, refuses if not scheduled, audit log, email.
- `AccountDeletionService::deleteAccount` — happy path (each reason × source combination), audit log, email, tokens revoked, sessions deleted, subscription cancelled.
- `AccountDeletionService::deleteAccount` — verifies NO financial data is touched (assert counts before/after for goals, properties, audit_logs, etc.).
- `AccountDeletionService::restoreAccount` — happy path, refuses for legacy_purged, audit log, fresh token issued.
- `AccountDeletionService::restoreAccount` — verifies all data is intact (counts unchanged).
- `RetentionPurgeService::purgeUser` — happy path; verifies hard-delete (this is the only place we hard-delete).

**Feature tests (Pest, `tests/Feature/`):**
- Settings → Privacy → Delete with active paid sub → schedules. Subsequent login still works. Cron fires deletion on the scheduled date.
- Settings → Privacy → Delete with free/trial/expired sub → deletes immediately. Login post-deletion returns restorable response.
- Trial expires → 30 days pass → grace cron deletes. Login returns restorable response.
- Retention overlay CTA → deletes immediately. Login returns restorable response.
- Login with deleted user's email + correct password → restorable response with token.
- Login with deleted user's email + wrong password → generic 401 (no enumeration).
- Register with deleted user's email → restorable response.
- Restore endpoint with valid token → restores, fresh Sanctum token issued, all data intact.
- Cancel scheduled deletion → returns to active state, audit log, email.
- Joint-owner UI: spouse's property card shows "(Deactivated)" when joint owner is soft-deleted.
- `life_events.joint_owner_id` FK migration: spouse can be hard-purged without FK violation.

**Integration / browser tests (Playwright, per project rules):**
- Full Settings → Privacy → Delete journey for a paid user (schedules) → verifies banner appears → cancels → verifies banner gone.
- Full Login → RestoreAccountModal → restore → land on `/subscription/select`.
- Full Register-with-deleted-email → RestoreAccountModal (with password) → restore.

**Migration tests:**
- Migration runs cleanly on a DB with existing soft-deleted users.
- `legacy_purged` backfill correctly tags pre-existing soft-deleted records.

## 18. Out of scope / future work

- **Admin panel**: an admin-initiated deletion path (`source='admin_panel'`, `reason='admin_initiated'`). Will be added when the admin panel grows the affordance; the schema and service support it already.
- **Compliance / regulatory hold flag**: a `regulatory_hold_until` timestamp that prevents the eventual hard-purge for users under investigation. Add when compliance team requests it.
- **Restoration fee or cooldown**: not implementing in v1.
- **Self-service data download** before deletion: existing `DataExportService` already covers this.
- **Account merge** (if a user re-registers and wants to merge with a different existing account): explicitly out of scope.

## 19. Decisions (final, after CSJ review on 2026-05-07)

1. **No fresh-start option with same email.** One person = one record. Re-registration with deleted email forces restoration.
2. **Default retention period: 7 years.** Configurable via `ACCOUNT_RETENTION_YEARS` env var.
3. **Active subscription on deletion: schedule for end of paid period (proration via scheduling), with notification.** User receives an email at scheduling time, reminders 7 days and 1 day before, and a confirmation email at execution. User can cancel the scheduled deletion at any time before it fires. After execution, restoration does not auto-resume the subscription. Trial users and free users are deleted immediately (no paid period to honour).
4. **Joint owner UX: keep the link, show "(Deactivated)" badge.** Spouse can clear the link manually if they wish.
5. **Audit strategy: extend existing `audit_logs` with new action codes.** No new audit table.
6. **Existing `DataPurgeService`-deleted users: tag with `deletion_reason='legacy_purged'`. Not restorable. Will be hard-purged on next monthly retention cron.**
7. **Restoration does NOT auto-resume subscription.** User picks a plan after restore.
8. **Login of soft-deleted user requires correct password before revealing the restoration option.** Prevents email-enumeration leak.

## 20. Open risks

- **R1 — Subscription billing edge cases at deletion time.** If a payment is in flight (Revolut webhook arrives after `deleteAccount` runs), what happens? Mitigation: webhook handlers must check `User::withTrashed()` and accept payment events on deleted users, recording them against the (cancelled) subscription. Verified during implementation.
- **R2 — Email volume from reminders.** A user who schedules deletion 30+ days out gets 4 emails (scheduled, 7-day, 1-day, confirmation). Acceptable. Re-scheduling (cancel + re-schedule) re-sends all of them. Acceptable.
- **R3 — Restoration token storage.** Short-lived signed JWT-style tokens stored in Redis with 5-minute TTL. Token includes `user_id` and a nonce. Verify nonce on restore to prevent replay.
- **R4 — Race between `accounts:execute-scheduled-deletions` cron and concurrent user activity.** A user logged in at 00:09:59 stays logged in via Sanctum bearer token. Cron at 00:10:01 deletes, revokes tokens, kills sessions. User's next API call returns 401. Acceptable; matches the user's stated intent.
- **R5 — `legacy_purged` users have randomised passwords from `DataPurgeService`.** Login attempts will silently fail. Documented behaviour; no UI change needed.
- **R6 — `purge_eligible_at` on existing soft-deleted users in the migration.** They already had their data destroyed; the eventual purge cron will just clean up the stub user row. Verified safe.

## 21. Implementation order (high-level — detailed plan to follow via writing-plans)

Suggested phases:

1. **Schema + migrations** — users columns, life_events FK fix, legacy backfill.
2. **Core service** — `AccountDeletionService` + `RetentionPurgeService` rename + remove `DataErasureService`.
3. **Auth flow** — login / register / restore endpoints. Modify existing controllers; add new `RestoreController`.
4. **Cron jobs** — `accounts:execute-scheduled-deletions`, `accounts:send-deletion-reminders`, `accounts:execute-grace-deletions` (rename), `accounts:purge-after-retention`. Update Kernel.
5. **Email mailables + Blade templates** — six new templates.
6. **UI** — `RestoreAccountModal`, `ScheduledDeletionBanner`, `PrivacySettings.vue` updates, `DataRetentionOverlay.vue` updates, joint-owner badges.
7. **Wire trigger paths** — repoint `GDPRController::executeErasure` and `PaymentController::deleteAllData`.
8. **Tests** — unit, feature, browser per §17.
9. **Verification** — full Playwright journey through all three trigger paths and the restore flow on a real seeded user.

---

End of design.
