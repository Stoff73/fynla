# Task Plan — seeded by session-end fallback (2026-05-19)

> Created by session-end because no vault is present on this machine. Authoritative plan:
> `docs/superpowers/plans/2026-05-19-sub-project-3-mobile-iframe-scaffold.md`. Authoritative
> handover: `May/May19Updates/handover-2026-05-19-session-1-clear.md`.
> Run `/planning-with-files:plan` to formalise if continuing in plugin mode.

## Current phase
SP3 "Mobile-first iframe scaffold" — execution via subagent-driven-development. Tasks 1–8 + 8b implemented & committed on branch `iFrames` (HEAD `5d293659`, pushed). Task 8b reviews + Task 9 outstanding.

## Phases
- [x] Brainstorm → spec → implementation plan (committed)
- [x] Task 1 — isolated mobile Vite build pipeline
- [x] Task 2 — `/m` host + `/m/app` routes & Blades
- [x] Task 3 — scoped SAMEORIGIN frame headers
- [x] Task 4 — phone-UA redirect middleware
- [x] Task 5 — Login/Verify/Dashboard scaffold screens
- [x] Task 6 — Capacitor repoint
- [x] Task 7 — two-env deploy wiring
- [x] Task 8 — legacy `resources/js/mobile/` retirement (CSJ-approved scope expansion)
- [~] Task 8b — residual `/m/*` nav cleanup: IMPLEMENTED (`5d293659`), spec + code-quality reviews NOT YET DONE (interrupted)
- [ ] Task 9 — Playwright E2E + `resources/mobile/README.md` + spec §5.3 cookie→Bearer fix + PR `iFrames`→`dev`

## Decisions log
- SP3 = scaffolding only; redesigned mobile UI is future work; scaffold screens disposable.
- Architecture: same-origin iframe, same repo, separate Vite build `resources/mobile/`→`public/m-build/`.
- Desktop/tablet unchanged; phones (web+native) → `/m`; native iOS not a live concern (scaffold acceptable).
- Task 8 scope expanded with CSJ approval: relocate `OfflineBanner` to `components/Common/`; clean dead `app.js`/`auth.js` refs.
- Task 8b cleanup of 4 inert native-guarded `/m/*` nav refs chosen by CSJ over deferring.
