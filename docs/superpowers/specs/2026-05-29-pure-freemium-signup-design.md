# Pure Freemium Signup — Remove the Trial, Make Free a First-Class State

**Date:** 2026-05-29
**Status:** Design — approved shape, pending spec review
**Branch:** `pureFreemium` (off `dev`; normal `feature → dev → main` flow, **not** a hotfix)
**Supersedes:** `2026-05-16-sub-project-2-freemium-tier-model-design.md` §"Tier resolution" trial row (line 165: *"Trial of a new tier resolves to that tier for its 7-day duration, then falls back to free on expiry"*). SP2 deliberately kept a 7-day trial on-ramp; this design removes it.

## Problem

Fynla currently advertises a freemium tier model (`free / tier1 / tier2 / tier3`, enforced by `DbTierGate` with `PAYMENT_ENABLED=true`), but the **signup on-ramp is still a 7-day free trial of a paid plan**, and the **Free tier is not actually usable**:

1. **Registration starts a trial.** `AuthController::register` (~`app/Http/Controllers/Api/AuthController.php:556`) calls `TrialService::startTrial($user, 'standard', 'yearly')`, creating a `Subscription` with `status='trialing'` and `users.trial_ends_at`. New users are *trial-of-a-paid-plan*, not Free.
2. **Free = read-only lockout.** `CheckSubscription` (global middleware, `app/Http/Middleware/CheckSubscription.php:98-119`) only allows **writes** when `hasActivePlan() || onTrial()`. A Free user (no active paid sub, no trial) is blocked from all writes → `403 subscription_required`. Today this never bites because everyone is on a trial or active plan — but the moment users become Free it makes the app read-only for them.
3. **Free users get a data-deletion countdown.** Trial expiry (`TrialService::expireTrials`) sets `subscriptions.data_retention_starts_at`, starting a 30-day grace → `ExecuteGraceDeletions` purges the account. Under pure freemium, Free users must keep their data indefinitely.

CSJ decision (2026-05-29): move to **pure freemium** — new users start on the **Free tier immediately**, no trial, and upgrade to a paid tier when they choose. Trials are removed **entirely** (signup *and* winback). Existing in-flight trialing/trial-expired users are converted to Free on deploy. Full removal of the trial machinery.

## Goals

- New signups land on the **Free tier** (`users.tier='free'`), no `Subscription` row, fully able to use the app within free-tier limits.
- The **Free tier is a usable, first-class state**: writes allowed, capped by `DbTierGate`; no read-only lockout, no data-deletion countdown.
- The **trial concept is gone**: no signup trial, no winback "restart trial", no trial reminder emails/cron, no trial UI.
- Existing **trial-origin** users (trialing + never-paid expired) are migrated to Free **without losing data** (deletion countdown halted).
- Genuinely **paid-then-churned** users keep the existing churn/grace/data-retention behaviour.

## Non-Goals (YAGNI)

- **No new "free-user re-engagement" lifecycle campaigns** in this effort. We remove the trialer campaigns; building freemium nudges (e.g. "free user, here's what paid unlocks") is a separate future effort if wanted.
- **No change to the paid upgrade/checkout flow** (`PaymentController` create-order/tier-order, `PlanSelectionModal`, `CheckoutPage`). Upgrade-from-free stays as-is.
- **No tier-cap retuning.** `DbTierGate` / `TierConfigurationStore` free-tier caps are taken as already-correct.
- **No removal of grace-period / data-retention** machinery — it is retained for paid churn.
- **No enum migration** to drop the `'trialing'` status value (risky, low value). The value is retained but never written by new code.

## Design

### 1. Signup → Free (no trial)

`AuthController::register` (`:551-556`):
- **Remove** the `$plan` resolution + `$this->trialService->startTrial(...)` call.
- Create the user with `tier='free'` (the `users` row is already created at `:530-541`; add `'tier' => 'free'`). Do **not** set `users.plan` to a paid slug and do **not** set `trial_ends_at`.
- No `Subscription` row is created at signup. (`TierResolver::resolve` returns `'free'` for `tier='free'` with no subscription; `isGrandfatheredLegacyPaid` returns false because `tier` is an explicit tier key.)
- Consent recording (`:568-573`) is unchanged — AI-chat consent is still required for the onboarding journey.

Onboarding is unaffected: `AiChatController::startOnboarding` gates on AI-chat consent + `onboarding_completed` + feature flag + `is_preview_user`, never on a subscription/trial.

### 2. Make Free usable — rework `CheckSubscription`

`CheckSubscription::handle` (`:98-119`) currently: pass if `hasActivePlan() || onTrial()`; else read-only; else (writes) 403. Rework so the **Free tier is allowed to write**, with per-tier creation caps enforced downstream by `DbTierGate` at the store boundary:

- Resolve the user's tier via `TierResolver`.
- **Free tier (and any active tier):** allow through (writes permitted). `DbTierGate::canCreate` enforces free-tier caps at the store layer (already built — `app/Services/Tiers/DbTierGate.php:18-33`).
- **Churned paid users only** (had an active paid subscription that is now `cancelled`/`expired` and past `current_period_end`): retain the read-only + grace-period + `subscription_required` lockout exactly as today. This path is keyed on the presence of a (now-terminal) **paid** subscription, not on "absence of active plan".
- Net effect: "no subscription at all" = Free user = usable. "Had a paid sub, now lapsed" = existing churn behaviour.

`CheckFeatureAccess` (legacy `feature:X` plan-order gate) is left in place; with Free users resolving to `'student'`-default it is a no-op for them (the real gate is `DbTierGate`). It is **not** in scope to rip out here, but its `onTrial()` branch becomes dead (acceptable; noted for a later cleanup).

### 3. Free-tier representation

