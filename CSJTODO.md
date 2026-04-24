# CSJTODO — Fynla

*Last updated: 23 April 2026 — session 63*
*Previous session: 18 April 2026 — session 62*

---

## Session 63 (23 April) — Lifecycle email system: foundation, 11 emails, test sends

### Completed This Session

- [x] **Email template foundation committed** (`9ee42f95`) — `resources/views/emails/layouts/master.blade.php` (slot-based 600px container, eggshell inside / white outside) + 19 module partials (`logo-bar`, `hero-header`, `gradient-header`, `body`, `code-box`, `notice`, `summary-table`, `stats-panel`, `numbered-steps`, `discount-panels`, `top-tips`, `badge`, `bullet-list`, `counter`, `feature-grid`, `cta`, `divider`, `description-box`, `console-box`, `signoff`, `footer`) + `.claude/skills/email-template/SKILL.md` codifying 7 design rules.
- [x] **9 lifecycle Mailables + Blades committed** (`92da2fe6`) — Welcome, GetStarted, DontMissOut, Insights, GreatJob, WellDone, SubscribeInProgress, SubscribeMaxDiscount, WeHaventSeenYou. All extend `LifecycleMail` base with the `utm()` helper so every CTA URL carries `utm_source=email&utm_medium=lifecycle&utm_campaign={slug}&utm_content={cta-id}`.
- [x] **2 more lifecycle emails** (`af546f1e`) — Countdown ("Time's running out" with discount code panel) + EndOfTrial ("Your free trial has ended"), bringing total to **11 lifecycle emails**.
- [x] **Test-send artisan command** (`php artisan mail:send-lifecycle-test {email} [--only=slug,slug]`) — auto-registered, demo data (James / Save Tax / 3 days / trial ends 26 April / 50% / 75% / 20%), supports `--only` for subset sends.
- [x] **11 emails test-sent to azlan@fynla.org** via local mail.fynla.org SMTP — all delivered, 2 follow-up bugs found and fixed:
  - Footer logo 404 (`LogoHiResWhite.png` → `LogoHiResFynlaLight.png`) — one-line module fix cascades to all 11.
  - Great-job "Top tips" layout broke mid-email — root cause was `@include`-ing the `top-tips` module (which is itself a `<tr>`) inside another `<td>`, producing invalid `<tr>` nested in `<td>`. Fixed by unrolling the top-tips markup inline.
  - Unsubscribe link rendered with Gmail's default blue/underline (ignoring inline `color:#b3b9c5; text-decoration:none;`) because `href="#"` was a placeholder. Fixed to `https://fynla.org/unsubscribe` (matches every existing Fynla email).
- [x] **11 HTML mockups** at `public/mockups/emails/` (gitignored) for visual review — `index.html` lists all 11 with descriptions. URLs: `http://localhost:8000/mockups/emails/index.html`.
- [x] **Product video v2** (`c2e790da`) — `/images/Homepage-Fynla-ProductVideo.mp4` → `/images/Homepage-Fynla-ProductVideov2.mp4` in 3 Vue files (LandingPage / CampaignPage / why-fynla/IndependentPage) + new 14 MB mp4 committed.
- [x] **Customer bucketing categorisation** agreed with user — Registered (skipped, <10%), In-progress (11–99%), Completed (100%). % is journey-scoped (already shown on homepage). Implementation deferred until user shares the email scheduling flow.

### NOT Done — Outstanding

- [ ] **Await Azlan's review feedback** on the 11 test emails — he'll flag any final visual/copy tweaks.
- [ ] **Await email scheduling flow from user** — they're working out how to share the interwoven journey logic (flowchart/photo/per-bucket lists/spreadsheet all agreed as acceptable formats). Session 64 task.
- [ ] **Implement customer bucket enum + observer** — once scheduling flow arrives: add `onboarding_bucket` enum column on `users`, observer to recompute on module-complete events, use in the scheduler to decide which track a user is on.
- [ ] **Wire the 11 emails into Laravel Scheduler** — driven by the flowchart once received. Will need per-bucket triggers, delays, skip-if conditions.
- [ ] **Dev deploy of lifecycle emails + product video v2** — `email-onboarding-video` branch is 5 commits ahead of `dev`/`main` with no deploy yet. Dev build was run this session (`./deploy/csjones-fynla/build.sh` produced 8.2 MB `public/build/`) but nothing uploaded to csjones.co.
- [ ] **Upload `public/images/Homepage-Fynla-ProductVideov2.mp4`** to the server(s) alongside the Vue build — without this the page will 404 on the video source even after build uploads land.
- [ ] **Test Fyn chat fixes on dev (csjones.co/fynla)** — carried from session 58.
- [ ] **Re-enable branch protection on `dev`** — carried from session 57.
- [ ] **Add `Current State/Insights.md` to the vault** — carried from session 62.
- [ ] **`AutoRiskCalculatorTest` enum truncation** — pre-existing, carried from April 16.

