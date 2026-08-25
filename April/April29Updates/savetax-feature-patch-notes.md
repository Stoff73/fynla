# Patch Notes — SaveTax Campaign + Channel Attribution

**Release date:** 29 April 2026
**Branch:** `feature/fyn-persona-split`
**Session:** 112
**Commits in this release:**

| SHA | Headline |
| --- | --- |
| `d45f6bf` | `/savetax` route → dedicated SaveTaxCampaignPage |
| `d910833` | Backend `campaign_map` config + controller dispatch |
| `38bac32` | Campaign welcome at `base_personal` |
| `2a34ee6` | Frontend `?from=` wire-through (Register → Dashboard → onboarding) |
| `fd9fc26` | Live tax allowances from TaxConfigService + 2 typo fixes |
| `c0f0a99` | `?utm_source=` → `users.signup_source` channel attribution |

---

## What's new

### 1. SaveTax campaign landing page (`/savetax`)

A dedicated marketing page replacing the generic `CampaignPage` template for `/savetax` only. Contains:

- **Hero** — "Save more on tax" gradient banner.
- **Allowances table for the active tax year** — Income column (Personal Allowance, Savings Allowance, Starting Rate for Savings, Marriage Allowance) and Investment & Cash column (ISA, CGT, Dividend, Pension Annual Allowance). Values are **fetched live** from `TaxConfigService` so they auto-update when the seeded `tax_configurations` row changes.
- **Tax year heading** — also dynamic, reads from `getTaxYear()`.
- **"Could this be you?"** — four example cards (Non-working spouse, High income tax trap, Investment Accounts, NICs / salary sacrifice).
- **Five CTAs** — all link to `/register?from=savetax` so the campaign attribution flows through registration into the onboarding chat.

Other campaign routes (`/biggerpension`, `/paymortgage`, `/managedebt`) are unchanged — they remain on the generic `CampaignPage`.

### 2. Campaign onboarding wire-through

Visitors who click any `/savetax` CTA → register → MFA → land on the dashboard with Fyn auto-opened to a campaign-specific welcome:

> "Hi {first_name}, welcome to Fynla — I'll help you build your tax-saving strategy. Let's start with the basics: what's your date of birth, and are you single, married, in a civil partnership, divorced, or widowed?"

Backend persists the campaign attribution on the user record (`users.onboarding_fyn_path = 'campaign'`, `onboarding_fyn_selection = 'savetax'`, `onboarding_fyn_step = 'base_personal'`) so resume cases continue from the right place.

**Side benefit:** the same wire-through closes a long-standing gap (BS-05 / PSP-LS) where the `?from=protection` / `?from=retirement` / `?from=goals` / `?from=budgeting` journey CTAs were silently dead because the SSE start request hardcoded `body: '{}'`. Every life-stage entry point now actually reaches the onboarding director.

### 3. Live tax allowances (no more rotting hardcoded numbers)

New unauthenticated endpoint:

```http
GET /api/public/tax-allowances
```

Returns the eight headline display values, the active tax year, and key thresholds — all sourced from the seeded `TaxConfiguration`. 1-hour cache keyed on tax year.

Response shape:

```json
{
  "tax_year": "2026/27",
  "income_allowances": [
    { "key": "personal_allowance", "label": "Personal Allowance", "note": "Tax-free income each year", "amount": 12570 },
    { "key": "savings_allowance", "label": "Savings Allowance", "note": "Basic-rate taxpayers", "amount": 1000 },
    { "key": "starting_rate_for_savings", "label": "Starting Rate for Savings", "note": "If non-savings income < £17,570", "amount": 5000 },
    { "key": "marriage_allowance", "label": "Marriage Allowance", "note": "Transferable to spouse", "amount": 1260 }
  ],
  "investment_allowances": [
    { "key": "isa_allowance", "label": "ISA Allowance", "note": "Tax-free savings & investing", "amount": 20000 },
    { "key": "cgt_allowance", "label": "CGT Allowance", "note": "Capital gains exempt amount", "amount": 3000 },
    { "key": "dividend_allowance", "label": "Dividend Allowance", "note": "Tax-free dividend income", "amount": 500 },
    { "key": "pension_annual_allowance", "label": "Pension Annual Allowance", "note": "Tax-relievable contributions", "amount": 60000 }
  ],
  "thresholds": {
    "hicbc_threshold": 100000,
    "starting_rate_for_savings_income_limit": 17570
  }
}
```

