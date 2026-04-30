# Tech Debt Report — Session 119 (30 April 2026, evening)

**Files analysed:** 9 (7 modified + 2 new migrations)
**Issues found:** 5
**Severity breakdown:** 0 critical, 2 warnings, 3 suggestions

Phase 3 of the SaveTax strategy expansion (#4 salary_sacrifice_ni, #6 bed_and_isa, #12 non_earner_spouse_pension). Mostly additive — capture-tool extensions, two schema migrations, three calculator generators, 13 unit tests.

## Critical Issues

None.

## Warnings

### W-1 — Hardcoded non-earner spouse pension figures (£2,880 / £720)

- **File:** `app/Services/Tax/TaxStrategyCalculator.php:~1175-1206`
- **Category:** Convention Violations (tax hardcoding) + Duplicate Code
- **What's wrong:** `buildNonEarnerSpousePensionRecommendation` hardcodes `$netContribution = 2880.0` and `$governmentUplift = 720.0`. These are the same figures already hardcoded in `buildLifecycleRecommendations` for `junior_pension` at lines 644-645 (`$juniorPensionNet = 2880.0`, `$juniorPensionUplift = 720.0`). The CSJTODO already flags the junior-pension instance under tech-debt item **S-3**; this commit adds a second instance.
- **Suggested fix:** When S-3 is actioned, expose both as TaxConfigService keys: `pension_allowances.non_earner_relievable_net_cap` and `pension_allowances.non_earner_uplift`. Both call sites use the same constants. Defer until next opportunistic touch — out of scope for Phase 3.

### W-2 — CGT-rate band match adds a third instance of band → rate mapping

- **File:** `app/Services/Tax/TaxStrategyCalculator.php:~937-941`
- **Category:** Duplicate Code
- **What's wrong:** `buildBedAndIsaRecommendation` introduces a `match($userBand) → cgt_rate` block. The codebase already has the same shape three times: `bandRateFor()` at line 950 (income-tax marginal rate), `dividend_allowance_harvest` at line 838 (dividend rate), and now CGT here. CSJTODO **W-1** already calls out the dividend-rate duplication as needing a `dividendRateForBand(string $band): float` helper. Same pattern fits CGT — a `cgtRateForBand(string $band, bool $residential = false): float` helper would unify it.
- **Suggested fix:** When the dividend helper is extracted (CSJTODO W-1, ~15 min), extract the CGT helper alongside it for negligible marginal cost. Defer.

## Suggestions

### S-1 — `resolveSpouseAge` uses an inline relationship list

- **File:** `app/Services/Tax/TaxStrategyCalculator.php:~1217`
- **Category:** Inconsistency
- **What's wrong:** `whereIn('relationship', ['spouse', 'partner', 'wife', 'husband', 'civil_partner'])` is inlined. Anywhere else in the codebase that needs to find a spouse via `family_members` would re-list the same enum subset.
- **Suggested fix:** Promote to a class constant (`private const SPOUSE_RELATIONSHIPS = [...]`) when a second caller appears. Not worth extracting on first use.

### S-2 — NI piecewise calculation could be extracted

- **File:** `app/Services/Tax/TaxStrategyCalculator.php:~915-925`
- **Category:** Complexity
- **What's wrong:** The salary-sacrifice generator has an inline 3-branch piecewise NI saving calculation (entirely below UEL / entirely above UEL / mixed). It's correct and well-commented but adds visual weight. ~10 lines of cognitive load.
- **Suggested fix:** Extract to `private function employeeNiSavingFor(float $income, float $contribution): float` if the pattern recurs (e.g. when Phase 5 tapered AA needs NI calculations). Single use is fine inline.

### S-3 — `bed_and_isa` proceeds estimation lacks a comment on the gain-to-value ratio

- **File:** `app/Services/Tax/TaxStrategyCalculator.php:~990-993`
- **Category:** Complexity
- **What's wrong:** `proceeds = totalCurrentValueWithGain * (realisableGains / totalUnrealisedGain)` is the right scaling — sell enough of the gain-bearing stock to crystallise £AEA of gain — but a reader has to derive that. The early return guarantees `totalUnrealisedGain > 0` so the division is safe, but the safety isn't stated.
- **Suggested fix:** One-line comment: `// Scale proceeds by the share of total gain we're crystallising — guaranteed safe because we early-return when totalUnrealisedGain == 0.` Or accept that the surrounding lines already convey it.

---

## Notes on what was NOT flagged

- **Calculator file length** — now 1,252 lines after Phase 3, up from 988. CSJTODO **S-1** already tracks this with a deferral until start of Phase 3 or 4. Phase 3 has landed; Phase 4 is the natural moment to extract per-strategy classes (`App\Services\Tax\Strategies\IncomeBandStrategy` etc.). Not a new issue — already on the books.
- **Migration future-dating** — `2026_05_04_*` is two days ahead of today (2026-04-30). Existing pattern from sessions 117/118 (e.g. `2026_05_03_*`) — intentional sequencing, not a regression.
- **xAI/Anthropic tool parity** — verified by the architecture suite (`ToolCatalogueParityTest`). Both files extended in lockstep.
- **Unit-test file length** — 870 lines after additions. Long but well-structured by `describe()` blocks; splitting per-phase would not improve readability.

## Recommendation

**No critical issues — Phase 3 is safe to commit.** The two warnings extend existing CSJTODO tech-debt items rather than creating new ones, and would be best addressed in a single sweep when CSJTODO **W-1** / **W-2** / **S-1** / **S-3** are actioned together (~45 min total).

---
*Generated by tech-debt-session skill — session 119*