### Context for Next Session

On `email-onboarding-video`, clean working tree, 5 commits ahead of `origin/email-onboarding-video` (now pushed). Azlan should have all 11 test emails in his inbox. Awaiting his review + the scheduling flow to wire the sequence up in the Laravel Scheduler.

Next session start point: whatever Azlan comes back with on the 11 emails, then the scheduling flow itself (format TBD — user offered flowchart image, per-bucket numbered lists, spreadsheet, or Mermaid).

---

## Outstanding — Tech Debt Deferred

- [ ] **`LifecycleMail::utm()` hardcodes `https://fynla.org`** in concrete Mailables. For dev/prod URL isolation, switch to `config('app.url')` + a `utm_medium` override so csjones.co test sends don't leak to fynla.org links. Minor — only matters once emails fire from dev.
- [ ] **No Pest tests for the 9+2 Mailables or `SendLifecycleTestCommand`** — scope was QA by inbox review, not automated tests. Add before scheduling work lands.
- [ ] **Unsubscribe URL is static `/unsubscribe`** — real List-Unsubscribe implementation needs signed per-recipient tokens (RFC 8058) and a proper route/controller. For now all emails point at the same page.
- [ ] **Email copy references hardcoded tax figures** (£60k AA, £20k ISA, £325k NRB, £3k gift exempt, 40% IHT) — marketing copy, will need a tax-year sweep when 2026/27 lands or values change. Not a `TaxConfigService` candidate because the copy itself is contextual not computational.
- [ ] `AutoRiskCalculatorTest` enum truncation (pre-existing, not email-related).

## Known Issues

None discovered this session that aren't already fixed or documented above.

## Deploy Status

**Production (fynla.org):** Unchanged since session 62 (commit `a14f17a` + tooling audit `062c7c7`). Full Admin Insights CMS live.

**Dev (csjones.co/fynla):** Last deployed from `onboardingFyn` (pre-CMS-merge state). Not updated this session.

**Pending deploy (`email-onboarding-video`, 5 commits ahead):**
- `9ee42f95` — email master layout + 19 modules + skill (PHP/blade only; no deploy impact on pages)
- `92da2fe6` — 9 Mailables + 9 blade templates + UTM helper + test-send command (PHP only; deploy enables `php artisan mail:send-lifecycle-test` on server and the 9 Mailables become usable)
- `c2e790da` — product video v2 (3 Vue files + 14 MB mp4) — **needs Vite build + `public/build/` upload + `public/images/Homepage-Fynla-ProductVideov2.mp4` upload**
- `c1577071` — footer logo + great-job top-tips fix (blade only)
- `af546f1e` — countdown + end-of-trial Mailables/Blades + unsubscribe URL fix (PHP only)

Dev build already run this session — `public/build/` is at 8.2 MB targeting `csjones.co/fynla` base paths. Upload when ready.

## Session 64+ Limitations Noted

- **Vault sync skipped** — the `fynlaBrain` vault at `/Users/CSJ/Desktop/fynlaBrain/` does not exist on this Windows machine (session work is happening on `C:\Users\phail\...`). Session index + git history + update notes should be synced from the Mac when the user switches back. This session's five commits + CSJTODO should be carried over manually.
- **Email mockups** under `public/mockups/emails/` are gitignored (per project `.gitignore`) and only exist locally on the Windows machine. They're review-only artefacts; the canonical source of truth is the Blade templates + module partials (which ARE committed).
