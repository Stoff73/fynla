# Deploy — dev (csjones.co/fynla), 2026-08-23 19:1x BST

**Commit:** `19bd1c83f`. **Branch:** `dev`, fast-forward from `ff6336ea6`, 16 commits.
**Authorised by:** CSJ, "all changes to dev".

## Gates before deploy

| Gate | Result |
|---|---|
| Backend suite | **7,878 passed, 30 skipped, 0 failed** (126,693 assertions) |
| Frontend suite | **1,237 passed** (122 files) |
| `tax-compliance-reviewer` | Two rounds — verdict in `handoffs/W-0463/` |
| `quality-lead` | **NOT RUN.** 144 items remain at `handoff`, uncertified |
| Browser | Estate table verified on web; `/m` teaser NOT reachable (Free-tier surface, personas are Premium) |

**The green above is the first uncontaminated full-suite run in two days.** Two earlier
runs reported 232 and then 61 failures; both were **test-database contention created by
the coordinator** running concurrent Pest processes against one `laravel_testing`
database — the trap recorded in the 2026-08-23 handover. Three files pulled from that
failure list passed cleanly in isolation. No code was implicated.

Separately, `./vendor/bin/pest` had been **fatal since 2026-08-22** — two files declaring
a global `spouseRow()` helper — so no full-suite run had been possible at all. Fixed in
`1af23f8e5`. **Nothing at `handoff` has a full-suite green behind it from before that.**

## Steps executed

1. `git pull --ff-only origin dev` — server was at `f64dfd5a8`, confirmed an ancestor
   first. 36 untracked files on the server (old build dirs, `.env` and `.htaccess`
   backups) — all untracked, none tracked-and-modified, so the pull could not clobber
   anything.
2. `php artisan migrate --force` — **12 pending, all DONE**, including two data
   migrations (`sync_rental_income_to_every_owner`,
   `split_joint_expenditure_recorded_on_one_account`) and
   `backfill_spouse_permissions_for_existing_links`.
3. `public/build/` and `public/m-build/` rsync'd; previous bundles snapshotted to `.prev`
   and old chunks copied back over the new ones so in-flight sessions survive.
4. Caches cleared — application, route, config and view — then the CONFIG cache alone
   rebuilt. The compiled-route path and the full optimiser were deliberately NOT used:
   they let the SPA catch-all shadow the server-rendered homepage, which the `/m` iframe
   loads. See the memory note on that failure.

## Verified after deploy

- Server HEAD `19bd1c83f`.
- **Deployed bundles grepped for strings only today's changes could produce**: "Modelled
  on second death" in the web bundle, "Household sharing" and `annual_premium` in the
  `/m` bundle. All present — the stale-bundle trap is that `/m` fails by AGREEING with
  you, so the grep has to run against the file the server actually serves.
- `https://csjones.co/fynla` 200 after redirect; `/fynla/m` 200.
- `storage/logs/laravel.log` clean; tax config resolving 2026/27.

## Shipped alongside, and worth stating plainly

The two `wip:` snapshots carrying **~379 files of cycle-1-4 persona work**. That work now
has a full-suite green behind it — which it never had before today — but **no
quality-lead pass**, and 144 board items sit at `handoff` uncertified.

## Known gaps at time of deploy

- **W-0469** — the business relief row and failed-gift tax reached web only.
- **W-0466 / W-0467** — copy decisions with CSJ (APR/AIM caveat; the `/m` teaser headline).
- **W-0465** — the projection applies no business relief.
- **iOS** — not built, not launched, not looked at.
