# Fynla Project Memory

## Build Commands - CRITICAL
- **NEVER** run `npx vite build`, `npm run build`, or raw vite/webpack commands directly
- **Development:** `./dev.sh` (starts Laravel + Vite)
- **Production builds:** `./deploy/fynla-org/build.sh` or `./deploy/csjones-fynla/build.sh`
- **iOS mobile builds:** `./deploy/mobile/build-ios.sh` (builds + syncs to Capacitor iOS project)
- After mobile builds, always clear cache: `php artisan cache:clear`
- For syntax checking, use `./dev.sh` and watch for compile errors in the terminal output

## Database Seeding - #1 CRITICAL RULE — NEVER SKIP
- **`php artisan db:seed` IS THE FIRST AND LAST COMMAND OF EVERY TASK. PERIOD.**
- Run it: BEFORE opening any browser. BEFORE any test. BEFORE any preview persona login. AFTER every backend change. AFTER every frontend change. AFTER running tests. AFTER stopping/starting servers. AFTER EVERYTHING.
- If in doubt: SEED. If not in doubt: SEED ANYWAY.
- When the user says "test this" or "check this" — SEED FIRST, then do the thing.
- When you finish ANY piece of work — SEED as the very last step.
- **NEVER** use `migrate:fresh` or `migrate:refresh` — these destroy data
- Preview personas WILL fail with 403 if not seeded.
- **THIS RULE HAS BEEN VIOLATED REPEATEDLY. USER IS FURIOUS. THERE IS ZERO TOLERANCE FOR SKIPPING SEEDS.**

## Revolut Payment Integration
- **SDK**: CDN script loading (NOT npm package). The npm `@revolut/checkout` package does NOT expose `embeddedCheckout()` as static method. Must use CDN `embed.js` from `sandbox-merchant.revolut.com` / `merchant.revolut.com`
- **Approach**: `RevolutCheckout.embeddedCheckout()` direct initialisation with `publicToken` (pk_...) — shows Revolut Pay, Card, Google Pay
- **Implementation plan**: `revolut/implementation-plan.md`
- **Bug fixes**: `revolut/bugFix.md` (SDK mismatch, CSP, localhost redirect, processing state)
- **System map**: `currentState/PaymentSubscription.md` — comprehensive system documentation
- **Deploy guide**: `Feb24Updates/newDeployRevolut.md` — files to upload, SSH commands, env vars
- **Key files**: `CheckoutPage.vue`, `PaymentController.php`, `RevolutService.php`, `WebhookController.php`, `PlanSelectionModal.vue`, `SubscriptionManagement.vue`
- **Config**: `REVOLUT_API_KEY` (sk_...), `REVOLUT_PUBLIC_KEY` (pk_...), `REVOLUT_WEBHOOK_SECRET`, `REVOLUT_SANDBOX`, `PAYMENT_ENABLED`, `VITE_REVOLUT_PUBLIC_KEY`, `VITE_REVOLUT_SANDBOX`
- **Pricing**: Stored in `subscription_plans` DB table. 4 tiers: Student, Standard, Family, Pro. Launch prices (first 500 users): Student £3.99/£30, Standard £10.99/£100, Family £14.99/£150, Pro £19.99/£200. Regular: Student £4.99/£45, Standard £14.99/£135, Family £21.99/£199, Pro £29.99/£270
- **Key gotcha**: Revolut fires `onSuccess` while order is in `"processing"` state — `confirmPayment` must accept this
- **Currency**: All amounts use `formatCurrencyWithPence()` for 2 decimal places, no period suffix

