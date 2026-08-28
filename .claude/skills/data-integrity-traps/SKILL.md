---
name: data-integrity-traps
description: The ways a Fynla value is correct at every layer and still reaches the user wrong — validation rules disagreeing with their column, a NOT NULL DEFAULT turning "never asked" into "chose this", a figure in the wrong money basis, and values that never complete the journey from service to screen. Use when writing or reviewing Form Requests, app/Services/Stores rules, migrations and column definitions, API Resources, view-model mappings, or any projection loop that inflates figures over time. Also use when a number displays wrongly but every layer looks right, or when a v-if never renders.
---

# Data integrity traps

Hard-won findings from the cycle-4 validation sweep (2026-08-22/23) and the estate cash-flow work. Each cost real time. Check them before writing a guard, because **a guard written to the wrong principle manufactures defects and then defends them with the authority of a green suite.**

## 1. A rule and its column must agree — but the directions are NOT symmetric

| Direction | Verdict |
|---|---|
| **Rule wider than the column** | **Always a defect.** The value passes validation and dies at the write: `SQLSTATE[22003] Out of range`, or `1048 cannot be null`. Nothing legitimises it. |
| **Column wider than the rule** | **Depends entirely on whether anything offers the excluded value.** Refusing what no path can produce is a *decision*. Refusing what the form puts in front of the user is a *defect*. |

Both appeared in **one line** of `MortgageStore`: `capped`/`offset` accepted but unstorable (direction 1), and `mixed` refused while the form offers it and three request classes allow it (direction 2). In the **same file**, `ownership_type` refusing `tenants_in_common` is **correct** — `MortgageNormaliser` coerces it to `joint` and documents that mortgages do not support it.

**So a test asserting rule and column MATCH would have enforced a regression.** Guards here carry an **exception list**, and **every exception must name the mechanism that guarantees the excluded value never arrives** — otherwise the list stops recording decisions and becomes a place to hide drift.

## 2. The seven axes, each blind to the others

1. `nullable` rule on a **NOT NULL** column — 192 occurrences.
2. Field **fillable and offered by a form but absent from `rules()`** — silently stripped by `validated()`; 95 occurrences.
3. **Rule range exceeds column precision** — e.g. `max:100` on `decimal(5,4)`, which stops at 9.9999.
4. **No `max:` rule at all**, leaving the column as the only guard.
5. The same column written under a **different, prefixed name** in another request — invisible to a name-matching sweep.
6. **`app/Services/Stores/` validate separately from `app/Http/Requests/`.**
7. **The Resource omits a field the template gates on** — the same disease at the **read** boundary rather than the write.

**Axis 6 is the one that hides.** `resources/mobile/api.js` has no post/put/patch helper anywhere, so **Fyn is not one of `/m`'s write paths — it is the only one**, and it writes through the Stores. The backend looks shared, and it is *at the endpoint*; it diverges one layer down where the Stores carry their own rules. **Sweeping `app/Http/Requests/` says nothing about how `/m` writes.**

Axis 6 is swept for **enum lists only** (`StoreEnumRulesMatchColumnsTest`, 17 rules). **Store numeric bounds have never been swept** and are already known to diverge: `MortgageStore:306` bounds `interest_rate` but says nothing about `fixed_interest_rate` or `variable_interest_rate`; `InvestmentAccountStore` sets no bound on `platform_fee_percent`, **so Fyn accepts a 12% platform fee the web form rejects with a 422.** Open as **W-0329**.

Existing guards: `tests/Unit/Database/ValidationMaxFitsColumnPrecisionTest.php`, `StoreEnumRulesMatchColumnsTest.php`.

## 3. A value can be correct at every layer and never arrive

Axes 1-6 ask *"can what the user typed reach the column?"* **Axis 7 asks the mirror: "can what the column holds reach the user?"** Three instances in one night, and the third was found inside the fix for the first two.

**Instance A — the Resource drops a sibling the `v-if` names.** `MortgageResource` serialises `fixed_interest_rate` but **not** `fixed_rate_percentage`. `PropertyDetailInline.vue:319` renders the fixed portion only `v-if="mortgage.rate_type === 'mixed' && mortgage.fixed_rate_percentage"`. The gate reads `undefined`, so **the row is structurally unreachable: no data can satisfy it.** The user enters a 60% portion at 12%, it saves correctly, and the detail view shows `Rate Type: Mixed` and no numbers.

**The trap is sibling coupling.** `fixed_interest_rate` *is* serialised, so anyone asking *"is the rate exposed?"* answers **yes** and stops. **When checking whether a value reaches the user, check every field its `v-if` names — not the value itself.** Open as **W-0351**.

