# CSJTODO — Fynla

*Last updated: 30 April 2026 (evening) — session 123 (`/tax-strategy` redesign + fynPersona deploy guide — commit `fad6e88`).*
*Previous session: 122 (30 April 2026 evening — SaveTax public-page chat unification + register-gated CTAs).*

---

## Session 123 (30 April 2026, late evening) — `/tax-strategy` dashboard redesign + fynPersona deploy guide

**Branch:** `feature/fyn-persona-split`. **Commit:** 1 (`fad6e88`) + closing docs commit.

CSJ's open complaint: dashboard generated after savetax onboarding "is not what I asked for, very poor execution, two random sliders on the side, very poor actions/recommendations not personalised to the user". Mid-session escalation: "no nav, no sidebar, no top nav, no fucking Fyn chat window, WTF" — audit found that `TaxStrategyDashboard.vue` was the **only authenticated view in the entire app that didn't wrap in `<AppLayout>`**. Three further course corrections during the redesign: scrub "Your" from every heading, personalise everything by `currentUser.first_name`, then "get rid of these fucking sliders" (deleted `StrategySliderPanel.vue` entirely), then "recommended actions must be placed in two columns".

### Completed this session

- [x] **`TaxStrategyDashboard.vue` wrapped in `<AppLayout>`** — sidebar, top navbar with "Chat with Fyn", trial banner, footer all return. Fixed the chrome-stripping that was the single biggest visible bug.
- [x] **`TaxYearHeader.vue` rebuilt as personalised hero** — reads `auth/currentUser` Vuex getter, sums `recommendations[].estimated_annual_tax_saved` (excludes warnings), counts actionable + warning items. Renders `{firstName}, save up to £X this year` headline + actionable/warning subline. Drops the original `from-horizon-500 to-raspberry-500` gradient banner (design guide v1.4.0 reserves that gradient for public marketing pages).
- [x] **`AllowanceGrid.vue`** — groups allowances into "Headroom available" (sorted by £ remaining desc, foregrounded) vs "Well-utilised" (compact rows below). Total-headroom callout in section header.
- [x] **`AllowanceCard.vue`** — tightened: `compact` mode for utilised cards, "X of headroom" framing instead of identical "used / remaining" treatment, status colour applied to the headroom-remaining text.
- [x] **`StrategyRecommendationList.vue`** — sorts by potential saving with warnings pinned first. Renders 17-entry `NEXT_STEPS` map: every card has a working "Open a pension" / "Open investments" / "Open savings & ISAs" / "See income & tax" CTA routing to the relevant module. `requires_advice` shown as violet "Speak to an adviser" badge. Warning category gets violet "Watch out" pill at top of card and violet border. **2-column grid (`grid-cols-1 md:grid-cols-2`).**
- [x] **`HouseholdView.vue` + `AssetShiftingPanel.vue`** — copy scrubbed of all "Your" headings ("Your spouse" → "Spouse", "Asset-shifting opportunities" → "Move assets to use spouse allowances"); section labels now `{firstName}'s allowances` and `Spouse's allowances`.
- [x] **`StrategySliderPanel.vue` deleted** — sliders weren't pre-populated to user's current state and were presented as decoupled "what if" controls. CSJ: "get rid of these fucking things". Backend pipework left intact for now (W-1 in tech-debt — see below).
- [x] **Live browser verification (Rule #15)** on `john@example.com` (single, £75k, headroom-heavy persona):
  - Hero: "John, save up to £49,319 this year — We've found 5 ways to cut the tax bill this year"
  - Allowances grouped: "Headroom available" (£79,260) — Pension AA £54k / ISA £20k / CGT £3k / Marriage Allowance £1,260 / Savings Allowance £500 / Dividend Allowance £500 — vs "Well-utilised" — PA £12,570 + Starting Rate £5,000.
  - Recommendations sorted £48,000 → £720 → £300 → £179 → £120 in 2-col layout. Top card "Carry forward up to £120,000 of unused Pension Allowance" has `Open a pension` CTA.
  - AppLayout chrome (sidebar, top navbar with "Chat with Fyn" toggle, trial banner, footer) all rendering correctly.
  - No "Your" in any heading on the page.
  - Screenshots: `tax-strategy-2col.png`, `tax-strategy-no-sliders.png`, `tax-strategy-redesign-v1.png`, `tax-strategy-with-applayout.png`.
- [x] **`fynPersona.md` deploy guide written** at `April/April30Updates/fynPersona.md` (532 lines) — covers full `feature/fyn-persona-split → dev` deploy at `https://csjones.co/fynla`. 252 commits past `origin/dev` (diverged at `58aeb47`); 135 PHP files, 42 Vue files, 24 migrations, 7 new tables, 2 deleted controllers/middleware, 1 renamed PHP class (autoloader collision risk flagged), 3 new composer deps, 1 new env var (`AI_AUDIT_HMAC_KEY`). Six phases with explicit pre-flight checklist, local build, file uploads, server deletes, SSH steps in correct order with the csjones gotcha that artisan runs from `~/www/csjones.co/fynla-app/` not `public_html/fynla/`, smoke-test path table, post-deploy monitoring, full reverse-step rollback. Generated from `git diff origin/dev...HEAD --name-status` per `feedback_deploy_guide_completeness.md`.
- [x] **Tech-debt audit** — see `April/April30Updates/tech-debt-report-session-123.md`. 0 critical, 1 medium, 2 low. **Vue Components 714 → 713** (StrategySliderPanel deleted). All 7 changed files pass clean checks (no "Your", no hex, no banned colours, no icons on banned surfaces, all Tailwind tokens valid).

### NOT Done — Outstanding for next session

#### Top priority — Deploy `feature/fyn-persona-split` to dev

CSJ's directive at session-end: "deploy to dev for next session". Use [[April/April30Updates/fynPersona|fynPersona.md]] as the deploy guide. The branch is 252 commits past `origin/dev` and **this is a large deploy** — do not skip the pre-flight checklist.

- [ ] **Pre-flight (mandatory)**: verify dev `.env` has `AI_AUDIT_HMAC_KEY` (generate via `openssl rand -base64 32` if absent); remove dead `AGENT_INTERNAL_TOKEN`; take SiteGround DB backup; confirm dev server is currently running `dev` branch (per memory `feedback_dev_server_is_separate.md`); check disk space (≥ 200MB free); pause scheduled jobs.
- [ ] **Phase 1**: build locally with `./deploy/csjones-fynla/build.sh` (correct Vite vars baked in: `VITE_BASE_PATH=/fynla/build/`, `VITE_ROUTER_BASE=/fynla/`, `VITE_API_BASE_URL=https://csjones.co/fynla`, `VITE_REVOLUT_SANDBOX=true`). Confirm `public/build/` populated with > 200 assets.
- [ ] **Phase 2**: upload via SiteGround File Manager — full `public/build/`, 135 PHP files, 4 config files (2 NEW: `config/fyn_eval.php`, `config/onboarding.php`), `routes/api.php`, `composer.{json,lock}`, 24 migrations, 5 seeders, 2 factories, `.htaccess` if changed.
- [ ] **Phase 3**: delete from server — `app/Http/Controllers/Api/AgentInternalController.php`, `app/Http/Middleware/AgentTokenAuth.php`, **`app/Services/AI/SystemPromptBuilder.php`** (the renamed file — autoloader collision risk if both `SystemPromptBuilder.php` and `AdvicePromptBuilder.php` exist), 6 Python agent scripts under `scripts/`.
- [ ] **Phase 4**: SSH `cd ~/www/csjones.co/fynla-app` (NOT `public_html/fynla` — see memory `reference_csjones_sibling_dir.md`) → `composer install --no-dev --optimize-autoloader` → `php artisan migrate --force` → `php artisan db:seed --force` → `cache:clear` + `config:clear` + `view:clear` + `route:clear` + `optimize` → `php artisan up`.
- [ ] **Phase 5**: smoke-test paths in incognito — `/savetax`, `/register?from=savetax`, onboarding end-to-end (chris@fynla.org or test user), `/tax-strategy` (single user — verify hero, allowances split, recommendations 2-col, AppLayout chrome), `/tax-strategy` (dual-earner persona bs27 — verify HouseholdView), `/dashboard` regression, admin eval recordings, `php artisan ai:audit-verify-chain` SSH check.
- [ ] **Phase 6**: monitor `storage/logs/laravel.log` for 15 min; tail queue worker for `ConversationSummariserJob` failures; verify `AI_AUDIT_HMAC_KEY` in effect; retain DB backup until 24-hour clean smoke.

#### Confirm with CSJ before acting

- [ ] **W-1 (medium tech-debt — orphaned slider backend)** — `StrategySliderPanel.vue` was deleted in `fad6e88` but the entire backend pipeline remains: `taxStrategyService.recalculate`, `taxStrategy/recalculate` Vuex action + `setRecalculating` mutation, `TaxStrategyController::calculate`, `TaxStrategyCalculateRequest`, `TaxStrategyOverridesDTO`, `TaxStrategyService::recalculate`, `POST /api/tax-strategy/calculate` route, plus 9 Pest test cases. Two options: **(a)** leave intact for a future "what-if" UI on the roadmap, or **(b)** rip out completely (mechanical refactor — strategy classes already work with `null` overrides, math service signatures simplify). CSJ was emphatic about removing sliders — option (b) is likely correct. Confirm before touching.

### Tech-debt items carried forward

- [ ] **S-3 (carried from sessions 118-120)** — Hardcoded Junior Pension £2,880 / £720 in `LifecycleStrategy::generate`. Comment now cites HMRC source, but exposing via `TaxConfigService` would let CSJ tweak figures without a code change.
- [ ] **S-2 (carried, was magic threshold)** — `> 1000` "worth recommending" threshold in `AssetShiftingBundleStrategy`. Extract `private const MIN_TRANSFER_TO_RECOMMEND = 1000.0;` when a 4th similar bundle appears.
- [ ] **W-1 (Rule #14, from session 122)** — `StaticFynChat.vue` lines 26, 30, 34 (3 checkmark SVGs in welcome list) and 101–103 (paper-plane SVG on Send button) violate Rule #14's ban on icons inside the Fyn chat window. All pre-existing. Replace with text bullets / "Send" label when the cleanup is in scope.
- [ ] **W-2 (from session 122)** — `SaveTaxCampaignPage.vue` lines 102–106 — bottom CTA renders green (`bg-spring-500`) while the other 6 CTAs render raspberry. Now that all 7 share the same label/destination, the colour split is visually inconsistent.
- [ ] **S-1 / S-2 (session 123 — micro)** — `NEXT_STEPS` route paths hardcoded in `StrategyRecommendationList.vue` (defer until 2nd consumer); `formatCurrency(Math.round(...))` repeated 5× across changed components (add `formatCurrencyRounded()` to `currencyMixin` in a future sweep).

### Context for Next Session

- **CSJ is starting the next session with the dev deploy.** The fynPersona.md guide is the source of truth — read it first, follow the phases in order. Do NOT deploy from memory — always cross-reference `git diff origin/dev...HEAD --name-status` before each upload batch (per `feedback_deploy_guide_completeness.md`).
- **Branch state**: `feature/fyn-persona-split` at `fad6e88` (plus the closing docs commit). All committed and pushed. 252 commits past `origin/dev`. Working tree clean.
- **Commit total today**: 21 commits across sessions 115-123.
- **NOT to be deployed to production** until dev is fully smoke-tested and CSJ explicitly approves the dev → main release PR. Per `feedback_main_via_dev_only.md`.

### Deploy Status

| Environment | Branch | Status |
|---|---|---|
| Production (`fynla.org`) | `main` | NOT being touched in next session |
| Dev / staging (`csjones.co/fynla`) | `dev` (target) | **Pending deploy of `feature/fyn-persona-split` per fynPersona.md** |
| Feature branch | `feature/fyn-persona-split` (HEAD `fad6e88`) | Pushed, ready for build |

### Memory laws relevant to next session

- `feedback_deploy_guide_completeness.md` — generate from `git diff`, not memory; missing files = 500
- `feedback_dev_server_is_separate.md` — confirm dev server is on `dev` branch before building/uploading
- `feedback_warn_before_spa_rebuild.md` — rename `public/build/` to `build.old/` on server before overwriting; merge cached files post-upload
- `feedback_htaccess_vs_middleware_headers.md` — never set CSP/HSTS in both `.htaccess` and `SecurityHeaders` middleware
- `reference_csjones_sibling_dir.md` — artisan runs from `~/www/csjones.co/fynla-app/`, NOT `public_html/fynla/`
- `reference_csjones_ssh_access.md` — SSH via `~/.ssh/fynlaDev` (passphrase); not the `ssh-fynla` MCP
- `feedback_main_via_dev_only.md` — nothing reaches main without dev verification first

---

## Codebase metrics (post-session 123)

- Vue Components: **713** (was 714 — StrategySliderPanel deleted)
- PHP Services: 292
- Controllers: 103
- Models: 107
- Vuex Stores: 34
- Agents: 9
- Migrations: 204
- Factories: 66
- Service dirs: 37
- API Services: 47
