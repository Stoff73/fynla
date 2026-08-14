# Registry — Capabilities

**Status:** Seeded 2026-08-13, session 2. **Knowingly incomplete and expected to
stay that way** — it grows by discovery, see §3.
**Owner:** CSJ. Amendments gated.

---

## 1. Why this exists

Three shallow scans in session 2 found three pieces of machinery the workforce
would otherwise have duplicated:

| Found | What we'd have built instead |
|---|---|
| Marketing approver step (PR #691) | A second approval mechanism for one artefact |
| Google Drive service account | A second credential for the same system |
| Bug reporter → GitHub issue → **autonomous Claude fix loop** | A second bug intake *and* a second fix loop |

Three for three, from a scan that wasn't even looking for them. Fynla is roughly
446 services, 128 controllers, 134 models, 675 components, plus custom artisan
commands and in-flight branches. **Nobody holds the whole map** — so "I don't
recall anything like that" is not evidence of absence, and must never be treated
as such.

**This generalises `CLAUDE.md` Rule 20**, which already requires that before
fixing any Fyn behaviour you *"enumerate every mechanism that implements it. If
more than one exists, consolidating them into ONE source is PART of the fix."*
Rule 20 is scoped to Fyn. CSJ extended the principle to everything on 2026-08-13:
machinery, logic, algorithms and code that already do a job must be found and
mapped before anything new is built.

## 2. The prior-art check — mandatory before any build

**No work item moves from `queued` to `claimed` without a recorded prior-art
check.** It is a field in the work item, not a habit:

```yaml
prior_art_checked: 2026-08-13T14:00:00Z
prior_art_found: [capability: marketing-approval]
prior_art_outcome: route          # none | route | extend
```

### Read the enforcing layer, not the descriptive one

**Lesson, session 7.** This workforce asked CSJ to confirm the pricing model twice,
then read `SubscriptionPlanSeeder` and concluded there were four tiers. Both were
wrong: the answer was in `TierResolver`, which names the four as
`LEGACY_PAID_PLANS` and resolves everything to `free`.

Two failures, both avoidable:

1. **Asked instead of read.** The information was in the repository the whole time.
   Interview rule 1 exists precisely for this: *never ask what is already written*.
2. **Read the wrong layer.** A seeder describes historical data. A resolver enforces
   current behaviour. **Where they disagree, the enforcing layer is the truth.**
   Find the code that *decides*, not the code that *lists*.

3. **Read the wrong thing *inside* the right file.** Session 3 concluded there were
   seven personas including `widow`, because a grep for `'widow'` in
   `PreviewUserSeeder.php` matched — **dead code**. `widow` was removed on 17 March
   2026 (commit `54b396a89`). The list the seeder actually iterates is
   `private const PERSONAS`, which has six. Worse, the conclusion was used to
   declare `CLAUDE.md` stale when `CLAUDE.md` was correct.

   **The rule: find the declaration that drives behaviour, not an occurrence of the
   string.** A constant that a loop reads is truth; a method further down the same
   file may be unreachable. Presence of a name proves it was once there — never
   that it still is.

   **And check for removal, not just presence.** `git log --all -i --grep="<name>"`
   would have surfaced a commit whose body says *"Remove widow persona from all
   systems"*. One command, ten seconds, and the whole error avoided.

### Canonical declarations — use these, not any mention

| Concern | Canonical | Not |
|---|---|---|
| Tax values | `TaxConfigService` | Anything hardcoded |
| **Personas** | **`PreviewController::VALID_PERSONAS`** — what the API returns and the user sees. `PreviewUserSeeder::PERSONAS` agrees. | Any `'widow'` string in the seeder file (dead code); `appMapping/v083/05-FRONTEND-ARCHITECTURE.md` (says seven — wrong) |
| Entitlement | `TierResolver`, `PremiumEntitlementResolver` | `SubscriptionPlanSeeder` |
| Prices, caps, AI budgets | `TierConfigurationSeeder` → `tier_configurations` | Any prose |

### What the search must cover

Grep alone is not a search. All six:

1. This capability map
2. The codebase — services, jobs, commands, controllers, config
3. **Custom artisan commands** (`php artisan list`) — a lot of Fynla's machinery lives here
4. **Open PRs and in-flight branches** — this is what caught the Google work; it would not have appeared in a codebase grep of `dev`
5. The vault (`fynlaBrain/`)
6. `.claude/skills/` and `.claude/agents/` — a duplicate skill is a duplicate

### Exactly three outcomes, never a fourth

| Outcome | Meaning |
|---|---|
| **none** | Nothing exists. Build it, and add it to this map. |
| **route** | Something exists and is adequate. Use it. Do not wrap it, mirror it, or "improve on it alongside". |
| **extend** | Something exists and is inadequate. Extend that thing. |

**"Build a parallel one because the existing one is awkward" is not an available
outcome.** If the existing mechanism is genuinely wrong, replacing it is a work
item with its own prior-art record — not a second mechanism that quietly coexists.

## 3. Discovery is continuous

The map is not built once. Three feeds:

- **Prior-art checks** — every search result lands here, including the negative
  ones. "We looked for X and found nothing" is worth recording; the next person
  should not repeat it.
- **Quartermaster contradiction sweep** — its existing gap class extends from *two
  agents applying different rules* to **two mechanisms doing one job**. Same class,
  same handling.
- **Encounter** — any agent that stumbles on undocumented machinery records it,
  whether or not it was looking.

## 4. The map

Seeded from `CLAUDE.md`, `config/`, custom artisan commands and session 2
discovery. **Unverified entries are marked.** Verifying them is Phase 1 work.

### Pipelines and loops — highest duplication risk

| Job | Implemented by | Verified |
|---|---|---|
| **Bug intake → GitHub issue → autonomous fix loop** | `GITHUB_BUG_ISSUE_*`; commit "mobile bug reporter → GitHub issue → autonomous Claude fix loop" | **Mapped 2026-08-13 → see §7.1. Collision risk confirmed real and unmitigated — read §7.0 before claiming any GitHub issue.** |
| **Marketing article pipeline** — Drive Word doc → `InsightArticle`, approver sets publish date, cross-env publish | PR #691, `codex/google-drive-marketing-readiness` | Found |
| **Google Drive access** | Google service account (PR #691) | Found |
| Lifecycle email engine | `config/lifecycle.php` | **Verified 2026-08-13 → §7.2** |
| Awin affiliate | `config/awin.php`, `deploy/awin/` | **Verified 2026-08-13 → §7.3** |
| Revolut payments + webhooks | `REVOLUT_*`, `deploy/deployRevolut.md` | Unverified |
| Push notifications | FCM + APNS | Unverified |

### Scheduled and maintenance jobs

**Superseded — the real schedule is `app/Console/Kernel.php`, mapped at §7.4
(verified 2026-08-13). Only four of the ten below actually run on a timer.** The
`CLAUDE.md` list is a custom-command index, not a schedule:

`preview:reset` · `audit:purge` · `trials:expire` · `sessions:cleanup` ·
`registrations:cleanup` · `fyn:episodic:backfill-blobs` ·
`fyn:episodic:cold-archive` · `fyn:episodic:reconcile` · `fyn:episodic:purge` ·
`fyn:user:erase`

### Domain machinery

| Job | Implemented by |
|---|---|
| **Entitlement / what a user may access** | `TierResolver` + `PremiumEntitlementResolver`. **Not** `SubscriptionPlanSeeder** — that seeds four *legacy* plans for grandfathered subscribers only. Model is free forever + one premium upgrade; cutover machinery is `TierCollapseLock`, `TierCollapsePreflight`, `RevolutTierVariationSync`. **Verified session 7 after this workforce misread the seeder as the pricing model.** |
| All UK tax values | `TaxConfigService` — **never hardcode** (Rule 2) |
| AI chat dispatch, read vs write | `AiChatController::sendMessage` → `AdviceFyn` / `OnboardingChatDirector` |
| Fyn system prompt | `FynSystemPrompt::text()` + `FynContextAssembler::build()` |
| Compliance / perimeter rules | `ComplianceRules.php`, `FcaProcessInstructions.php` |
| Mobile dashboard aggregation | `MobileDashboardAggregator` → `normaliseModule()` |
| Gamification, levels, percentile | `MobileLevelService`, `config/gamification.php` |
| Ownership share calculation | `CalculatesOwnershipShare` trait / `ownership.js` |
| Preview persona isolation | `is_preview_user`, `PreviewWriteInterceptor` |
| Retention / audit purge | `config/retention.php`, `audit-retention-policy.md` |

### Known non-coverage

Things the product does **not** do, recorded so nobody assumes it does. Absence of
a capability is as much a map entry as its presence — and more dangerous, because
it fails silently.

| Not covered | Evidence | Consequence |
|---|---|---|
| **Grandfathered legacy subscribers — none exist** | CSJ, 2026-08-13: no users to grandfather; founder accounts are admin | `LEGACY_PAID_PLANS`, `isGrandfatheredLegacyPaid()`, `SubscriptionPlanSeeder`'s four plans and the tier-collapse machinery currently protect nobody. **Removal candidate for the Quality lead** — not asserted dead. `00-precedence.md` §2.5 removal test applies; `TierCollapseLock` guards payment during cutover and whether that cutover is complete is a code question. |
| **Crypto / digital assets as an asset class** | Verified 2026-08-13: appears only in Estate will and letter documents. Absent from net worth, investment and inheritance-tax calculation. | A holder gets a silently incomplete picture. Not an exclusion — day-traders and crypto users are clients Fynla has not built for (`03-hard-nos.md` §4). Disclosure question carried to `05-perimeter.md`. |

### Known duplication already recorded

`CLAUDE.md` Rule 20 names past instances: two ownership-phrasing vocabularies, two
answer paths, three SSE consumers with one missing `navigation`, per-surface
markdown renderers. **Grandfathered — do not rip out** (Rule 15's forward-only
principle), but never add to.

## 5. What this map is not

It is **not** a file index and must never become one. It lists *jobs the system
already does*. A capability nobody can name in one line is described wrongly.

---

## 6. The Cartographer — the map has an owner

**Ratified 2026-08-13, session 2.** A map nobody owns goes stale, and a stale map
is worse than none — it produces confident wrong answers. So mapping is a role,
not a shared chore.

### 6.1 Why a distinct agent, not the Quartermaster

The Quartermaster audits whether each agent *had what it needed*. The Cartographer
*builds what they need*. Keeping them separate means the agent that judges whether
the map was sufficient is not the agent that wrote it — the same independence
principle already ratified for evidence packs (`08-process.md` §2.4), where the
author never verifies their own work.

*(Alternative considered: folding this into the Archivist. Rejected — curating
documents and surveying a 446-service codebase are different jobs on different
cadences, and this one gates every work item.)*

### 6.2 Three dimensions, not one

A capability list alone prevents duplication. It does not prevent regression. The
map therefore records:

| Dimension | Answers | Prevents |
|---|---|---|
| **Capability** | What job is done, and by what | Duplication |
| **Surface** | Which of web · `/m` · iOS · API · background it covers (Rule 19) | Half-finished work |
| **Consumers** | What depends on this, and would break if it changed | **Regression** |

The third is the one usually missing. "What exists" tells you not to rebuild it;
only "what depends on it" tells you what you are about to break. It is also what
Rule 20 is really asking for when it demands all surfaces and all paths.

### 6.3 Role-scoped views — not every agent gets everything

**Ratified: the Cartographer understands each agent's role and maps to it.** The
full map on a codebase this size would consume the context budget and bury the
signal. An agent that sees everything attends to nothing.

Each agent receives:

| Scope | Detail level |
|---|---|
| **In-domain** — capabilities the agent owns or will touch | Full: implementation, surfaces, consumers, known issues |
| **Adjacent** — capabilities its work commonly meets | Name, owner, one line. Enough to recognise and route, not enough to act. |
| **Everything else** | Not carried. **Reachable on request** — the master map is always queryable. |

**No agent is ever unable to find something.** It simply is not carrying all of it.
That distinction is what separates scoping from hiding, and the master map stays
one source (`charter.md` §11's routing rule applies to the map itself).

Scoping serves the four failure modes CSJ named:

- **Duplication** — you know what already exists in your domain
- **Redundancy** — you know what exists adjacent, so you route rather than rebuild
- **Regression** — you know what consumes what you are about to change
- **Frustration** — you are not reading 446 services to fix one

### 6.4 Freshness is part of the map

Every entry carries `verified: <date>`. Three feeds keep it current:

1. **On merge** — any merge updates the capabilities it touched. The evidence pack
   already enumerates them.
2. **On discovery** — prior-art checks, encounters, Quartermaster contradiction
   findings.
3. **Full re-survey** — quarterly, alongside the doctrine review.

**Stale entries are marked, not hidden.** A prior-art check that lands on an entry
older than the staleness threshold must re-verify it before relying on it. Silent
staleness is how a map starts lying.

### 6.5 Standing duty

The Cartographer reports to the Chief of Staff, works continuously, and produces a
completion report only on survey milestones. Its map views are consumed by every
other agent, so **a Cartographer that stalls degrades everyone** — its liveness is
watched accordingly (`rhythm.md` §5).

---

## 7. Mapped capabilities — survey 2026-08-13

Survey of work item **W-0004**. Closes the "found, not yet mapped" entry in §4 for
the autonomous bug-fix loop, and verifies three previously-unverified entries.

### 7.0 FINDING — the collision risk is real and unmitigated

**There is no claim marker of any kind on a bug issue.** A Build lead cannot tell,
by looking at an open GitHub issue, whether the autonomous loop has already taken
it. Established by reading the whole path:

- The only signals on an issue are the labels `bug`, `from-mobile`, `claude-auto`
  (`config/services.php:87`) and the `@claude` title/body prefix
  (`GithubIssueService.php:132`, `:153`). **All three are set at creation time by
  the reporter, not on claim by the fixer.**
- The workflow **never sets an assignee, never adds an `in-progress` label, never
  transitions status, and takes no lock** (`.github/workflows/claude.yml` in full).
  Its `concurrency` group `claude-autofix-${{ issue.number }}`
  (`claude.yml:54-56`) de-duplicates *this workflow against itself* only. It is
  invisible to, and offers no protection from, any human or any agent.
- Nothing in `workforce/` references `claude-auto`, `from-mobile`, or issue
  assignment — verified by grep across the whole workforce tree. The Build lead has
  no read path into this loop's state.

**Two directions of collision, both live:**

1. **Lead takes an issue the loop is already on.** An open `claude-auto` issue with
   no PR yet is indistinguishable from a run that is mid-flight, a run that failed,
   and a run that never fired. All three look identical.
2. **Lead accidentally *starts* the loop.** The workflow also fires on
   `issue_comment` containing the literal string `@claude` from any
   OWNER/MEMBER/COLLABORATOR (`claude.yml:73-78`). A Build lead commenting
   "@claude already looked at this" on any issue triggers an autonomous run that
   checks out `dev`, edits files and opens a PR.

**Mitigation is a work item, not a note.** Minimum viable: the loop claims by
assignee or an `agent-claimed` label before it starts, and the Build lead's
prior-art check reads that field. Until then, treat every `claude-auto`-labelled
issue as potentially claimed. This is recorded, not fixed — fixing it is outside a
Cartographer's remit.

**Partial cover, do not mistake for mitigation:** the loop cannot reach `main` or
production. It checks out `dev` (`claude.yml:81-85`) and targets `dev`
(`claude.yml:115`). Anything it lands still passes through the normal `dev → main`
release PR.

### 7.1 Bug intake → GitHub issue → autonomous Claude fix loop

`verified: 2026-08-13`

| Dimension | Detail |
|---|---|
| **Capability** | A user files an in-app bug report; it becomes a GitHub issue; a Claude Code GitHub Action autonomously diagnoses, fixes, tests and opens a PR to `dev`, optionally auto-merging it. |
| **Surface** | **All four clients + background.** Web (`resources/js/services/bugReportService.js:37`), `/m` (`resources/mobile/views/BugReportSheet.vue:118`, mounted app-wide at `resources/mobile/App.vue:11`), iOS native (`ios-native/Fynla/Features/BugReport/BugReportClient.swift:17`, `platform: "ios"` at `:59`), API (`POST /api/bug-report`, `routes/api.php:1477-1478`), background (GitHub Actions runner). |
| **Consumers** | GitHub repo `Stoff73/fynla` issues + PRs; the `dev` branch; the `chris@fynla.org` inbox; `throttle:bug-reports`; `PreviewWriteInterceptor::EXCLUDED_ROUTES` (`app/Http/Middleware/PreviewWriteInterceptor.php:82` — preview personas are *deliberately allowed* to file). Changing the label set in `config/services.php:87` silently stops the workflow firing. |

**Trigger.** A user action, not a schedule and not a webhook. Floating "Report a
problem" control on each client → `POST /api/bug-report`
(`routes/api.php:1477`, `auth:sanctum` + `throttle:bug-reports`) →
`BugReportController::store` (`app/Http/Controllers/Api/BugReportController.php:42`).

**Path, end to end.**

1. `BugReportController::store` validates ~20 diagnostic fields (`:44-65`), and
   when filed from Fyn chat attaches up to 40 turns of the user's own conversation,
   ownership-scoped via `AiConversation::forUser` (`:155-185`).
2. Two sinks, in order: `Mail::to('chris@fynla.org')->queue(BugReportMail)`
   (`:115`) — the source of truth — then, best-effort,
   `GithubIssueService::fromConfig()->createBugIssue()` (`:120`).
3. `GithubIssueService` (`app/Services/Integrations/GithubIssueService.php:61`)
   POSTs to `api.github.com/repos/{repo}/issues` with title
   `@claude [bug][<severity>] <80 chars>` (`:132`), a structured body prefixed
   `@claude please investigate, fix, test and verify this bug.` (`:153`), and
   labels `['bug','from-mobile','claude-auto']`. It **never throws into the request
   path** — failure logs and returns `null` (`:80-109`).
4. `.github/workflows/claude.yml` fires on `issues: [opened, labeled]` and
   `issue_comment: [created]` (`:40-44`), gated (`:66-78`) on **both** the author
   being OWNER/MEMBER/COLLABORATOR **and** (`@claude` in body OR `claude-auto`
   label).
5. It checks out `dev` (`:81-85`) and runs `anthropics/claude-code-action@v1`
   (`:88`) with a six-step prompt (`:104-119`): `systematic-debugging` →
   minimal fix → regression test + `./vendor/bin/pest` → Playwright browser verify
   → `tech-debt-session` → branch off `dev`, open PR to `dev` labelled
   `claude-auto` with `Closes #<n>`.
6. **Result: it opens a PR and then tries to merge it itself** —
   `gh pr merge --auto --squash` (`:121-126`). If branch protection rejects, it
   leaves the PR open and comments. If the bug is not reproducible it comments and
   stops (`:128-130`).

**Scope — effectively unbounded within the repo.** Runs with
`--permission-mode bypassPermissions` (`:144`) and `contents: write`,
`pull-requests: write`, `issues: write`, `id-token: write` (`:46-51`). The prompt
*asks* for scope discipline; **nothing mechanically restricts which files, modules
or surfaces it may edit.** Bounded only by: the trust gate, `--max-turns 25`
(`:143`), `--model claude-opus-4-8` (`:142`), and `dev` as the sole target.

**Autonomy — no human approves before it acts.** The only human gate is at merge,
and it is conditional: with `CLAUDE_BOT_TOKEN` set and on the `dev`
branch-protection bypass list it self-merges unreviewed (policy 8b,
`docs/specs/bug-report-claude-loop.md:161-174`); unset, it falls back to
`github.token` (`claude.yml:91`), which cannot bypass CODEOWNERS, and degrades to
a PR awaiting `@Stoff73` (`claude.yml:23-34`). **Whether prod is on 8a or 8b is a
repo-settings question the repository cannot answer.**

**Status — off by default in code; live state undetermined.**
`config/services.php:86` defaults `enabled` to `false`, and
`GithubIssueService::createBugIssue` returns early with no token, no repo, or not
enabled (`:63-65`). Local `.env` does **not** contain `GITHUB_BUG_ISSUE_TOKEN`
(verified by name search; no value read or recorded) → **off locally.** Both deploy
templates set `GITHUB_BUG_ISSUE_ENABLED=true`
(`deploy/fynla-org/.env.production.example:139`,
`deploy/csjones-fynla/.env.production.example:144`), but the June handover states
prod stays disabled pending a CSJ decision
(`June/June10Updates/handover-2026-06-10-session-1.md:24`). **These contradict.**
Server `.env` files and the repo secrets `CLAUDE_CODE_OAUTH_TOKEN` /
`CLAUDE_BOT_TOKEN` are not discoverable from the repository — **live status is
undetermined and must be confirmed with CSJ before anyone relies on it either way.**

**Failure mode worth naming:** if the `GITHUB_BUG_ISSUE_TOKEN` identity is not an
OWNER/MEMBER/COLLABORATOR on the repo, issues are created but the `issues` trigger
never fires (`claude.yml:63-65`) — a silent half-working loop that looks enabled.

**Provenance:** spec `docs/specs/bug-report-claude-loop.md`; commits `818f3bc56`
(original), `7071bc187`, `e11067cd1`, `4210343ba`, `be9b2e0ef`, `a6be31aa1`
(max-turns 10 → 25), `e5046c4dd` (Fyn transcript capture).

### 7.2 Lifecycle email engine

`verified: 2026-08-13`

| Dimension | Detail |
|---|---|
| **Capability** | Daily campaign sweep that emails churned and lapsed subscribers with signed feedback / update-payment links, and records what they clicked. |
| **Surface** | **Background** (send) + **web** (signed magic-link landing pages) + preferences on **web, API, iOS**. Not on `/m` — `resources/mobile/` has zero lifecycle references. |
| **Consumers** | `LifecycleEmailLog`, `FeedbackResponse`, signed routes `routes/web.php:32-41` → `LifecycleActionController`; `NotificationPreference` opt-outs on web (`NotificationPreferences.vue:89-90`), API (`Api/NotificationPreferenceController.php:42-43`), mobile API (`Api/V1/Mobile/NotificationPreferenceController.php:35-36`) and iOS (`PushModels.swift:106-107`). |

**Trigger:** scheduled artisan command, not an observer — `lifecycle:run-daily`
at 08:30 (`app/Console/Kernel.php:35`), implemented at
`app/Console/Commands/RunLifecycleEngine.php:14`. Enabled by default
(`config/lifecycle.php:8`, `LIFECYCLE_ENGINE_ENABLED` defaults `true`) and `true`
in both deploy templates. Only two campaigns registered
(`config/lifecycle.php:12-15`): `ChurnedSubscriberCampaign`,
`LapsedSubscriberCampaign`. `LifecycleEngine::dispatchEmail` sends
**synchronously, not queued** (`app/Services/Lifecycle/LifecycleEngine.php:139`).

**Known non-coverage / dead weight (recorded, not fixed):** seven mailables —
`WelcomeMail`, `GetStartedMail`, `DontMissOutMail`, `GreatJobMail`, `InsightsMail`,
`WeHaventSeenYouMail`, `WellDoneMail` — plus the `LifecycleMail` UTM base class and
their blades in `resources/views/emails/lifecycle/` are referenced by **no**
campaign, command or controller. The two live mailables extend `Mailable` directly.
The `lifecycle.apply-discount` route (`routes/web.php:33-34`) has no campaign that
generates it. **Anyone assuming Fynla sends welcome or re-engagement emails is
wrong — it sends exactly two.** Removal candidate for the Quality lead; not
asserted dead.

### 7.3 Awin affiliate

`verified: 2026-08-13`

| Dimension | Detail |
|---|---|
| **Capability** | Affiliate attribution and conversion reporting: click-cookie capture, browser MasterTag + fallback pixel, and server-to-server conversion postback. **No product feed export.** |
| **Surface** | **Web** (full: `resources/js/utils/awinTracking.js`, router hook `resources/js/router/index.js:1794-1801`, conversion at `CheckoutPage.vue:189,:543`). **API/background** (cookie middleware + S2S job). **Not on `/m`, not on iOS** — zero references in `resources/mobile/` or `ios-native/`. |
| **Consumers** | Global middleware `CaptureAwcCookie` (`app/Http/Kernel.php:106`); `EncryptCookies::$except` (`:19`); `SecurityHeaders.php:67` widens CSP only when enabled; `FireAwinConversionJob` dispatched from `PaymentController.php:742` and `WebhookController.php:136`; `payments.awin_*` columns. Disabling it changes CSP and payment-path behaviour. |

**Live status:** `config/awin.php:19` defaults `AWIN_ENABLED` to `false`. Prod
template sets `AWIN_ENABLED=true` and `VITE_AWIN_ENABLED=true`
(`deploy/fynla-org/.env.production.example:124,:130`); staging is off
(`deploy/csjones-fynla/.env.production.example:129,:135`). **Live server `.env` not
verifiable from the repo.** No API key or token exists in this integration — env
keys are `AWIN_ENABLED`, `AWIN_MERCHANT_ID`, `AWIN_S2S_BASE_URL`,
`AWIN_DEFAULT_COMMISSION_GROUP`, `AWIN_COOKIE_DOMAIN`, `AWIN_HTTP_TIMEOUT_SECONDS`
plus the four `VITE_AWIN_*`. `deploy/awin/` contains **only** `README.md`
(runbook; kill switch at `:213-230`) — no scripts.

### 7.4 The scheduled command set

`verified: 2026-08-13`. Source: `app/Console/Kernel.php:18-66`. **27 entries.**
Surface: background, all environments. Consumers: subscriptions, account deletion,
notifications, payments, AI audit and Fyn episodic memory.

| Cadence | Entries (Kernel.php line) |
|---|---|
| Every 3 min | `ai:conversations:summarise-stale --idle-minutes=3 --pause` (31-33, `withoutOverlapping`) |
| Every 5 min | job `PublishScheduledInsightsJob` (47) |
| Every 10 min | `payments:reconcile-pending --older-than=15` (42, `withoutOverlapping`); `apple:notifications:recover` (43-45, `withoutOverlapping`) |
| Every 30 min | `ai:conversations:summarise-stale` (59) |
| Hourly | `registrations:cleanup` (27) |
| Daily | `subscriptions:expire` 00:05 (22) · `accounts:execute-scheduled-deletions` 00:10 (23) · `accounts:execute-grace-deletions` 00:15 (24) · `accounts:send-deletion-reminders` 00:20 (25) · `subscriptions:check-overdue` 01:00 (41) · `sessions:cleanup` 02:00 (28) · job `AiIdempotencyCleanupJob` 03:30 (50) · `notifications:daily-insight` 08:00 (34) · `lifecycle:run-daily` 08:30 (35) · `subscriptions:send-renewal-reminders` 09:00 (20) · `data-retention:send-warnings` 09:00 (21) · `notifications:policy-renewals` 09:00 (36) · `protection:send-alerts` 09:15 (37) · `notifications:mortgage-rate-alerts` 09:30 (38) · `savings:send-alerts` 10:00 (39) · `estate:send-alerts` 10:30 (40) · `fyn:episodic:reconcile` 00:00 (64) |
| Weekly (Sun) | `audit:purge` 03:00 (29) · job `AiAuditRetentionJob` 04:00 (53) · `ai:audit:verify-chain` 04:30 (54) · `fyn:episodic:cold-archive` 00:00 (65) |
| Monthly | `accounts:purge-after-retention` 1st, 02:00 (26) |

**Operational facts worth carrying:** **no `onOneServer()` anywhere** and **no
`->environment()` gating anywhere** — on a multi-server deploy every daily job
would run per host. `withoutOverlapping()` on only three entries.
`ai:conversations:summarise-stale` is **registered twice** with different args and
cadence (31 and 59); only the 3-minute variant has overlap protection.

**Not scheduled** — 27 of 51 commands in `app/Console/Commands/` never run
automatically. Mostly one-shot backfills and dev tooling
(`fyn:episodic:backfill-blobs`, `fyn:user:erase`, `preview:reset`, `data:encrypt`,
the `*:backfill-*` family, `eval:*`). `fyn:episodic:purge` is manual by design
(`Kernel.php:62-63`). **The `CLAUDE.md` custom-command table is not a schedule** —
only four of the ten it lists actually run on a timer.

### 7.5 Known non-coverage found in this survey

| Not covered | Evidence | Consequence |
|---|---|---|
| **No claim/lock/assignee on autonomous-loop issues** | `.github/workflows/claude.yml` sets no assignee or status label; `concurrency` at `:54-56` scopes to this workflow only; no `claude-auto` reference anywhere in `workforce/` | Two agents can fix one bug. See §7.0. **The most important finding in this survey.** |
| **No welcome / onboarding / re-engagement email** | `config/lifecycle.php:12-15` registers two campaigns; seven mailables orphaned | Anyone specifying "the welcome email" is specifying something that does not send. |
| **Awin absent from `/m` and iOS** | Zero references in `resources/mobile/`, `ios-native/` | Conversions from mobile surfaces attribute only via the global cookie + server-side postback; no MasterTag. Breaks Rule 19 parity if anyone assumes coverage. |
| **Live enablement of the bug loop is not knowable from the repo** | Deploy templates say `true`; `June/June10Updates/handover-2026-06-10-session-1.md:24` says prod stays `false`; server `.env` and repo secrets not in the repo | Do not assert on/off in either direction. Confirm with CSJ. |
