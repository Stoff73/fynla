---
id: GATE-0002
workstream: quality
item: null
action: Apply the 2026-08-14 CLAUDE.md context audit — 9 edits across 6 rank-1 files, plus a new ios-native/CLAUDE.md
raised: 2026-08-14T14:10:00Z
decided_by: CSJ
decided_at: 2026-08-14T14:40:00Z
decision: approve
status: applied
---

## What is being asked

CSJ commissioned an audit of every `CLAUDE.md` and the `.claude/` tree, then said
"yes" to applying recommendations 1–8. **Every one of those recommendations lands
on a path `oversight-guard.sh` gates to a founder** (`(^|/)CLAUDE\.md`,
`\.github/CODEOWNERS`, `\.claude/settings(\.local)?\.json`,
`\.claude/hooks/[^/]*\.sh`). The guard denied the first edit. That is the guard
working, so the work is proposed here instead.

Full audit: `August/Aug14Updates/claude-context-audit-2026-08-14.md`.

Each edit below is exact and mechanical — old string, new string, verified value.

---

## Evidence

Every number was counted on the tree at `c9e64c2`, not read from prose.

### The headline: the mobile docs describe an app that no longer exists

`resources/js/CLAUDE.md:152-186` and root `CLAUDE.md:355-364` both document the
**Capacitor** app. Of 12 concrete file references, **9 do not exist**:

| Documented | Reality |
|---|---|
| `resources/js/mobile/` | Does not exist. Mobile SPA is `resources/mobile/` (25 `.vue`, own `api.js`/`router.js`/`store.js`/`tokens.js`) |
| `MobileLayout.vue`, `BiometricPrompt.vue`, `MobileLoginScreen.vue`, `VoiceInputButton.vue`, `appLifecycle.js` | None found anywhere in the repo |
| store modules `mobileDashboard`, `mobileNotifications` | Not in `resources/js/store/modules/` |
| `normaliseModule()` | **Its only occurrence in the entire repo is the CLAUDE.md sentence describing it** |
| `ModuleSummaryCard` / `ModuleSummary` | No such files |
| `platform.js`, `auth/mobileLogout`, `MobileDashboardAggregator`, `MobileLevelService` | Exist ✓ |

### `ios/` and `ios-native/` have no documentation at all

- `ios-native/` — **240 Swift files**, the app actually on TestFlight (build 6,
  2026-08-12). Only doc is `TESTFLIGHT.md`. **No `CLAUDE.md`.**
- `ios/` — legacy Capacitor target. **Last commit touching it: 2026-03-13.**
  `org.fynla.app` is **not on the App Store** (iTunes lookup `resultCount: 0`).
- Root `CLAUDE.md:357` still says packages 4–7 are "the **open PR chain**
  #634 → #636 → #635 → #637" — **all four merged 2026-07-22**; says "Nothing
  native has shipped" — TestFlight build 6 is live; says the Capacitor target
  "remains the legacy **shipped** app" — nothing is shipped; and points at
  worktree `/Users/CSJ/Desktop/fynla-ios-package7`, which is actually at
  `/Users/CSJ/Desktop/01 Fynla/Code and Worktrees/Linked Worktrees/fynla-ios-package7`.

**This is the documentation gap that cost a day on 2026-08-13** (see
`August/Aug14Updates/` iOS login investigation): nothing in any CLAUDE.md records
that the TestFlight build is `Fynla-Staging` and talks to the **csjones**
database, so a fynla.org account cannot log into it. Edit 9 fixes that.

### Counts, all stale

Root: services 214→**462** (dirs 32→**44**), Api controllers 89→**129**,
components 488→**517** (dirs 29→**34**), views 138→**158**, requests 83→**98**,
observers 12→**19**, JS services 45→**58**, Vuex 33→**35**.

`tests/`: Unit 123→**379**, Feature 77→**377**, Architecture 8→**39**,
"1,600+ cases"→**6,601 `it()` + 110 `test()`**, factories 64→**55**, and
`Browser/` (27 files, 24 BS-NN scenarios) plus the `Eval` suite are **absent from
the doc entirely** — while Rule 14 defines "done" by those very scenarios.