The page falls back gracefully to the previously hardcoded values if the request fails — a marketing page must never blank out.

**Seeder change:** `marriage_allowance.amount = 1260` was previously missing from the `income_tax` block — added so the page can read it.

### 4. Marketing-channel attribution (`users.signup_source`)

Every new user record now carries an optional channel-of-origin string, captured from a `utm_source` parameter on the URL the user first lands on. Use it to measure campaign ROI — which social platform actually converts.

- **Allowed values:** `linkedin`, `facebook`, `instagram`, `tiktok`, `x`, `youtube`
- **Capture mechanism:** sessionStorage (no cookie — no GDPR consent prompt required)
- **Attribution model:** first-touch (existing values are not overwritten by subsequent landings within the same session)
- **Persistence:** flows through the registration form → `pending_registrations.signup_source` → copied to `users.signup_source` when MFA verifies

Schema additions:

```sql
ALTER TABLE users                  ADD COLUMN signup_source VARCHAR(32) NULL AFTER referral_code;
ALTER TABLE pending_registrations  ADD COLUMN signup_source VARCHAR(32) NULL AFTER referral_code;
```

A non-allowlisted value is rejected with a 422 validation error — keeps the column clean for analytics.

### 5. Two small typo fixes on `/savetax`

- Example card title "General Investment Accounts" → **"Investment Accounts"**
- Example body figure £3,500 → **£3,000** (matched to the seeded CGT annual exempt amount)

---

## How to use the channel-attribution feature

### URL recipe

