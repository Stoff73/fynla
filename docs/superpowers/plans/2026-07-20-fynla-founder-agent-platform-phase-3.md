# Fynla Founder-Agent Platform Phase 3 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver the founder dashboard, Git-backed configuration publication/rollback, production operations and controlled three-founder rollout.

**Architecture:** A separate Laravel/Inertia/Vue application at `agents.fynla.org` authenticates the three founders with Google Workspace and manages `fynla-agents` through a least-privilege GitHub App. Drafts are branches/pull requests, ordinary publishes require validation, high-risk publishes require a distinct second founder review, and release tags trigger atomic activation in the Python service. The VPS adds encrypted off-site backup, independent monitoring, restore drills and founder operating evidence.

**Tech Stack:** PHP 8.3, Laravel 12, Inertia 2, Vue 3, Vite, Tailwind, Laravel Socialite, GitHub App APIs, PostgreSQL 16, Pest, Vitest, the Phase 1/2 Python services, Docker Compose v2, Caddy 2 and restic.

## Global Constraints

- Phases 1 and 2 and their founder release gates must be green before production rollout.
- The dashboard is a separate application on the VPS, not part of the customer-facing Laravel app on SiteGround.
- Only the three allowlisted founder Google Workspace identities can sign in.
- Dashboard edits always create Git commits; there is no live configuration editor or direct filesystem write to the running service.
- A release activates one immutable commit SHA only after schema, prompt, tool and golden-case validation passes.
- Prompt wording, interjection thresholds and ordinary routing may be published by one founder after validation.
- Write scopes, source access, security controls and prohibited-action changes require approval by a different founder; infrastructure/security changes must include Chris.
- Failed validation or failed health check leaves the previous active release unchanged.
- Rollback reactivates a previously validated commit through a new audited release tag and posts a Slack notice.
- The dashboard GitHub App can manage only `fynla-agents`; it has no access to application code or `fynla-vault`.
- Backups are encrypted off-site every six hours, retained for 30 daily and 12 monthly restore points.
- Recovery objectives are RPO six hours and RTO four hours.
- Operational logs retain metadata for 30 days; append-only action/config audit records retain at least seven years.
- Browser acceptance uses installed Google Chrome only.
- Use TDD and one focused commit per task.

---

## Phase 3 file map

### Dashboard

- `dashboard/app/Models/Founder.php` — allowlisted founder identity and role metadata.
- `dashboard/app/Http/Controllers/Auth/GoogleController.php` — Google Workspace OAuth callback.
- `dashboard/app/Http/Middleware/RequireFounder.php` — three-founder allowlist enforcement.
- `dashboard/app/Services/GitHub/AgentConfigRepository.php` — branch, commit, pull request, validation status, release tag and history operations only for `fynla-agents`.
- `dashboard/app/Services/Config/ChangeClassifier.php` — ordinary versus high-risk path/semantic classification.
- `dashboard/app/Policies/ConfigReleasePolicy.php` — author/reviewer/Chris approval rules.
- `dashboard/resources/js/Pages/Agents/Index.vue` — agent/prompt library.
- `dashboard/resources/js/Pages/Agents/Edit.vue` — draft editor with Markdown preview.
- `dashboard/resources/js/Pages/Releases/Index.vue` — validation, publish, history/diff and rollback.
- `dashboard/resources/js/Pages/Operations/Index.vue` — connector/release/action status.

### Release and operations

- `src/fynla_agent/config/webhook.py` — signed GitHub release/config event ingestion.
- `src/fynla_agent/config/validation.py` — schema and golden-case validation command.
- `src/fynla_agent/config/releases.py` — release tag verification, atomic activation and recovery.
- `deploy/backup.sh` — encrypted PostgreSQL/config backup through restic.
- `deploy/restore.sh` — clean-target restore and derived-index rebuild.
- `deploy/system-check.sh` — health, capacity and release checks.
- `docs/runbooks/` — dashboard, release, rollback, incident, backup and founder guides.