`database/`: seeders 23→**29**, factories 64→**55**.

### Hard errors

- `php artisan trials:expire` **does not exist**. Real signature:
  `subscriptions:expire` (`app/Console/Commands/ExpireSubscriptions.php`).
- `.github/CODEOWNERS` is documented as enforcing review; **deleted on
  `fix/widow-persona-cleanup`** (`ab339eb`), still present on `dev`. Interacts
  with GATE-0001 §"Interaction with CODEOWNERS removal".
- Design guide cited as **v1.3.0** (root), **v1.2.0** (`resources/js`,
  `designSystem.js`). Actual: **v1.3.1**.
- `app/Services/CLAUDE.md:62` comments `getTaxYear(); // '2025/26'`; root says
  active year is **2026/27**.
- `app/Services/CLAUDE.md:9` "all **9** module agents" — 9 files, one of which is
  `BaseAgent`. 8 module agents. Root names **7** and omits
  **`TaxOptimisationAgent`** entirely.
- `app/Http/CLAUDE.md:115` "**All** routes in `routes/api.php`" — hides
  `routes/api_v1.php` (the whole `/api/v1/native/*` iOS surface and its
  `IdentifyNativeClient` / `EnforceNativeVersion` / `EnsureActiveNativeSession` /
  `CaptureNativeDeviceLabel` middleware) and `routes/e2e.php`.

---

## The edits

### 1. Root `CLAUDE.md` — delete the metrics table

Every figure is wrong and none changes a decision. **Deleted, not corrected** — a
hand-maintained count is wrong again next month.

```
OLD (lines 9-18):
| Metric | Count |
|--------|-------|
| Vue Components | 675 |
| PHP Services | 446 |
| Controllers | 128 |
| Models | 134 |
| Vuex Stores | 35 |
| Agents | 9 |

**Production**: https://fynla.org | **Version**: v1.0

NEW:
**Three clients, one backend:** the desktop web SPA (`resources/js/`), the `/m`
mobile web pathway (`resources/mobile/`), and the native SwiftUI iOS app
(`ios-native/`). See Rule 19 and the Mobile Clients section.

**Production**: https://fynla.org | **Version**: v1.0
```

### 2. Root `CLAUDE.md:60` — the command that errors

```
OLD: | `php artisan trials:expire` | Expire ended trial subscriptions |
NEW: | `php artisan subscriptions:expire` | Expire ended trial/lapsed subscriptions |
```

### 3. Root `CLAUDE.md:76-90` — drop the rotting counts, add the missing agent

```
OLD:76  - `Agents/` - Module orchestrators (ProtectionAgent, SavingsAgent, InvestmentAgent, RetirementAgent, EstateAgent, GoalsAgent, CoordinatingAgent)
NEW:76  - `Agents/` - Module orchestrators extending `BaseAgent` (Protection, Savings, Investment, Retirement, Estate, Goals, TaxOptimisation, Coordinating)

OLD:77  - `Services/{Module}/` - Domain calculations (214 services across 32 module directories)
NEW:77  - `Services/{Module}/` - Domain calculations, one module per directory

OLD:78  - `Http/Controllers/Api/` - API endpoints (89 controllers)
NEW:78  - `Http/Controllers/Api/` - API endpoints

OLD:79  - `Http/Requests/` - Form request validation (83 classes)
NEW:79  - `Http/Requests/` - Form request validation

OLD:83  - `Observers/` - Risk recalculation observers, goal contribution trackers, Monte Carlo triggers (12 observers)
NEW:83  - `Observers/` - Risk recalculation, goal contribution tracking, Monte Carlo triggers

OLD:87  - `components/{Module}/` - Vue components (488 across 29 module directories)
NEW:87  - `components/{Module}/` - Vue components, organised by module

OLD:88  - `views/` - Page-level route components (138 views)
NEW:88  - `views/` - Page-level route components

OLD:89  - `store/modules/` - Vuex state management (33 namespaced modules)
NEW:89  - `store/modules/` - Vuex state management (all namespaced)

OLD:90  - `services/` - API wrappers (45 services)
NEW:90  - `services/` - API wrappers
```

