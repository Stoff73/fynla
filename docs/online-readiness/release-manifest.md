# Online Readiness Release Manifest

Captured on 2026-07-11 from the locally available remote-tracking refs. This is the Gate 0 baseline; deployment state is historical evidence from the restored July handovers, not a fresh server probe.

## Branch anchors

- Production main — `git rev-parse origin/main`

  ```text
  2e8357bef1c453da40e2c1991a462d8914b262e5
  ```

- Staging dev — `git rev-parse origin/dev`

  ```text
  e16ea5f89ed091fbce5371da2d253e9363e0ce2b
  ```

- Merge base — `git merge-base origin/main origin/dev`

  ```text
  80aa2aaf570d0c837bed01477f28d51721ea0f9f
  ```

## Release difference

- Commit counts — `git rev-list --left-right --count origin/main...origin/dev` (`main-only dev-only`)

  ```text
  28  163
  ```

- Changed-file count — `git diff --name-only origin/main...origin/dev | wc -l`

  ```text
  367
  ```

- Shortstat — `git diff --shortstat origin/main...origin/dev`

  ```text
   367 files changed, 19348 insertions(+), 8754 deletions(-)
  ```

### Main-only documentation commits that must survive merge

The relevant July planning and evidence commits reported by `git log --format='%H %s' origin/main --not origin/dev` are:

```text
2e8357bef1c453da40e2c1991a462d8914b262e5 docs(fyn): AI remediation spec + plan for Opus (CSJ rulings D1/D3/D4/D9 + gpt-5-nano requirement)
e7041e1cf4991b443c7f81eb73bad74bf8df840d docs(fyn): six-agent AI blindspot map + prompting playbook (2026-07-07)
5899e667b6e95dc44e1eab483db7bd788b5afb3c docs(todo): #613/#614/#615 merged + csjones deploy verified; new Fyn repetition bug logged
2f73542a55ed2df16750946b4f23ca625ccc7344 docs(session): context-clear handover 2026-07-07-session-1 + full-app audit report
56bd2c0129deff03f3ae714c052183155a8778d0 docs(session): context-clear handover 2026-07-06-session-1 (audit fixes #612 live-verified + campaign specs/plans)
4ba6ba190ccef923c67ad045bbb5123d2ba76164 docs(campaigns): add the testing process & gates ladder to both campaign plans
61630d84ad835ca87eeaafe8aa51f12e45d18200 docs(campaigns): investment + estate campaign specs and implementation plans
db478be35f21fdcdcdd95276a47b1c8f6dd1c0ed docs(campaigns): audit flag statuses — PR #612 merged + live-verified on csjones
66555ff6e47c77cfe64b5404ea41f72320e7cf5b docs(campaigns): savetax + pensioncheck full maps & audits; flag statuses after PR #612 fixes
143fa8ee18cd5b220cb49db4b939d2d65d80c0e0 docs(session): eod handover 2026-07-05-session-1 (pension campaign #607-#610 shipped to dev + E2E green)
79b8c57f2e70da31ea19442d2925781fbe6c61a2 docs(session): context-clear handover 2026-07-03-session-2 (WP-5c shipped + campaign playbook)
59131174450886e44a44e490b1647ccdf5c06b34 docs(session): eod handover 2026-07-01-session-1 (#592 + #593 shipped to dev, live-verified)
```

### July source-register commit/blob manifest

- Canonical source ref: `origin/main`
- Canonical source commit: `2e8357bef1c453da40e2c1991a462d8914b262e5`
- Registered corpus: 34 artifacts, each with its `origin/main` blob identifier in `docs/online-readiness/july-plan-register.yaml`.
- Intentional imported-source difference: `July/July7Updates/fyn-ai-remediation-plan.md` corrects the QuerySchemas source path and records that correction in its document history. Its `source_blob` remains the original `origin/main` blob, so provenance is preserved.
- Register provenance: created at Gate 0 from the canonical `origin/main` source commit and the per-artifact blob identifiers recorded in the register.

## Migrations

`git diff --name-only origin/main...origin/dev -- database/migrations`:

```text
database/migrations/2026_07_03_000001_add_active_campaign_to_users.php
```

The release process must compare the deployed migration table with this path and every later migration added before candidate freeze. No destructive migration command is permitted.

## Runtime surfaces

The release candidate is not a PHP-only upload. The following surfaces must be built, deployed and verified together against one immutable SHA:

- PHP/source: 102 changed paths under `app/`, `config/` and `routes/` at this baseline.
- Desktop bundle: 53 changed paths under `resources/js/`; build only with the environment-specific deployment script.
- `/m` bundle: 20 changed paths under `resources/mobile/`; verify the built manifest and served chunk, not source alone.
- Public PHP pages: 13 changed paths under `public/pages/`; verify direct public routes, redirects, registration handoff and cache-busters.
- Fyn corpus and prompt snapshots: 46 changed paths under `fyn-memory/` and `tests/fixtures/Fyn/`; regenerate and verify required golden masters whenever prompt or corpus bytes change.
- Scheduler and queue: the three-dot branch diff currently reports no changed path under `app/Console/` or `app/Jobs/`, while Tasks 11–12 and 21 require material scheduler/queue changes before release. Recompute this inventory at candidate freeze and verify worker, failed-job monitoring, scheduled-command hooks and heartbeat live.

## Deployment state

- csjones: `e16ea5f89ed091fbce5371da2d253e9363e0ce2b`; last recorded live verification 2026-07-07. Source: the `5899e66` CSJTODO update, which states PRs #613, #614 and #615 were merged, deployed and browser-verified. This is historical and must be replaced by a fresh immutable-candidate deployment record at Gate 4.
- fynla.org: last recorded code release merge `cc8d6774bb46ea8efce75f8e195a12ab7866a609` (PR #573); last recorded live verification 2026-06-25. Later July handovers repeatedly state production was untouched. The current `origin/main` tip contains main-only documentation commits and must not be mistaken for a freshly deployed production SHA without a server-side check.
