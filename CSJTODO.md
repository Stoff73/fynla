# CSJTODO — Fynla

*Last updated: 18 April 2026 — session 62*
*Previous session: 17 April 2026 — sessions 60-61*

---

## Session 62 (18 April) — Admin Insights CMS rollout + production recovery

### Completed This Session

- [x] **Admin dashboard redesign** — two sections (Users / AI), 6 clickable summary cards with deltas since previous admin login (`user_sessions` driven), right padding clears the Fyn chat panel. Icons removed per request.
- [x] **Category expansion** — 10 categories total. Added `ai`, `fintech`, `developer`, `financial-planning`, `international`; renamed `tax-changes` → `tax`. Migration widens-then-narrows the enum and rewrites legacy rows. Validation + factory + admin dropdowns + public hub filters + article page label map all updated.
- [x] **CMS rename** — AdminPanel sidebar "Insights" → "CMS"; admin article list heading "Content Management System".
- [x] **Summary UX** — stopped rendering as article footer; helper copy beneath the Summary field.
- [x] **Image block UX** — inline helper copy for upload formats/size/alt purpose/alignment semantics. Save-fail alert now surfaces field-level errors. `BlockValidator` rejects empty/whitespace `path` or `alt`.
- [x] **Public article layout** — hero image full-width with title + subtitle overlaid bottom-left (gradient for legibility, category chip top-right). Fallback header with category inline right of title when no hero. `flow-root` contains floated images; wrapping paragraph tops align with image top.
- [x] **Preview auth fix** — `InsightController::show` resolves admin via the Sanctum guard so `?preview=true` returns drafts. Public draft requests still 404.
- [x] **Featured endpoint** — no longer falls back to latest published. Returns `featured: null` when nothing flagged.
- [x] **Multi-author byline** — new nullable `authors` JSON column (Brett Isenberg, Azlan Raj, Chris Slater-Jones allow-list). Admin editor has checkbox group; public article renders "By X" / "By X and Y" / "By X, Y and Z" inline with the publish date.
- [x] **Rich-text toolbar** — new Tiptap-based `RichTextEditor.vue` shared across heading / paragraph / list / pull quote / callout / key takeaways. Bold, italic, underline, inline link + preset font-size and text-colour marks emitting Tailwind classes on a 7-value allow-list. Backend + DOMPurify scrub `<span>` attributes to the same allow-list.
- [x] **Block chrome stripped** — quote / callout / takeaways no longer render tinted backgrounds, left accents, or rounded card corners (icon tints kept).
- [x] **Self-healing seeder** — `ExistingInsightsMetadataSeeder` now copies `resources/js/assets/insights/*.jpg` into `storage/app/public/insights/bespoke/` on every run and sets `hero_image_*_path` on all 8 bespoke articles. Idempotent — existing files in target are not overwritten, so CMS-uploaded replacements survive.
- [x] **Production deploy** — PR #219 merged to `main` (admin override, branch deleted). 62 commits. Deployed: 85 PHP/Vue/config files via SSH, 5 migrations run, `storage:link` created, 8 bespoke hero images copied into storage, DB paths populated, `how-much-to-retire-uk` flagged featured, caches cleared. Verified end-to-end on fynla.org — landing, hub, bespoke article pages all render with images and no errors.
- [x] **Session 61 tooling audit committed** — `.claude/` agents + skills + CLAUDE.md edits carried over from yesterday landed as commit `062c7c7` on main.
- [x] **Deploy guide** — `April/April18Updates/deployCMS.md` written and iterated with accurate post-incident guidance (flag=true as default, self-healing seeder, storage:link requirement). Mirrored to vault.
- [x] **Vault sync** — `Apr18.md` (14 commits) created, `Apr2026 Commits.md` bumped, `Home.md` commit counts updated, `April Index.md` session 62 entry added.

### NOT Done — Outstanding

- [ ] **Test Fyn chat fixes on dev (csjones.co/fynla)** — deployed in session 58 but not browser-tested. Carried from session 58.
- [ ] **Re-enable branch protection on `dev`** — carried from session 57.
- [ ] **Add `Current State/Insights.md` to the vault** — mirrors the pattern of `Investment.md` / `EstatePlanning.md`. Should document block schema, image pipeline, feature-flag rollout history, and the seeder self-healing behaviour. Flagged during vault-sync; not auto-created.
- [ ] **`AutoRiskCalculatorTest` pre-existing failure** — `risk_level` enum truncation. Not Insights-related. Carried from April 16.

### Context for Next Session

On `main` branch, clean working tree. Production is running the full Admin Insights CMS as of `062c7c7`. 8 bespoke articles published with hero images rendering correctly; one flagged featured. `/admin/insights` CMS ready for authors to use — hero image replacement via the CMS editor survives reseeds (seeder doesn't overwrite existing files).

Next session start point: likely the "Insights current-state doc" write-up or another CMS-related follow-up (e.g. more authors added to the allow-list, additional bespoke hero image refinements, or replacing the `stocks-shares-isa-uk` placeholder).

---

## Outstanding — Tech Debt Deferred

- [ ] `AutoRiskCalculatorTest` enum truncation (pre-existing, not Insights-related).

## Known Issues

None discovered this session that aren't already resolved or documented above.

## Deploy Status

**Production (fynla.org):** Running commit `a14f17a` (PR #219 merged) + `062c7c7` (tooling audit). Full Admin Insights CMS live with 8 bespoke articles. `VITE_INSIGHTS_CMS_ENABLED=true`. All images serving from `/storage/insights/bespoke/*.jpg`.

**Dev (csjones.co/fynla):** Last deployed from `onboardingFyn` for Fyn chat testing — unchanged today. Not yet updated with the CMS merged to main; any future dev deploy should pick up main's state (ASK which branch the dev server is running before building per `feedback_dev_server_is_separate`).

**Pending deploy:** none. Everything committed to main is in production.