### 4. Root `CLAUDE.md:169` — design guide version

```
OLD: read `./fynlaDesignGuide.md` (v1.3.0)
NEW: read `./fynlaDesignGuide.md` (v1.3.1)
```
Same fix in `resources/js/CLAUDE.md:89` (`v1.2.0` → `v1.3.1`). `designSystem.js:7`
and `:200` also say v1.2.0 — **not gated**, but left alone pending this decision
so all four move together.

### 5. Root `CLAUDE.md:344` — CODEOWNERS

**Resolved by evidence — no founder choice needed.** Queried live
(`gh api repos/Stoff73/fynla/branches/{main,dev}/protection`, 2026-08-14):

```
main / dev — IDENTICAL
  required_approving_review_count: 0
  require_code_owner_reviews:      false
  required_status_checks:          null
  enforce_admins:                  false
  allow_force_pushes:              false
```

`require_code_owner_reviews` is **false on both branches**, so GitHub never
enforced CODEOWNERS. The claim in `CLAUDE.md:344` was already false *before*
`ab339eb` deleted the file; the deletion changed nothing operationally. Apply:

```
OLD: - `.github/CODEOWNERS` forces `@Stoff73` as a required reviewer on every PR.
NEW: - PR review is **not** enforced by GitHub on `dev` or `main`
       (`required_approving_review_count: 0`, `require_code_owner_reviews: false`,
       no required status checks, `enforce_admins: false`). CODEOWNERS was removed
       2026-08-14 (`ab339eb`); it was never being enforced. The merge gate is the
       evidence pack, which is process, not mechanism.
```

**This also falsifies GATE-0001's stated premise.** That gate says *"GitHub branch
protection is still in place and may still hold — unverified."* It does not hold:
zero required approvals, no required status checks, admins unenforced. Nothing
mechanical stops `claude.yml`'s `gh pr merge --auto --squash` from self-merging to
`dev`. GATE-0001 was approved on the assumption that something might. Flagged to
CSJ 2026-08-14; GATE-0001 is decided and is not edited here.

### 6. `app/Http/CLAUDE.md:115` — stop hiding the iOS API

```
OLD: All routes in `routes/api.php`, prefixed with `/api/`:

NEW: Three route files:
- `routes/api.php` — the web + `/m` API, prefixed `/api/`
- `routes/api_v1.php` — the **native iOS** surface, prefixed `/api/v1/`. Native
  auth/session lives here (`/native/auth/session/exchange|refresh`), behind
  `native.client` (`IdentifyNativeClient`), `native.version`
  (`EnforceNativeVersion`) and `native.session` (`EnsureActiveNativeSession`).
  **These routes do not exist on production** — see the Mobile Clients section.
- `routes/e2e.php` — browser-scenario support, non-production only
```

### 7. `app/Services/CLAUDE.md` — three corrections

```
OLD:9   All 9 module agents extend `BaseAgent` and implement three required methods:
NEW:9   Every module agent extends `BaseAgent` and implements three required methods:

OLD:62  $taxYear = $taxConfig->getTaxYear();  // '2025/26'
NEW:62  $taxYear = $taxConfig->getTaxYear();  // '2026/27' (the active year; never hardcode it)

OLD:96  Observers exist for: User, Property, InvestmentAccount, SavingsAccount, DCPension, FamilyMember (risk), InvestmentAccountGoal, SavingsAccountGoal (goal tracking), LifeEventMonteCarlo (Monte Carlo triggers).
NEW:96  Observers cover risk recalculation (User, Property, InvestmentAccount, SavingsAccount, DCPension, FamilyMember), goal tracking (InvestmentAccountGoal, SavingsAccountGoal) and Monte Carlo triggers (LifeEventMonteCarlo). See `app/Observers/` for the full set.
```

