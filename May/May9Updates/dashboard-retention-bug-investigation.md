# Dashboard retention-flag bug — investigation note

**Date:** 2026-05-09 (autonomous autopilot, session 2)
**CSJTODO reference:** Bug 2 — "Dashboard reads retention-flagged data. After Delete My Data, Profile Completeness still reports non-zero Family/Finances %."
**Status:** Investigation complete. **Blocked on CSJ design decision before code.**

---

## What I found

### The data-only erasure path is intentionally minimal

`POST /api/auth/gdpr/erasure/execute` with `type='data'` runs:

```php
// app/Http/Controllers/Api/GDPRController.php:563-569
$user->update([
    'employment_status' => null,
    'salary' => null,
    'national_insurance_number' => null,
]);
$this->auditService->logGDPR(
    AuditLog::ACTION_ERASURE_COMPLETED,
    $user->id,
    ['type' => 'data_only']
);
```

That is the entire post-erasure side-effect. **No flag is set on the `users` row, no retention column is written, no rows in financial tables are touched.** The 7-year FCA retention spec preserves the records (correct per CSJTODO session 7's reframing — "Don't add a wipe").

### There is no canonical "data-erasure-requested" column

CSJTODO instructs to grep `data_retention|retention_starts_at|purge_eligible_at|regulatory_retention` against the canonical spec to find the column. I did:

| Column | Used for |
|---|---|
| `users.purge_eligible_at` | **Account** deletion only — `AccountDeletionService::deleteAccount` sets `now()->addYears(7)`. Cleared on restore. |
| `users.deletion_reason`, `deletion_source`, `deletion_scheduled_for`, `restored_at` | Account-deletion lifecycle — not data-only. |
| `subscriptions.data_retention_starts_at` | Subscription-grace-period tracking (used by `data-retention:send-warnings`) — orthogonal to user-initiated data erasure. |
| Audit row `event_type=gdpr action=erasure_completed metadata.type=data_only` | The only durable record that data-only erasure happened. |

**The canonical spec (`April/April24Updates/spec/00-canonical.md`) is about the Two-Fyn architecture, not retention.** It contains no retention-column guidance. CSJTODO's "find the canonical retention column" assumption does not match the codebase.

### `ProfileCompletenessChecker` queries financial tables directly

`app/Services/UserProfile/ProfileCompletenessChecker.php` checks completeness through methods like `hasIncome($user)`, `hasAssets($user)`, `hasProtectionPlans($user)`. Each queries the live tables (`Estate\Asset`, `Property`, `SavingsAccount`, `InvestmentAccount`, etc.). After data-only erasure these tables still hold the row, so `hasAssets` returns true and Family/Finances percentages stay non-zero — the exact symptom in CSJTODO.

Backend dashboard aggregators (`app/Services/Dashboard/DashboardAggregator.php`, `app/Services/Mobile/MobileDashboardAggregator.php`) read the same tables.

---

## Why this is blocked on a design decision

To "treat retention-flagged users as empty" we need a flag that:

1. Is durable (survives a fresh login / new session)
2. Is per-user (not per-row across all financial tables)
3. Cleanly distinguishes "data-only erasure" from "full account deletion" (which has its own purge timeline)
4. Gracefully handles "user later re-enters data" (does erasure-flag clear, or block re-entry?)

**There is no existing column that fits.** The audit log row qualifies as "the source of truth" but querying `audit_logs` on every dashboard read is N+1 risk and brittle.

The cleanest fix is a new column on `users`, but the design has open questions only CSJ can answer. The instinct to "just ship it" is wrong here — the CSJTODO entry is one line; the canonical spec is silent; and one of the questions (re-entry behavior) genuinely changes the column semantics.

---

## Proposed design (for CSJ review)

**Schema:**
```php
// new migration
$table->timestamp('data_erasure_requested_at')
    ->nullable()
    ->after('national_insurance_number')
    ->comment('Set when user runs Delete My Data (data-only). Hides financial rows from dashboard while preserving them for FCA 7-year retention. Cleared on next data write.');
```

Naming choice: `data_erasure_requested_at` (not `data_retention_started_at`) because retention is automatic for all users; what's special here is the **erasure request**.

**Flow:**

1. `executeErasure` (data path) sets `data_erasure_requested_at = now()` alongside the existing nullification.
2. `ProfileCompletenessChecker::checkCompleteness` adds a top guard:
   ```php
   if ($user->data_erasure_requested_at !== null) {
       return [
           'completeness_score' => 0,
           'is_complete' => false,
           'missing_fields' => [...],
           'all_checks' => array_map(fn ($c) => [...$c, 'filled' => false], $checks),
           'recommendations' => $this->generateRecommendations($checks, $isMarried),
           'is_married' => $isMarried,
           'data_erasure_requested' => true,
       ];
   }
   ```
3. `DashboardAggregator` and `MobileDashboardAggregator` add similar guards on hero metrics and module summaries (return zero / "—" instead of preserved values).
4. **Re-entry behavior (CSJ to confirm):** when a user adds a new asset / changes income after erasure, do we clear `data_erasure_requested_at` automatically? Two options:
   - **Auto-clear** — observer on first new write hits a financial table → `update(['data_erasure_requested_at' => null])`. Simpler UX, looser audit story.
   - **Manual clear** — explicit user action ("I want to start over") clears the flag. Tighter compliance posture, more friction.
5. UI: add a banner on dashboard when `data_erasure_requested = true` ("Your financial data is hidden after Delete My Data. Add new entries to bring the dashboard back.").

---

## Open questions for CSJ

1. **Column name** — `data_erasure_requested_at`, `dashboard_hidden_after`, `view_after_erasure_at`, or something else?
2. **Re-entry behavior** — auto-clear on first write, or manual?
3. **Scope of the flag** — should it ALSO hide:
   - Net worth panel?
   - Goals & life events?
   - Plans pages?
   - Insights / recommendations?
   The CSJTODO entry only mentions Profile Completeness, but consistency suggests yes-to-all.
4. **Banner copy** — should the dashboard explicitly tell the user "data hidden after Delete My Data"? Or silently zero out so the user feels they have a fresh slate?
5. **Test user** — CSJTODO says "re-test on `chris+restoretest4@fynla.org`" but session 7 cleaned up the prior testers. Probably need a fresh `chris+erasuretest1@fynla.org` for QA.

---

## Why I did not just ship a guess

Per autonomous autopilot rules: *"If you hit a blocker that genuinely requires CSJ's input (e.g. credentials, a destructive action, a path the handover explicitly defers), surface it concisely and proceed with whatever investigative work is unblocked while waiting."*

The column-name and re-entry questions are not "I could pick either" — they shape the audit story (FCA-relevant). The wider-scope question (only Profile Completeness, or all dashboard widgets?) materially affects the PR size. Better to get a 5-minute CSJ ack than to ship the wrong shape and have to revert.

I have NOT written code, NOT opened a PR, and NOT modified any DB rows. Nothing on disk has changed except this note.

---

## What I'd do once CSJ answers

1. Migration with the agreed column name + comment.
2. Update `GDPRController::executeErasure` data path.
3. Add `User::hasRequestedDataErasure(): bool` method.
4. Gate `ProfileCompletenessChecker`, `DashboardAggregator`, `MobileDashboardAggregator` (and any wider widgets per Q3).
5. Add observers to clear the flag on first new write to financial tables (per Q2 if "auto-clear" wins).
6. Pest tests:
   - GDPRController feature test confirms column gets set on data-only path
   - ProfileCompletenessChecker unit test confirms post-flag returns 0%
   - DashboardAggregator integration test confirms hero metrics zero out
7. Browser verification on csjones with a fresh test user.
8. PR feature → dev.

Estimated time once unblocked: ~3 hours including tests + browser verify.