When you (or anyone managing Fynla's social presence) post a link to a Fynla page on a social platform, append `utm_source=<platform>` so the resulting signups can be credited.

| Platform | URL pattern |
| --- | --- |
| LinkedIn | `https://fynla.org/savetax?utm_source=linkedin` |
| Facebook | `https://fynla.org/savetax?utm_source=facebook` |
| Instagram | `https://fynla.org/savetax?utm_source=instagram` |
| TikTok | `https://fynla.org/savetax?utm_source=tiktok` |
| X / Twitter | `https://fynla.org/savetax?utm_source=x` |
| YouTube | `https://fynla.org/savetax?utm_source=youtube` |

The same pattern works for **any** Fynla URL, not just `/savetax`. Examples:

| Posting location | Posted URL |
| --- | --- |
| LinkedIn post linking to the homepage | `https://fynla.org/?utm_source=linkedin` |
| Instagram bio link to pricing | `https://fynla.org/pricing?utm_source=instagram` |
| TikTok video description for retirement journey | `https://fynla.org/?utm_source=tiktok&from=retirement` |
| Facebook ad for the savetax campaign | `https://fynla.org/savetax?utm_source=facebook` |
| YouTube description for an explainer video | `https://fynla.org/savetax?utm_source=youtube` |

`utm_source` and the existing `from` parameter compose freely — you can have both, either, or neither.

### Rules of thumb

- **Always lower-case.** `?utm_source=LinkedIn` will be silently ignored (not on the allowlist).
- **One source per URL.** No need for `utm_medium` / `utm_campaign` — Fynla only reads `utm_source` today.
- **Strip query params from URL shorteners that drop them.** Some shorteners (especially LinkedIn's auto-shortener) preserve the query string; double-check by hitting the shortened URL once and inspecting the final landing URL.
- **Test before posting at scale.** Open the URL in an incognito window, then run this in DevTools console after the page loads:

  ```js
  console.log(sessionStorage.getItem('fynla.signup_source'))
  ```

  If it logs the platform name (e.g. `"linkedin"`), capture is working.

### Adding a new platform

If you want to track a platform not currently on the allowlist (e.g. Reddit, Threads, Bluesky):

1. **Frontend allowlist** — add the lowercase name to `ALLOWED_SOURCES` in `resources/js/utils/sourceCapture.js`.
2. **Backend allowlist** — add the same string to `ALLOWED_SIGNUP_SOURCES` in `app/Http/Requests/RegisterRequest.php`.
3. Both lists reference each other in their docblocks — keep them in sync.
4. Pest test `it exposes the canonical allowlist via the RegisterRequest constant` will fail until you update the assertion in `tests/Feature/Auth/SignupSourceCaptureTest.php` — that's intentional, the hard-coded list there is the contract.

### How to query signups by channel

```sql
-- Totals per channel
SELECT signup_source, COUNT(*) AS signups
FROM users
WHERE signup_source IS NOT NULL
GROUP BY signup_source
ORDER BY signups DESC;

-- Conversion-to-paid by channel (assuming a subscription table)
SELECT
  u.signup_source,
  COUNT(*) AS signups,
  SUM(CASE WHEN s.status = 'active' THEN 1 ELSE 0 END) AS paid_conversions,
  ROUND(
    SUM(CASE WHEN s.status = 'active' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 1
  ) AS conversion_pct
FROM users u
LEFT JOIN user_subscriptions s ON s.user_id = u.id
WHERE u.signup_source IS NOT NULL
GROUP BY u.signup_source
ORDER BY signups DESC;
```

---

## Test coverage delta

- **+5** Pest cases for `EntrySourceCampaignMapTest` (config-driven dispatch, ordering, fallthrough, journey non-regression)
- **+5** Pest cases for the campaign welcome branch in `OnboardingStateMachineTest`
- **+6** Pest cases for the public tax-allowances endpoint
- **+10** Pest cases for `SignupSourceCaptureTest` (allowlist parameterised across 6 platforms, organic null path, validation rejection, MFA-verify copy, constant exposure)
- **Total: +26 new passing tests**, zero regressions across Auth + Onboarding + Fyn + Public suites (266 passed, 1 skipped, 0 failed).
- Architecture suite: 95/95 green.

---

## Browser verification (Playwright MCP, end-to-end)

Both feature paths were driven live in a real browser before merge:

**SaveTax campaign onboarding:**

1. Navigate `/savetax` → page renders, all CTAs link to `/register?from=savetax`.
2. Click "Start your free 7-day trial" → URL becomes `/register?from=savetax`.
3. Fill registration → submit → MFA modal → enter 6-digit code from DB.
4. Land on `/dashboard`, Fyn auto-opens with: *"Hi {name}, welcome to Fynla — I'll help you build your tax-saving strategy…"*
5. DB: `path=campaign · selection=savetax · step=base_personal` ✓

**Channel attribution:**

1. Navigate `/savetax?utm_source=linkedin` → sessionStorage `fynla.signup_source = 'linkedin'` ✓
2. Browse to `/register?from=savetax` → sessionStorage persists ✓
3. Fill + submit registration → `pending_registrations.signup_source = 'linkedin'` ✓
4. Complete MFA → `users.signup_source = 'linkedin'` on the new user row ✓
5. SessionStorage cleared post-registration so next signup in the same session won't inherit ✓

---

## Deployment

Standard `feature → dev → main` flow:

1. Open PR `feature/fyn-persona-split → dev`, merge after approval.
2. Build with `./deploy/csjones-fynla/build.sh` (sets `VITE_BASE_PATH=/fynla/build/`).
3. Upload `public/build/` + the changed PHP files + the new migration to `~/www/csjones.co/public_html/fynla/`.
4. SSH in: `php artisan migrate --force && php artisan db:seed --class=TaxConfigurationSeeder --force && php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan optimize`.
5. Smoke test on `https://csjones.co/fynla/savetax?utm_source=linkedin`.
6. Once green on dev, open PR `dev → main`, merge, repeat with `./deploy/fynla-org/build.sh` for production.

The `marriage_allowance` seeder addition makes a `db:seed --class=TaxConfigurationSeeder` mandatory after migrations on each environment — without it, the `Marriage Allowance` row on `/savetax` will display £0.

---

## Out of scope (deferred)

These were called out up-front in the original campaign plan and remain so:

- **Section 4** — post-expenses state-machine branch ("does {spouse_name} work?")
- **Section 5** — `capture_spouse_work_details` tool + the deterministic "no, doesn't work" write path
- **Section 6** — terminal page / strategy outcome
- **BS-26 / BS-27** Playwright scenario stubs

These need CSJ's planned conversation map before they can be specced. Future plan to follow.

---

## Related docs

- **Original plan:** `April/April28Updates/savetax-campaign-onboarding-plan.md`
- **As-shipped spec:** `April/April28Updates/savetax-campaign-onboarding-spec.md`
- **Sprint 0+1 audit (parent context):** `April/April28Updates/sprint-0-and-1-audit-report.md`
- **Two-Fyn canonical contract:** `April/April24Updates/spec/00-canonical.md`