### 8. `tests/CLAUDE.md:9-26` — add the suites Rule 14 depends on

```
OLD:
tests/
  Unit/           123 test files - Isolated service/agent/model tests
    Agents/       Agent orchestration tests
    Models/       Model domain logic
    Services/     Service calculations (organised by module)
  Feature/        77 test files - API endpoint integration tests
    Api/          General API tests
    Auth/         Authentication flow
    Estate/       Estate module endpoints
    Protection/   Protection module endpoints
    Savings/      Savings module endpoints
    Security/     Security-specific tests
  Architecture/   8 test files - Code standards enforcement
  Integration/    3 test files - Multi-step workflow tests

Total suite: 1,600+ individual `it()` cases.

NEW:
tests/
  Unit/           Isolated service/agent/model tests
    Agents/       Agent orchestration
    Models/       Model domain logic
    Services/     Service calculations (organised by module)
  Feature/        API endpoint integration tests
    Api/ Auth/ Estate/ Protection/ Savings/ Security/
  Architecture/   Code standards enforcement (Pest arch tests)
  Integration/    Multi-step workflow tests
  Browser/        Playwright end-to-end scenarios
    scenarios/    BS-NN-*.php — the Rule 14 acceptance contract
  Eval/           Fyn evaluation runs (own testsuite, not part of ./vendor/bin/pest)

Six testsuites are declared in `phpunit.xml`: Unit, Feature, Integration,
Architecture, Browser, Eval.

**`tests/Browser/scenarios/BS-NN-*.php` is where Rule 14 lives.** The docblock at
the top of each scenario IS the acceptance contract — every assertion in it must
hold (DB row, SSE shape, audit chain, UI card, no fabricated success) before the
work is done. Do not treat a green unit suite as satisfying a BS-NN scenario.
```

Also `tests/CLAUDE.md:118` `64 factories in database/factories/` → `Factories in
\`database/factories/\``, and `database/CLAUDE.md:94` `**23 seeder classes**` →
`**Seeder classes**`, `:128` `64 factories in \`database/factories/\`.` →
`Factories live in \`database/factories/\`.`

### 9. Root `CLAUDE.md:355-364` — replace the whole Mobile section

The current section is titled after a dead app and every bullet describes it.
Replacement:

```markdown
## Mobile Clients (`/m`, `ios-native/`, `ios/`)

**Three clients, one backend.** Rule 19 governs: work is not done until it is
verified on web AND `/m`.

### `/m` — mobile web (`resources/mobile/`)
Phones are detected and routed to `/m`, which iframes the funnel and serves an
**isolated** Vite bundle (`vite.mobile.config.js` → `public/m-build/`) with its
own `api.js`, `router.js`, `store.js` and `tokens.js`. It does **not** share the
web SPA's store, router or services — a fix in `resources/js/` does not reach
`/m`. API base is chosen at runtime (`resources/mobile/api.js:19-21`):
`Capacitor.isNativePlatform()` → `VITE_API_BASE_URL`; browser → same-origin with
the `VITE_ROUTER_BASE` subdirectory prefix.

### `ios-native/` — the native SwiftUI app (current)
240 Swift files. Packages 1–7 all merged to `dev` (#630–#633, #634–#637,
2026-07-22); TestFlight hotfix chain #685–#689 shipped **build 6** (2026-08-12).
Two schemes:

| Scheme | Backend | Bundle ID | Notes |
|---|---|---|---|
| `Fynla-Staging` | `https://csjones.co/fynla` | `org.fynla.app.dev` | The build on TestFlight |
| `Fynla-Production` | `https://fynla.org` | `org.fynla.app` | **Login cannot work yet** |

**⚠️ The TestFlight app reads the csjones STAGING database.** An account created
on fynla.org does not exist there — login returns 401 with audit
`reason: user_not_found`, which the UI renders as "Invalid email or password".
Testers must register **on csjones.co/fynla**. Diagnosed 2026-08-13; see
`August/Aug14Updates/`.

