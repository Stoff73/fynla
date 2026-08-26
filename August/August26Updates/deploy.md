# Deploy notes — 26 August 2026

**Target: `csjones.co/fynla` (dev/staging) only.** Everything below merged to `dev`
(head `c9ba0ba00`). Nothing here is on `main`, so **fynla.org is unaffected** — production
takes it through the periodic `dev → main` release PR, which only `@Stoff73` opens.

12 work items across PRs #718, #719, #720, #727, #728, #729.

---

## 1. Pull the code

csjones is a git checkout tracking `origin/dev`, so the PHP half is a pull, not an upload.

```bash
ssh -p 18765 u163-ptanegf9edny@ssh.csjones.co
```

```bash
cd ~/www/csjones.co/fynla-app && git pull origin dev
```

## 2. Migration — REQUIRED, this release adds a column

```bash
cd ~/www/csjones.co/fynla-app && php artisan migrate --force
```

`database/migrations/2026_08_26_100000_add_joint_owner_name_to_the_four_tables_that_lack_it.php`
adds a nullable `joint_owner_name` to `savings_accounts`, `investment_accounts`,
`business_interests` and `liabilities`. Idempotent — it skips a table that already has
the column — and reversible.

**Skip it and the app breaks rather than degrades:** `SavingsAccountResource` and
`InvestmentAccountResource` now publish `joint_owner_name` unconditionally, so every
savings and investment read 500s against an unmigrated schema.

## 3. Reseed — REQUIRED, and not just hygiene this time

```bash
cd ~/www/csjones.co/fynla-app && php artisan db:seed --force
```

W-0043's fix is **in `ChrisUserSeeder`**, not in a data patch. The orphaned buy-to-let
mortgage — marked joint at 50% with no `joint_owner_id` and no `joint_owner_name`, so
half a real liability was attributed to nobody — is *seeded*. It regenerated on every
reseed, which is why the earlier one-off sweep could never settle it. Reseeding is what
repairs the existing row in place. **Skip it and staging keeps the orphan.**

Never `migrate:fresh` or `migrate:refresh` here.

## 4. Frontend build — REQUIRED

Three Vue components changed, so the built bundle is stale until rebuilt. Build
**locally** (the server lacks the npm memory) with the **csjones** script — never the
fynla-org one, the two set different `VITE_BASE_PATH`/`RewriteBase` and the wrong
combination breaks routing silently:

```bash
./deploy/csjones-fynla/build.sh
```

Then upload `public/build/` to `~/www/csjones.co/fynla-app/public/build/`.

Changed:
- `resources/js/components/Goals/GoalFormModal.vue` — essential + ownership fields (W-0038)
- `resources/js/components/Savings/SaveAccountModal.vue` — off-platform co-owner (W-0042)
- `resources/js/components/Investment/StandardInvestmentFields.vue` — same (W-0042)
  — **see the caveat below before testing this one**

## 5. Cache clears

```bash
cd ~/www/csjones.co/fynla-app && php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear
```

**Do NOT run `php artisan optimize` or `route:cache` on this app.** The compiled matcher
lets the SPA catch-all shadow `/`, and the `/m` iframe loads `/`. Re-cache config only if
you need to: `php artisan config:cache`.

---

## Backend files in this release

**Estate**
- `Estate/EstateActionDefinitionService.php` — IHT recommendation reads the real engine (W-0501)
- `Estate/IHTCalculationService.php` — spouse age frame (W-0374), configured amendment date (W-0372)
- `Estate/FailedGiftTaxCalculator.php` — same-day transfers share one band (W-0468)
- `Estate/EstateAssetAggregatorService.php` — docblock only (W-0375)

**Stores / ownership**
- `Stores/PropertyStore.php` — eager-load `mortgages` on two reads (W-0502)
- `Stores/SavingsStore.php`, `Stores/InvestmentAccountStore.php`, `Stores/LiabilityStore.php` — validate `joint_owner_name` (W-0042)
- `Stores/Normalisers/SavingsAccountNormaliser.php`, `Stores/Normalisers/InvestmentAccountNormaliser.php` (W-0042)
- `Traits/CalculatesOwnershipShare.php` — `atUserShare` refuses a mortgage (W-0425)

**Models / requests / resources** — `joint_owner_name` fillable, validated and published
across `SavingsAccount`, `InvestmentAccount`, `BusinessInterest`, `Estate/Liability`,
their store/update requests, and both resources. `Models/Goal.php` docblock (W-0038).

**AI** — `Agents/CoordinatingAgent.php`: `create_goal` carries `is_essential` and
ownership; four `create_*` tools accept `joint_owner_name`. Golden-master tool-schema
fixtures regenerated to match (additions only, 24/0 and 45/0).

---

## Behaviour worth watching after deploy

- **The Inheritance Tax recommendation will change figures for some users** (W-0501). It
  used to sum full asset values with no ownership share and scope on `user_id`, so a user
  whose exposure sat in an asset they held as `joint_owner_id` was told **nothing**. It now
  reads `IHTCalculationService`, so numbers move in both directions and warnings appear for
  users who previously saw none. That is the fix, not a regression.
- **`/estate` should stop 500ing on staging** for anyone who is the `joint_owner_id` of a
  property owned by someone else (W-0502).
- **Same-day gifts now cumulate.** Two £300,000 gifts on one date against a £325,000 band
  previously produced nil tax; £275,000 is chargeable.

## Two things NOT closed by this deploy

1. **W-0502 has no regression guard.** Three fixtures all passed against the *unfixed*
   code, so the shipped test exercises the path but would not have caught the defect.
   Acceptance 2 is open and the item says so.
2. **W-0042 on the investment form is suspect.** `StandardInvestmentFields.vue` binds
   `v-model="jointOwnerSelection"` but the component has **no `data()` block**, so the
   property is undeclared — and there is no rehydration on edit, which its savings
   counterpart has. Entering an off-platform co-owner name may not work on that form at
   all. **Test the savings and investment paths separately; do not assume one proves the
   other.** Full detail in `tech-debt-report.md`, third pass, finding 3.

---
*Vault copy not written — `/Users/CSJ/Desktop/fynlaBrain/` does not exist on this machine.*