---

### Task 1: Scaffold the separate Laravel/Inertia/Vue dashboard and founder-only login

**Files:**
- Create: `dashboard/composer.json`
- Create: `dashboard/package.json`
- Create: `dashboard/app/Models/Founder.php`
- Create: `dashboard/database/migrations/2026_07_20_000001_create_founders_table.php`
- Create: `dashboard/app/Http/Controllers/Auth/GoogleController.php`
- Create: `dashboard/app/Http/Middleware/RequireFounder.php`
- Create: `dashboard/config/founders.php`
- Modify: `dashboard/config/auth.php`
- Create: `dashboard/routes/web.php`
- Create: `dashboard/resources/js/Layouts/FounderLayout.vue`
- Create: `dashboard/resources/js/Pages/Home.vue`
- Create: `dashboard/tests/Feature/Auth/GoogleFounderLoginTest.php`
- Create: `dashboard/tests/Feature/Auth/FounderMiddlewareTest.php`

**Interfaces:**
- Consumes: Google OAuth identity claims and secret-backed exact founder email allowlist.
- Produces: authenticated `Founder` session with stable internal ID and role; all dashboard routes protected by `founder` middleware.

- [ ] **Step 1: Scaffold exact framework majors and test harness**

Run inside `dashboard/`:

```bash
composer create-project laravel/laravel:^12.0 .
composer require inertiajs/inertia-laravel:^2.0 laravel/socialite:^5.0
npm install @inertiajs/vue3@^2 vue@^3 vite@^6
composer require --dev pestphp/pest:^3 pestphp/pest-plugin-laravel:^3
```

Expected: composer/npm lockfiles created; no dependency is added to the customer app.

- [ ] **Step 2: Write failing recognised/unknown founder login tests**

```php
<?php

use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;

it('signs in an allowlisted founder', function (): void {
    config()->set('founders.emails', ['chris@fynla.org']);
    $oauthUser = (new User)->map([
        'id' => 'google-1', 'email' => 'chris@fynla.org', 'name' => 'Chris Slater-Jones',
        'user' => ['hd' => 'fynla.org', 'email_verified' => true],
    ]);
    $provider = Mockery::mock(AbstractProvider::class);
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('user')->andReturn($oauthUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get('/auth/google/callback')->assertRedirect('/agents');
    $this->assertAuthenticated('founder');
});

it('rejects an identity outside the founder allowlist', function (): void {
    config()->set('founders.emails', ['chris@fynla.org']);
    $oauthUser = (new User)->map([
        'id' => 'google-2', 'email' => 'outsider@example.com', 'name' => 'Outsider',
        'user' => ['hd' => 'example.com', 'email_verified' => true],
    ]);
    $provider = Mockery::mock(AbstractProvider::class);
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('user')->andReturn($oauthUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get('/auth/google/callback')->assertForbidden();
});
```

- [ ] **Step 3: Run tests and observe missing controller/guard failures**

Run: `cd dashboard && php artisan test tests/Feature/Auth`

Expected: FAIL because founder authentication is absent.

- [ ] **Step 4: Implement founder-only authentication**

`config/founders.php` reads three exact email addresses from `FYNLA_FOUNDER_EMAILS`, normalises lowercase and exposes no default. The callback requires verified Google email, exact allowlist membership and hosted-domain claim matching the Fynla Workspace domain. Persist Google subject, email, display name and last login; never store OAuth access/refresh tokens because the dashboard's GitHub work uses its service App.

The founder migration contains only `id`, unique `google_subject`, unique `email`, `display_name`, `role`, `active`, `last_login_at` and timestamps. Add a dedicated `founder` session guard/provider in `config/auth.php`; `RequireFounder` rejects inactive records on every request.

- [ ] **Step 5: Run auth tests and asset tests**

Run: `cd dashboard && php artisan test tests/Feature/Auth && npm run build`

Expected: all auth tests PASS and Vite build succeeds.