**Production has no native endpoints.** `routes/api_v1.php` on fynla.org has zero
`native/auth` routes — probe `GET /api/v1/native/health`: prod returns
`200 text/html` (SPA fallback = route absent), csjones returns
`400 application/json`. A `Fynla-Production` build clears `/api/auth/login` then
404s at `/api/v1/native/auth/session/exchange`. Fixing this is a `dev → main`
release, not a code change.

Release pipeline: `ios-native/TESTFLIGHT.md`. Per-screen `/m` parity ledger:
`codex/plans/ios/2026-07-20-native-m-parity-ledger.md`.

### `ios/` — legacy Capacitor target (dormant)
Wraps `public/m-build` via `capacitor.config.ts`. **Untouched since 2026-03-13**;
`org.fynla.app` is not on the App Store. Build script `deploy/mobile/build-ios.sh`
and the `@capacitor/*` dependencies are still present. Do not develop against it
without asking CSJ — it is superseded by `ios-native/`, but not yet deleted.

Still load-bearing if you do build it:
- **Build:** `./deploy/mobile/build-ios.sh` (never `npx vite build` alone).
  After any mobile change, `php artisan cache:clear` (dashboard cached 5 min/user).
- **vite.config.js blank-screen rules:** never add `external` to `rollupOptions`
  for image/asset paths; always keep `transformAssetUrls: false` in the `vue()`
  plugin; always keep `!disablePWA && VitePWA(...)`.
- **Biometrics:** mobile logout uses `auth/mobileLogout` (local state only) —
  never `auth/logout`, which revokes the server token and breaks Face ID.
```

Line 357's stale status paragraph, and lines 359–364 (which point at the dead
`resources/js/CLAUDE.md` mobile section, the `mobile_capacitor_patterns.md`
memory that MEMORY.md itself flags as legacy, and the non-existent
`normaliseModule()` chain) are **deleted**.

### 10. `resources/js/CLAUDE.md:152-186` — replace the Mobile section

Delete all 35 lines. Replace with:

```markdown
## Mobile — not in this directory

The `/m` mobile SPA is **`resources/mobile/`**, an isolated bundle with its own
router, store and API client. Nothing under `resources/js/` is shared with it.
The native iOS app is `ios-native/` (SwiftUI). Both are covered in root
`CLAUDE.md` → Mobile Clients.

The only `resources/js/` file the mobile clients touch is
`store/modules/auth.js` — `auth/mobileLogout` clears local state without
revoking the server token. **Never call `auth/logout` from a mobile client**; it
revokes the token and breaks Face ID.
```

### 11. New file: `ios-native/CLAUDE.md`

There is no doc for 240 Swift files. Proposed (short — the detail belongs in root
and `TESTFLIGHT.md`):

```markdown
# Native iOS Conventions (SwiftUI)

Supplements root `CLAUDE.md`. Read the **Mobile Clients** section there first —
especially the staging-backend warning.

> **GOLDEN RULE #20:** every Fyn change is made ONCE, in ONE place, for ALL
> surfaces. That includes this app. A Fyn fix landed only in `resources/mobile/`
> is not done.

## Environment
`AppEnvironment` (`Fynla/App/AppEnvironment.swift`) reads `FYNLA_ENVIRONMENT`,
`FYNLA_API_BASE_URL`, `FYNLA_WEB_BASE_URL` from the Info.plist, fed by
`Configurations/{Staging,Production}.xcconfig`. URLs must be HTTPS with no
user-info — the initialiser throws otherwise. `Local.xcconfig` holds
`DEVELOPMENT_TEAM` and is gitignored; create it once per machine.

## Layering
`Core/API` (`APIClient`, `HTTPTransport`, `TolerantDecoding`) · `Core/Authentication`
(`AuthClient` protocols + `APIAuthClient` actor, `AuthenticationCoordinator`) ·
`Core/Keychain` · `Core/StoreKit` · `Features/{Area}/` (View + Model per screen).

- Networking goes through `APIClient`/`APIAuthClient` — never `URLSession` in a view.
- Decode with `TolerantDecoding`: the backend adds fields freely and a strict
  decoder turns an additive API change into a crash.
