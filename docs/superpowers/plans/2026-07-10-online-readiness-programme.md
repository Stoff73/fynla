# Fynla Online Readiness Programme Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Take the current `dev` release train through critical/high remediation, reproducible lint and test gates, automated and agent-led browser acceptance, an evidence-first Fyn guidance architecture, staging proof, and a controlled fynla.org production release, then deliver every still-executable July plan through separate continuation release trains.

**Architecture:** Work proceeds as a gated release train. Quality infrastructure lands first; every blocker then ships as a bounded feature branch through local tests, feature-branch deployment to csjones, independent browser verification on desktop and `/m`, and merge to `dev`. Before the framework upgrade and whole-product gauntlet, Fyn is consolidated into a hybrid evidence-first guidance system: deterministic engines establish truth, one Advice Case records evidence and policy, typed memory provides continuity, ordinary turns bypass the planner, and mechanical gates enforce the operating perimeter. The final initial-release `dev` tip receives the whole-product gauntlet, a seven-day staging soak, deployment/rollback rehearsal, CSJ go/no-go, and only then `dev -> main` production promotion. After its seven-day production check, the delivered-plan parity/polish work, provider expansion, investment campaign, and estate campaign each repeat the same release discipline as isolated continuations.

**Tech Stack:** PHP 8.3, Laravel 10 initially then Laravel 13, Sanctum, MySQL 8, Vue 3, Vuex, Vite 5, Tailwind 3, Pest 2, Vitest 3, Playwright, GitHub Actions, SiteGround cron/database queues, Sentry.

## Global Constraints

- Every critical and high finding in the July 2026 full-app, blind-spot, and Fyn audits is a production blocker until closed with evidence or proved inapplicable.
- All 34 artifacts in the verified July Updates inventory are restored to `dev`; every executable plan/work package has a delivered proof or a numbered task. A deleted branch never counts as a reason to omit its plan.
- All work branches from `dev`; all feature pull requests target `dev`; only the final release pull request targets `main`.
- A user-visible feature branch is deployed to csjones and browser-verified before merge, per `.agents/skills/release/SKILL.md`.
- "Done" means desktop web and `/m` mobile web unless CSJ explicitly excludes a surface; Capacitor iOS packaging is outside this programme.
- Advice Fyn remains read-only. Persistent writes continue through the server-side `delegate_to_capture` handoff; no frontend persona split is introduced.
- Fyn launches in mechanically fail-closed `guidance` mode. Targeted support and regulated advice remain disabled until separately permissioned, governed, tested and approved.
- Every substantive advice response has one structured Advice Case in `ai_advice_logs`, linked to the existing signed episodic record; no parallel advice-log store is introduced.
- Ordinary factual and single-module Fyn turns bypass the planner. Complex-turn planning remains shadowed until Task 22J evidence supports a narrower active route.
- Canonical financial state is always fetched live. Per-user Markdown semantic/episodic memory is migrated to typed SQL plus the existing signed episode subsystem before launch.
- No hardcoded UK tax values. Source values through `TaxConfigService`; 2027/28 values require current authoritative verification and CSJ acceptance.
- Never run `migrate:fresh`, `migrate:refresh`, `db:wipe`, `route:cache`, or `artisan optimize`.
- Run `php artisan db:seed` after any operation that modifies or removes local database data.
- Production and csjones builds use only their target-specific scripts; raw Vite production builds are forbidden.
- User-facing pages follow the design system, layout, no-score, copy, icon, enum, currency, form-modal, preview-isolation, and joint-asset rules in `AGENTS.md`.
- Every code task follows test-driven development: failing regression test, observed failure, minimal implementation, focused green, affected-lane green, then commit.
- Every completion claim uses fresh command output. Skipped Pest Browser stubs do not count as browser passes.
- Existing untracked `.agents/`, `.codex/`, `AGENTS.md`, `docs/security/security-review-2026-06-09.md`, and Python cache files are not part of this plan and must not be staged.
- The initial production release is Tasks 1-28 plus the inserted Tasks 22A-22J. Tasks 29-32 are included continuation releases and start only after Task 28's seven-day check is green.

---

## Programme file map

### Quality foundation

- `scripts/quality/run.sh` - canonical quality-lane dispatcher.
- `scripts/quality/php-syntax.sh` - tracked PHP syntax scan.
- `scripts/quality/policy-lint.sh` - forward-only Fynla policy lint.
- `scripts/quality/check-mobile-impact.mjs` - `/m` impact declaration gate.
- `eslint.config.js` - JavaScript/Vue lint configuration.
- `.github/workflows/quality.yml` - blocking pull-request quality workflow.
- `.github/workflows/nightly.yml` - full scheduled browser/dependency workflow.
- `.github/pull_request_template.md` - test and mobile-impact declaration.

### Browser foundation

- `playwright.config.js` - deterministic desktop/mobile Playwright projects.
- `scripts/e2e/prepare.sh` - guarded empty-MySQL-database preparation.
- `scripts/e2e/serve.sh` - Laravel and Vite orchestration.
- `routes/e2e.php` - test support routes registered only in `APP_ENV=e2e`.
- `app/Http/Controllers/TestSupport/E2EController.php` - verification-code and fixture lookup in the isolated E2E environment.
- `tests/E2E/fixtures/app.js` - shared page, request, console, and authentication fixtures.
- `tests/E2E/smoke/desktop.spec.js` - desktop smoke.
- `tests/E2E/smoke/mobile.spec.js` - `/m` smoke and phone redirect.
- `tests/E2E/auth/registration.spec.js` - real registration and verification flow.
- `tests/Browser/acceptance/*.yaml` - agent-executable acceptance contracts.
- `tests/Browser/acceptance/schema.json` - acceptance manifest schema.
- `tests/Browser/results/schema.json` - agent evidence schema.
- `scripts/quality/validate-acceptance.mjs` - manifest/result validator.

### Programme control

- `docs/online-readiness/july-plan-register.yaml` - one disposition and task/proof mapping for every July artifact and executable work package.
- `docs/online-readiness/audit-ledger.yaml` - finding-to-task/test/evidence ledger.
- `docs/online-readiness/release-manifest.md` - exact release diff and deployment scope.
- `docs/online-readiness/coverage-matrix.md` - persona/module/surface matrix.
- `docs/online-readiness/agent-browser-runbook.md` - interaction and evidence rules.
- `docs/online-readiness/rollback-runbook.md` - rehearsed production rollback.
- `docs/online-readiness/go-no-go.md` - final CSJ decision record.
- `docs/online-readiness/post-release.md` - 15-minute, 24-hour, and seven-day checks.

### Evidence-first Fyn architecture

- `docs/superpowers/specs/2026-07-10-fyn-evidence-first-advice-design.md` - approved hybrid CoALA/evidence-first design and regulatory operating perimeter.
- `docs/superpowers/plans/2026-07-10-fyn-evidence-first-advice.md` - executable Tasks 22A-22J with four browser checkpoints.
- `docs/online-readiness/fyn-advice-architecture-go-no-go.md` - operating-mode, planner, learning, migration, policy and rollback decision record.
- `app/Services/AI/Policy/` - operating-policy and mechanical response gate.
- `app/Services/AI/Advice/` - Advice Case value, holder and recorder over `AiAdviceLog`.
- `app/Services/AI/Fyn/FynEvidence*` - one immutable evidence snapshot shared by planner and reasoner.
- `app/Models/UserMemoryFact.php` plus `UserMemoryRepository` - typed, user-controllable relationship memory.

### Existing source contracts reconciled from `origin/main`

- All 34 artifacts under `July/July1Updates/`, `July/July3Updates/`, `July/July4Updates/`, `July/July5Updates/`, `July/July6Updates/`, and `July/July7Updates/` listed in `docs/superpowers/specs/2026-07-10-july-updates-inventory.md`.
- Executable plans include the SaveTax/gamification work packages, pension campaign plan, WP-5c milestone spec, investment campaign plan/spec, estate campaign plan/spec, blind-spot remediation plan/spec, and Fyn remediation plan/spec.

---

### Task 1: Reconcile source contracts and create the audit ledger

**Files:**
- Create on `dev`: all 34 artifacts in `docs/superpowers/specs/2026-07-10-july-updates-inventory.md`, copied from `origin/main`.
- Create: `docs/online-readiness/july-plan-register.yaml`
- Create: `docs/online-readiness/audit-ledger.yaml`
- Create: `docs/online-readiness/release-manifest.md`
- Create: `tests/Architecture/OnlineReadinessDocumentsTest.php`
- Modify after import: `July/July7Updates/fyn-ai-remediation-plan.md` only to correct `app/Services/AI/QuerySchemas.php` to `app/Constants/QuerySchemas.php`; record that correction in the document history.

**Interfaces:**
- Consumes: the verified 34-artifact inventory, `origin/main`, current `origin/dev`, and the online-readiness design.
- Produces: one source register with artifact dispositions/work-package mappings and one finding ledger with `id`, `source`, `severity`, `title`, `workstream`, `task`, `status`, `tests`, and `evidence` fields.

- [ ] **Step 1: Write the document-presence, source-register, and ledger-schema test**

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

it('keeps and registers the complete July Updates corpus on the dev line', function (): void {
    $register = Yaml::parseFile(base_path('docs/online-readiness/july-plan-register.yaml'));
    $registered = collect($register['artifacts'])->pluck('path')->sort()->values();

    $actual = collect([
        'July/July1Updates', 'July/July3Updates', 'July/July4Updates',
        'July/July5Updates', 'July/July6Updates', 'July/July7Updates',
    ])->flatMap(fn (string $directory) => collect(File::allFiles(base_path($directory))))
        ->filter(fn ($file) => in_array(strtolower($file->getExtension()), ['md', 'patch', 'jpeg', 'png'], true))
        ->map(fn ($file) => str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname()))
        ->sort()
        ->values();

    expect($register['source']['ref'])->toBe('origin/main')
        ->and($register['source']['commit'])->toBe('2e8357bef1c453da40e2c1991a462d8914b262e5')
        ->and($registered)->toHaveCount(34)
        ->and($registered->all())->toBe($actual->all());

    foreach ($register['artifacts'] as $artifact) {
        expect(array_keys($artifact))->toContain('path', 'kind', 'disposition', 'source_blob', 'programme_tasks', 'proof');
        expect($artifact['disposition'])->toBeIn([
            'delivered', 'launch_remediation', 'continuation', 'evidence_only', 'superseded',
        ]);

        if (in_array($artifact['disposition'], ['launch_remediation', 'continuation'], true)) {
            expect($artifact['programme_tasks'])->not->toBeEmpty($artifact['path']);
        }

        if ($artifact['disposition'] === 'delivered') {
            expect($artifact['proof'])->not->toBeEmpty($artifact['path']);
        }
    }
});

it('requires actionable fields on every launch finding', function (): void {
    $ledger = Yaml::parseFile(base_path('docs/online-readiness/audit-ledger.yaml'));

    expect($ledger['findings'])->toBeArray()->not->toBeEmpty();

    foreach ($ledger['findings'] as $finding) {
        expect(array_keys($finding))->toContain(
            'id', 'source', 'severity', 'title', 'workstream',
            'task', 'status', 'tests', 'evidence'
        );
        expect($finding['status'])->toBeIn(['open', 'in_progress', 'green', 'inapplicable', 'deferred']);

        if (in_array($finding['severity'], ['critical', 'high'], true)) {
            expect($finding['status'])->not->toBe('deferred');
        }
    }
});
```

- [ ] **Step 2: Run the test and observe the missing-file failure**

Run: `./vendor/bin/pest tests/Architecture/OnlineReadinessDocumentsTest.php`

Expected: FAIL because the complete July corpus, source register, and ledger are absent from the `dev` line.

- [ ] **Step 3: Restore the complete canonical July corpus from `origin/main`**

```bash
git checkout origin/main -- \
  July/July1Updates July/July3Updates July/July4Updates \
  July/July5Updates July/July6Updates July/July7Updates
```

Expected: 34 tracked artifacts appear as additions relative to `dev` and match `origin/main` before the one documented QuerySchemas path correction.

- [ ] **Step 4: Create the July source and plan register**

Record all 34 artifacts. `source_blob` is the blob identifier from `git rev-parse origin/main:<path>`. Use one or more `work_packages` for every executable heading in the SaveTax/gamification packages, pension campaign plan, WP-5c spec, investment/estate campaign plans, blind-spot remediation plan, and Fyn remediation plan:

```yaml
version: 1
source:
  ref: origin/main
  commit: 2e8357bef1c453da40e2c1991a462d8914b262e5
artifacts:
  - path: July/July3Updates/pension-campaign-plan.md
    kind: implementation_plan
    disposition: delivered
    source_blob: 0c7886f9abcf356d9f89b61e7427e4d67802fb8b
    programme_tasks: [24, 25, 29]
    proof: [9872133, b980709, a6e3705, 6f965f1]
  - path: July/July6Updates/investment-campaign-plan.md
    kind: implementation_plan
    disposition: continuation
    source_blob: 4bd75d153c8cc62cf7c0e1cc02a0a53f4ae70d3c
    programme_tasks: [31]
    proof: []
work_packages:
  - id: JULY-GAM-WP1
    source_path: July/July3Updates/gamification-recs-tasks-map.md
    source_heading: WP-1 Capture integrity
    disposition: delivered
    programme_tasks: [24, 25, 29]
    proof: [3d8d2b0, cb9f6a8]
```

Register historical handovers/screenshots as `evidence_only`; register the proposed Fyn patch as `superseded`; register delivered plans with merge proof; register launch work against Tasks 8-23; register continuation work against Tasks 29-32. No executable work package may have an empty `programme_tasks` list.

- [ ] **Step 5: Create the ledger with one record per audit finding**

Use this exact shape for every record:

```yaml
version: 1
release_base: origin/main
release_candidate: origin/dev
findings:
  - id: BS-TB-1
    source: blindspot-audit-2026-07-07
    severity: critical
    title: No date-driven tax-year rollover
    workstream: tax-rollover
    task: 18
    status: open
    tests:
      - tests/Feature/Tax/DateDrivenActivationTest.php
    evidence: []
```

Transcribe every critical/high finding from both July audits, plus each Fyn P0/P1 item. Include medium/low findings as `deferred` unless another task explicitly promotes them. Add delivered-plan regression records for SaveTax, WP-1–6/WP-5c, pensioncheck, PR #612, PR #613, and PR #614. Do not collapse two independently testable findings into one record.

- [ ] **Step 6: Write the initial release manifest**

Include these sections with captured command output:

```markdown
# Online Readiness Release Manifest

## Branch anchors
- Production main: capture the verbatim output of `git rev-parse origin/main`
- Staging dev: capture the verbatim output of `git rev-parse origin/dev`
- Merge base: capture the verbatim output of `git merge-base origin/main origin/dev`

## Release difference
- Commit counts: output of `git rev-list --left-right --count origin/main...origin/dev`
- Changed-file count and shortstat
- Main-only documentation commits that must survive merge
- July source-register commit/blob manifest

## Migrations
- Every path from `git diff --name-only origin/main...origin/dev -- database/migrations`

## Runtime surfaces
- PHP/source
- Desktop bundle
- `/m` bundle
- Public PHP pages
- Fyn corpus and prompt snapshots
- Scheduler and queue

## Deployment state
- csjones SHA and last verified date
- fynla.org SHA and last verified date
```

- [ ] **Step 7: Correct the stale Fyn source path and add a document-history line**

Change every `app/Services/AI/QuerySchemas.php` reference in the imported Fyn plan to `app/Constants/QuerySchemas.php`. Add: `2026-07-10 - corrected QuerySchemas source path after dev-line verification.`

- [ ] **Step 8: Run the architecture test**

Run: `./vendor/bin/pest tests/Architecture/OnlineReadinessDocumentsTest.php`

Expected: PASS, with exactly 34 registered/present artifacts, every executable work package mapped, and every critical/high record non-deferred.

- [ ] **Step 9: Commit**

```bash
git add July/July1Updates July/July3Updates July/July4Updates \
  July/July5Updates July/July6Updates July/July7Updates docs/online-readiness \
  tests/Architecture/OnlineReadinessDocumentsTest.php
git commit -m "docs: reconcile complete July planning corpus"
```

---

### Task 2: Add repository-owned PHP, Vue, and policy lint

**Files:**
- Create: `eslint.config.js`
- Create: `scripts/quality/php-syntax.sh`
- Create: `scripts/quality/policy-lint.sh`
- Create: `scripts/quality/check-mobile-impact.mjs`
- Modify: `package.json`
- Modify: `package-lock.json`
- Modify: `composer.json`
- Create: `tests/Architecture/QualityToolingTest.php`

**Interfaces:**
- Consumes: changed-file range `QUALITY_BASE..QUALITY_HEAD`, optional `PR_BODY`.
- Produces: `npm run lint`, `npm run lint:policy`, `composer lint:php`, and deterministic non-zero exits on violations.

- [ ] **Step 1: Write the tooling-contract test**

```php
<?php

declare(strict_types=1);

it('exposes canonical quality scripts', function (): void {
    $package = json_decode(file_get_contents(base_path('package.json')), true, flags: JSON_THROW_ON_ERROR);
    $composer = json_decode(file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($package['scripts'])->toHaveKeys([
        'lint', 'lint:policy', 'test:frontend', 'test:e2e:smoke', 'test:e2e:full',
    ]);
    expect($composer['scripts'])->toHaveKeys(['lint:php', 'quality']);

    foreach ([
        'eslint.config.js',
        'scripts/quality/php-syntax.sh',
        'scripts/quality/policy-lint.sh',
        'scripts/quality/check-mobile-impact.mjs',
    ] as $path) {
        expect(file_exists(base_path($path)))->toBeTrue($path);
    }
});
```

- [ ] **Step 2: Run the test and observe failure**

Run: `./vendor/bin/pest tests/Architecture/QualityToolingTest.php`

Expected: FAIL because the scripts/configuration do not exist.

- [ ] **Step 3: Install the conservative ESLint stack**

Run: `npm install --save-dev eslint@^9.39.1 @eslint/js@^9.39.1 eslint-plugin-vue@^10.6.2 globals@^16.5.0`

Expected: `package.json` and `package-lock.json` update; installation exits 0.

- [ ] **Step 4: Create `eslint.config.js`**

```js
import eslint from '@eslint/js';
import pluginVue from 'eslint-plugin-vue';
import globals from 'globals';

export default [
  { ignores: ['public/build/**', 'public/m-build/**', 'node_modules/**', 'vendor/**'] },
  eslint.configs.recommended,
  ...pluginVue.configs['flat/recommended'],
  {
    files: ['**/*.{js,vue}'],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: { ...globals.browser, ...globals.node },
    },
    rules: {
      'no-console': 'off',
      'no-undef': 'error',
      'no-unreachable': 'error',
      'no-constant-condition': ['error', { checkLoops: false }],
      'vue/multi-word-component-names': 'off',
      'vue/no-mutating-props': 'error',
      'vue/no-use-v-if-with-v-for': 'error',
      'vue/require-v-for-key': 'error',
    },
  },
  {
    files: ['tests/**/*.{js,vue}'],
    languageOptions: { globals: { ...globals.browser, ...globals.node } },
  },
];
```

If the first full lint exposes legacy-only style noise, disable only style rules in this config. Correctness rules remain errors; do not mass-format unrelated files.

- [ ] **Step 5: Create the PHP syntax script**

```bash
#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

while IFS= read -r file; do
  php -l "$file" >/dev/null
done < <(git ls-files 'app/*.php' 'app/**/*.php' 'config/*.php' \
  'database/*.php' 'database/**/*.php' 'routes/*.php' \
  'public/pages/*.php' 'public/pages/**/*.php')

echo "PHP syntax: clean"
```

- [ ] **Step 6: Create the forward-only policy lint**

`scripts/quality/policy-lint.sh` must:

```bash
#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

BASE="${QUALITY_BASE:-HEAD^}"
HEAD_REF="${QUALITY_HEAD:-HEAD}"
FILES="$(git diff --name-only "$BASE" "$HEAD_REF" -- \
  'resources/**/*.vue' 'resources/**/*.js' 'resources/**/*.css')"
violations=0