- [ ] **Step 6: Commit**

```bash
git add dashboard
git commit -m "feat: add founder-only agent dashboard"
```

### Task 2: Add Git-backed drafts, diffs and validation status

**Files:**
- Create: `dashboard/app/Services/GitHub/AgentConfigRepository.php`
- Create: `dashboard/app/Services/GitHub/AgentConfigRepositoryContract.php`
- Create: `dashboard/app/Data/ConfigDraftData.php`
- Create: `dashboard/app/Http/Controllers/AgentController.php`
- Create: `dashboard/app/Http/Controllers/ConfigDraftController.php`
- Create: `dashboard/resources/js/Pages/Agents/Index.vue`
- Create: `dashboard/resources/js/Pages/Agents/Edit.vue`
- Create: `dashboard/resources/js/Components/MarkdownPreview.vue`
- Create: `dashboard/tests/Feature/Agents/ConfigDraftTest.php`
- Create: `dashboard/tests/Unit/GitHub/AgentConfigRepositoryTest.php`
- Create: `dashboard/resources/js/Pages/Agents/__tests__/Edit.test.js`

**Interfaces:**
- Consumes: founder session and GitHub App installation restricted to `fynla-agents`.
- Produces: `createDraft(founder, baseSha, path, content, changeNote) -> ConfigDraftData`; one branch/commit/pull request per draft.

- [ ] **Step 1: Define the repository contract**

```php
<?php

interface AgentConfigRepositoryContract
{
    public function listFiles(string $commitSha): array;
    public function getFile(string $commitSha, string $path): array;
    public function createDraft(Founder $founder, string $baseSha, string $path, string $content, string $changeNote): ConfigDraftData;
    public function diff(string $baseSha, string $headSha): array;
    public function validationStatus(string $headSha): array;
}

final readonly class ConfigDraftData
{
    public function __construct(
        public string $branch,
        public string $baseSha,
        public string $headSha,
        public string $path,
        public string $changeNote,
        public string $pullRequestUrl,
    ) {}
}
```

- [ ] **Step 2: Write failing draft boundary tests**

```php
it('creates a named branch commit and pull request with a change note', function (): void {
    $draft = $this->service->createDraft(
        $this->founder,
        'base-sha',
        'prompts/founder-system.md',
        '# Updated prompt',
        'Clarify source citations',
    );

    expect($draft->branch)->toStartWith('dashboard/chris-slater-jones/')
        ->and($draft->pullRequestUrl)->toStartWith('https://github.com/')
        ->and($draft->changeNote)->toBe('Clarify source citations');
});

it('rejects edits outside the agent repository paths', function (): void {
    $this->post('/drafts', ['path' => '../fynla/app/Models/User.php'])
        ->assertSessionHasErrors('path');
});
```

- [ ] **Step 3: Run tests and observe missing-service failures**

Run: `cd dashboard && php artisan test tests/Feature/Agents tests/Unit/GitHub`

Expected: FAIL because draft services do not exist.

- [ ] **Step 4: Implement GitHub App draft operations**

Allow only paths under `agents/`, `prompts/`, `routing/`, `sources/`, `models/`, `tools/`, `schemas/`, `tests/golden/`. Create a branch following `dashboard/{founder-slug}/{uuid}`, one commit containing the edited path, and a pull request with author, change note and base SHA. Refuse a stale base SHA and show the new diff instead of silently rebasing.

- [ ] **Step 5: Implement the Vue editor**

The page displays path, current commit, change-note field, plain Markdown/YAML editor, rendered Markdown preview when relevant, diff preview and save-draft button. It does not display provider credentials or permit arbitrary repo selection.

Write a Vitest case that edits the content, requires a change note and verifies the submitted payload contains the visible base SHA/path/content only.

- [ ] **Step 6: Run dashboard draft tests**