- Auth endpoints are `api/auth/*` (shared with web) plus
  `api/v1/native/auth/session/*` (native-only).

## Parity
Every screen must match `/m` on detail, functionality, states, intent and design.
Ledger: `codex/plans/ios/2026-07-20-native-m-parity-ledger.md`. When `/m` changes,
this app changes in the same PR.

## Design rules
Rules 8, 10–12, 15 apply here exactly as on web. The Fyn avatar is always allowed
(Rule 15). The `/m` dashboard gamification (level wheel, action progress,
percentile) is approved by design — never strip it.

## Testing / release
`FynlaTests` (unit) · `FynlaUITests` (incl. `LiveJourneyTests`, which needs the
`TEST_RUNNER_` env prefix). Release: `ios-native/TESTFLIGHT.md`.
Known: 6 StoreKit hosted-config unit tests are red locally, green in CI — never chase.
```

### 12. `.claude/references/` — delete 3 dead files

`accessibility-checklist.md`, `performance-checklist.md`, `testing-patterns.md`
are referenced by **nothing** (only `security-checklist.md` is used, by the
`security-and-hardening` skill). All tracked, so recoverable. **Not gated** —
awaiting a yes/no only so it lands with the rest.

---

## Not proposed here

- **`.claude/settings.json` + the two untracked hooks.** Tracked `settings.json`
  wires `hooks/oversight-guard.sh` and `hooks/workforce-guard.sh`, both
  **untracked**. Committing settings without them breaks every Bash/Write hook in
  any other checkout. Both paths are gated and it is CSJ's in-flight work —
  flagged, not touched.
- **Hook commands use absolute paths** (`/Users/CSJ/Desktop/fynla/.claude/...`) in
  a tracked file. `$CLAUDE_PROJECT_DIR` would fix it. Gated.
- **`settings.local.json`**: `enabledPlugins.github` contradicts `settings.json`;
  `autoMode.allow` still carries a blanket csjones SSH/artisan grant scoped in its
  own text to "the `fix/persona-split-review-fixes` deploy session"; ~10 junk
  permission entries from mis-parsed compound commands. Gated.
- **19 agents, overlapping remits, no routing rule** (`product-manager` vs
  `product-lead`; `design-lead` vs `premium-ui-designer` vs `ux-writing-expert`).
  The 8 workforce agents are untracked and unmentioned in CLAUDE.md. Needs a
  decision, not an edit.
- **13 worktrees**, 7 holding stale full copies of all six CLAUDE.md files (one a
  generation behind at 452 lines). `git worktree prune` plus deliberate removal.

## What happens if held

The mobile documentation stays actively wrong on the surface Rule 19 touches most
often, and the fact that cost a day on 2026-08-13 — TestFlight talks to csjones —
stays recorded only in a memory file and this gate. `trials:expire` stays in the
docs as a command that errors on invocation.

Nothing here is urgent enough to route around the guard. Held is a fine outcome;
silently applied would not be.

## Decision and reasoning

**CSJ: APPROVE — 2026-08-14.** CSJ disabled `oversight-guard.sh` in
`.claude/settings.json` so the workforce could apply the edits directly, and
directed the item-3 cleanup ("don't need redundant lines or breaking when I
commit work").

**Applied 2026-08-14.** All 12 edits landed. Verified: `trials:expire`,
`normaliseModule`, `ModuleSummaryCard`, `MobileLayout`, `VoiceInputButton`,
`v1.3.0`, `1,600+`, `64 factories`, `23 seeder`, `214 services`,
`89 controllers`, `33 namespaced` and the CODEOWNERS claim are all gone from
every CLAUDE.md. New `ios-native/CLAUDE.md` (56 lines). Both settings files
re-validated as JSON.

### Found while applying — three things not in the original proposal

1. **`.claude/settings.json` was invalid JSON.** CSJ disabled the guard with `//`
   comments, which JSON does not allow, so the file failed to parse and **every
   hook was silently off** — including `dangerous-command-guard` and
   `prod-guard`. Replaced with a clean structural removal of the single
   `oversight-guard` entry; all other hooks preserved. **CSJ must paste the entry
   back** (text in the handover). A guard disabled by commenting is a guard that
   takes the whole enforcement layer down with it.

