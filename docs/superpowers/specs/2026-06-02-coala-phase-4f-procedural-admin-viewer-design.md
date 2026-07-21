# CoALA Phase 4f — Read-only Procedural Admin Viewer — Design Spec

Date: 2026-06-02
Branch: `feat/coala-4f-admin-viewer`
Risk: LOW (read-only UI; no behaviour move)
Master plan: `fynla-coala-implementation-plan.md` sec 781.

## 1. Scope and boundary

Build a **read-only admin surface to VIEW the procedural-memory corpus** (the
`.md` procedures loaded by Phase 4a's `ProceduralCorpus`). Admins can see, per
`kind`/`module`, every procedure and its versions, and drill into a single
procedure to read its frontmatter (as a header table) plus its body.

This phase adds:
- One admin-gated read controller exposing **two GET endpoints** over
  `ProceduralCorpus`.
- Two admin-gated lazy-loaded routes mapping to one Vue admin view.
- The Vue view (wrapped in `<AppLayout>`), with an empty state and an error
  state.
- A frontend API service wrapper.

### Hard boundary — what 4f MUST NOT touch

- **No writes.** GET endpoints only. No create/update/delete of procedures or
  any other record. No new AI/chat tools. The `ProceduralCorpusLoader` is
  consumed via `load()` (the never-throws runtime entry) — 4f never edits the
  corpus, never calls `loadStrict()` outside the existing deploy gate.
- **No change to the assembled tool catalogue or either Fyn write-state.**
  4f reads the corpus for display only; it does not feed the assembler, the
  prompt, or `AiToolDefinitions`. The Two-Fyn contract (AdviceFyn read-only,
  onboarding the only writer) is untouched — 4f exposes no tool to any model.
- **No prompt-prefix impact.** `FynSystemPrompt::text()` and
  `FynContextAssembler::build()` are not imported or referenced. The static
  prompt stays byte-identical because 4f does not touch it.
- **No new dependency.** Markdown body is rendered with the existing
  `<pre class="whitespace-pre-wrap">` approach already used by the Phase 2
  `EpisodicComplianceLog.vue` viewer — no `marked`/`markdown-it` is added.

### Out of scope / deferred

- Editing procedures (create/activate/deactivate/version-bump) — this is a
  PR-to-`.md` workflow by design; an editor would contradict the externalisation
  goal. Explicitly deferred, not built.
- Rendering markdown to rich HTML (headings, lists, code highlighting). The
  body is shown verbatim in a monospace `<pre>` block; that is sufficient for an
  admin forensic view and avoids a sanitiser/dependency. Deferred.
- Diffing two versions of a procedure side-by-side. Deferred.
- Surfacing the `pointers/` sibling subsystem — out of scope (4a explicitly
  ignores it; so does this viewer).
- Effective-dating "as of" date picker — the viewer shows the version's own
  `effective_from`/`effective_to` and `active` flag; it does not let the admin
  resolve "what was active on date X". Deferred.
- A nav-menu/sidebar link to the new page — route is reachable by URL and via
  the breadcrumb pattern; adding a menu entry is a separate UX decision.
  Deferred (matches how `EpisodicComplianceLog` shipped without a menu item).

## 2. Components / files

### Backend (new)
- `app/Http/Controllers/Api/Admin/ProceduralCorpusController.php`
  - `index(ProceduralCorpus $corpus)` — list all procedures grouped by
    `kind` → `module`, each with frontmatter-only summary (no body).
  - `show(Request $request, ProceduralCorpus $corpus)` — one procedure's
    every version (frontmatter + body), keyed by the `procedure_id` query/route
    param.
  - Injects `ProceduralCorpus` via the container (it is built by the
    `ProceduralCorpusLoader` singleton's `load()`; see §6 binding note).

### Backend (edited)
- `routes/api.php` — add two routes inside the existing
  `Route::middleware(['auth:sanctum', 'permission:admin.access'])->prefix('admin')`
  group (the same group that hosts `ai-audit/episodes`):
  - `GET admin/procedural-corpus` → `index`
  - `GET admin/procedural-corpus/{procedureId}` → `show`
    (`{procedureId}` constrained to allow dots, since IDs look like
    `retirement.tool.create_dc_pension`).

### Frontend (new)
- `resources/js/services/proceduralCorpusService.js` — pure API wrapper:
  `getCorpus()`, `getProcedure(procedureId)`.
- `resources/js/views/Admin/ProceduralCorpusViewer.vue` — admin view, wrapped
  in `<AppLayout>`. Renders the grouped list; expanding a procedure fetches its
  versions and shows a frontmatter header table + `<pre>` body per version.

### Frontend (edited)
- `resources/js/router/index.js` — add the lazy import + one route
  `/admin/procedural-corpus` (`meta: { requiresAuth: true, requiresAdmin: true,
  breadcrumb: [...] }`), mirroring the `EpisodicComplianceLog` registration at
  index.js:1210–1223. The dotted `{procedureId}` is a query param on the detail
  fetch, so a single SPA route suffices (no second SPA route needed).

### Tests (new)
- `tests/Feature/AI/ProceduralCorpusAdminEndpointTest.php` — admin auth
  required; non-admin → 403; unauthenticated → 401; temp-dir corpus fixture
  returns the seeded procedures grouped by kind/module; empty corpus → empty
  groups (200, no error); detail returns frontmatter + body for all versions of
  one id.
- `tests/Unit/Frontend/ProceduralCorpusViewerExistsTest.php` (or extend an
  existing layout-assertion test) — assert the Vue view file exists and its
  template wraps `<AppLayout>` (string assertion on file contents, matching how
  other "view wraps AppLayout" checks are done in this repo). If a Vue test
  harness convention already exists for this, mirror it instead.

## 3. Data flow

```
Vue ProceduralCorpusViewer.vue
  → proceduralCorpusService.getCorpus()
    → GET /api/admin/procedural-corpus      (auth:sanctum + permission:admin.access)
      → ProceduralCorpusController@index
        → ProceduralCorpus (built from ProceduralCorpusLoader::load())
        → group ProceduralCorpus::all() by kind → module
        → JSON: { success, data: { groups: [...] } }   // frontmatter only, no body

  on expand a procedure:
  → proceduralCorpusService.getProcedure(procedureId)
    → GET /api/admin/procedural-corpus/{procedureId}
      → ProceduralCorpusController@show
        → ProceduralCorpus::versions(procedureId)        // all versions asc
        → JSON: { success, data: { procedure_id, versions: [ {frontmatter…, body} ] } }
```

The `index` payload shape (frontmatter-only summary per procedure):
```
groups: [
  { kind, modules: [
    { module, procedures: [
      { procedure_id, active_version, version_count,
        versions: [ { version, active, effective_from, effective_to } ] }
    ]}
  ]}
]
```
`detail` payload shape:
```
{ procedure_id,
  versions: [ { kind, module, version, active, effective_from, effective_to, body } ] }
```
Dates are serialised as ISO date strings (`Carbon->toDateString()`), British
date display happens client-side via the existing `dateFormatter` util.

## 4. Error handling — degrade, never break

- The controller uses `ProceduralCorpus` obtained from
  `ProceduralCorpusLoader::load()`, which **never throws** and degrades to the
  last-good/empty corpus. A malformed or missing corpus therefore yields an
  **empty grouped list** with a 200 — the viewer shows the empty state, not an
  error. This is the central degrade guarantee and is inherited from 4a, not
  re-implemented.
- `show` for an unknown `procedure_id` returns `{ success: true, data: {
  procedure_id, versions: [] } }` (200) — the detail panel shows "No versions
  found for this procedure." rather than a 404, so a stale UI link cannot break.
- Frontend: list-fetch failure sets a single inline error message
  ("Could not load the procedural corpus. Please try again.") and renders no
  table; detail-fetch failure sets a per-row detail error and leaves the rest of
  the page intact — mirroring `EpisodicComplianceLog.vue`'s two-tier error
  model.
- Auth: handled entirely by the existing
  `permission:admin.access` middleware (403 for non-admin, 401 for
  unauthenticated). No bespoke auth code in the controller.

## 5. Golden-master strategy (zero-regression proof)

4f **moves no existing behaviour** — it is purely additive read-only surface.
There is no PHP constant being externalised here and no prompt/tool output being
re-shaped. Therefore the byte-identity golden-master that guards 4b–4e does
**not** apply in the "captured fixture vs post-refactor output" form.

The zero-regression guarantees that DO bind 4f, and how each is proven:

1. **Tool catalogue / prompt unchanged.** Proven structurally: 4f imports
   neither `AiToolDefinitions`, `FynSystemPrompt`, nor `FynContextAssembler`,
   and registers no tool. Verified by grep over the new files in review + \
   the full existing prompt/tool/assembler golden-master tests staying green
   (`./vendor/bin/pest tests/Feature/AI tests/Unit/Services/AI` — must remain
   green, demonstrating 4f did not perturb them).
2. **Loader runtime contract unchanged.** 4f only calls `load()`; the existing
   `ProceduralCorpusLoaderTest` (never-throws, degrade, cache, hot-reload) must
   stay green.
3. **Endpoint contract pinned.** The new feature test asserts the exact JSON
   structure (`assertJsonStructure`) and that a known temp-dir fixture maps to a
   known grouped response — this is the closest analogue to a golden-master for
   the new surface and is the acceptance pin for the controller.

"Done" is not declared until (1)–(3) are green.

## 6. Container binding note

`ProceduralCorpus` is produced by `ProceduralCorpusLoader::load()`. The
controller type-hints `ProceduralCorpus`; resolution comes from the loader
singleton. The controller method signature resolves the corpus via
`app(ProceduralCorpusLoader::class)->load()` (constructor- or method-injected
loader), **not** by binding `ProceduralCorpus` itself in the container — this
keeps the 60s mtime hot-reload + last-good cache semantics intact and avoids a
stale frozen instance. The controller holds the loader, calls `load()` per
request.

## 7. Validation

- `php artisan route:list --path=admin/procedural-corpus` shows both routes
  behind `auth:sanctum` + `permission:admin.access`.
- Feature test green (admin 200, non-admin 403, unauthenticated 401, empty
  corpus clean 200, fixture grouped correctly, detail returns body).
- Vue view exists and wraps `<AppLayout>` (Rule #14) — asserted in test.
- Manual grep confirms **no** icon/emoji/Unicode-as-icon/icon-font/`::before`
  glyph in the new Vue file (Rule #16) and **no** numeric score in user-facing
  copy (Rule #13).
- `./vendor/bin/pint` clean on every new/edited PHP file.
- British spelling in all user-facing copy.

## 8. Testing

- `./vendor/bin/pest tests/Feature/AI/ProceduralCorpusAdminEndpointTest.php`
- `./vendor/bin/pest tests/Unit/Services/AI/Memory/Procedural` (regression —
  loader/corpus untouched, must stay green)
- `./vendor/bin/pest tests/Feature/AI tests/Unit/Services/AI` (prompt/tool/
  assembler golden-masters stay green → proves §5.1)
- Temp-dir corpus fixtures created in the feature test exactly as the loader
  tests do (`config(['fyn.memory.procedural_path' => sys_get_temp_dir()…])`,
  write `{kind}/{module}/*.md`, `File::deleteDirectory` in `afterEach`).

## 9. Done-when

- [ ] Branch `feat/coala-4f-admin-viewer` carries spec + plan + code commits.
- [ ] `GET admin/procedural-corpus` + `GET admin/procedural-corpus/{procedureId}`
      live, admin-gated, read-only.
- [ ] `ProceduralCorpusViewer.vue` exists, wraps `<AppLayout>`, no icons, no
      scores, British spelling, renders empty state cleanly.
- [ ] Route registered (lazy, `requiresAdmin`).
- [ ] Feature test + view-exists test green.
- [ ] Existing procedural + AI prompt/tool/assembler suites stay green.
- [ ] `./vendor/bin/pint` reports passed on all touched PHP files.