## Design System — fynlaDesignGuide.md v1.3.0
- **Single source of truth**: `fynlaDesignGuide.md` (replaced `designStyle.md`, archived as `designStyle-legacy.md`)
- **Font stack**: Segoe UI (primary, system font), Inter (fallback, Google Fonts), JetBrains Mono (monospace)
- **Font weights**: Display/H1 = 900 (Black), H2-H5 = 700 (Bold)
- **Key color mappings**:
  - CTAs/buttons: `raspberry-500` (#E83E6D)
  - Text/nav: `horizon-500` (#1F2A44)
  - Success: `spring-500` (#20B486)
  - Warnings/focus rings: `violet-500` (#5854E6)
  - Hover/subtle bg: `savannah-100` (#FDFAF7)
  - Page background: `eggshell-500` (#F7F6F4)
  - Muted text: `neutral-500` (#717171)
  - Borders: `light-gray` (#EEEEEE)
- **Banned tokens**: `primary-*`, `secondary-*` (old slate), `gray-*` for general UI, `amber-*`, `orange-*`
- **Kept unchanged**: Risk-level badge colors (green/teal/blue/red), account type badges (ISA blue, SIPP purple, etc.)
- **Chart colors via**: `designSystem.js` `CHART_COLORS` array (8 colors from new palette)

## CRITICAL LAW — Browser Testing is MANDATORY
- **"Tested" means you CLICKED, FILLED, SUBMITTED in Playwright and verified the RESULT.**
- Reading a diff is NOT testing. A snapshot without interaction is NOT testing.
- NEVER say "verified", "pass", "confirmed" for items you did not interact with in the browser.
- NEVER write a report or declare "complete" until ALL browser testing is finished.
- If login/registration fails — ASK THE USER. Do NOT skip. Do NOT defer.
- Every [x] checkbox must have a corresponding Playwright CLICK/FILL/SUBMIT.
- **THIS HAS BEEN VIOLATED REPEATEDLY. ZERO TOLERANCE. NO EXCEPTIONS.**

## Memory Files
- [project_decision_engine_upgrade.md](project_decision_engine_upgrade.md) — Decision engine upgrade initiative: 5 modules, 31 tasks, 5 sprints. Master plan at March/March14Updates/MASTER-IMPLEMENTATION-PLAN.md
- [mobile_capacitor_patterns.md](mobile_capacitor_patterns.md) — Capacitor iOS gotchas: WKWebView MIME type errors, vite.config.js rules, biometric (Face ID) login flow + mobileLogout, URL origins, build process, data shapes, SSE streaming
- [feedback_ios_testing_checklist.md](feedback_ios_testing_checklist.md) — MANDATORY checklist before claiming iOS mobile work is done (includes vite.config.js checks for MIME type errors)
- [ux_data_journey_analysis.md](ux_data_journey_analysis.md) — UX data journey analysis
- [feedback_never_raw_vite_build.md](feedback_never_raw_vite_build.md) — NEVER use npx vite build — always use ./deploy/fynla-org/build.sh
- [feedback_no_self_approval.md](feedback_no_self_approval.md) — NEVER self-approve plans or run fake review loops. Ask the user what they want before making changes.
- [feedback_never_switch_branches.md](feedback_never_switch_branches.md) — Work directly on the current branch in the main working directory. Only use worktrees when parallel agents need different branches simultaneously.
- [feedback_main_via_dev_only.md](feedback_main_via_dev_only.md) — ABSOLUTE: nothing merges to main without first being committed to dev, deployed to csjones.co/fynla, and browser-tested. Both main and dev are GH-protected. Only CSJ overrides — and only with explicit words in the current turn.
- [feedback_no_main_dir_commands.md](feedback_no_main_dir_commands.md) — SUPERSEDED by feedback_never_switch_branches.md. User now works sequentially in main dir.
- [feedback_worktree_deploy_disconnect.md](feedback_worktree_deploy_disconnect.md) — Worktree work must be merged back to main BEFORE user builds/deploys. Otherwise user builds from main (old code) thinking they're deploying your changes.
- [feedback_never_skip_testing.md](feedback_never_skip_testing.md) — CRITICAL: After ANY fix, ALWAYS test from Step 1, fill EVERY field on EVERY step, NEVER skip or jump to the fix point. Fixes can break other things. NEVER be lazy.
- [critical_browser_testing_law.md](critical_browser_testing_law.md) — ABSOLUTE LAW: "Browser tested" means CLICKED/FILLED/SUBMITTED in Playwright. Diff review is NOT testing. NEVER say "verified" for untested items. NEVER write reports before testing is done. ASK USER when blocked.
- [reference_fynlabrain_vault.md](reference_fynlabrain_vault.md) — fynlaBrain Obsidian vault structure at /Users/CSJ/Desktop/fynlaBrain. Format: YAML frontmatter, wikilinks, MOC index files. NOT a git repo.
- [project_code_review_2026_03_18.md](project_code_review_2026_03_18.md) — Full codebase review 18 Mar 2026: 94/94 tasks fixed. Tax values MUST use TaxConfigService. Financial casts must be decimal:2. Auth uses UserResource. Key patterns and locations.
- [feedback_breaking_frustration_cycle.md](feedback_breaking_frustration_cycle.md) — 8 rules to prevent the plan→execute→broken→rage cycle. Core: never say "done" without browser evidence, fill every field, test from step 1 after fixes, never self-approve sub-agents, scope discipline, ask don't guess, isolate parallel agents, report honestly.
- [feedback_ai_form_fill_process.md](feedback_ai_form_fill_process.md) — MANDATORY 10-step process for AI form fill: read files → fill manually → verify DB → write algorithm → code → test with Grok. NEVER skip manual testing. NEVER claim PASS on £0 accounts.
- [project_ai_form_fill_status.md](project_ai_form_fill_status.md) — AI form fill DEPLOYED to production 25 March 2026. 14/14 modules passing on fynla.org. Merged to main (PR #160).
- [feedback_deploy_guide_completeness.md](feedback_deploy_guide_completeness.md) — ALWAYS generate deploy guides from `git diff` not memory. Missing files caused 500 on production.
- [feedback_merge_branch_conflicts.md](feedback_merge_branch_conflicts.md) — Before merging long-running branches, cross-reference files changed on both branches. Git merge can silently overwrite main's changes.
- [feedback_subagent_accountability.md](feedback_subagent_accountability.md) — If using subagents, MUST check their work rigorously. Prefer inline execution for sequential work.
- [feedback_never_touch_env_or_db.md](feedback_never_touch_env_or_db.md) — NEVER modify .env, insert/delete DB records, or disable features to work around login/subscription issues. Use the system as designed. ASK the user.
- [feedback_ai_process_no_shortcuts.md](feedback_ai_process_no_shortcuts.md) — AI form fill process Steps 1-10 must be followed IN ORDER. Steps 4-5 (manual browser fill + DB verify) MUST happen BEFORE Steps 6-8 (algorithm + code). Writing code before testing is forbidden.
- [feedback_eval_gate_compliance.md](feedback_eval_gate_compliance.md) — When stop hook says "invoke /eval-review", MUST invoke the skill immediately. Never self-evaluate. Always dispatch the eval-reviewer agent.
- [feedback_file_locations.md](feedback_file_locations.md) — When user specifies a file path, write there — don't override with default skill locations.
- [feedback_always_test_locally.md](feedback_always_test_locally.md) — ALWAYS test locally before deploying. Never ask or skip.
- [feedback_never_version_bump.md](feedback_never_version_bump.md) — NEVER version bump unless user explicitly tells you to. Do not suggest or track as outstanding.
- [feedback_never_hardcode_tax_values.md](feedback_never_hardcode_tax_values.md) — CRITICAL: ZERO hardcoded tax years, allowances, thresholds, or rates. Backend: TaxConfigService. Frontend: getCurrentTaxYear() + taxConfig.js. Stop hook enforces.
- [feedback_never_minimize_bugs.md](feedback_never_minimize_bugs.md) — Never downplay visible bugs as "minor" or "partially fixed". If content is cut off, it is BROKEN. Report honestly.
- [feedback_deploy_guides_both_locations.md](feedback_deploy_guides_both_locations.md) — Deploy guides ALWAYS go in BOTH fynlaBrain vault AND repo directory. Never just one.
- [project_revolut_live_status.md](project_revolut_live_status.md) — revolutLive branch: subscriptions, discount codes, invoicing. All 3 discount bugs RESOLVED 8 Apr 2026.
- [project_pr214_with_persona_split.md](project_pr214_with_persona_split.md) — PR #214 (onboardingFyn) must be reviewed alongside feature/fyn-persona-split, not in isolation. Active onboarding work has moved to fyn-persona-split; #214 may be superseded. Do not rebase/merge #214 solo.
- [feedback_warn_before_spa_rebuild.md](feedback_warn_before_spa_rebuild.md) — Warn CSJ before rebuilding public/build/ while they have an incognito session open. Always use `cp -rn build.old/. build/` merge pattern on upload so in-flight dynamic imports survive. Fresh session still needed if the bug was in the compiled bundle itself.
- [feedback_htaccess_vs_middleware_headers.md](feedback_htaccess_vs_middleware_headers.md) — Never set CSP/HSTS/Permissions-Policy in both .htaccess and SecurityHeaders middleware. Apache wins, silently overwrites middleware's rich CSP, blocks Revolut widget / fonts / analytics. .htaccess should only set headers PHP can't (X-Content-Type-Options, X-Frame-Options, etc.).
- [feedback_never_claim_verified.md](feedback_never_claim_verified.md) — NEVER say "verified" from partial evidence. Must complete full flow (payment, invoice PDF, emails, DB) before claiming success.
- [feedback_never_close_browser.md](feedback_never_close_browser.md) — ABSOLUTE RULE: NEVER call mcp__playwright__browser_close unless the user explicitly asks in the current turn. Closing the browser loses user's tabs, auth, form state. Violated in session 55.
- [reference_csjones_sibling_dir.md](reference_csjones_sibling_dir.md) — csjones.co/fynla dev uses sibling-dir + symlink pattern. Laravel app at ~/www/csjones.co/fynla-app/, NOT public_html/fynla. Artisan commands must cd there first. Production (fynla.org) uses standard layout, don't confuse them.
- [reference_csjones_ssh_access.md](reference_csjones_ssh_access.md) — SSH to csjones.co uses ~/.ssh/fynlaDev (passphrase-protected) via plain ssh -p 18765. Not via ssh-fynla MCP (that's fynla.org prod). Check `ssh-add -l` first; ask user for passphrase once if not loaded; NEVER run ssh-keygen or probe keys.
- [feedback_dev_server_is_separate.md](feedback_dev_server_is_separate.md) — Dev server may run a DIFFERENT branch than `dev` (e.g. onboardingFyn). ASK which branch is deployed before building/uploading. Git branch != server deployment.
- [reference_month_updates_folders.md](reference_month_updates_folders.md) — Month-updates folders exist in BOTH fynla repo AND fynlaBrain vault. Default to REPO (/Users/CSJ/Desktop/fynla/{Month}/{Month}{D}Updates/) for deploys, PRDs, CSJTODO, handovers. Day is unpadded (April1Updates, not April01Updates).
- [feedback_incremental_verification.md](feedback_incremental_verification.md) — One bug, one fix, one live browser test, then the next. Batched fixes + one test cycle means you can't tell which broke. "Tests pass" isn't sufficient for UI-layer bugs. Diagnose network/console/backend before reverting. Page-reload after any Vite HMR.
- [feedback_fyn_model_choice_is_deliberate.md](feedback_fyn_model_choice_is_deliberate.md) — grok-4-1-fast-reasoning is a unit-economics choice, not lag. Don't flag as stale. Quality lifts must come from prompts/evals/structured outputs, not model upgrade.
- [project_advanced_chat_model_branch.md](project_advanced_chat_model_branch.md) — HasAiGuardrails advanced_chat_model branch is dead code (escalates to same grok-4-1). Parked for Sprint 3: delete (YAGNI) OR repoint xAI-advanced to Claude Sonnet 4.6 for Pro complex queries.
- [project_fyn_ai_audit_24apr.md](project_fyn_ai_audit_24apr.md) — Fyn AI 24 April audit outputs + 7 CSJ decisions (guidance-only FCA, two-Fyn no-orchestrator, Python sidecar delete, local-first deploy, multi-entity 95% baseline + 100% hard-fail floors, both rubrics). Sprint 0 honest effort ~3-4 weeks not 1-2 days. Morning docs contain load-bearing errors; afternoon artefacts (audit-evidence / audit-synthesis / fyn-rubrics) are the correction inputs.