2. **A plaintext SSH key passphrase sat in `settings.local.json`** —
   `SSH_ASKPASS="echo <passphrase>" ssh-add ~/.ssh/production`, i.e. the
   production key's passphrase in a config file. Entry removed. **The passphrase
   itself should be rotated** — removal from this file does not un-expose it.

3. **The allow-list pre-authorised deleting the guard layer.** Two standing
   rules: `Bash(rm .../.claude/hooks/eval-gate.sh ... security_reminder_hook.py
   ... vault_reminder_hook.sh)` and **`Bash(rmdir /Users/CSJ/Desktop/fynla/.claude/hooks/)`**.
   The second is blanket permission to remove the hooks directory — precisely the
   "make machinery report LESS" action `charter.md` §2 exists to prevent, granted
   in advance in a file the guard protects. Both removed.

Also done: the two workforce hooks staged (`git add`) so committing
`settings.json` no longer breaks other checkouts; the stale `autoMode` csjones
grant re-scoped from blanket write to read-only inspection; ~12 junk permission
entries removed; 3 unreferenced `.claude/references/` checklists `git rm`'d.

### Post-restore verification — the guard has two holes (2026-08-14)

CSJ restored the `oversight-guard` entry and asked for `settings.json` to be
checked. It is **valid JSON, all 11 hooks present, executable and wired, no
orphans** — but the guard is weaker than its own header claims.

1. **`oversight-guard.sh` is wired ONLY to `Write|Edit`.** Its header says
   *"Matchers: Write|Edit AND Bash"*, and it carries a whole Bash branch
   (`:63-71`) written to stop `sed -i` / `cat >` / `rm` / `mv` /
   `git checkout --`, noting *"Verified as a real bypass 2026-08-13"*. In
   `settings.json` the `Bash|mcp__ssh-fynla__ssh_exec` group runs
   **`workforce-guard.sh` only**. That entire anti-bypass branch is dead code as
   wired — the exact bypass it was written for is open.

2. **The `(^|/)` anchor fails on bare filenames in command strings.** Probed
   directly against the script:

   ```
   ALLOWED  rm CLAUDE.md          DENIED  rm ./CLAUDE.md
   ALLOWED  mv CLAUDE.md /tmp/x   DENIED  mv ./CLAUDE.md /tmp/x
   ALLOWED  sed -i s/a/b/ CLAUDE.md
   ALLOWED  rm CODEOWNERS         DENIED  rm .github/CODEOWNERS
   ```

   `(^|/)` is right for a `file_path` value; in a command the token is preceded
   by a **space**. Since agents work from the repo root and CLAUDE.md is at the
   root, `rm CLAUDE.md` is the natural form — the bypass is the common case.

   Fix both together (gated — CSJ applies):

   ```
   settings.json — add to the "Bash|mcp__ssh-fynla__ssh_exec" hooks array:
     { "type": "command",
       "command": "bash /Users/CSJ/Desktop/fynla/.claude/hooks/oversight-guard.sh",
       "timeout": 10,
       "statusMessage": "Checking workforce oversight boundary..." }

   oversight-guard.sh:39 — widen the anchor:
     OLD  (^|/)CLAUDE\.md
     NEW  (^|[/[:space:]"'])CLAUDE\.md
   ```

3. **Session caveat:** a probe `Edit` against `CLAUDE.md` reached the tool
   (failed on string-not-found, not on a hook deny), so the restored entry is
   **not yet live in the running session** — expect it on next session start.

### Still open (unchanged from "Not proposed here")

Absolute hook paths in a tracked file; 11 untracked `.claude/agents/*.md` with
overlapping remits and no routing rule; 13 worktrees holding stale CLAUDE.md
copies; `enabledPlugins.github` contradiction between the two settings files.