Run: `cd dashboard && php artisan test tests/Feature/Agents tests/Unit/GitHub && npm test -- --run resources/js/Pages/Agents/__tests__/Edit.test.js`

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add dashboard/app dashboard/resources/js dashboard/tests
git commit -m "feat: edit agent configuration through Git drafts"
```

### Task 3: Enforce validation and two-founder high-risk publication

**Files:**
- Create: `dashboard/app/Services/Config/ChangeClassifier.php`
- Create: `dashboard/app/Policies/ConfigReleasePolicy.php`
- Create: `dashboard/app/Http/Controllers/ReleaseController.php`
- Create: `dashboard/resources/js/Pages/Releases/Show.vue`
- Create: `dashboard/tests/Unit/Config/ChangeClassifierTest.php`
- Create: `dashboard/tests/Feature/Releases/PublishReleaseTest.php`
- Create: `src/fynla_agent/config/validation.py`
- Create: `tests/unit/config/test_validation_command.py`
- Modify in `fynla-agents`: `.github/workflows/validate.yml`

**Interfaces:**
- Consumes: draft diff, validation check conclusion, founder identity/reviews.
- Produces: merged config commit plus annotated `agent-release/<UTC timestamp>` tag; high-risk release requires a distinct qualified approval.

- [ ] **Step 1: Write exact high-risk classification tests**

```php
it('classifies tool source and prohibited policy changes as high risk', function (string $path): void {
    expect(app(ChangeClassifier::class)->classify([$path]))->toBe('high');
})->with([
    'tools/allowlist.yaml',
    'sources/allowlist.yaml',
    'tools/prohibited-actions.yaml',
]);

it('classifies prompt copy and channel threshold changes as ordinary', function (string $path): void {
    expect(app(ChangeClassifier::class)->classify([$path]))->toBe('ordinary');
})->with(['prompts/founder-system.md', 'agents/founder-assistant.yaml']);
```

- [ ] **Step 2: Write publication policy tests**

```php
it('allows an ordinary validated release by its founder author', function (): void {
    $release = ReleaseFixture::ordinary(validated: true, author: $this->chris);
    expect($this->policy->publish($this->chris, $release))->toBeTrue();
});

it('requires a different founder and Chris for infrastructure security', function (): void {
    $release = ReleaseFixture::highRisk(
        author: $this->azlan,
        reviews: [$this->brett],
        includesInfrastructureSecurity: true,
    );
    expect($this->policy->publish($this->azlan, $release))->toBeFalse();
    $release->addReview($this->chris);
    expect($this->policy->publish($this->azlan, $release))->toBeTrue();
});
```

- [ ] **Step 3: Run tests and observe missing classifier/policy failures**

Run: `cd dashboard && php artisan test tests/Unit/Config tests/Feature/Releases`

Expected: FAIL because classifier and publication policy are absent.

- [ ] **Step 4: Implement the validation command and GitHub check**

The GitHub workflow runs `python -m fynla_agent.config.validation --checkout "$GITHUB_WORKSPACE" --commit-sha "$GITHUB_SHA"`. The command loads the strict release, scans for secrets, validates schemas/references/tool phases/model aliases, runs prompt/tool golden cases and exits non-zero on any failure. The workflow reports one required `agent-config/validate` check against the pull request SHA.

Write the Python test to assert malformed YAML, missing prompt, unknown tool, commercial model hard-code, secret-like key and failing golden case each return non-zero with a safe path/error code.

- [ ] **Step 5: Implement publication through GitHub only**

The dashboard verifies the head SHA has the required successful check, applies `ConfigReleasePolicy`, uses the GitHub App to merge the config PR, and creates an annotated tag following `agent-release/{YYYYMMDDTHHMMSSZ}` containing author, approvers, change note, risk and merged commit SHA. It does not call the agent service.

- [ ] **Step 6: Run publication tests**

Run: `cd dashboard && php artisan test tests/Unit/Config tests/Feature/Releases && cd .. && pytest tests/unit/config/test_validation_command.py -q`

Expected: PASS for ordinary/high-risk/Chris-required paths and all validation failures.

- [ ] **Step 7: Commit platform and config workflow**

Platform:

```bash
git add dashboard src/fynla_agent/config/validation.py tests/unit/config/test_validation_command.py
git commit -m "feat: validate and govern agent releases"
```

`fynla-agents`:

```bash
git add .github/workflows/validate.yml
git commit -m "ci: validate agent configuration releases"
```

### Task 4: Activate and roll back immutable releases with Slack notices

**Files:**
- Create: `src/fynla_agent/config/webhook.py`
- Create: `src/fynla_agent/config/releases.py`
- Create: `src/fynla_agent/config/notices.py`
- Create: `dashboard/app/Http/Controllers/RollbackController.php`
- Create: `dashboard/resources/js/Pages/Releases/Index.vue`
- Create: `dashboard/resources/js/Components/ReleaseDiff.vue`
- Create: `tests/integration/config/test_release_webhook.py`
- Create: `tests/integration/config/test_release_recovery.py`
- Create: `dashboard/tests/Feature/Releases/RollbackReleaseTest.php`

**Interfaces:**
- Consumes: signed GitHub tag/repository events and validated commit SHA.
- Produces: atomic active release, health-tested rollback and one `#fynla-agents` notice.