- A Free user has **`users.tier='free'` and no `Subscription` row.**
- `GET /payment/trial-status` is **repurposed in place** (same route, to avoid frontend churn) to return tier/subscription state: `{ tier, has_subscription, status?, plan?, next_renewal_date? }`. It returns **no trial fields**. Free users get `has_subscription=false, tier='free'`. A rename to `/payment/subscription-status` is an optional later cleanup, out of scope here.
- `TrialCountdownBanner` is removed; nothing renders a countdown.

### 4. Migration (deploy-time, data-safety critical)

A migration (or one-off command run on deploy) reconciles existing users. **Distinguish trial-origin from paid-churned by whether the user has any `completed` `Payment`.**

For each non-preview user:
- **Has a completed Payment** (genuinely paid at some point): **leave untouched** — their `Subscription` (active / cancelled / expired + grace) follows the existing paid path.
- **No completed Payment** (trial-origin: `trialing` or trial-`expired`): set `users.tier='free'`, `users.plan='free'`, clear `users.trial_ends_at`; **delete the trial `Subscription` row** (Free users have none) **after ensuring `data_retention_starts_at` is cleared** so no deletion is pending. These users keep all their data and become usable Free users.

Data-safety guard: the migration must **not** route any user into the `data_retention_starts_at` / deletion path. Verify post-migration that no trial-origin user has a pending grace deletion.

Prod snapshot at design time: 8 active, 32 expired, 0 trialing. csjones: 2 active, 13 expired, 3 trialing. The 32 prod "expired" must be split by completed-Payment before any conversion.

### 5. Full removal of trial machinery

Remove (in the plan, with tests updated):
- `TrialService::startTrial` and `restartTrial` (and their callers: `AuthController:556`, `LifecycleActionController:39`). Keep `expireTrials` only if still needed for any residual paid logic; otherwise fold cancelled-paid expiry into `expireCancelledSubscriptions` and delete `expireTrials`.
- `trials:send-reminders` cron (`Console/Kernel.php:20`) + `SendTrialReminderEmails` command + `TrialReminderMail` + `EndOfTrialMail`.
- Lifecycle trialer campaigns: `EmptyTrialerCampaign`, `EngagedTrialerCampaign`, `CancelledTrialerCampaign` + their mailables + the `restartTrial` magic-link action in `LifecycleActionController`. Keep `LapsedSubscriberCampaign` / `ChurnedSubscriberCampaign` (paid churn).
- Frontend: `TrialCountdownBanner.vue` + its render in `AppLayout.vue`; any `trialing` / `days_remaining` / trial-status consumers.
- `Subscription` trial-only members: `scopeTrialing`, `isTrialing`, `daysLeftInTrial`, `trialProgress`, `onTrial()` (User) once callers are gone.
- Plan `trial_days`: stop reading it; set seeder values to 0 (leave column or drop in a later cleanup).
- Admin metrics that count `trialing` (`UserMetricsService`, `AdminController`, `TrialBreakdown.vue`) — update to drop the trial dimension.

Keep: `trials:expire`→repurposed for cancelled-paid expiry, grace-period/data-retention (`isInGracePeriod`, `gracePeriodEndsAt`, `data-retention:send-warnings`, `accounts:execute-grace-deletions`) for paid churn.

## Data Flow (after)

```
Register → user.tier='free' (no Subscription)
  → onboarding (consent-gated) → app usable
  → every create: Store boundary → DbTierGate.canCreate(user,'free',count) → cap enforced
  → CheckSubscription: free tier → pass (writes allowed)
Upgrade → /payment/create-order (tier) → pay → Subscription status='active', user.tier=tierN
Paid churn → cancel → period end → expired + grace + (eventual) deletion  [unchanged]
```

## Testing Strategy

- **Unit:** `TierResolver` (free), `DbTierGate::canCreate` at/over free caps, reworked `CheckSubscription` decision table (free=write-allowed; paid-churned=read-only/grace/403), migration classifier (completed-Payment → keep vs convert).
- **Feature:** register → assert `tier='free'`, no Subscription, no `trial_ends_at`; free user can create up to the free cap and is blocked at cap+1 (`DbTierGate`, not `CheckSubscription`); free user is **not** read-only; paid-churned user still hits grace/lockout.
- **Migration test:** seed trialing + trial-expired + paid-churned + paid-active; run migration; assert trial-origin → free with `data_retention_starts_at` null and data intact; paid users untouched.
- **Browser (csjones sandbox):** fresh register → land on dashboard as Free, **no trial banner**; create data within limits; hit a free cap → upgrade prompt; complete a sandbox upgrade → tier raised. Per CLAUDE.md browser-testing law.
- **Regression:** confirm no route returns `subscription_required` for a Free user mid-onboarding/normal use.

## Risks

- **Data deletion (highest):** a mis-classified trial-origin user left with `data_retention_starts_at` set would be purged by `ExecuteGraceDeletions`. Mitigation: explicit completed-Payment classifier + post-migration assertion that no Free user has a pending deletion; dry-run on csjones first.
- **CheckSubscription rework breadth:** it's global middleware on all API routes; a wrong condition could lock out or over-expose. Mitigation: decision-table unit tests + browser regression of a Free user's full journey.
- **Hidden trial-status consumers:** frontend/admin code reading `trialing`/`days_remaining`. Mitigation: the removal inventory in §5 + grep sweep in the plan.
- **PAYMENT_ENABLED interaction:** both gates short-circuit when false. Prod/csjones are `true`, so the rework is live; verify behaviour under both flag states.

## Rollout

`feature → dev → main`. Build + deploy to csjones, run the migration as a **dry-run then live** on the staging DB, browser-test the full Free journey, then release `dev → main` and run the migration on prod with the data-safety assertion. No prod hotfix.
