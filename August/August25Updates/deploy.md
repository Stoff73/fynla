# Deploy notes — 2026-08-25

**Nothing was deployed this session.** No upload to fynla.org, no `git pull` on csjones.
This records what a deploy of today's work would require.

## Where the work is

| Branch | State |
|---|---|
| `dev` | PR #716 and #717 merged. `88e9d08ce`, `3de6395ef`. |
| `feature/icecube/w0368-undivided-share-discount` | 2 commits, pushed, **no PR** — awaiting a tax-compliance re-gate |

**`dev` deploys to csjones, not to production.** Production is `main`, which has not
moved. Do not use the fynla.org build script for any of this
(`VITE_BASE_PATH` differs — a dev build on prod is a blank page).

## Migrations — four new, all on `dev`

Run in order on any environment taking this work:

```bash
php artisan migrate --force
```

| Migration | What it does | Reversible? |
|---|---|---|
| `2026_08_25_100000_say_on_the_column_which_mortgage_ownership_types_are_writable` | Column COMMENT only on `mortgages.ownership_type` | Yes — `down()` clears the comment |
| `2026_08_25_140000_drop_the_charitable_bequest_column_nothing_reads` | **Drops `users.charitable_bequest`** | Shape only — contents are gone. Nothing read them |
| `2026_08_25_150000_let_mortgages_record_capped_and_offset_rate_types` | Widens `mortgages.rate_type` enum | Yes, but `down()` fails if a row holds `capped`/`offset` — correct, it must not rewrite a user's stated product |
| `2026_08_25_190000_record_whether_a_property_co_owner_is_the_spouse` | Adds nullable `properties.joint_owner_is_spouse` | Yes |

**Only the third is on `dev`-merged work plus the first two; the fourth is on the
unmerged W-0368 branch.** Check which branch you are deploying.

## Seeder

`TaxConfigurationSeeder` changed — it gains
`inheritance_tax.undivided_share_discount_percent`. **On the W-0368 branch only.**

```bash
php artisan db:seed --class=TaxConfigurationSeeder --force
```

## Frontend

`resources/js/` changed (investment projection chart, income definitions panel, property
form) and `resources/mobile/` changed (investment risk card). A deploy needs **both**
bundles:

```bash
npm run build:mobile     # /m  -> public/m-build/
./deploy/csjones-fynla/build.sh   # dev environment build
```

## Warnings

- **Every user's investment projection drops** (W-0008) — projections are now net of
  platform, adviser and fund fees. This is a visible change to a financial figure across
  the product. It was gated and cleared, but users will notice.
- **`dev` currently carries two known live tax defects**, both raised today and neither
  fixed: **W-0485** (Blind Person's Allowance wrongly reduces adjusted net income) and
  **W-0204** (salary sacrifice not added back to threshold income). Both are `high`.
- `browser-smoke` and `InsightsTest` are red on `dev` for reasons independent of this
  work — see the handover.