- [ ] **Step 1: Write signature, failed-activation and rollback tests**

```python
async def test_unsigned_release_webhook_is_rejected(client):
    response = await client.post("/webhooks/github", json=release_event())
    assert response.status_code == 401


async def test_unhealthy_release_keeps_previous_sha(release_service):
    release_service.health_probe.fail_for("new-sha")
    with pytest.raises(ReleaseHealthFailed):
        await release_service.activate("new-sha", release_metadata())
    assert release_service.active_sha() == "previous-sha"


async def test_rollback_reactivates_validated_commit_and_notifies(release_service, slack_api):
    await release_service.activate("older-validated-sha", rollback_metadata())
    assert release_service.active_sha() == "older-validated-sha"
    assert "Rollback" in slack_api.single_operations_notice().text
```

- [ ] **Step 2: Run tests and observe missing release-service failures**

Run: `pytest tests/integration/config/test_release_webhook.py tests/integration/config/test_release_recovery.py -q && cd dashboard && php artisan test tests/Feature/Releases/RollbackReleaseTest.php`

Expected: FAIL because webhook activation/rollback are absent.

- [ ] **Step 3: Implement signed release event ingestion**

Verify GitHub HMAC signature and delivery ID, accept only tag events from the configured `fynla-agents` repository, require tag prefix `agent-release/`, fetch the tagged commit into a new read-only release directory, run validation, atomically activate, run readiness probe, and automatically restore the previous pointer if readiness fails.

- [ ] **Step 4: Implement history and rollback UI**

The release page shows commit/tag, author, reviewers, risk, change note, check result, active state and rendered diff. Rollback is allowed only to a previously validated commit, requires a new change note, runs the same risk policy, and creates a new annotated release tag pointing at the prior commit.

- [ ] **Step 5: Implement operations notices**

Post one `#fynla-agents` message on publish, validation failure, automatic recovery and rollback. Include event type, author/approver, change note, commit/tag link, prior/new active SHA and health outcome; never include secrets or full prompts.

- [ ] **Step 6: Run release/rollback tests and commit**

Run: `pytest tests/integration/config -q && cd dashboard && php artisan test tests/Feature/Releases`

Expected: PASS; unsigned/duplicate events rejected, unhealthy release recovered, rollback audited/notified.

```bash
git add src/fynla_agent/config dashboard/app dashboard/resources/js dashboard/tests tests/integration/config
git commit -m "feat: publish and roll back agent releases"
```

### Task 5: Add dashboard operations visibility, encrypted backups and recovery drills