for file in $FILES; do
  [ -f "$file" ] || continue
  case "$file" in
    tests/*|public/*|database/*|resources/js/constants/designSystem.js|resources/css/app.css) continue ;;
  esac

  ADDED="$(git diff --unified=0 "$BASE" "$HEAD_REF" -- "$file" \
    | sed -n '/^+++ /d; s/^+//p')"

  if printf '%s\n' "$ADDED" | rg -n '(amber|orange|gray|primary|secondary)-[0-9]{2,3}'; then
    violations=1
  fi

  if [[ "$file" == *.vue ]] && printf '%s\n' "$ADDED" | rg -n '#[0-9A-Fa-f]{3,8}\b'; then
    violations=1
  fi

  if printf '%s' "$ADDED" | python3 -c '
import sys
text = sys.stdin.read()
raise SystemExit(1 if any(0x1F000 <= ord(c) <= 0x1FAFF or 0x2600 <= ord(c) <= 0x27BF for c in text) else 0)
'; then
    true
  else
    echo "$file: new emoji or Unicode icon detected"
    violations=1
  fi
done

exit "$violations"
```

Extend this script with the existing agent-hook exclusions so grandfathered palette/icon sources do not block.

- [ ] **Step 7: Create the `/m` impact declaration checker**

```js
import { execFileSync } from 'node:child_process';

const base = process.env.QUALITY_BASE || 'HEAD^';
const head = process.env.QUALITY_HEAD || 'HEAD';
const body = process.env.PR_BODY || '';
const files = execFileSync('git', ['diff', '--name-only', base, head], { encoding: 'utf8' })
  .trim().split('\n').filter(Boolean);

const webChanged = files.some((file) => /^resources\/js\/(views|components)\//.test(file));
const mobileChanged = files.some((file) => file.startsWith('resources/mobile/'));
const declaration = /Mobile impact:\s*(mobile-changed|shared-backend|no-counterpart-approved)/i.test(body);

if (webChanged && !mobileChanged && !declaration) {
  console.error('Desktop UI changed without /m files or a valid Mobile impact declaration.');
  process.exit(1);
}
```

- [ ] **Step 8: Add package and Composer scripts**

Add to `package.json`:

```json
"lint": "eslint resources/js resources/mobile tests/frontend tests/E2E *.config.js --max-warnings=0",
"lint:policy": "bash scripts/quality/policy-lint.sh",
"test:frontend": "vitest run",
"test:e2e:smoke": "playwright test --grep @smoke",
"test:e2e:full": "playwright test"
```

Add to `composer.json`:

```json
"lint:php": [
  "bash scripts/quality/php-syntax.sh",
  "@php vendor/bin/pint --test"
],
"quality": [
  "@lint:php",
  "@php vendor/bin/pest --testsuite=Architecture",
  "@php vendor/bin/pest --testsuite=Unit",
  "@php vendor/bin/pest --testsuite=Feature",
  "@php vendor/bin/pest --testsuite=Integration"
]
```

- [ ] **Step 9: Run focused verification**

Run:

```bash
./vendor/bin/pest tests/Architecture/QualityToolingTest.php
npm run lint
QUALITY_BASE=origin/dev QUALITY_HEAD=HEAD npm run lint:policy
composer lint:php
```

Expected: all commands exit 0. If Pint reports legacy drift, restrict the first PR's Pint gate to `git diff --name-only --diff-filter=ACM` PHP files and record a separate ratchet count; do not rewrite unrelated files.

- [ ] **Step 10: Commit**

```bash
git add eslint.config.js scripts/quality package.json package-lock.json composer.json \
  tests/Architecture/QualityToolingTest.php
git commit -m "chore: add repository quality lint gates"
```

---

### Task 3: Add the canonical quality runner and pull-request template

**Files:**
- Create: `scripts/quality/run.sh`
- Create: `.github/pull_request_template.md`
- Modify: `tests/Architecture/QualityToolingTest.php`

**Interfaces:**
- Consumes: lane name `lint|php|frontend|build|browser:smoke|browser:full|all`.
- Produces: one documented command used by agents and CI, with first-failure non-zero exit.

- [ ] **Step 1: Extend the architecture test with lane assertions**

Add an assertion that `scripts/quality/run.sh` contains every supported lane and that the pull-request template contains `Mobile impact:`, `Desktop browser evidence:`, and `/m browser evidence:`.

- [ ] **Step 2: Run the test and observe failure**

Run: `./vendor/bin/pest tests/Architecture/QualityToolingTest.php`

Expected: FAIL because the runner/template are missing.

- [ ] **Step 3: Create the lane dispatcher**

```bash
#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

lane="${1:-all}"

run_lint() {
  composer lint:php
  npm run lint
  npm run lint:policy
}

run_php() { composer quality; }
run_frontend() { npm run test:frontend; }
run_build() {
  ./deploy/csjones-fynla/build.sh
  test -f public/build/manifest.json
  test -f public/m-build/manifest.json
  ./deploy/fynla-org/build.sh
  test -f public/build/manifest.json
  test -f public/m-build/manifest.json
}

case "$lane" in
  lint) run_lint ;;
  php) run_php ;;
  frontend) run_frontend ;;
  build) run_build ;;
  browser:smoke) npm run test:e2e:smoke ;;
  browser:full) npm run test:e2e:full ;;
  all)
    run_lint
    run_php
    run_frontend
    run_build
    npm run test:e2e:smoke
    ;;
  *) echo "Unknown quality lane: $lane" >&2; exit 64 ;;
esac
```

- [ ] **Step 4: Create the pull-request template**

```markdown
## Scope
- Finding/task:
- User-visible change:

## Verification
- PHP command and result:
- Frontend command and result:
- Desktop browser evidence:
- /m browser evidence:

## Mobile impact
Mobile impact: mobile-changed

Allowed values: `mobile-changed`, `shared-backend`, `no-counterpart-approved`.

## Deployment
- csjones feature-branch SHA:
- Migration/corpus/cache actions:
- Rollback:
```

- [ ] **Step 5: Run the runner contract and lint lane**

Run:

```bash
./vendor/bin/pest tests/Architecture/QualityToolingTest.php
bash scripts/quality/run.sh lint
```

Expected: PASS and exit 0.

- [ ] **Step 6: Commit**

```bash
git add scripts/quality/run.sh .github/pull_request_template.md \
  tests/Architecture/QualityToolingTest.php
git commit -m "chore: add canonical quality runner"
```

---

### Task 4: Add blocking GitHub Actions quality workflows

**Files:**
- Create: `.github/workflows/quality.yml`
- Create: `.github/workflows/nightly.yml`
- Create: `tests/Architecture/QualityWorkflowTest.php`

**Interfaces:**
- Consumes: pull requests targeting `dev` or `main`; scheduled nightly runs.
- Produces: blocking `lint`, `php-tests`, `frontend-tests`, `builds`, and `browser-smoke` checks; retained artifacts for failures.

- [ ] **Step 1: Write the workflow-contract test**

```php
<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

it('defines every blocking quality job', function (): void {
    $workflow = Yaml::parseFile(base_path('.github/workflows/quality.yml'));
    expect($workflow['jobs'])->toHaveKeys([
        'lint', 'php-tests', 'frontend-tests', 'builds', 'browser-smoke',
    ]);
});

it('runs full browser and dependency checks nightly', function (): void {
    $workflow = Yaml::parseFile(base_path('.github/workflows/nightly.yml'));
    expect($workflow['jobs'])->toHaveKeys(['full-browser', 'dependency-audit']);
});
```

- [ ] **Step 2: Run the test and observe missing workflow failure**

Run: `./vendor/bin/pest tests/Architecture/QualityWorkflowTest.php`

Expected: FAIL because the workflows do not exist.

- [ ] **Step 3: Create `.github/workflows/quality.yml`**

Use these non-negotiable workflow properties:

```yaml
name: Quality Gate

on:
  pull_request:
    branches: [dev, main]

concurrency:
  group: quality-${{ github.event.pull_request.number }}
  cancel-in-progress: true

permissions:
  contents: read

env:
  APP_ENV: testing
  APP_KEY: base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=
  DB_CONNECTION: mysql
  DB_HOST: 127.0.0.1
  DB_PORT: 3306
  DB_USERNAME: root
  DB_PASSWORD: password
  DB_DATABASE: laravel_testing
  CACHE_DRIVER: array
  QUEUE_CONNECTION: sync

jobs:
  lint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with: { fetch-depth: 0 }
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3', tools: composer:v2 }
      - uses: actions/setup-node@v4
        with: { node-version: '20', cache: npm }
      - run: composer install --no-interaction --prefer-dist
      - run: npm ci
      - run: bash scripts/quality/run.sh lint
      - name: Mobile impact declaration
        env:
          QUALITY_BASE: ${{ github.event.pull_request.base.sha }}
          QUALITY_HEAD: ${{ github.event.pull_request.head.sha }}
          PR_BODY: ${{ github.event.pull_request.body }}
        run: node scripts/quality/check-mobile-impact.mjs

  php-tests:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: laravel_testing
        ports: ['3306:3306']
        options: >-
          --health-cmd="mysqladmin ping -h localhost -ppassword"
          --health-interval=10s --health-timeout=5s --health-retries=10
    strategy:
      fail-fast: false
      matrix:
        suite: [Architecture, Unit, Feature, Integration, Eval]
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3', tools: composer:v2 }
      - run: composer install --no-interaction --prefer-dist
      - run: ./vendor/bin/pest --testsuite=${{ matrix.suite }}

  frontend-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with: { node-version: '20', cache: npm }
      - run: npm ci
      - run: npm run test:frontend

  builds:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with: { node-version: '20', cache: npm }
      - run: npm ci
      - run: bash scripts/quality/run.sh build

  browser-smoke:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
        ports: ['3306:3306']
        options: >-
          --health-cmd="mysqladmin ping -h localhost -ppassword"
          --health-interval=10s --health-timeout=5s --health-retries=10
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3', tools: composer:v2 }
      - uses: actions/setup-node@v4
        with: { node-version: '20', cache: npm }
      - run: composer install --no-interaction --prefer-dist
      - run: npm ci
      - run: npx playwright install --with-deps chromium webkit
      - run: bash scripts/e2e/prepare.sh
        env: { E2E_DB_NAME: fynla_e2e_ci }
      - run: npm run test:e2e:smoke
        env:
          APP_ENV: e2e
          DB_DATABASE: fynla_e2e_ci
          PLAYWRIGHT_BASE_URL: http://127.0.0.1:8000
      - uses: actions/upload-artifact@v4
        if: always()
        with:
          name: playwright-smoke
          path: |
            playwright-report
            test-results
```

When writing the actual YAML, quote the top-level `on` key if Symfony YAML parsing would otherwise coerce it. Keep actions pinned at their reviewed major versions; pin to commit SHAs before branch protection makes the workflow mandatory.

- [ ] **Step 4: Create `.github/workflows/nightly.yml`**

The nightly workflow checks out `dev`, installs locked dependencies, runs `composer audit`, `npm audit --omit=dev`, `npm run test:e2e:full`, and uploads reports. Schedule at `02:30` UTC and permit `workflow_dispatch`.

- [ ] **Step 5: Run the workflow-contract test and a local YAML parse**

Run:

```bash
./vendor/bin/pest tests/Architecture/QualityWorkflowTest.php
./vendor/bin/yaml-lint .github/workflows/quality.yml .github/workflows/nightly.yml
```

Expected: PASS; both YAML files parse and all job keys exist.

- [ ] **Step 6: Commit**

```bash
git add .github/workflows/quality.yml .github/workflows/nightly.yml \
  tests/Architecture/QualityWorkflowTest.php
git commit -m "ci: add blocking quality workflows"
```

---

### Task 5: Repair Playwright and add an isolated E2E environment

**Files:**
- Modify: `playwright.config.js`
- Create: `scripts/e2e/prepare.sh`
- Create: `scripts/e2e/serve.sh`
- Create: `routes/e2e.php`
- Create: `app/Http/Controllers/TestSupport/E2EController.php`
- Modify: `app/Providers/RouteServiceProvider.php`
- Create: `tests/Feature/TestSupport/E2ERoutesTest.php`

**Interfaces:**
- Consumes: `APP_ENV=e2e`, `E2E_DB_NAME` ending in `_e2e` or starting `fynla_e2e_`, MySQL credentials from environment.
- Produces: isolated database, Laravel on `127.0.0.1:8000`, Vite on `127.0.0.1:5173`, test-support endpoints absent outside E2E.

- [ ] **Step 1: Write test-support route security tests**

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('does not register E2E support routes outside the e2e environment', function (): void {
    expect(app()->environment('e2e'))->toBeFalse();
    expect(Route::has('e2e.verification-code'))->toBeFalse();
});
```

Create a second process-level test script under `tests/Helpers/assert-e2e-routes.php` that boots the app with `APP_ENV=e2e` and asserts the route exists; invoke it from the architecture test with `Symfony\Component\Process\Process`, which is already installed.

- [ ] **Step 2: Run the non-E2E test**

Run: `./vendor/bin/pest tests/Feature/TestSupport/E2ERoutesTest.php`

Expected: PASS before implementation for absence; the E2E process assertion fails because `routes/e2e.php` is missing.

- [ ] **Step 3: Create the guarded database preparation script**

```bash
#!/usr/bin/env bash
set -euo pipefail

name="${E2E_DB_NAME:-}"
case "$name" in
  *_e2e|fynla_e2e_*) ;;
  *) echo "Refusing non-E2E database name: $name" >&2; exit 64 ;;
esac

mysql -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" \
  -u "${DB_USERNAME:-root}" --password="${DB_PASSWORD:-}" \
  -e "CREATE DATABASE IF NOT EXISTS \`$name\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

APP_ENV=e2e DB_DATABASE="$name" php artisan migrate --force
APP_ENV=e2e DB_DATABASE="$name" php artisan db:seed --force
```

This script creates or migrates only a name that passes the guard. It never drops a database or table.

- [ ] **Step 4: Register E2E routes only in the E2E environment**

Add to `RouteServiceProvider::boot()` after normal API/web route registration:

```php
if ($this->app->environment('e2e')) {
    Route::middleware('api')
        ->prefix('__e2e')
        ->group(base_path('routes/e2e.php'));
}
```

`routes/e2e.php` exposes only:

```php
use App\Http\Controllers\TestSupport\E2EController;
use Illuminate\Support\Facades\Route;

Route::get('/verification-code', [E2EController::class, 'verificationCode'])
    ->name('e2e.verification-code');
Route::get('/user', [E2EController::class, 'user'])
    ->name('e2e.user');
```

The controller validates email and returns only the latest code or the named user's non-secret state. It aborts with 404 unless `app()->environment('e2e')` even though the route is already environment-gated.

- [ ] **Step 5: Replace `playwright.config.js`**

```js
import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000';

export default defineConfig({
  testDir: './tests/E2E',
  timeout: 60_000,
  expect: { timeout: 10_000 },
  fullyParallel: false,
  forbidOnly: Boolean(process.env.CI),
  retries: process.env.CI ? 1 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: [['list'], ['html', { open: 'never' }], ['json', { outputFile: 'test-results/results.json' }]],
  use: {
    baseURL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    { name: 'desktop-chromium', use: { ...devices['Desktop Chrome'] } },
    { name: 'mobile-chromium', use: { ...devices['Pixel 7'] } },
    { name: 'mobile-webkit', use: { ...devices['iPhone 14'] } },
  ],
  webServer: [
    { command: 'bash scripts/e2e/serve.sh laravel', url: baseURL, reuseExistingServer: !process.env.CI, timeout: 120_000 },
    { command: 'bash scripts/e2e/serve.sh vite', url: 'http://127.0.0.1:5173', reuseExistingServer: !process.env.CI, timeout: 120_000 },
  ],
});
```

- [ ] **Step 6: Create `scripts/e2e/serve.sh`**

The `laravel` branch runs `php artisan serve --host=127.0.0.1 --port=8000` with `APP_ENV=e2e` and guarded `DB_DATABASE`. The `vite` branch runs `npm run dev -- --host 127.0.0.1 --port 5173`. Unknown arguments exit 64.

- [ ] **Step 7: Run route and Playwright discovery verification**

Run:

```bash
./vendor/bin/pest tests/Feature/TestSupport/E2ERoutesTest.php
APP_ENV=e2e php tests/Helpers/assert-e2e-routes.php
npx playwright test --list
```

Expected: the route is absent in testing, present in E2E, and Playwright lists tests from `tests/E2E` on a case-sensitive path.

- [ ] **Step 8: Commit**

```bash
git add playwright.config.js scripts/e2e routes/e2e.php \
  app/Http/Controllers/TestSupport/E2EController.php app/Providers/RouteServiceProvider.php \
  tests/Feature/TestSupport/E2ERoutesTest.php tests/Helpers/assert-e2e-routes.php
git commit -m "test: isolate and repair Playwright environment"
```

---

### Task 6: Replace stale browser helpers and add deterministic smoke tests

**Files:**
- Replace: `tests/E2E/helpers/auth.js`
- Replace: `tests/E2E/helpers/common.js`
- Create: `tests/E2E/fixtures/app.js`
- Create: `tests/E2E/smoke/desktop.spec.js`
- Create: `tests/E2E/smoke/mobile.spec.js`
- Create: `tests/E2E/auth/registration.spec.js`
- Modify: legacy `tests/E2E/01-*.spec.js` through `07-*.spec.js` to skip with a linked migration issue only until Task 24 replaces them; they must not run as false-green tests.

**Interfaces:**
- Consumes: isolated E2E app, real landing/register/login UI, `__e2e/verification-code` only in E2E.
- Produces: reusable `appTest`, `expectNoRuntimeErrors()`, `registerVerifiedUser()`, `selectPreviewPersona()`, and hard desktop/`/m` smoke gates.

- [ ] **Step 1: Create a failing desktop smoke test**

```js
import { test, expect } from '../fixtures/app.js';

test('@smoke desktop landing and preview dashboard boot', async ({ page, runtimeErrors, selectPreviewPersona }) => {
  await page.goto('/');
  await expect(page.getByRole('link', { name: /sign in/i })).toBeVisible();
  await selectPreviewPersona('young_family');
  await expect(page).toHaveURL(/\/dashboard/);
  await expect(page.getByRole('main')).toBeVisible();
  expect(runtimeErrors).toEqual([]);
});
```

- [ ] **Step 2: Create a failing `/m` smoke test**

```js
import { test, expect } from '../fixtures/app.js';

test('@smoke phone traffic reaches the mobile web application', async ({ page, runtimeErrors }) => {
  await page.goto('/');
  await expect(page).toHaveURL(/\/m(?:\/|$)/);
  const frame = page.frameLocator('iframe');
  await expect(frame.getByRole('main')).toBeVisible();
  expect(runtimeErrors).toEqual([]);
});
```

- [ ] **Step 3: Run the smoke tests and observe helper failures**

Run:

```bash
E2E_DB_NAME=laravel_e2e bash scripts/e2e/prepare.sh
APP_ENV=e2e DB_DATABASE=laravel_e2e npm run test:e2e:smoke
```

Expected: FAIL because `fixtures/app.js` and deterministic helpers do not exist.

- [ ] **Step 4: Implement `tests/E2E/fixtures/app.js`**

```js
import { test as base, expect } from '@playwright/test';

export const test = base.extend({
  runtimeErrors: async ({ page }, use) => {
    const errors = [];
    page.on('pageerror', (error) => errors.push(`pageerror: ${error.message}`));
    page.on('console', (message) => {
      if (message.type() === 'error') errors.push(`console: ${message.text()}`);
    });
    page.on('response', (response) => {
      const url = response.url();
      if (response.status() >= 500 && url.includes('/api/')) errors.push(`${response.status()} ${url}`);
    });
    await use(errors);
  },
  selectPreviewPersona: async ({ page }, use) => {
    await use(async (persona) => {
      await page.goto('/');
      await page.locator(`[data-persona="${persona}"]`).click();
      await page.waitForURL(/\/dashboard/);
    });
  },
  registerVerifiedUser: async ({ page, request }, use) => {
    await use(async ({ firstName, surname, email, password }) => {
      await page.goto('/register');
      await page.getByLabel(/first name/i).fill(firstName);
      await page.getByLabel(/surname/i).fill(surname);
      await page.getByLabel(/^email/i).fill(email);
      await page.getByLabel(/^password$/i).fill(password);
      await page.getByLabel(/confirm password/i).fill(password);
      await page.getByRole('button', { name: /create|register/i }).click();
      const response = await request.get(`/__e2e/verification-code?email=${encodeURIComponent(email)}`);
      expect(response.ok()).toBeTruthy();
      const { code } = await response.json();
      await page.getByLabel(/verification code/i).fill(code);
      await page.getByRole('button', { name: /verify/i }).click();
      await expect(page).toHaveURL(/\/dashboard/);
    });
  },
});

export { expect };
```

If the actual landing selectors lack stable test IDs, add only functional `data-testid` attributes to the persona controls; do not alter layout or copy.

- [ ] **Step 5: Add the real registration test**

Use a unique `e2e.<run>.<timestamp>@example.com`, complete verification through the helper, assert the resulting user endpoint shows `is_preview_user=false`, then log out and log back in through the UI.

- [ ] **Step 6: Remove weak helper behaviour**

Delete `waitForTimeout`, catch-and-ignore loading waits, optional `isVisible()` passes, random-number fixture values, and `toBeDefined()` assertions from the shared helpers. Replace each consumer with response/locator/database-state waits as it is migrated in Task 24.

- [ ] **Step 7: Mark unmigrated legacy specs honestly**

Use `test.describe.skip('legacy E2E migration tracked by online-readiness Task 24', ...)` at the outer describe of each old file. The smoke/auth tests remain active. This prevents the existing 55 weak tests from being counted as green while preserving them as migration inventory.

- [ ] **Step 8: Run smoke and auth tests**

Run:

```bash
APP_ENV=e2e DB_DATABASE=laravel_e2e npx playwright test tests/E2E/smoke tests/E2E/auth --project=desktop-chromium
APP_ENV=e2e DB_DATABASE=laravel_e2e npx playwright test tests/E2E/smoke/mobile.spec.js --project=mobile-chromium
APP_ENV=e2e DB_DATABASE=laravel_e2e npx playwright test tests/E2E/smoke/mobile.spec.js --project=mobile-webkit
```

Expected: all active tests pass; zero runtime errors; legacy files report explicit skips and are not represented as coverage.

- [ ] **Step 9: Commit**

```bash
git add tests/E2E
git commit -m "test: add deterministic desktop and mobile browser smoke"
```

---

### Task 7: Add the agent browser acceptance contract

**Files:**
- Create: `tests/Browser/acceptance/schema.json`
- Create: `tests/Browser/results/schema.json`
- Create: `tests/Browser/acceptance/release-smoke.yaml`
- Create: `scripts/quality/validate-acceptance.mjs`
- Create: `docs/online-readiness/agent-browser-runbook.md`
- Create: `tests/frontend/quality/acceptanceManifest.test.js`

**Interfaces:**
- Consumes: YAML manifest containing environment, user, surfaces, steps, assertions, evidence, and cleanup.
- Produces: validated agent-run JSON with commit SHA, environment URL, browser, viewport, step results, evidence paths, and redacted diagnostic notes.

- [ ] **Step 1: Write the failing manifest validator test**

```js
import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import yaml from 'yaml';

describe('agent acceptance manifest', () => {
  it('defines desktop and mobile interactions plus evidence', () => {
    const manifest = yaml.parse(readFileSync('tests/Browser/acceptance/release-smoke.yaml', 'utf8'));
    expect(manifest.surfaces.map((surface) => surface.name)).toEqual(['desktop', 'mobile']);
    for (const surface of manifest.surfaces) {
      expect(surface.steps.length).toBeGreaterThan(0);
      expect(surface.steps.every((step) => step.action && step.assert)).toBe(true);
    }
  });
});
```

Run `npm install --save-dev yaml@^2.8.1` before writing the test so YAML parsing is a direct locked dependency.

- [ ] **Step 2: Run the test and observe missing manifest failure**

Run: `npm run test:frontend -- tests/frontend/quality/acceptanceManifest.test.js`

Expected: FAIL because the manifest is absent.

- [ ] **Step 3: Create the acceptance schema and first manifest**

The YAML shape is:

```yaml
id: release-smoke
finding_ids: []
environment: csjones
user:
  kind: dedicated_qa
  email_ref: CSJONES_QA_EMAIL
surfaces:
  - name: desktop
    start_url: https://csjones.co/fynla
    steps:
      - action: click
        target: Sign in
        assert: Login form is visible
        evidence: 01-desktop-login.png
  - name: mobile
    start_url: https://csjones.co/fynla/m
    steps:
      - action: inspect-frame
        target: mobile application iframe
        assert: Mobile main region is visible
        evidence: 02-mobile-boot.png
negative_assertions:
  - no console errors
  - no unexpected 4xx or 5xx API responses
  - no desktop/mobile state divergence
cleanup:
  - retain only the standing QA account
```

The JSON result schema requires `manifest_id`, `commit_sha`, `started_at`, `finished_at`, `environment_url`, `browser`, `viewport`, `status`, `steps`, `negative_assertions`, and `evidence`.

- [ ] **Step 4: Implement the validator**

`validate-acceptance.mjs` loads YAML/JSON, validates required keys, rejects missing desktop or mobile surfaces for a user-visible manifest, rejects steps without both action and assertion, and rejects result files whose commit SHA differs from the requested release SHA.

- [ ] **Step 5: Write the agent browser runbook**

The runbook requires normal accessible interactions: click, fill, submit, wait, observe. It forbids direct DOM JavaScript clicks, state injection, snapshot-only acceptance, plaintext secrets, and fabricated evidence. It documents local MFA lookup, csjones test-user handling, production CSJ-code handling, database/SSE evidence, and cleanup.

- [ ] **Step 6: Run validation**

Run:

```bash
npm run test:frontend -- tests/frontend/quality/acceptanceManifest.test.js
node scripts/quality/validate-acceptance.mjs tests/Browser/acceptance/release-smoke.yaml
```

Expected: PASS and `release-smoke: valid`.

- [ ] **Step 7: Commit**

```bash
git add tests/Browser/acceptance tests/Browser/results scripts/quality/validate-acceptance.mjs \
  docs/online-readiness/agent-browser-runbook.md tests/frontend/quality/acceptanceManifest.test.js \
  package.json package-lock.json
git commit -m "test: define agent browser acceptance contract"
```

---

### Task 8: Fix Fyn query classification, KYC gating, and instruction/tool coherence

**Files:**
- Modify: `app/Constants/QuerySchemas.php`
- Modify: `app/Services/AI/QueryClassifier.php`
- Modify: `app/Services/AI/KycGateChecker.php`
- Modify: `app/Services/AI/AdvicePromptBuilder.php`
- Modify: `app/Agents/CoordinatingAgent.php`
- Create: `app/Constants/GateRoutes.php`
- Create: `tests/Architecture/GateRoutesTest.php`
- Consume: `app/Services/Tiers/TeaserGate.php`, the same capability service used by `app/Http/Middleware/EnsureFullHolisticAccess.php`.
- Modify: `app/Services/PrerequisiteGateService.php`
- Create: `tests/Unit/Services/AI/QueryRequiredDataTest.php`
- Modify: existing query-classifier and KYC tests under `tests/Unit/Services/AI/` and `tests/Feature/Fyn/`.

**Interfaces:**
- Consumes: classification `{primary, related, modules}` and `QuerySchemas::REQUIRED_DATA`.
- Produces: a primary-question-only KYC result; goals never block non-goals advice; blocked advice uses `GateRoutes` labels and never names a stripped tool or exposes an internal route.

- [ ] **Step 1: Record the decision-gate outcomes**

Before code, add evidence entries to `docs/online-readiness/audit-ledger.yaml` for:

- Advice signposting: `plain-text route guidance; navigate_to_page stays capture-side`.
- a1/a2 overlays: `activate both after loop guards land`.
- Holistic tier gate: `same gate as REST endpoint` or CSJ's recorded alternative.

The first two are the design recommendations. If CSJ selects a different option, amend the design document before implementation.

- [ ] **Step 2: Write failing classifier and KYC tests**

Cover at minimum:

```php
it('classifies on track for retirement as retirement readiness, not goals', function (): void {
    $result = app(QueryClassifier::class)->classify('Am I on track for retirement?');
    expect($result['primary'])->toBe(QuerySchemas::RETIREMENT_READINESS)
        ->and($result['related'])->not->toContain(QuerySchemas::GOALS_PROGRESS);
});

it('allows retirement advice without goals when retirement inputs exist', function (): void {
    $user = User::factory()->create([
        'date_of_birth' => '1985-01-01',
        'annual_employment_income' => 75000,
        'monthly_expenditure' => null,
    ]);
    DCPension::factory()->for($user)->create();

    $result = app(KycGateChecker::class)->check($user, [
        'primary' => QuerySchemas::RETIREMENT_READINESS,
        'related' => [],
        'modules' => ['retirement'],
    ]);

    expect($result['passed'])->toBeTrue()
        ->and($result['prompt_text'])->not->toContain('goals');
});

it('never instructs an unavailable advice tool', function (): void {
    $user = User::factory()->create(['date_of_birth' => null]);
    $result = app(KycGateChecker::class)->check($user, [
        'primary' => QuerySchemas::RETIREMENT_READINESS,
        'related' => [],
        'modules' => ['retirement'],
    ]);

    expect($result['prompt_text'])
        ->not->toContain('navigate_to_page')
        ->toContain('Personal Details')
        ->not->toContain('/profile');
});
```

- [ ] **Step 3: Run the tests and observe the current failure family**

Run: `./vendor/bin/pest tests/Unit/Services/AI/QueryRequiredDataTest.php --filter='retirement|unavailable'`

Expected: retirement wording can classify through goals/related gates, missing expenditure can block, and the prompt names `navigate_to_page`.

- [ ] **Step 4: Add a required-data vocabulary to `QuerySchemas`**

Add constants for `date_of_birth`, `income`, `expenditure`, `protection`, `savings`, `liabilities`, `retirement`, `investment`, `estate`, `goals`, and `property`. Define `REQUIRED_DATA` for every advice type. The minimum launch map is:

```php
public const REQUIRED_DATA = [
    self::PROTECTION_COVER => ['date_of_birth', 'income', 'expenditure'],
    self::PROTECTION_POLICY => ['protection'],
    self::SAVINGS_EMERGENCY => ['income', 'expenditure'],
    self::SAVINGS_ACCOUNTS => ['savings'],
    self::SAVINGS_DEBT => ['income', 'expenditure', 'liabilities'],
    self::RETIREMENT_CONTRIBUTION => ['income', 'retirement'],
    self::RETIREMENT_READINESS => ['date_of_birth', 'income', 'retirement'],
    self::RETIREMENT_DECUMULATION => ['date_of_birth', 'retirement'],
    self::INVESTMENT_PORTFOLIO => ['investment'],
    self::INVESTMENT_FEES => ['investment'],
    self::INVESTMENT_TAX => ['income', 'investment'],
    self::ESTATE_IHT => ['estate', 'property'],
    self::ESTATE_PLANNING => ['estate'],
    self::GOALS_PROGRESS => ['goals'],
    self::TAX_OPTIMISATION => ['income'],
    self::PROPERTY => ['property'],
    self::INCOME => ['income'],
    self::HOLISTIC_HEALTH => ['date_of_birth', 'income', 'expenditure'],
    self::AFFORDABILITY => ['income', 'expenditure'],
];
```

Each vocabulary key resolves through one private `KycGateChecker::checkRequirement(User $user, string $requirement): ?array` method. Module keys call `PrerequisiteGateService` only for that required module. Do not loop over related/implicit modules.

- [ ] **Step 5: Narrow the goals pattern**

Amend the `GOALS_PROGRESS` keyword patterns so phrases containing retirement, pension, mortgage, house, ISA, savings, or investment targets do not become goals-primary unless the user explicitly says goal or life event. Add exact positive and negative classifier cases.

- [ ] **Step 6: Replace unavailable-tool instructions**

Create the `GateRoutes` map from the real routers. In KYC, `AdvicePromptBuilder`, `CoordinatingAgent` blocked-tool results, and `PrerequisiteGateService` completeness context, use its human label and never expose the internal path:

```text
Tell the user which information is missing and signpost the exact page label in plain text. Do not output an internal route and do not call a navigation or write tool on the advice surface.
```

Keep capture/onboarding navigation behaviour unchanged.

- [ ] **Step 7: Enforce the Holistic Plan tier gate in Fyn**

Inject `TeaserGate` into `CoordinatingAgent` and check `isFull($user, 'holistic_plan')` at the start of `handleFinancialPlan`. A non-entitled user receives the same structured `upgrade_required`, Tier 2 message, and required-tier value as the REST middleware; an entitled user proceeds. Add free/tier2/preview tests so the Fyn tool cannot bypass the REST capability rule.

- [ ] **Step 8: Run focused and Fyn contract tests**

Run:

```bash
./vendor/bin/pest tests/Unit/Services/AI/QueryRequiredDataTest.php
./vendor/bin/pest tests/Architecture/GateRoutesTest.php
./vendor/bin/pest tests/Feature/Fyn tests/Feature/AI
```

Expected: PASS; goal-less retirement readiness is unblocked when its own inputs exist; no advice prompt names a stripped tool.

- [ ] **Step 9: Commit and verify on the feature branch**

```bash
git add app/Constants/QuerySchemas.php app/Services/AI/QueryClassifier.php \
  app/Constants/GateRoutes.php \
  app/Services/AI/KycGateChecker.php app/Services/AI/AdvicePromptBuilder.php \
  app/Agents/CoordinatingAgent.php app/Services/PrerequisiteGateService.php \
  tests/Architecture/GateRoutesTest.php tests/Unit/Services/AI tests/Feature/Fyn tests/Feature/AI \
  docs/online-readiness/audit-ledger.yaml
git commit -m "fix: gate Fyn advice only on required data"
```

Deploy the feature branch to csjones, but defer the live 19079 acceptance claim until Tasks 9 and 10 are also deployed.

---

### Task 9: Add provider-independent Fyn loop and repetition guards

**Files:**
- Modify: `app/Traits/HasAiChat.php:256-842`
- Modify: `app/Services/AI/StructuredResponseValidator.php:206`
- Create: `tests/Unit/Services/AI/FynLoopGuardTest.php`
- Create: `tests/Feature/AI/FynRepetitionRegressionTest.php`

**Interfaces:**
- Consumes: one streamed tool-loop turn.
- Produces: current-iteration assistant history, at most one execution per normalized tool call, collapsed consecutive repeated blocks, and a clean final cap pass replacing prior accumulated text.
- `StructuredResponseValidator::sanitiseWithViolations(string): array{response: string, violations: array<array{rule: string, detail: string, severity: string}>}` returns sanitised text and any sanitisation-time violations. Keep `sanitise(string): string` as a compatibility wrapper.

- [ ] **Step 1: Write failing guard tests**

Create scripted xAI responses with three identical `get_tax_information` calls and the same refusal paragraph repeated four times. Assert:

```php
expect($executor->callsFor('get_tax_information'))->toHaveCount(1);
expect(substr_count($persisted->content, 'I need a little more information'))->toBe(1);
expect(array_column($persisted->metadata['validation_violations'], 'rule'))
    ->toContain('repetition_collapsed');
```

Add a history assertion showing iteration two receives only iteration one's text, not the full accumulated response from all earlier iterations.

- [ ] **Step 2: Run the tests and observe duplicate execution/content**

Run: `./vendor/bin/pest tests/Unit/Services/AI/FynLoopGuardTest.php tests/Feature/AI/FynRepetitionRegressionTest.php`

Expected: FAIL with more than one tool execution and repeated persisted text.

- [ ] **Step 3: Separate `$iterationText` from `$fullResponse`**

At the start of every loop iteration:

```php
$iterationText = '';
```

Append streamed text to both buffers while streaming, but set the history assistant message from `$iterationText` only:

```php
if ($iterationText !== '') {
    $assistantMsg['content'] = $iterationText;
}
```

Never set the iteration history message from `$fullResponse`.

- [ ] **Step 4: Deduplicate normalized tool calls**

Initialize before the loop:

```php
$executedCalls = [];
```

For each call, recursively key-sort arguments, JSON-encode with `JSON_THROW_ON_ERROR`, and key by name plus SHA-256. On repeat, do not execute; inject a tool result stating the result was already supplied and increment the cap counter.

- [ ] **Step 5: Collapse repeated blocks in `StructuredResponseValidator`**

Add `sanitiseWithViolations()` and make the existing `sanitise()` return only its `response` value for backward compatibility. Split on blank-line paragraph boundaries, normalize whitespace/case for comparison, retain the first of three-or-more consecutive identical blocks, and return this structured violation when a collapse occurs:

```php
[
    'rule' => 'repetition_collapsed',
    'detail' => 'Collapsed consecutive repeated response blocks',
    'severity' => 'high',
]
```

Preserve lists whose items differ after normalization. In `HasAiChat`, call `sanitiseWithViolations()`, persist its `response`, then merge its `violations` with `validateAndLog()` before writing `metadata.validation_violations`. This makes the sanitisation event observable without introducing request-leaking mutable validator state.

- [ ] **Step 6: Make the cap pass replace accumulated text**

Before the tools-disabled final pass, reset `$fullResponse` and the iteration buffer. The final response replaces the failed tool-loop output rather than appending to it.

- [ ] **Step 7: Run focused, Fyn, and full PHP tests**

Run:

```bash
./vendor/bin/pest tests/Unit/Services/AI/FynLoopGuardTest.php tests/Feature/AI/FynRepetitionRegressionTest.php
./vendor/bin/pest tests/Feature/Fyn tests/Feature/AI tests/Unit/Services/AI
./vendor/bin/pest
```

Expected: all commands exit 0; the regression records one paragraph and one identical tool execution.

- [ ] **Step 8: Commit**

```bash
git add app/Traits/HasAiChat.php app/Services/AI/StructuredResponseValidator.php \
  tests/Unit/Services/AI/FynLoopGuardTest.php tests/Feature/AI/FynRepetitionRegressionTest.php
git commit -m "fix: guard Fyn tool loops from repetition"
```

---

### Task 10: Activate Fyn overlays and close web/`/m` state/event parity

**Files:**
- Modify: `fyn-memory/procedural/system_prompt_overlay/general/a1-answer-first.md`
- Modify: `fyn-memory/procedural/system_prompt_overlay/general/a2-ack-hygiene.md`
- Modify: prompt-overlay golden-master fixtures under `tests/fixtures/PromptOverlay/`
- Modify: `app/Services/Onboarding/OnboardingService.php:1009-1105`
- Modify: `resources/mobile/mixins/onboardingChat.js:44-49`
- Modify: `resources/mobile/api.js`
- Modify: `resources/js/store/modules/aiChat.js`
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php:2760-2840`
- Modify: `April/April24Updates/spec/00-canonical.md`
- Modify: `tests/Feature/Onboarding/WizardCompletionTest.php`
- Create/modify: frontend SSE fixture tests for desktop and `/m`.
- Create: `tests/Browser/acceptance/fyn-19079-repetition.yaml`

**Interfaces:**
- Consumes: unified Fyn stream events and completion state.
- Produces: matching server dispatch and client state on web/`/m`, specific messages for typed failures, visible capture events, and live incident evidence.

- [ ] **Step 1: Write failing completion-state and SSE tests**

Assert all three wizard completion methods clear `active_campaign`, `onboarding_fyn_path`, `onboarding_fyn_selection`, `onboarding_fyn_step`, and `onboarding_fyn_context.paused_at_step`. Add frontend tests for `token_limit`, `consent_required`, `handoff_error`, typed `error.message`, `entity_created`, `capture_complete`, and `skip_link` on `/m`; add the missing `level_up` desktop store case.

- [ ] **Step 2: Run the focused tests and observe parity failures**

Run:

```bash
./vendor/bin/pest tests/Feature/Onboarding/WizardCompletionTest.php
npm run test:frontend -- tests/frontend
```

Expected: stale campaign/path fields remain and the named surface-event cases fail or are absent.

- [ ] **Step 3: Clear server completion state in one transaction**

Extract one private `finaliseOnboardingState(User $user): User` method and call it from `skipToDashboard`, `completeOnboarding`, and `completeQuickOnboarding`. Preserve unrelated context keys while removing `paused_at_step`.

- [ ] **Step 4: Match the mobile predicate to server dispatch**

Use:

```js
return (store.user?.onboarding_completed === false || Boolean(store.user?.active_campaign))
  && store.user?.onboarding_fyn_step !== null;
```

Amend the canonical contract with the `active_campaign` re-entry disjunct and null-step requirement.

- [ ] **Step 5: Implement surface event parity**

For every typed event, render specific plain text without icons. `handleInlineCapture` emits deterministic failure text when the direct-write result contains `error=true`. Either consume `capture_write_result` on both clients or remove the orphan event after proving no contract needs it; record the choice in the canonical spec.

- [ ] **Step 6: Activate the two overlays and regenerate snapshots**

Set `active: true` in both overlay files. Run the existing overlay snapshot regeneration command/flag documented by the imported Fyn plan, then run the golden-master tests. Overlay files and regenerated fixtures land in the same commit.

- [ ] **Step 7: Create the live acceptance manifest**

The manifest provisions a completed-onboarding user with retirement data and zero goals, asks "Am I on track for retirement?" on desktop and `/m`, and asserts one clean answer, no goals gate, at most one identical tool execution, a sane `ai_messages` row length, matching surface state, and no repeated paragraph.

- [ ] **Step 8: Run local tests**

Run:

```bash
./vendor/bin/pest tests/Feature/Onboarding/WizardCompletionTest.php tests/Feature/Fyn tests/Feature/AI
npm run test:frontend
node scripts/quality/validate-acceptance.mjs tests/Browser/acceptance/fyn-19079-repetition.yaml
```

Expected: PASS.

- [ ] **Step 9: Deploy Tasks 8-10 together to csjones and run the agent acceptance**

Build with `./deploy/csjones-fynla/build.sh`, deploy the feature branch before merge, run the manifest on desktop and `/m`, and attach the redacted DB/tool-call evidence. Loop until the manifest is green.

- [ ] **Step 10: Commit**

```bash
git add fyn-memory/procedural/system_prompt_overlay tests/fixtures/PromptOverlay \
  app/Services/Onboarding/OnboardingService.php app/Services/Onboarding/OnboardingChatDirector.php \
  resources/mobile resources/js/store/modules/aiChat.js April/April24Updates/spec/00-canonical.md \
  tests/Feature/Onboarding/WizardCompletionTest.php tests/frontend \
  tests/Browser/acceptance/fyn-19079-repetition.yaml
git commit -m "fix: align Fyn completion and stream parity"
```

Update the Fyn incident records in the audit ledger to `green` only after live csjones evidence is recorded.

---

### Task 11: Install observability, personal-data scrubbing, and operational heartbeats

**Files:**
- Modify: `composer.json`, `composer.lock`
- Create: `config/sentry.php`
- Modify: `.env.example`
- Modify: `deploy/csjones-fynla/.env.production`
- Modify: `deploy/fynla-org/.env.production`
- Modify: `config/logging.php`
- Modify: `app/Exceptions/Handler.php`
- Modify: `app/Console/Kernel.php`
- Create: `app/Console/Commands/SchedulerHeartbeat.php`
- Create: `app/Console/Commands/AlertFailedJobs.php`
- Create: `tests/Feature/Observability/ObservabilityTest.php`

**Interfaces:**
- Consumes: exceptions, error logs, scheduled-command failures, failed job rows.
- Produces: Sentry events with user ID only, scrubbed context, scheduler missed-check alerts, and failed-job alerts.

- [ ] **Step 1: Write failing observability tests**

Use a fake Sentry transport/hub to assert an unhandled authenticated exception includes `user_id` but not email, national insurance number, income keys, authorization headers, tool arguments, or message content. Seed a `failed_jobs` row and assert `fyn:alert-failed-jobs` reports count/UUID/date without payload/exception body.

- [ ] **Step 2: Run tests and observe missing integration**

Run: `./vendor/bin/pest tests/Feature/Observability/ObservabilityTest.php`

Expected: FAIL because Sentry configuration/commands are absent.

- [ ] **Step 3: Install Sentry and publish configuration**

Run:

```bash
composer require sentry/sentry-laravel
php artisan vendor:publish --provider="Sentry\Laravel\ServiceProvider"
```

Keep DSN values empty in committed templates. Real values are operator-set on csjones and production; never edit local `.env` to make tests pass.

- [ ] **Step 4: Configure redaction and error-only logging**

Set `send_default_pii=false`, `traces_sample_rate=0`, and a `before_send` callback that recursively removes keys matching email, national insurance number, authorization, secret, token, password, annual/monthly income, currency/value, assembled context, tool results, and message content. Add a Sentry log channel at `error` level while retaining the single file channel.

- [ ] **Step 5: Wire exception context**

In `Handler::reportable`, set only numeric user ID, environment, request path template where available, and release SHA. Do not attach request bodies or full user models.

- [ ] **Step 6: Add scheduler failure hooks and heartbeat**

Add `onFailure` reporting to every scheduled command/job and `withoutOverlapping` to deletion, billing sync, audit retention, and episodic maintenance tasks. Schedule a heartbeat every fifteen minutes and connect it to a Sentry cron monitor.

- [ ] **Step 7: Add the failed-job watchdog**

Give `AlertFailedJobs` the exact signature `fyn:alert-failed-jobs`. It returns success for zero rows. For non-zero rows it reports count, oldest UUID, and `failed_at`, then returns failure so the scheduler hook also fires. It never includes the stored payload or exception text.

- [ ] **Step 8: Run verification**

Run:

```bash
./vendor/bin/pest tests/Feature/Observability/ObservabilityTest.php
php artisan schedule:list
composer audit
```

Expected: PASS; schedule lists heartbeat/watchdog and guarded tasks; Composer audit has no unresolved critical/high advisory.

- [ ] **Step 9: Deploy feature branch to csjones and prove one backend test event**

Set the staging DSN through CSJ-authorized server environment editing, clear/config-cache, trigger a controlled test exception, and confirm one event with staging tag and no personal data. Do not trigger the exception on production.

- [ ] **Step 10: Commit**

```bash
git add composer.json composer.lock config/sentry.php config/logging.php .env.example \
  deploy/csjones-fynla/.env.production deploy/fynla-org/.env.production \
  app/Exceptions/Handler.php app/Console/Kernel.php app/Console/Commands \
  tests/Feature/Observability
git commit -m "feat: add production error and scheduler monitoring"
```

---

### Task 12: Move queued work to a SiteGround-safe database queue

**Files:**
- Create: `database/migrations/2026_07_10_000001_create_queue_tables.php`
- Modify: `.env.example`
- Modify: `deploy/csjones-fynla/.env.production`
- Modify: `deploy/fynla-org/.env.production`
- Modify: `deploy/DEPLOY.md`
- Modify: all seven files under `app/Jobs/`
- Create: `tests/Support/QueueProofJob.php`
- Create: `tests/Feature/Queue/DatabaseQueueTest.php`
- Create: `tests/Unit/Jobs/JobFailureReportingTest.php`

**Interfaces:**
- Consumes: Laravel dispatches and SiteGround once-per-minute cron.
- Produces: persisted `jobs`, `job_batches`, `failed_jobs`, retry/failure reporting, and non-inline request behaviour.

- [ ] **Step 1: Write the failing async proof**

Create `Tests\Support\QueueProofJob`, implementing `ShouldQueue`, with a string cache-key constructor argument and a `handle()` method that writes that cache key. Use it explicitly in the feature test:

```php
use Tests\Support\QueueProofJob;

it('persists a database-queued job instead of running it inline', function (): void {
    config(['queue.default' => 'database']);
    Cache::forget('queue-proof');

    QueueProofJob::dispatch('queue-proof');

    expect(DB::table('jobs')->count())->toBe(1)
        ->and(Cache::get('queue-proof'))->toBeNull();
});
```

The proof job writes the cache marker only in `handle()`.

- [ ] **Step 2: Run and observe missing queue-table failure**

Run: `./vendor/bin/pest tests/Feature/Queue/DatabaseQueueTest.php`

Expected: FAIL because queue tables do not exist.

- [ ] **Step 3: Add one migration containing all queue tables**

Use Laravel's canonical schemas for `jobs`, `job_batches`, and `failed_jobs`. The down method drops only those three tables. Run `php artisan migrate`, then the mandatory `php artisan db:seed`.

- [ ] **Step 4: Switch deployed templates to database queues**

Set `QUEUE_CONNECTION=database` in both deployment templates. Keep local `.env.example` at `sync` with a comment that staging/production override to database. Document this exact SiteGround cron for each Laravel root:

```cron
* * * * * cd ~/www/csjones.co/fynla-app && php artisan queue:work --stop-when-empty --max-time=55 --tries=3 --sleep=3 >> storage/logs/worker.log 2>&1
* * * * * cd ~/www/fynla.org/public_html && php artisan queue:work --stop-when-empty --max-time=55 --tries=3 --sleep=3 >> storage/logs/worker.log 2>&1
```

- [ ] **Step 5: Add terminal `failed(Throwable $e): void` handlers**

Each job calls `report($e)`. `RunMonteCarloSimulation` also marks its status row failed. Remove the catch-and-return-success path from `RecalculateRiskProfileJob` so retries and `failed_jobs` work.

- [ ] **Step 6: Run queue tests and a real worker proof**

Run:

```bash
./vendor/bin/pest tests/Feature/Queue/DatabaseQueueTest.php tests/Unit/Jobs/JobFailureReportingTest.php
CACHE_DRIVER=file QUEUE_CONNECTION=database php artisan queue:work --once --tries=1
```

Expected: tests pass; the queued proof marker appears only after the worker command. Use the file cache for this cross-process proof because the array cache is process-local.

- [ ] **Step 7: Deploy to csjones and verify cron processing**

Run migrations, seed, switch the server environment after CSJ authorization, install the cron, dispatch a proof job, and show that the HTTP request returns before the next worker tick while the job is processed within one minute.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_07_10_000001_create_queue_tables.php .env.example \
  deploy/csjones-fynla/.env.production deploy/fynla-org/.env.production deploy/DEPLOY.md \
  app/Jobs tests/Support/QueueProofJob.php tests/Feature/Queue tests/Unit/Jobs
git commit -m "feat: run background jobs on the database queue"
```

---

### Task 13: Make authentication email and Revolut failures truthful

**Files:**
- Modify: `app/Http/Controllers/Api/AuthController.php` registration and multifactor mail paths.
- Modify: `app/Http/Controllers/Api/PaymentController.php` cancellation path.
- Modify: `app/Http/Controllers/Api/WebhookController.php` processing catches.
- Create: `tests/Feature/Auth/MailFailureTruthTest.php`
- Create: `tests/Feature/Payment/RevolutFailureTruthTest.php`
- Create: `tests/Browser/acceptance/payment-sandbox-failures.yaml`

**Interfaces:**
- Consumes: mail transport exceptions and Revolut service exceptions.
- Produces: non-success API responses, unchanged local subscription state on remote cancellation failure, and retryable non-2xx webhooks.

- [ ] **Step 1: Write failing API tests**

Mock mail to throw during registration and multifactor issuance; assert non-2xx and no "check your email" success. Mock Revolut cancellation to throw; assert local subscription remains active. Make webhook processing throw; assert 500 so Revolut retries.

- [ ] **Step 2: Run tests and observe false-success responses**

Run: `./vendor/bin/pest tests/Feature/Auth/MailFailureTruthTest.php tests/Feature/Payment/RevolutFailureTruthTest.php`

Expected: FAIL because current paths return success or 200.

- [ ] **Step 3: Implement explicit failures**

Each catch calls `report($e)` with non-personal identifiers only. Registration/multifactor return the same 500 shape already used by resend. Cancellation returns an actionable 502/503 and does not change local status. Webhook handler returns 500 on processing failure; signature rejection remains 401.

- [ ] **Step 4: Run focused payment/auth suites**

Run:

```bash
./vendor/bin/pest tests/Feature/Auth tests/Feature/Payment tests/Feature/Api --filter='mail|mfa|revolut|webhook|cancel'
```

Expected: PASS with no false-success assertions.

- [ ] **Step 5: Deploy to csjones and run sandbox failure acceptance**

Use Revolut sandbox and controlled service failure toggles/mocks available only on staging. Verify visible error copy, unchanged local subscription state, and retryable webhook status. Do not induce payment failures on production.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/AuthController.php app/Http/Controllers/Api/PaymentController.php \
  app/Http/Controllers/Api/WebhookController.php tests/Feature/Auth/MailFailureTruthTest.php \
  tests/Feature/Payment/RevolutFailureTruthTest.php tests/Browser/acceptance/payment-sandbox-failures.yaml
git commit -m "fix: surface mail and payment processing failures"
```

---

### Task 14: Replace silent dashboard and Inheritance Tax zeroes with unavailable states

**Files:**
- Modify: `app/Services/Dashboard/DashboardAggregator.php`
- Modify: `app/Services/Mobile/MobileDashboardAggregator.php`
- Modify: `app/Agents/EstateAgent.php`
- Modify: `app/Services/Coordination/PlanSources/ProtectionStrategySource.php`
- Modify: `app/Services/Coordination/PlanSources/EstateStrategySource.php`
- Modify: `app/Services/AI/AiToolDefinitions.php`
- Modify: `app/Services/AI/XaiToolDefinitions.php`
- Modify: affected web dashboard cards under `resources/js/components/Dashboard/`
- Modify: `resources/mobile/views/Dashboard.vue` and affected mobile module cards.
- Create: `tests/Feature/Dashboard/DashboardFailureTruthTest.php`
- Create: `tests/Unit/Services/Estate/IHTFailureTruthTest.php`
- Create: `tests/Browser/acceptance/dashboard-unavailable-web-mobile.yaml`

**Interfaces:**
- Consumes: thrown module analysis, alert, Inheritance Tax, and pointer-corpus errors.
- Produces: `_error`/`unavailable` markers, no fabricated £0/success, visible plain-text unavailable states, and Sentry events.

- [ ] **Step 1: Write failing backend tests**

Force each aggregator dependency to throw. Assert summary keys contain `_error=true` and do not contain a fabricated numeric zero. Force EstateAgent Inheritance Tax calculation to throw; assert `success=false` and no gifting calculation executes.

- [ ] **Step 2: Write failing frontend tests**

Mount web and mobile cards with unavailable fixtures. Assert "We couldn't load this section right now" and absence of `£0`. Do not add an icon.

- [ ] **Step 3: Run and observe current zero laundering**

Run:

```bash
./vendor/bin/pest tests/Feature/Dashboard/DashboardFailureTruthTest.php tests/Unit/Services/Estate/IHTFailureTruthTest.php
npm run test:frontend -- tests/frontend
```

Expected: FAIL because catches return null/zero/empty state.

- [ ] **Step 4: Route every high-risk catch to explicit unavailable state**

Call `report($e)`, return an explicit marker, and preserve partial data only when it is independently valid and labelled. Add `report($e)` to the silent strategy-source and pointer-tool catches. Never include exception messages in API output.

- [ ] **Step 5: Render matching web and `/m` states**

Use design-system tokens and plain text. Module cards remain present so layout does not jump; actions that require unavailable data are disabled with explanatory text.

- [ ] **Step 6: Run backend/frontend suites**

Run:

```bash
./vendor/bin/pest tests/Feature/Dashboard tests/Unit/Services/Mobile tests/Unit/Services/Estate
npm run test:frontend
```

Expected: PASS.

- [ ] **Step 7: Deploy feature branch and run the agent manifest**

Use a controlled staging failure injection, verify desktop and `/m` show unavailable rather than £0, and confirm one scrubbed monitoring event.

- [ ] **Step 8: Commit**

```bash
git add app/Services/Dashboard app/Services/Mobile/MobileDashboardAggregator.php \
  app/Agents/EstateAgent.php app/Services/Coordination/PlanSources \
  app/Services/AI/AiToolDefinitions.php app/Services/AI/XaiToolDefinitions.php \
  resources/js/components/Dashboard resources/mobile tests/Feature/Dashboard \
  tests/Unit/Services/Estate tests/Browser/acceptance/dashboard-unavailable-web-mobile.yaml
git commit -m "fix: show unavailable financial summaries truthfully"
```

---

### Task 15: Fix the live self-service data-deletion break and copy

**Files:**
- Modify: `app/Http/Controllers/Api/GDPRController.php:569-575` and erasure copy near line 292.
- Create: `tests/Feature/GDPR/DeleteFinancialProfileDataTest.php`
- Create: `tests/Browser/acceptance/delete-profile-data.yaml`

**Interfaces:**
- Consumes: authenticated, second-factor-confirmed self-service profile-data deletion.
- Produces: null real profile income/employment/national-insurance fields, unchanged account records, and truthful copy distinguishing profile clear from full retained erasure.

- [ ] **Step 1: Write the failing feature test**

First record CSJ confirmation that this endpoint clears the named profile fields and tells the user exactly that, while the separate retention-controlled pathway owns full account erasure. If CSJ selects full cascade here, amend the design and this task before writing the test.

Create a user with every real income column, employer, national insurance number, a savings account, and a pension. Call the endpoint after satisfying its second-factor requirement. Assert the named user columns are null, account/pension rows remain, and the response does not claim all financial data was deleted.

- [ ] **Step 2: Run and observe the unknown `salary` column failure**

Run: `./vendor/bin/pest tests/Feature/GDPR/DeleteFinancialProfileDataTest.php`

Expected: FAIL with SQL unknown-column or incomplete field clearing.

- [ ] **Step 3: Replace the invalid update payload**

Clear exactly:

```php
[
    'annual_employment_income' => null,
    'annual_self_employment_income' => null,
    'annual_rental_income' => null,
    'annual_dividend_income' => null,
    'annual_interest_income' => null,
    'annual_other_income' => null,
    'annual_trust_income' => null,
    'employer' => null,
    'national_insurance_number' => null,
    'employment_status' => null,
]
```

Return copy stating profile income and employment details were cleared while separately stored accounts/plans remain. Update erasure confirmation copy to state the seven-year retention process.

- [ ] **Step 4: Run feature test and browser acceptance locally**

Run:

```bash
./vendor/bin/pest tests/Feature/GDPR/DeleteFinancialProfileDataTest.php
node scripts/quality/validate-acceptance.mjs tests/Browser/acceptance/delete-profile-data.yaml
```

Expected: PASS.

- [ ] **Step 5: Deploy to csjones and execute the real UI journey**

Open settings, pass second-factor verification, submit, verify visible truthful copy, reload, and verify only the named profile fields are cleared. Query DB evidence for the test user and retain no code/secret in artifacts.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/GDPRController.php \
  tests/Feature/GDPR/DeleteFinancialProfileDataTest.php \
  tests/Browser/acceptance/delete-profile-data.yaml
git commit -m "fix: make profile data deletion accurate and truthful"
```

---

### Task 16: Complete GDPR retention, hard deletion, joint reassignment, and AI-store erasure

**Files:**
- Create: `database/migrations/2026_07_10_000002_create_erased_user_tombstones_table.php`
- Create: `app/Models/ErasedUserTombstone.php`
- Create: `app/Services/Account/JointRecordReassignmentService.php`
- Modify: `app/Services/Account/RetentionPurgeService.php`
- Modify: `app/Console/Commands/FynUserErase.php`
- Create: `app/Console/Commands/CleanupExpiredExports.php`
- Modify: `app/Services/GDPR/DataExportService.php`
- Modify: `app/Console/Kernel.php`
- Create: `tests/Feature/GDPR/PurgeCompletenessTest.php`
- Create: `tests/Feature/GDPR/JointRecordPurgeTest.php`
- Create: `tests/Feature/GDPR/AiStoreRetentionTest.php`
- Create: `tests/Feature/GDPR/ExportCleanupTest.php`

**Interfaces:**
- Consumes: user at the end of the seven-year retention window.
- Produces: spouse-preserving reassignment, complete child/AI/file deletion subject to FCA minimum retention, hashed re-registration tombstone, hard-deleted user row, and scheduled export cleanup.

- [ ] **Step 1: Record CSJ retention decisions**

Record acceptance of hard-deleting the user after the retention window and retaining only a SHA-256 hash of normalized email plus erased timestamp/reason. Record that recent FCA advice records remain until their minimum age, with identifiers detached/anonymized only as permitted by the approved retention policy.

- [ ] **Step 2: Write the completeness test before editing the purge list**

The test scans migrations/schema for tables with user foreign keys or `user_id`, compares them with an explicit purge/retain policy map, creates representative rows in every supported factory/table, runs purge, and asserts each row is deleted, anonymized, reassigned, or retained with a named policy. A newly added user table must fail the test until classified.

- [ ] **Step 3: Write joint reassignment tests**

For every `HasJointOwnership` model, create primary A, spouse B, and a joint row with A's ownership percentage. Purge A. Assert B becomes `user_id`, `joint_owner_id=null`, and `ownership_percentage=100 - old_percentage`; B retains the correct economic share.

- [ ] **Step 4: Write AI/FCA and export-file tests**

Assert recent advice records are retained for the minimum period, old records/blobs are erased, semantic facts and proposed facts are removed, and export files are deleted with their DB rows.

- [ ] **Step 5: Run tests and observe incomplete/soft-delete behaviour**

Run: `./vendor/bin/pest tests/Feature/GDPR/PurgeCompletenessTest.php tests/Feature/GDPR/JointRecordPurgeTest.php tests/Feature/GDPR/AiStoreRetentionTest.php tests/Feature/GDPR/ExportCleanupTest.php`

Expected: FAIL on absent tables, soft-deleted user, spouse data loss, AI-store survivors, or export survivors.

- [ ] **Step 6: Add the tombstone and reassignment service**

The tombstone stores `email_hash` (unique 64-char), `erased_at`, and `retention_reason`; it never stores plaintext email. `JointRecordReassignmentService::reassignForErasure(User $user): array` runs in the purge transaction before primary-owned rows are deleted.

- [ ] **Step 7: Make purge policy exhaustive and hard-delete last**

Classify every user-keyed table. Execute reassignments first, delete/anonymize children, erase hot/cold AI blobs through shared locators, remove export files, create tombstone, then call `forceDelete()` on the user. Correct docblocks that previously claimed foreign-key actions fired during soft delete.

- [ ] **Step 8: Guard `fyn:user:erase` by record age**

Without `--force`, remain dry-run. With `--force`, retain advice content younger than the FCA minimum and print counts/reasons; erase eligible older data and semantic stores. Never expose content in command output.

- [ ] **Step 9: Schedule export cleanup with observability**

Run daily at `03:45`, with `withoutOverlapping` and the Task 11 failure hook.

- [ ] **Step 10: Run migrations, reseed, and test**

Run:

```bash
php artisan migrate
php artisan db:seed
./vendor/bin/pest tests/Feature/GDPR
```

Expected: PASS; user row hard-deleted; spouse shares preserved; policy map exhaustive.

- [ ] **Step 11: Commit**

```bash
git add database/migrations/2026_07_10_000002_create_erased_user_tombstones_table.php \
  app/Models/ErasedUserTombstone.php app/Services/Account app/Services/GDPR \
  app/Console/Commands/FynUserErase.php app/Console/Commands/CleanupExpiredExports.php \
  app/Console/Kernel.php tests/Feature/GDPR docs/online-readiness/audit-ledger.yaml
git commit -m "fix: complete retention-controlled user erasure"
```

---

### Task 17: Enforce accepted-spouse joint ownership and revoke access on unlink

**Files:**
- Create: `app/Rules/IsAcceptedSpouse.php`
- Modify: every FormRequest containing `joint_owner_id` under `app/Http/Requests/`.
- Modify: store/service boundaries that accept joint owner IDs, including `app/Services/Stores/MortgageStore.php` and `app/Services/Stores/SavingsStore.php`.
- Create: `app/Services/Family/SpouseUnlinkService.php`
- Modify: `app/Http/Controllers/Api/FamilyMembersController.php:561-602`
- Modify: `app/Agents/CoordinatingAgent.php` marital/divorce update path.
- Modify: `app/Http/Controllers/Api/EstateController.php` joint liability query.
- Modify: `app/Http/Controllers/Api/InvestmentController.php` update/destroy scoping.
- Modify: `app/Models/User.php` mass-assignment configuration.
- Create: `tests/Feature/JointOwnership/JointOwnerValidationTest.php`
- Create: `tests/Feature/JointOwnership/UnlinkRevokesAccessTest.php`
- Create: `tests/Feature/JointOwnership/JointLiabilityVisibilityTest.php`

**Interfaces:**
- Consumes: acting `User`, nullable `joint_owner_id`, spouse link, accepted spouse permission.
- Produces: validation failure for strangers, accepted-spouse-only joint records, transactional unlink cleanup, and no ex-spouse read access.

- [ ] **Step 1: Write failing authorization tests**

For each joint model, test null, accepted spouse, non-spouse, unaccepted spouse, preview-user cross-link, and deleted/unlinked spouse. Assert non-spouse writes return 422 and never create/update a row. Assert `/m` endpoints inherit the same FormRequests.

- [ ] **Step 2: Write failing unlink tests**

Create all joint model types for a couple, unlink through both UI-controller and Fyn marital-update paths, then assert `joint_owner_id=null`, primary ownership 100%, spouse query scope no longer returns rows, and both users' caches are invalidated.

- [ ] **Step 3: Run and observe stranger acceptance/ex-spouse visibility**

Run: `./vendor/bin/pest tests/Feature/JointOwnership`

Expected: FAIL because current validation is only `exists:users,id` and unlink does not clear joint pointers.

- [ ] **Step 4: Implement `IsAcceptedSpouse`**

```php
<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class IsAcceptedSpouse implements ValidationRule
{
    public function __construct(private readonly User $actor) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        if ((int) $value !== (int) $this->actor->spouse_id
            || ! $this->actor->hasAcceptedSpousePermission()) {
            $fail('The selected joint owner must be your linked spouse.');
        }
    }
}
```

Apply `new IsAcceptedSpouse($this->user())` in every store/update request and repeat the invariant at service/store boundaries used outside HTTP.

- [ ] **Step 5: Implement transactional unlink cleanup**

`SpouseUnlinkService::unlink(User $user, User $spouse): void` locks both users, updates every `HasJointOwnership` model for the pair, clears permissions/spouse IDs, and invalidates both users. The service is the only unlink implementation called by the controller and Fyn.

- [ ] **Step 6: Fix low-level parity**

Use the canonical joint scope for liabilities, scope investment update/delete queries before loading the row, and remove `is_admin` from user mass assignment.

- [ ] **Step 7: Run authorization and module regression suites**

Run:

```bash
./vendor/bin/pest tests/Feature/JointOwnership
./vendor/bin/pest tests/Feature/Property tests/Feature/Savings tests/Feature/Investment \
  tests/Feature/Goals tests/Feature/Estate
```

Expected: PASS.

- [ ] **Step 8: Deploy and browser-verify desktop plus `/m`**

Create a joint record with an accepted spouse, confirm both surfaces show correct shares, unlink, confirm the former spouse loses visibility and primary retains 100%. Attempt a non-spouse request and confirm 422/no DB row.

- [ ] **Step 9: Commit**

```bash
git add app/Rules/IsAcceptedSpouse.php app/Http/Requests app/Services/Stores \
  app/Services/Family/SpouseUnlinkService.php app/Http/Controllers/Api/FamilyMembersController.php \
  app/Agents/CoordinatingAgent.php app/Http/Controllers/Api/EstateController.php \
  app/Http/Controllers/Api/InvestmentController.php app/Models/User.php \
  tests/Feature/JointOwnership
git commit -m "fix: enforce and revoke joint ownership access"
```

---

### Task 18: Make tax-year rollover date-driven and observable

**Files:**
- Modify: `app/Services/Stores/TaxConfigStore.php:219-227`
- Modify: `database/seeders/TaxConfigurationSeeder.php`
- Create: `app/Console/Commands/ActivateCurrentTaxYear.php`
- Create: `app/Console/Commands/CheckSuccessorTaxConfig.php`
- Modify: `app/Console/Kernel.php`
- Modify: `app/Http/Requests/StorePersonalAccountLineItemRequest.php`
- Modify: `app/Services/Retirement/RetirementStrategyService.php`
- Modify: `app/Services/Retirement/SalarySacrificeAnalyzer.php`
- Modify: `app/Services/Retirement/RetirementActionDefinitionService.php`
- Modify: `app/Services/Onboarding/OnboardingStateMachine.php`
- Modify: `resources/js/components/Retirement/DCPensionForm.vue`
- Modify: `resources/js/components/Onboarding/steps/IncomeStep.vue`
- Modify: `app/Constants/FinancialPlanningKnowledge.php`
- Create: `tests/Feature/Tax/DateDrivenActivationTest.php`
- Create: `tests/Feature/Tax/SuccessorConfigurationWarningTest.php`
- Create: `tests/Unit/Tax/TaxYearBoundaryPredicateTest.php`
- Create: `tests/Unit/Services/Savings/ISATrackerResetTest.php`
- Create: `tests/Feature/Retirement/MinimumPensionAccessAgeTest.php`

**Interfaces:**
- Consumes: Europe/London current date and tax configuration effective dates.
- Produces: exactly one date-correct active configuration, successor warnings, correct 6 April boundaries, a fresh ISA allowance after rollover, and one effective-dated minimum pension access age consumed by backend, web, `/m`, onboarding, and Fyn.

- [ ] **Step 1: Verify and record 2027/28 sources before seeding**

Use current HMRC/GOV.UK primary sources at implementation time. Record every value, URL, publication date, and CSJ approval in the audit ledger. If the full 2027/28 configuration is not authoritative, seed no guessed values; ship activation/warning mechanics and make missing successor configuration a monitored blocker until authoritative values exist.

- [ ] **Step 2: Write failing frozen-clock tests**

Cover 2027-04-05 23:59:59 and 2027-04-06 00:00:00 Europe/London, reseeding after rollover, no matching config, two overlapping configs, missing successor warning, and ISA contributions staying in the prior-year bucket while the new-year allowance resets. Add 2028-04-05/06 fixtures proving the configured minimum pension access age changes on its verified effective date and every consumer reports/validates the same value.

- [ ] **Step 3: Write the boundary predicate regression**

Pin 2026-05-03, 2026-12-03, 2027-04-05, and 2027-04-06 for both the line-item request and retirement refund timing. Expected predicate:

```php
$isOnOrAfterTaxYearStart = $month > 4 || ($month === 4 && $day >= 6);
```

- [ ] **Step 4: Run and observe stale activation and boundary failures**

Run:

```bash
./vendor/bin/pest tests/Feature/Tax/DateDrivenActivationTest.php \
  tests/Feature/Tax/SuccessorConfigurationWarningTest.php \
  tests/Unit/Tax/TaxYearBoundaryPredicateTest.php \
  tests/Unit/Services/Savings/ISATrackerResetTest.php \
  tests/Feature/Retirement/MinimumPensionAccessAgeTest.php
```

Expected: FAIL because the seeder pins 2026/27 and May-December days 1-5 are misclassified.

- [ ] **Step 5: Implement atomic date-driven activation**

`ActivateCurrentTaxYear` finds exactly one row with `effective_from <= now < effective_to`, then in a transaction marks it active and all others inactive. Zero or multiple matches cause a reported failure and no partial update. Clear tax caches only after commit.

- [ ] **Step 6: Stop the seeder from re-pinning a constant year**

Seed/update each year's values without setting a hardcoded active constant. Call the activation service/command after seeding based on the current date. Add 2027/28 only when Step 1 is satisfied.

- [ ] **Step 7: Add successor warning and schedule**

From 1 January through 5 April, run monthly and report when no successor exists within six weeks of `effective_to`; add daily activation at 00:30 with Task 11 failure hooks.

- [ ] **Step 8: Correct date-aware retirement rules, copy, and AI knowledge**

Read the configured salary-sacrifice cap effective date rather than literals. Add `pension.minimum_access_age` to effective-dated configuration and replace the hardcoded 55 checks/copy in `OnboardingStateMachine`, `DCPensionForm`, `IncomeStep`, retirement recommendations, and `FinancialPlanningKnowledge`. Remove worked tax figures from `FinancialPlanningKnowledge` when a tax-information tool/config is authoritative. No user-facing copy names a year or age inconsistent with config.

- [ ] **Step 9: Run migration/seed and tax tests**

Run:

```bash
php artisan db:seed
./vendor/bin/pest tests/Feature/Tax tests/Feature/Retirement/MinimumPensionAccessAgeTest.php \
  tests/Unit/Tax tests/Unit/Services/Savings/ISATrackerResetTest.php
php artisan schedule:list
```

Expected: PASS; one active date-correct config; successor warning scheduled.

- [ ] **Step 10: Commit**

```bash
git add app/Services/Stores/TaxConfigStore.php database/seeders/TaxConfigurationSeeder.php \
  app/Console/Commands/ActivateCurrentTaxYear.php app/Console/Commands/CheckSuccessorTaxConfig.php \
  app/Console/Kernel.php app/Http/Requests/StorePersonalAccountLineItemRequest.php \
  app/Services/Retirement app/Services/Onboarding/OnboardingStateMachine.php \
  resources/js/components/Retirement/DCPensionForm.vue resources/js/components/Onboarding/steps/IncomeStep.vue \
  app/Constants/FinancialPlanningKnowledge.php \
  tests/Feature/Tax tests/Unit/Tax tests/Unit/Services/Savings/ISATrackerResetTest.php \
  docs/online-readiness/audit-ledger.yaml
git commit -m "fix: automate tax year rollover safely"
```

---

### Task 19: Pin high-risk financial calculations and replace tests that cannot fail

**Files:**
- Create/extend: `tests/Unit/Services/Estate/IHTCalculationServiceTest.php`
- Create: `tests/Unit/Services/Estate/SpouseNRBTrackerServiceTest.php`
- Extend: `tests/Feature/Estate/EstateApiTest.php`
- Create tests for `InvestmentProjectionService`, `ChattelCGTService`, `PSACalculator`, `FSCSAssessor`, `WhatIfCalculator`, `WhatIfScenarioService`, Markowitz/correlation/covariance/rebalancing classes, `PensionPortfolioAnalyzer`, and `CalculatesOwnershipShare`.
- Modify: `tests/Feature/RetirementIntegrationTest.php`
- Modify: `tests/Feature/Estate/EstateIntegrationTest.php`
- Modify: `tests/Feature/Protection/ProtectionApiTest.php`
- Extend: UK tax calculator boundary tests.
- Create: `tests/frontend/utils/ownership.test.js`
- Create: `tests/frontend/utils/currency.test.js`
- Create: `tests/frontend/constants/taxConfig.test.js`
- Create: `docs/online-readiness/financial-fixtures.md`
- Modify after decision: `app/Agents/RetirementAgent.php`, `app/Services/Mobile/MobileDashboardAggregator.php`, desktop dashboard retirement summary, `/m` retirement summary, and Fyn retirement tool-result mapping so they consume one canonical projected-income field.

**Interfaces:**
- Consumes: approved tax configuration and deterministic financial fixtures.
- Produces: exact-value regression tests for every critical/high money-service gap and frontend/backend ownership parity.

- [ ] **Step 1: Record the retirement-engine decision**

CSJ chooses the projected-income engine used by desktop dashboard, `/m`, and Fyn. The design recommendation is the configured 4% safe-withdrawal-rate summary, while Monte Carlo and scheme quotes remain visibly labelled as different bases. Record the choice, canonical field name, and labelled secondary figures in the audit ledger before code.

- [ ] **Step 2: Write the fixture document before tests**

For each fixture record input facts, source rule/config key, expected intermediate values, expected final pounds/pence, and allowed presentation rounding. Include RNRB at £2,000,000, £2,000,001, and £2,350,000; direct-descendant/no-descendant; residence-value cap; charitable legacy; MPAA; Annual Allowance taper; Gift Aid band extension; additional-rate boundaries; prior-year tapered carry-forward; joint ownership; personal savings allowance; FSCS; What-If; investment projection/rebalancing.

- [ ] **Step 3: Write exact failing tests**

Use `toBe()`/exact decimal strings for deterministic results, not broad ranges. Feature tests assert both status and persisted/output financial value. Protection update/delete tests assert database state. Replace `expect(true)->toBeTrue()` and self-consistency assertions.

Add one seeded retirement fixture asserting the same canonical projected-income amount on the RetirementAgent summary, desktop dashboard API/view model, mobile aggregator, `/m` module payload, and Fyn tool result. Assert Monte Carlo/scheme figures use distinct labelled keys and cannot overwrite the canonical summary key.

- [ ] **Step 4: Run the new financial tests**

Run all new files explicitly. Expected: failures expose missing coverage and any remaining incorrect calculations; do not weaken expected values to make current code pass.

- [ ] **Step 5: Fix calculation roots one service at a time**

For each red fixture, use `TaxConfigService`, shared calculators/traits, decimal-safe operations, and existing stores. Commit independently reviewer-sized fixes when behaviour changes; keep characterization-only tests together only when production output is already correct.

- [ ] **Step 6: Add frontend utility parity tests**

Pin primary/joint/spouse shares against the same fixture table used by the backend trait. Pin `currency.js` rounding and 5/6 April `taxConfig.js` year selection with fake timers.

- [ ] **Step 7: Run full financial and frontend suites**

Run:

```bash
./vendor/bin/pest tests/Unit/Services/Estate tests/Unit/Services/Tax \
  tests/Unit/Services/Investment tests/Unit/Services/Retirement \
  tests/Unit/Services/Savings tests/Feature/Estate tests/Feature/Protection
npm run test:frontend
```

Expected: PASS; no exact fixture mismatch.

- [ ] **Step 8: Update ledger evidence and commit**

```bash
git add tests/Unit tests/Feature tests/frontend docs/online-readiness/financial-fixtures.md \
  app/Services app/Traits docs/online-readiness/audit-ledger.yaml
git commit -m "test: pin high risk financial calculations"
```

If code changes span more than one module, split commits by module and repeat focused/full verification for each.

---

### Task 20: Fix cross-surface cache invalidation, idempotency, and lost updates

**Files:**
- Modify: `app/Http/Controllers/Api/PropertyController.php`
- Modify: `app/Http/Controllers/Api/InvestmentController.php`
- Modify: mortgage, chattel, business-interest, Will, and Trust write controllers.
- Modify: `app/Services/Cache/CacheInvalidationService.php`
- Modify: `app/Services/Mobile/MobileDashboardAggregator.php` documentation.
- Modify/generalize: existing idempotency middleware/table used by AI send-message.
- Modify: module create routes in `routes/api.php` and `routes/api_v1.php` where separate.
- Modify: `app/Traits/TracksGoalContributions.php`
- Modify: `app/Services/Goals/GoalProgressService.php`
- Modify: `app/Services/Gamification/PointsService.php`
- Modify: `app/Observers/RiskRecalculationObserver.php`
- Modify: `app/Http/Controllers/Api/InvestmentController.php` Monte Carlo dispatch ordering.
- Modify: `app/Http/Controllers/Api/AiChatController.php` and `app/Services/AI/Loop/ConcurrentTurnQueue.php`.
- Create: `tests/Feature/Cache/CrossSurfaceInvalidationTest.php`
- Create: `tests/Feature/Idempotency/ModuleCreateIdempotencyTest.php`
- Create: `tests/Feature/Concurrency/LostUpdateTest.php`
- Create: `tests/Feature/Investment/MonteCarloDispatchRaceTest.php`
- Create: `tests/Feature/Fyn/InflightLockTest.php`
- Create: `tests/Browser/acceptance/web-write-mobile-refresh.yaml`

**Interfaces:**
- Consumes: web or `/m` writes, idempotency key, concurrent updates/jobs.
- Produces: immediate spouse/mobile invalidation, one row per retried create, atomic totals, race-free Monte Carlo ownership, and renewable Fyn turn locks.

- [ ] **Step 1: Record the mobile-dashboard cache decision**

Record CSJ confirmation of the design recommendation: retain the 24-hour TTL only as a fallback and make complete write-path invalidation the freshness contract. If CSJ chooses a shorter TTL, record the exact seconds and add a load-test threshold before implementation.

- [ ] **Step 2: Write failing cache/idempotency tests**

Prime mobile/dashboard and analysis caches, perform web writes for every uncovered controller, and assert all affected user/spouse keys are gone. Send the same create twice with one idempotency key and assert one row plus replayed response.

- [ ] **Step 3: Write failing concurrency tests**

Use two database connections/processes where required to prove two simultaneous goal contributions and point awards preserve both deltas. Assert two concurrent risk recalculation triggers dispatch one trailing/current job. Dispatch Monte Carlo on the database queue and assert owner state exists before worker handle.

- [ ] **Step 4: Run and observe stale/lost/race behaviour**

Run:

```bash
./vendor/bin/pest tests/Feature/Cache/CrossSurfaceInvalidationTest.php \
  tests/Feature/Idempotency/ModuleCreateIdempotencyTest.php \
  tests/Feature/Concurrency/LostUpdateTest.php \
  tests/Feature/Investment/MonteCarloDispatchRaceTest.php \
  tests/Feature/Fyn/InflightLockTest.php
```

Expected: FAIL on current uncovered controllers and non-atomic paths.

- [ ] **Step 5: Centralize invalidation after successful commits**

Call `invalidateForUserAndSpouse()` after every write only after the DB transaction succeeds. Include desktop analysis, advice prompt, and mobile dashboard keys in `CacheInvalidationService`. Retain 24-hour mobile cache as fallback and correct its documentation; write-path invalidation is the freshness contract.

- [ ] **Step 6: Generalize idempotency for module creates**

Key by authenticated user, route/action, and caller-supplied idempotency key. Store response status/body and reject key reuse with a different request hash. Apply to savings, property, mortgage, investment, goals/life events, protection policies, chattels, and business interests on shared web/mobile endpoints.

- [ ] **Step 7: Make updates atomic**

Use transactions plus `lockForUpdate` or atomic increments for goal amounts and total points. Replace risk `has` then `put` with `Cache::add`. Write Monte Carlo ownership/dedupe state before dispatch.

- [ ] **Step 8: Renew Fyn inflight locks and report expired queued turns**

Set/renew the lock beyond the maximum allowed turn; a second turn cannot acquire it. When stale queue cleanup expires a turn, report a scrubbed event rather than dropping silently.

- [ ] **Step 9: Run full affected suites and browser acceptance**

Run focused tests, then module feature suites. Deploy to csjones, edit a property/investment/mortgage on desktop, open `/m`, and verify immediate updated figures without waiting for TTL.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/Api app/Services/Cache app/Services/Mobile \
  app/Services/Goals app/Services/Gamification app/Traits/TracksGoalContributions.php \
  app/Observers/RiskRecalculationObserver.php app/Services/AI/Loop app/Http/Controllers/Api/AiChatController.php \
  routes tests/Feature tests/Browser/acceptance/web-write-mobile-refresh.yaml
git commit -m "fix: make writes coherent and idempotent across surfaces"
```

---

### Task 21: Move scale cliffs off request paths and bound large operations

**Files:**
- Modify: `app/Services/Mobile/PlanningProgressService.php`
- Create: `app/Console/Commands/BuildPlanningDistribution.php`
- Modify: `app/Services/Retirement/RetirementProjectionService.php`
- Modify: `app/Agents/RetirementAgent.php`
- Modify: `app/Console/Commands/SummariseStaleConversationsCommand.php`
- Create: migration adding summary-state/index columns required by the stale scan.
- Modify: `app/Traits/HasAiChat.php` selected columns.
- Create: migration adding `ai_messages.created_at` index.
- Modify: `app/Http/Controllers/Api/AdminController.php`
- Modify: `app/Console/Commands/PurgeAuditLogs.php`
- Modify: `app/Http/Controllers/Api/AiChatController.php` transcript pagination.
- Create: `tests/Feature/Performance/RequestPathQueryBudgetTest.php`
- Create: `tests/Feature/AI/ConversationPaginationTest.php`
- Create: `tests/Feature/Database/OperationalIndexTest.php`

**Interfaces:**
- Consumes: scheduled database queue and nightly aggregates from Tasks 11-12.
- Produces: O(1) planning distribution reads, queued Monte Carlo/summarization, selective chat hydration, indexed admin queries, chunked purges, and bounded transcripts.

- [ ] **Step 1: Write failing query-budget and pagination tests**

Seed 10 and 1,000 users and assert mobile percentile request query count is constant. Assert a cold dashboard does not execute Monte Carlo inline. Assert stale-summary query uses indexed scalar columns. Assert conversation show returns a bounded page with cursor/next link.

- [ ] **Step 2: Run and observe scale-dependent behaviour**

Run: `./vendor/bin/pest tests/Feature/Performance/RequestPathQueryBudgetTest.php tests/Feature/AI/ConversationPaginationTest.php tests/Feature/Database/OperationalIndexTest.php`

Expected: FAIL on user-proportional queries/unbounded transcript/missing indexes.

- [ ] **Step 3: Add scheduled planning distribution**

Compute the cohort distribution once daily at 05:00, store a versioned cache/table snapshot, and serve percentiles from it. On missing snapshot, show unavailable/updating rather than running all-user computation in request.

- [ ] **Step 4: Queue Monte Carlo and conversation summaries**

Use existing input hashes and database queue. Dashboard returns last valid result plus updating state. Add indexed `summary_state`/`last_summarised_message_id`; scheduler selects by indexed fields and dispatches jobs without inline provider calls.

- [ ] **Step 5: Reduce hydration and index operational queries**

Select only `id`, `role`, `content`, `conversation_id`, and `created_at` for conversational history. Add `ai_messages.created_at` index and verify `EXPLAIN` for admin aggregates.

- [ ] **Step 6: Bound destructive/large reads**

Chunk audit deletion in fixed batches and paginate/cursor the conversation transcript. Cap page size server-side.

- [ ] **Step 7: Run migrations, seed, and performance tests**

Run:

```bash
php artisan migrate
php artisan db:seed
./vendor/bin/pest tests/Feature/Performance tests/Feature/AI/ConversationPaginationTest.php \
  tests/Feature/Database/OperationalIndexTest.php
```

Expected: PASS; query budget constant; indexes used; no cold inline Monte Carlo.

- [ ] **Step 8: Deploy and verify `/m` performance/percentile**

On csjones, run aggregate command/worker, load `/m`, verify percentile remains and dashboard returns without synchronous Monte Carlo. Record server timings before/after.

- [ ] **Step 9: Commit**

```bash
git add app/Services/Mobile/PlanningProgressService.php app/Console/Commands \
  app/Services/Retirement app/Agents/RetirementAgent.php app/Traits/HasAiChat.php \
  app/Http/Controllers/Api/AdminController.php app/Http/Controllers/Api/AiChatController.php \
  database/migrations tests/Feature/Performance tests/Feature/AI/ConversationPaginationTest.php \
  tests/Feature/Database/OperationalIndexTest.php
git commit -m "perf: remove unbounded work from request paths"
```

---

### Task 22: Close the remaining July Fyn gate, corpus, compliance, and evaluation contracts

**Files:**
- Modify: `app/Constants/GateRoutes.php`
- Modify: `app/Services/PrerequisiteGateService.php`
- Modify: `app/Services/AI/KycGateChecker.php`
- Modify: readiness services under `app/Services/{Protection,Savings,Investment,Retirement,Estate}/`
- Modify: `app/Services/AI/Memory/Procedural/ProceduralCorpus.php`
- Modify: `app/Services/AI/AiToolDefinitions.php`
- Modify: `app/Services/AI/ToolResultContract.php`
- Modify: `app/Agents/CoordinatingAgent.php`
- Modify: `app/Http/Controllers/Api/V1/Mobile/ModuleSummaryController.php`
- Create: `app/Services/AI/ComplianceBackstop.php`
- Create: `app/Http/Controllers/Api/Admin/AdviceViolationController.php`
- Create: `resources/js/views/Admin/AdviceViolations.vue`
- Modify: `resources/js/router/index.js`, `routes/api.php`
- Create: `app/Console/Commands/RegenerateFynSnapshots.php`
- Modify: `tests/Architecture/ToolCatalogueParityTest.php`
- Create: `tests/Architecture/GateRoutesTest.php`
- Create: `tests/Unit/Services/PrerequisiteGateServiceTest.php`
- Create: `tests/Feature/AI/ComplianceBackstopTest.php`
- Create: `tests/Feature/AI/ProviderCorpusSelectionTest.php`
- Create: `tests/Feature/AI/ModelVisibleScoreStripTest.php`
- Create scenarios under `tests/Feature/Fyn/Eval/scenarios/{02-preview-personas,05-cancel-timeout,06-prompt-injection,07-regulatory,08-provider-parity,09-canonical-behaviour}/`.

**Interfaces:**
- `GateRoutes::resolve(string $destination): array{label: string, web: string, mobile: ?string}` is the only gate-destination map; model-facing copy consumes `label` only.
- `ComplianceBackstop::apply(string $response, ?array $classification = null): array{response: string, violations: array<array{rule: string, detail: string, severity: string}>}` returns the final persistable text plus structured violations.
- `ProceduralCorpus::active(string $id, string $provider): ?Procedure` resolves `xai` normally and aliases `openai` to the `.xai.md` variant until a distinct OpenAI corpus is intentionally introduced.
- Produces: route-valid gate labels, all-seven-module completeness truth, provider-correct tool schemas, no model-visible financial-quality scores, deterministic compliance backstops, and populated eval categories.

- [ ] **Step 1: Record the compliance defaults already recommended in the design**

Record these decisions in `docs/online-readiness/audit-ledger.yaml`: required adviser signposts are appended deterministically; provider/product-name detection is report-only until its eval false-positive rate is accepted; violations are queryable in admin; banned acronym/icon output is corrected before persistence. This task does not add product recommendations or silently block otherwise valid advice.

- [ ] **Step 2: Write failing gate-route and completeness tests**

For every gate destination, assert the web route resolves, the mobile route resolves or is explicitly null, and the model-facing text contains the human label but no raw route or unavailable tool name. Assert `assessAll()` returns explicit protection, savings, investment, retirement, estate, goals, and tax entries with no `?? 100` fabricated default. Assert `create_what_if_scenario` is the checked tool name.

Run:

```bash
./vendor/bin/pest tests/Architecture/GateRoutesTest.php tests/Unit/Services/PrerequisiteGateServiceTest.php
```

Expected: FAIL on dead paths, omitted goals/tax, the fabricated completion default, and the stale `run_what_if_scenario` name.

- [ ] **Step 3: Implement `GateRoutes` and complete the gate assessment**

Build the map from the real desktop and `/m` routers. Readiness/KYC services request a destination key, and model-facing signposts say only `Open <label> to add the missing information.` Route strings remain server/UI metadata and never appear in Fyn prose. Add goals/tax assessments, remove the 100% fallback, correct the scenario tool name, and document that all persistent write-tool safety is owned by `AdviceFyn::WRITE_TOOLS` plus the dispatch gate.

- [ ] **Step 4: Write failing content-level corpus parity tests**

Extend `ToolCatalogueParityTest` to compare tool names, parameter names, types, required arrays, enums, defaults, and descriptions across Anthropic and xAI variants. Add a provider-selection test proving xAI onboarding/campaign extraction loads `.xai.md`, Anthropic loads `.md`, and OpenAI resolves through the declared xAI alias.

Run:

```bash
./vendor/bin/pest tests/Architecture/ToolCatalogueParityTest.php \
  tests/Feature/AI/ProviderCorpusSelectionTest.php
```

Expected: FAIL on the current `current_account` divergence and Anthropic-default extraction path.

- [ ] **Step 5: Make corpus selection provider-correct and repair each proven divergence**

Pass the active provider into `AiToolDefinitions::onboardingExtractionTools()` and `ProceduralCorpus::active()`. Fix each mismatch only after choosing the intended contract from its handler validation and existing golden fixture. Re-record affected fixtures in the same commit; never mass-copy one provider corpus over another.

- [ ] **Step 6: Write failing score-strip and compliance tests**

Assert module tool results sent to the model contain no financial-quality keys matching adequacy, efficiency, completeness, tax efficiency, urgency, drift, optimisation, impact, ease, alignment, total, or nested `module_scores`; preserve concrete currency/percentage facts and the approved gamification `level`/`percentile` fields. Assert regulated advice missing the canonical adviser line gains it once, product/provider names produce report-only violations, and banned acronyms/icons are corrected before persistence.

- [ ] **Step 7: Implement model-visible score stripping and `ComplianceBackstop`**

Remove financial-quality score keys recursively after `ToolResultContract::validate()` and before tool content reaches the model. Update the contract so it validates real analytical inputs without requiring a score to be exposed. Make `ModuleSummaryController::removeScores()` consume the same key policy without touching gamification. Apply `ComplianceBackstop` after Task 9 sanitisation and before persistence; merge its structured violations into `metadata.validation_violations`.

- [ ] **Step 8: Add the admin violations queue**

Read `fynlaDesignGuide.md` before the view change. Expose a paginated, authorization-protected admin endpoint over assistant messages whose metadata has violations. Return message/conversation/user IDs, rules, severities, timestamps, and a short already-sanitised excerpt; never return system prompts, assembled context, tool arguments/results, email, National Insurance number, or full financial payloads. The desktop-only admin view wraps in `AppLayout`, contains no newly invented icons, and filters by rule/severity/date.

- [ ] **Step 9: Populate the empty evaluation categories**

Add at least three deterministic scenarios each for regulatory compliance and prompt injection; add cancellation/timeout, preview-persona, provider-parity, and repetition scenarios sufficient to exercise their named category. Provider-parity assertions compare contract/grounded facts, not byte-identical prose. Ensure every scenario validates against `_schema.json` and is counted by `EvalScenarioCountTest`.

- [ ] **Step 10: Add one guarded snapshot-regeneration command**

`php artisan fyn:snapshots:regen --force` regenerates the Fyn system-prompt snapshot, PromptOverlay golden masters, and tool-schema fixtures by invoking the existing fixture builders. Without `--force` it prints the paths it would change and exits without writing. Add command tests for dry-run and forced modes.

- [ ] **Step 11: Run the full Fyn/corpus/compliance lane**

Run:

```bash
./vendor/bin/pest tests/Architecture/ToolCatalogueParityTest.php tests/Architecture/GateRoutesTest.php
./vendor/bin/pest tests/Feature/AI tests/Feature/Fyn tests/Unit/Services/AI
./vendor/bin/pest tests/Feature/Fyn/Eval
```

Expected: PASS; all eval categories populated; no model-facing dead route/tool instruction; no financial-quality score leakage; compliance changes visible in structured metadata/admin without personal-data leakage.

- [ ] **Step 12: Deploy and verify the semantic cases on both surfaces**

On csjones, exercise one missing-data signpost, one investment/pension answer needing risk/adviser caveats, one prompt-injection attempt through a user-controlled record name, and one score-bearing raw module analysis on web and `/m`. Compare persisted sanitised text/violations and prove the advice surface remains read-only.

- [ ] **Step 13: Commit in reviewer-sized slices**

```bash
git commit -m "fix: make Fyn gate routes and completeness truthful"
git commit -m "fix: enforce provider-correct Fyn corpus contracts"
git commit -m "fix: add model-output compliance backstops"
git commit -m "test: fill Fyn eval and snapshot integrity gaps"
```

---

### Tasks 22A-22J: Implement the evidence-first Fyn guidance architecture

**Canonical design:** `docs/superpowers/specs/2026-07-10-fyn-evidence-first-advice-design.md`

**Executable implementation plan:** `docs/superpowers/plans/2026-07-10-fyn-evidence-first-advice.md`

**Launch position:** This is part of Gate 2 and the initial production release. It is not a continuation enhancement. Task 23 must not begin until all four linked checkpoints and the final Fyn architecture acceptance are green.

**Work packages:**

- **22A — Operating perimeter:** fail-closed `guidance` policy; targeted support and regulated advice disabled.
- **22B — One turn preparation:** compute classification, required-data/KYC and policy once.
- **22C — Advice Case:** extend `AiAdviceLog` into the canonical structured decision record linked to the signed episode.
- **22D — One evidence snapshot:** share the same live facts, memories, procedures and provenance with planner and reasoner.
- **22E — Planner efficiency:** direct route for ordinary turns, planner shadow for approved complex signals, queued learning/summarisation.
- **22F — Episodic consolidation:** SQL/signed-blob episodes become canonical; retire runtime Markdown episode scans/writes.
- **22G — Trusted relationship memory:** typed SQL facts with provenance, trust, effective dating and supersession; idempotent legacy migration.
- **22H — User control:** desktop and `/m` memory view/confirm/correct/delete plus chat write handoff.
- **22I — Mechanical policy:** Advice Case-grounded allow/sanitise/regenerate/block gate.
- **22J — Evaluation and activation:** route/cost/memory/policy telemetry, live-provider gauntlet and explicit planner/learning go/no-go.

**Required sequence:** Execute every checkbox, command, commit boundary and browser checkpoint in the linked plan. The summary above does not replace its exact file interfaces or acceptance criteria.

**Required launch defaults unless CSJ's signed go/no-go records a narrower approved change:**

```dotenv
FYN_ADVICE_MODE=guidance
FYN_PLANNER_MODE=shadow
FYN_LEARNING_ENABLED=false
```

**Master acceptance:** One substantive response produces one preparation, one evidence snapshot, one Advice Case and one linked signed episode; simple/module turns use no planner; user memory is correctable/erasable on desktop and `/m`; unsupported figures, fabricated writes and disabled advice modes fail closed; GroundGate and hidden capture remain intact.

---

### Task 23: Upgrade the runtime to Laravel 13 and a supported dependency set

**Files:**
- Modify: `composer.json`, `composer.lock`
- Modify only compatibility-affected framework bootstrap/config/provider/middleware files identified by official upgrade guides.
- Create: `docs/online-readiness/laravel-13-compatibility.md`
- Create: `tests/Architecture/RuntimeSupportTest.php`
- Modify: deployment PHP requirements/templates if server selection changes.

**Interfaces:**
- Consumes: green Tasks 1-22 and official Laravel 11, 12, and 13 upgrade guides.
- Produces: Laravel `^13.0`, PHP `^8.3`, compatible Sanctum/first-party packages, green full suites/build/browser matrix, and verified SiteGround runtime.

Laravel 13 is the target because its official support table lists PHP 8.3-8.5 and security fixes through 17 March 2028: [Laravel 13 release notes](https://laravel.com/docs/13.x/releases).

- [ ] **Step 1: Write the failing runtime-support test**

```php
<?php

declare(strict_types=1);

it('targets the supported Laravel 13 runtime', function (): void {
    $composer = json_decode(file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);
    expect($composer['require']['php'])->toBe('^8.3')
        ->and($composer['require']['laravel/framework'])->toBe('^13.0')
        ->and($composer['config']['platform']['php'])->toMatch('/^8\.[3-5]\./');
});
```

- [ ] **Step 2: Capture compatibility blockers**

Run:

```bash
composer why-not laravel/framework ^11.0
composer why-not laravel/framework ^12.0
composer why-not laravel/framework ^13.0
composer outdated --direct
```

Record each direct dependency, current constraint, required compatible constraint, code-impact files, and test lane in `laravel-13-compatibility.md`. Confirm SiteGround provides a supported PHP 8.3+ CLI and web runtime before changing constraints.

- [ ] **Step 3: Upgrade one framework major per commit**

Follow official 10->11, 11->12, and 12->13 guides in order. At each major:

1. Update Composer constraints and compatible Sanctum/Collision/Pest/PHPUnit dependencies.
2. Run `composer update --with-all-dependencies`.
3. Apply only required compatibility changes; do not restructure the entire application to the new skeleton.
4. Run PHP syntax, architecture, unit, feature, integration, and evaluation suites.
5. Commit `chore: upgrade Laravel to 11`, then `chore: upgrade Laravel to 12`, then `chore: upgrade Laravel to 13` only when each corresponding stage is green.

- [ ] **Step 4: Resolve third-party compatibility explicitly**

For each package that blocks Laravel 13, prefer a maintained compatible release. Replace/remove only when no maintained compatible release exists, with a dedicated test for the affected document, payment, authentication, spreadsheet, PDF, mail, or AI flow.

- [ ] **Step 5: Run the complete local gate**

Run:

```bash
bash scripts/quality/run.sh lint
bash scripts/quality/run.sh php
bash scripts/quality/run.sh frontend
bash scripts/quality/run.sh build
npm run test:e2e:full
composer audit
npm audit --omit=dev
```

Expected: all blocking commands exit 0; no unresolved critical/high dependency advisory.

- [ ] **Step 6: Deploy the framework branch to csjones before merge**

Verify PHP version, migrations, queue worker, scheduler, auth, desktop, `/m`, Fyn streaming, document generation, and Revolut sandbox. Run the full agent acceptance set from Task 25. Loop until green.

- [ ] **Step 7: Commit the final runtime support proof**

```bash
git add composer.json composer.lock docs/online-readiness/laravel-13-compatibility.md \
  tests/Architecture/RuntimeSupportTest.php app bootstrap config routes deploy
git commit -m "chore: complete Laravel 13 compatibility"
```

---

### Task 24: Replace legacy E2E tests with the full automated product matrix

**Files:**
- Create: `tests/E2E/matrix/personas.js`
- Create: `tests/E2E/matrix/modules.js`
- Create: `tests/E2E/matrix/persona-module.desktop.spec.js`
- Create: `tests/E2E/matrix/persona-module.mobile.spec.js`
- Create: `tests/E2E/fixtures/persona-expectations.js`
- Create journey specs under `tests/E2E/journeys/` for goal, property/mortgage, pension allowance, life-event allocation, estate gifts, campaigns, and cross-surface cache.
- Create: `tests/E2E/fyn/advice-capture.spec.js`
- Create: `tests/E2E/fyn/failure-events.spec.js`
- Create: `tests/E2E/admin/authorization.spec.js`
- Create: `tests/E2E/advisor/authorization.spec.js`
- Create: `tests/E2E/payments/revolut-sandbox.spec.js`
- Create: `docs/online-readiness/coverage-matrix.md`
- Create: `tests/Architecture/BrowserCoverageMatrixTest.php`
- Modify: `playwright.config.js` project test matching.
- Delete after replacement: `tests/E2E/01-protection.spec.js` through `tests/E2E/07-sp3-mobile-iframe.spec.js` and obsolete helpers.
- Modify/automate: applicable `tests/Browser/scenarios/BS-*.php` contracts.

**Interfaces:**
- Consumes: stable preview seeds, dedicated E2E users, module definitions, desktop and mobile projects.
- Produces: six-persona x seven-module x two-surface coverage, cross-module journeys, Fyn/auth/admin/advisor/payment coverage, and an explicit mapping for every BS-NN scenario.

- [ ] **Step 1: Freeze persona expectations from seeded data**

Run `php artisan db:seed --class=PreviewUserSeeder --force` in the isolated E2E database. For each persona, record only stable expected facts used by the UI: names, module record counts, headline currency values, ownership shares, and designed unavailable/empty modules. Do not derive expected values from the API during the test; that would let backend and UI share the same wrong result.

- [ ] **Step 2: Define the matrix contracts**

`personas.js` exports exactly:

```js
export const personas = [
  'young_family',
  'peak_earners',
  'entrepreneur',
  'young_saver',
  'retired_couple',
  'student',
];
```

`modules.js` defines web route, `/m` route, heading, list selector, CRUD capability, and expected fixture key for Protection, Savings, Investment, Retirement, Estate, Goals & Life Events, and Coordination/holistic plan.

- [ ] **Step 3: Write the matrix test and make the coverage architecture test fail**

The architecture test parses `coverage-matrix.md` and requires 84 web/mobile persona-module cells, each with automated test path, agent manifest path, and status. Initial status is `red` until the test passes; skipped does not satisfy a required cell.

Run: `./vendor/bin/pest tests/Architecture/BrowserCoverageMatrixTest.php`

Expected: FAIL because the matrix/test files are incomplete.

- [ ] **Step 4: Implement desktop persona-module tests**

For every persona/module:

1. Select/login as the persona through supported UI.
2. Navigate via user-visible navigation, not direct state injection.
3. Assert layout/main region and exact seeded headline fixture.
4. Assert expected records.
5. Perform one supported create/read/update/delete round trip on an E2E-owned record.
6. Reload and prove persistence at each write.
7. Assert dashboard/module summary updates.
8. Assert zero runtime errors.

- [ ] **Step 5: Implement `/m` persona-module tests**

Use the verified mobile authentication path from the `verify-m` skill. Assert the corresponding mobile route/card/summary and repeat supported writes through mobile UI. When a surface has no mobile counterpart by design, the matrix must cite the approved exception; admin/advisor are the known desktop-only examples.

- [ ] **Step 6: Implement cross-module journeys**

Pin these flows end to end:

- Goal create -> contribution -> dashboard progress.
- Property -> mortgage -> net worth on desktop and `/m`.
- Defined Contribution pension contribution -> Annual Allowance/MPAA output.
- Life event -> generated allocation -> regenerate.
- Estate gift -> seven-year taper -> exact Inheritance Tax result.
- SaveTax and pensioncheck anonymous funnel -> registration -> Fyn onboarding -> target page.
- SaveTax July3 user issues: visible registration error, no registration flash, balance acknowledgement, edit-return/read-back, short date-of-birth confirmation, action logging, bold questions, and no stray Tax Strategy Continue button.
- WP-1–6/WP-5b/WP-5c: intent-only capture writes nothing; failed capture is visible; one action ID completes across surfaces; Done/history persist; achievements labels are earned facts; milestone catalogue mints once/yearly; upcoming/history paginate; campaign affinity and nudges work.
- Pensioncheck fresh and existing-user delta walks: no re-asked known data, exact capture persistence, one completion award, campaign state cleared, synthesis/terminal correct, and no SaveTax bleed.
- Desktop write -> immediate `/m` cache-coherent result.

- [ ] **Step 7: Implement Fyn and authorization journeys**

Automate deterministic Fyn cassettes for read-only advice, delegated capture, write failure, token limit, consent, resume, and repetition. Test admin/advisor API/UI authorization boundaries with owned and unowned users. Run Revolut only against sandbox and assert remote/local state agreement.

- [ ] **Step 8: Convert BS-NN contracts**

Add a table to `coverage-matrix.md` mapping every BS-NN file to `automated`, `agent`, or `both`, with exact test/manifest evidence. Move deterministic scenarios into Playwright. Keep live-provider/stream semantic cases as agent acceptance. Remove any claim that the skipped Pest stub itself passed.

- [ ] **Step 9: Delete weak legacy tests only after mapped replacement**

For each old file, list every intended behaviour and its replacement path in the commit body. Delete the old file only after all non-optional behaviours are green in the new matrix.

- [ ] **Step 10: Run the full browser lane**

Run:

```bash
npm run test:e2e:full
./vendor/bin/pest tests/Architecture/BrowserCoverageMatrixTest.php
```

Expected: PASS; 84 required cells green; no unexpected skipped required test; desktop Chromium plus mobile Chromium green; mobile WebKit smoke green.

- [ ] **Step 11: Commit by matrix slice**

Use reviewer-sized commits:

```bash
git commit -m "test: add persona module browser matrix"
git commit -m "test: add cross module browser journeys"
git commit -m "test: automate Fyn and authorization journeys"
git commit -m "test: retire weak legacy browser specs"
```

Run the full browser lane after each commit.

---

### Task 25: Run the independent whole-product agent browser gauntlet

**Files:**
- Create/complete: manifests under `tests/Browser/acceptance/` for every critical/high user-visible finding and release surface.
- Create: `docs/online-readiness/evidence/${RELEASE_SHA}/summary.md`, where `RELEASE_SHA=$(git rev-parse origin/dev)` is captured before the run.
- Create: `docs/online-readiness/evidence/${RELEASE_SHA}/results.json`.
- Modify: `docs/online-readiness/coverage-matrix.md`
- Modify: `docs/online-readiness/audit-ledger.yaml`

**Interfaces:**
- Consumes: exact csjones release SHA, validated acceptance manifests, dedicated staging users/personas.
- Produces: independent interaction evidence, 84 persona/module/surface semantic checks, high-risk flow evidence, and red/green finding statuses.

- [ ] **Step 1: Deploy the exact release-candidate `dev` SHA to csjones**

Build web and `/m` locally, pull the same SHA on the server, run migrations and seed, start/verify queue cron and scheduler, clear caches ending in `config:cache`, and verify homepage content.

- [ ] **Step 2: Validate every manifest before browser work**

Run: `node scripts/quality/validate-acceptance.mjs tests/Browser/acceptance`

Expected: all manifests valid; every user-visible critical/high finding has desktop and `/m` steps or a documented designed exception.

- [ ] **Step 3: Execute the 84 semantic matrix checks**

The browser agent interacts with each persona/module on desktop and `/m`: navigation, seeded figures, record details, supported write round trip, reload, cross-surface result, no hidden server/console error. It records one result per matrix cell.

- [ ] **Step 4: Execute high-risk journeys**

Run authentication/multifactor, joint unlink, GDPR profile clear, Fyn repetition, delegated capture/failure, tax rollover fixture display, estate exact calculation, queue-delayed Monte Carlo, dashboard unavailable state, campaigns, admin/advisor auth, and Revolut sandbox manifests.

- [ ] **Step 5: Verify database, stream, and monitoring evidence**

For writes and Fyn flows, compare UI outcome with scoped database rows, server-sent event types, audit chain, cache state, queue job state, and scrubbed monitoring events. Never query unscoped real users; staging test identities are explicit.

- [ ] **Step 6: Route every red result through the mandatory loop**

Do not mark partial success. Create/fix a bounded branch, add regression coverage, deploy feature branch, rerun the failed manifest and its affected matrix slice, then rerun the full release gauntlet after merge.

- [ ] **Step 7: Write evidence summary**

The summary records SHA, build manifest hashes, database migration state, browser versions, counts by matrix/manifests, failures/fixes, exact remaining red findings, and cleanup. `results.json` validates against the Task 7 schema.

- [ ] **Step 8: Update coverage and audit ledgers**

Set a critical/high finding to `green` only when its tests and environment evidence paths exist. `inapplicable` requires file/line and runtime evidence plus reviewer approval.

- [ ] **Step 9: Commit evidence metadata**

Commit redacted summary/JSON/ledger/matrix. Keep screenshots/traces/videos as CI or controlled vault artifacts when they contain test personal data.

---

### Task 26: Create and soak the immutable staging release candidate

**Files:**
- Finalize: `docs/online-readiness/release-manifest.md`
- Create: `docs/online-readiness/staging-checklist.md`
- Create: `docs/online-readiness/staging-soak.md`
- Modify: `docs/online-readiness/audit-ledger.yaml`

**Interfaces:**
- Consumes: green Tasks 1-25 and one immutable `origin/dev` SHA.
- Produces: release candidate with matching source/build/database evidence and a seven-day clean staging window.

- [ ] **Step 1: Freeze feature intake**

Record the release-candidate SHA and reject unrelated merges until production release or release cancellation. Only blocker fixes may change the candidate; any blocker fix creates a new SHA and reruns affected/full gates.

- [ ] **Step 2: Run the complete clean-checkout gate**

From a fresh worktree/clone at the candidate SHA:

```bash
composer install --no-interaction --prefer-dist
npm ci
bash scripts/quality/run.sh lint
bash scripts/quality/run.sh php
bash scripts/quality/run.sh frontend
bash scripts/quality/run.sh build
npm run test:e2e:full
composer audit
npm audit --omit=dev
```

Expected: every command exits 0. Record versions and output summaries.

- [ ] **Step 3: Deploy the exact candidate and hash artifacts**

Hash `public/build/manifest.json`, `public/m-build/manifest.json`, and uploaded bundle trees. Record server `git rev-parse HEAD`, migration status, queue/schedule status, config cache state, and homepage content.

- [ ] **Step 4: Run the full agent gauntlet once more**

Task 25 results must reference the immutable candidate SHA and deployed URL. No earlier feature-branch evidence substitutes for release-candidate evidence.

- [ ] **Step 5: Start the seven-day soak**

Monitor Sentry, Laravel logs, worker log, failed jobs, scheduler heartbeat, webhook/payment state, queue latency, HTTP 5xx, Fyn errors, and support reports daily. Record checks in `staging-soak.md`.

- [ ] **Step 6: Apply soak reset rules**

A severity-one finding resets the seven-day clock after its fix deploy. A severity-two finding blocks release until fixed and rerun; CSJ records whether its risk warrants clock reset. Severity-three items may enter the post-launch ledger only when they violate no repository rule.

- [ ] **Step 7: Close the staging gate**

Expected: seven consecutive days with zero unresolved severity-one/two defects; every critical/high ledger row green or evidence-backed inapplicable.

- [ ] **Step 8: Commit the staging record**

```bash
git add docs/online-readiness/release-manifest.md docs/online-readiness/staging-checklist.md \
  docs/online-readiness/staging-soak.md docs/online-readiness/audit-ledger.yaml
git commit -m "docs: record green staging release candidate"
```

---

### Task 27: Rehearse production deployment and rollback, then record go/no-go

**Files:**
- Create: `docs/online-readiness/rollback-runbook.md`
- Create: `docs/online-readiness/production-checklist.md`
- Create: `docs/online-readiness/go-no-go.md`
- Generate through skills: deployment notes/checklist for the exact release diff.

**Interfaces:**
- Consumes: sanitized current production snapshot, immutable release SHA, staging proof.
- Produces: measured migration/deploy/rollback sequence, security re-check, recovery time, and CSJ's explicit decision.

- [ ] **Step 1: Generate file-level deployment notes**

Set `RELEASE_SHA=$(git rev-parse origin/dev)`, then use the `deploy-notes` and `deploy-checklist` skills against `origin/main...${RELEASE_SHA}`. Include source, both bundles, public pages, corpus, migrations, Composer changes, queue cron, scheduler, config templates, upload paths, and server commands.

- [ ] **Step 2: Rehearse migrations against a sanitized production snapshot**

Restore the snapshot into an isolated rehearsal database. Run `php artisan migrate --force`, required seeders, corpus validators, queue/scheduler checks, and the production smoke API subset. Record row counts/checksums before/after for migration-touched tables. Never run destructive reset commands.

- [ ] **Step 3: Rehearse code/asset rollback**

Prove restoration of prior PHP/source, `public/build`, `public/m-build`, config/cache, worker state, and application availability. For non-reversible migrations, prove the previous code can run against the forward schema or document/implement a forward-compatible rollback patch before go.

- [ ] **Step 4: Define explicit rollback triggers and authority**

List authentication/session failure, unauthorized access, data loss/duplication, financial mismatch, payment divergence, desktop/`/m` boot failure, sustained 5xx, queue/scheduler outage, and Fyn write-safety breach. CSJ is the go/no-go and rollback authority.

- [ ] **Step 5: Run final security/dependency review**

Run `composer audit`, `npm audit --omit=dev`, the security-and-hardening skill over the release diff, secret scan, route/auth matrix, and production-config validation without exposing values.

- [ ] **Step 6: Write the go/no-go document**

Include each gate status, critical/high ledger state, severity-one/two count (must be zero), staging soak dates, deployment duration, rollback duration, data/financial/security results, and CSJ decision/signature/date.

- [ ] **Step 7: Stop if the decision is no-go**

No-go creates a new bounded blocker work item and returns to the relevant task/gate. Do not schedule or begin production deployment.

- [ ] **Step 8: Commit the readiness pack**

```bash
git add docs/online-readiness/rollback-runbook.md docs/online-readiness/production-checklist.md \
  docs/online-readiness/go-no-go.md
git commit -m "docs: approve production readiness and rollback"
```

---

### Task 28: Promote `dev` to production and complete post-release proof

**Files:**
- Modify after checks: `docs/online-readiness/post-release.md`
- Generate: final deployment notes/checklist artifacts.
- No product code changes are allowed inside the release operation; a product-code need returns to a feature branch and staging gate.

**Interfaces:**
- Consumes: CSJ's explicit current-turn release authorization, immutable green `dev` SHA, approved go/no-go.
- Produces: merged `main`, deployed fynla.org source/assets/schema, production smoke evidence, and 15-minute/24-hour/seven-day health record.

- [ ] **Step 1: Invoke the release skill and re-check the three merge questions**

Confirm CSJ has authorized this release now, the exact `dev` tip is deployed and browser-green on csjones, and `main...dev` contains nothing outside the evidence pack. Any "no" stops the release.

- [ ] **Step 2: Open and merge `dev -> main`**

Use the protected-branch process. Record pull request URL, merged SHA, and prove it contains the staging candidate. Do not deploy an unmerged local commit.

- [ ] **Step 3: Build production assets locally**

Run:

```bash
git checkout main
git pull origin main
./deploy/fynla-org/build.sh
```

Expected: desktop and `/m` manifests exist; record hashes and confirm production base paths.

- [ ] **Step 4: Back up and reconcile production**

Take/confirm SiteGround file and database rollback points. Perform the full source drift reconciliation from deployment notes, upload `public/build/` and `public/m-build/`, changed PHP/source/public/corpus files, and remove files explicitly deleted by the release manifest.

- [ ] **Step 5: Finalize the production server**

From `~/www/fynla.org/public_html` run in this order:

```bash
composer dump-autoload -o
php artisan migrate:status
php artisan migrate --force
php artisan fyn:episodic:backfill-blobs
php artisan db:seed --force
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
php artisan config:cache
php artisan fyn:semantic:reindex
php artisan fyn:pointers:reindex
php artisan fyn:procedural:validate
php artisan schedule:list
php artisan queue:monitor database:default --max=100
```

Run only Fyn commands present in the released code. Any migration, seed, corpus validator, config cache, queue, or schedule failure triggers the rollback decision before smoke acceptance.

- [ ] **Step 6: Run production smoke with a dedicated QA account**

Ask CSJ for the multifactor code. Verify homepage content, login/session, dashboard, one key module, read-only Fyn advice, `/m` boot/dashboard, public campaigns, admin availability, scheduler/queue health, and non-mutating payment/subscription status. Do not perform uncontrolled production financial CRUD or a real charge.

- [ ] **Step 7: Monitor the first 15 minutes**

Watch Sentry, Laravel log, worker log, failed jobs, 5xx rate, webhook/payment events, and scheduler heartbeat. Record exact checks in `post-release.md`. A rollback trigger invokes the rehearsed runbook immediately.

- [ ] **Step 8: Run the 24-hour check**

Recheck auth/session, queue latency/failures, scheduled commands, mail, Fyn errors/repetition, payment/webhook state, tax config, dashboard error markers, desktop/`/m` traffic, and support reports.

- [ ] **Step 9: Run the seven-day check**

Review monitoring trends, outstanding errors, performance, payment reconciliation, erasure/retention jobs, tax successor status, and customer reports. Move only non-blocking medium/low items to the ranked post-launch backlog.

- [ ] **Step 10: Commit the post-release record and resume feature development**

```bash
git add docs/online-readiness/post-release.md docs/online-readiness/audit-ledger.yaml
git commit -m "docs: record production release verification"
```

Normal feature intake resumes only after the seven-day check is green.

---

### Task 29: Close the delivered July pensioncheck and gamification parity/polish list

**Files:**
- Consume completely: `July/July3Updates/{campaign-playbook.md,pension-campaign-plan.md,savetax-recs-gamification-map.md,wp5c-milestones-spec.md}`
- Consume completely: `July/July4Updates/pensioncheck-patch-notes-technical.md`
- Create: `database/migrations/2026_07_10_000010_add_last_completed_campaign_to_users_table.php`
- Modify: `app/Models/User.php`
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php`
- Modify: `app/Services/Mobile/NextActionsService.php`
- Create: `resources/js/views/Achievements.vue`
- Modify: `resources/js/router/index.js`, `resources/js/layouts/AppNavbar.vue`
- Modify: `resources/js/components/Fyn/FynQuickReplies.vue`
- Modify: `resources/js/views/Retirement/PensionDetail.vue`
- Modify: `resources/mobile/views/modules/Retirement.vue`, `resources/mobile/views/modules/RetirementPensionDetail.vue`
- Modify: `public/pages/index.php`, `public/pages/pensioncheck.php`, `public/pages/pensioncheck-plan.php`, `public/pages/js/pensioncheck.js`, and `public/pages/js/pensioncheck-plan.js`.
- Create after CSJ copy/art approval: `public/images/og/pensioncheck.jpg`, `public/images/og/pensioncheck-plan.jpg`
- Create: `tests/Feature/Campaigns/DeliveredJulyCampaignRegressionTest.php`
- Create: `tests/Feature/Gamification/DesktopParityTest.php`
- Create: `tests/E2E/journeys/july-delivered-plans.spec.js`
- Create: `tests/Browser/acceptance/july-delivered-plans-closure.yaml`

**Interfaces:**
- `users.last_completed_campaign` is the durable nullable campaign affinity after `active_campaign` clears; allowed values come only from `config('onboarding.campaign_map')`.
- Desktop achievements consumes the same paginated badges, completed actions, milestones/upcoming items, and activity-history endpoints as `/m`; it does not create a second gamification model.
- Produces: current regression evidence for every delivered July work package, durable pension affinity, truthful contribution presentation, desktop parity, approved pensioncheck copy, and valid social assets.

- [ ] **Step 1: Record the continuation decisions**

CSJ approves final pensioncheck funnel/plan/homepage/Fyn copy, whether the higher-rate carry-forward question stays, and the two original social-image compositions. Record the recommended durable-affinity choice (`last_completed_campaign`) and confirm the historical email loop remains deferred; email is not silently added by this task.

- [ ] **Step 2: Write failing delivered-plan characterisation tests**

Pin the behaviours delivered by PR #594, WP-1–6, WP-5b/WP-5c, PRs #607-#610, and PR #612: no phantom captures, visible failed captures, one actions model/completion IDs, history, earned-achievement labels, milestone mint-once/yearly repeats, campaign affinity, SaveTax flow, pension fresh flow, and pension existing-user delta flow. Each test cites the source file/work-package ID from `july-plan-register.yaml`.

- [ ] **Step 3: Add durable completed-campaign affinity**

Write the migration, migrate, and seed. On a successful campaign terminal set `last_completed_campaign` before clearing `active_campaign`; pause/cancel does not set it. Backfill from a validated `funnel_answers.campaign` only when the user has completed onboarding. `NextActionsService` prefers `active_campaign`, then `last_completed_campaign`, then the validated legacy SaveTax marker. Add tests for SaveTax, pensioncheck re-entry, pause, invalid values, and repeat completion.

- [ ] **Step 4: Build desktop achievements/milestones/history parity**

Read `fynlaDesignGuide.md` first. Create an `AppLayout`-wrapped desktop view over the existing shared endpoints: earned achievements, completed actions, grouped upcoming/earned milestones, and cursor-paginated history. Use no decorative icons on cards/detail content; the AppNavbar entry may use the existing functional navigation icon pattern because collapsed navigation needs it. Do not expose a financial-quality score.

- [ ] **Step 5: Close the pensioncheck presentation defects**

Use the `html-template` skill for the public-page edits. Apply approved copy across the public pages, homepage source, and Fyn corpus/in-code states in lockstep. Generate the two approved 1200x630 social images through the image-generation skill and visually verify them before committing. Move minimum-pension-access-age behaviour to Task 18's effective-dated configuration. Make desktop and `/m` show the stored percentage when salary is unavailable instead of presenting a fabricated £0 monthly contribution. Render the existing bold verify-question markdown correctly in `FynQuickReplies` without allowing raw HTML.

- [ ] **Step 6: Run focused and full regression lanes**

Run:

```bash
php artisan migrate
php artisan db:seed
./vendor/bin/pest tests/Feature/Campaigns tests/Feature/Gamification tests/Feature/Onboarding
./vendor/bin/pest
npm run test:frontend
npm run test:e2e:full -- tests/E2E/journeys/july-delivered-plans.spec.js
```

Expected: PASS; all source work-package IDs have current test evidence; no email loop was added; desktop and `/m` parity is green.

- [ ] **Step 7: Run independent live acceptance and release**

Deploy the feature branch to csjones. The browser agent executes SaveTax fresh, pensioncheck fresh, pensioncheck existing-user re-entry, action complete/replace, milestone mint, desktop achievements/history, and `/m` parity. Verify database awards/tracking/campaign fields and no duplicate completions. Loop every red result to green, then run the standard staging/go-no-go/production/post-release gates before marking Task 29 complete.

- [ ] **Step 8: Commit by bounded concern**

```bash
git commit -m "fix: persist completed campaign affinity"
git commit -m "feat: add desktop gamification history parity"
git commit -m "fix: close pensioncheck presentation gaps"
git commit -m "test: reverify delivered July campaign plans"
```

---

### Task 30: Reconcile xAI model truth and add the July-specified OpenAI provider

**Files:**
- Consume completely: `July/July7Updates/fyn-ai-remediation-spec.md` WS-F2 and `fyn-ai-remediation-plan.md` PR-4/PR-5.
- Modify: `config/services.php`, `.env.example`, deployment environment templates.
- Create: `app/Services/AI/OpenAiClient.php`
- Create: `app/Services/AI/Provider.php`
- Modify: `app/Traits/HasAiChat.php`
- Modify: `app/Services/AI/Loop/Planner.php`
- Modify: `app/Services/AI/ConversationSummariser.php`
- Modify: `app/Services/AI/Learning/ProposedFactSynthesiser.php`
- Modify: provider guardrails, usage/cost accounting, and tool-definition selection under `app/Services/AI/`.
- Modify: `app/Services/AI/Memory/Procedural/ProceduralCorpus.php`, `ProceduralCorpusLoader.php`
- Create: `tests/Feature/AI/OpenAiProviderTest.php`
- Create: `tests/Feature/AI/OpenAiToolSchemaGoldenMasterTest.php`
- Modify: `tests/Feature/Fyn/Eval/CassetteModelProvenanceTest.php`, provider-parity fixtures/scenarios.
- Create: `docs/online-readiness/openai-provider-contract.md`

**Interfaces:**
- `Provider` is a string-backed PHP enum with `Anthropic = 'anthropic'`, `Xai = 'xai'`, and `OpenAi = 'openai'`; no boolean `$isXai` branches remain in shared orchestration.
- `AI_PROVIDER=openai` switches the full advice loop by environment alone; per-component overrides are explicit nullable config, defaulting to the global provider.
- OpenAI consumes the `.xai.md` tool corpus through Task 22's documented alias and produces the same internal normalized stream/tool/usage events as xAI.

- [ ] **Step 1: Verify the current external contract before code**

Use the `openai-docs` skill and official OpenAI developer documentation to capture the current model identifier, Responses/Chat API choice, streaming/tool-call schema, accepted token/reasoning parameters, and current pricing in `openai-provider-contract.md`. Use the `openai-platform-api-key` skill for credential setup; never print or commit a key. Separately verify the real csjones/production xAI model values without exposing credentials and record whether they already match the July ruling (`grok-4.3`).

- [ ] **Step 2: Write failing provider-normalisation tests**

Given equivalent scripted Anthropic, xAI, and OpenAI streams, assert normalized text deltas, tool calls, usage, finish reason, errors, and cancellation are identical. Assert the OpenAI request matches the captured official contract and never sends a parameter the selected model rejects.

- [ ] **Step 3: Introduce `Provider` and remove boolean provider branching**

Centralize provider resolution and per-component overrides. Convert `HasAiChat`, Planner, summariser, learning synthesiser, guardrails, catalogue selection, and cost accounting one component at a time, keeping Anthropic/xAI characterisation tests green after each commit.

- [ ] **Step 4: Implement the OpenAI client and corpus alias**

Use the already-installed `openai-php/client` dependency unless the captured official contract proves it cannot support the required API. Normalize streaming/tool/usage events at the client boundary. `ProceduralCorpusLoader` accepts `openai`; `ProceduralCorpus::active(..., 'openai')` resolves the xAI-form schema intentionally and is covered by parity tests.

- [ ] **Step 5: Reconcile xAI provenance and record OpenAI cassettes**

Make the configured xAI model, cassette directory, and provenance test agree. Record the minimum OpenAI query-type/tool/cancellation/provider-parity cassettes against a dedicated non-production project. Store no prompt secrets, personal data, or API credentials in fixtures.

- [ ] **Step 6: Run provider and full Fyn gates**

Run:

```bash
./vendor/bin/pest tests/Feature/AI/OpenAiProviderTest.php \
  tests/Feature/AI/OpenAiToolSchemaGoldenMasterTest.php \
  tests/Feature/Fyn/Eval/CassetteModelProvenanceTest.php
./vendor/bin/pest tests/Feature/AI tests/Feature/Fyn tests/Unit/Services/AI
```

Expected: PASS with all three provider contracts; switching back to xAI is environment-only.

- [ ] **Step 7: Stage dormant, then canary explicitly**

Deploy with xAI still active. Run OpenAI via a dedicated component override/test identity, inspect cost/error/tool/eval evidence, and obtain CSJ's routing decision. Only then enable OpenAI for the approved component or keep it wired but dormant. Run web and `/m` semantic parity plus rollback-by-env proof before the continuation production release.

- [ ] **Step 8: Commit and release**

```bash
git commit -m "refactor: normalize Fyn provider selection"
git commit -m "feat: add OpenAI provider support"
git commit -m "test: add three-provider Fyn parity"
```

Pass the standard csjones, agent-browser, go/no-go, production, and post-release gates before Task 31 begins.

---

### Task 31: Execute and release the investment campaign plan

**Files:**
- Consume completely and track every checkbox in: `July/July6Updates/investment-campaign-spec.md` and `July/July6Updates/investment-campaign-plan.md`.
- Consume completely: `July/July3Updates/campaign-playbook.md` and `July/July6Updates/pensionCampaign.md` as the delivered template.
- Modify/create exactly the substrate, public-surface, state/corpus, store-reader, route, service, test, and browser files enumerated by the imported investment plan.
- Create: `tests/Browser/acceptance/investment-campaign-new-user.yaml`
- Create: `tests/Browser/acceptance/investment-campaign-existing-user.yaml`
- Modify: `docs/online-readiness/july-plan-register.yaml`, coverage matrix, and release evidence.

**Interfaces:**
- Campaign key/URL is `investmentcheck` only after the Task 29 decision record confirms it.
- Uses shared campaign re-entry, `last_completed_campaign`, provider-normalized Fyn, and existing `InvestmentStrategySource`; no parallel campaign framework is introduced.
- Produces a public funnel/plan, fresh-user and completed-user delta walks, desktop and `/m` investment landing, campaign affinity, milestones/actions, and complete release evidence.

- [ ] **Step 1: Revalidate and freeze the imported spec**

Read the entire spec and plan against the post-Task-30 tree. Record path/signature drift as a document-history amendment before code; do not silently reinterpret it. CSJ confirms URL, final copy, funnel questions, and social assets. Keep income and expenditure sections because the canonical plan marks them blocking.

- [ ] **Step 2: Execute Slice A exactly and review**

Implement substrate/config seams with failing tests first. Keep the campaign disabled until all Slice A tests and SaveTax/pensioncheck regressions are green. Commit and independently review the slice.

- [ ] **Step 3: Execute Slice B exactly and review**

Use the `html-template` skill. Build the public funnel, estimate/plan page, registration pull-through, routes, homepage entry, metadata, and approved assets. Test XSS/query handling, base-path rewriting, phone routing, no-JavaScript degradation, and both target builds. Commit and independently browser-review public desktop/mobile widths.

- [ ] **Step 4: Execute Slice C exactly and review**

Build the investment walk, provider-correct corpus/in-code states, store reader, synchronous risk-profile ensure, advice/synthesis, terminal, actions, affinity, and milestones. Preserve the read-only advice/write-handoff boundary. Run every trap-table check and regression listed by the imported plan before enabling the campaign.

- [ ] **Step 5: Execute Slice D live loop**

On csjones, the browser agent completes anonymous funnel -> registration -> verification -> full fresh walk and a completed-user delta walk on desktop and `/m`. Verify database rows, server-sent events, action IDs, point awards, milestones, final investment figures, cache coherence, and zero SaveTax/pensioncheck bleed. Loop until both manifests and the imported Slice D contract are green.

- [ ] **Step 6: Complete an isolated production release train**

Run full automated/agent gauntlets, staging soak proportionate to the campaign's risk, deployment/rollback rehearsal, CSJ go/no-go, `dev -> main`, and 15-minute/24-hour/seven-day checks. Update every investment work-package row to `delivered` only after production evidence exists.

---

### Task 32: Execute and release the estate and Inheritance Tax campaign plan

**Files:**
- Consume completely and track every checkbox in: `July/July6Updates/estate-campaign-spec.md` and `July/July6Updates/estate-campaign-plan.md`.
- Consume completely: `July/July3Updates/campaign-playbook.md` and the delivered investment/pension campaign evidence.
- Modify/create exactly the substrate, public-surface, state/corpus, `capture_will_status`, navigation allowlist, service, test, and browser files enumerated by the imported estate plan.
- Create: `tests/Browser/acceptance/estate-campaign-new-user.yaml`
- Create: `tests/Browser/acceptance/estate-campaign-existing-user.yaml`
- Modify: `docs/online-readiness/july-plan-register.yaml`, coverage matrix, and release evidence.

**Interfaces:**
- Starts only after Task 31's seven-day production check is green.
- Campaign key/URL is `inheritancecheck` only after the decision record confirms it.
- Uses `EstateStrategySource`, exact tax-config-driven Inheritance Tax calculations, one new `capture_will_status` write tool, shared campaign re-entry, and Tier 2 teaser enforcement.
- Produces a public funnel/plan, fresh/delta walks, desktop and `/m` estate landing, truthful teaser/gating, actions/milestones, and complete release evidence.

- [ ] **Step 1: Revalidate and freeze the imported spec**

Read the entire spec and plan against the post-investment tree. Record path/signature drift before code. CSJ confirms URL, copy, questions, teaser treatment, and assets. Re-verify every tax rule/value through `TaxConfigService`; never copy a figure from the 2026 document into code.

- [ ] **Step 2: Execute Slice A exactly and review**

Add the campaign substrate/config and required navigation allowlist entries with failing tests first. Keep the campaign disabled; run all existing campaign regressions and the imported trap table.

- [ ] **Step 3: Execute Slice B exactly and review**

Use the `html-template` skill. Build the public funnel, `EstateEstimateService`, plan/registration surfaces, routes, homepage entry, metadata, and approved assets. Pin exact calculation fixtures, XSS/base-path/no-JavaScript behaviour, phone routing, and both builds.

- [ ] **Step 4: Execute Slice C exactly and review**

Implement `capture_will_status` schemas/handler, state/corpus lockstep, skips, advice, synthesis, terminal, actions, milestones, and Tier 2 teaser. Validate every write input, keep advice read-only, and prove the campaign never creates a will or legal instrument from ambiguous intent.

- [ ] **Step 5: Execute Slice D live loop**

On csjones, the browser agent runs fresh and existing-user journeys on desktop and `/m`. Verify exact estate/Inheritance Tax figures, will-status write truth, joint/spouse treatment, database rows, server-sent events, actions, milestones, teaser access, and zero regression in the three earlier campaigns. Loop until every imported acceptance and both manifests are green.

- [ ] **Step 6: Complete the final isolated production release train**

Run the full automated/agent gauntlet, staging soak, rollback rehearsal, CSJ go/no-go, production promotion, and post-release checks. Mark the estate work packages delivered, verify all 34 July artifacts still exist on `main` and `dev`, and close the master July plan register only when no executable work package lacks production evidence or an explicit CSJ-approved disposition.

---

## Execution order and pull-request grouping

| Order | Tasks | Suggested branch | Required live gate |
|---|---:|---|---|
| 1 | 1 | `codex/readiness-contracts` | none; documentation/architecture test |
| 2 | 2-3 | `codex/quality-lint-runner` | local quality lint |
| 3 | 4 | `codex/quality-ci` | GitHub workflow run green |
| 4 | 5-7 | `codex/playwright-agent-gates` | local desktop and `/m` smoke |
| 5 | 8-10 | `codex/fyn-p0-remediation` | csjones 19079 web and `/m` acceptance |
| 6 | 11 | `codex/observability` | csjones scrubbed Sentry proof |
| 7 | 12 | `codex/database-queue` | csjones async worker proof |
| 8 | 13 | `codex/auth-payment-truth` | csjones mail/Revolut sandbox failures |
| 9 | 14 | `codex/dashboard-error-truth` | csjones desktop and `/m` unavailable state |
| 10 | 15 | `codex/gdpr-profile-delete` | csjones second-factor UI journey |
| 11 | 16 | `codex/gdpr-retention` | purge rehearsal tests; no prod mutation |
| 12 | 17 | `codex/joint-ownership-auth` | csjones desktop and `/m` spouse/unlink |
| 13 | 18 | `codex/tax-rollover` | frozen-clock tests and csjones config proof |
| 14 | 19 | `codex/financial-fixtures` | exact financial tests; affected UI checks |
| 15 | 20 | `codex/write-coherence` | csjones web-write -> `/m` refresh |
| 16 | 21 | `codex/request-scale` | csjones query/timing proof |
| 17 | 22 | `codex/fyn-contract-closure` | Fyn route/corpus/compliance/eval acceptance |
| 18 | 22A-22C | `codex/fyn-advice-case` | guidance policy, single preparation and Advice Case checkpoint |
| 19 | 22D-22E | `codex/fyn-evidence-routing` | shared evidence and planner-efficiency checkpoint |
| 20 | 22F-22H | `codex/fyn-trusted-memory` | migration plus memory control on web and `/m` |
| 21 | 22I-22J | `codex/fyn-policy-evals` | mechanical policy and immutable-staging Fyn go/no-go |
| 22 | 23 | `codex/laravel-13` | full csjones gauntlet |
| 23 | 24 | `codex/e2e-matrix` | automated full matrix green |
| 24 | 25-26 | release-candidate `dev` | whole agent gauntlet and seven-day soak |
| 25 | 27 | release-candidate `dev` | rehearsal and CSJ go/no-go |
| 26 | 28 | `dev -> main` | initial production and post-release checks |
| 27 | 29 | `codex/july-delivered-plan-closure` | pension/gamification web + `/m` release train |
| 28 | 30 | `codex/openai-provider` | three-provider canary and release train |
| 29 | 31 | `codex/investment-campaign` | investment campaign full release train |
| 30 | 32 | `codex/estate-campaign` | estate campaign full release train |

Every feature branch is deployed to csjones before its PR merges when it has runtime/user-visible impact. Solo-author merge administration is permitted only after CSJ approval and the live gate.

## Gate summary

- Gate 0 complete after Task 1.
- Gate 1 quality spine complete after Tasks 2-7 and a green protected PR run.
- Gate 2 blocker remediation complete after Tasks 8-23 plus Tasks 22A-22J, all four evidence-first Fyn checkpoints, and all critical/high ledger records are green/inapplicable.
- Gate 3 whole-product gauntlet complete after Tasks 24-25.
- Gate 4 staging release candidate complete after Task 26.
- Gate 5 production authorization/rehearsal complete after Task 27.
- Gate 6 initial production/post-release proof complete after Task 28.
- Gate 7 continuation release trains complete after Tasks 29-32.

## Plan-level verification checklist

- Every design acceptance criterion maps to at least one task.
- Every critical/high July workstream maps to Tasks 8-23.
- The approved evidence-first Fyn design maps to Tasks 22A-22J and is a pre-launch Gate 2 requirement.
- Fyn launch defaults remain `guidance`, planner `shadow`, learning disabled unless the signed architecture go/no-go explicitly approves a narrower change.
- One Advice Case, one evidence snapshot, complexity-gated planning, canonical typed memory, desktop/`/m` memory control and mechanical policy are verified before Task 23.
- Automated lint, PHP, frontend, build, desktop browser, and `/m` browser gates map to Tasks 2-7 and 24.
- Agent interaction/evidence maps to Tasks 7, 25, 26, 28, and every continuation.
- Staging soak, rollback, go/no-go, production, and post-release checks map to Tasks 26-28 and repeat for Tasks 29-32.
- All 34 July artifacts and every executable work package map through Task 1's source register.
- Delivered SaveTax, gamification, milestone, and pension plans are regression/polish contracts rather than rebuild instructions.
- OpenAI provider expansion, investment campaign, and estate campaign are included as isolated continuation release trains.
- Production mutations remain operator/CSJ-authorized only.