**Instance B — a value computed and read by nothing.** `IHTCalculationService` computed `charitable_rate_test_amount`, applying a statutory distinction a tax reviewer had to rule on, and never put it in the result array. Zero consumers across `app`, `resources` and `tests`. The card had one charitable figure to render and two to explain, and the survivor went out under *"Your will leaves £20,000 to charity"* — false for both spouses, who each leave £10,000.

**The check:** `grep` for a key a service sets and **count its consumers. Zero means either dead code or a distinction that never reaches the user — and the two look identical from inside the service.**

**Instance C — the boundary is not always a Resource.** `IHTPlanning.vue` built its view-model with a **hand-written mapping that enumerates fields rather than spreading them**, dropping a newly published field one layer below the controller and one above the template. **An allowlist nobody thought of as a boundary.**

**The tell:** a key present in the view-model that does **not** exist in the API response means a mapping is being hand-built somewhere between them.

**Use `?? null`, not `|| 0`, when the consumer distinguishes "nothing to show" from "zero".** A zero-default collapses the two and the card cannot tell them apart.

**Testing the ends does not test the join.** When you publish a value, follow it to the template.

## 4. Check reachability before filing

Four of nine instances were in components mounted by nothing. **A sweep that greps `resources/` and files everything over-reports by a third.**

## 5. No guard moves a configured rate and asserts on a Vue template

The Rule 2 (no hardcoded tax values) charitable family was swept **twice and declared closed twice**. Both sweeps covered `app/` and exactly **one** Vue file — and that one only because it happened to be open for another reason. `RateLiteralsComeFromConfigurationTest` and `CharitableExemptionVersusRateTestTest` both drive PHP services and assert on **service output**, so **the entire frontend sits outside the family.** Nine instances across seven files survived two "complete" sweeps.

**The sharpest instance was authored by one of those sweeps.** `IHTPlanning.vue:246` — *"The 10% test that decides the reduced rate…"* — was written to explain the statutory distinction the tax reviewer had just ruled on, **and hardcoded the threshold in the same breath.**

Two further shapes it exposed:
- **A rate in arithmetic in the frontend** (`futureTaxableEstate * 0.40`) computing a *displayed liability* — the class a prose sweep is blind to.
- **A `v-if` gating on a key no payload carries.** One degree worse than a Resource dropping a field: there it existed and was not sent; here it never existed at all. `grep` finds the key in one template and nowhere else.

## 6. A `NOT NULL DEFAULT` makes "never asked" indistinguishable from "chose this"

`users.expenditure_sharing_mode` is `enum('joint','separate') NOT NULL DEFAULT 'joint'`. **A married user who has never opened the form, never seen the toggle and never formed a view reads identically to one who deliberately chose Joint.** Live shape on dev when found: 19 users, all `joint`, zero `separate`, 12 with a spouse — **nobody has ever chosen. Every value is the default.**

The column had **already turned an unanswered question into an answer before any feature read it.** Any code treating that value as a declaration inherits the fabrication, and a rule like *"if no preference is recorded, ask"* has nothing to detect, because the unanswered state is not expressible.

Same defect as a **tri-state column behind a two-state control** (`NULL` / `true` / `false` rendered by a falsy check, so *"we have not asked you"* and *"you told us no"* look identical).

**When adding a column that records a user's choice, ask what "not yet asked" looks like.** If the answer is "the same as one of the choices", either make it nullable or add a `..._declared_at` companion. **A default is a convenience for the schema, not a statement by the user.**

**Where a default must stand, the consequence is disclosure, not arithmetic:** a surface acting on a defaulted value should say what it assumed, the way a form does by showing the setting beside the input. A chat turn or an API call has no such affordance and cannot rely on it silently.

## 7. One money basis — and know which one every figure is already in

A projection loop carries figures in one basis: today's money, inflated once per year. **Figures arriving from another module are not necessarily in the same money, and nothing in a `float` says which.**

The worked case: `PensionProjector::projectTotalRetirementIncome()` returns private pension income **nominal at retirement** (a pot grown at its own rate) and the State Pension forecast in **today's money** — *both in the same returned array*. Feeding the first into a loop that inflates annually inflates it a second time for the whole of retirement.

**Convert at the boundary, once, in the open.** Deflate what is nominal; leave what is already real. Say so at the call site, because **a double-inflated figure never looks wrong** — it is smooth, plausible, directionally sensible, and it compounds.

**Treat a division that looks unnecessary as suspicious before deleting it.** A lone `pow(1 + inflation, years)` with no obvious purpose is exactly what a later "simplification" removes.

**Related:** the same module carried three phantom column names swallowed by `?? 0`, and a hardcoded rate beside a configured one that was never read. **A float carries no units, no provenance and no absence** — whatever you rely on, state it where it is consumed.