**Files:**
- Create: `dashboard/app/Http/Controllers/OperationsController.php`
- Create: `dashboard/resources/js/Pages/Operations/Index.vue`
- Create: `dashboard/tests/Feature/Operations/OperationsViewTest.php`
- Create: `deploy/backup.sh`
- Create: `deploy/restore.sh`
- Create: `deploy/system-check.sh`
- Modify: `deploy/compose.yaml`
- Modify: `deploy/Caddyfile`
- Create: `tests/operations/test_backup_manifest.py`
- Create: `tests/operations/test_restore_drill.py`
- Create: `docs/runbooks/backup-restore.md`
- Create: `docs/runbooks/incident-response.md`

**Interfaces:**
- Consumes: safe health/status endpoints, PostgreSQL dump, config/vault repository metadata and restic repository secret.
- Produces: founder operations view, encrypted six-hour restore points, verified clean-target restore and independent alerting.

- [ ] **Step 1: Write safe operations-view tests**

```php
it('shows safe status without credentials or document content', function (): void {
    $response = $this->actingAs($this->chris, 'founder')->get('/operations')->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('database.status')
        ->has('queue.lag_seconds')
        ->has('sources')
        ->has('active_release.sha')
        ->missing('secrets')
        ->missing('documents'));
});
```

- [ ] **Step 2: Write backup-manifest and restore tests**

```python
def test_backup_manifest_contains_required_durable_state(latest_backup_manifest):
    assert set(latest_backup_manifest["included"]) >= {
        "action_proposals", "action_approvals", "action_executions",
        "audit_events", "founder_tokens", "config_releases",
    }
    assert latest_backup_manifest["encrypted"] is True


def test_restore_rebuilds_derived_index_and_preserves_audit(restored_environment):
    assert restored_environment.audit_chain_matches_source()
    assert restored_environment.search_index_was_rebuilt()
    assert restored_environment.readiness_is_green()
```

- [ ] **Step 3: Run tests and observe missing operations/backup failures**

Run: `cd dashboard && php artisan test tests/Feature/Operations && cd .. && pytest tests/operations -q`

Expected: FAIL because status view and scripts are absent.

- [ ] **Step 4: Implement backup and restore scripts**

`backup.sh` obtains an application-consistent PostgreSQL dump, writes a JSON manifest with schema version/commit/config SHA/table counts/checksums, then uses restic with a secret-supplied repository/password. It schedules every six hours and applies 30 daily/12 monthly retention. It never stores the restic password in the manifest or Git.

`restore.sh` requires an empty target database, restores durable tables/credentials/config releases, verifies checksums, resets all jobs stuck in `executing` to a recoverable state, rebuilds derived documents/chunks/embeddings from sources, runs reconciliation, and leaves write tools disabled until readiness is green.

- [ ] **Step 5: Implement independent monitoring**

`system-check.sh` checks public HTTPS, active release, database, queue lag, Slack Socket Mode, LiteLLM, source freshness, last backup age, memory and disk. Caddy publishes only safe readiness/liveness. Configure an external monitor outside the VPS to email all founders when HTTPS is down; Slack alerts are secondary because the VPS may be unavailable.

- [ ] **Step 6: Run a clean-target restore drill**

Run: `pytest tests/operations -q`

Expected: PASS with measured restore under four hours and latest recoverable durable event no older than six hours. Record actual timestamps and durations in the runbook evidence.

- [ ] **Step 7: Run operations/dashboard tests and commit**

Run: `cd dashboard && php artisan test tests/Feature/Operations && npm run build && cd .. && pytest tests/operations -q && docker compose -f deploy/compose.yaml config --quiet`

Expected: PASS, build succeeds and Compose validates.

```bash
git add dashboard deploy tests/operations docs/runbooks/backup-restore.md docs/runbooks/incident-response.md
git commit -m "feat: operate and recover founder platform"
```

### Task 6: Complete staging, Chrome acceptance and controlled founder rollout

**Files:**
- Create: `docs/runbooks/founder-guide.md`
- Create: `docs/runbooks/production-deploy.md`
- Create: `docs/runbooks/production-rollback.md`
- Create: `docs/evidence/phase-3.md`
- Create: `docs/evidence/founder-acceptance.md`
- Create: `tests/acceptance/test_release_permissions.py`
- Create: `tests/architecture/test_siteground_isolation.py`

**Interfaces:**
- Consumes: all three phase gates, staging services, founder identities and production DNS/VPS.
- Produces: production-ready founder platform with documented acceptance and rollback.

- [ ] **Step 1: Write final architecture and permission tests**

```python
def test_siteground_and_customer_app_are_not_platform_targets(deployment_manifest):
    assert "fynla.org/public_html" not in deployment_manifest
    assert "mysql" not in deployment_manifest.lower()
    assert {"mcp.fynla.org", "agents.fynla.org"} <= set(parse_hosts(deployment_manifest))


def test_dashboard_github_app_is_agent_repo_only(github_app_manifest):
    assert github_app_manifest.repositories == {"fynla-agents"}
    assert "fynla" not in github_app_manifest.repositories
    assert "fynla-vault" not in github_app_manifest.repositories
```

- [ ] **Step 2: Run the full automated release gate**

Run: `pytest -q && ruff check src tests && cd dashboard && php artisan test && npm test -- --run && npm run build && cd .. && docker compose -f deploy/compose.yaml config --quiet`

Expected: all Python/Pest/Vitest tests PASS, Ruff clean, Vite build succeeds, Compose validates.

- [ ] **Step 3: Run installed-Google-Chrome acceptance**

Using the Chrome connector only, verify at production-like staging:

1. each founder can sign in through Google Workspace; a non-founder is rejected;
2. prompt edit creates a Git branch/commit/PR and visible diff;
3. ordinary validated prompt release publishes by its author;
4. source/tool/security release is blocked until a second founder approval, including Chris where required;
5. bad validation never changes the active SHA;
6. publish notice appears in `#fynla-agents`;
7. rollback reactivates the prior SHA and posts a notice;
8. Slack knowledge/action workflows from Phases 1 and 2 remain green;
9. operations view contains status but no secrets or document bodies;
10. mobile-width dashboard remains usable for approval/history/rollback.

Record screenshots, URLs, commit/config SHAs, founder identities and outcomes in `docs/evidence/phase-3.md`.

- [ ] **Step 4: Rehearse production deployment and rollback**

Provision the Fynla-owned VPS, create SiteGround DNS A records, deploy Compose/Caddy/secrets, restore a staging backup, run readiness, then execute the rollback runbook to the prior application and config release. Confirm `fynla.org` application health before and after; no SiteGround files change.

- [ ] **Step 5: Issue founder credentials and train through real workflows**

Issue separate founder MCP tokens once, store only hashes, verify revocation/rotation, and have each founder complete: ask a grounded question, report a bug, approve a GitHub issue, create a domain decision, and find the result in its canonical system. The founder guide uses screenshots and plain language, not commands.

- [ ] **Step 6: Record explicit go/no-go**

`docs/evidence/founder-acceptance.md` records Azlan, Brett and Chris decisions, accepted limitations, production commit/config SHA, provider aliases, backup timestamp, restore duration and rollback result. Any rejection keeps write tools disabled.

- [ ] **Step 7: Commit the release evidence**

```bash
git add docs/runbooks/founder-guide.md docs/runbooks/production-deploy.md docs/runbooks/production-rollback.md docs/evidence/phase-3.md docs/evidence/founder-acceptance.md tests/acceptance/test_release_permissions.py tests/architecture/test_siteground_isolation.py
git commit -m "release: approve founder platform production rollout"
```

## Phase 3 completion gate

Phase 3 is complete only when all automated and installed-Chrome acceptance gates pass, the clean restore meets RPO/RTO, rollback is rehearsed, every founder completes the real workflow, no customer/CoALA/SiteGround boundary is crossed, and all three founders record production approval.
